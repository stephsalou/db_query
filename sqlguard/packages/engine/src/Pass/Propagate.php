<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlGuard\Engine\Domain\AccessPath;
use SqlGuard\Engine\Domain\Alert;
use SqlGuard\Engine\Domain\LimitRecorder;
use SqlGuard\Engine\Domain\Position;
use SqlGuard\Engine\Domain\PropagationChain;
use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Domain\SinkRef;
use SqlGuard\Engine\Domain\Taint;

/**
 * Propagation intra-procedurale (story 3.3) sur un CFG structure : les branches
 * sont analysees separement puis unies a la confluence (AD-4), les boucles par
 * point fixe borne.
 *
 * L'interprocedural (story 3.4) n'est PAS implemente : chaque appel vers un
 * symbole du perimetre produit une entree `unknown_callee` dans les limites
 * d'analyse (AD-15). L'outil dit ce qu'il n'a pas suivi plutot que de se taire.
 */
final class Propagate
{
    /** @var list<Alert> */
    private array $alerts = [];
    /** @var array<string,int> compteur d'ordinal par symbole+kind (AD-5) */
    private array $ordinals = [];

    public function __construct(
        private readonly RulePack $rulePack,
        private readonly LimitRecorder $limits,
    ) {}

    /** @return list<Alert> */
    public function alerts(): array { return $this->alerts; }

    /** @param list<Node> $ast */
    public function analyseFile(array $ast, string $canonicalFile): void
    {
        foreach ($this->collectSymbols($ast, $canonicalFile) as [$fqn, $stmts, $params]) {
            $state = new TaintState();
            // Les parametres d'un symbole sont d'origine inconnue, pas propres.
            foreach ($params as $p) {
                if ($p instanceof Node\Param && $p->var instanceof Expr\Variable && is_string($p->var->name)) {
                    $state->set(AccessPath::variable($p->var->name), Taint::unknown());
                }
            }
            $this->walk($stmts, $state, $fqn, $canonicalFile);
        }
    }

    /**
     * Decoupe le fichier en symboles analysables. Le code de premier niveau
     * porte le fqn `<file:chemin>` (AD-5).
     * @param list<Node> $nodes
     * @return list<array{0:string,1:list<Node>,2:list<Node\Param>}>
     */
    private function collectSymbols(array $nodes, string $file, string $ns = ''): array
    {
        $out = [];
        $topLevel = [];
        foreach ($nodes as $n) {
            if ($n instanceof Stmt\Namespace_) {
                $name = $n->name?->toString() ?? '';
                $out = [...$out, ...$this->collectSymbols($n->stmts ?? [], $file, $name)];
                continue;
            }
            if ($n instanceof Stmt\Function_) {
                $out[] = ['\\' . ($ns !== '' ? $ns . '\\' : '') . $n->name->toString(), $n->stmts ?? [], $n->params];
                continue;
            }
            if ($n instanceof Stmt\Class_ || $n instanceof Stmt\Trait_ || $n instanceof Stmt\Interface_) {
                $cls = '\\' . ($ns !== '' ? $ns . '\\' : '') . ($n->name?->toString() ?? 'anonymous');
                foreach ($n->stmts as $m) {
                    if ($m instanceof Stmt\ClassMethod) {
                        $out[] = [$cls . '::' . $m->name->toString(), $m->stmts ?? [], $m->params];
                    }
                }
                continue;
            }
            $topLevel[] = $n;
        }
        if ($topLevel !== []) {
            $out[] = ["<file:$file>", $topLevel, []];
        }
        return $out;
    }

    /** @param list<Node> $stmts */
    private function walk(array $stmts, TaintState $state, string $fqn, string $file): TaintState
    {
        foreach ($stmts as $stmt) {
            $state = $this->walkStmt($stmt, $state, $fqn, $file);
        }
        return $state;
    }

    private function walkStmt(Node $stmt, TaintState $state, string $fqn, string $file): TaintState
    {
        // --- affectation -----------------------------------------------------
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
            $this->inspectExpr($stmt->expr->expr, $state, $fqn, $file);
            $this->assign($stmt->expr->var, $stmt->expr->expr, $state, $fqn, $file);
            return $state;
        }
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\AssignOp\Concat) {
            $this->inspectExpr($stmt->expr->expr, $state, $fqn, $file);
            $left  = $this->pathOf($stmt->expr->var, $state, $file);
            $cur   = $left !== null ? $state->get($left) : Taint::clean();
            $added = $this->evaluate($stmt->expr->expr, $state, $fqn, $file);
            if ($left !== null) {
                $state->set($left, $cur->union($added), $this->pos($stmt, $file));
            }
            return $state;
        }
        if ($stmt instanceof Stmt\Expression) {
            $this->inspectExpr($stmt->expr, $state, $fqn, $file, discardedResult: true);
            return $state;
        }

        // --- confluence : branches analysees puis unies (AD-4) ----------------
        if ($stmt instanceof Stmt\If_) {
            $this->inspectExpr($stmt->cond, $state, $fqn, $file);
            $then = $this->walk($stmt->stmts, $state->copy(), $fqn, $file);
            $acc  = $then;
            foreach ($stmt->elseifs as $ei) {
                $acc = $acc->union($this->walk($ei->stmts, $state->copy(), $fqn, $file));
            }
            $acc = $acc->union($stmt->else !== null
                ? $this->walk($stmt->else->stmts, $state->copy(), $fqn, $file)
                : $state->copy());
            return $acc;
        }
        if ($stmt instanceof Stmt\Switch_) {
            $acc = $state->copy();
            foreach ($stmt->cases as $c) {
                $acc = $acc->union($this->walk($c->stmts, $state->copy(), $fqn, $file));
            }
            return $acc;
        }
        if ($stmt instanceof Stmt\TryCatch) {
            $acc = $this->walk($stmt->stmts, $state->copy(), $fqn, $file);
            foreach ($stmt->catches as $c) {
                $acc = $acc->union($this->walk($c->stmts, $state->copy(), $fqn, $file));
            }
            if ($stmt->finally !== null) {
                $acc = $this->walk($stmt->finally->stmts, $acc, $fqn, $file);
            }
            return $acc;
        }

        // --- boucles : point fixe borne --------------------------------------
        if ($stmt instanceof Stmt\Foreach_ || $stmt instanceof Stmt\While_
            || $stmt instanceof Stmt\For_ || $stmt instanceof Stmt\Do_) {
            if ($stmt instanceof Stmt\Foreach_) {
                $src = $this->evaluate($stmt->expr, $state, $fqn, $file);
                if ($stmt->valueVar instanceof Expr\Variable && is_string($stmt->valueVar->name)) {
                    $state->set(AccessPath::variable($stmt->valueVar->name), $src, $this->pos($stmt, $file));
                }
            }
            $body = $stmt->stmts ?? [];
            $cur = $state->copy();
            for ($i = 0; $i < 3; $i++) {
                $before = $cur->signature();
                $cur = $cur->union($this->walk($body, $cur->copy(), $fqn, $file));
                if ($cur->signature() === $before) {
                    return $cur;
                }
            }
            $this->limits->record(
                LimitRecorder::LOOP_NOT_CONVERGED,
                'point fixe non atteint en 3 iterations',
                $this->pos($stmt, $file),
            );
            return $cur;
        }

        // --- conteneurs de statements ----------------------------------------
        if ($stmt instanceof Stmt\Block) {
            return $this->walk($stmt->stmts, $state, $fqn, $file);
        }
        if ($stmt instanceof Stmt\Return_ && $stmt->expr !== null) {
            $this->inspectExpr($stmt->expr, $state, $fqn, $file);
            // La valeur sort du symbole : l'evaluer consigne les appels non
            // suivis, sinon un `return mystere($_GET[...])` passerait pour une
            // analyse complete alors qu'elle ne l'est pas (NFR-6).
            $this->evaluate($stmt->expr, $state, $fqn, $file);
            return $state;
        }
        if ($stmt instanceof Stmt\Echo_) {
            foreach ($stmt->exprs as $e) { $this->inspectExpr($e, $state, $fqn, $file); }
            return $state;
        }
        return $state;
    }

    private function assign(Expr $target, Expr $value, TaintState $state, string $fqn, string $file): void
    {
        $path = $this->pathOf($target, $state, $file);
        if ($path === null) {
            return;
        }
        $state->set($path, $this->evaluate($value, $state, $fqn, $file), $this->pos($value, $file));
    }

    /** Chemin d'acces d'une expression affectable, ou null si non modelisable. */
    private function pathOf(Expr $e, TaintState $state, string $file): ?AccessPath
    {
        if ($e instanceof Expr\Variable && is_string($e->name)) {
            return AccessPath::variable($e->name);
        }
        if ($e instanceof Expr\ArrayDimFetch) {
            $segs = [];
            $cur = $e;
            while ($cur instanceof Expr\ArrayDimFetch) {
                if ($cur->dim instanceof Node\Scalar\String_) {
                    $segs[] = $cur->dim->value;
                } elseif ($cur->dim instanceof Node\Scalar\Int_) {
                    $segs[] = (string) $cur->dim->value;
                } else {
                    // cle non litterale : le conteneur entier devient agrege (AD-4)
                    $root = $this->rootName($cur);
                    if ($root !== null) {
                        $this->limits->record(
                            LimitRecorder::NON_LITERAL_KEY,
                            "cle non litterale sur \$$root",
                            $this->pos($cur, $file),
                        );
                        return AccessPath::aggregate($root);
                    }
                    return null;
                }
                $cur = $cur->var;
            }
            if ($cur instanceof Expr\Variable && is_string($cur->name)) {
                $segs = array_reverse($segs);
                if (count($segs) > AccessPath::MAX_DEPTH) {
                    $this->limits->record(
                        LimitRecorder::DEPTH_EXCEEDED,
                        "profondeur > " . AccessPath::MAX_DEPTH . " sur \${$cur->name}",
                        $this->pos($e, $file),
                    );
                    return AccessPath::aggregate($cur->name);
                }
                return AccessPath::of($cur->name, $segs);
            }
        }
        if ($e instanceof Expr\PropertyFetch && $e->var instanceof Expr\Variable
            && is_string($e->var->name) && $e->name instanceof Node\Identifier) {
            return AccessPath::of($e->var->name, [$e->name->toString()]);
        }
        return null;
    }

    private function rootName(Expr $e): ?string
    {
        while ($e instanceof Expr\ArrayDimFetch) { $e = $e->var; }
        return ($e instanceof Expr\Variable && is_string($e->name)) ? $e->name : null;
    }

    /** Evalue la marque d'une expression. */
    private function evaluate(Expr $e, TaintState $state, string $fqn, string $file): Taint
    {
        // superglobale non fiable
        if ($e instanceof Expr\ArrayDimFetch) {
            $root = $this->rootName($e);
            if ($root !== null && in_array($root, Vocabulary::SOURCE_GLOBALS, true)) {
                return Taint::tainted('$' . $root);
            }
            if ($root === '_SERVER') {
                // Cle litterale : seules les cles controlables par le client
                // sont non fiables. Cle non litterale : prudence, on marque et
                // on consigne l'imprecision.
                if ($e->dim instanceof Node\Scalar\String_) {
                    return Vocabulary::isServerKeyControllable($e->dim->value)
                        ? Taint::tainted('$_SERVER[' . $e->dim->value . ']')
                        : Taint::clean();
                }
                $this->limits->record(
                    LimitRecorder::NON_LITERAL_KEY,
                    'cle non litterale sur $_SERVER : marquee par prudence',
                    $this->pos($e, $file),
                );
                return Taint::tainted('$_SERVER');
            }
        }
        if ($e instanceof Expr\Variable && is_string($e->name)
            && in_array($e->name, Vocabulary::SOURCE_GLOBALS, true)) {
            return Taint::tainted('$' . $e->name);
        }

        // litteraux : propres
        if ($e instanceof Node\Scalar\String_ || $e instanceof Node\Scalar\Int_
            || $e instanceof Node\Scalar\Float_) {
            return Taint::clean();
        }

        // cast sur type sur : rompt la propagation
        if ($e instanceof Expr\Cast) {
            // Les casts numeriques et booleens rendent la valeur inoffensive pour
            // du SQL. Test par instanceof : getShortName() rend `Int_`, pas `int`.
            if ($e instanceof Expr\Cast\Int_)    { return Taint::sanitized('cast-int'); }
            if ($e instanceof Expr\Cast\Double)  { return Taint::sanitized('cast-float'); }
            if ($e instanceof Expr\Cast\Bool_)   { return Taint::sanitized('cast-bool'); }
            // (string), (array), (object) ne protegent rien : la marque passe.
            return $this->evaluate($e->expr, $state, $fqn, $file);
        }

        // concatenation et interpolation : union des parties
        if ($e instanceof Expr\BinaryOp\Concat) {
            return $this->evaluate($e->left, $state, $fqn, $file)
                ->union($this->evaluate($e->right, $state, $fqn, $file));
        }
        if ($e instanceof Node\Scalar\InterpolatedString) {
            $t = Taint::clean();
            foreach ($e->parts as $part) {
                if ($part instanceof Expr) {
                    $t = $t->union($this->evaluate($part, $state, $fqn, $file));
                }
            }
            return $t;
        }

        // appel de fonction
        if ($e instanceof Expr\FuncCall) {
            if ($e->name instanceof Node\Name) {
                $fn = strtolower($e->name->toString());
                if (isset(Vocabulary::SANITIZERS[$fn])) {
                    // Assainisseur : rompt la propagation UNIQUEMENT parce que sa
                    // valeur de retour est ici consommee.
                    return Taint::sanitized(Vocabulary::SANITIZERS[$fn]);
                }
                if (isset(Vocabulary::WEAK_ESCAPERS[$fn])) {
                    // Echappeur insuffisant pour du SQL : la marque PASSE.
                    $t = Taint::clean();
                    foreach ($e->args as $a) {
                        if ($a instanceof Node\Arg) { $t = $t->union($this->evaluate($a->value, $state, $fqn, $file)); }
                    }
                    return $t;
                }
                if (in_array($fn, ['sprintf', 'implode', 'join', 'str_replace', 'trim', 'strtolower', 'strtoupper'], true)) {
                    $t = Taint::clean();
                    foreach ($e->args as $a) {
                        if ($a instanceof Node\Arg) { $t = $t->union($this->evaluate($a->value, $state, $fqn, $file)); }
                    }
                    return $t;
                }
                $this->limits->record(
                    LimitRecorder::UNKNOWN_CALLEE,
                    "appel non suivi : $fn()",
                    $this->pos($e, $file),
                );
                return Taint::unknown();
            }
            $this->limits->record(LimitRecorder::DYNAMIC_CALL, 'appel dynamique', $this->pos($e, $file));
            return Taint::unknown();
        }

        // appel de methode
        if ($e instanceof Expr\MethodCall && $e->name instanceof Node\Identifier) {
            $m = strtolower($e->name->toString());
            if (isset(Vocabulary::SANITIZER_METHODS[$m])) {
                return Taint::sanitized(Vocabulary::SANITIZER_METHODS[$m]);
            }
            if (in_array($m, Vocabulary::KNOWN_DB_METHODS, true)
                || isset(Vocabulary::SINK_METHODS[$m])) {
                // Methode base connue : le resultat n'est pas une valeur SQL.
                return Taint::clean();
            }
            $this->limits->record(
                LimitRecorder::UNKNOWN_CALLEE,
                "methode non suivie : ->$m()",
                $this->pos($e, $file),
            );
            return Taint::unknown();
        }

        // chemin d'acces connu
        $path = $this->pathOf($e, $state, $file);
        if ($path !== null) {
            return $state->get($path);
        }

        if ($e instanceof Expr\Ternary) {
            $a = $e->if !== null ? $this->evaluate($e->if, $state, $fqn, $file) : Taint::clean();
            return $a->union($this->evaluate($e->else, $state, $fqn, $file));
        }
        if ($e instanceof Expr\BinaryOp\Coalesce) {
            return $this->evaluate($e->left, $state, $fqn, $file)
                ->union($this->evaluate($e->right, $state, $fqn, $file));
        }
        return Taint::clean();
    }

    /**
     * Cherche les sinks dans une expression, et les assainisseurs dont la valeur
     * de retour est jetee (regle `sanitizer-noop` — le defaut historique de ce
     * depot, voir docs/provenance.md).
     */
    private function inspectExpr(
        Expr $e,
        TaintState $state,
        string $fqn,
        string $file,
        bool $discardedResult = false,
    ): void {
        // assainisseur dont le retour est jete
        if ($discardedResult && $e instanceof Expr\FuncCall && $e->name instanceof Node\Name) {
            $fn = strtolower($e->name->toString());
            $isEscaper = isset(Vocabulary::SANITIZERS[$fn]) || isset(Vocabulary::WEAK_ESCAPERS[$fn]);
            if ($isEscaper && $this->rulePack->has('sanitizer-noop')) {
                $this->emit('sanitizer-noop', 'sanitizer.discarded', $fqn, $file, $this->pos($e, $file), [
                    ['label' => "valeur de retour de $fn() jetee", 'position' => $this->pos($e, $file)],
                ]);
            }
        }

        if ($e instanceof Expr\MethodCall && $e->name instanceof Node\Identifier) {
            $m = strtolower($e->name->toString());
            if (isset(Vocabulary::SINK_METHODS[$m]) && isset($e->args[0])
                && $e->args[0] instanceof Node\Arg) {
                $arg = $e->args[0]->value;
                $t = $this->evaluate($arg, $state, $fqn, $file);
                if ($t->isTainted() && $this->rulePack->has('sql-injection-concat-pdo')) {
                    $steps = [];
                    foreach ($t->sources as $src) {
                        $steps[] = ['label' => "source non fiable $src", 'position' => $this->pos($arg, $file)];
                    }
                    $steps[] = [
                        'label' => 'atteint ' . Vocabulary::SINK_METHODS[$m] . ' par concatenation',
                        'position' => $this->pos($e, $file),
                    ];
                    $this->emit(
                        'sql-injection-concat-pdo',
                        Vocabulary::SINK_METHODS[$m],
                        $fqn, $file, $this->pos($e, $file), $steps,
                    );
                }
            }
        }

        // descente recursive
        foreach ($e->getSubNodeNames() as $name) {
            $sub = $e->{$name};
            foreach (is_array($sub) ? $sub : [$sub] as $child) {
                if ($child instanceof Node\Arg) { $child = $child->value; }
                if ($child instanceof Expr) {
                    $this->inspectExpr($child, $state, $fqn, $file);
                }
            }
        }
    }

    /** @param list<array{label:string,position:Position}> $steps */
    private function emit(
        string $ruleId,
        string $sinkKind,
        string $fqn,
        string $file,
        Position $pos,
        array $steps,
    ): void {
        $key = $fqn . '|' . $sinkKind;
        $ordinal = $this->ordinals[$key] ?? 0;
        $this->ordinals[$key] = $ordinal + 1;

        $witness = new PropagationChain($steps);
        // Invariant NFR-3 / FR-7 : aucune alerte sans temoin.
        if ($witness->isEmpty()) {
            return;
        }
        $this->alerts[] = new Alert(
            Alert::computeId($ruleId, $this->rulePack->id, $this->rulePack->major(), $file, $fqn, $sinkKind, $ordinal),
            new SinkRef($ruleId, $fqn, $sinkKind, $ordinal),
            $pos,
            $this->rulePack->id,
            $this->rulePack->major(),
            $witness,
        );
    }

    private function pos(Node $n, string $file): Position
    {
        return new Position($file, $n->getStartLine(), 1);
    }
}

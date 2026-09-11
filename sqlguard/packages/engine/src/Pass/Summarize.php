<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlGuard\Engine\Domain\AccessPath;
use SqlGuard\Engine\Domain\FunctionSummary;
use SqlGuard\Engine\Domain\LimitRecorder;
use SqlGuard\Engine\Domain\Position;
use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Domain\Taint;

/**
 * Calcule un FunctionSummary par symbole (AD-6).
 *
 * Ordre : SCC condensees par Tarjan, parcourues en ordre topologique inverse.
 * A l'interieur d'une SCC, fixpoint ascendant borne a MAX_ITERATIONS ; au-dela,
 * widening vers `unresolved` et une entree de limites — jamais une boucle
 * infinie sur recursion mutuelle.
 */
final class Summarize
{
    public const MAX_ITERATIONS = 8;

    /** @var array<string,FunctionSummary> */
    private array $summaries = [];
    /** @var array<string,true> proprietes ecrites avec une valeur marquee, par classe::prop */
    private array $taintedProperties = [];

    public function __construct(
        private readonly SymbolTable $symbols,
        private readonly RulePack $rulePack,
        private readonly LimitRecorder $limits,
    ) {}

    /** @return array<string,FunctionSummary> */
    public function run(): array
    {
        // side_taints (AD-6) : ecritures de proprietes avec une valeur marquee.
        // Calcul insensible au flux entre methodes — une sur-approximation
        // assumee : c'est le motif dominant du legacy (`$this->sql = ...` dans
        // une methode, execute dans une autre) et l'ignorer produisait un
        // manque silencieux.
        $this->collectTaintedProperties();

        $graph = $this->buildCallGraph();
        foreach ($graph->stronglyConnectedComponents() as $scc) {
            $this->solveComponent($scc);
        }
        return $this->summaries;
    }

    /** @return array<string,true> */
    public function taintedProperties(): array { return $this->taintedProperties; }

    private function collectTaintedProperties(): void
    {
        foreach ($this->symbols->fqns() as $fqn) {
            if (!str_contains($fqn, '::')) { continue; }
            $class = substr($fqn, 0, strpos($fqn, '::'));
            $this->scanPropertyWrites($this->symbols->get($fqn)['stmts'], $class);
        }
    }

    /** @param list<Node>|Node|null $n */
    private function scanPropertyWrites($n, string $class): void
    {
        if ($n === null) { return; }
        if (is_array($n)) {
            foreach ($n as $c) { $this->scanPropertyWrites($c, $class); }
            return;
        }
        if (!$n instanceof Node) { return; }

        $assign = null;
        if ($n instanceof Expr\Assign) { $assign = $n; }
        if ($n instanceof Expr\AssignOp\Concat) { $assign = $n; }
        if ($assign !== null
            && $assign->var instanceof Expr\PropertyFetch
            && $assign->var->name instanceof Node\Identifier
            && $this->expressionLooksTainted($assign->expr)) {
            $this->taintedProperties[$class . '::' . $assign->var->name->toString()] = true;
        }
        foreach ($n->getSubNodeNames() as $name) {
            $this->scanPropertyWrites($n->{$name}, $class);
        }
    }

    /** Heuristique volontairement large : une superglobale apparait-elle ? */
    private function expressionLooksTainted($e): bool
    {
        if (!$e instanceof Node) { return false; }
        if ($e instanceof Expr\ArrayDimFetch) {
            $r = $e->var;
            while ($r instanceof Expr\ArrayDimFetch) { $r = $r->var; }
            if ($r instanceof Expr\Variable && is_string($r->name)
                && (in_array($r->name, Vocabulary::SOURCE_GLOBALS, true) || $r->name === '_SERVER')) {
                return true;
            }
        }
        foreach ($e->getSubNodeNames() as $name) {
            $sub = $e->{$name};
            foreach (is_array($sub) ? $sub : [$sub] as $c) {
                if ($c instanceof Node\Arg) { $c = $c->value; }
                if ($this->expressionLooksTainted($c)) { return true; }
            }
        }
        return false;
    }

    private function buildCallGraph(): CallGraph
    {
        $g = new CallGraph();
        foreach ($this->symbols->fqns() as $fqn) {
            $g->addNode($fqn);
            foreach ($this->calleesOf($this->symbols->get($fqn)['stmts']) as $callee) {
                $g->addEdge($fqn, $callee);
            }
        }
        return $g;
    }

    /** @param list<Node>|Node|null $n @return list<string> */
    private function calleesOf($n, array $acc = []): array
    {
        if ($n === null) { return $acc; }
        if (is_array($n)) {
            foreach ($n as $c) { $acc = $this->calleesOf($c, $acc); }
            return $acc;
        }
        if (!$n instanceof Node) { return $acc; }

        if ($n instanceof Expr\FuncCall && $n->name instanceof Node\Name) {
            $r = $this->symbols->resolveFunction($n->name->toString());
            if ($r !== null) { $acc[] = $r; }
        }
        if ($n instanceof Expr\MethodCall && $n->name instanceof Node\Identifier) {
            $r = $this->symbols->resolveMethod($n->name->toString());
            if ($r !== null) { $acc[] = $r; }
        }
        if ($n instanceof Expr\StaticCall && $n->name instanceof Node\Identifier
            && $n->class instanceof Node\Name) {
            $r = '\\' . ltrim($n->class->toString(), '\\') . '::' . $n->name->toString();
            if ($this->symbols->has($r)) { $acc[] = $r; }
        }
        if ($n instanceof Expr\FuncCall && $n->name instanceof Node\Name
            && in_array(strtolower($n->name->toString()), ['call_user_func', 'call_user_func_array'], true)
            && isset($n->args[0]) && $n->args[0] instanceof Node\Arg
            && $n->args[0]->value instanceof Node\Scalar\String_) {
            $r = $this->symbols->resolveFunction($n->args[0]->value->value);
            if ($r !== null) { $acc[] = $r; }
        }
        foreach ($n->getSubNodeNames() as $name) {
            $acc = $this->calleesOf($n->{$name}, $acc);
        }
        return $acc;
    }

    /** @param list<string> $scc */
    private function solveComponent(array $scc): void
    {
        // Amorce : resume vide, pour que les appels internes a la SCC aient
        // quelque chose a lire des la premiere iteration.
        foreach ($scc as $fqn) {
            $this->summaries[$fqn] ??= FunctionSummary::empty($fqn);
        }

        $recursive = count($scc) > 1
            || $this->calleesOf($this->symbols->get($scc[0])['stmts']) !== []
                && in_array($scc[0], $this->calleesOf($this->symbols->get($scc[0])['stmts']), true);
        $maxIter = $recursive ? self::MAX_ITERATIONS : 1;

        for ($iter = 0; $iter < $maxIter; $iter++) {
            $before = [];
            foreach ($scc as $fqn) { $before[$fqn] = $this->summaries[$fqn]->signature(); }

            foreach ($scc as $fqn) {
                $this->summaries[$fqn] = $this->summarise($fqn);
            }

            $stable = true;
            foreach ($scc as $fqn) {
                if ($this->summaries[$fqn]->signature() !== $before[$fqn]) { $stable = false; break; }
            }
            if ($stable) { return; }
        }

        if ($maxIter === self::MAX_ITERATIONS) {
            // Widening : on ne boucle pas indefiniment, on le declare.
            foreach ($scc as $fqn) {
                $s = $this->summaries[$fqn];
                $this->summaries[$fqn] = new FunctionSummary(
                    $s->symbolFqn, $s->params, $s->returnsTaintedUnconditionally,
                    $s->sideTaints, [...$s->unresolved, LimitRecorder::LOOP_NOT_CONVERGED],
                );
            }
            $this->limits->record(
                LimitRecorder::LOOP_NOT_CONVERGED,
                'fixpoint interprocedural non atteint en ' . self::MAX_ITERATIONS
                    . ' iterations sur la composante : ' . implode(', ', $scc),
                new Position($this->symbols->get($scc[0])['file'], 1),
            );
        }
    }

    /**
     * Un resume par parametre : on marque le parametre i, on analyse le corps,
     * et on observe ce que la marque atteint.
     */
    private function summarise(string $fqn): FunctionSummary
    {
        $sym = $this->symbols->get($fqn);
        $params = [];
        $returnsUnconditionally = false;

        foreach ($sym['params'] as $i => $p) {
            if (!$p instanceof Node\Param || !$p->var instanceof Expr\Variable || !is_string($p->var->name)) {
                continue;
            }
            $name = $p->var->name;
            // Registre jetable : une sonde est relancee une fois PAR PARAMETRE,
            // donc ecrire dans le registre rendu a l'utilisateur y multipliait
            // chaque limite par le nombre de parametres. Rien n'est perdu : la
            // passe finale parcourt les memes symboles et consigne une fois.
            $probe = new Propagate($this->rulePack, new LimitRecorder(), $this->symbols);
            $probe->withSummaries($this->summaries);

            $state = new TaintState();
            // Les autres parametres restent inconnus : on isole la contribution
            // du parametre i.
            foreach ($sym['params'] as $j => $q) {
                if ($q instanceof Node\Param && $q->var instanceof Expr\Variable && is_string($q->var->name)) {
                    $state->set(
                        AccessPath::variable($q->var->name),
                        $j === $i ? Taint::tainted('param:' . $name) : Taint::unknown(),
                    );
                }
            }
            $out = $probe->collectFor($fqn, $sym['stmts'], $sym['file'], $state);
            $params[$i] = [
                'name'           => $name,
                'reaches_return' => $out['reaches_return'],
                'reaches_sink'   => $out['sinks'],
                'sanitized_by'   => [],
                'hop_template'   => [$fqn . '#' . $i],
            ];
        }

        return new FunctionSummary($fqn, $params, $returnsUnconditionally);
    }
}

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

    public function __construct(
        private readonly SymbolTable $symbols,
        private readonly RulePack $rulePack,
        private readonly LimitRecorder $limits,
    ) {}

    /** @return array<string,FunctionSummary> */
    public function run(): array
    {
        $graph = $this->buildCallGraph();
        foreach ($graph->stronglyConnectedComponents() as $scc) {
            $this->solveComponent($scc);
        }
        return $this->summaries;
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
            $probe = new Propagate($this->rulePack, $this->limits, $this->symbols);
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

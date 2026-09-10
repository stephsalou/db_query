<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

/**
 * Graphe d'appel condense en composantes fortement connexes (Tarjan) et rendu
 * en ORDRE TOPOLOGIQUE INVERSE (AD-6) : les appeles avant les appelants, pour
 * qu'un resume soit disponible quand on analyse celui qui l'appelle.
 *
 * La condensation est ce qui garantit la terminaison sur recursion mutuelle.
 */
final class CallGraph
{
    /** @var array<string,list<string>> */
    private array $edges = [];

    public function addNode(string $fqn): void
    {
        $this->edges[$fqn] ??= [];
    }

    public function addEdge(string $from, string $to): void
    {
        $this->addNode($from);
        $this->addNode($to);
        if (!in_array($to, $this->edges[$from], true)) {
            $this->edges[$from][] = $to;
        }
    }

    /**
     * Tarjan. Renvoie les SCC dans l'ordre ou elles sortent de l'algorithme,
     * qui est deja l'ordre topologique inverse de la condensation.
     * @return list<list<string>>
     */
    public function stronglyConnectedComponents(): array
    {
        $index = 0;
        $indices = [];
        $low = [];
        $onStack = [];
        $stack = [];
        $out = [];

        $nodes = array_keys($this->edges);
        sort($nodes, SORT_STRING); // determinisme (NFR-1)

        // Tarjan iteratif : la recursion exploserait sur un gros depot.
        foreach ($nodes as $root) {
            if (isset($indices[$root])) { continue; }
            /** @var list<array{0:string,1:int}> $work */
            $work = [[$root, 0]];
            while ($work !== []) {
                [$v, $pi] = $work[count($work) - 1];
                if ($pi === 0) {
                    $indices[$v] = $index;
                    $low[$v] = $index;
                    $index++;
                    $stack[] = $v;
                    $onStack[$v] = true;
                }
                $recursed = false;
                $succ = $this->edges[$v] ?? [];
                for ($i = $pi; $i < count($succ); $i++) {
                    $w = $succ[$i];
                    if (!isset($indices[$w])) {
                        $work[count($work) - 1] = [$v, $i + 1];
                        $work[] = [$w, 0];
                        $recursed = true;
                        break;
                    }
                    if ($onStack[$w] ?? false) {
                        $low[$v] = min($low[$v], $indices[$w]);
                    }
                }
                if ($recursed) { continue; }

                if ($low[$v] === $indices[$v]) {
                    $comp = [];
                    do {
                        $w = array_pop($stack);
                        $onStack[$w] = false;
                        $comp[] = $w;
                    } while ($w !== $v);
                    sort($comp, SORT_STRING);
                    $out[] = $comp;
                }
                array_pop($work);
                if ($work !== []) {
                    $parent = $work[count($work) - 1][0];
                    $low[$parent] = min($low[$parent], $low[$v]);
                }
            }
        }
        return $out;
    }
}

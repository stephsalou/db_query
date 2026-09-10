<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Ordinaux de sink precalcules par PARCOURS PREFIXE de l'AST (AD-5).
 *
 * Les compter au fil de l'analyse de flux serait faux : les branches sont
 * parcourues separement puis unies, donc un meme sink pourrait etre vu deux
 * fois et l'ordinal dependrait du chemin — ce qui casserait l'identite d'alerte
 * (AD-9) et le determinisme (NFR-1).
 */
final class SinkIndex
{
    /** @var array<int,int> spl_object_id du noeud -> ordinal */
    private array $ordinals = [];

    /** @param list<Node> $stmts */
    public function __construct(array $stmts)
    {
        $counters = [];
        $this->walk($stmts, $counters);
    }

    /** @param list<Node>|Node|null $n @param array<string,int> $counters */
    private function walk($n, array &$counters): void
    {
        if ($n === null) { return; }
        if (is_array($n)) {
            foreach ($n as $c) { $this->walk($c, $counters); }
            return;
        }
        if (!$n instanceof Node) { return; }

        if ($n instanceof Expr\MethodCall && $n->name instanceof Node\Identifier) {
            $kind = Vocabulary::SINK_METHODS[strtolower($n->name->toString())] ?? null;
            if ($kind !== null) {
                $counters[$kind] ??= 0;
                $this->ordinals[spl_object_id($n)] = $counters[$kind]++;
            }
        }
        if ($n instanceof Expr\FuncCall && $n->name instanceof Node\Name) {
            $fn = strtolower($n->name->toString());
            if (isset(Vocabulary::SANITIZERS[$fn]) || isset(Vocabulary::WEAK_ESCAPERS[$fn])) {
                $counters['sanitizer.discarded'] ??= 0;
                $this->ordinals[spl_object_id($n)] = $counters['sanitizer.discarded']++;
            }
        }

        foreach ($n->getSubNodeNames() as $name) {
            $this->walk($n->{$name}, $counters);
        }
    }

    public function ordinalOf(Node $n): int
    {
        return $this->ordinals[spl_object_id($n)] ?? 0;
    }
}

<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Identite d'un point de sink, independante de la ligne (AD-5).
 * `ordinal` est l'index 0-based du n-ieme sink du meme kind dans ce symbole,
 * en parcours prefixe deterministe de l'AST.
 */
final class SinkRef
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $symbolFqn,
        public readonly string $sinkKind,
        public readonly int $ordinal,
    ) {}
}

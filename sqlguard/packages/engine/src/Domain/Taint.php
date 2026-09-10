<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Treillis de marque (AD-4) : Clean < Sanitized(context) < Tainted(sourceRef),
 * avec Unknown comme element au-dessus de Clean mais non comparable a Tainted.
 *
 * L'union aux confluences de flux prend le plus haut des deux.
 */
final class Taint
{
    public const CLEAN     = 0;
    public const SANITIZED = 1;
    public const UNKNOWN   = 2;
    public const TAINTED   = 3;

    private function __construct(
        public readonly int $level,
        /** @var list<string> references de sources, pour le temoin */
        public readonly array $sources = [],
        public readonly ?string $sanitizerContext = null,
    ) {}

    public static function clean(): self { return new self(self::CLEAN); }
    public static function unknown(): self { return new self(self::UNKNOWN); }

    public static function tainted(string $sourceRef): self
    {
        return new self(self::TAINTED, [$sourceRef]);
    }

    public static function sanitized(string $context): self
    {
        return new self(self::SANITIZED, [], $context);
    }

    public function isTainted(): bool { return $this->level === self::TAINTED; }

    /** Union au point de confluence : le plus haut niveau gagne, sources fusionnees. */
    public function union(self $other): self
    {
        if ($this->level === $other->level && $this->level === self::TAINTED) {
            $merged = array_values(array_unique([...$this->sources, ...$other->sources]));
            return new self(self::TAINTED, $merged);
        }
        return $this->level >= $other->level ? $this : $other;
    }
}

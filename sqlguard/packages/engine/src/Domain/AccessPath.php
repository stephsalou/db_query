<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Chemin d'acces (AD-4) : `variable[.prop|[cleLitterale]]*`, profondeur max 2.
 * Au-dela, ou sur cle non litterale, le conteneur entier devient une cellule
 * agregee et l'imprecision est consignee dans les limites d'analyse.
 */
final class AccessPath
{
    public const MAX_DEPTH = 2;

    private function __construct(
        public readonly string $root,
        /** @var list<string> */
        public readonly array $segments,
        public readonly bool $aggregated,
    ) {}

    public static function variable(string $name): self
    {
        return new self($name, [], false);
    }

    /** @param list<string> $segments */
    public static function of(string $root, array $segments): self
    {
        if (count($segments) > self::MAX_DEPTH) {
            return new self($root, [], true);
        }
        return new self($root, $segments, false);
    }

    /** Conteneur entier, quand la cle n'est pas litterale. */
    public static function aggregate(string $root): self
    {
        return new self($root, [], true);
    }

    public function key(): string
    {
        $k = '$' . $this->root;
        foreach ($this->segments as $s) {
            $k .= '[' . $s . ']';
        }
        return $this->aggregated ? $k . '[*]' : $k;
    }

    /** Le conteneur agrege couvre tous ses sous-chemins. */
    public function covers(self $other): bool
    {
        return $this->aggregated && $this->root === $other->root;
    }
}

<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Temoin de niveau 1 (FR-5) : la chaine source -> sink, reproductible.
 * Ne contient JAMAIS de charge utile armee — cela releve du niveau 2, cloisonne
 * hors du contrat S-2 (AD-17).
 */
final class PropagationChain
{
    /** @param list<array{label:string,position:Position}> $steps */
    public function __construct(public readonly array $steps) {}

    public function isEmpty(): bool { return $this->steps === []; }

    /** @return list<array{label:string,file:string,line:int,column:int}> */
    public function toArray(): array
    {
        return array_map(
            fn(array $s): array => ['label' => $s['label']] + $s['position']->toArray(),
            $this->steps,
        );
    }
}

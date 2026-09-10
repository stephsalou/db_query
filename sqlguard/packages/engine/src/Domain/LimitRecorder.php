<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Registre unique des limites d'analyse (AD-15), en AJOUT SEUL.
 *
 * Une seule instance circule dans le pipeline. Aucune passe ne lit ni ne filtre
 * les limites d'une autre ; seul Judge les consomme. Toute construction non
 * suivie produit exactement une entree — jamais une alerte silencieuse.
 */
final class LimitRecorder
{
    /** Enumeration fermee : ajouter un code exige un schema_version mineur. */
    public const DYNAMIC_CALL       = 'dynamic_call';
    public const NON_LITERAL_KEY    = 'non_literal_key';
    public const DEPTH_EXCEEDED     = 'depth_exceeded';
    public const UNRESOLVED_INCLUDE = 'unresolved_include';
    public const PARSE_ERROR        = 'parse_error';
    public const LOOP_NOT_CONVERGED = 'loop_not_converged';
    public const UNKNOWN_CALLEE     = 'unknown_callee';
    public const REFLECTION         = 'reflection';

    private const CODES = [
        self::DYNAMIC_CALL, self::NON_LITERAL_KEY, self::DEPTH_EXCEEDED,
        self::UNRESOLVED_INCLUDE, self::PARSE_ERROR, self::LOOP_NOT_CONVERGED,
        self::UNKNOWN_CALLEE, self::REFLECTION,
    ];

    /** @var list<array{reason_code:string,detail:string,position:Position}> */
    private array $entries = [];

    public function record(string $reasonCode, string $detail, Position $position): void
    {
        if (!in_array($reasonCode, self::CODES, true)) {
            throw new \InvalidArgumentException("code de limite hors enumeration : $reasonCode");
        }
        $this->entries[] = ['reason_code' => $reasonCode, 'detail' => $detail, 'position' => $position];
    }

    public function count(): int { return count($this->entries); }
    public function isEmpty(): bool { return $this->entries === []; }

    /** @return list<array{reason_code:string,detail:string,file:string,line:int,column:int}> */
    public function toArray(): array
    {
        return array_map(
            fn(array $e): array => ['reason_code' => $e['reason_code'], 'detail' => $e['detail']]
                + $e['position']->toArray(),
            $this->entries,
        );
    }
}

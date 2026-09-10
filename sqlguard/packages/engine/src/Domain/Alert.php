<?php declare(strict_types=1);

namespace SqlGuard\Engine\Domain;

/**
 * Une alerte. Son identite est un hash structurel (AD-9) : elle survit au
 * reformatage et au deplacement de lignes, sinon les suppressions des
 * utilisateurs seraient invalidees en silence.
 *
 * Un seul calculateur d'identite existe (AD-10) : `computeId()` ci-dessous.
 */
final class Alert
{
    public const UNIT_SEPARATOR = "\x1F";

    public function __construct(
        public readonly string $id,
        public readonly SinkRef $sinkRef,
        public readonly Position $position,
        public readonly string $rulePackId,
        public readonly int $rulePackMajor,
        /** Chaine de propagation : temoin de niveau 1, obligatoire (FR-7, NFR-3). */
        public readonly PropagationChain $witness,
    ) {}

    /**
     * Les SEPT champs d'AD-9, dans cet ordre exact. N'entrent pas dans le
     * payload : ligne, colonne, extrait de code, blancs, noms de variables.
     */
    public static function computeId(
        string $ruleId,
        string $rulePackId,
        int $rulePackMajor,
        string $canonicalFile,
        string $symbolFqn,
        string $sinkKind,
        int $ordinal,
    ): string {
        $payload = implode(self::UNIT_SEPARATOR, [
            $ruleId,
            $rulePackId,
            (string) $rulePackMajor,
            $canonicalFile,
            $symbolFqn,
            $sinkKind,
            (string) $ordinal,
        ]);
        return 'SG1-' . substr(hash('sha256', $payload), 0, 16);
    }
}

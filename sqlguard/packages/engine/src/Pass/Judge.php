<?php declare(strict_types=1);

namespace SqlGuard\Engine\Pass;

use SqlGuard\Engine\Contract\Report;

/**
 * Unique proprietaire du verdict et du seuil (AD-14). Seul Judge consomme les
 * limites d'analyse (AD-15).
 *
 * Codes de sortie : 0 propre, 1 alertes au-dessus du seuil, 2 analyse
 * incomplete sans alerte — echec explicite plutot que silence (NFR-6).
 */
final class Judge
{
    public const EXIT_CLEAN      = 0;
    public const EXIT_ALERTS     = 1;
    public const EXIT_INCOMPLETE = 2;
    public const EXIT_ERROR      = 3;

    public function verdict(Report $report, int $maxLevel = 5): int
    {
        // Seules les alertes absentes de la reference font echouer (story 4.2).
        if ($report->newAlerts() !== []) {
            return self::EXIT_ALERTS;
        }
        return $report->isComplete() ? self::EXIT_CLEAN : self::EXIT_INCOMPLETE;
    }
}

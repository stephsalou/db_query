<?php declare(strict_types=1);

namespace SqlGuard\Bench;

use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Infra\LocalFileSource;
use SqlGuard\Engine\Scanner;

/**
 * Harnais qualite (FR-13, NFR-2). Apparie les alertes aux attendus sur
 * l'IDENTITE du sink — rule_id + symbol_fqn + sink_kind + ordinal (AD-21) —
 * jamais sur le numero de ligne ni sur l'interieur de la chaine de propagation.
 *
 * Le corpus est ancre et non positionnel (AD-20) : reformater un cas ne doit
 * pas changer le resultat de la mesure.
 */
final class QualityHarness
{
    public function __construct(
        private readonly string $corpusDir,
        private readonly string $manifestPath,
    ) {}

    /** @return array<string,mixed> */
    public function run(): array
    {
        $rulePack = RulePack::fromManifestFile($this->manifestPath);
        $tp = 0; $fn = 0; $fp = 0; $loc = 0;
        $perCase = [];

        $cases = glob($this->corpusDir . '/*', GLOB_ONLYDIR) ?: [];
        sort($cases, SORT_STRING);

        foreach ($cases as $dir) {
            $id = basename($dir);
            /** @var array{expected:list<array<string,mixed>>} $meta */
            $meta = json_decode((string) file_get_contents($dir . '/expected.json'), true, 512, JSON_THROW_ON_ERROR);
            $loc += count(file($dir . '/code.php') ?: []);

            $report = (new Scanner(new LocalFileSource($dir), $rulePack))->scan();
            $actual = [];
            foreach ($report->alerts() as $a) {
                $actual[$this->key($a->sinkRef->ruleId, $a->sinkRef->symbolFqn, $a->sinkRef->sinkKind, $a->sinkRef->ordinal)] = true;
            }
            $expected = [];
            foreach ($meta['expected'] as $e) {
                $expected[$this->key((string) $e['rule_id'], (string) $e['symbol_fqn'], (string) $e['sink_kind'], (int) $e['ordinal'])] = true;
            }

            $caseTp = count(array_intersect_key($expected, $actual));
            $caseFn = count(array_diff_key($expected, $actual));
            $caseFp = count(array_diff_key($actual, $expected));
            $tp += $caseTp; $fn += $caseFn; $fp += $caseFp;

            $perCase[$id] = ['tp' => $caseTp, 'fn' => $caseFn, 'fp' => $caseFp];
            if ($caseFn > 0) { $perCase[$id]['manques'] = array_keys(array_diff_key($expected, $actual)); }
            if ($caseFp > 0) { $perCase[$id]['faux_positifs'] = array_keys(array_diff_key($actual, $expected)); }
        }

        $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 1.0;
        $fpPer10k = $loc > 0 ? $fp / ($loc / 10000) : 0.0;

        return [
            'cases'          => count($cases),
            'loc'            => $loc,
            'tp'             => $tp,
            'fn'             => $fn,
            'fp'             => $fp,
            'recall'         => round($recall, 4),
            'fp_per_10kloc'  => round($fpPer10k, 2),
            'seuil_nfr2'     => ['recall_min' => 0.85, 'fp_per_10kloc_max' => 3.0],
            'nfr2_respecte'  => $recall >= 0.85 && $fpPer10k <= 3.0,
            'par_cas'        => $perCase,
        ];
    }

    private function key(string $ruleId, string $fqn, string $kind, int $ordinal): string
    {
        return "$ruleId|$fqn|$kind|$ordinal";
    }
}

<?php declare(strict_types=1);

namespace SqlGuard\Bench;

use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Infra\LocalFileSource;
use SqlGuard\Engine\Scanner;

/**
 * Banc de performance (FR-14, story 2.1, AD-22).
 *
 * Mesure d'abord, budgets ensuite : le §16 du PRD interdit d'ecrire le moindre
 * budget avant que ce banc ait tourne. Aucun chiffre n'est code en dur ici.
 *
 * Les depots de reference sont des paquets Composer a version figee, donc
 * reinstallables a l'identique par un tiers.
 */
final class PerformanceHarness
{
    /** @param array<string,array{path:string,source:string}> $targets */
    public function __construct(
        private readonly array $targets,
        private readonly string $manifestPath,
    ) {}

    /** @return array<string,mixed> */
    public function run(): array
    {
        $pack = RulePack::fromManifestFile($this->manifestPath);
        $rows = [];

        foreach ($this->targets as $name => $t) {
            if (!is_dir($t['path'])) {
                $rows[$name] = ['disponible' => false, 'source' => $t['source']];
                continue;
            }
            $loc = $this->countLines($t['path']);

            gc_collect_cycles();
            $memBefore = memory_get_usage(true);
            $t0 = hrtime(true);
            $report = (new Scanner(new LocalFileSource($t['path']), $pack))->scan();
            $elapsed = (hrtime(true) - $t0) / 1e9;
            $peak = memory_get_peak_usage(true);
            $d = $report->toArray();

            $rows[$name] = [
                'disponible'     => true,
                'source'         => $t['source'],
                'loc'            => $loc,
                'fichiers'       => $d['scope']['files_included'],
                'non_analysables'=> $d['scope']['files_excluded'],
                'secondes'       => round($elapsed, 2),
                'lignes_par_sec' => $elapsed > 0 ? (int) round($loc / $elapsed) : 0,
                'pic_memoire_mo' => round($peak / 1048576, 1),
                'alertes'        => count($d['alerts']),
                'limites'        => count($d['limits']),
                'statut'         => $d['status'],
            ];
            unset($report, $d);
        }

        return [
            'outil'      => \SqlGuard\Engine\Contract\Report::TOOL_VERSION,
            'date'       => date('Y-m-d'),
            'php'        => PHP_VERSION,
            'plateforme' => php_uname('s') . ' ' . php_uname('m'),
            'cibles'     => $rows,
        ];
    }

    private function countLines(string $dir): int
    {
        $total = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $c = @file_get_contents($f->getPathname());
                if ($c !== false) { $total += substr_count($c, "\n") + 1; }
            }
        }
        return $total;
    }
}

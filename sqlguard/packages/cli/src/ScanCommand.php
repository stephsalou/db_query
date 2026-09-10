<?php declare(strict_types=1);

namespace SqlGuard\Cli;

use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Infra\LocalFileSource;
use SqlGuard\Engine\Pass\Judge;
use SqlGuard\Engine\Scanner;

/** Adaptateur CLI. Ne contient aucune logique de verdict : Judge en est seul proprietaire (AD-14). */
final class ScanCommand
{
    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $root = null;
        $format = 'text';
        $manifest = null;
        $baselineFile = null;
        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--json') { $format = 'json'; continue; }
            if (str_starts_with($arg, '--rules=')) { $manifest = substr($arg, 8); continue; }
            if (str_starts_with($arg, '--baseline=')) { $baselineFile = substr($arg, 11); continue; }
            if (str_starts_with($arg, '--')) { continue; }
            $root ??= $arg;
        }
        $root ??= '.';
        if (!is_dir($root)) {
            fwrite(STDERR, "repertoire introuvable : $root\n");
            return Judge::EXIT_ERROR;
        }
        $manifest ??= dirname(__DIR__, 4) . '/rules/manifest.json';
        if (!is_file($manifest)) {
            fwrite(STDERR, "manifeste de regles introuvable : $manifest\n");
            return Judge::EXIT_ERROR;
        }

        $baseline = [];
        if ($baselineFile !== null) {
            if (!is_file($baselineFile)) {
                fwrite(STDERR, "fichier de reference introuvable : $baselineFile\n");
                return Judge::EXIT_ERROR;
            }
            /** @var array{alerts?:list<array{id?:string}>} $b */
            $b = json_decode((string) file_get_contents($baselineFile), true) ?: [];
            foreach ($b['alerts'] ?? [] as $a) {
                if (isset($a['id'])) { $baseline[] = (string) $a['id']; }
            }
        }

        $report = (new Scanner(
            new LocalFileSource(realpath($root) ?: $root),
            RulePack::fromManifestFile($manifest),
            $baseline,
        ))->scan();

        if ($format === 'json') {
            echo $report->toJson();
        } else {
            $this->renderText($report);
        }
        return (new Judge())->verdict($report);
    }

    private function renderText(\SqlGuard\Engine\Contract\Report $report): void
    {
        $d = $report->toArray();
        $n = count($d['alerts']);
        echo "sqlguard " . $d['tool_version'] . " — paquet de regles "
            . $d['rule_pack']['id'] . " " . $d['rule_pack']['version'] . "\n";
        echo str_repeat('-', 64) . "\n";
        foreach ($d['alerts'] as $a) {
            echo sprintf("%s  %s\n", $a['id'], $a['rule_id']);
            echo sprintf("  %s:%d  dans %s\n", $a['position']['file'], $a['position']['line'], $a['sink']['symbol_fqn']);
            foreach ($a['witness']['chain'] as $i => $s) {
                echo sprintf("    %d. %s  (%s:%d)\n", $i + 1, $s['label'], $s['file'], $s['line']);
            }
            echo "\n";
        }
        echo sprintf(
            "%d alerte(s) · %d fichier(s) analyse(s) · %d limite(s) d'analyse · statut %s\n",
            $n, $d['scope']['files_included'], count($d['limits']), $d['status'],
        );
        if ($d['status'] === 'incomplete') {
            echo "ATTENTION : analyse incomplete. Ce verdict ne couvre pas tout le code.\n";
        }
    }
}

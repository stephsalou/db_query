<?php declare(strict_types=1);

namespace SqlGuard\Bench;

use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Infra\LocalFileSource;
use SqlGuard\Engine\Scanner;

/**
 * Comparaison mesuree contre Psalm et Semgrep (FR-13, story 2.4, PRD §3.4).
 *
 * Regle de methode, non negociable : les DEFAITES de sqlguard sont publiees
 * comme ses victoires. Un harnais qui ne peut pas perdre ne mesure rien.
 *
 * Psalm est mesure en DEUX configurations, parce que l'ecart entre les deux est
 * lui-meme le resultat le plus interessant :
 *   - `psalm-defaut`  : configuration minimale, telle qu'un projet l'obtient.
 *   - `psalm-configure` : avec le stub PDO charge explicitement.
 * Ne mesurer que la premiere serait deloyal ; ne mesurer que la seconde
 * masquerait ce que rencontre reellement un utilisateur.
 */
final class ComparisonHarness
{
    public function __construct(
        private readonly string $corpusDir,
        private readonly string $manifestPath,
        private readonly ?string $psalmBin = null,
        private readonly ?string $psalmDir = null,
        private readonly ?string $semgrepBin = null,
    ) {}

    /** @return array<string,mixed> */
    public function run(): array
    {
        $cases = glob($this->corpusDir . '/*', GLOB_ONLYDIR) ?: [];
        sort($cases, SORT_STRING);

        $tools = ['sqlguard' => [], 'psalm-defaut' => [], 'psalm-configure' => [], 'semgrep' => []];
        $truth = [];
        $loc = 0;

        foreach ($cases as $dir) {
            $id = basename($dir);
            $meta = json_decode((string) file_get_contents($dir . '/expected.json'), true, 512, JSON_THROW_ON_ERROR);
            // Le corpus annote des sinks ; pour une comparaison inter-outils on
            // ramene a « ce cas contient-il au moins une vraie alerte SQL ? ».
            // Les outils tiers ne partagent ni nos identifiants ni nos ordinaux.
            $sqlExpected = array_filter(
                $meta['expected'],
                fn(array $e): bool => (string) $e['rule_id'] === 'sql-injection-concat-pdo',
            );
            $truth[$id] = $sqlExpected !== [];
            $loc += count(file($dir . '/code.php') ?: []);

            $tools['sqlguard'][$id]        = $this->sqlguardFinds($dir);
            $tools['psalm-defaut'][$id]    = $this->psalmFinds($dir, false);
            $tools['psalm-configure'][$id] = $this->psalmFinds($dir, true);
            $tools['semgrep'][$id]         = $this->semgrepFinds($dir);
        }

        $result = ['cases' => count($cases), 'loc' => $loc, 'tools' => []];
        foreach ($tools as $tool => $found) {
            if ($found === [] || in_array(null, $found, true)) {
                $result['tools'][$tool] = ['disponible' => false];
                continue;
            }
            $tp = $fn = $fp = 0;
            $lost = [];
            foreach ($truth as $id => $isVuln) {
                $f = $found[$id];
                if ($isVuln && $f)        { $tp++; }
                elseif ($isVuln && !$f)   { $fn++; $lost[] = "manque:$id"; }
                elseif (!$isVuln && $f)   { $fp++; $lost[] = "faux+:$id"; }
            }
            $result['tools'][$tool] = [
                'disponible'    => true,
                'tp' => $tp, 'fn' => $fn, 'fp' => $fp,
                'rappel'        => ($tp + $fn) > 0 ? round($tp / ($tp + $fn), 4) : 1.0,
                'fp_per_10kloc' => $loc > 0 ? round($fp / ($loc / 10000), 2) : 0.0,
                'ecarts'        => $lost,
            ];
        }
        return $result;
    }

    private function sqlguardFinds(string $dir): bool
    {
        $pack = RulePack::fromManifestFile($this->manifestPath);
        foreach ((new Scanner(new LocalFileSource($dir), $pack))->scan()->alerts() as $a) {
            if ($a->sinkRef->ruleId === 'sql-injection-concat-pdo') { return true; }
        }
        return false;
    }

    private function psalmFinds(string $dir, bool $withStub): ?bool
    {
        if ($this->psalmBin === null || $this->psalmDir === null) { return null; }
        $work = $this->psalmDir;
        $target = $work . '/target';
        $this->resetDir($target);
        copy($dir . '/code.php', $target . '/code.php');

        $stubs = $withStub
            ? '<stubs><file name="vendor/vimeo/psalm/stubs/extensions/pdo.phpstub" /></stubs>'
            : '';
        file_put_contents($work . '/psalm.xml', <<<XML
        <?xml version="1.0"?>
        <psalm errorLevel="1" resolveFromConfigFile="true" findUnusedCode="false">
            <projectFiles><directory name="target" /></projectFiles>
            $stubs
        </psalm>
        XML);

        $cmd = sprintf(
            'cd %s && php %s --taint-analysis --no-cache 2>&1',
            escapeshellarg($work),
            escapeshellarg($this->psalmBin),
        );
        $out = (string) shell_exec($cmd);
        return str_contains($out, 'TaintedSql');
    }

    private function semgrepFinds(string $dir): ?bool
    {
        if ($this->semgrepBin === null) { return null; }
        // `arch -arm64` : sur un macOS Apple Silicon ou PHP tourne en x86_64
        // (Rosetta), l'enfant herite de x86_64 et l'extension native de semgrep
        // (pydantic_core, arm64) refuse de se charger. Sans ce prefixe, semgrep
        // sort en traceback et le harnais le declarerait « indisponible » — soit
        // une comparaison silencieusement amputee d'un concurrent.
        $prefix = (PHP_OS_FAMILY === 'Darwin' && php_uname('m') === 'x86_64'
            && is_executable('/usr/bin/arch')) ? 'arch -arm64 ' : '';
        $cmd = sprintf(
            '%s%s --config=p/php --json --quiet --metrics=off %s 2>/dev/null',
            $prefix,
            escapeshellarg($this->semgrepBin),
            escapeshellarg($dir),
        );
        $out = (string) shell_exec($cmd);
        $d = json_decode($out, true);
        if (!is_array($d) || !isset($d['results'])) { return null; }
        foreach ($d['results'] as $r) {
            $id = strtolower((string) ($r['check_id'] ?? ''));
            if (str_contains($id, 'sql')) { return true; }
        }
        return false;
    }

    private function resetDir(string $d): void
    {
        if (is_dir($d)) {
            foreach (glob($d . '/*') ?: [] as $f) { @unlink($f); }
        } else {
            mkdir($d, 0700, true);
        }
    }
}

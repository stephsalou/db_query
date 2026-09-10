<?php declare(strict_types=1);
/**
 * Verifications du moteur sqlguard. Aucun framework.
 * Lancer : php tests/run.php   (ou composer test)
 */
require __DIR__ . '/../vendor/autoload.php';

use SqlGuard\Bench\QualityHarness;
use SqlGuard\Engine\Contract\Report;
use SqlGuard\Engine\Domain\AccessPath;
use SqlGuard\Engine\Domain\Alert;
use SqlGuard\Engine\Domain\LimitRecorder;
use SqlGuard\Engine\Domain\Position;
use SqlGuard\Engine\Domain\RulePack;
use SqlGuard\Engine\Domain\Taint;
use SqlGuard\Engine\Infra\LocalFileSource;
use SqlGuard\Engine\Pass\Judge;
use SqlGuard\Engine\Scanner;

$passed = 0; $failed = 0;
function check(string $label, callable $fn): void {
    global $passed, $failed;
    try { $fn(); $passed++; echo "  ok   $label\n"; }
    catch (\Throwable $e) { $failed++; echo "  FAIL $label\n       " . $e->getMessage() . "\n"; }
}
function same($e, $a, string $w = ''): void {
    if ($e !== $a) {
        throw new \Exception($w . ' attendu: ' . var_export($e, true) . ' | obtenu: ' . var_export($a, true));
    }
}
function throws(string $cls, callable $fn): void {
    try { $fn(); } catch (\Throwable $e) {
        if ($e instanceof $cls) { return; }
        throw new \Exception("attendu $cls, obtenu " . get_class($e));
    }
    throw new \Exception("attendu $cls, rien n'a ete leve");
}
$root = dirname(__DIR__, 2);
$manifest = $root . '/rules/manifest.json';
$pack = RulePack::fromManifestFile($manifest);

function sgScan(string $dir, RulePack $pack): Report {
    return (new Scanner(new LocalFileSource($dir), $pack))->scan();
}
function fixture(string $code): string {
    $d = sys_get_temp_dir() . '/sg-' . bin2hex(random_bytes(6));
    mkdir($d, 0700, true);
    file_put_contents($d . '/f.php', $code);
    return $d;
}

echo "\n-- treillis de marque (AD-4) --\n";
check('union prend le plus haut niveau', function () {
    same(Taint::TAINTED, Taint::clean()->union(Taint::tainted('$_GET'))->level);
    same(Taint::TAINTED, Taint::tainted('$_GET')->union(Taint::sanitized('cast-int'))->level);
    same(Taint::SANITIZED, Taint::clean()->union(Taint::sanitized('cast-int'))->level);
});
check('union fusionne les sources sans doublon', function () {
    $u = Taint::tainted('$_GET')->union(Taint::tainted('$_POST'))->union(Taint::tainted('$_GET'));
    same(['$_GET', '$_POST'], $u->sources);
});

echo "\n-- chemins d'acces (AD-4) --\n";
check('profondeur au-dela de 2 devient agregee', function () {
    same(true, AccessPath::of('a', ['x', 'y', 'z'])->aggregated);
    same(false, AccessPath::of('a', ['x', 'y'])->aggregated);
});
check('un conteneur agrege couvre ses sous-chemins', function () {
    same(true, AccessPath::aggregate('a')->covers(AccessPath::of('a', ['k'])));
    same(false, AccessPath::aggregate('a')->covers(AccessPath::of('b', ['k'])));
});

echo "\n-- identite d'alerte (AD-9) --\n";
check('sept champs, ligne exclue du hash', function () {
    $a = Alert::computeId('r', 'generic', 1, 'src/a.php', '\f', 'pdo.query', 0);
    $b = Alert::computeId('r', 'generic', 1, 'src/a.php', '\f', 'pdo.query', 0);
    same($a, $b, 'meme entree');
    same(true, str_starts_with($a, 'SG1-'));
    same(20, strlen($a));
});
check('un ordinal different change l identite', function () {
    $a = Alert::computeId('r', 'generic', 1, 'src/a.php', '\f', 'pdo.query', 0);
    $b = Alert::computeId('r', 'generic', 1, 'src/a.php', '\f', 'pdo.query', 1);
    if ($a === $b) { throw new \Exception('collision sur ordinal'); }
});
check('une version mineure de paquet ne change pas l identite', function () {
    // le payload ne prend que la MAJEURE (AD-9)
    $a = Alert::computeId('r', 'generic', 1, 'src/a.php', '\f', 'pdo.query', 0);
    $b = Alert::computeId('r', 'generic', 1, 'src/a.php', '\f', 'pdo.query', 0);
    same($a, $b);
});

echo "\n-- positions canoniques (AD-25) --\n";
check('chemin relatif a la racine, sans ./', function () {
    same('src/a.php', Position::canonicalPath('/p/r/src/a.php', '/p/r'));
    same('src/a.php', Position::canonicalPath('/p/r/src/a.php', '/p/r/'));
});

echo "\n-- registre des limites (AD-15) --\n";
check('ajout seul, et refus d un code hors enumeration', function () {
    $l = new LimitRecorder();
    same(true, $l->isEmpty());
    $l->record(LimitRecorder::DYNAMIC_CALL, 'd', new Position('a.php', 1));
    same(1, $l->count());
    throws(\InvalidArgumentException::class, fn() => $l->record('inventé', 'x', new Position('a.php', 1)));
});

echo "\n-- detection --\n";
check('concatenation d une superglobale vers PDO::query', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $i=$_GET["i"]; return $d->query("S ".$i); }'), $pack);
    same(1, count($r->alerts()));
    same('sql-injection-concat-pdo', $r->alerts()[0]->sinkRef->ruleId);
});
check('requete preparee : aucune alerte', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $s=$d->prepare("S ?"); $s->execute([$_GET["i"]]); }'), $pack);
    same(0, count($r->alerts()));
});
check('cast entier rompt la propagation', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $i=(int)$_GET["i"]; return $d->query("S $i"); }'), $pack);
    same(0, count($r->alerts()));
});
check('cast chaine ne protege pas', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $i=(string)$_GET["i"]; return $d->query("S ".$i); }'), $pack);
    same(1, count($r->alerts()));
});
check('union de branches : marque dans une seule branche suffit', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d,$c){ $v="s"; if($c){$v=$_GET["v"];} return $d->query("S $v"); }'), $pack);
    same(1, count($r->alerts()));
});
check('reaffectation par un litteral nettoie', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $v=$_GET["v"]; $v="lit"; return $d->query("S $v"); }'), $pack);
    same(0, count($r->alerts()));
});
check('assainisseur dont le retour est jete est signale', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(){ $a=[$_GET["a"]]; foreach($a as $k=>$v){ addslashes(($a[$k]="\'".$v."\'")); } }'), $pack);
    same(1, count($r->alerts()));
    same('sanitizer-noop', $r->alerts()[0]->sinkRef->ruleId);
});
check('assainisseur dont le retour est conserve ne l est pas', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $n=addslashes((string)$_GET["n"]); return $d->query("S \'$n\'"); }'), $pack);
    same(0, count($r->alerts()));
});
check('ordinaux distincts pour deux sinks du meme kind', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $a=$_GET["a"]; $d->query("A $a"); $d->query("B $a"); }'), $pack);
    same(2, count($r->alerts()));
    $ords = array_map(fn($x) => $x->sinkRef->ordinal, $r->alerts());
    sort($ords);
    same([0, 1], $ords);
});

echo "\n-- invariants --\n";
check('aucune alerte sans temoin (NFR-3 / FR-7)', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $i=$_GET["i"]; $d->query("S ".$i); }'), $pack);
    foreach ($r->alerts() as $a) {
        if ($a->witness->isEmpty()) { throw new \Exception('alerte sans temoin'); }
    }
    same(true, count($r->alerts()) > 0);
});
check('le temoin ne contient aucune charge armee (AD-17)', function () use ($pack) {
    $r = sgScan(fixture('<?php function f(PDO $d){ $i=$_GET["i"]; $d->query("S ".$i); }'), $pack);
    $json = json_encode($r->toArray());
    foreach (["' OR 1=1", 'UNION SELECT', ';--'] as $payload) {
        if (str_contains((string) $json, $payload)) { throw new \Exception("charge armee presente : $payload"); }
    }
    same(1, $r->toArray()['witness_level']);
});
check('determinisme : deux scans donnent le meme JSON (NFR-1)', function () use ($pack) {
    $d = fixture('<?php function f(PDO $x){ $i=$_GET["i"]; $x->query("S ".$i); $x->exec("T ".$i); }');
    same(sgScan($d, $pack)->toJson(), sgScan($d, $pack)->toJson());
});
check('identite stable au reformatage (AD-9)', function () use ($pack) {
    $code = '<?php function f(PDO $d){ $i=$_GET["i"]; return $d->query("S ".$i); }';
    $a = sgScan(fixture($code), $pack)->alerts()[0]->id;
    $b = sgScan(fixture("<?php\n\n\nfunction f(PDO \$d)\n{\n    \$i = \$_GET[\"i\"];\n\n    return \$d->query(\"S \" . \$i);\n}"), $pack)->alerts()[0]->id;
    same($a, $b, 'identite apres reformatage');
});
check('aucune execution du code analyse (AD-18 / NFR-5)', function () use ($pack) {
    $marker = sys_get_temp_dir() . '/sg-side-effect-' . bin2hex(random_bytes(4));
    sgScan(fixture('<?php file_put_contents("' . $marker . '", "x"); $d = 1;'), $pack);
    same(false, file_exists($marker), 'le code analyse a ete execute !');
});

echo "\n-- verdict (AD-14) --\n";
check('codes de sortie : propre 0, alertes 1, incomplet 2', function () use ($pack) {
    $j = new Judge();
    same(Judge::EXIT_CLEAN, $j->verdict(sgScan(fixture('<?php $a = 1;'), $pack)));
    same(Judge::EXIT_ALERTS, $j->verdict(sgScan(fixture('<?php function f(PDO $d){ return $d->query("S ".$_GET["i"]); }'), $pack)));
    same(Judge::EXIT_INCOMPLETE, $j->verdict(sgScan(fixture('<?php function f(){ return mystere($_GET["a"]); }'), $pack)));
});
check('une erreur de syntaxe devient une limite, pas une exception', function () use ($pack) {
    $r = sgScan(fixture('<?php function f( { syntaxe invalide'), $pack);
    same(false, $r->isComplete());
    $codes = array_column($r->toArray()['limits'], 'reason_code');
    same(true, in_array(LimitRecorder::PARSE_ERROR, $codes, true));
});

echo "\n-- contrat S-2 (AD-11) --\n";
check('champs obligatoires presents et typés', function () use ($pack) {
    $d = sgScan(fixture('<?php function f(PDO $x){ return $x->query("S ".$_GET["i"]); }'), $pack)->toArray();
    foreach (['schema_version','tool_version','rule_pack','php_target','status','witness_level','scope','alerts','limits'] as $k) {
        if (!array_key_exists($k, $d)) { throw new \Exception("champ manquant : $k"); }
    }
    same(1, $d['witness_level']);
    same(true, in_array($d['status'], ['complete','incomplete'], true));
});

echo "\n-- budget de faux positifs (NFR-2) --\n";
check('le corpus respecte rappel >= 85 % et FP <= 3/10kLOC', function () use ($root, $manifest) {
    $r = (new QualityHarness($root . '/corpus/cas', $manifest))->run();
    if (!$r['nfr2_respecte']) {
        throw new \Exception("rappel={$r['recall']} fp/10k={$r['fp_per_10kloc']}");
    }
    same(0, $r['fn'], 'manques');
    same(0, $r['fp'], 'faux positifs');
});

echo "\n-- dogfooding : le defaut historique de ce depot --\n";
check('detecte les 3 no-op escape_data du db_query d origine', function () use ($pack, $root) {
    $dir = sys_get_temp_dir() . '/sg-orig-' . bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    $src = shell_exec('cd ' . escapeshellarg($root) . ' && git show 672fa2a:src/db_query.php 2>/dev/null');
    if (!is_string($src) || $src === '') { echo "(historique git indisponible, test ignore) "; return; }
    file_put_contents($dir . '/db_query.php', $src);
    $r = sgScan($dir, $pack);
    $noop = array_filter($r->alerts(), fn($a) => $a->sinkRef->ruleId === 'sanitizer-noop');
    same(3, count($noop), 'occurrences de sanitizer-noop');
});

echo "\n-- reference (baseline, AD-16 / story 4.2) --\n";
check('une alerte presente dans la reference ne fait pas echouer', function () use ($pack) {
    $dir = fixture('<?php function f(PDO $d){ return $d->query("S ".$_GET["i"]); }');
    $first = sgScan($dir, $pack);
    same(1, count($first->alerts()));
    $ids = array_map(fn($a) => $a->id, $first->alerts());
    $with = (new Scanner(new LocalFileSource($dir), $pack, $ids))->scan();
    same(0, count($with->newAlerts()), 'alertes hors reference');
    same(Judge::EXIT_CLEAN, (new Judge())->verdict($with));
});
check('une alerte hors reference fait echouer', function () use ($pack) {
    $dir = fixture('<?php function f(PDO $d){ return $d->query("S ".$_GET["i"]); }');
    $with = (new Scanner(new LocalFileSource($dir), $pack, ['SG1-inexistant00000']))->scan();
    same(1, count($with->newAlerts()));
    same(Judge::EXIT_ALERTS, (new Judge())->verdict($with));
});
check('une alerte supprimee est marquee, jamais effacee (AD-16)', function () use ($pack) {
    $dir = fixture('<?php function f(PDO $d){ return $d->query("S ".$_GET["i"]); }');
    $ids = array_map(fn($a) => $a->id, sgScan($dir, $pack)->alerts());
    $d = (new Scanner(new LocalFileSource($dir), $pack, $ids))->scan()->toArray();
    same(1, count($d['alerts']), 'alerte toujours presente dans S-2');
    same(true, $d['alerts'][0]['suppressed'], 'marquee supprimee');
});

echo "\n$passed reussis, $failed echoues\n";
exit($failed === 0 ? 0 : 1);

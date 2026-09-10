<?php
/**
 * Verifications de db_query. Aucun framework, aucune base MySQL : le
 * constructeur de requetes est teste sur getSql()/getBindings(), et le
 * bout-en-bout sur SQLite en memoire.
 *
 * Lancer : php tests/test_db_query.php   (ou composer test)
 */

require __DIR__ . '/../autoload.php';

use steph\db_query\autoload;
use steph\db_query\db_query;
use steph\db_query\Exception\DatabaseException;

autoload::register();

$passed = 0;
$failed = 0;

function check(string $label, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ok   $label\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "  FAIL $label\n       " . $e->getMessage() . "\n";
    }
}

function same($expected, $actual, string $what = ''): void
{
    if ($expected !== $actual) {
        throw new \Exception(
            $what . " attendu: " . var_export($expected, true)
            . " | obtenu: " . var_export($actual, true)
        );
    }
}

function throws(string $class, callable $fn): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new \Exception("attendu $class, obtenu " . get_class($e) . ': ' . $e->getMessage());
    }
    throw new \Exception("attendu $class, rien n'a ete leve");
}

function q(): db_query
{
    return db_query::withPdo(new \PDO('sqlite::memory:'));
}

echo "\n-- construction du SQL --\n";

check('select/from met les identifiants entre backticks', function () {
    same('SELECT * FROM `user`', q()->select('*')->from('user')->getSql());
});

check('la liste de colonnes est decoupee et quotee', function () {
    same('SELECT `id`, `first_name` FROM `user`',
        q()->select('id,first_name')->from('user')->getSql());
});

check('INSERT INTO a bien son espace et des placeholders', function () {
    $b = q()->insert('user', 'id,name', [[1, 'bob'], [2, 'alice']]);
    same('INSERT INTO `user` (`id`, `name`) VALUES (?, ?), (?, ?)', $b->getSql());
    same([1, 'bob', 2, 'alice'], $b->getBindings());
});

check('update quote les colonnes et lie les valeurs', function () {
    $b = q()->update('user', 'first_name,age', ['bob', 30]);
    same('UPDATE `user` SET `first_name` = ?, `age` = ? ', $b->getSql());
    same(['bob', 30], $b->getBindings());
});

check('where lie la valeur au lieu de la concatener', function () {
    $b = q()->select('*')->from('user')->where([['id', 7]]);
    same('SELECT * FROM `user` WHERE `id` = ?', $b->getSql());
    same([7], $b->getBindings());
});

check('where enchaine avec la conjonction demandee', function () {
    $b = q()->select('*')->from('user')->where([['id', 7], ['OR', 'name', 'bob']]);
    same('SELECT * FROM `user` WHERE `id` = ? OR `name` = ?', $b->getSql());
    same([7, 'bob'], $b->getBindings());
});

echo "\n-- injection SQL --\n";

check('une valeur hostile reste une valeur liee', function () {
    $evil = "1' OR '1'='1";
    $b = q()->select('*')->from('user')->where([['id', $evil]]);
    same('SELECT * FROM `user` WHERE `id` = ?', $b->getSql());
    same([$evil], $b->getBindings());
    if (strpos($b->getSql(), 'OR') !== false) {
        throw new \Exception('la valeur a fuite dans le SQL');
    }
});

check('une valeur hostile dans un insert reste liee', function () {
    $evil = "a'); DROP TABLE user; --";
    $b = q()->insert('user', 'name', [[$evil]]);
    same('INSERT INTO `user` (`name`) VALUES (?)', $b->getSql());
    same([$evil], $b->getBindings());
});

check('un identifiant hostile est refuse', function () {
    throws(DatabaseException::class, fn() => q()->select('*')->from('user WHERE 1=1 UNION SELECT 1'));
});

check('un backtick dans une colonne est refuse', function () {
    throws(DatabaseException::class, fn() => q()->select('a`,(SELECT 1)'));
});

echo "\n-- garde-fous --\n";

check('where([]) ne produit pas WHERE 1', function () {
    $sql = q()->select('*')->from('user')->where([])->getSql();
    same('SELECT * FROM `user`', $sql);
    if (stripos($sql, 'where') !== false) {
        throw new \Exception('un WHERE implicite a ete ajoute');
    }
});

check('un DELETE sans WHERE est refuse', function () {
    throws(DatabaseException::class, fn() => q()->delete()->from('user')->where()->run());
});

check('allowUnbounded() autorise le DELETE global', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    $db->exec("INSERT INTO user (name) VALUES ('a'), ('b')");
    $b = db_query::withPdo($db);
    same(2, $b->delete()->from('user')->allowUnbounded()->run()->getResult());
});

check('un UPDATE sans WHERE est refuse', function () {
    throws(DatabaseException::class, fn() => q()->update('user', 'name', ['x'])->run());
});

check('une condition mal formee est refusee', function () {
    throws(DatabaseException::class, fn() => q()->select('*')->from('user')->where([['a', 'b', 'c', 'd']]));
    throws(DatabaseException::class, fn() => q()->select('*')->from('user')->where(['id', 7]));
    throws(DatabaseException::class, fn() => q()->select('*')->from('user')->where([['NOT', 'id', 7]]));
});

check('un desaccord colonnes/valeurs est refuse', function () {
    throws(DatabaseException::class, fn() => q()->insert('user', 'id,name', [[1]]));
    throws(DatabaseException::class, fn() => q()->insert('user', 'id,name', [[1, 'a', 'trop']]));
    throws(DatabaseException::class, fn() => q()->update('user', 'id,name', ['seul']));
});

check('from() hors requete est refuse', function () {
    throws(DatabaseException::class, fn() => q()->from('user'));
});

check('une requete vide ne s execute pas', function () {
    throws(DatabaseException::class, fn() => q()->run());
});

check('getDB() sans connexion leve une DatabaseException', function () {
    $b = q();
    (function () { $this->DB = null; })->call($b);
    throws(DatabaseException::class, fn() => $b->getDB());
});

echo "\n-- sous-requetes --\n";

check('open/close_sub_query produisent un IN equilibre', function () {
    $b = q()->select('*')->from('a')->where([['id', 1]])
        ->open_sub_query('id', 'b')->select('id')->from('b')->close_sub_query();
    same('SELECT * FROM `a` WHERE `id` = ? AND `b`.`id` IN (SELECT `id` FROM `b` )', $b->getSql());
    same([1], $b->getBindings());
});

check('open_sub_query sans where est refuse', function () {
    throws(DatabaseException::class, fn() => q()->select('*')->from('a')->open_sub_query('id', 'b'));
});

check('close_sub_query sans ouverture est refuse', function () {
    throws(DatabaseException::class, fn() => q()->select('*')->from('a')->close_sub_query());
});

check('une sous-requete non fermee ne s execute pas', function () {
    throws(DatabaseException::class, function () {
        q()->select('*')->from('a')->where([['id', 1]])->open_sub_query('id', 'b')->run();
    });
});

echo "\n-- etat entre requetes --\n";

check('le SQL est reinitialise apres un echec d execution', function () {
    $b = q(); // table inexistante -> PDO leve
    try {
        $b->select('*')->from('table_absente')->run_fetch(true);
    } catch (\Throwable $e) {
        // attendu
    }
    same('', $b->getSql(), 'sql apres echec');
    same([], $b->getBindings(), 'bindings apres echec');
});

check('le SQL est reinitialise apres un succes', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    $b = db_query::withPdo($db);
    $b->select('*')->from('user')->run_fetch(true);
    same('', $b->getSql());
});

echo "\n-- bout en bout (SQLite) --\n";

check('insert puis select rendent la valeur hostile telle quelle', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    $b = db_query::withPdo($db);

    $evil = "a'); DROP TABLE user; --";
    $b->insert('user', 'name', [[$evil]])->insert_run();

    $row = db_query::withPdo($db)->select('name')->from('user')
        ->where([['name', $evil]])->run_fetch()->getResult();
    same($evil, $row->name, 'valeur relue');

    // la table existe toujours : rien n a ete execute depuis la valeur
    $n = $db->query('SELECT COUNT(*) FROM user')->fetchColumn();
    same(1, (int) $n, 'lignes restantes');
});

check('run() renvoie le nombre de lignes touchees', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    $db->exec("INSERT INTO user (name) VALUES ('a'), ('b'), ('c')");
    $b = db_query::withPdo($db);
    same(1, $b->update('user', 'name', ['zz'])->where([['name', 'a']])->run()->getResult());
});

check('run_fetch sans resultat renvoie null, pas false', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    $b = db_query::withPdo($db);
    same(null, $b->select('*')->from('user')->where([['name', 'absent']])->run_fetch()->getResult());
});

echo "\n-- exemples du readme --\n";

check('l exemple sub_query du readme s execute', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, active INTEGER)');
    $db->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER)');
    $db->exec('INSERT INTO user (id, active) VALUES (1,1), (2,1), (3,0)');
    $db->exec('INSERT INTO orders (user_id) VALUES (1), (1), (2)');

    $rows = db_query::withPdo($db)->select('*')->from('user')
        ->where([['active', 1]])
        ->open_sub_query('id', 'user')
            ->select('user_id')->from('orders')
        ->close_sub_query()
        ->run_fetch(true)->getResult();

    same(2, count($rows), 'lignes rendues');
});

check('l exemple raw sql du readme s execute', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    $db->exec("INSERT INTO user (id, name) VALUES (7, 'bob')");
    $row = db_query::withPdo($db)
        ->setSql('SELECT * FROM user WHERE id = ?', [7])
        ->run_fetch()->getResult();
    same('bob', $row->name);
});

check('l exemple insert du readme rend un id', function () {
    $db = new \PDO('sqlite::memory:');
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
    $id = db_query::withPdo($db)->insert('user', 'first_name,last_name', [
        ['bob', 'martin'],
        ['alice', 'dupont'],
    ])->insert_run()->getResult();
    same('2', (string) $id);
});

echo "\n$passed reussis, $failed echoues\n";
exit($failed === 0 ? 0 : 1);

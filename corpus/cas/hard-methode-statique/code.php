<?php
class Q {
    public static function run(PDO $db, string $s) { return $db->query($s); }
}
function go(PDO $db) { return Q::run($db, "SELECT " . $_GET['q']); }

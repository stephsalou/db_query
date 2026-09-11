<?php
class Q {
    private static function safe(string $s): int { return intval($s); }
    public function run(PDO $db) { return $db->query("SELECT * FROM t WHERE i = " . self::safe($_GET['i'])); }
}

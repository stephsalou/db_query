<?php
class Q {
    private function sink(PDO $db, string $s) { return $db->query($s); }
    public function run(PDO $db) { return $this->sink($db, "SELECT " . $_GET['q']); }
}

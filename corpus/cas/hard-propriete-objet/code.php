<?php
class Repo {
    private string $sql = '';
    public function build() { $this->sql = "SELECT * FROM t WHERE a = '" . $_GET['a'] . "'"; }
    public function run(PDO $db) { return $db->query($this->sql); }
}

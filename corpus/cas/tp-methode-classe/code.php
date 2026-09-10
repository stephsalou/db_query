<?php
class Repo {
    private PDO $db;
    public function find() {
        return $this->db->query("SELECT * FROM t WHERE k = '" . $_GET['k'] . "'");
    }
}

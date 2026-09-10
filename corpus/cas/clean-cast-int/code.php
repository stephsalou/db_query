<?php
function f(PDO $db) {
    $id = (int) $_GET['id'];
    return $db->query("SELECT * FROM t WHERE id = $id");
}

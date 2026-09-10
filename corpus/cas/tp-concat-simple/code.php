<?php
function f(PDO $db) {
    $id = $_GET['id'];
    return $db->query("SELECT * FROM t WHERE id = '" . $id . "'");
}

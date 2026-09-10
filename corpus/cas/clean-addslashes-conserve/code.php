<?php
function f(PDO $db) {
    $n = addslashes((string) $_GET['n']);
    return $db->query("SELECT * FROM t WHERE n = '$n'");
}

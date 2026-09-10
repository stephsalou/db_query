<?php
function f(PDO $db) {
    $n = $db->quote($_GET['n']);
    return $db->query("SELECT * FROM t WHERE n = $n");
}

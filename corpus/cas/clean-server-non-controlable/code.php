<?php
function f(PDO $db) {
    $root = $_SERVER['DOCUMENT_ROOT'];
    return $db->query("SELECT * FROM t WHERE p = '$root'");
}

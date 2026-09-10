<?php
function f(PDO $db) {
    $a = $_GET['a'];
    $db->query("SELECT 1 FROM t WHERE a = '$a'");
    return $db->query("SELECT 2 FROM t WHERE a = '$a'");
}

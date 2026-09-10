<?php
function f(PDO $db) {
    $v = $_GET['v'];
    $v = 'litteral';
    return $db->query("SELECT * FROM t WHERE v = '$v'");
}

<?php
function f(PDO $db, bool $c) {
    $v = 'safe';
    if ($c) {
        $v = $_GET['v'];
    }
    return $db->query("SELECT * FROM t WHERE v = '$v'");
}

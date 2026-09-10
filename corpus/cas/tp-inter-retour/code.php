<?php
function passthrough(string $v) { return $v . '!'; }
function useIt(PDO $db) {
    $v = passthrough($_GET['v']);
    return $db->query("SELECT * FROM t WHERE v = '$v'");
}

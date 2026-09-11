<?php
function go(PDO $db, bool $c) {
    $v = $_GET['v'];
    if ($c) { $v = intval($v); }
    return $db->query("SELECT * FROM t WHERE v = $v");
}

<?php
function go(PDO $db) {
    $f = function (string $s) use ($db) { return $db->query($s); };
    return $f("SELECT * FROM t WHERE a = 1");
}

<?php
function runIt(PDO $db, string $sql) {
    return $db->query($sql);
}
function caller(PDO $db) {
    return runIt($db, "SELECT * FROM t WHERE a = '" . $_GET['a'] . "'");
}

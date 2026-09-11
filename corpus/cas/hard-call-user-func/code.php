<?php
function sink(PDO $db, string $s) { return $db->query($s); }
function go(PDO $db) {
    return call_user_func('sink', $db, "SELECT " . $_GET['q']);
}

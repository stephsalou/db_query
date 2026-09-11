<?php
$GLOBALS['q'] = $_GET['q'];
function go(PDO $db) {
    global $q;
    return $db->query("SELECT " . $q);
}

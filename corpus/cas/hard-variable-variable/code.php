<?php
function go(PDO $db) {
    $name = 'q';
    $$name = $_GET['q'];
    return $db->query("SELECT " . $q);
}

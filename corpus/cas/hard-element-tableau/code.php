<?php
function sink(PDO $db, string $s) { return $db->query($s); }
function go(PDO $db) {
    $row = ['q' => $_GET['q']];
    return sink($db, "SELECT " . $row['q']);
}

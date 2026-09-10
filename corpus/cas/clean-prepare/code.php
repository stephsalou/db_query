<?php
function f(PDO $db) {
    $st = $db->prepare('SELECT * FROM t WHERE id = ?');
    $st->execute([$_GET['id']]);
    return $st->fetchAll();
}

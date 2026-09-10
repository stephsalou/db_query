<?php
function f(PDO $db) {
    $p = (float) $_POST['price'];
    return $db->exec("UPDATE t SET price = $p WHERE id = 1");
}

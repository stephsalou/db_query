<?php
function f(PDO $db) {
    $n = $_POST['name'];
    return $db->query("SELECT * FROM t WHERE n = '{$n}'");
}

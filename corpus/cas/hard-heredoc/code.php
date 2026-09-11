<?php
function go(PDO $db) {
    $a = $_GET['a'];
    $sql = <<<SQL
    SELECT * FROM t WHERE a = '$a'
    SQL;
    return $db->query($sql);
}

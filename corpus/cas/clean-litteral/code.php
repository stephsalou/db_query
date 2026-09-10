<?php
function f(PDO $db) {
    return $db->query('SELECT * FROM t WHERE a = 1');
}

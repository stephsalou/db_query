<?php
function f(PDO $db) {
    return $db->query("SELECT * FROM log WHERE r = '" . $_SERVER['HTTP_REFERER'] . "'");
}

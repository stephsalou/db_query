<?php
function f(PDO $db) {
    return $db->query("SELECT * FROM log WHERE ua = '" . $_SERVER['HTTP_USER_AGENT'] . "'");
}

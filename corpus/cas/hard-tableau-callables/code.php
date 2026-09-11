<?php
function dispatch(PDO $db, array $handlers) {
    $h = $handlers['run'];
    return $h($db, "SELECT * FROM t WHERE a = '" . $_GET['a'] . "'");
}

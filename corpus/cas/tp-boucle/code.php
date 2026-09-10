<?php
function f(PDO $db) {
    foreach ($_POST['rows'] as $r) {
        $db->exec("INSERT INTO t VALUES ('" . $r . "')");
    }
}

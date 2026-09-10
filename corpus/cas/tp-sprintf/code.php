<?php
function f(PDO $db) {
    return $db->exec(sprintf('DELETE FROM t WHERE n = %s', $_REQUEST['n']));
}

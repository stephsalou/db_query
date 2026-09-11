<?php
function handle(Container $c) {
    $db = $c->get('db');
    return $db->query("SELECT * FROM t WHERE a = '" . $_GET['a'] . "'");
}

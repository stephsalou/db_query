<?php
function safely(PDO $db, string $s) { return $db->query("SELECT * FROM t WHERE i = " . intval($s)); }
function caller(PDO $db) { return safely($db, $_GET['i']); }

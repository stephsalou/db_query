<?php
function lvl3(PDO $db, string $s) { return $db->exec($s); }
function lvl2(PDO $db, string $s) { return lvl3($db, $s); }
function lvl1(PDO $db, string $s) { return lvl2($db, $s); }
function entry(PDO $db) { return lvl1($db, "DELETE FROM t WHERE x = " . $_POST['x']); }

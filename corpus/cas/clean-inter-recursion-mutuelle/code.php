<?php
function ping(string $s, int $n) { return $n > 0 ? pong($s, $n - 1) : $s; }
function pong(string $s, int $n) { return $n > 0 ? ping($s, $n - 1) : $s; }
function useThem() { return ping('litteral', 3); }

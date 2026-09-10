<?php
function f() {
    $arr = [$_GET['a']];
    foreach ($arr as $k => $v) {
        addslashes(($arr[$k] = "'" . $v . "'"));
    }
    return $arr;
}

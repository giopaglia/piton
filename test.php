<?php
$a = [1,2,3,4];
$b = $a;

var_dump($a);
var_dump($b);

$b[1] = 10;

var_dump($a);
var_dump($b);
?>
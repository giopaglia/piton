<?php

function my_func(&$a) {
    $a[] = 30;
}

$arr = array(10, 20);
my_func($arr);
var_dump($arr);
die();

//////////////////////////////////////////

$a = [1,2,3,4];
$b = $a;

var_dump($a);
var_dump($b);

$b[1] = 10;

var_dump($a);
var_dump($b);
?>
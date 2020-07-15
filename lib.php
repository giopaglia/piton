<?php

define("PACKAGE_NAME", "DBFit");
define("MODELS_FOLDER", "models");

/* Library of generic utils */

function mysql_set($arr, $map_function = "mysql_quote_str") { return "(" . mysql_list($arr, $map_function) . ")"; }
function mysql_list($arr, $map_function = "mysql_backtick_str") { return join(", ", array_map($map_function, $arr)); }
function mysql_quote_str($str) { return "'$str'"; }
function mysql_backtick_str($str) { return "`$str`"; }

function get_var_dump($a)  { ob_start(); var_dump($a); return ob_get_clean(); }
function die_var_dump($a)  { die(get_var_dump($a)); }
function get_arr_dump($arr, $delimiter = ", ") {
  return "[" . array_list($arr) . "]";
}
function array_list($arr, $delimiter = ", ") {
  $out_str = "";
  foreach ($arr as $i => $val) {
    if (method_exists($val, "toString")) {
      $s = $val->toString();
    } else if (is_array($val)) {
      $s = get_arr_dump($val);
    } else {
      $s = strval($val);
    }
    $out_str .= (isAssoc($arr) ? "$i => " : "") . $s . ($i!==count($arr)-1 ? $delimiter : "");
  }
  return $out_str;
}

# Source: https://stackoverflow.com/a/173479/5646732
function isAssoc(array $arr)
{
  if(array() === $arr) return false;
  return array_keys($arr) !== range(0, count($arr) - 1);
}

# Source: https://www.php.net/manual/en/debugger.php#118058
function console_log($data){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
}


/**
* Normalizes the doubles in the array by their sum.
* 
* @param doubles the array of double
* @exception IllegalArgumentException if sum is Zero or NaN
*/
function normalize(&$arr) {
  $s = array_sum($arr);

  if (is_nan($s)) {
    throw new IllegalArgumentException("Can't normalize array. Sum is NaN.");
  }
  if ($s == 0) {
    // Maybe this should just be a return.
    throw new IllegalArgumentException("Can't normalize array. Sum is zero.");
  }
  foreach ($arr as &$v) {
    $v /= $s;
  }
}


# Source: https://stackoverflow.com/a/1091219
function join_paths() {
    $args = func_get_args();
    $paths = array();
    foreach ($args as $arg) {
        $paths = array_merge($paths, (array)$arg);
    }

    $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
    $paths = array_filter($paths);
    return join('/', $paths);
}

/*
 * This is a Python/Ruby style zip()
 *
 * zip(array $a1, array $a2, ... array $an, [bool $python=true])
 *
 * The last argument is an optional bool that determines the how the function
 * handles when the array arguments are different in length
 *
 * By default, it does it the Python way, that is, the returned array will
 * be truncated to the length of the shortest argument
 *
 * If set to FALSE, it does it the Ruby way, and NULL values are used to
 * fill the undefined entries
 *
 * Source: https://stackoverflow.com/questions/2815162/is-there-a-php-function-like-pythons-zip
 */
function zip() {
    $args = func_get_args();

    $ruby = array_pop($args);
    if (is_array($ruby))
        $args[] = $ruby;

    $counts = array_map('count', $args);
    $count = ($ruby) ? min($counts) : max($counts);
    $zipped = array();

    for ($i = 0; $i < $count; $i++) {
        for ($j = 0; $j < count($args); $j++) {
            $val = (isset($args[$j][$i])) ? $args[$j][$i] : null;
            $zipped[$i][$j] = $val;
        }
    }
    return $zipped;
}


# Files in directory
function filesin($a, $full_path = false, $sortby=false)
{
	$a = safeSuffix($a, "/");
	$ret = [];
	foreach(array_slice(scandir($a), 2) as $item)
		if(is_file($a . $item))
		{
			$f = ($full_path ? $a : "") . $item;
			if($sortby==false)
				$ret[] = $f;
			else if($sortby=="mtime")
				$ret[$f] = filemtime($a . "/" . $f);
		}
	
	if($sortby=="mtime")
	{
		arsort($ret);
		$ret = array_keys($ret);
	}

	return $ret;
}

// Source: https://www.php.net/manual/en/function.srand.php
function make_seed()
{
  list($usec, $sec) = explode(' ', microtime());
  return $sec + $usec * 1000000;
}

function startsWith($haystack, $needle) { return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false; }
function endsWith($haystack, $needle)   { return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false); }
function safePrefix($haystack, $needle) { return (startsWith($haystack, $needle) ? $haystack : $needle . $haystack); }
function safeSuffix($haystack, $needle) { return (endsWith($haystack, $needle)   ? $haystack : $haystack . $needle); }
?>
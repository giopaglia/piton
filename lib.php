<?php

define("PACKAGE_NAME", "DBFit");
define("MODELS_FOLDER", "models");

define("DEBUGMODE_ALG",   bindec('10'));
define("DEBUGMODE_DATA", bindec('100'));

define("DEBUGMODE", false);

$line = date('Y-m-d H:i:s') . " - " . $_SERVER["REMOTE_ADDR"];
file_put_contents('visitors.log', $line . PHP_EOL, FILE_APPEND);

// define("DEBUGMODE", DEBUGMODE_ALG);
// define("DEBUGMODE", DEBUGMODE_DATA | DEBUGMODE_ALG);
// define("DEBUGMODE", DEBUGMODE_DATA | DEBUGMODE_ALG | 128 | -1);
// define("DEBUGMODE", 4);

/* Library of generic utils */

function check_that(bool $cond, $msg = NULL)
{
  if (!$cond) {
    die($msg);
  }
}

function die_error($msg = NULL)
{
  if ($msg === NULL) {
    $msg = "An error occurred.";
  }
  die("<b>ERROR!</b> " . $msg);
}

function warn($msg)
{
  echo "<b>WARNING!</b> " . $msg . PHP_EOL;
}

function mysql_set($arr, $map_function = "mysql_quote_str") { return "(" . mysql_list($arr, $map_function) . ")"; }
function mysql_list($arr, $map_function = "mysql_backtick_str") { return join(", ", array_map($map_function, $arr)); }
function mysql_quote_str($str) { return "'$str'"; }
function mysql_backtick_str($str) { return "`$str`"; }

function &mysql_select(object &$db, string $sql, bool $silent = false) : object {

  if (!$silent) {
    echo "SQL:" . PHP_EOL . $sql . PHP_EOL;
  }
  $stmt = $db->prepare($sql);
  if (!$stmt)
    die_error("Incorrect SQL query:" . PHP_EOL . $sql);
  if (!$stmt->execute())
    die_error("Query failed:" . PHP_EOL . $sql);
  $res = $stmt->get_result();
  $stmt->close();
  if (!($res !== false))
    die_error("SQL query failed:" . PHP_EOL . $sql);
  return $res;
}

function mysql_number(float $x) : string { return (is_nan($x) ? "NULL" : strval($x)); }
function mysql_string(object $db, $x) : string { return ($x === NULL ? "NULL" : "'" . $db->real_escape_string(strval($x)) . "'"); }

function noop($str) { return $str; }

function safe_div($n, $d) { return $d == 0 ? NAN : $n / $d; }

function get_var_dump($a)  { ob_start(); var_dump($a); return ob_get_clean(); }
function die_var_dump($a)  { die(get_var_dump($a)); }
function toString($a)
{
  switch (true) {
    case is_array($a):
      return get_arr_dump($a);
      break;
    
    case $a === NULL:
      return "<i>NULL</i>";
      break;
    case $a === false:
      return "<i style='color:red'>false</i>";
      break;
    case $a === true:
      return "<i style='color:green'>true</i>";
      break;
    
    default:
      return strval($a);
      break;
  }
  die(get_var_dump($a));
}
function get_arr_dump(array $arr, $delimiter = ", ") {
  return "[" . array_list($arr) . "]";
}
function array_list(array $arr, $delimiter = ", ") {
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

# Source: https://stackoverflow.com/a/28033817/5646732
function sub_array(array $haystack, array $needle) { return array_intersect_key($haystack, array_flip($needle));}

function listify(&$v) { // TODO , $depth = 1
  if ($v === NULL) {
    $v = [];
  } else {
    $v = (is_array($v) ? $v : [$v]);
  }
}

function toList($v) { listify($v); return $v; }

function is_array_of_strings($arr) {
  if (!is_array($arr)) return false;
  foreach ($arr as $v)
    if (!is_string($v))
      return false;
  return true;
}
function clone_object(object $o) {return clone $o;}

// Set value in multi-dimensional array
// https://stackoverflow.com/questions/15483496/how-to-dynamically-set-value-in-multidimensional-array-by-reference
function arr_set_value(&$arr, $keyPath, $value, $allowNonExistentPaths = true) {
  $temp = &$arr;
  foreach ( $keyPath as $key ) {
    if ($allowNonExistentPaths && !isset($temp[$key])) {
      $temp[$key] = [];
    }
    $temp = &$temp[$key];
    // echo toString($temp) . PHP_EOL;
  }
  $temp = $value;
  // if ($allowNonExistentPaths)
  //   die_error();
  return $temp;
}

function arr_get_value(&$arr, $keyPath, $allowNonExistentPaths = false) {
  $temp = $arr;
  foreach ( $keyPath as $key ) {
    if (!$allowNonExistentPaths || isset($temp[$key])) {
      $temp = $temp[$key];
    } else {
      $temp = NULL;
      break;
    }
  }
  $out = $temp;
  // directly? return $temp;
  return $out;
}

# Source: https://www.php.net/manual/en/function.array-diff.php#110572
function array_equiv(array $A, array $B) {
  sort($A);
  sort($B);
  return $A == $B;
} 

# Source: https://stackoverflow.com/a/173479/5646732
function isAssoc(array $arr)
{
  if (array() === $arr) return false;
  return array_keys($arr) !== range(0, count($arr) - 1);
}

# https://stackoverflow.com/questions/4102777/php-random-shuffle-array-maintaining-key-value
# (see others at https://www.php.net/manual/en/function.shuffle.php#113790 )
function shuffle_assoc($list) { 
  if (!is_array($list)) return $list; 

  $keys = array_keys($list); 
  shuffle($keys); 
  $random = array(); 
  foreach ($keys as $key) { 
    $random[$key] = $list[$key]; 
  }
  return $random; 
} 

# https://www.php.net/manual/en/function.array-column.php#122738
function array_column_assoc($array, $column) {
  return array_combine(array_keys($array), array_column($array, $column));
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

function safe_basename($filename) {
  return str_replace("/", "-", $filename);
}

# Source of inspiration: https://www.php.net/manual/en/function.array-map.php#81767
function array_map_kv() {
  $args = func_get_args();
  $newargs = [array_shift($args)];
  foreach($args as $arr) {
    $newargs[] = array_keys($arr);
    $newargs[] = $arr;
  }
  return call_user_func_array("array_map", $newargs);
}

// # Source: https://www.php.net/manual/en/function.explode.php#111307
// Use preg_split istead
// function multiexplode(array $delimiters, string $string) : array {
//   $ready = str_replace($delimiters, $delimiters[0], $string);
//   return explode($delimiters[0], $ready);
// }

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
            $val = (isset($args[$j][$i])) ? $args[$j][$i] : NULL;
            $zipped[$i][$j] = $val;
        }
    }
    return $zipped;
}


function zip_assoc() {
  $args = func_get_args();

  $ruby = array_pop($args);
  if (is_array($ruby))
      $args[] = $ruby;

  $keys = array_keys($args[0]);
  foreach ($args as $arr) {
    if ($keys !== array_keys($arr)) {
      die_error("zip_assoc: expected same keys, but got: "
        . PHP_EOL . toString($keys)
        . PHP_EOL . toString(array_keys($arr))
      );
    }
  }

  $counts = array_map('count', $args);
  $count = ($ruby) ? min($counts) : max($counts);
  $zipped = array();

  foreach ($keys as $k) {
    foreach ($args as $j => $arr) {
      $val = (isset($arr[$k])) ? $arr[$k] : NULL;
      $zipped[$k][$j] = $val;
    }
  }
  return $zipped; // array_combine($keys, );
}

# Based onto: https://stackoverflow.com/a/27968556/5646732
function powerSet(array $array, bool $includeEmpty = true, int $maxSize = -1) {
  // add the empty set
  $results = [];
  if ($includeEmpty) {
    $results[] = [];
  }
  
  if ($maxSize == -1) {
    $maxSize = count($array);
  }
  if ($maxSize < 0 || $maxSize > count($array)) {
    die_error("powerSet(...): maxSize=$maxSize out of bounds [0," . count($array) . "]");
  }
  foreach ($array as $element) {
    foreach ($results as $combination) {
      $newElem = array_merge($combination,[$element]);
      if (count($newElem) <= $maxSize) {
        $results[] = $newElem;
      }
    }
    if (!$includeEmpty) {
      $newElem = [$element];
      if (count($newElem) <= $maxSize) {
        $results[] = $newElem;
      }
    }
  }

  return $results;
}

// print_r(powerSet([1,2,3,4]));
// print_r(powerSet([1,2,3,4],false));
// print_r(powerSet([1,2,3,4],false,0));
// print_r(powerSet([1,2,3,4],true,0));
// print_r(powerSet([1,2,3,4],false,1));
// print_r(powerSet([1,2,3,4],true,1));
// print_r(powerSet([1,2,3,4],false,-1));
// print_r(powerSet([1,2,3,4],true,-1));
// print_r(powerSet([1,2,3,4],false,0));
// print_r(powerSet([1,2,3,4],true,0));


# Files in directory
function filesin($a, $full_path = false, $sortby=false)
{
	$a = safeSuffix($a, "/");
	$ret = [];
	foreach (array_slice(scandir($a), 2) as $item)
		if (is_file($a . $item))
		{
			$f = ($full_path ? $a : "") . $item;
			if ($sortby==false)
				$ret[] = $f;
			else if ($sortby=="mtime")
				$ret[$f] = filemtime($a . "/" . $f);
		}
	
	if ($sortby=="mtime")
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

function postfixisify(&$string, $postfix) {
  if (!endsWith($string, $postfix)) {
    $string .= $postfix;
  }
}
function prefixisify(&$string, $prefix) {
  if (!startsWith($string, $prefix)) {
    $string = $prefix . $string;
  }
}
function depostfixify(&$string, $postfix) {
  if (endsWith($string, $postfix)) {
    $string = substr($string, 0, strlen($string)-strlen($postfix));
  }
}
function deprefixify(&$string, $prefix) {
  if (startsWith($string, $prefix)) {
    $string = substr($string, -strlen($prefix));
  }
}
function startsWith($haystack, $needle, $caseSensitive = true) { return $needle === "" ||  ($caseSensitive ? (strrpos($haystack, $needle, -strlen($haystack)) !== false) : (strripos($haystack, $needle, -strlen($haystack)) !== false)); }
function endsWith($haystack, $needle, $caseSensitive = true)   { return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && ($caseSensitive ? (strpos($haystack, $needle, $temp) !== false) : (stripos($haystack, $needle, $temp) !== false))); }
function safePrefix($haystack, $needle) { return (startsWith($haystack, $needle) ? $haystack : $needle . $haystack); }
function safeSuffix($haystack, $needle) { return (endsWith($haystack, $needle)   ? $haystack : $haystack . $needle); }
?>

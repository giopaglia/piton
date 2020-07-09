<?php

/* Library of generic utils */

define("MODELS_FOLDER", "models");

function mysql_set($arr, $map_function = "mysql_quote_str") { return "(" . mysql_list($arr, $map_function) . ")"; }
function mysql_list($arr, $map_function = "mysql_backtick_str") { return join(", ", array_map($map_function, $arr)); }
function mysql_quote_str($str) { return "'$str'"; }
function mysql_backtick_str($str) { return "`$str`"; }

function get_var_dump($a)  { ob_start(); var_dump($a); return ob_get_clean(); }
function die_var_dump($a)  { die(get_var_dump($a)); }

# Source: https://www.php.net/manual/en/debugger.php#118058
function console_log($data){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
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
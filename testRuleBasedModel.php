<html>
<head>
  <style>
  input[type=text], select, textarea {
    width: 100%;
    padding: 12px 20px;
    margin: 8px 0;
    display: inline-block;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
  }

  input[type=submit] {
    width: 100%;
    background-color: #4CAF50;
    color: white;
    padding: 14px 20px;
    margin: 8px 0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }

  input[type=submit]:hover {
    background-color: #45a049;
  }

  div {
    border-radius: 5px;
    background-color: #f2f2f2;
    padding: 20px;
  }
  </style>
</head>
<body>
<?php

chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";
echo "<pre>";
$data = Instances::createFromARFF("query_processato_femmine_2_NORMALOIDI_no_FRAX_bilanciato.arff");
echo "</pre>";

if (isset($_GET["rbmodel"])) {
  global $data;
  echo "<pre>";
  $a = RuleBasedModel::fromString(trim($_GET["rbmodel"])
   . "
  => normaloide"
  ,
  new DiscreteAttribute("T_score_normaloidi", "output enum", ["osteoporosi", "normaloide"]));

  // echo "MODEL:" . PHP_EOL . $a . PHP_EOL;
  echo "</pre>";
  echo RuleBasedModel::HTMLShowTestResults($a->test($data, true)) . PHP_EOL;
}
?>
<form method="get" action="<?php echo $_SERVER['PHP_SELF'];?>">
<textarea name="rbmodel" placeholder="Paste rule-based model here..."></textarea>
<button type="submit">
</form>
</body>
</html>

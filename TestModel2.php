<?php

chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";

echo "<html>";
echo "<body>";
// ClassificationRule::fromString(" => [2]");
// // ClassificationRule::fromString(" => [-1]");
// ClassificationRule::fromString("()   => [0]");
// ClassificationRule::fromString("(Ciao = 2) => [1]");
// ClassificationRule::fromString("(Ciao >= 2)  => [1]");
// // ClassificationRule::fromString("(Ciao != 2)  => [1]");
// // ClassificationRule::fromString("(Ciao == 2 and Ciao2 <= 1)    => [1]");
// // ClassificationRule::fromString("(Ciao == 2     and    Ciao2 <= 1)    => [1]");
// ClassificationRule::fromString("(Ciao = 2 and Ciao2 <= 1)    => [1]");
// ClassificationRule::fromString("(Ciao = 2     and    Ciao2 <= 1)    => [1]");

// $a = RuleBasedModel::fromString("(Ciao = 2     and    Ciao2 <= 1)    => [1]
// (Ciao2 = 2     and    Ciao6 <= 1)    => [1]
// (Ciao3 = 2     and    Ciao <= 1)    => [1]
// (Ciao5 = 2     and    Ciao2 <= 1)    => [0]
// (Ciao5 = 2     and    Ciao2 <= 1    and    Ciao2 <= 1)    => [4]
// (Ciao5 = 2)    => [0]
//   => [3]");

echo "<pre>";
$data = Instances::createFromARFF("query_processato_femmine_2_NORMALOIDI_no_FRAX_bilanciato.arff");
echo "</pre>";
// echo $data->toString() . PHP_EOL;

function testOsteoporosisRule($rules_str) {  
  global $data;
  echo "<pre>";
  $a = RuleBasedModel::fromString($rules_str
   . "
  => normaloide"
  ,
  new DiscreteAttribute("T_score_normaloidi", "output enum", ["osteoporosi", "normaloide"]));

  // echo "MODEL:" . PHP_EOL . $a . PHP_EOL;
  echo "</pre>";
  echo RuleBasedModel::HTMLShowTestResults($a->test($data, true)) . PHP_EOL;
}

testOsteoporosisRule("(FRATTURA VERTEBRE = più di 1) AND(PATOLOGIA ESOFAGEA = 1) AND( TERAPIA STATO = Mai) AND (ETÀ SCAN >= 55): osteoporosi (13.0/1.0)");
testOsteoporosisRule("(TERAPIA OSTEOPROTETTIVA SPECIFICA CHECKBOX = 0) AND (FRATTURA VERTEBRE = più di 1) AND(PATOLOGIA ESOFAGEA = 1) AND( TERAPIA STATO = Mai) AND(PATOLOGIA CARDIACA = 0) AND (ETÀ SCAN >= 60): osteoporosi (13.0/1.0)");
testOsteoporosisRule("(FRATTURA VERTEBRE != 0) AND(PATOLOGIA ESOFAGEA = 1) AND( TERAPIA STATO = Mai) AND (ETÀ SCAN >= 60): osteoporosi");
testOsteoporosisRule("(FRATTURA SITI DIVERSI CHECKBOX = 0) AND (FRATTURA VERTEBRE = 1): osteoporosi (63.0/10.0)");
testOsteoporosisRule("(SINTOMI_VASOMOTORI = 0) AND  (ABUSO FUMO = 0) AND  (PATOLOGIA VASCOLARE = 0) AND  (FRATTURA FAMILIARITA’ = 0) AND  (FRATTURA VERTEBRE = più di 1): osteoporosi (12.43/3.66)");
testOsteoporosisRule("(FRATTURA SITI DIVERSI CHECKBOX = 1): osteoporosi (60.0/11.0)");
testOsteoporosisRule("(FRATTURA VERTEBRE != 0): osteoporosi (60.0/11.0)");
testOsteoporosisRule("(FRATTURA VERTEBRE = 1): osteoporosi (60.0/11.0)");
testOsteoporosisRule("(FRATTURA VERTEBRE = più di 1): osteoporosi (60.0/11.0)");
testOsteoporosisRule("
(ETÀ SCAN >= 62.109589) and (BMI <= 25.5389) => T_score_normaloidi=osteoporosi (31.0/7.0)
(ETÀ SCAN >= 54.271233) and (BMI <= 22.9398) => T_score_normaloidi=osteoporosi (31.0/7.0)
(FRATTURA VERTEBRE = più di 1) => T_score_normaloidi=osteoporosi (31.0/7.0)
(FRATTURA VERTEBRE = 1) => T_score_normaloidi=osteoporosi (31.0/7.0)");

testOsteoporosisRule("
(ETÀ SCAN >= 62.109589) and (BMI <= 25.5389) => T_score_normaloidi=osteoporosi (31.0/7.0)
(ETÀ SCAN >= 54.271233) and (BMI <= 22.9398) => T_score_normaloidi=osteoporosi (31.0/7.0)
(FRATTURA VERTEBRE != 0) => T_score_normaloidi=osteoporosi (31.0/7.0)");

testOsteoporosisRule("
(TERAPIA OSTEOPROTETTIVA SPECIFICA CHECKBOX = 0) AND (FRATTURA VERTEBRE = 1): osteoporosi (39.0/3.0)
(TERAPIA OSTEOPROTETTIVA SPECIFICA CHECKBOX = 0) AND (FRATTURA VERTEBRE = più di 1) AND (PATOLOGIA ESOFAGEA = 0): osteoporosi (50.0/10.0)");

testOsteoporosisRule("
(TERAPIA OSTEOPROTETTIVA SPECIFICA CHECKBOX = 0) AND (FRATTURA VERTEBRE != 0): osteoporosi (39.0/3.0)");

testOsteoporosisRule("
(FRATTURA SITI DIVERSI CHECKBOX = 0) AND ( FRATTURA VERTEBRE != 0): osteoporosi (108.0/32.0)   ");

// $data->appendPredictions($a);
// echo $data->toString() . PHP_EOL;


echo "</body>";
echo "</html>";
      
?>

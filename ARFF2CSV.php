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

echo RuleBasedModel::fromString("(Ciao = 2     and    Ciao2 <= 1)    => [1]
(Ciao2 = 2     and    Ciao6 <= 1)    => [1]
(Ciao3 = 2     and    Ciao <= 1)    => [1]
(Ciao5 = 2     and    Ciao2 <= 1)    => [0]
(Ciao5 = 2     and    Ciao2 <= 1    and    Ciao2 <= 1)    => [4]
(Ciao5 = 2)    => [0]
  => [3]");

echo "</pre>";
// $data = Instances::createFromARFF("query_processato_femmine_Tscore_COLLO_bilanciato.arff");
// $data->save_CSV("query_processato_femmine_Tscore_COLLO_bilanciato.arff.csv");

// $data = Instances::createFromARFF("query_processato_femmine_Tscore_colonna_bilanciato.arff");
// $data->save_CSV("query_processato_femmine_Tscore_colonna_bilanciato.arff.csv");

// echo $data;

?>

<?php

chdir(dirname(__FILE__));
ini_set('xdebug.halt_level', E_WARNING|E_NOTICE|E_USER_WARNING|E_USER_NOTICE);
ini_set('memory_limit', '1024M'); // or you could use 1G
ini_set('max_execution_time', 3000);
set_time_limit(3000);

include "lib.php";
include "local-lib.php";

include "DBFit.php";

/*
TODOs:
- Text processing via NlpTools
- Parallelize code ( https://medium.com/@rossbulat/true-php7-multi-threading-how-to-rebuild-php-and-use-pthreads-bed4243c0561 )
- Implement an unweighted version of Instances
- Fix those == that should actually be === https://stackoverflow.com/questions/12151997/why-does-1234-1234-test-evaluate-to-true#comment16259587_12151997
- Add method setSQL() that directly asks for the SELECT - FROM - WHERE query;
- Make sql querying secure with addslashes or whatever
 */

/****************************************************
*                                                   *
*                 Here I test stuff                 *
*                                                   *
****************************************************/

testMed();
exit();
testSPAM();
exit();
testSillyWithJoin();
exit();
testSilly();
testWinery();
exit();
testCovid();
exit();
testDiabetes();
exit();
exit();
echo "All good" . PHP_EOL;



function testMed() {
  $db = getDBConnection();

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);

  /*
  select * from Anamnesi
  inner join Diagnosi on Anamnesi.ID_REFERTO = Diagnosi.ID_REFERTO
    and Anamnesi.DATA_SALVA = Diagnosi.DATA_SALVA
  inner join Referti on Anamnesi.ID_REFERTO = Referti.ID
  inner join Pazienti on Anamnesi.ID = Referti.ID_PAZIENTE
  //  inner join Spine on Pazienti.PATIENT_KEY = Spine.PATIENT_KEY
  //    and Anamnesi.DATA_SALVA = Spine.DATA_SALVA
  //  inner join ScanAnalysis on Pazienti.PATIENT_KEY = ScanAnalysis.PATIENT_KEY
  //    and Anamnesi.DATA_SALVA = ScanAnalysis.DATA_SALVA
    
   */
  $db_fit->setTables([
    "Anamnesi",
    // ["Diagnosi",
    //  ["Anamnesi.ID_REFERTO = Diagnosi.ID_REFERTO",
    //    "Anamnesi.DATA_SALVA = Diagnosi.DATA_SALVA"]], 
    ["Referti", "Anamnesi.ID_REFERTO = Referti.ID"], 
    ["RaccomandazioniTerapeutiche", "RaccomandazioniTerapeutiche.ID_REFERTO = Referti.ID"], 
    ["RaccomandazioniTerapeuticheUnitarie", "RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"], 
    ["Pazienti", "Pazienti.ID = Referti.ID_PAZIENTE"]
  ]);
  $db_fit->setIdentifierColumnName("Referti.ID");
  $db_fit->setDefaultOption("TextTreatment", ["BinaryBagOfWords", 10]);
  $db_fit->setColumns("*");
  // $db_fit->setLimit(10);
  // $db_fit->setLimit(1000);
  $db_fit->setOutputColumnName("RaccomandazioniTerapeuticheUnitarie.TIPO", true);
  $lr = new PRip();
  // $lr->setNumOptimizations(10); TODO
  $lr->setNumOptimizations(3);
  $db_fit->setLearner($lr);
  $db_fit->test_all_capabilities();
  $db_fit->predictByIdentifier(10);
  $db_fit->predictByIdentifier(15);
  $db_fit->predictByIdentifier(1);
  $db_fit->predictByIdentifier(2);
  $db_fit->predictByIdentifier(3);
}

function testSPAM() {
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = "spam";
  $columns = [["Category", "ForceCategorical"], ["Message", ["BinaryBagOfWords", 10]]];
  $output_column_name = "Category";

  $db_fit = new DBFit($db);
  $db_fit->setIdentifierColumnName("ID");
  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setTables($table_names);
  $db_fit->setColumns($columns);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();
  $db_fit->predictByIdentifier(1);
}

function testSilly() {
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["patients"];
  $columns = ["ID", "Gender", ["BirthDate", "YearsSince", "Age"], "Sillyness"];
  $where_criteria = NULL;
  $output_column_name = "Sillyness";

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode("FullTraining");
  $db_fit->setTables($table_names);
  $db_fit->setColumns($columns);
  $db_fit->setWhereCriteria($where_criteria);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();
}

function testWinery() {
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["winery"];
  $columns = [
    ["winery.country", "ForceCategorical"],
    ["winery.description", ["BinaryBagOfWords", 14]],
  ];
  $where_criteria = [];
  $output_column_name = "winery.country";

  $db_fit = new DBFit($db);
  // $db_fit->setTrainingMode("FullTraining");
  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setTables($table_names);
  $db_fit->setColumns($columns);
  $db_fit->setWhereCriteria($where_criteria);
  $db_fit->setLimit(100);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();
  $db_fit->loadModel("models/2020-07-20_22:25:14");
  $db_fit->test(NULL);
}

function testSillyWithJoin() {
  
  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["patients", "reports"];
  $columns = [
    "patients.Gender",
    "patients.ID",
    ["patients.BirthDate", "MonthsSince", "MonthAge"],
    "patients.Sillyness",
    "reports.Date",
    ["reports.PatientState", NULL, "State"],
    ["reports.PatientHeartbeatMeasure", NULL, "Heartbeat"],
    ["reports.PatientID", NULL, "ID"],
    ["reports.DoctorIsFrares"]
  ];
  $where_criteria = ["patients.ID = reports.PatientID"];
  $output_column_name = "patients.Sillyness";

  $db_fit = new DBFit($db);
  $db_fit->setTrainingMode([.8, .2]);
  $db_fit->setTables($table_names);
  $db_fit->setColumns($columns);
  $db_fit->setWhereCriteria($where_criteria);
  $db_fit->setOutputColumnName($output_column_name);
  // $db_fit->setModelType($model_type);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();

}

function testCovid() {

  $db = getDBConnection();
  $model_type = "RuleBased";
  $learning_method = "PRip";

  $table_names = ["covid19_italy_province"];
  $columns = [["Date", "DaysSince", "DaysAgo"] , ["ProvinceCode", "ForceCategorical"], "Date", "TotalPositiveCases"];
  $where_criteria = NULL;
  $output_column_name = "ProvinceCode";

  $db_fit = new DBFit($db);
  // $db_fit->setModelType($model_type);
  $db_fit->setTables($table_names);
  $db_fit->setColumns($columns);
  $db_fit->setWhereCriteria($where_criteria);
  $db_fit->setOutputColumnName($output_column_name);
  $db_fit->setLimit(1000);
  $db_fit->setLearningMethod($learning_method);
  $db_fit->test_all_capabilities();

}

function testDiabetes() {
  $trainingMode = [.8, .2];
  //$trainingMode = "FullTraining";
  $data = Instances::createFromARFF("diabetes.arff");
  /* training modes */
  switch (true) {
    /* Full training: use data for both training and testing */
    case $trainingMode == "FullTraining":
      $trainData = $data;
      $testData = $data;
      break;
    
    /* Train+test split */
    case is_array($trainingMode):
      $trRat = $trainingMode[0]/($trainingMode[0]+$trainingMode[1]);
      list($trainData, $testData) = Instances::partition($data, $trRat);
      
      break;
    
    default:
      die_error("Unknown training mode ('$trainingMode')");
      break;
  }

  /* Train */
  $model = new RuleBasedModel();
  $learner = new PRip();
  $model->fit($trainData, $learner);

  echo "Ultimately, here are the extracted rules: " . PHP_EOL;
  foreach ($model->getRules() as $x => $rule) {
    echo $x . ": " . $rule->toString() . PHP_EOL;
  }

  /* Test */
  $ground_truths = [];
  $classAttr = $testData->getClassAttribute();

  for ($x = 0; $x < $testData->numInstances(); $x++) {
    $ground_truths[] = $classAttr->reprVal($testData->inst_classValue($x));
  }

  // $testData->dropOutputAttr();
  $predictions = $model->predict($testData);

  // echo "\$ground_truths : " . get_var_dump($ground_truths) . PHP_EOL;
  // echo "\$predictions : " . get_var_dump($predictions) . PHP_EOL;
  $negatives = 0;
  $positives = 0;
  foreach ($ground_truths as $val) {
    echo str_pad($val, 10, " ");
  }
  echo "\n";
  foreach ($predictions as $val) {
    echo str_pad($val, 10, " ");
  }
  echo "\n";
  foreach ($ground_truths as $i => $val) {
    if ($ground_truths[$i] != $predictions[$i]) {
      $negatives++;
    } else {
      $positives++;
    }
  }
  echo "Test accuracy: " . ($positives/($positives+$negatives));
  echo "\n";

}
?>
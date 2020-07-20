<?php

include "lib.php";
include "local-lib.php";

include "DBFit.php";

 /****************************************************
 *                                                   *
 *                 Here I test stuff                 *
 *                                                   *
 ****************************************************/

$db = getDBConnection();
$model_type = "RuleBased";
$learning_method = "RIPPER";



$table_names = ["patients"];
$columns = ["ID", "Gender", ["BirthDate", "YearsSince", "Age"], "Sillyness"];
$join_criterion = NULL;
$output_column_name = "Sillyness";

$db_fit = new DBFit($db);
$db_fit->setTrainingMode("FullTraining");
$db_fit->setModelType($model_type);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($columns);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();




$table_names = ["winery"];
$columns = [
	["winery.country", "ForceCategorical"],
	["winery.description", ["BinaryBagOfWords", 14]],
];
$join_criterion = [];
$output_column_name = "winery.country";

$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
// $db_fit->setTrainingMode("FullTraining");
$db_fit->setTrainingMode([.8, .2]);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($columns);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setLimit(100);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();
$db_fit->loadModel("models/2020-07-20_19:57:01");
$db_fit->test(NULL);




$table_names = ["patients", "reports"];
$columns = [
	"patients.Gender",
	"patients.ID",
	["patients.BirthDate", "MonthsSince", "MonthAge"],
	"patients.Sillyness",
  "reports.Date",
  ["reports.PatientState", NULL, "State"],
  ["reports.PatientHeartbeatMeasure", NULL, "Heartbeat"],
  ["reports.PatientID", NULL, "ID"]
];
$join_criterion = ["patients.ID = reports.PatientID"];
$output_column_name = "patients.Sillyness";


$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setTrainingMode([.8, .2]);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($columns);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();

echo "All good" . PHP_EOL;

exit();


$table_names = ["covid19_italy_province"];
$columns = [["Date", "DaysSince", "DaysAgo"] , ["ProvinceCode", "ForceCategorical"], "Date", "TotalPositiveCases"];
$join_criterion = NULL;
$output_column_name = "ProvinceCode";

$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($columns);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setLimit(1000);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();

echo "All good" . PHP_EOL;
exit();

?>
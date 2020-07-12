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
$column = ["ID", "Gender", ["BirthDate", "YearsSince", "Age"], "Sillyness"];
$join_criterion = NULL;
$output_column_name = "Sillyness";

$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($column);
//$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();


$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($column);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();

/* // TODO
$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setSQL("SELECT * FROM `reports`");
$db_fit->setColumnType("BirthDate", "YearsSince");
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();
*/

$table_names = ["covid19_italy_province.csv"];
$column = [["Date", "DaysSince"] , "ProvinceCode", "TotalPositiveCases"];
$join_criterion = NULL;
$output_column_name = "TotalPositiveCases";

$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($column);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();

exit();


$table_names = ["patients", "reports"];
$column = ["patients.Gender", "patients.ID", "patients.BirthDate", "patients.Sillyness",
         "reports.Date", "reports.PatientState", "reports.PatientHeartbeatMeasure", "reports.PatientID"];
$join_criterion = ["patients.ID = reports.PatientID"];
$output_column_name = "patients.Sillyness";


$db_fit = new DBFit($db);
$db_fit->setModelType($model_type);
$db_fit->setTableNames($table_names);
$db_fit->setColumns($column);
$db_fit->setJoinCriterion($join_criterion);
$db_fit->setOutputColumnName($output_column_name);
$db_fit->setModelType($model_type);
$db_fit->setLearningMethod($learning_method);
$db_fit->test_all_capabilities();

echo "All good" . PHP_EOL;

exit();

// TODO make sql querying secure with addslashes or whatever
// Useful sources: 
// - https://stackoverflow.com/questions/21088937/is-this-mysqli-safe
// - https://stackoverflow.com/questions/28606581/which-is-a-right-safe-mysqli-query
// - https://stackoverflow.com/questions/34566530/prevent-sql-injection-in-mysqli
// - https://stackoverflow.com/questions/20179565/php-secure-mysqli-query
// - https://stackoverflow.com/questions/15062290/how-to-use-mysqli-securely
// - https://www.php.net/manual/en/mysqli-stmt.bind-result.php
// - https://stackoverflow.com/questions/330268/i-have-an-array-of-integers-how-do-i-use-each-one-in-a-mysql-query-in-php
// 
?>
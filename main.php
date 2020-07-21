<?php

include "lib.php";
include "local-lib.php";

include "DBFit.php";

/****************************************************
*                                                   *
*                 Here I test stuff                 *
*                                                   *
****************************************************/


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



function testSilly() {
	$db = getDBConnection();
	$model_type = "RuleBased";
	$learning_method = "RIPPER";

	$table_names = ["patients"];
	$columns = ["ID", "Gender", ["BirthDate", "YearsSince", "Age"], "Sillyness"];
	$join_criterion = NULL;
	$output_column_name = "Sillyness";

	$db_fit = new DBFit($db);
	$db_fit->setTrainingMode("FullTraining");
	$db_fit->setTableNames($table_names);
	$db_fit->setColumns($columns);
	$db_fit->setJoinCriterion($join_criterion);
	$db_fit->setOutputColumnName($output_column_name);
	$db_fit->setModelType($model_type);
	$db_fit->setLearningMethod($learning_method);
	$db_fit->test_all_capabilities();
}

function testWinery() {
	$db = getDBConnection();
	$model_type = "RuleBased";
	$learning_method = "RIPPER";

	$table_names = ["winery"];
	$columns = [
		["winery.country", "ForceCategorical"],
		["winery.description", ["BinaryBagOfWords", 14]],
	];
	$join_criterion = [];
	$output_column_name = "winery.country";

	$db_fit = new DBFit($db);
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
	$db_fit->loadModel("models/2020-07-20_22:25:14");
	$db_fit->test(NULL);
}

function testSillyWithJoin() {
	
	$db = getDBConnection();
	$model_type = "RuleBased";
	$learning_method = "RIPPER";

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
	$join_criterion = ["patients.ID = reports.PatientID"];
	$output_column_name = "patients.Sillyness";

	$db_fit = new DBFit($db);
	$db_fit->setTrainingMode([.8, .2]);
	$db_fit->setTableNames($table_names);
	$db_fit->setColumns($columns);
	$db_fit->setJoinCriterion($join_criterion);
	$db_fit->setOutputColumnName($output_column_name);
	$db_fit->setModelType($model_type);
	$db_fit->setLearningMethod($learning_method);
	$db_fit->test_all_capabilities();

}

function testCovid() {

	$db = getDBConnection();
	$model_type = "RuleBased";
	$learning_method = "RIPPER";

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
	  $ground_truths[] = $testData->inst_classValue($x);
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
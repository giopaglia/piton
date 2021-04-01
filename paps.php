<?php
include_once "lib.php";
include_once "DBFit.php";
include_once "local-lib.php";
include_once "DiscriminativeModel/WittgensteinLearner.php";
include_once "DiscriminativeModel/SklearnLearner.php";

$db = getDBConnection();

$experimentID = 1;
$model_name = "data-Terapie osteoprotettive=Terapie osteoprotettive_Alendronato";
/* $model_name="Iris"; */
$model_id = 1;

/* $trainData = Instances::createFromDB($db, "X" . md5("data-Terapie osteoprotettive=Terapie osteoprotettive_Alendronato-TRAIN"));
$testData = Instances::createFromDB($db, "X" . md5("data-Terapie osteoprotettive=Terapie osteoprotettive_Alendronato-TEST")); */
/* $testData->save_ARFF("testData.arff"); */
/* $iris = Instances::createFromARFF("myIris.arff"); */
$trainData = Instances::createFromARFF("trainData.arff");
$testData = Instances::createFromARFF("testData.arff");

#$learner = new WittgensteinLearner("RIPPERk", $db, 2);
$learner = new SklearnLearner($db);

/* Train */
$model = $learner->initModel();
$model->fit($trainData, $learner);

echo "MODEL:" . PHP_EOL . $model . PHP_EOL;

// $model->save(join_paths(MODELS_FOLDER, $model_name));
/* $model->saveToDB($db, [$experimentID, $model_name], $model_id, $iris, $iris);  */
$model->saveToDB($db, [$experimentID, $model_name], $model_id); 
/* $model->dumpToDB($db, $model_id);

/* echo "Trained model '$model_name'." . PHP_EOL;

echo $model . PHP_EOL; */

?>

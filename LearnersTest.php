<?php
include_once "lib.php";
include_once "DBFit.php";
include_once "local-lib.php";
include_once "DiscriminativeModel/WittgensteinLearner.php";
include_once "DiscriminativeModel/SklearnLearner.php";

$db = getDBConnection();

/**-------------------------------*/
/*** Testing learner performances */
/**-------------------------------*/

/** Number of executions to evaluate average execution time */
$N_exec = 20;
/** File were I store the testing results */
$testResults = fopen("test_results.txt", "w");
/** Execution times strings to append in the test_result file */
$exec_times = "Learner\tAlgorithm\tExecutionTime (average execution time on" . $N_exec . " executions\n";

/** Training dataset used for testing */
$trainData = Instances::createFromARFF("arff/trainData.arff");
/** Testing dataset used for testing */
$testData = Instances::createFromARFF("arff/testData.arff");

/** General info to save into the db */
$experimentID = 1;
$model_name = "data-Terapie osteoprotettive=Terapie osteoprotettive_Alendronato";
$model_id = 1;

/** First round: WittgensteinLearner using RIPPERk algorithm */
$learner = new WittgensteinLearner("RIPPERk", $db);

/** Model init */
$model = $learner->initModel();

/** Training */
$model->fit($trainData, $learner);

/** Printing model */
echo "MODEL created using WittgensteinLearner RIPPERk algorithm:" . PHP_EOL . $model . PHP_EOL;
fwrite($testResults, "MODEL created using WittgensteinLearner RIPPERk algorithm:\n" . $model . "\n");

/** Saving model to DB to confront accuracies (it also prints them on the cli) */
/** TODO: saving this to the file, how can I access them? */
$model->saveToDB($db, [$experimentID, $model_name], $model_id, $testData, $trainData);

/** Evaluating average execution time per N executions */
$start = microtime(true);
for ($i = 0; $i < $N_exec; $i++)
{
  /** Model init */
  $model = $learner->initModel();

  /** Training */
  $model->fit($trainData, $learner);
}
$time_elapsed_secs = microtime(true) - $start;
echo "Average execution time: " . $time_elapsed_secs/$N_exec . " s" . PHP_EOL;
$exec_times .= "WittgensteinLearner\tRIPPERk\t" . $time_elapsed_secs/$N_exec . " s\n";

/** Second round: WittgensteinLearner using IREP algorithm */
$learner = new WittgensteinLearner("IREP", $db);

/** Model init */
$model = $learner->initModel();

/** Training */
$model->fit($trainData, $learner);

/** Printing model */
echo "MODEL created using WittgensteinLearner IREP algorithm:" . PHP_EOL . $model . PHP_EOL;
fwrite($testResults, "MODEL created using WittgensteinLearner IREP algorithm:\n" . $model . "\n");

/** Saving model to DB to confront accuracies (it also prints them on the cli) */
/** TODO: saving this to the file, how can I access them? */
$model->saveToDB($db, [$experimentID, $model_name], $model_id, $testData, $trainData);

/** Evaluating average execution time per N executions */
$start = microtime(true);
for ($i = 0; $i < $N_exec; $i++)
{
  /** Model init */
  $model = $learner->initModel();

  /** Training */
  $model->fit($trainData, $learner);
}
$time_elapsed_secs = microtime(true) - $start;
echo "Average execution time: " . $time_elapsed_secs/$N_exec . " s" . PHP_EOL;
$exec_times .= "WittgensteinLearner\tIREP\t" . $time_elapsed_secs/$N_exec . " s\n";

/** Third round: SklearnLearner using CART algorithm */
$learner = new SklearnLearner("CART", $db);

/** Model init */
$model = $learner->initModel();

/** Training */
$model->fit($trainData, $learner);

/** Printing model */
echo "MODEL created using SklearnLearner CART algorithm:" . PHP_EOL . $model . PHP_EOL;
fwrite($testResults, "MODEL created using SklearnLearner CART algorithm:\n" . $model . "\n");

/** Saving model to DB to confront accuracies (it also prints them on the cli) */
/** TODO: saving this to the file, how can I access them? */
$model->saveToDB($db, [$experimentID, $model_name], $model_id, $testData, $trainData);

/** Evaluating average execution time per N executions */
$start = microtime(true);
for ($i = 0; $i < $N_exec; $i++)
{
  /** Model init */
  $model = $learner->initModel();

  /** Training */
  $model->fit($trainData, $learner);
}
$time_elapsed_secs = microtime(true) - $start;
echo "Average execution time: " . $time_elapsed_secs/$N_exec . " s" . PHP_EOL;
$exec_times .= "SklearnLearner\t\tCART\t" . $time_elapsed_secs/$N_exec . " s\n";

/** Fourth round: PRIP */
$learner = new PRip(NULL);
$numOptimizations = 2;
$numFolds = 5;
$minNo = 2;
$learner->setNumOptimizations($numOptimizations);
$learner->setNumFolds($numFolds);
$learner->setMinNo($minNo);

/** Model init */
$model = $learner->initModel();

/** Training */
$model->fit($trainData, $learner);

/** Printing model */
echo "MODEL created using PRip:" . PHP_EOL . $model . PHP_EOL;
fwrite($testResults, "MODEL created using PRip:\n" . $model . "\n");

/** Saving model to DB to confront accuracies (it also prints them on the cli) */
/** TODO: saving this to the file, how can I access them? */
$model->saveToDB($db, [$experimentID, $model_name], $model_id, $testData, $trainData);

/** Evaluating average execution time per N executions */
$start = microtime(true);
for ($i = 0; $i < $N_exec; $i++)
{
  /** Model init */
  $model = $learner->initModel();

  /** Training */
  $model->fit($trainData, $learner);
}
$time_elapsed_secs = microtime(true) - $start;
echo "Average execution time: " . $time_elapsed_secs/$N_exec . " s" . PHP_EOL;
$exec_times .= "piton\t\t\tPRip\t" . $time_elapsed_secs/$N_exec . " s\n";

/** Writing execution times to test_results */
fwrite($testResults, $exec_times);
/** Closing the file */
fclose($testResults);

?>

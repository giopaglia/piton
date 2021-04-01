<?php

include_once "Learner.php";

/*
 * Interface for Sklearn learner.
 * 
 * This class offers compatibility with the python sklearn package, which
 * implements an optimised version of the CART algorithm for the creation of
 * decision trees. These trees are then converted into RuleBasedModels.
 * 
 * The package must be installed on your operating system or python environment.
 * To install, use:
 *    $ pip3 install -U scikit-learn
 */
class SklearnLearner extends Learner {    
  /**
   * A SklearnLearner needs a connection to a db to exchange data.
   */
  private $db;

  /*** Options that are useful during the training stage. */

  /** ... */

  /***
   * Limits for early-stopping. Intended for enhancing model interpretability and limiting training time
   * on noisy datasets. Not specifically intended for use as a hyperparameter, since pruning already occurs
   * during training, though it is certainly possible that tuning could improve model performance.
   */

   /** ... */

  /**
   * The constructor of the SklearnLearner.
   * A database connection is required, but can be changed later on.
   * The other options are set to NULL and will be set by python to their default values, and can be set later on.
   */
  function __construct(object $db) {
    $this->setDBConnection($db);
    /** Options */
    /** ... */
  }

  /**
   * Gives information about the database which the learner is connected,
   * from which it will communicate with the python script.
   */
  function getDBConnection() : object {
    return $this->db;
  }

  /**
   * Sets the database used for the communication with the python script
   */
  function setDBConnection(object $db) :void {
    $this->db = $db;
  }

  /**
   * Returns an uninitialized DiscriminativeModel.
   */
  function initModel() : DiscriminativeModel {
    return new RuleBasedModel();
  }

  /**
   * Builds a model through a specified wittgenstein algorithm.
   * 
   * @param model the model to train.
   * @param data the training data (wrapped in a structure that holds the appropriate header information for the attributes).
   */
  function teach(DiscriminativeModel &$model, Instances $data) {

    /** DB used to communicate with python */
    $db = $this->getDBConnection();

    /** Options */
    /** ... */

    /** Information about the attributes of the training data set */
    $attributes = $data->getAttributes();
    $classAttr = $data->getClassAttribute();
    
    /** Saving the training data set in a temporary table in the database */
    $tableName = "reserved__tmpWittgensteinTrainData" . uniqid();
    $data->SaveToDB($db, $tableName);

    /** If a parameter is NULL, I translate it to None */
    $getParameter = function ($parameter) {
      if ($parameter === NULL) {
        return "None";
      } else {
        return addcslashes($parameter, '"');
      }
    };

    /** Call to the python script that will use the training algorithm of the library */
    $command = escapeshellcmd("python DiscriminativeModel/PythonLearners/sklearn_learner.py "
                              . $getParameter($tableName));
    $output = shell_exec($command);
    
    /** Drop of the temporary table for safety reason */
    $sql = "DROP TABLE $tableName";
    mysql_prepare_and_executes($db, $sql);

    /** Parsing of the extracted rules to a string I can use to build a RuleBasedModel */
    echo $output;
    preg_match('/extracted_rule_based_model:(.*?)\[(.*?)\]/ms', $output, $matches);
    if (empty($matches[0])) {
      die_error("The SklearnLearner using $classifier did not return a valid RuleBasedModel." . PHP_EOL);
    }
    $rule = $matches[0];
    if (substr($rule, 0, strlen('extracted_rule_based_model: [')) == 'extracted_rule_based_model: [') {
      $rule = substr($rule, strlen('extracted_rule_based_model: ['));
    }
    $rule = rtrim($rule, "]");
    
    /** Creation of the rule based model; nb: I cannot use the same model or it will not update outside of the function */
    $newModel = RuleBasedModel::fromString($rule, $classAttr);

    /** Model update */
    $model->setRules($newModel->getRules());
    $model->setAttributes($attributes);
  }
}
?>

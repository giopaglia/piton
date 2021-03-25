<?php

include_once "Learner.php";

/*
 * Interface for wittgenstein learner
 */
class WittgensteinLearner extends Learner {
    
  /** A WittgensteinLearner needs a connection to a db to exchange data */
  private $db;

  /*** Options that are useful during the training stage */

  /**
   * Number of RIPPERk optimization iterations.
   * int, default=2
   */
  private $k;  

  /**
   * Terminate Ruleset grow phase early if a Ruleset description length is encountered
   * that is more than this amount above the lowest description length so far encountered.
   * int, default=64
   */
  private $dlAllowance;  

  /**
   * Proportion of training set to be used for pruning.
   * float, default=.33
   */
  private $pruneSize;

  /**
   * Fit apparent numeric attributes into a maximum of n_discretize_bins discrete bins,
   * inclusive on upper part of range. Pass None to disable auto-discretization.
   * int, default=10
   */
  private $nDiscretizeBins;

  /***
   * Limits for early-stopping. Intended for enhancing model interpretability and limiting training time
   * on noisy datasets. Not specifically intended for use as a hyperparameter, since pruning already occurs
   * during training, though it is certainly possible that tuning could improve model performance.
   */

  /**
   * Maximum number of rules
   * int, default=None
   */
  private $maxRules;

  /**
   * Maximum number of conds per rule.
   * int, default=None
   */
  private $maxRuleConds;

  /**
   * Maximum number of total conds in entire ruleset.
   * int, default=None
   */
  private $maxTotalConds;

  /**
   * Random seed for repeatable results.
   * int, default=None
   */
  private $randomState;

  /**
   * Output progress, model development, and/or computation.
   * Each level includes the information belonging to lower-value levels.
   *                1: Show results of each major phase
   *                2: Show Ruleset grow/optimization steps
   *                3: Show Ruleset grow/optimization calculations
   *                4: Show Rule grow/prune steps
   *                5: Show Rule grow/prune calculations
   * int, default=0
   */
  private $verbosity;
  
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
   * Gets information about the number of RIPPERk optimization iterations.
   */
  function getK() : ?int {
    return $this->k;
  }

  /**
   * Sets the number of RIPPERk optimization iterations.
   */
  function setK(?int $k) : void {
    $this->k = $k;
  }

  /**
   * Gets information about the description length allowance.
   */
  function getDlAllowance() : ?int {
    return $this->dlAllowance;
  }

  /**
   * Sets the description length allowance.
   */
  function setDlAllowance(?int $dlAllowance) : void {
    $this->dlAllowance = $dlAllowance;
  }

  /**
   * Gets information about the proportion of training set to be used for pruning.
   */
  function getPruneSize() : ?float {
    return $this->pruneSize;
  }

  /**
   * Sets the proportion of training set to be used for pruning.
   */
  function setPruneSize(?float $pruneSize) : void {
    $this->pruneSize = $pruneSize;
  }

  /**
   * Gets information about the maximum of discrete bins for apparent numeric attributes fitting.
   */
  function getNDiscretizeBins() : ?int {
    return $this->nDiscretizeBins;
  }

  /**
   * Sets the maximum of discrete bins for apparent numeric attributes fitting.
   */
  function setNDiscretizeBins(?int $nDiscretizeBins) : void {
    $this->nDiscretizeBins = $nDiscretizeBins;
  }

  /**
   * Gets information about the maximum number of rules.
   */
  function getMaxRules() : ?int {
    return $this->maxRules;
  }

  /**
   * Sets the maximum number of rules.
   */
  function setMaxRules(?int $maxRules) : void {
    $this->maxRules = $maxRules;
  }

  /**
   * Gets information about the maximum number of conds per rule.
   */
  function getMaxRuleConds() : ?int {
    return $this->maxRuleConds;
  }

  /**
   * Sets the maximum number of conds per rule.
   */
  function setMaxRuleConds(?int $maxRuleConds) : void {
    $this->maxRuleConds = $maxRuleConds;
  }

  /**
   * Gets information about the maximum number of total conds in entire ruleset.
   */
  function getMaxTotalConds() : ?int {
    return $this->maxTotalConds;
  }

  /**
   * Sets the maximum number of total conds in entire ruleset.
   */
  function setMaxTotalConds(?int $maxTotalConds) : void {
    $this->maxTotalConds = $maxTotalConds;
  }

  /**
   * Gets information about the setted random seed for repeatable results.
   */
  function getRandomState() : ?int {
    return $this->randomState;
  }

  /**
   * Sets the random seed for repeatable results.
   */
  function setRandomState(?int $randomState) : void {
    $this->randomState = $randomState;
  }

  /**
   * Gets information about the verbosity of the output progress, model development, and/or computation.
   */
  function getVerbosity() : ?int {
    return $this->verbosity;
  }

  /**
   * Sets information about the verbosity of the output progress, model development, and/or computation.
   */
  function setVerbosity(?int $verbosity) : void {
    $this->verbosity = $verbosity;
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
    $k = $this->getK();
    $dlAllowance = $this->getDlAllowance();
    $pruneSize = $this->getPruneSize();
    $nDiscretizeBins = $this->getNDiscretizeBins();
    $maxRules = $this->getMaxRules();
    $maxRuleConds = $this->getMaxRuleConds();
    $maxTotalConds = $this->getMaxTotalConds();
    $randomState = $this->getRandomState();
    $verbosity = $this->getVerbosity();

    /** Information about the attributes of the training data set */
    $attributes = $data->getAttributes();
    $classAttr = $data->getClassAttribute();
    
    /** Saving the training data set in a temporary table in the database */
    $data->SaveToDB($db, "reserved__tmpWittgensteinTrainData");

    /** If a parameter is NULL, I translate it to None */
    $getParameter = function ($parameter) {
      if ($parameter === NULL) {
        return "None";
      } else {
        return $parameter;
      }
    };

    /** Call to the python script that will use the training algorithm of the library */
    $command = escapeshellcmd("python DiscriminativeModel/PythonLearners/wittgenstein_learner.py "
                              . "reserved__tmpWittgensteinTrainData " . $getParameter($k) . " "
                              . $getParameter($dlAllowance) . " " . $getParameter($pruneSize) . " "
                              . $getParameter($nDiscretizeBins) . " " . $getParameter($maxRules) . " "
                              . $getParameter($maxRuleConds) . " " . $getParameter($maxTotalConds) . " "
                              . $getParameter($randomState) . " " . $getParameter($verbosity));
    $output = shell_exec($command);
    
    /** Drop of the temporary table for safety reason */
    $sql = "DROP TABLE reserved__tmpWittgensteinTrainData";
    mysql_prepare_and_executes($db, $sql);

    /** Parsing of the extracted rules to a string I can use to build a RuleBasedModel */
    echo $output;
    preg_match('/extracted_rule_based_model:(.*?)\[(.*?)\]/ms', $output, $matches);
    $rule = $matches[0];
    if (substr($rule, 0, strlen('extracted_rule_based_model: [')) == 'extracted_rule_based_model: [') {
      $rule = substr($rule, strlen('extracted_rule_based_model: ['));
    }
    $rule = rtrim($rule, "]");
    
    /** Creation of the rule based model; nb: I cannot use the same model or it will not update outside of the function */
    $newModel = RuleBasedModel::fromWittgensteinString($rule, $classAttr, $attributes);

    /** Model update */
    $model->setRules($newModel->getRules());
    $model->setAttributes($attributes);
  }
}
?>
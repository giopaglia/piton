<?php

include "Antecedent.php";
include "Rule.php";
include "RuleStats.php";

/*
 * Interface for a generic discriminative model
 */
abstract class _DiscriminativeModel {

  abstract function fit(Instances &$data, Learner &$learner);
  abstract function predict(Instances $testData);

  abstract function save(string $path);
  abstract function load(string $path);

  static function loadFromFile(string $path) : _DiscriminativeModel {
    echo "_DiscriminativeModel::loadFromFile($path)" . PHP_EOL;
    die("TODO");
  }

}

/*
 * This class represents a propositional rule-based model.
 */
class RuleBasedModel extends _DiscriminativeModel {
  private $rules;
  
  function __construct() {
    echo "RuleBasedModel()" . PHP_EOL;
    $this->rules = NULL;
  }

  function fit(Instances &$trainData, Learner &$learner) {
    echo "RuleBasedModel->fit([trainData], " . get_class($learner) . ")" . PHP_EOL;
    $learner->teach($this, $trainData);
  }

  function predict(Instances $testData) {
    echo "RuleBasedModel->predict(" . $testData->toString(true) . ")" . PHP_EOL;
    
    if (!(is_array($this->rules)))
      die_error("Can't use uninitialized rule-based model.");

    if (!(count($this->rules)))
      die_error("Can't use empty set of rules in rule-based model.");

    $classAttrDomain = $testData->getClassAttribute()->getDomain();
    $predictions = [];
    $data = $testData;
    for ($x = 0; $x < $data->numInstances(); $x++) {
      foreach ($this->rules as $rule) {
        if ($rule->covers($data, $x)) {
          $predictions[] = $classAttrDomain[$rule->getConsequent()];
          break;
        }
      }
    }

    if (count($predictions) != $data->numInstances())
      die_error("Couldn't perform predictions for some instances (" .
        count($predictions) . "/" . $data->numInstances() . " performed)");

    // Usa  per prevedere il campo della colonna in output
    // ritorna campi della colonna in output.
    return $predictions;
  }

  function save(string $path) {
    echo "RuleBasedModel->save($path)" . PHP_EOL;
    // TODO
  }
  function load(string $path) {
    echo "RuleBasedModel->load($path)" . PHP_EOL;
    // TODO
  }


  public function getRules() : array
  {
    return $this->rules;
  }

  public function setRules(array $rules) : self
  {
    $this->rules = $rules;
    return $this;
  }

  public function resetRules()
  {
    return $this->setRules([]);
  }
}


?>
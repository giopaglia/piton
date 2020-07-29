<?php

include "Antecedent.php";
include "Rule.php";
include "RuleStats.php";

/*
 * Interface for a generic discriminative model
 */
abstract class DiscriminativeModel {

  abstract function fit(Instances &$data, Learner &$learner);
  abstract function predict(Instances $testData);

  abstract function save(string $path);
  abstract function load(string $path);

  static function loadFromFile(string $path) : DiscriminativeModel {
    if (DEBUGMODE > 2) echo "DiscriminativeModel::loadFromFile($path)" . PHP_EOL;
    postfixisify($path, ".mod");

    $str = file_get_contents($path);
    $obj_str = strtok($str, "\n");
    switch ($obj_str) {
      case "RuleBasedModel":
        $model = new RuleBasedModel();
        $model->load($path);
        break;

      default:
        die_error("Unknown model type in DiscriminativeModel::loadFromFile(\"$path\")" . $obj_str);
        break;
    }
    return $model;
  }
}

/*
 * This class represents a propositional rule-based model.
 */
class RuleBasedModel extends DiscriminativeModel {

  /* The set of rules */
  private $rules;
  
  /* The set of attributes which the rules refer to */
  private $attributes;
  
  function __construct() {
    if (DEBUGMODE > 2) echo "RuleBasedModel()" . PHP_EOL;
    $this->rules = NULL;
    $this->attributes = NULL;
  }

  /* Train the model using an optimizer */
  function fit(Instances &$trainData, Learner &$learner) {
    if (DEBUGMODE > 2) echo "RuleBasedModel->fit([trainData], " . get_class($learner) . ")" . PHP_EOL;
    $learner->teach($this, $trainData);
  }

  /* Perform prediction onto some data. */
  function predict(Instances $testData) : array {
    if (DEBUGMODE > 2) echo "RuleBasedModel->predict(" . $testData->toString(true) . ")" . PHP_EOL;

    if (!(is_array($this->rules)))
      die_error("Can't use uninitialized rule-based model.");

    if (!(count($this->rules)))
      die_error("Can't use empty set of rules in rule-based model.");

    /* Extract the data in the same form that was seen during training */
    $testData = clone $testData;
    $testData->sortAttrsAs($this->attributes);

    /* Predict */
    $classAttr = $testData->getClassAttribute();
    $predictions = [];
    if (DEBUGMODE > 1) {
      echo "rules:\n";
      foreach ($this->rules as $r => $rule) {
        echo $rule->toString();
        echo "\n";
      }
    }
    if (DEBUGMODE > 1) echo "testing:\n";
    for ($x = 0; $x < $testData->numInstances(); $x++) {
      if (DEBUGMODE > 1) echo "[$x] : " . $testData->inst_toString($x);
      foreach ($this->rules as $r => $rule) {
        if ($rule->covers($testData, $x)) {
          if (DEBUGMODE > 1) echo $r;
          $predictions[] = $classAttr->reprVal($rule->getConsequent());
          break;
        }
      }
      if (DEBUGMODE > 1) echo "\n";
    }

    if (count($predictions) != $testData->numInstances())
      die_error("Couldn't perform predictions for some instances (" .
        count($predictions) . "/" . $testData->numInstances() . " performed)");

    return $predictions;
  }

  /* Save model to file */
  function save(string $path) {
    if (DEBUGMODE > 2) echo "RuleBasedModel->save($path)" . PHP_EOL;
    postfixisify($path, ".mod");

    // $obj_repr = ["rules" => [], "attributes" => []];
    // foreach ($this->rules as $rule) {
    //   $obj_repr["rules"][] = $rule->serialize();
    // }
    // foreach ($this->attributes as $attribute) {
    //   $obj_repr["attributes"][] = $attribute->serialize();
    // }

    // file_put_contents($path, json_encode($obj_repr));
    $obj_repr = ["rules" => $this->rules, "attributes" => $this->attributes];
    file_put_contents($path, "RuleBasedModel\n" . serialize($obj_repr));
  }
  function load(string $path) {
    if (DEBUGMODE > 2) echo "RuleBasedModel->load($path)" . PHP_EOL;
    postfixisify($path, ".mod");
    // $obj_repr = json_decode(file_get_contents($path));

    // $this->rules = [];
    // $this->attributes = [];
    // foreach ($obj_repr["rules"] as $rule_repr) {
    //   $this->rules[] = Rule::createFromSerial($rule_repr);
    // }
    // foreach ($obj_repr["attributes"] as $attribute_repr) {
    //   $this->attributes[] = Attribute::createFromSerial($attribute_repr);
    // }
    $str = file_get_contents($path);
    $obj_str = strtok($str, "\n");
    $obj_str = strtok("\n");
    $obj_repr = unserialize($obj_str);
    $this->rules = $obj_repr["rules"];
    $this->attributes = $obj_repr["attributes"];
  }

  public function getAttributes() : array
  {
    return $this->attributes;
  }

  public function setAttributes(array $attributes) : self
  {
    $this->attributes = $attributes;
    return $this;
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
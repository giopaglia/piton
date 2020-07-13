<?php

include "Antecedent.php";
include "Rule.php";
include "RuleStats.php";

/*
 * Interface for discriminative models
 */
interface _DiscriminativeModel {
  function fit(&$data, $learner);
  function predict($input_data);
  
  function save($path);
  function load($path);
}

// class DiscriminativeModel implements _DiscriminativeModel {

//   static function loadModel($path) {
//     echo "DiscriminativeModel::loadModel($path)" . PHP_EOL;
//     die("TODO");
//   }
// }

/*
 * This class represents a propositional rule-based model.
 */
// class RuleBasedModel extends DiscriminativeModel {
class RuleBasedModel implements _DiscriminativeModel {
  private $rules;
  
  function __construct() {
    echo "RuleBasedModel()" . PHP_EOL;
    $this->rules = [];
  }

  function fit(&$data, $learner) {
    echo "RuleBasedModel->fit(" . serialize($data) . ", " . serialize($learner) . ")" . PHP_EOL;
    $learner->teach($this, $data);
  }

  function predict($input_dataframe) {
    echo "RuleBasedModel->predict(" . serialize($input_dataframe) . ")" . PHP_EOL;
    // TODO
    // check vari ...
    // Usa  per prevedere il campo della colonna in output
    // ritorna campi della colonna in output.
  }

  function save($path) {
    echo "RuleBasedModel->save($path)" . PHP_EOL;
    // TODO
  }
  function load($path) {
    echo "RuleBasedModel->load($path)" . PHP_EOL;
    // TODO
  }


    /**
     * @return mixed
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param mixed $rules
     *
     * @return self
     */
    public function setRules($rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return self
     */
    public function resetRules()
    {
        return $this->setRules([]);
    }
}


?>
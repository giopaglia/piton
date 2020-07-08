<?php

include "Antecedent.php";

/*
 * Interface for predictive models
 */
interface PredictiveModel {
    function fit($data, $learner);
    function predict($input_data);
    
    function save($path);
    function load($path);
}

/*
 * This class represents a propositional rule-based model.
 */
class RuleBasedModel implements PredictiveModel {
    private $rules;
    
    function __construct() {
        echo "RuleBasedModel()" . PHP_EOL;
        $this->rules = [];
    }

    function fit($data, $learner) {
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

}


?>
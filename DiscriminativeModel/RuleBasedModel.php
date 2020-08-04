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

  /* Save model to database */
  function dumpToDB(object &$db, string $tableName) {
    //if (DEBUGMODE > 2) 
      echo "DiscriminativeModel->dumpToDB($tableName)" . PHP_EOL;
    prefixisify($tableName, "rules_");
    $sql = "DROP TABLE IF EXISTS `{$tableName}_dump`";

    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    $sql = "CREATE TABLE `{$tableName}_dump` (dump LONGTEXT)";

    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    $sql = "INSERT INTO `{$tableName}_dump` VALUES (?)";

    echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    $dump = serialize($this);
    // echo $dump;
    $stmt->bind_param("s", $dump);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();
    
  }

  function &LoadFromDB(object &$db, string $tableName) {
    if (DEBUGMODE > 2) echo "DiscriminativeModel->LoadFromDB($tableName)" . PHP_EOL;
    prefixisify($tableName, "rules_");
    
    $sql = "SELECT dump FROM " . $tableName . "_dump";
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $res = $stmt->get_result();
    $stmt->close();
    
    if (!($res !== false))
      die_error("SQL query failed: $sql");
    if($res->num_rows !== 1) {
      die_error("Error reading RuleBasedModel table dump.");
    }
    $obj = unserialize($res->fetch_assoc()["dump"]);
    return $obj;
  }

  /* Print a textual representation of the rule */
  abstract function __toString () : string;
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

  /* Save model to database */
  function saveToDB(object &$db, string $tableName, ?Instances &$testData = NULL) {
    //if (DEBUGMODE > 2) 
      echo "RuleBasedModel->saveToDB($tableName)" . PHP_EOL;
    
    if ($testData !== NULL) {
      $testData = clone $testData;
      $testData->sortAttrsAs($this->attributes);
    }

    prefixisify($tableName, "rules_");

    $sql = "DROP TABLE IF EXISTS `$tableName`";

    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    $sql = "CREATE TABLE `$tableName` ";
    $sql .= "(ID INT AUTO_INCREMENT PRIMARY KEY, class VARCHAR(256), rule TEXT, support float, confidence float, lift float, conviction float)";
    // $sql .= "(class VARCHAR(256) PRIMARY KEY, regola TEXT)"; TODO why primary

    echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    $arr_vals = [];
    foreach ($this->rules as $rule) {
      echo $rule->toString($this->attributes[0]) . PHP_EOL;
      $antds = [];
      foreach ($rule->getAntecedents() as $antd) {
        $antds[] = $antd->serialize();
      }
      $str = "\"" .
           strval($this->attributes[0]->reprVal($rule->getConsequent()))
            . "\", \"" . join(" AND ", $antds) . "\"";

      if ($testData !== NULL) {

        $measures = $rule->computeMeasures($testData);
        $str .= "," . join(",", array_map("mysql_number", $measures));
      }
      $arr_vals[] = $str;
    }
    foreach ($this->attributes as $attribute) {
      echo $attribute->toString(false) . PHP_EOL;
    }

    $sql = "INSERT INTO `$tableName`";
    if ($testData === NULL) {
      $sql .= " (class, rule)";
    } else {
      $sql .= " (class, rule, support, confidence, lift, conviction)";
    }
    $sql .= " VALUES (" . join("), (", $arr_vals) . ")";
    
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();
  }

  // function LoadFromDB(object &$db, string $tableName) {
  //   if (DEBUGMODE > 2) echo "RuleBasedModel->LoadFromDB($tableName)" . PHP_EOL;
  //   prefixisify($tableName, "rules_");
    
  //   // $sql = "SELECT class, rule, relevance, confidence, lift, conviction FROM $tableName";
  //   $sql = "SELECT dump FROM " . $tableName . "_dump";
  //   echo "SQL: $sql" . PHP_EOL;
  //   $stmt = $this->db->prepare($sql);
  //   if (!$stmt)
  //     die_error("Incorrect SQL query: $sql");
    // if (!$stmt->execute())
    //   die_error("Query failed: $sql");
    // $stmt->close();
  //   $res = $stmt->get_result();
  //   if (!($res !== false))
  //     die_error("SQL query failed: $sql");
  //   if(count($res) !== 1) {
  //     die_error("Error reading RuleBasedModel table dump.");
  //   }
  //   $obj_repr = unserialize($res[0]);
  //   $this->rules = $obj_repr["rules"];
  //   $this->attributes = $obj_repr["attributes"];
  // }

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

  /* Print a textual representation of the rule */
  function __toString () : string {
    $out_str = "";
    $out_str .= "RuleBasedModel with rules: " . PHP_EOL;
    foreach ($this->getRules() as $x => $rule) {
      $out_str .= $x . ": " . $rule->toString() . PHP_EOL;
    }
    return $out_str;
  }
}


?>
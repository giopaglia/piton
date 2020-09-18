<?php

include "Antecedent.php";
include "Rule.php";
include "RuleStats.php";

/*
 * Interface for a generic discriminative model
 */
abstract class DiscriminativeModel {

  static $prefix = "models__m";
  static $indexTableName = "models__index";

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
    if (DEBUGMODE)
      echo "DiscriminativeModel->dumpToDB('$tableName')" . PHP_EOL;

    $tableName = self::$prefix . $tableName;
    
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
    
    $tableName = self::$prefix . $tableName;
    
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
  function predict(Instances $testData, bool $returnClassIndices = false) : array {
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
          $idx = $rule->getConsequent();
          $predictions[] = ($returnClassIndices ? $idx : $classAttr->reprVal($idx));
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

  // Test a model TODO explain
  function test(Instances $testData) : array {
    if (DEBUGMODE)
      echo "RuleBasedModel->test(" . $testData->toString(true) . ")" . PHP_EOL;

    $ground_truths = [];
    
    for ($x = 0; $x < $testData->numInstances(); $x++) {
      $ground_truths[] = $testData->inst_classValue($x);
    }

    // $testData->dropOutputAttr();
    $predictions = $this->predict($testData, true);
    
    // echo "\$ground_truths : " . get_var_dump($ground_truths) . PHP_EOL;
    // echo "\$predictions : " . get_var_dump($predictions) . PHP_EOL;
    if (DEBUGMODE > 1) {
      echo "ground_truths,predictions:" . PHP_EOL;
      foreach ($ground_truths as $i => $val) {
        echo "[" . $val . "," . $predictions[$i] . "]";
      }
    }

    $classAttr = $testData->getClassAttribute();

    $domain = $classAttr->getDomain();
    // For the binary case, one measure for YES class is enough
    if (count($domain) == 2) {
      // TODO:
      //  since algorithms like prip can alter the order of the domain,
      //  the class chosen here may not be the one expected. Right now I look for the one not starting with "NO_", but this is not correct. Please fix this.
      // echo "domain " . get_var_dump($domain);
      if (startsWith($domain[0], "NO_"))
        $domain = [1 => $domain[1]];
      elseif (startsWith($domain[1], "NO_"))
        $domain = [0 => $domain[0]];
      else {
        $domain = [0 => $domain[0]];
      }
    }
    $measures = [];
    foreach ($domain as $classId => $className) {
      $measures[$classId] = $this->computeMeasures($ground_truths, $predictions, $classId);
    }
    return $measures;
  }

  function computeMeasures(array $ground_truths, array $predictions, int $classId) : array {
    $positives = 0;
    $negatives = 0;
    $TP = 0;
    $TN = 0;
    $FP = 0;
    $FN = 0;
    if (DEBUGMODE > 1) echo "\n";
    foreach ($ground_truths as $i => $val) {
      if ($ground_truths[$i] == $classId) {
        $positives++;

        if ($ground_truths[$i] == $predictions[$i]) {
          $TP++;
        } else {
          $FN++;
        }
      }
      else {
        $negatives++;

        if ($ground_truths[$i] == $predictions[$i]) {
          $TN++;
        } else {
          $FP++;
        }
      }
    }
    $accuracy = safe_div(($TP+$TN), ($positives+$negatives));
    $sensitivity = safe_div($TP, $positives);
    $specificity = safe_div($TN, $negatives);
    $PPV = safe_div($TP, ($TP+$FP));
    $NPV = safe_div($TN, ($TN+$FN));
    
    // if (DEBUGMODE > -1) echo "\$positives    : $positives    " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$negatives    : $negatives " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$TP           : $TP " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$TN           : $TN " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$FP           : $FP " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$FN           : $FN " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$accuracy     : $accuracy " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$sensitivity  : $sensitivity " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$specificity  : $specificity " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$PPV          : $PPV " . PHP_EOL;
    // if (DEBUGMODE > -1) echo "\$NPV          : $NPV " . PHP_EOL;

    return [
      $positives, $negatives,
      $TP, $TN, $FP, $FN,
      $accuracy,
      $sensitivity, $specificity, $PPV, $NPV];
  }

  /* Save model to database */
  function saveToDB(object &$db, string $modelName, string $tableName, ?Instances &$testData = NULL, ?Instances &$trainData = NULL, $rulesAffRilThesholds = [0.2, 0.7]) {
    if (DEBUGMODE)
      echo "RuleBasedModel->saveToDB('$modelName', '$tableName', ...)" . PHP_EOL;
    
    if ($testData !== NULL) {
      $testData = clone $testData;
      $testData->sortAttrsAs($this->attributes);
    }

    if ($trainData !== NULL) {
      $trainData = clone $trainData;
      $trainData->sortAttrsAs($this->attributes);
    }
    
    $allData = NULL;
    if ($trainData !== NULL && $testData !== NULL) {
      $allData = clone $trainData;
      $allData->pushInstancesFrom($testData);
    }

    $tableName = self::$prefix . $tableName;

    $sql = "DROP TABLE IF EXISTS `$tableName`";
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    $sql = "CREATE TABLE `$tableName`";
    $sql .= " (ID INT AUTO_INCREMENT PRIMARY KEY, class VARCHAR(256) NOT NULL, rule TEXT NOT NULL, support DECIMAL(10,2) DEFAULT NULL, confidence DECIMAL(10,2) DEFAULT NULL, lift DECIMAL(10,2) DEFAULT NULL, conviction DECIMAL(10,2) DEFAULT NULL)";
    // $sql .= "(class VARCHAR(256) PRIMARY KEY, regola TEXT)"; TODO why primary

    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    if (DEBUGMODE > -1) {
      echo "MODEL '$modelName' (table '$tableName'): " . PHP_EOL;
      if ($testData !== NULL)
        echo "  LOCAL EVALUATION:" . PHP_EOL;
    }

    $numRulesAffRil = 0;
    $numRulesAff = 0;
    $numRulesRil = 0;
    $numRulesNANR = 0;

    $arr_vals = [];
    foreach ($this->rules as $rule) {
      if (DEBUGMODE > -1)
        echo "    " . $rule->toString($this->getClassAttribute()) . PHP_EOL;

      $antds = [];
      foreach ($rule->getAntecedents() as $antd) {
        $antds[] = $antd->serialize();
      }
      $str = "\"" .
           strval($this->getClassAttribute()->reprVal($rule->getConsequent()))
            . "\", \"" . join(" AND ", $antds) . "\"";

      if ($testData !== NULL) {
        $measures = $rule->computeMeasures($testData);
        list($support, $confidence, $lift, $conviction) = $measures;
        
        $str .= "," . join(",", array_map("mysql_number", $measures));
        
        $ril = $support > $rulesAffRilThesholds[0];
        $aff = $confidence > $rulesAffRilThesholds[1];
        if ($ril && $aff) {
          $numRulesAffRil++;
        } else if ($ril) {
          $numRulesRil++;
        } else if ($aff) {
          $numRulesAff++;
        } else {
          $numRulesNANR++;
        }

        if (DEBUGMODE > -1) {
          echo "      support      : $support\n";
          echo "      confidence   : $confidence\n";
          echo "      lift         : $lift\n";
          echo "      conviction   : $conviction\n";
        }

      }
      $arr_vals[] = $str;
    }

    $sql = "INSERT INTO `$tableName`";
    if ($testData === NULL) {
      $sql .= " (class, rule)";
    } else {
      $sql .= " (class, rule, support, confidence, lift, conviction)";
    }
    $sql .= " VALUES (" . join("), (", $arr_vals) . ")";
    
    // echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    // foreach ($this->attributes as $attribute) {
    //   echo $attribute->toString(false) . PHP_EOL;
    // }

    // TODO remove?
    // $sql = "DROP TABLE IF EXISTS `" . self::$indexTableName . "`";
    // $stmt = $db->prepare($sql);
    // if (!$stmt)
    //   die_error("Incorrect SQL query: $sql");
    // if (!$stmt->execute())
    //   die_error("Query failed: $sql");
    // $stmt->close();

    $sql = "CREATE TABLE IF NOT EXISTS `" . self::$indexTableName . "`";
    $sql .= " (ID INT AUTO_INCREMENT PRIMARY KEY, date DATETIME NOT NULL, modelName TEXT NOT NULL, tableName TEXT NOT NULL, classId INT NOT NULL, className TEXT NOT NULL";
    $sql .= ", totNumRules INT DEFAULT NULL";
    $sql .= ", numRulesAffRil INT DEFAULT NULL";
    $sql .= ", numRulesAffNR INT DEFAULT NULL";
    $sql .= ", numRulesNARil INT DEFAULT NULL";
    $sql .= ", numRulesNANR INT DEFAULT NULL";
    $sql .= ", percRulesAffRil DECIMAL(10,2) AS (`numRulesAffRil` / `totNumRules`)";
    $sql .= ", percRulesAffNR DECIMAL(10,2) AS (`numRulesAffNR` / `totNumRules`)";
    $sql .= ", percRulesNARil DECIMAL(10,2) AS (`numRulesNARil` / `totNumRules`)";
    $sql .= ", percRulesNANR DECIMAL(10,2) AS (`numRulesNANR` / `totNumRules`)";
    $sql .= ", totN DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", classShare DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", trainN DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", testN DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", positives DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", negatives DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", TP DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", TN DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", FP DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", FN DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", accuracy DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", sensitivity DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", specificity DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", PPV DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", NPV DECIMAL(10,2) DEFAULT NULL";
    $sql .= ")";

    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();

    // IF NOT EXISTS
    // $sql = "CREATE VIEW `" . self::$indexTableName . "_view` AS SELECT modelName, numRules, totN, trainN, testN, positives, negatives, TP, TN, FP, FN, accuracy, sensitivity, specificity, PPV, NPV  FROM `" . self::$indexTableName . "` ORDER BY `tableName` ASC";
    // $stmt = $db->prepare($sql);
    // if (!$stmt)
    //   die_error("Incorrect SQL query: $sql");
    // if (!$stmt->execute())
    //   die_error("Query failed: $sql");
    // $stmt->close();

    // $globalIndicatorsStr = "";
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // $globalIndicatorsStr .= 
    // 

    $sql = "INSERT INTO `" . self::$indexTableName . "`";
    $sql .= " (date, modelName, tableName, classId, className, totNumRules, numRulesAffRil, numRulesAffNR, numRulesNARil, numRulesNANR";
    if ($allData !== NULL) {
      $sql .= ", totN";
      $sql .= ", classShare";
    }
    if ($trainData !== NULL) {
      $sql .= ", trainN";
    }
    if ($testData !== NULL) {
      $sql .= ", testN";
    }
    if ($testData !== NULL) {
      $sql .= ", positives, negatives, TP, TN, FP, FN, accuracy, sensitivity, specificity, PPV, NPV";
    }
    $sql .= ") VALUES (";

    $valuesSql = [];
    if ($testData !== NULL) {
      $measures = $this->test($testData);
    }
    $classAttr = $this->getClassAttribute();
    
    if (DEBUGMODE > -1)
      if ($testData !== NULL)
        echo "  GLOBAL EVALUATION:" . PHP_EOL;
    
    foreach ($classAttr->getDomain() as $classId => $className) {
      if ($testData !== NULL) {
        if (isset($measures[$classId])) {
          $valueSql = "'" . date('Y-m-d H:i:s') . "', '$modelName', '$tableName', $classId, '$className'";
          // TODO rule numbers should refer to the model, not the className. Mmm
          $totNumRules = count($this->getRules());
          $valueSql .= ", " . strval($totNumRules);
          $valueSql .= ", " . strval($numRulesAffRil);
          $valueSql .= ", " . strval($numRulesAff);
          $valueSql .= ", " . strval($numRulesRil);
          $valueSql .= ", " . strval($numRulesNANR);
          if ($allData !== NULL) {
            $valueSql .= ", " . strval($allData->numInstances());
            $valueSql .= ", " . strval($allData->getClassShare($classId));
          }
          if ($trainData !== NULL) {
            $valueSql .= ", " . strval($trainData->numInstances());
          }
          if ($testData !== NULL) {
            $valueSql .= ", " . strval($testData->numInstances());
          }
          $valueSql .= ", " . join(", ", array_map("mysql_number", $measures[$classId])); 
          $valuesSql[] = $valueSql;
          if (intval(DEBUGMODE) > -1) {
            echo "    '$className', ($classId): " . PHP_EOL;
            if ($allData !== NULL)
              echo "    totN: " . ($allData->numInstances()) . PHP_EOL;
              echo "    classShare: " . ($allData->getClassShare($classId)) . PHP_EOL;
            if ($trainData !== NULL)
              echo "    trainN: {$trainData->numInstances()}" . PHP_EOL;
            if ($testData !== NULL)
              echo "    testN: {$testData->numInstances()}" . PHP_EOL;
            list($positives, $negatives,
              $TP, $TN, $FP, $FN,
              $accuracy,
              $sensitivity, $specificity, $PPV, $NPV) = $measures[$classId];
            echo "      positives    : $positives\n";
            echo "      negatives    : $negatives\n";
            echo "      TP           : $TP\n";
            echo "      TN           : $TN\n";
            echo "      FP           : $FP\n";
            echo "      FN           : $FN\n";
            echo "      accuracy     : $accuracy\n";
            echo "      sensitivity  : $sensitivity\n";
            echo "      specificity  : $specificity\n";
            echo "      PPV          : $PPV\n";
            echo "      NPV          : $NPV\n";
          }
        }
      }
      else {
        $valueSql = "'" . date('Y-m-d H:i:s') . "', '$modelName', '$tableName', $classId, '$className'";
        $valuesSql[] = $valueSql;
      }
    }
    $sql .= join("), (", $valuesSql) . ")";

    // echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();
    // For reference, to read the comment:
    // SELECT table_comment 
    // FROM INFORMATION_SCHEMA.TABLES 
    // WHERE table_schema='my_cool_database' 
    //     AND table_name='$tableName';

  }

  // function LoadFromDB(object &$db, string $tableName) {
  //   if (DEBUGMODE > 2) echo "RuleBasedModel->LoadFromDB($tableName)" . PHP_EOL;
  //   prefixisify($tableName, prefix);
    
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

  function getClassAttribute() : Attribute {
    // Note: assuming the class attribute is the first
    return $this->getAttributes()[0];
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
    $rules = $this->getRules();
    $attrs = $this->getAttributes();
    $out_str = "    ";
    $out_str .= "RuleBasedModel with "
              . count($rules) . " rules & "
              . count($attrs) . " attributes: " . PHP_EOL . "    ";
    foreach ($rules as $x => $rule) {
      $out_str .= "R" . $x . ": " . $rule->toString() . PHP_EOL . "    ";
    }
    // foreach ($this->getAttributes() as $x => $attr) {
    //   $out_str .= $x . ": " . $attr->toString() . PHP_EOL . "    ";
    // }
    if (count($attrs)) {
    $x = 0;               $out_str .= "A" . $x . ": " . $attrs[$x]->toString(false) . PHP_EOL . "    ";
      if (count($attrs)>2) {
    $x = 1;               $out_str .= "A" . $x . ": " . $attrs[$x]->toString(false) . PHP_EOL . "    ";
    } if (count($attrs)>3) {
    $x = 2;               $out_str .= "A" . $x . ": " . $attrs[$x]->toString(false) . PHP_EOL . "    ";
    } if (count($attrs)>4) {
                          $out_str .= "..." . PHP_EOL . "    ";
    }
    $x = count($attrs)-1; $out_str .= "A" . $x . ": " . $attrs[$x]->toString(false) . PHP_EOL . "    ";
    }
    return $out_str;
  }
}


?>
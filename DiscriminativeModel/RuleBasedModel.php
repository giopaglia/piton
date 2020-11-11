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
  function predict(Instances $testData, bool $useClassIndices = false
    , bool $returnPerRuleMeasures = false
  ) : array {
    if (DEBUGMODE > 2) echo "RuleBasedModel->predict(" . $testData->toString(true) . ")" . PHP_EOL;

    if (!(is_array($this->rules)))
      die_error("Can't use uninitialized rule-based model.");

    if (!(count($this->rules)))
      die_error("Can't use empty set of rules in rule-based model.");

    /* Extract the data in the same form that was seen during training */
    $allTestData = clone $testData;
    if ($this->attributes !== NULL) {
      // $allTestData->sortAttrsAs($this->attributes);
      $allTestData->sortAttrsAs($this->attributes, true);
      
      /* Predict */
      $classAttr = $allTestData->getClassAttribute();
      $predictions = [];
      if (DEBUGMODE > 1) {
        echo "rules:\n";
        foreach ($this->rules as $r => $rule) {
          echo $rule->toString();
          echo "\n";
        }
      }
      // if ($returnRuleCovering) {
      //   $rules_covering = array_fill(0,count($this->getRules()),0);
      //   $rules_hitting = array_fill(0,count($this->getRules()),0);
      // }

      if (DEBUGMODE > 1) echo "testing:\n";
      for ($x = 0; $x < $allTestData->numInstances(); $x++) {
        if (DEBUGMODE > 1) echo "[$x] : ";
        if (DEBUGMODE & DEBUGMODE_DATA) echo $allTestData->inst_toString($x);
        $prediction = NULL;
        foreach ($this->rules as $r => $rule) {
          if ($rule->covers($allTestData, $x)) {
            if (DEBUGMODE > 1) echo " R$r";
            $idx = $rule->getConsequent();
            // if ($returnRuleCovering) {
            //   $rules_covering[$r]++;
            //   if ($returnRuleCovering) {
            //     $rules_hitting[$r]++;
            //   }
            // }
            if (DEBUGMODE > 1) echo " -> $idx";
            $prediction = ($useClassIndices ? $idx : $classAttr->reprVal($idx));
            break;
          }
        }
        $predictions[] = $prediction;
        if (DEBUGMODE > 1) echo "\n";
      }

      if ($returnPerRuleMeasures) {
        $curTestData = clone $allTestData;
        $rules_measures = [];
        foreach ($this->getRules() as $r => $rule) {
          
          $fullRuleMeasures = $rule->computeMeasures($curTestData, true);
          $curTestData = $fullRuleMeasures["filteredData"];
          // echo "<pre>" . $curTestData->toString() . "</pre>" . PHP_EOL;

          $subRuleMeasures = $rule->computeMeasures($allTestData);

          $rules_measures[$r] = [
            "rule"            => $rule->toString(),
            "subCovered"      => $subRuleMeasures["covered"],
            "subSupport"      => $subRuleMeasures["support"],
            "subConfidence"   => $subRuleMeasures["confidence"],
            "subLift"         => $subRuleMeasures["lift"],
            "subConviction"   => $subRuleMeasures["conviction"],
            "covered"         => $fullRuleMeasures["covered"],
            "support"         => $fullRuleMeasures["support"],
            "confidence"      => $fullRuleMeasures["confidence"],
            "lift"            => $fullRuleMeasures["lift"],
            "conviction"      => $fullRuleMeasures["conviction"],
          ];
        }
      }
    }
    else {
      die_error("RuleBasedModel needs attributesSet for predict().");
    }

    $null_predictions = count(array_filter($predictions, function ($v) { return $v !== NULL; }));
    if ($null_predictions != $allTestData->numInstances()) {
      warn("Couldn't perform predictions for some instances (# predictions: " .
        $null_predictions . "/" . $allTestData->numInstances() . ")");
    }
    
    if($returnPerRuleMeasures) {
      return [$predictions, $rules_measures];
    } else {
      return $predictions;
    }
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
  function test(Instances $testData, bool $testRuleByRule = false) : array {
    if (DEBUGMODE)
      echo "RuleBasedModel->test(" . $testData->toString(true) . ")" . PHP_EOL;

    $classAttr = $testData->getClassAttribute();
    $domain = $classAttr->getDomain();

    $ground_truths = [];
    
    for ($x = 0; $x < $testData->numInstances(); $x++) {
      $ground_truths[] = $testData->inst_classValue($x);
      // $ground_truths[] = $domain[$testData->inst_classValue($x)];
    }

    // $testData->dropOutputAttr();
    // $predictions = $this->predict($testData, true);
    if ($testRuleByRule) {
      list($predictions, $rules_measures) = $this->predict($testData, true, $testRuleByRule);
    } else {
      $predictions = $this->predict($testData, true, $testRuleByRule);
    }

    // echo "\$ground_truths : " . get_var_dump($ground_truths) . PHP_EOL;
    // echo "\$predictions : " . get_var_dump($predictions) . PHP_EOL;
    if (DEBUGMODE > 1) {
      // echo "ground_truths,predictions:" . PHP_EOL;
      // foreach ($ground_truths as $i => $val) {
      //   echo "[" . $val . "," . $predictions[$i] . "]" . PHP_EOL;
      // }
    }

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

    if ($testRuleByRule) {
      return ["rules_measures" => $rules_measures, "measures" => $measures, "totTest" => $testData->numInstances()];
    } else {
      return $measures;
    }
  }

  static function HTMLShowTestResults (array $testResults) : string {
    $measures = NULL;
    $rules_measures = NULL;
    if (isset($testResults["measures"]) && isset($testResults["rules_measures"])) {
      $measures = $testResults["measures"];
      $rules_measures = $testResults["rules_measures"];
    } else {
      $measures = $testResults;
      $rules_measures = NULL;
    }

    $out = "";
    $out .= "<br><br>";

    if (isset($rules_measures)) {
      $out .= "Local measurements:<br>";
      $out .= "<table class='blueTable' style='border-collapse: collapse; ' border='1'>";
      $out .= "<thead>";
      $out .= "<tr>";
      $out .= "<th>#</th>
<th>rule</th>
<th colspan='5'>full rule</th>
<th colspan='5'>sub rule (no model context)</th>
";
      $out .= "</tr>";
      $out .= "<tr>";
      $out .= "<th>#</th>
<th>rule</th>
<th colspan='2'>support</th>
<th>confidence</th>
<th>lift</th>
<th>conviction</th>
<th colspan='2'>support</th>
<th>confidence</th>
<th>lift</th>
<th>conviction</th>";
      $out .= "</tr>";
      $out .= "</thead>";
      $out .= "<tbody>";
      foreach ($rules_measures as $r => $rules_measure) {
        $out .= "<tr>";
        $out .= "<td>" . $r . "</td>";
        $out .= "<td>" . $rules_measure["rule"] . "</td>";
        $out .= "<td>" . number_format($rules_measure["covered"]/$testResults["totTest"], 3) . "</td><td>" . $rules_measure["covered"] . "</td>";
        $out .= "<td>" . number_format($rules_measure["confidence"], 3) . "</td>";
        // $out .= "<td>" . number_format($rules_measure["support"]*100, 2) . "%</td>";
        // $out .= "<td>" . number_format($rules_measure["confidence"]*100, 2) . "%</td>";
        $out .= "<td>" . number_format($rules_measure["lift"], 3) . "</td>";
        $out .= "<td>" . number_format($rules_measure["conviction"], 3) . "</td>";
        $out .= "<td>" . number_format($rules_measure["subSupport"], 3) . "</td><td>" . $rules_measure["subCovered"] . "</td>";
        $out .= "<td>" . number_format($rules_measure["subConfidence"], 3) . "</td>";
        // $out .= "<td>" . number_format($rules_measure["subSupport"]*100, 2) . "%</td>";
        // $out .= "<td>" . number_format($rules_measure["subConfidence"]*100, 2) . "%</td>";
        $out .= "<td>" . number_format($rules_measure["subLift"], 3) . "</td>";
        $out .= "<td>" . number_format($rules_measure["subConviction"], 3) . "</td>";
        $out .= "</tr>";
      }
      $out .= "</tbody>";
      $out .= "</table>";
    }

    if (isset($measures)) {
      $out .= "Global measurements:<br>";
      $out .= "<table class='blueTable' style='border-collapse: collapse; ' border='1'>";
      $out .= "<thead>";
      $out .= "<tr>";
      $out .= "<th>classId</th>
<th>positives</th>
<th>negatives</th>
<th>TP</th>
<th>TN</th>
<th>FP</th>
<th>FN</th>
<th>accuracy</th>
<th>sensitivity</th>
<th>specificity</th>
<th>PPV</th>
<th>NPV</th>";
      $out .= "</tr>";
      $out .= "</thead>";
      $out .= "<tbody>";
      foreach ($measures as $m => $measure) {
        $out .= "<tr>";
        $out .= "<td>" . $m . "</td>";
        $out .= "<td>" . ($measure[0]) . "</td>";
        $out .= "<td>" . ($measure[1]) . "</td>";
        $out .= "<td>" . ($measure[2]) . "</td>";
        $out .= "<td>" . ($measure[3]) . "</td>";
        $out .= "<td>" . ($measure[4]) . "</td>";
        $out .= "<td>" . ($measure[5]) . "</td>";
        $out .= "<td>" . number_format($measure[6], 2) . "</td>";
        $out .= "<td>" . number_format($measure[7], 2) . "</td>";
        $out .= "<td>" . number_format($measure[8], 2) . "</td>";
        $out .= "<td>" . number_format($measure[9], 2) . "</td>";
        $out .= "<td>" . number_format($measure[10], 2) . "</td>";
        $out .= "</tr>";
      }
      $out .= "</tbody>";
      $out .= "</table>";
      
    }
    $out .= "<style>table.blueTable {
  border: 1px solid #1C6EA4;
  background-color: #EEEEEE;
  width: 100%;
  text-align: left;
  border-collapse: collapse;
}
table.blueTable td, table.blueTable th {
  border: 1px solid #AAAAAA;
  padding: 3px 2px;
}
table.blueTable tbody td {
  font-size: 13px;
}
table.blueTable tbody tr:nth-child(even) {
  background: #D0E4F5;
}
table.blueTable thead {
  background: #1C6EA4;
  background: -moz-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
  background: -webkit-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
  background: linear-gradient(to bottom, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
  border-bottom: 2px solid #444444;
}
table.blueTable thead th {
  font-size: 15px;
  font-weight: bold;
  color: #FFFFFF;
  border-left: 2px solid #D0E4F5;
}
table.blueTable thead th:first-child {
  border-left: none;
}

table.blueTable tfoot {
  font-size: 14px;
  font-weight: bold;
  color: #FFFFFF;
  background: #D0E4F5;
  background: -moz-linear-gradient(top, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
  background: -webkit-linear-gradient(top, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
  background: linear-gradient(to bottom, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
  border-top: 2px solid #444444;
}
table.blueTable tfoot td {
  font-size: 14px;
}
table.blueTable tfoot .links {
  text-align: right;
}
table.blueTable tfoot .links a{
  display: inline-block;
  background: #1C6EA4;
  color: #FFFFFF;
  padding: 2px 8px;
  border-radius: 5px;
}</style>";
    return $out;
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
        if ($predictions[$i] === NULL) {
          die_error("TODO: how to evaluate with NULL predictions?");
        }
        if ($ground_truths[$i] == $predictions[$i]) {
          $TP++;
        } else {
          $FN++;
        }
      }
      else {
        $negatives++;
        if ($predictions[$i] === NULL) {
          die_error("TODO: how to evaluate with NULL predictions?");
        }
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
  function saveToDB(object &$db, $modelName, string $tableName, ?Instances &$testData = NULL, ?Instances &$trainData = NULL, $rulesAffRilThesholds = NULL) {
    
    if ($rulesAffRilThesholds === NULL) {
      $rulesAffRilThesholds = [0.2, 0.7];
    }

    if (DEBUGMODE)
      echo "RuleBasedModel->saveToDB(" . toString($modelName)
        . ", " . toString($tableName) . ", ...)" . PHP_EOL;
    
    $batch_id = NULL;
    if (is_array($modelName)) {
      $batch_id = $modelName[0];
      $modelName = $modelName[1];
    }

    if ($testData !== NULL) {
      $testData = clone $testData;
      $testData->sortAttrsAs($this->attributes);
    }

    $allData = NULL;
    if ($trainData !== NULL && $testData !== NULL) {
      $allData = clone $trainData;
      $allData->sortAttrsAs($this->attributes);
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
    $sql .= " (ID INT AUTO_INCREMENT PRIMARY KEY";
    $sql .= ", class VARCHAR(256) NOT NULL";
    $sql .= ", rule TEXT NOT NULL";
    $sql .= ", support DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", confidence DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", lift DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", conviction DECIMAL(10,2) DEFAULT NULL";
    $sql .= ")";
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

    $modelStr = "";

    $numRulesRA = 0;
    $numRulesRNA = 0;
    $numRulesNRA = 0;
    $numRulesNRNA = 0;

    $arr_vals = [];
    foreach ($this->rules as $rule) {
      $classAttr = $this->getClassAttribute();
      if (DEBUGMODE > -1)
        echo "    " . $rule->toString($classAttr) . PHP_EOL;

      $antds = [];
      foreach ($rule->getAntecedents() as $antd) {
        $antds[] = "("  . $antd->serialize() . ")";
      }
      $antecedentStr = join(" and ", $antds);
      $consequentStr = strval($classAttr->reprVal($rule->getConsequent()));

      $modelStr .= $antecedentStr . " => " . $consequentStr . "\n";

      $str = "\"" .
              addcslashes($consequentStr, "\"")
            . "\", \"" . addcslashes($antecedentStr, "\"") . "\"";

      if ($testData !== NULL) {
        $ruleMeasures = $rule->computeMeasures($testData);
        $covered    = $ruleMeasures["covered"];
        $support    = $ruleMeasures["support"];
        $confidence = $ruleMeasures["confidence"];
        $lift       = $ruleMeasures["lift"];
        $conviction = $ruleMeasures["conviction"];

        $str .= "," . join(",", array_map("mysql_number", [$support, $confidence, $lift, $conviction]));
        
        $ril = $support > $rulesAffRilThesholds[0];
        $aff = $confidence > $rulesAffRilThesholds[1];
        if ($ril && $aff) {
          $numRulesRA++;
        } else if ($ril) {
          $numRulesRNA++;
        } else if ($aff) {
          $numRulesNRA++;
        } else {
          $numRulesNRNA++;
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

    if (DEBUGMODE > -1) {
      echo $modelStr;
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
    $sql .= " (ID INT AUTO_INCREMENT PRIMARY KEY";
    $sql .= ", batch_id VARCHAR(256)";
    $sql .= ", date DATETIME NOT NULL";
    $sql .= ", modelName TEXT NOT NULL";
    $sql .= ", tableName TEXT NOT NULL";
    $sql .= ", classId INT NOT NULL";
    $sql .= ", className TEXT NOT NULL";

    $sql .= ", numRulesRA INT DEFAULT NULL";
    $sql .= ", numRulesRNA INT DEFAULT NULL";
    $sql .= ", numRulesNRA INT DEFAULT NULL";
    $sql .= ", numRulesNRNA INT DEFAULT NULL";
    $sql .= ", totNumRules INT AS (`numRulesRA` + `numRulesRNA` + `numRulesNRA` + `numRulesNRNA`)";
    $sql .= ", percRulesRA DECIMAL(10,2) AS (`numRulesRA` / `totNumRules`)";
    $sql .= ", percRulesRNA DECIMAL(10,2) AS (`numRulesRNA` / `totNumRules`)";
    $sql .= ", percRulesNRA DECIMAL(10,2) AS (`numRulesNRA` / `totNumRules`)";
    $sql .= ", percRulesNRNA DECIMAL(10,2) AS (`numRulesNRNA` / `totNumRules`)";
    $sql .= ", totPositives DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", totNegatives DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", totN DECIMAL(10,2) AS (`totPositives` + `totNegatives`)";
    $sql .= ", totClassShare DECIMAL(10,2) AS (`totPositives` / `totN`)";
    $sql .= ", testPositives DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", testNegatives DECIMAL(10,2) DEFAULT NULL";
    $sql .= ", testN DECIMAL(10,2) AS (`testPositives` + `testNegatives`)";
    $sql .= ", trainN DECIMAL(10,2) AS (`totN` - `testN`)";
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

    // TODO IF NOT EXISTS
    $view_name = self::$indexTableName . "_view";

    $sql = "SELECT * FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_NAME = N'$view_name' AND TABLE_SCHEMA = database()";
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $res = $stmt->get_result();
    if (!($res !== false))
      die_error("SQL query failed: $sql");
    $create_view = ($res->num_rows == 0);
    $stmt->close();

    $sql = "VIEW `{$view_name}` AS SELECT ";
    $sql .= "batch_id";
    $sql .= ", modelName";
    $sql .= ", className AS `className (0)`";
    $sql .= ", TP";
    $sql .= ", TN";
    $sql .= ", FP";
    $sql .= ", FN";
    $sql .= ", totPositives AS totPos";
    $sql .= ", totNegatives AS totNeg";
    $sql .= ", totN AS `totN (0)`";
    $sql .= ", testN";
    $sql .= ", trainN";
    $sql .= ", NULL";
    $sql .= ", className";
    $sql .= ", totN";
    $sql .= ", totPositives";
    $sql .= ", totNumRules";
    $sql .= ", numRulesRA";
    $sql .= ", numRulesRNA";
    $sql .= ", numRulesNRA";
    $sql .= ", numRulesNRNA";
    $sql .= ", accuracy";
    $sql .= ", sensitivity";
    $sql .= ", specificity";
    $sql .= ", PPV";
    $sql .= ", NPV";
    // $sql .= " FROM `" . self::$indexTableName . "` ORDER BY `batch_id` ASC, `tableName` ASC";
    // TODO generalize
    $sql .= " FROM `" . self::$indexTableName . "` ORDER BY batch_id DESC,
case
    when `modelName` = '_RaccomandazioniTerapeuticheUnitarie.TIPO.Terapie osteoprotettive' then 1
    when `modelName` = '_RaccomandazioniTerapeuticheUnitarie.TIPO.Vitamina D Supplementazione' then 2
    when `modelName` = '_RaccomandazioniTerapeuticheUnitarie.TIPO.Calcio supplementazione' then 3
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Terapie osteoprotettive=Terapie osteoprotettive_PrincipioAttivo.Alendronato' then 4
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Terapie osteoprotettive=Terapie osteoprotettive_PrincipioAttivo.Denosumab' then 5
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Terapie osteoprotettive=Terapie osteoprotettive_PrincipioAttivo.Risedronato' then 6
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Vitamina D Supplementazione=Vitamina D Supplementazione_PrincipioAttivo.Calcifediolo' then 7
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Vitamina D Supplementazione=Vitamina D Supplementazione_PrincipioAttivo.Colecalciferolo' then 8
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Calcio supplementazione=Calcio supplementazione_PrincipioAttivo.Calcio citrato' then 9
    when `modelName` = 'RaccomandazioniTerapeuticheUnitarie>TIPO.Calcio supplementazione=Calcio supplementazione_PrincipioAttivo.Calcio carbonato' then 10
    else 1000
end";
    
    // if($create_view) {
    //   $sql = "CREATE " . $sql;
    // } else {
    //   $sql = "ALTER " . $sql;
    // }

    if($create_view) {
      $sql = "CREATE " . $sql;
      $stmt = $db->prepare($sql);
      if (!$stmt)
        die_error("Incorrect SQL query: $sql");
      if (!$stmt->execute())
        die_error("Query failed: $sql");
      $stmt->close();
    }

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
    $sql .= " (";
    if ($batch_id !== NULL) {
      $sql .= "batch_id, ";
    }
    $sql .= "date, modelName, tableName, classId, className";
    $sql .= ", numRulesRA, numRulesRNA, numRulesNRA, numRulesNRNA";
    if ($allData !== NULL) {
      $sql .= ", totPositives, totNegatives";
      $totMeasures = $this->test($allData);
    }
    if ($testData !== NULL) {
      $sql .= ", testPositives, testNegatives, TP, TN, FP, FN, accuracy, sensitivity, specificity, PPV, NPV";
      $testMeasures = $this->test($testData);
    }
    $sql .= ") VALUES (";

    $valuesSql = [];
    
    if (DEBUGMODE > -1)
      if ($testData !== NULL)
        echo "  GLOBAL EVALUATION:" . PHP_EOL;
    
    foreach ($totMeasures as $classId => $totMeasures_) {
      $className = $classAttr->reprVal($classId);

      $valueSql = "";
      if ($batch_id !== NULL) {
        $valueSql .= "" . mysql_string($db, $batch_id) . ", '";
      }
      $valueSql .= date('Y-m-d H:i:s') . "', '$modelName', '$tableName', $classId, '$className'";
      // TODO rule numbers should refer to the model, not the className. Mmm
      $valueSql .= ", " . strval($numRulesRA);
      $valueSql .= ", " . strval($numRulesRNA);
      $valueSql .= ", " . strval($numRulesNRA);
      $valueSql .= ", " . strval($numRulesNRNA);

      if ($allData !== NULL) {
        // $valueSql .= ", " . strval($allData->numInstances());
        list($totPositives, $totNegatives,
          , , , ,
          ,
          , , , ) = $totMeasures[$classId];
        $valueSql .= ", " . strval($totPositives);
        $valueSql .= ", " . strval($totNegatives);
        // $valueSql .= ", " . strval($allData->getClassShare($classId));
      }
      if ($testData !== NULL) {
        $valueSql .= ", " . join(", ", array_map("mysql_number", $testMeasures[$classId])); 
        $valuesSql[] = $valueSql;
      }

      if (intval(DEBUGMODE) > -1) {
        echo "    '$className', ($classId): " . PHP_EOL;
        if ($allData !== NULL) {
          // echo "    totN: " . ($allData->numInstances()) . PHP_EOL;
          echo "    totPositives: " . ($totPositives) . PHP_EOL;
          echo "    totNegatives: " . ($totNegatives) . PHP_EOL;
          // echo "    classShare: " . ($allData->getClassShare($classId)) . PHP_EOL;
        }
        if ($testData !== NULL) {
          // echo "    testN: {$testData->numInstances()}" . PHP_EOL;
          list($positives, $negatives,
            $TP, $TN, $FP, $FN,
            $accuracy,
            $sensitivity, $specificity, $PPV, $NPV) = $testMeasures[$classId];
          echo "    testN: " . ($positives+$negatives) . PHP_EOL;
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
    $sql .= join("), (", $valuesSql) . ")";

    // echo "SQL: $sql" . PHP_EOL;
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql");
    if (!$stmt->execute())
      die_error("Query failed: $sql");
    $stmt->close();
    // TODO maybe we can make use of mysql comments!
    // For reference, to read the comment:
    // SELECT table_comment 
    // FROM INFORMATION_SCHEMA.TABLES 
    // WHERE table_schema='database()' 
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

  function reindexAttributes() {
    foreach ($this->attributes as $k => &$attribute) {
      $attribute->setIndex($k);
    }
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

  // TODO: here I'm asssumning a classification rule
  static function fromString(string $str, ?DiscreteAttribute $classAttr = NULL) : RuleBasedModel {
    $rules_str_arr = preg_split("/[\n\r]/", trim($str));
    $rules = [];
    if ($classAttr === NULL) {
      $classAttr = new DiscreteAttribute("outputAttr", "parsedOutputAttr", []);
    }
    $outputMap = array_flip($classAttr->getDomain());
    $attributes = [$classAttr];
    foreach ($rules_str_arr as $rule_str) {
      list($rule, $ruleAttributes) = ClassificationRule::fromString($rule_str, $outputMap);
      $rules[] = $rule;
      $attributes = array_merge($attributes, $ruleAttributes);
    }
    $model = new RuleBasedModel();
    $model->setRules($rules);
    $model->setAttributes($attributes);
    $model->reindexAttributes();
    return $model;
  }

  /* Print a textual representation of the rule */
  function __toString () : string {
    $rules = $this->getRules();
    $attrs = $this->attributes;
    $out_str = "    ";
    $out_str .= "RuleBasedModel with "
              . count($rules) . " rules"
              . ($attrs === NULL ? "" : " & " . count($attrs) . " attributes")
              . ": " . PHP_EOL . "    ";
    foreach ($rules as $x => $rule) {
      $out_str .= "R" . $x . ": " . $rule->toString() . PHP_EOL . "    ";
    }
    // foreach ($this->getAttributes() as $x => $attr) {
    //   $out_str .= $x . ": " . $attr->toString() . PHP_EOL . "    ";
    // }
    if ($attrs !== NULL && count($attrs)) {
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

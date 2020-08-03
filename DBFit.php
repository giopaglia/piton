<?php


include "PorterStemmer.php";
include "DiscriminativeModel/RuleBasedModel.php";
include "DiscriminativeModel/PRip.php";

/*
 * This class can be used to learn intelligent models from a MySQL database.
 *
 * TODO explain
 * 
 * Handles different types of attributes:
 * - numerical
 * - categorical (finite domain)
 * - dates
 * - strings
 * 
 */
class DBFit {
  /* Database access (Object-Oriented MySQL style) */
  private $db;

  /* Concerning database tables (array of terms.) (TODO strings but also not, explain) */
  private $tables;

  /* MySQL columns to read. This is an array of terms, one for each column.
    For each column, the name must be specified, so a term can simply be
     the name of the column (e.g "Age").
    When dealing with more than one MySQL
     table, it is mandatory that each column name references the table it belongs,
     as in "patient.Age".
    Additional parameters can be supplied for managing the column pre-processing.
    - A "treatment" for a column determines how to derive an attribute from the
       column data. For example, "YearsSince" translates each value of
       a date/datetime column into an attribute value representing the number of
       years since the date. "DaysSince", "MonthsSince" are also available.
      "DaysSince" is the default treatment for dates/datetimes
      "ForceCategorical" forces the corresponding attribute to be nominal, with
       its domain consisting of the unique values found in the table for the column.
      For text fields, "BinaryBagOfWords" can be used to generate k binary attributes
       representing the presence of a frequent word in the field.
      The column term when a treatment is desired must be an array
       [columnName, treatment] (e.g ["BirthDate", "ForceCategorical"])
      Treatments may require/allow arguments, and these can be supplied through
       an array instead of a simple string. For example, "BinaryBagOfWords"
       requires a parameter k, representing the size of the dictionary.
       As an example, the following term requires BinaryBagOfWords with k=10:
       ["Description", ["BinaryBagOfWords", 10]].
      A NULL treatment implies no such pre-processing step.
    - The name of the attribute derived from the column can also be specified:
       for instance, ["BirthDate", "YearsSince", "Age"] creates an "Age" attribute
       by processing a "BirthDate" sql column.
  */
  private $columns;

  /* Name of the (categorical) column/attribute to predict (string) */
  private $outputColumnName;
  private $outputColumnTreatment;

  /* SQL WHERE clauses for the concerning tables (array of strings) */
  private $whereCriteria;

  /* SQL LIMIT term in the SELECT query */
  private $limit;

  /* An identifier column, used during sql-based prediction */
  private $identifierColumnName;

  /* Optimizer in use for training the model */
  private $learner;

  /* Discriminative model trained/loaded */
  private $model;

  /* Training mode (e.g full training, or perform train/test split) */
  static private $defTrainingMode = [80, 20];
  private $trainingMode;

  /* Default options TODO explain */
  private $defaultOptions = [];

  /* MAP: Mysql column type -> attr type */
  static $col2attr_type = [
    "datetime" => [
      "" => "datetime"
    , "DaysSince" => "int"
    , "MonthsSince" => "int"
    , "YearsSince" => "int"
    ]
  , "date" => [
      "" => "int"
    , "DaysSince" => "int"
    , "MonthsSince" => "int"
    , "YearsSince" => "int"
    ]
  , "int"     => ["" => "int"]
  , "bigint"  => ["" => "int"]
  , "float"   => ["" => "float"]
  , "real"    => ["" => "float"]
  , "double"  => ["" => "double"]
  , "enum"    => ["" => "enum"]
  , "tinyint(1)" => ["" => "bool"]
  , "boolean"    => ["" => "bool"]
  ];

  function __construct(object $db) {
    echo "DBFit(DB)" . PHP_EOL;
    if(!(get_class($db) == "mysqli"))
      die_error("DBFit requires a mysqli object, but got object of type "
        . get_class($db) . ".");
    $this->db = $db;
    $this->tables = [];
    $this->columns = [];
    $this->setOutputColumnName(NULL);
    $this->setIdentifierColumnName(NULL);
    $this->whereCriteria = NULL;
    $this->limit = NULL;

    $this->model = NULL;
    $this->learner = NULL;
    $this->trainingMode = NULL;
  }

  /** Read data & pre-process it */
  private function readData($idVal = NULL) {

    echo "DBFit->readData()" . PHP_EOL;
    /* Checks */
    if (!count($this->tables)) {
      die_error("Must specify the concerning tables, through ->setTables() or ->addTable().");
    }
    if ($this->columns !== "*") {
      if (!count($this->columns)) {
        die_error("Must specify the concerning columns, through ->setColumns() or ->addColumn().");
      }
    }
    
    /* Obtain column types & derive attributes */
    $attributes = [];
    $sql = "SELECT * FROM `information_schema`.`columns` WHERE `table_name` IN "
          . mysql_set(array_map([$this, "getTableName"], range(0, count($this->tables)-1))) . " ";

    $res = mysql_select($this->db, $sql);
    $raw_mysql_columns = [];
    foreach ($res as $row) {
      // echo get_var_dump($row) . PHP_EOL;
      $raw_mysql_columns[] = $row;
    }
    // var_dump($raw_mysql_columns);
    // var_dump($this->columns);
    
    if ($this->columns === "*") {
      $this->deriveColumnsFromRawMySQLColumns($raw_mysql_columns);
    }

    /* Checks */
    /* And place the output column at the FIRST spot */
    $output_col_in_columns = false;
    foreach ($this->columns as $i_col => $col) {
      if ($this->getColumnName($i_col) == $this->outputColumnName) {
        $output_col_in_columns = true;
        array_splice($this->columns, $i_col, 1);
        array_unshift($this->columns, $col);
        $this->setColumnTreatment(0, $this->outputColumnTreatment);
      }
    }
    if (!($output_col_in_columns)) {
      die_error("The output column name (here \"" . $this->outputColumnName
        . "\") must be in columns.");
    }
    // if($this->identifierColumnName !== NULL) {
    //   $id_col_in_columns = false;
    //   foreach ($this->columns as $i_col => $col) {
    //     if ($this->getColumnName($i_col) == $this->identifierColumnName) {
    //       $id_col_in_columns = true;
    //     }
    //   }
    //   if (!($id_col_in_columns)) {
    //     die_error("The identifier column name (here \"" . $this->identifierColumnName
    //       . "\") must be in columns.");
    //   }
    // }
    if (count($this->tables) > 1) {
      foreach ($this->columns as $i_col => $col) {
        if (!preg_match("/.*\..*/i", $this->getColumnName($i_col))) {
          die_error("Invalid column name: '" . $this->getColumnName($i_col)
            . "'. When reading more than one table, " .
          "please specify column names in their 'table_name.column_name' format.");
        }
      }
      if (!preg_match("/.*\..*/i", $this->identifierColumnName)) {
        die_error("Invalid identifier column name: '"
          . $this->identifierColumnName . "'. When reading more than one table, "
          . "please specify column names in their 'table_name.column_name' format.");
      }
    }
    // var_dump($this->columns);

    /* If any WHERE/JOIN-ON constraint forces the equality between two columns,
        drop one of the resulting attributes. */
    $columnsToIgnore = [];
    $constraints = [];
    if ($this->whereCriteria !== NULL && count($this->whereCriteria)) {
      foreach ($this->whereCriteria as $criterion) {
        $constraints[] = $criterion;
      }
    }
    foreach ($this->tables as $k => $table) {
      $constraints = array_merge($constraints, $this->getTableJoinCritera($k));
    }
    foreach ($constraints as $constraint) {
      if(preg_match("/\s*([\S\.]+)\s*=\s*([\S\.]+)\s*/i", $constraint, $matches)) {
        $fst = $matches[1];
        $snd = $matches[2];

        if (!in_array($fst, [$this->identifierColumnName, $this->outputColumnName])
          && !in_array($fst, $columnsToIgnore)) {
          $columnsToIgnore[] = $fst;
        } else if (!in_array($snd, [$this->identifierColumnName, $this->outputColumnName])
          && !in_array($snd, $columnsToIgnore)) {
          $columnsToIgnore[] = $snd;
        } else {
          // TODO
          die_error("TODO unexpected case encountered when removing redundant columns.");
        }
      }
    }

    /* Create attributes from column info */
    for ($i_col = 0; $i_col < count($this->columns); $i_col++) {
      if (in_array($this->getColumnName($i_col), $columnsToIgnore)) {
        $attribute = NULL;
      }
      else {
        $mysql_column = NULL;
        /* Find column */
        foreach ($raw_mysql_columns as $col) {
          if (in_array($this->getColumnName($i_col),
              [$col["TABLE_NAME"].".".$col["COLUMN_NAME"], $col["COLUMN_NAME"]])) {
            $mysql_column = $col;
            break;
          }
        }
        if ($mysql_column === NULL) {
          die_error("Couldn't retrieve information about column \""
            . $this->getColumnName($i_col) . "\"");
        }
        $this->setColumnMySQLType($i_col, $mysql_column["COLUMN_TYPE"]);
        
        /* Create attribute(s) for this column */
        $attribute = $this->computeAttributesOfColumn($i_col);
      }
      $attributes[] = $attribute;
    }

    /* Finally obtain data */
    
    $sql = $this->getSQLSelectQuery(NULL, $idVal);
    $res = mysql_select($this->db, $sql);
    $data = $this->readRawData($res, $attributes);

    // echo count($data) . " rows retrieved" . PHP_EOL;
    // echo get_var_dump($data);
    
    /* Deflate attribute and data arrays (breaking the symmetry with columns) */
    
    $final_data = [];

    foreach ($data as $attr_vals) {
      $row = [];
      foreach ($attr_vals as $i_col => $attr_val) {
        $attribute = $attributes[$i_col];
        if ($attribute === NULL) {
          // Ignore attribute/value
          continue;
        }
        else if (is_array($attr_val)) {
          foreach ($attr_val as $v) {
            $row[] = $v;
          }
        }
        else {
          die_error("Something's off. Invalid attr_val = " . get_var_dump($attr_val));
          // $row[] = $attr_val;
        }
      }
      $final_data[] = $row;
    }
    
    $final_attributes = [];

    foreach ($attributes[0] as $outputAttribute) {
      if(!($outputAttribute instanceof DiscreteAttribute)) {
        die_error("Output column(s) must be categorical! '"
          . $outputAttribute->getName() . "' is not.");
      }
    }
    foreach ($attributes as $attribute) {
      if ($attribute === NULL) {
        // Ignore attribute/value
        continue;
      }
      else if (is_array($attribute)) {
        foreach ($attribute as $attr) {
          $final_attributes[] = $attr;
        }
      }
      else {
        die_error("Unknown attribute encountered. Must debug code. "
         . get_var_dump($attribute));
      }
    }

    // var_dump($final_attributes);
    if (!count($final_data)) {
      die_error("No data instance found.");
    }
    /* Build instances */
    $data = new Instances($final_attributes, $final_data);
    
    // echo $data->toString(false);
    
    if (DEBUGMODE && $idVal === NULL) {
      $data->save_ARFF("instances");
    }

    return $data;
  }

  function &readRawData(object &$res, array $attributes) : array {

    $data = [];

    foreach ($res as $raw_row) {
      // echo get_var_dump($raw_row) . PHP_EOL;
      
      /* Pre-process data */
      $attr_vals = [];
      for ($i_col = 0; $i_col < count($this->columns); $i_col++) {
        $attribute = $attributes[$i_col];
        
        if ($attribute === NULL) {
          // Ignore attribute/value
          $attr_val = NULL;
        }
        else {
          $attr_val = [];
          $raw_val = $raw_row[$this->getColNickname($this->getColumnName($i_col))];

          if ($raw_val === NULL) {
            foreach ($attribute as $attr) {
              $attr_val[] = NULL;
            }
          }
          else {
            switch (true) {
              /* ForceBinary */
              case $this->getColumnTreatmentType($i_col) == "ForceBinary":

                // TODO figure out: a null value means what?
                /* Append k values, one for each of the classes */
                $classes = $this->getColumnTreatmentArg($i_col, 0);
                foreach ($classes as $class) {
                  $val = intval($class == $raw_val);
                  $attr_val[] = $val;
                }
                break;
               
              /* Text column */
              case $this->getColumnTreatmentType($i_col) == "BinaryBagOfWords":

                // TODO check: a null text means null values for the binary bags, right?
                /* Append k values, one for each word in the dictionary */
                $dict = $this->getColumnTreatmentArg($i_col, 0);
                foreach ($dict as $word) {
                  $val = in_array($word, $this->text2words($raw_val));
                  $attr_val[] = $val;
                }
                break;
               
              default: /* Single value */
                if (count($attribute) != 1) {
                  die_error("Something's off. Found multiple attributes for column "
                    . $this->getColumnName($i_col)
                    . " ($i_col)" . get_var_dump($attribute));
                }
                $attribute = $attribute[0];

                /* For categorical attributes, use the class index as value */
                if ($attribute instanceof DiscreteAttribute) {
                  $val = $attribute->getKey($raw_val);
                  if ($val === false) {
                    /* When forcing categorical, push the unfound values to the domain */
                    if (in_array($this->getColumnTreatmentType($i_col), ["ForceCategorical", "ForceCategoricalIfNotEnum"])) {
                      $attribute->pushDomainVal($raw_val);
                      $val = $attribute->getKey($raw_val);
                    }
                    else {
                      die_error("Something's off. Couldn't find element \"" . toString($raw_val) . "\" in domain of attribute {$attribute->getName()}. ");
                    }
                  }
                }
                /* Dates & Datetime values */
                else if (in_array($this->getColumnMySQLType($i_col), ["date", "datetime"])) {
                  $type_to_format = [
                    "date"     => "Y-m-d"
                  , "datetime" => "Y-m-d H:i:s"
                  ];
                  $date = DateTime::createFromFormat($type_to_format[$this->getColumnMySQLType($i_col)], $raw_val);
                  if (!($date !== false))
                    die_error("Incorrect date string \"$raw_val\"");

                  switch ($this->getColumnTreatmentType($i_col)) {
                    /* By default, DaysSince is used. */
                    case NULL:
                      // break;
                    case "DaysSince":
                      $today = new DateTime("now");
                      $val = intval($date->diff($today)->format("%R%a"));
                      break;
                    case "MonthsSince":
                      $today = new DateTime("now");
                      $val = intval($date->diff($today)->format("%R%m"));
                      break;
                    case "YearsSince":
                      $today = new DateTime("now");
                      $val = intval($date->diff($today)->format("%R%y"));
                      break;
                    default:
                    die_error("Unknown treatment for {$this->getColumnMySQLType($i_col)} column \"" .
                      $this->getColumnTreatmentType($i_col) . "\"");
                      break;
                  };
                }
                $attr_val = [$val];
                break;
            }
          }
        }
        $attr_vals[] = $attr_val;
      } // foreach ($this->columns as $i_col => $column)

      // Check that the identifier column actually identifies single rows,
      //  and merge rows, just in case.
      if ($this->identifierColumnName !== NULL) {
        $idVal = $raw_row[$this->getColNickname($this->identifierColumnName)];
        if (isset($data[$idVal])) {
          $attr_vals_orig = &$data[$idVal];

          foreach (zip($attr_vals_orig, $attr_vals) as $i_col => $z) {
            if ($z[0] !== $z[1]) {
              if ($this->outputColumnName != $this->getColumnName($i_col)) {
                die_error("Found more than one row with same identifier value: '{$this->identifierColumnName}' = " . get_var_dump($idVal)
                  . ", but merging on column " . $this->getColumnName($i_col)
                  . " ($i_col) failed. "
                  . get_var_dump($z[0]) . get_var_dump($z[1])
                  . get_var_dump($attr_vals_orig) . get_var_dump($attr_vals)
                  . "Suggestion: explicitly ask to ignore this column." //TODO
                  // ". The identifier column must unique identify each data instance."
                          //   // . get_var_dump($row) . "\n"
                          );
              }
              else {
                $attribute = $attributes[$i_col];
                if (is_array($attr_vals_orig[$i_col])) {
                  foreach (zip($z[0], $z[1]) as $a => $val) {
                    if ($attribute[$a]->getType() == "bool") {
                      $attr_vals_orig[$i_col][$a] = $attr_vals_orig[$i_col][$a] && $z[1][$a];
                    }
                    else {
                      die_error("Found more than one row with same identifier value: '{$this->identifierColumnName}' = " . get_var_dump($idVal)
                      . ", but I don't know how to merge values for column " . $this->getColumnName($i_col)
                      . " ($i_col) of type '{$attribute[$a]->getType()}'. "
                      . "Suggestion: specify ForceBinary treatment for this column." //TODO
                      // . get_var_dump($z[0]) . get_var_dump($z[1])
                      // . get_var_dump($attr_vals_orig) . get_var_dump($attr_vals)
                      );
                    }
                  }
                } else if ($data[$idVal][$i_col] !== NULL) {
                die_error("Something's off. Invalid attr_val = " . get_var_dump($data[$idVal][$i_col]));
                }
              }
            }
          };
          // 
        } else {
          $data[$idVal] = $attr_vals;
        }
      } else {
        $data[] = $attr_vals;
      }
    } // foreach ($res as $raw_row)

    return $data;
  }
  /**
   * Load an existing discriminative model.
   * Defaulted to the model trained the most recently
   */
  function loadModel(?string $path = NULL) {
    echo "DBFit->loadModel($path)" . PHP_EOL;
    
    /* Default path to that of the latest model */
    if ($path === NULL) {
      $models = filesin(MODELS_FOLDER);
      if (count($models) == 0) {
        die_error("loadModel: No model to load in folder: \"". MODELS_FOLDER . "\"");
      }
      sort($models, true);
      $path = $models[0];
      echo "$path";
    }

    $this->model = DiscriminativeModel::loadFromFile($path);
  }

  /* Train and test a model on the data, and save to database */
  function updateModel() {
    echo "DBFit->updateModel()" . PHP_EOL;

    if(!($this->learner instanceof Learner))
      die_error("Learner is not initialized. Use ->setLearner() or ->setLearningMethod()");

    if(!($this->model instanceof DiscriminativeModel))
      die_error("Model is not initialized");

    $data = $this->readData();

    if ($this->trainingMode === NULL) {
      $this->trainingMode = $this->defTrainingMode;
      echo "Training mode defaulted to " . toString($this->trainingMode);
    }

    /* training modes */
    switch (true) {
      /* Full training: use data for both training and testing */
      case $this->trainingMode == "FullTraining":
        $trainData = $data;
        $testData = $data;
        break;
      
      /* Train+test split */
      case is_array($this->trainingMode):
        $trRat = $this->trainingMode[0]/($this->trainingMode[0]+$this->trainingMode[1]);
        // TODO 
        $data->randomize();
        list($trainData, $testData) = Instances::partition($data, $trRat);
        
        break;
      
      default:
        die_error("Unknown training mode: " . toString($this->trainingMode));
        break;
    }

    echo "TRAIN" . PHP_EOL . $trainData->toString(true) . PHP_EOL;
    echo "TEST" . PHP_EOL . $testData->toString(true) . PHP_EOL;
    // die_error("TODO");
    
    /* Train */
    $this->model->fit($trainData, $this->learner);
    
    echo "Ultimately, here are the extracted rules: " . PHP_EOL;
    foreach ($this->model->getRules() as $x => $rule) {
      echo $x . ": " . $rule->toString() . PHP_EOL;
    }

    /* Test */
    $this->test($testData);
    // $this->model->save(join_paths(MODELS_FOLDER, date("Y-m-d_H:i:s")));
    
    $this->model->dumpToDB($this->db,
      str_replace(".", "_", $this->getOutputColumnName()));
      // . "_" . join("", array_map([$this, "getColumnName"], range(0, count($this->columns)-1))));
   
    $this->model->saveToDB($this->db,
     str_replace(".", "_", $this->getOutputColumnName())
     , $testData);

  }

  /* Use the model for predicting on a set of instances */
  function predict(Instances $inputData) : array {
    echo "DBFit->predict(" . $inputData->toString(true) . ")" . PHP_EOL;

    if(!($this->model instanceof DiscriminativeModel))
      die_error("Model is not initialized");

    return $this->model->predict($inputData);
  }

  /* Use the model for predicting the value of the output columns for a new instance,
      identified by the identifier column */
  function predictByIdentifier(string $idVal) : array {
    echo "DBFit->predictByIdentifier($idVal)" . PHP_EOL;

    if(!($this->model instanceof DiscriminativeModel))
      die_error("Model is not initialized");

    if($this->identifierColumnName === NULL)
      die_error("In order to predictByIdentifier, an identifierColumnName must be set."
        . " Use ->setIdentifierColumnName()");

    $data = $this->readData($idVal);

    if(false && $idVal !== NULL && count($data) !== 1) {
      // TODO figure out, possible?
      die_error("Found more than one instance at predict time. {$this->identifierColumnName} = $idVal");
    }

    // var_dump($data);
    $predict = $this->predict($data);
    // var_dump($predict);
    $predictions = [$predict];
    var_dump($predictions);

    return $predictions;
  }

  // Test the model
  function test(Instances $testData) {
    echo "DBFit->test(" . $testData->toString(true) . ")" . PHP_EOL;

    $ground_truths = [];
    $classAttr = $testData->getClassAttribute();

    for ($x = 0; $x < $testData->numInstances(); $x++) {
      $ground_truths[] = $classAttr->reprVal($testData->inst_classValue($x));
    }

    // $testData->dropOutputAttr();
    $predictions = $this->predict($testData);
    
    // echo "\$ground_truths : " . get_var_dump($ground_truths) . PHP_EOL;
    // echo "\$predictions : " . get_var_dump($predictions) . PHP_EOL;
    $negatives = 0;
    $positives = 0;
    if (DEBUGMODE > 1) {
      foreach ($ground_truths as $i => $val) {
        echo "[" . $val . "," . $predictions[$i] . "]";
      }
    }
    if (DEBUGMODE > 1) echo "\n";
    foreach ($ground_truths as $i => $val) {
      if ($ground_truths[$i] != $predictions[$i]) {
        $negatives++;
      } else {
        $positives++;
      }
    }
    echo "Test accuracy: " . ($positives/($positives+$negatives));
    echo "\n";
    
    // TODO compute confusion matrix, etc. using $predictions $ground_truths
  }

  /* DEBUG-ONLY - TODO remove */
  function test_all_capabilities() {
    echo "DBFit->test_all_capabilities()" . PHP_EOL;
    
    $start = microtime(TRUE);
    $this->updateModel();
    $end = microtime(TRUE);
    echo "updateModel took " . ($end - $start) . " seconds to complete." . PHP_EOL;
    
    $start = microtime(TRUE);
    $this->model->LoadFromDB($this->db, str_replace(".", "_", $this->getOutputColumnName()));
    $end = microtime(TRUE);
    echo "LoadFromDB took " . ($end - $start) . " seconds to complete." . PHP_EOL;

    if ($this->identifierColumnName !== NULL) {
      $start = microtime(TRUE);
      $this->predictByIdentifier(1);
      $end = microtime(TRUE);
      echo "predictByIdentifier took " . ($end - $start) . " seconds to complete." . PHP_EOL;
    }
  }

  /* TODO explain */
  function getColumnNames($IncludeIdCol = false) {
    $cols = array_map([$this, "getColumnName"], range(0, count($this->columns)-1));
    if ($IncludeIdCol && $this->identifierColumnName !== NULL) {
      if (!in_array($this->identifierColumnName, $cols)) {
        $cols[] = $this->identifierColumnName;
      }
    }
    return $cols;
  }

  /* Need a nickname for every column when using table.column format,
      since PHP MySQL connctions do not allow to access result fields
      using this format */
  function getColNickname($colName) {
    return str_replace(".", "_DOT_", $colName);
  }

  /* TODO explain */
  function getSQLSelectQuery($cols = NULL, $idVal = NULL) {
    if ($cols === NULL) {
      $cols = $this->getColumnNames(true);
    }
    listify($cols);
    foreach ($cols as &$col) {
      $col = $col . " AS " . $this->getColNickname($col);
    }

    $sql = "SELECT " . mysql_list($cols, "noop") . " FROM ";
    
    foreach ($this->tables as $k => $table) {
      if ($k == 0) {
      $sql .= $this->getTableName($k);
      }
      else {
        $sql .= $this->getTableJoinType($k) . " " . $this->getTableName($k);
        $crit = $this->getTableJoinCritera($k);
        if (count($crit)) {
          // TODO remove duplicate attributes
          $sql .= " ON " . join(" AND ", $crit);
        }
      }
      $sql .= " ";
    }

    $whereCriteria = [];
    if ($this->whereCriteria !== NULL && count($this->whereCriteria)) {
      $whereCriteria = array_merge($whereCriteria, $this->whereCriteria);
    }
    if ($idVal !== NULL) {
      if($this->identifierColumnName === NULL)
        die_error("An identifier column name must be set. Use ->setIdentifierColumnName()");
      $whereCriteria[] = $this->identifierColumnName . " = $idVal";
    }

    if (count($whereCriteria)) {
      $sql .= " WHERE " . join("AND ", $whereCriteria);
    }

    if ($this->limit !== NULL) {
      if ($idVal === NULL) {
        $sql .= " LIMIT {$this->limit}";
      }
      // else {
      //   warn("Limit term ignored at predict time");
      // }

    }

    return $sql;
  }

  function computeAttributesOfColumn($i_col)
  {
    $attr_name = $this->getColumnAttrName($i_col);

    switch(true) {
      /* Forcing a categorical attribute */
      case $this->getColumnTreatmentType($i_col) == "ForceCategorical":
        $attribute = [new DiscreteAttribute($attr_name, "enum")];
        break;
      /* Enum column */
      case $this->getColumnAttrType($i_col) == "enum":
        $domain_arr_str = (preg_replace("/enum\((.*)\)/i", "[$1]", $this->getColumnMySQLType($i_col)));
        eval("\$domain_arr = " . $domain_arr_str . ";");
        $attribute = [new DiscreteAttribute($attr_name, "enum", $domain_arr)];
        break;
      /* Forcing a categorical attribute */
      case $this->getColumnTreatmentType($i_col) == "ForceCategoricalIfNotEnum":
        $attribute = [new DiscreteAttribute($attr_name, "enum")];
        break;
      /* Forcing a set of binary categorical attributes */
      case $this->getColumnTreatmentType($i_col) == "ForceBinary":
        
        /* Find classes */
        $classes = [];
        $sql = $this->getSQLSelectQuery($this->getColumnName($i_col));
        $res = mysql_select($this->db, $sql);

        foreach ($res as $raw_row) {
          $class = $raw_row[$this->getColNickname($this->getColumnName($i_col))];
          $classes[$class] = 0;
        }
        $classes = array_keys($classes);

        // var_dump($classes);
        $attribute = [];

        foreach ($classes as $class) {
          $attribute[] = new DiscreteAttribute($attr_name . "/" . $class, "bool", ["0", "1"]);
        }
        $this->setColumnTreatmentArg($i_col, 0, $classes);

        break;
      /* Numeric column */
      case in_array($this->getColumnAttrType($i_col), ["int", "float", "double"]):
        $attribute = [new ContinuousAttribute($attr_name, $this->getColumnAttrType($i_col))];
        break;
      /* Boolean column */
      case in_array($this->getColumnAttrType($i_col), ["bool", "boolean"]):
        $attribute = [new DiscreteAttribute($attr_name, "bool", ["0", "1"])];
        break;
      /* Text column */
      case $this->getColumnAttrType($i_col) == "text":
        switch($this->getColumnTreatmentType($i_col)) {
          case "BinaryBagOfWords":
            /* Binary attributes indicating the presence of each word */
            $generateDictAttrs = function($dict) use ($attr_name, $i_col) {
              $attribute = [];
              foreach ($dict as $word) {
                $attribute[] = new DiscreteAttribute("'$word' in $attr_name",
                  "word_presence", ["N", "Y"]);
              }
              $this->setColumnTreatmentArg($i_col, 0, $dict);
              return $attribute;
            };

            /* The argument can be the dictionary size (k), or more directly the dictionary */
            if ( is_integer($this->getColumnTreatmentArg($i_col, 0))) {
              $k = $this->getColumnTreatmentArg($i_col, 0);

              /* Find $k most frequent words */
              $word_counts = [];
              $sql = $this->getSQLSelectQuery($this->getColumnName($i_col));
              $res = mysql_select($this->db, $sql);
              
              if (!isset($this->stop_words)) {
                $lang = "en";
                $this->stop_words = explode("\n", file_get_contents($lang . "-stopwords.txt"));
              }
              foreach ($res as $raw_row) {
                $text = $raw_row[$this->getColNickname($this->getColumnName($i_col))];
                
                $words = $this->text2words($text);

                foreach ($words as $word) {
                  if (!isset($word_counts[$word]))
                    $word_counts[$word] = 0;
                  $word_counts[$word] += 1;
                }
              }
              // var_dump($word_counts);
              
              if (!count($word_counts)) {
                warn("Couldn't derive a BinaryBagOfWords dictionary for attribute \"" .
                  $this->getColumnName($i_col) . "\". This column will be ignored.");

                $attribute = NULL;
              } else {
                $dict = [];
                // TODO optimize this?
                foreach (range(0, $k-1) as $i) {
                  $max_count = max($word_counts);
                  $max_word = array_search($max_count, $word_counts);
                  $dict[] = $max_word;
                  unset($word_counts[$max_word]);
                  if (!count($word_counts)) {
                    break;
                  }
                }
                // var_dump($dict);
                
                if (count($dict) < $k) {
                  warn("Couldn't derive a BinaryBagOfWords dictionary of size $k for attribute \"" 
                    . $this->getColumnName($i_col) . "\". Dictionary of size "
                    . count($dict) . " will be used.");
                }
                $attribute = $generateDictAttrs($dict);
              }
            }
            else if (is_array($this->getColumnTreatmentArg($i_col, 0))) {
              $dict = $this->getColumnTreatmentArg($i_col, 0);
              $attribute = $generateDictAttrs($dict);
            }
            else {
              die_error("Please specify a parameter (dictionary or dictionary size)"
                . " for bag-of-words"
                . " processing column '" . $this->getColumnName($i_col) . "'.");
            }
            break;
          default:
            die_error("Unknown treatment for text column \""
               . $this->getColumnName($i_col) . "\" : "
               . get_var_dump($this->getColumnTreatmentType($i_col)));
            break;
        }
        break;
      default:
        die_error("Unknown column type: " . $this->getColumnMySQLType($i_col));
        break;
    }
    return $attribute;
  }
  // TODO use Nlptools
  function text2words($text) {
    if ($text === NULL) {
      return [];
    }
    $text = strtolower($text);
    
    # to keep letters only (remove punctuation and such)
    $text = preg_replace('/[^a-z]+/i', '_', $text);
    
    # tokenize
    $words = array_filter(explode("_", $text));

    # remove stopwords
    $words = array_diff($words, $this->stop_words);

    # lemmatize
    // lemmatize($text)

    # stem
    $words = array_map(["PorterStemmer", "Stem"], $words);
    
    return $words;
  }


  static function isEnumType(string $mysql_type) {
    return preg_match("/enum.*/i", $mysql_type);
  }

  static function isTextType(string $mysql_type) {
    return preg_match("/varchar.*/i", $mysql_type) ||
           preg_match("/text.*/i", $mysql_type);
  }


  function getTableName(int $i) : string {
    return $this->tables[$i]["name"];
  }
  function &getTableJoinCritera(int $i) {
    return $this->tables[$i]["join_criteria"];
  }
  function &getTableJoinType(int $i) {
    return $this->tables[$i]["join_type"];
  }

  function getColumnName(int $i, bool $force_no_table_name = false) : string {
    $n = $this->columns[$i]["name"];
    return $force_no_table_name && count(explode(".", $n)) > 1 ? explode(".", $n)[1] : $n;
  }
  function &getColumnTreatment(int $i) {
    if ($this->columns[$i]["treatment"] !== NULL)
      listify($this->columns[$i]["treatment"]);
    $tr = &$this->columns[$i]["treatment"];
    if (($tr === NULL) && $this->getColumnAttrType($i, $tr) === "text"
           && isset($this->defaultOptions["TextTreatment"])) {
      $this->setColumnTreatment($i, $this->defaultOptions["TextTreatment"]);
      return $this->getColumnTreatment($i);
    }

    return $tr;
  }
  function getColumnTreatmentType(int $i) {
    $tr = $this->getColumnTreatment($i);
    $t = !is_array($tr) ? $tr : $tr[0];
    return $t;
  }
  function getColumnTreatmentArg(int $i, int $j) {
    $tr = $this->getColumnTreatment($i);
    return !is_array($tr) || !isset($tr[1+$j]) ? NULL : $tr[1+$j];
  }
  function setColumnTreatment(int $i, $val) {
    $this->columns[$i]["treatment"] = $val;
  }
  function setColumnTreatmentArg(int $i, int $j, $val) {
    $this->getColumnTreatment($i)[1+$j] = $val;
  }
  function getColumnAttrName(int $i) {
    $col = $this->columns[$i];
    return !array_key_exists("attr_name", $col) ?
        $this->getColumnName($i, true) : $col["attr_name"];
  }

  function getColumnMySQLType(int $i) {
    $col = $this->columns[$i];
    return $col["mysql_type"];
  }
  function setColumnMySQLType(int $i, $val) {
    $this->columns[$i]["mysql_type"] = $val;
  }

  function getColumnAttrType(int $i, $tr = -1) {
    $mysql_type = $this->getColumnMySQLType($i);
    if (self::isEnumType($mysql_type)) {
      return "enum";
    }
    else if (self::isTextType($mysql_type)) {
      return "text";
    } else {
      if ($tr === -1) {
        $tr = $this->getColumnTreatmentType($i);
      }
      if (isset(self::$col2attr_type[$mysql_type])) {
        return self::$col2attr_type[$mysql_type][$tr];
      } else {
        die_error("Unknokn column type: \"$mysql_type\"! Code must be expanded to cover this one!");
      }
    }
  }


  function getDb() : object
  {
    return $this->db;
  }

  function setDb(object $db) : self
  {
    $this->db = $db;
    return $this;
  }

  function setTables($tables) : self
  {
    listify($tables);
    $this->tables = [];
    foreach ($tables as $table) {
      $this->addTable($table);
    }

    return $this;
  }

  function addTable($tab) : self
  {
    $new_tab = [];
    $new_tab["name"] = NULL;
    $new_tab["join_criteria"] = [];
    $new_tab["join_type"] = count($this->tables) ? "INNER JOIN" : "";

    if (is_string($tab)) {
      $new_tab["name"] = $tab;
    } else if (is_array($tab)) {
      $new_tab["name"] = $tab[0];
      if (isset($tab[1])) {
        if (!count($this->tables)) {
          die_error("Join criteria can't be specified for the first specified table: "
          . "\"{$tab[0]}\": ");
        }

        listify($tab[1]);
        $new_tab["join_criteria"] = $tab[1];
      }
      if (isset($tab[2])) {
        $new_tab["join_type"] = $tab[2];
      }
    } else {
      die_error("Malformed table: " . toString($tab));
    }

    if (!is_array($this->tables)) {
      die_error("Can't addTable at this time! Use ->setTables() instead.");
    }
    $this->tables[] = &$new_tab;
    
    return $this;
  }

  function setColumns($columns) : self
  {
    if ($columns === "*") {
      $this->columns = $columns;
    } else {
      listify($columns);
      $this->columns = [];
      foreach ($columns as $col) {
        $this->addColumn($col);
      }
    }

    return $this;
  }

  function addColumn($col) : self
  {
    $new_col = [];
    $new_col["name"] = NULL;
    $new_col["treatment"] = NULL;
    $new_col["attr_name"] = NULL;
    $new_col["mysql_type"] = NULL;
    if (is_string($col)) {
      $new_col["name"] = $col;
    } else if (is_array($col)) {
      $new_col["name"] = $col[0];
      if (isset($col[1])) {
        listify($col[1]);
        $new_col["treatment"] = $col[1];
      }
      if (isset($col[2])) {
        $new_col["attr_name"] = $col[2];
      }
    } else {
      die_error("Malformed column: " . toString($col));
    }

    if ($new_col["attr_name"] === NULL) {
      $new_col["attr_name"] = $new_col["name"];
    }

    if (!is_array($this->columns)) {
      die_error("Can't addColumn at this time! Use ->setColumns() instead.");
    }

    $this->columns[] = &$new_col;
    
    return $this;
  }

  function deriveColumnsFromRawMySQLColumns(array $raw_mysql_columns) {
    $cols = [];
    foreach ($raw_mysql_columns as $raw_col) {
      $cols[] = $raw_col["TABLE_NAME"].".".$raw_col["COLUMN_NAME"];
    }
    echo toString($cols) . "\n";
    $this->setColumns($cols);
  }

  function getOutputColumnName() : string
  {
    return $this->outputColumnName;
  }

  function setOutputColumnName(?string $outputColumnName, $treatment = "ForceCategoricalIfNotEnum") : self
  {
    if ($this->outputColumnName !== NULL && $this->identifierColumnName !== NULL
      && $this->outputColumnName == $this->identifierColumnName) {
      die_error("Output column ('{$this->outputColumnName}') cannot be used as identifier.");
    }
    $this->outputColumnName = $outputColumnName;
    $this->outputColumnTreatment = $treatment;
    return $this;
  }

  // function getIdentifierColumnName() : string
  // {
  //   return $this->identifierColumnName;
  // }

  function setIdentifierColumnName(?string $identifierColumnName) : self
  {
    if ($this->outputColumnName !== NULL && $this->identifierColumnName !== NULL
      && $this->outputColumnName == $this->identifierColumnName) {
      die_error("Identifier column ('{$this->outputColumnName}') cannot be considered as the output column.");
    }
    $this->identifierColumnName = $identifierColumnName;
    return $this;
  }

  function setWhereCriteria($whereCriteria) : self
  {
    listify($whereCriteria);
    foreach ($whereCriteria as $jc) {
      if (!is_string($jc)) {
        die_error("Non-string value encountered in whereCriteria: "
        . "\"$jc\": ");
      }
    }
    $this->whereCriteria = $whereCriteria;
    return $this;
  }


  function setLimit(?int $limit) : self
  {
    $this->limit = $limit;
    return $this;
  }

  function setLearner(Learner $learner) : self
  {
    $this->learner = $learner;

    $this->model = $this->learner->initModel();

    return $this;
  }

  function getLearner() : string
  {
    return $this->learner;
  }

  function setLearningMethod(string $learningMethod) : self
  {
    if(!($learningMethod == "PRip"))
      die_error("Only \"PRip\" is available as a learning method");

    $learner = new PRip();
    // TODO $learner->setNumOptimizations(20);
    $this->setLearner($learner);

    return $this;
  }

  function getTrainingMode()
  {
    return $this->trainingMode;
  }

  function setTrainingMode($trainingMode) : self
  {
    $this->trainingMode = $trainingMode;
    return $this;
  }

  function setTrainingSplit(array $trainingMode) : self
  {
    $this->setTrainingMode($trainingMode);
    return $this;
  }

  function setDefaultOption($opt_name, $opt) : self
  {
    $this->defaultOptions[$opt_name] = $opt;
    return $this;
  }

}

?>
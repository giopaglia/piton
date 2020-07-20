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

  /* Names of the concerning database tables (array of strings) */
  private $tableNames;

  /* Join criterion for the concerning tables (array of strings) */
  private $joinCriterion;

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

  /* Attribute to predict (must be categorical) */
  private $outputColumnName;

  /* Limit term in the SELECT query */
  private $limit;


  /* Type of the discriminative model (string) */
  private $modelType;

  /* Learning procedure in use (string) */
  private $learningMethod;


  /* Discriminative model trained/loaded */
  private $model;

  /* Optimizer for training the model */
  private $learner;

  /* Training mode (e.g full training, or perform train/test split) */
  private $trainingMode;

  /* Data */
  private $data;


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
  , "float"   => ["" => "float"]
  , "real"    => ["" => "float"]
  , "double"  => ["" => "double"]
  , "boolean" => ["" => "bool"]
  , "enum"    => ["" => "enum"]
  ];

  function __construct(object $db) {
    echo "DBFit(DB)" . PHP_EOL;
    if(!(get_class($db) == "mysqli"))
      die_error("DBFit requires a mysqli object, but got object of type "
        . get_class($db) . ".");
    $this->db = $db;
    $this->setTableNames(NULL);
    $this->joinCriterion = NULL;
    $this->columns = NULL;
    $this->setOutputColumnName(NULL);
    $this->setLimit(NULL);
    // $this->setModelType("RuleBased");
    // $this->setLearningMethod("RIPPER");
    $this->model = NULL;
    $this->learner = NULL;
    // $this->setTrainingMode("FullTraining");
    $this->trainingMode = NULL;
    $this->data = NULL;
  }

  /** Read data & pre-process it */
  private function readData(bool $force = false) {
    if ($this->data !== NULL && !$force) {
      return;
    }

    echo "DBFit->readData()" . PHP_EOL;

    /* Checks */
    /* And place the output column into the FIRST spot */
    $output_col_in_columns = false;
    foreach ($this->columns as $i_col => $col) {
    	if ($this->getColumnName($i_col) == $this->outputColumnName) {
    		$output_col_in_columns = true;
        array_splice($this->columns, $i_col, 1);
        array_unshift($this->columns, $col);
        break;
      }
    }
	  if (!($output_col_in_columns)) {
      die_error("The output column name (here \"" . $this->outputColumnName
        . "\") must be in columns.");
    }
    if (count($this->tableNames) > 1) {
      foreach ($this->columns as $i_col => $col) {
        if (!preg_match("/.*\..*/i", $this->getColumnName($i_col))) {
          die_error("When reading more than one table, " .
              "please specify column names in their 'table_name.column_name' form");
        }
      }
    }
    
    /* Obtain column types & derive attributes */
    $attributes = [];
    $sql = "SELECT * FROM `information_schema`.`columns` WHERE `table_name` IN "
          . mysql_set($this->tableNames) . " ";
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $raw_mysql_columns = [];
    $res = $stmt->get_result();
    if (!($res !== false))
      die_error("SQL query failed: $sql");

    foreach ($res as $row) {
      // echo get_var_dump($row) . PHP_EOL;
      $raw_mysql_columns[] = $row;
    }
    // var_dump($raw_mysql_columns);
    // var_dump($this->columns);
    
    /* TODO: If the JOIN operation forces the equality between two columns,
        drop one of the resulting attributes.
    if ($this->joinCriterion != NULL && count($this->joinCriterion)) {
      foreach ($this->joinCriterion as $criterion) {
        if(preg_match("/.*[\s\w]=[\s\w].*
        /i", $criterion)) {

        }
      }
    }*/
    
    /* Create attributes from column info */
    foreach ($this->columns as $i_col => $column) {
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

      /* Create attribute */
      $attr_name = $this->getColumnAttrName($i_col);

      switch(true) {
        /* Forcing a categorical attribute */
        case $this->getColumnTreatmentType($i_col) == "ForceCategorical":
          $attribute = new DiscreteAttribute($attr_name, "enum");
          break;
        /* Numeric column */
        case in_array($this->getColumnMySQLType($i_col), ["int", "float", "double", "real", "date", "datetime"]):
          $attribute = new ContinuousAttribute($attr_name, $this->getColumnAttrType($i_col));
          break;
        /* Enum column */
        case self::isEnumType($this->getColumnMySQLType($i_col)):
          $domain_arr_str = (preg_replace("/enum\((.*)\)/i", "[$1]", $this->getColumnMySQLType($i_col)));
          eval("\$domain_arr = " . $domain_arr_str . ";");
          $attribute = new DiscreteAttribute($attr_name, "enum", $domain_arr);
          break;
        /* Text column */
        case self::isTextType($this->getColumnMySQLType($i_col)):
          switch($this->getColumnTreatmentType($i_col)) {
            case "BinaryBagOfWords":
              /* The argument can be the dictionary size (k), or more directly the dictionary */
              if ( is_integer($this->getColumnTreatmentArg($i_col, 0))) {
                $k = $this->getColumnTreatmentArg($i_col, 0);

                /* Find $k most frequent words */
                $word_counts = [];
                $sql = $this->getSQLSelectQuery($this->getColumnName($i_col));
                echo "SQL: $sql" . PHP_EOL;
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                
                if (!isset($this->stop_words)) {
                  $lang = "en";
                  $this->stop_words = explode("\n", file_get_contents($lang . "-stopwords.txt"));
                }
                $res = $stmt->get_result();
                if (!($res !== false))
                  die_error("SQL query failed: $sql");
                foreach ($res as $raw_row) {
                  $text = $raw_row[$this->getColumnName($i_col, true)];
                  
                  $words = $this->text2words($text);

                  foreach ($words as $word) {
                    if (!isset($word_counts[$word]))
                      $word_counts[$word] = 0;
                    $word_counts[$word] += 1;
                  }
                }
                // var_dump($word_counts);
                
                $dict = [];
                // TODO optimize this?
                foreach (range(0, $k-1) as $i) {
                  $max_count = max($word_counts);
                  $max_word = array_search($max_count, $word_counts);
                  $dict[] = $max_word;
                  unset($word_counts[$max_word]);
                }
                // var_dump($dict);
              }
              else if (is_array($this->getColumnTreatmentArg($i_col, 0))) {
                $dict = $this->getColumnTreatmentArg($i_col, 0);
              }
              else {
                die_error("Please specify a parameter (dictionary or dictionary size)"
                  . " for bag-of-words"
                  . " processing column '" . $this->getColumnName($i_col) . "'.");
              }

              /* Binary attributes indicating the presence of each word */
              $attribute = [];
              foreach ($dict as $word) {
                $attribute[] = new DiscreteAttribute("'$word' in $attr_name",
                  "word_presence", ["N", "Y"]);
              }

              $this->setColumnTreatmentArg($i_col, 0, $dict);
              break;
            default:
              die_error("Unknown treatment for text column: " . $this->getColumnName($i_col));
              break;
          }
          break;
        default:
          die_error("Unknown column type: " . $this->getColumnMySQLType($i_col));
          break;
      }

      $attributes[] = $attribute;
    }

    /* Finally obtain data */
    $data = [];
    
    $sql = $this->getSQLSelectQuery(array_map([$this, "getColumnName"], range(0, count($this->columns)-1)));
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!($res !== false))
      die_error("SQL query failed: $sql");
    foreach ($res as $raw_row) {
      // echo get_var_dump($raw_row) . PHP_EOL;
      
      /* Pre-process data */
      $row = [];
      foreach ($this->columns as $i_col => $column) {
        $attribute = $attributes[$i_col];

        $raw_val = $raw_row[$this->getColumnName($i_col, true)];
        
        switch (true) {
          /* Text column */
          case $this->getColumnTreatmentType($i_col) == "BinaryBagOfWords":

            /* Append k values, one for each word in the dictionary */
            $dict = $this->getColumnTreatmentArg($i_col, 0);
            foreach ($dict as $word) {
              $val = in_array($word, $this->text2words($raw_val));
              $row[] = $val;
            }
            break;
           
          default:
            /* Default value (the original, raw one) */
            $val = $raw_val;

            if ($raw_val !== NULL) {
              /* For categorical attributes, use the class index as value */
              if ($attribute instanceof DiscreteAttribute) {
                $val = $attribute->getKey($raw_val);
                if ($val === false) {
                  /* When forcing categorical, push the unfound values to the domain */
                  if ($this->getColumnTreatmentType($i_col) == "ForceCategorical") {
                    $attribute->pushDomainVal($raw_val);
                    $val = $attribute->getKey($raw_val);
                  }
                  else {
                    die_error("Something's off. Couldn't find element \"" . get_var_dump($raw_val) . "\" in domain of attribute {$attribute->getName()}. ");
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
                    die_error("Unknown treatment for {$this->getColumnMySQLType($i_col)} column '{$this->getColumnTreatmentType($column)}'");
                    break;
                };
              }
            }
            $row[] = $val;
            break;
        }
      } // foreach ($this->columns as $i_col => $column)
      $data[] = $row;
    } // foreach ($res as $raw_row)

    // echo count($data) . " rows retrieved" . PHP_EOL;
    // echo get_var_dump($data);
    
    /* Linerize attribute array (breaking the symmetry with columns) */
    $final_attributes = [];

    foreach ($attributes as $attribute) {
      if ($attribute instanceof _Attribute) {
        $final_attributes[] = $attribute;
      } else if (is_array($attribute)) {
        foreach ($attribute as $attr) {
          $final_attributes[] = $attr;
        }
      } else {
        die_error("Unknown attribute encountered. Must debug code.");
      }
    }

    /* Build instances */
    $this->data = new Instances($final_attributes, $data);
    
    $this->data->save_ARFF("instances");

    return $this->data;
  }

  /** Train and test a discriminative model onto the data */
  function learnModel() {
    echo "DBFit->learnModel()" . PHP_EOL;
    
    $this->readData();

    /* training modes */
    switch (true) {
      /* Full training: use data for both training and testing */
      case $this->trainingMode == "FullTraining":
        $trainData = $this->data;
        $testData = $this->data;
        break;
      
      /* Train+test split */
      case is_array($this->trainingMode):
        $trRat = $this->trainingMode[0]/($this->trainingMode[0]+$this->trainingMode[1]);
        list($trainData, $testData) = Instances::partition($this->data, $trRat);
        
        break;
      
      default:
        die_error("Unknown training mode ('$this->trainingMode')");
        break;
    }
    
    /* Train */
    $this->model->fit($trainData, $this->learner);
    
    echo "Ultimately, here are the extracted rules: " . PHP_EOL;
    foreach ($this->model->getRules() as $x => $rule) {
      echo $x . ": " . $rule->toString() . PHP_EOL;
    }

    /* Test */
    $this->test($testData);
  }

  /**
   * Load an existing discriminative model.
   * Defaulted to the model trained the most recently
   */
  // TODO check if this works as expected
  function loadModel(?string $path = NULL) {
    echo "DBFit->loadModel($path)" . PHP_EOL;
    
    /* Default path to that of the latest model */
    if ($path == NULL) {
      $models = filesin(MODELS_FOLDER);
      if (count($models) == 0) {
        die_error("loadModel: No model to load in folder: \"". MODELS_FOLDER . "\"");
      }
      sort($models, true);
      $path = $models[0];
      echo "$path";
    }

    $this->model = _DiscriminativeModel::loadFromFile($path);
  }

  /* Learn a model, and save to file */
  function updateModel() {
    echo "DBFit->updateModel()" . PHP_EOL;
    $this->learnModel();
    $this->model->save(join_paths(MODELS_FOLDER, date("Y-m-d_H:i:s")));
  }

  /* Use the model for predicting */
  function predict(Instances $inputData) : array {
    echo "DBFit->predict(" . $inputData->toString(true) . ")" . PHP_EOL;

    if(!($this->model instanceof _DiscriminativeModel))
      die_error("Model is not initialized");

    return $this->model->predict($inputData);
  }

  // Test the model
  function test(?Instances $testData) {
    if ($testData === NULL) {
      $testData = $this->data;
    }
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
    foreach ($ground_truths as $val) {
      echo str_pad($val, 10, " ");
    }
    echo "\n";
    foreach ($predictions as $val) {
      echo str_pad($val, 10, " ");
    }
    echo "\n";
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
    
    $this->readData();
    $this->updateModel();
    
    $end = microtime(TRUE);
    echo "The code took " . ($end - $start) . " seconds to complete.";
  }

  function getSQLSelectQuery($cols) {
    listify($cols);
    $sql = "SELECT " . mysql_list($cols, "noop") . " FROM " . mysql_list($this->tableNames);
    if ($this->limit !== NULL) {
      $sql .= " LIMIT {$this->limit}";
    }

    if ($this->joinCriterion != NULL && count($this->joinCriterion)) {
      $sql .= " WHERE 1";
      foreach ($this->joinCriterion as $criterion) {
        $sql .= " AND $criterion";
      }
    }
    return $sql;
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
    return preg_match("/varchar.*/i", $mysql_type);
  }



  function getColumnName(int $i_col, bool $force_no_table_name = false) {
    $col = $this->columns[$i_col];
    $n = $col["name"];
    return $force_no_table_name && count(explode(".", $n)) > 1 ? explode(".", $n)[1] : $n;
  }
  function &getColumnTreatment(int $i_col) {
    return $this->columns[$i_col]["treatment"];
  }
  function getColumnTreatmentType(int $i_col) {
    $tr = $this->getColumnTreatment($i_col);
    return !is_array($tr) ? $tr : $tr[0];
  }
  function getColumnTreatmentArg(int $i_col, int $i) {
    $tr = $this->getColumnTreatment($i_col);
    return !is_array($tr) || !isset($tr[1+$i]) ? NULL : $tr[1+$i];
  }
  function setColumnTreatmentArg(int $i_col, int $i, $val) {
    $this->getColumnTreatment($i_col)[1+$i] = $val;
    // $col["treatment"][1+$i] = $val;
  }
  function getColumnAttrName(int $i_col) {
    $col = $this->columns[$i_col];
    return !array_key_exists("attr_name", $col) ?
        $this->getColumnName($i_col, true) : $col["attr_name"];
  }

  function getColumnMySQLType(int $i_col) {
    $col = $this->columns[$i_col];
    return $col["mysql_type"];
  }
  function setColumnMySQLType(int $i_col, $val) {
    $this->columns[$i_col]["mysql_type"] = $val;
  }

  function getColumnAttrType(int $i_col) {
    $mysql_type = $this->getColumnMySQLType($i_col);
    if (self::isEnumType($mysql_type)) {
      return "enum";
    }
    else if (self::isTextType($mysql_type)) {
      return "text";
    } else {
      return self::$col2attr_type[$mysql_type][$this->getColumnTreatmentType($i_col)];
    }
  }


  public function getDb() : object
  {
    return $this->db;
  }

  public function setDb(object $db) : self
  {
    $this->data = NULL;

    $this->db = $db;
    return $this;
  }

  public function getTableNames() : array
  {
    return $this->tableNames;
  }

  public function setTableNames($tableNames) : self
  {
    $this->data = NULL;

    listify($tableNames);
    foreach ($tableNames as $tableName) {
      if (!is_string($tableName)) {
        die_error("Non-string value encountered in tableNames: "
        . "\"$tableName\": ");
      }
    }
    $this->tableNames = $tableNames;

    return $this;
  }

  public function getJoinCriterion()
  {
    return $this->joinCriterion;
  }

  public function setJoinCriterion($joinCriterion) : self
  {
    $this->data = NULL;

    listify($joinCriterion);
    foreach ($joinCriterion as $jc) {
      if (!is_string($jc)) {
        die_error("Non-string value encountered in joinCriterion: "
        . "\"$jc\": ");
      }
    }
    $this->joinCriterion = $joinCriterion;
    return $this;
  }

  public function addColumn($col) : self
  {
    $new_col = [];
    $new_col["name"] = NULL;
    $new_col["treatment"] = NULL;
    $new_col["attr_name"] = NULL;
    $new_col["mysql_type"] = NULL;
    if (is_string($col)) {
      $new_col["name"] = $col;
    } else if (is_array($col)) {
      if (isset($col[0])) {
        $new_col["name"] = $col[0];
      }
      if (isset($col[1])) {
        listify($col[1]);
        $new_col["treatment"] = $col[1];
      }
      if (isset($col[2])) {
        $new_col["attr_name"] = $col[2];
      }
    } else {
      die_error("Malformed column: " . get_var_dump($col));
    }

    if ($new_col["attr_name"] == NULL) {
      $new_col["attr_name"] = $new_col["name"];
    }
    $this->columns[] = &$new_col;
    
    return $this;
  }

  public function setColumns(?array $columns) : self
  {
    $this->data = NULL;

    $this->columns = [];
    foreach ($columns as $col) {
      $this->addColumn($col);
    }

    return $this;
  }

  public function getOutputColumnName() : string
  {
    return $this->outputColumnName;
  }

  public function setOutputColumnName(?string $outputColumnName) : self
  {
    $this->data = NULL;

    $this->outputColumnName = $outputColumnName;
    return $this;
  }

  public function getLimit() : int
  {
    return $this->limit;
  }

  public function setLimit(?int $limit) : self
  {
    $this->data = NULL;

    $this->limit = $limit;
    return $this;
  }

  public function getModelType() : string
  {
    return $this->modelType;
  }

  public function setModelType(?string $modelType) : self
  {
    $this->data = NULL;

    $this->modelType = $modelType;
    if(!($this->modelType == "RuleBased"))
      die_error("Only \"RuleBased\" is available as a discriminative model");

    $this->model = new RuleBasedModel();

    return $this;
  }

  public function getLearningMethod() : string
  {
    return $this->learningMethod;
  }

  public function setLearningMethod(?string $learningMethod) : self
  {
    $this->data = NULL;

    $this->learningMethod = $learningMethod;
    if(!($this->learningMethod == "RIPPER"))
      die_error("Only \"RIPPER\" is available as a learning method");

    $this->learner = new PRip();
    return $this;
  }

  public function getTrainingMode()
  {
    return $this->trainingMode;
  }

  public function setTrainingMode($trainingMode) : self
  {
    $this->data = NULL;

    $this->trainingMode = $trainingMode;
    return $this;
  }
}

?>
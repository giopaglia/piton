<?php


include "PorterStemmer.php";
include "DiscriminativeModel/RuleBasedModel.php";
include "DiscriminativeModel/PRip.php";

/*
 * This class can be used to learn intelligent models from a MySQL database.
 *
 * Parameters:
 * - $db                       database access (Object-Oriented MySQL style)
 * - $table_names              names of the concerning database tables (array of strings)
 * - $columns             column names, and an eventual "treatment" for that column (used when processing the data, e.g time in years since the date) (array of (strings/[string, string])). Treatment can be "DaysSince", "MonthsSince", "YearsSince". (TODO ["Default", $value]). Additionally, we can specify how we want to call the derived attribute: for instance, ["BirthDate", "YearsSince", "Age"] creates an "Age" attribute by processing a "BirthDate" sql column.
 * - $join_criterion (?)       join criterion for the concerning tables (TODO se eventualmente uno vuole piu' liberta', questo parametro ed il precedente possono essere sostituiti una stringa $sql, il forse che' fa perdere un po' il senso dell'interfaccia di questa classe)
 * - $output_column_name       attribute to (must be categorical)
 * - $model_type               type of the predictive model (string?)
 * - $learning_method          learning procedure in use
 *
 * Handles different types of attributes:
 * - numerical
 * - categorical (finite domain),
 * - dates
 * - strings
 * 
 */
class DBFit {
  private $model;

  private $db;
  private $table_names;
  private $join_criterion;
  private $columns;
  private $output_column_name;
  private $limit;

  private $model_type;
  private $learning_method;

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
    $this->db = $db;
  }

  private function read_data() {
    echo "DBFit->read_data()" . PHP_EOL;

    /* Checks */
    // And move output column such that it's the FIRST column
    $output_col_in_columns = false;
    foreach ($this->columns as $i_col => $col) {
    	if ($this->getColumnName($i_col) == $this->output_column_name) {
    		$output_col_in_columns = true;
        array_splice($this->columns, $i_col, 1);
        array_unshift($this->columns, $col);
        break;
      }
    }
	  if (!($output_col_in_columns)) {
      die("ERROR! The output column name (here \"{$this->output_column_name}\") must be in columns");
    }

    if (count($this->table_names) > 1) {
      foreach ($this->columns as $i_col => $col) {
        if (!preg_match("/.*\..*/i", $this->getColumnName($i_col))) {
          die("ERROR! When reading more than one table, " .
              "please specify column names in their 'table_name.column_name' form");
        }
      }
    }
    

    /* Obtain column types & derive attributes */
    $attributes = [];
    $sql = "SELECT * FROM `information_schema`.`columns` WHERE `table_name` IN "
          . mysql_set($this->table_names) . " ";
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $raw_mysql_columns = [];
    $res = $stmt->get_result();
    assert($res !== false, "SQL query failed.");

    foreach ($res as $row) {
      // echo get_var_dump($row) . PHP_EOL;
      $raw_mysql_columns[] = $row;
    }
    // var_dump($raw_mysql_columns);
    // var_dump($this->columns);
    
    
    foreach ($this->columns as $i_col => $column) {
      $mysql_column = NULL;
      foreach ($raw_mysql_columns as $col) {
        if (in_array($this->getColumnName($i_col),
            [$col["TABLE_NAME"].".".$col["COLUMN_NAME"], $col["COLUMN_NAME"]])) {
          $mysql_column = $col;
          break;
        }
      }
      if ($mysql_column === NULL) {
        die("Couldn't retrieve information about column \"" . $this->getColumnName($i_col) . "\"");
      }
      $this->setColumnMySQLType($i_col, $mysql_column["COLUMN_TYPE"]);

      // TODO where does "boolean" go? Should end up creating a discrete attr w/ 2 classes.
      $attr_name = $this->getColumnAttrName($i_col);

      switch(true) {
        case $this->getColumnTreatmentType($i_col) == "ForceCategorical":
          $attribute = new DiscreteAttribute($attr_name, "enum");
          break;
        case in_array($this->getColumnMySQLType($i_col), ["int", "float", "double", "real", "date", "datetime"]):
          $attribute = new ContinuousAttribute($attr_name, $this->getColumnAttrType($i_col));
          break;
        case self::isEnumType($this->getColumnMySQLType($i_col)):
          $domain_arr_str = (preg_replace("/enum\((.*)\)/i", "[$1]", $this->getColumnMySQLType($i_col)));
          eval("\$domain_arr = " . $domain_arr_str . ";");
          $attribute = new DiscreteAttribute($attr_name, "enum", $domain_arr);
          break;
        case self::isTextType($this->getColumnMySQLType($i_col)):
          switch($this->getColumnTreatmentType($i_col)) {
            case "BinaryBagOfWords":
              if ( is_numeric($this->getColumnTreatmentArg($i_col, 0))) {
                $k = $this->getColumnTreatmentArg($i_col, 0);

                // Find $k most frequent words
                $word_counts = [];
                $sql = $this->getSQLSelectQuery($this->getColumnName($i_col));
                echo "SQL: $sql" . PHP_EOL;
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                
                if (!isset($this->stop_words)) {
                  $lang = "en";
                  $this->stop_words = explode("\n", file_get_contents($lang . "-stopwords.txt"));
                }

                foreach ($stmt->get_result() as $raw_row) {
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
                die("Please specify a dictionary size for bag-of-words processing column '"
                   . $this->getColumnName($i_col) . "'.");
              }

              // Binary attributes indicating the presence of each word
              $attribute = [];
              foreach ($dict as $word) {
                $attribute[] = new DiscreteAttribute("'$word' in $attr_name",
                  "word_presence", ["✘", "✔"]);
              }

              $this->setColumnTreatmentArg($i_col, 0, $dict);
              break;
            default:
              die("Unknown treatment for text column: " . $this->getColumnName($i_col));
              break;
          }
          break;
        default:
          die("Unknown column type: " . $this->getColumnMySQLType($i_col));
          break;
      }

      $attributes[] = $attribute;
    }

    /* Obtain data */
    $data = [];
    $cols_attrs = zip($attributes, $this->columns);
    // var_dump($cols_attrs);
    
    $sql = $this->getSQLSelectQuery(array_map([$this, "getColumnName"], range(0, count($this->columns)-1)));
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    foreach ($stmt->get_result() as $raw_row) {
      // echo get_var_dump($raw_row) . PHP_EOL;
      
      /* Pre-process data */
      $row = [];
      foreach ($cols_attrs as $i_col => $arr) {
        $attribute = $arr[0];
        $column    = $arr[1];

        // echo $this->getColumnName($i_col, true);
        $raw_val = $raw_row[$this->getColumnName($i_col, true)];
        
        switch (true) {
          case $this->getColumnTreatmentType($i_col) == "BinaryBagOfWords":

            $dict = $this->getColumnTreatmentArg($i_col, 0);
            var_dump($dict);
            foreach ($dict as $word) {
              $val = in_array($word, $this->text2words($raw_val));
              $row[] = $val;
            }
            break;
           
          default:
            // Default value (the original, raw one)
            $val = $raw_val;

            if ($raw_val !== NULL) {
              if ($attribute instanceof DiscreteAttribute) {
                $val = array_search($raw_val, $attribute->getDomain());
                if ($val === false) {
                  if ($this->getColumnTreatmentType($i_col) == "ForceCategorical") {
                    $attribute->pushDomainVal($raw_val);
                    $val = array_search($raw_val, $attribute->getDomain());
                  }
                  else {
                    die("Something's off. Couldn't find element \"" . get_var_dump($raw_val) . "\" in domain of attribute {$attribute->getName()}. " . serialize($attribute));
                  }
                }
              }
              else if (in_array($this->getColumnMySQLType($i_col), ["date", "datetime"])) {
                $type_to_format = [
                  "date"     => "Y-m-d"
                , "datetime" => "Y-m-d H:i:s"
                ];
                $date = DateTime::createFromFormat($type_to_format[$this->getColumnMySQLType($i_col)], $raw_val);
                assert($date !== false, "Incorrect date string \"$raw_val\"");

                switch ($this->getColumnTreatmentType($i_col)) {
                  case NULL:
                    // By default, use DaysSince
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
                    die("Unknown treatment for {$this->getColumnMySQLType($i_col)} column '{$this->getColumnTreatmentType($column)}'");
                    break;
                };
              }
            }
            $row[] = $val;
            break;
        }
      } // Foreach value in row
      $data[] = $row;
    } // Foreach row
    // echo count($data) . " rows retrieved" . PHP_EOL;
    // echo get_var_dump($data);
    
    $final_attributes = [];

    foreach ($attributes as $attribute) {
      if ($attribute instanceof _Attribute) {
        $final_attributes[] = $attribute;
      } else if (is_array($attribute)) {
        foreach ($attribute as $attr) {
          $final_attributes[] = $attr;
        }
      } else {
        die("Unknown attribute encountered. Must debug code.");
      }
    }

    $dataframe = new Instances($final_attributes, $data);
    
    $dataframe->save_ARFF("tmp");

    return $dataframe;
  }

  // Train a predictive model onto the data
  function learn_model() {
    echo "DBFit->learn_model()" . PHP_EOL;

    assert($this->model_type == "RuleBased", "Only \"RuleBased\" is available as a predictive model");

    $this->model = new RuleBasedModel();
    
    assert($this->learning_method == "RIPPER", "Only \"RIPPER\" is available as a learning method");

    $learner = new PRip();
    
    $dataframe = $this->read_data();
    $this->model->fit($dataframe, $learner);
  }

  // Load an existing predictive model. Defaulted to the model trained the most recently
  function load_model($path = NULL) {
    echo "DBFit->load_model($path)" . PHP_EOL;
    // TODO take care of the fact that we might have different model types.
    /* Default path to that of the latest model */
    if ($path == NULL) {
      // TODO check if this works as expected
      $models = filesin(MODELS_FOLDER);
      if (count($models) == 0) {
        // TODO take a less drastic measure
        die("Error! No model to load.");
      }
      sort($models, true);
      $path = $models[0];
      echo "$path";
    }
    die("TODO load_model");
    //$this->model = DiscriminativeModel::loadModel($path);
    // $this->model = new DiscriminativeModel::loadModel($path);
  }

  // Learn a model, and save to file
  function update_model() {
    echo "DBFit->update_model()" . PHP_EOL;
    $this->learn_model();
    // TODO evaluate model?
    $this->model->save(join_paths(MODELS_FOLDER, date("Y-m-d_H:i:s")));
  }

  // Use the model for predicting
  function predict(Instances $input_data) {
    echo "DBFit->predict(" . $input_data->toString(true) . ")" . PHP_EOL;
    assert($this->model instanceof _DiscriminativeModel, "Error! Model is not initialized");
    return $this->model->predict($input_data);
  }

  // Test the model
  function test(Instances $test_data) {
    echo "DBFit->test(" . $test_data->toString(true) . ")" . PHP_EOL;

    $ground_truths = $test_data->getClassValues();
    $test_data->dropOutputAttr();
    $predictions = $this->predict($test_data);
    
    echo "\$ground_truths : " . get_var_dump($ground_truths) . PHP_EOL;
    echo "\$predictions : " . get_var_dump($predictions) . PHP_EOL;

    // TODO compute confusion matrix, etc. using $predictions $ground_truths
  }

  /* DEBUG-ONLY - Test capabilities */
  function test_all_capabilities() {
    echo "DBFit->test_all_capabilities()" . PHP_EOL;
    
    $dataframe = $this->read_data();

    /* For testing, let's use the original data and cut the output column */
    $attrs = $dataframe->getAttributes();
    //var_dump($attrs);
    // echo "TESTING DiscreteAntecedent & SPLIT DATA" . PHP_EOL;
    // $ant = new DiscreteAntecedent($attrs[2]);
    // $splitData = $ant->splitData($dataframe, 0.5, 1);
    // foreach ($splitData as $k => $d) {
      // echo "[$k] => " . PHP_EOL;
      // echo $d->toString();
    // }
    // echo $ant->toString();
    // echo "END TESTING DiscreteAntecedent & SPLIT DATA" . PHP_EOL;

    // echo "TESTING ContinuousAntecedent & SPLIT DATA" . PHP_EOL;
    // $ant = new ContinuousAntecedent($attrs[3]);
    // $splitData = $ant->splitData($dataframe, 0.5, 1);
    // foreach ($splitData as $k => $d) {
      // echo "[$k] => " . PHP_EOL;
      // echo $d->toString();
    // }
    // echo $ant->toString();
    // echo "END TESTING ContinuousAntecedent & SPLIT DATA" . PHP_EOL;
    
    echo PHP_EOL;
    echo PHP_EOL;
    
    $this->update_model();
    $input_dataframe = clone $dataframe;
    $this->test($input_dataframe);
    // $this->load_model();
    // $this->predict($input_dataframe);
    
  }



  static function isEnumType(string $mysql_type) {
    return preg_match("/enum.*/i", $mysql_type);
  }

  static function isTextType(string $mysql_type) {
    return preg_match("/varchar.*/i", $mysql_type);
  }


  function getSQLSelectQuery($cols) {
    listify($cols);
    $sql = "SELECT " . mysql_list($cols, "noop") . " FROM " . mysql_list($this->table_names);
    if ($this->limit !== NULL) {
      $sql .= " LIMIT {$this->limit}";
    }

    if ($this->join_criterion != NULL && count($this->join_criterion)) {
      $sql .= " WHERE 1";
      foreach ($this->join_criterion as $criterion) {
        $sql .= " AND $criterion";
        // TODO if is equality of two columns, drop one of the columns/attributes
        // if(preg_match("/.*[\s\w]=[\s\w].*/i", $criterion)) {}
      }
    }
    return $sql;
  }


  function text2words($text) {
    $text = strtolower($text);
    
    # to keep letters only (remove punctuation and such)
    $text = preg_replace('/[^a-z]+/i', '_', $text);
    
    # tokenize
    $words = array_filter(explode("_", $text));

    # remove stopwords
    $words = array_diff($words, $this->stop_words);

    # lemmatize
    // TODO lemmatize($text)

    # stem
    $words = array_map(["PorterStemmer", "Stem"], $words);
    
    return $words;
  }

  public function getModel() : _DiscriminativeModel
  {
    return $this->model;
  }

  public function setModel(_DiscriminativeModel $model) : self
  {
    $this->model = $model;
    return $this;
  }

  public function getDb() : object
  {
    return $this->db;
  }

  public function setDb(object $db) : self
  {
    $this->db = $db;
    return $this;
  }

  public function getTableNames() : array
  {
    // TODO introduce all kinds of checks
    return $this->table_names;
  }

  /**
   * @param mixed $table_names
   *
   * @return self
   */
  public function setTableNames($table_names)
  {
      listify($table_names);
      $this->table_names = $table_names;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getJoinCriterion()
  {
      return $this->join_criterion;
  }

  /**
   * @param mixed $join_criterion
   *
   * @return self
   */
  public function setJoinCriterion($join_criterion)
  {
      listify($join_criterion);
      $this->join_criterion = $join_criterion;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getColumns()
  {
      return $this->columns;
  }

  function getColumnName(int $i_col, bool $force_no_table_name = false) {
    // var_dump($i_col);
    // var_dump($this->columns);
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

  /**
   * @param mixed $columns
   *
   * @return self
   */
  public function setColumns(array $columns)
  {

      $this->columns = [];
      foreach ($columns as $i_col => &$col) {
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
          die("ERROR! Malformed column: " . get_var_dump($col));
        }

        if ($new_col["attr_name"] == NULL) {
          $new_col["attr_name"] = $new_col["name"];
        }

        $this->columns[] = $new_col;
      }

      return $this;
  }

  /**
   * @return mixed
   */
  public function getOutputColumnName()
  {
      return $this->output_column_name;
  }

  /**
   * @param mixed $output_column_name
   *
   * @return self
   */
  public function setOutputColumnName(string $output_column_name)
  {
      $this->output_column_name = $output_column_name;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getLimit()
  {
      return $this->limit;
  }

  /**
   * @param mixed $limit
   *
   * @return self
   */
  public function setLimit(int $limit)
  {
      $this->limit = $limit;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getModelType()
  {
      return $this->model_type;
  }

  /**
   * @param mixed $model_type
   *
   * @return self
   */
  public function setModelType(string $model_type)
  {
      $this->model_type = $model_type;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getLearningMethod()
  {
      return $this->learning_method;
  }

  /**
   * @param mixed $learning_method
   *
   * @return self
   */
  public function setLearningMethod(string $learning_method)
  {
      $this->learning_method = $learning_method;

      return $this;
  }
}

?>
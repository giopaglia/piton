<?php


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

  private $model_type;
  private $learning_method;

  /* MAP: Mysql column type -> attr type */
  static $col2attr_type = [
    "date" => [
      "" => "date"
    , "DaysSince" => "int"
    , "MonthsSince" => "int"
    , "YearsSince" => "int"
    ]
  , "int"     => ["" => "int"]
  , "float"   => ["" => "float"]
  , "real"    => ["" => "float"]
  , "double"  => ["" => "double"]
  , "boolean" => ["" => "bool"]
  , "enum" => ["" => "enum"]
  ];

  function __construct($db) {
    echo "DBFit(DB)" . PHP_EOL;
    $this->db = $db;
  }

  private function read_data() {
    echo "DBFit->read_data()" . PHP_EOL;

    /* Checks */
    $output_col_in_columns = false;
    foreach ($this->columns as $col) {
    	if (self::getColumnName($col) == $this->output_column_name)
    		$output_col_in_columns = true;
    }
	  assert($output_col_in_columns, "\$output_column_name must be in \$columns");
    
    // Move output column such that it's the FIRST column
    if (($k = array_search($this->output_column_name, $this->columns)) !== false) {
      unset($this->columns[$k]);
      array_unshift($this->columns, $this->output_column_name);
    };

    /* Obtain column types & derive attributes */
    $attributes = [];
    $sql = "SHOW COLUMNS FROM " . mysql_list($this->table_names) . " WHERE FIELD IN " . mysql_set(array_map(["self", "getColumnName"], $this->columns));
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $raw_mysql_columns = [];
    foreach ($stmt->get_result() as $row) {
      // echo get_var_dump($row) . PHP_EOL;
      $raw_mysql_columns[] = $row;
    }
    //var_dump($raw_mysql_columns);
    $mysql_columns = [];
    foreach ($this->columns as $column) {
      $mysql_column = NULL;
      foreach ($raw_mysql_columns as $col) {
        if ($col["Field"] == self::getColumnName($column)) {
          $mysql_column = $col;
          $mysql_columns[] = $mysql_column;
          break;
        }
      }
      assert($mysql_column !== NULL, "Couldn't retrieve information about column \"" . self::getColumnName($column) . "\"");
      // TODO figure out, where does "boolean" go? Should end up creating a discrete attr w/ 2 classes.
      $attr_name = self::getColumnAttrName($column);

      switch(true) {
        case in_array($mysql_column["Type"], ["int", "float", "double", "real", "date"]):
          $attribute = new ContinuousAttribute($attr_name, self::getColumnAttrType($mysql_column["Type"], $column));
          break;
        case self::isEnumType($mysql_column["Type"]):
          $domain_arr_str = (preg_replace("/enum\((.*)\)/i", "[$1]", $mysql_column["Type"]));
          eval("\$domain_arr = " . $domain_arr_str . ";");
          $attribute = new DiscreteAttribute($attr_name, "enum", $domain_arr);
          break;
        default:
          die("Unknown field type: " . $mysql_column["Type"]);
      }
      $attributes[] = $attribute;
    }

    /* Obtain data */
    $data = [];
    $cols_attrs = zip($attributes, $mysql_columns, $this->columns);
    //var_dump($cols_attrs);
    $sql = "SELECT " . mysql_list(array_map(["self", "getColumnName"], $this->columns)) . " FROM " . mysql_list($this->table_names);
    // TODO: Use $join_criterion to introduce WHERE and (INNER) JOIN.
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    foreach ($stmt->get_result() as $raw_row) {
      // echo get_var_dump($raw_row) . PHP_EOL;
      
      /* Pre-process data */
      $row = [];
      foreach ($cols_attrs as $arr) {
        $attribute = $arr[0];
        $ms_column = $arr[1];
        $column    = $arr[2];

        $raw_val = $raw_row[self::getColumnName($column)];
        
        // Default value (the original, raw one)
        $val = $raw_val;

        if ($raw_val !== NULL) {
          if ($ms_column["Type"] == "date") {
            // Get timestamp
            $date = DateTime::createFromFormat("Y-m-d", $raw_val);
            assert($date !== false, "Incorrect date string");

            switch (self::getColumnTreatment($column)) {
              case NULL:
                $val = $date->getTimestamp();
                break;
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
                die("TODO {$this->getColumnTreatment($column)}");
                break;
            };
          }

          // For convenience, use domain indices instead of bare values
          if ($attribute instanceof DiscreteAttribute) {
            $val = array_search($raw_val, $attribute->getDomain());
          }

          // TODO use the column "treatment" to derive $val from $raw_val
        }

        switch (self::getColumnTreatment($column)) {
          case NULL: break;
          default:
            # code...
            break;
        };

        $row[] = $val;
      }
      $data[] = $row;
    
    }
    // echo count($data) . " rows retrieved" . PHP_EOL;
    // echo get_var_dump($data);
    
    $dataframe = new Instances($attributes, $data);
    
    echo $dataframe->save_ARFF("tmp");

    return $dataframe;
  }

  // Train a predictive model onto the data
  function learn_model() {
    echo "DBFit->learn_model()" . PHP_EOL;

    assert($this->model_type == "RuleBased", "Only \"RuleBased\" is available as a predictive model");

    assert($this->learning_method == "RIPPER", "Only \"RIPPER\" is available as a learning method");

    $dataframe = $this->read_data();


    $learner = new PRip();
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
      echo "ASDASDASD $path";
    }
    $this->model->load($path);
  }

  // Learn a model, and save to file
  function update_model() {
    echo "DBFit->update_model()" . PHP_EOL;
    $this->learn_model();
    // TODO evaluate model?
    $this->model->save(join_paths(MODELS_FOLDER, date("Y-m-d_H:i:s")));
  }

  // Use the model for predicting
  function predict($input_data) {
    echo "DBFit->predict(".serialize($input_data).")" . PHP_EOL;
    assert($this->model instanceof DiscriminativeModel, "Error! Model is not initialized");
    return $this->model->predict($input_data);
  }

  /* DEBUG-ONLY - Test capabilities */
  function test_all_capabilities() {
    echo "DBFit->test_all_capabilities()" . PHP_EOL;
    
    $dataframe = $this->read_data();

    /* For testing, let's use the original data and cut the output column */
    $input_dataframe = clone $dataframe;
    $input_dataframe->dropOutputAttr();
    $attrs = $dataframe->getAttrs();
    //var_dump($attrs);
    echo "TESTING DiscreteAntecedent & SPLIT DATA" . PHP_EOL;
    $ant = new DiscreteAntecedent($attrs[2]);
    $splitData = $ant->splitData($dataframe, 0.5, 1);
    foreach ($splitData as $k => $d) {
      echo "[$k] => " . PHP_EOL;
      echo $d->toString();
    }
    echo $ant->toString();
    echo "END TESTING DiscreteAntecedent & SPLIT DATA" . PHP_EOL;

    echo "TESTING ContinuousAntecedent & SPLIT DATA" . PHP_EOL;
    $ant = new ContinuousAntecedent($attrs[3]);
    $splitData = $ant->splitData($dataframe, 0.5, 1);
    foreach ($splitData as $k => $d) {
      echo "[$k] => " . PHP_EOL;
      echo $d->toString();
    }
    echo $ant->toString();
    echo "END TESTING ContinuousAntecedent & SPLIT DATA" . PHP_EOL;
    
    $this->update_model();
    $this->predict($input_dataframe);
    $this->load_model();
    // $this->predict($input_dataframe);
    
  }


  static function getColumnName($col) {
    return !is_array($col) ? $col : $col[0];
  }
  static function getColumnTreatment($col) {
    return !is_array($col) ? NULL : $col[1];
  }
  static function getColumnAttrName($col) {
    return !is_array($col) ? self::getColumnName($col) : $col[2];
  }

  static function getColumnAttrType($mysql_type, $col) {
    if (self::isEnumType($mysql_type)) {
      return "enum";
    }
    return self::$col2attr_type[$mysql_type][self::getColumnTreatment($col)];
  }

  static function isEnumType($mysql_type) {
    return preg_match("/enum.*/i", $mysql_type);
  }


  /**
   * @return mixed
   */
  public function getModel()
  {
      return $this->model;
  }

  /**
   * @return mixed
   */
  public function getDb()
  {
      return $this->db;
  }

  /**
   * @return mixed
   */
  public function getTableNames()
  {
      return $this->table_names;
  }

  /**
   * @return mixed
   */
  public function getJoinCriterion()
  {
      return $this->join_criterion;
  }

  /**
   * @return mixed
   */
  public function getColumns()
  {
      return $this->columns;
  }

  /**
   * @return mixed
   */
  public function getOutputColumnName()
  {
      return $this->output_column_name;
  }

  /**
   * @return mixed
   */
  public function getModelType()
  {
      return $this->model_type;
  }

  /**
   * @return mixed
   */
  public function getLearningMethod()
  {
      return $this->learning_method;
  }

    /**
     * @param mixed $model
     *
     * @return self
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param mixed $db
     *
     * @return self
     */
    public function setDb($db)
    {
        $this->db = $db;

        return $this;
    }

    /**
     * @param mixed $table_names
     *
     * @return self
     */
    public function setTableNames($table_names)
    {
        $this->table_names = $table_names;

        return $this;
    }

    /**
     * @param mixed $join_criterion
     *
     * @return self
     */
    public function setJoinCriterion($join_criterion)
    {
        $this->join_criterion = $join_criterion;

        return $this;
    }

    /**
     * @param mixed $columns
     *
     * @return self
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param mixed $output_column_name
     *
     * @return self
     */
    public function setOutputColumnName($output_column_name)
    {
        $this->output_column_name = $output_column_name;

        return $this;
    }

    /**
     * @param mixed $model_type
     *
     * @return self
     */
    public function setModelType($model_type)
    {
        $this->model_type = $model_type;

        return $this;
    }

    /**
     * @param mixed $learning_method
     *
     * @return self
     */
    public function setLearningMethod($learning_method)
    {
        $this->learning_method = $learning_method;

        return $this;
    }
}

?>
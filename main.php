<?php

include "lib.php";
include "local-lib.php";

include "DiscriminativeModel/RuleBasedModel.php";
include "DiscriminativeModel/PRip.php";

/*
 * This class can be used to learn intelligent models from a MySQL database.
 *
 * Parameters:
 * - $db                       database access (Object-Oriented MySQL style)
 * - $table_names              names of the concerning database tables (array of strings)
 * - $column_names             column names, and an eventual "treatment" for that column (used when processing the data, e.g time in years since the date) (array of (strings/[string, string]))
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
  private $column_names;
  private $output_column_name;

  private $model_type;
  private $learning_method;

  function __construct($db,
             $table_names,
             $column_names,
             $join_criterion,
             $output_column_name,
             $model_type,
             $learning_method) {

    echo "DBFit(" . get_class($db) . ", " . serialize($table_names) . ", " . serialize($column_names) . ", " . serialize($join_criterion) . ", $output_column_name, $model_type, $learning_method)" . PHP_EOL;

    $this->model = new RuleBasedModel();

    $this->db = $db;
    $this->table_names = $table_names;
    $this->column_names = $column_names;
    $this->join_criterion = $join_criterion;
    $this->output_column_name = $output_column_name;
    
    $this->model_type = $model_type;
    $this->learning_method = $learning_method;

    assert($this->model_type == "RuleBased", "Only \"RuleBased\" is available as a predictive model");
  }

  // Reads, cleans and returns the data from the database
  private function read_data() {
    echo "DBFit->read_data()" . PHP_EOL;

    /* Checks */
    assert(in_array($this->output_column_name,$this->column_names), "\$output_column_name must be in \$column_names");
    
    // Move output column such that it's the last column
    if (($k = array_search($this->output_column_name, $this->column_names)) !== false) {
      unset($this->column_names[$k]);
      array_unshift($this->column_names, $this->output_column_name);
    };

    /* Obtain column types & derive attributes */
    $attributes = [];
    $sql = "SHOW COLUMNS FROM " . mysql_list($this->table_names) . " WHERE FIELD IN " . mysql_set($this->column_names);
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $columns = [];
    foreach ($stmt->get_result() as $row) {
      // echo get_var_dump($row) . PHP_EOL;
      $columns[] = $row;
    }
    //var_dump($columns);

    foreach ($columns as $column) {
      // echo $column["Type"] . PHP_EOL;
      // TODO: I'm assuming there are no missing values (i.e $column["Null"] == "NO")
      // TODO figure out, where does "boolean" go?
      switch(true) {
        case in_array($column["Type"], ["int", "float", "double", "real", "date"]):
          $attribute = new ContinuousAttribute($column["Field"], $column["Type"]);
          break;
        case preg_match("/enum.*/i", $column["Type"]):
          $domain_arr_str = (preg_replace("/enum\((.*)\)/i", "[$1]", $column["Type"]));
          eval("\$domain_arr = " . $domain_arr_str . ";");
          $attribute = new DiscreteAttribute($column["Field"], $column["Type"], $domain_arr);
          break;
        default:
          die("Unknown field type: " . $column["Type"]);
      }
      $attributes[] = $attribute;
    }

    /* Obtain data */
    $data = [];
    $sql = "SELECT " . mysql_list($this->column_names) . " FROM " . mysql_list($this->table_names);
    // TODO: Use $join_criterion to introduce WHERE and (INNER) JOIN.
    echo "SQL: $sql" . PHP_EOL;
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    foreach ($stmt->get_result() as $raw_row) {
      // echo get_var_dump($raw_row) . PHP_EOL;
      
      /* Pre-process data */
      $row = [];
      foreach ($attributes as $attribute) {
        $raw_val = $raw_row[$attribute->getName()];
        
        // Default value (the original, raw one)
        $val = $raw_val;

        if ($raw_val !== NULL) {
          if ($attribute->getType() == "date") {
            // Get timestamp
            $date = DateTime::createFromFormat("Y-m-d", $raw_val);
            assert($date !== false, "Incorrect date string");
            $val = $date->getTimestamp();
          }

          // For convenience, use domain indices instead of bare values
          if ($attribute instanceof DiscreteAttribute) {
            $val = array_search($raw_val, $attribute->getDomain());
          }

          // TODO use the column "treatment" to derive $val from $raw_val
        }

        $row[] = $val;
      }
      $data[] = $row;
    
    }
    // echo count($data) . " rows retrieved" . PHP_EOL;
    // echo get_var_dump($data);
    
    $dataframe = new Instances($attributes, $data);

    return $dataframe;
  }

  // Train a predictive model onto the data
  function learn_model() {
    echo "DBFit->learn_model()" . PHP_EOL;

    $dataframe = $this->read_data();

    assert($this->learning_method == "RIPPER", "Only \"RIPPER\" is available as a learning method");

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

    echo "TESTING ANTECEDENTSDiscreteAntecedent & SPLIT DATA" . PHP_EOL;
    $ant = new DiscreteAntecedent($attrs[1]);
    $splitData = $ant->splitData($dataframe, 0.5, 1);
    foreach ($splitData as $k => $d) {
      echo "[$k] => " . PHP_EOL;
      echo $d->toString();
    }
    echo $ant->toString();
    echo "END TESTING DiscreteAntecedent & SPLIT DATA" . PHP_EOL;

    echo "TESTING ContinuousAntecedent & SPLIT DATA" . PHP_EOL;
    $ant = new ContinuousAntecedent($attrs[2]);
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

}

 /****************************************************
 *                                                   *
 *                 Here I test stuff                 *
 *                                                   *
 ****************************************************/

$db = getDBConnection();

$model_type = "RuleBased";
$learning_method = "RIPPER";


$table_names = ["patients"];
$column_names = ["ID", "Gender", "BirthDate", "Sillyness"];
$join_criterion = NULL;
$output_column_name = "Sillyness";

$db_fit = new DBFit($db,
    $table_names,
    $column_names,
    $join_criterion,
    $output_column_name,
    $model_type,
    $learning_method);

$db_fit->test_all_capabilities();

exit();


$table_names = ["patients", "reports"];
$column_names = ["patients.Gender", "patients.ID", "patients.BirthDate", "patients.Sillyness",
         "reports.Date", "reports.PatientState", "reports.PatientHeartbeatMeasure", "reports.PatientID"];
$join_criterion = ["patients.ID = reports.PatientID"];
$output_column_name = "patients.Sillyness";

$db_fit = new DBFit($db,
    $table_names,
    $column_names,
    $join_criterion,
    $output_column_name,
    $model_type,
    $learning_method);
$db_fit->test_all_capabilities();

echo "All good" . PHP_EOL;


// TODO make sql querying secure with addslashes or whatever
// Useful sources: 
// - https://stackoverflow.com/questions/21088937/is-this-mysqli-safe
// - https://stackoverflow.com/questions/28606581/which-is-a-right-safe-mysqli-query
// - https://stackoverflow.com/questions/34566530/prevent-sql-injection-in-mysqli
// - https://stackoverflow.com/questions/20179565/php-secure-mysqli-query
// - https://stackoverflow.com/questions/15062290/how-to-use-mysqli-securely
// - https://www.php.net/manual/en/mysqli-stmt.bind-result.php
// - https://stackoverflow.com/questions/330268/i-have-an-array-of-integers-how-do-i-use-each-one-in-a-mysql-query-in-php
// 
?>
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

  /*
    The database tables where the input columns are (array of table-terms, one for each table)
    
    *
    
    For each table, the name must be specified. The name alone is sufficient for
    the first specified table, so the first term can be the name in the form of a string (e.g "patient"). For the remaining tables, join criteria can be specified, by means of 'joinClauses' and 'joinType'.
    If one wants to specify these parameters, then the table-term should be an array [tableName, joinClauses=[], joinType="INNER JOIN"].
    joinClauses is a list of 'MySQL constraint strings' such as "patent.ID = report.patientID", used in the JOIN operation. If a single constraint is desired, then joinClauses can also simply be the string represeting the constraint (as compared to the array containing the single constraint).
    The join type, defaulted to "INNER JOIN", is the MySQL type of join.
  */
  private $inputTables;

  /*
    Input columns. (array of column-terms, one for each column)

    *
    
    For each column, the name must be specified, and it makes up sufficient information. As such, a term can simply be the name of the column (e.g "Age").
    When dealing with more than one MySQL table, it is mandatory that each column name references the table it belongs using the dot notation, as in "patient.Age".
    Additional parameters can be supplied for managing the column pre-processing.
    The generic form for a column-term is [columnName, treatment=NULL, attrName=columnName].
    - A "treatment" for a column determines how to derive an attribute from the
       column data. For example, "YearsSince" translates each value of
       a date/datetime column into an attribute value representing the number of
       years since the date. "DaysSince", "MonthsSince" are also available.
      "DaysSince" is the default treatment for dates/datetimes
      "ForceCategorical" forces the corresponding attribute to be nominal. If the column is an enum fields, the enum domain will be inherited, otherwise a domain will be built using the unique values found in the column.
      "ForceCategoricalBinary" takes one step further and translates the nominal attribute to become a set of k binary attributes, with k the original number of classes.
      (TODO generalize: "ForceBinary" generates k binary attributes from a generic nominal attribute of domain size k.)
      For text fields, "BinaryBagOfWords" can be used to generate k binary attributes, each representing the presence of one of the most frequent words.
      When a treatment is desired, the column-term must be an array
       [columnName, treatment=NULL] (e.g ["BirthDate", "ForceCategorical"])
      Treatments may require/allow arguments, and these can be supplied through
       an array instead of a simple string. For example, "BinaryBagOfWords"
       requires a parameter k, representing the size of the dictionary.
       As an example, the following term requires BinaryBagOfWords with k=10:
       ["Description", ["BinaryBagOfWords", 10]].
      The treatment for input column is defaulted to NULL, which implies no such pre-processing step. Note that the module complains whenever it encounter
        text fields with no treatment specified. When dealing with many text fields, consider setting the default option "textTreatment" via ->setDefaultOption(). For example, ->setDefaultOption("textTreatment", ["BinaryBagOfWords", 10]).
    - The name of the attribute derived from the column can also be specified:
       for instance, ["BirthDate", "YearsSince", "Age"] creates an "Age" attribute
       by processing a "BirthDate" sql column.
  */
  private $columns;
  
  /* Columns that are to be treated as output.
      (array of outputColumn-terms, one for each column)

    *
  
    This module supports hierarchical models. This means that a unique DBFit object can be used to train different models at predicting different output columns that are inter-related, with different sets of data.
    In the simplest case, the user specifies a unique output column, from which M attributes are generated. Then, M models are generated, each predicting an attribute value, which is then used for deriving a value for the output column.
    One can then take this a step further and, for each of the M models, independently train K models, where K is the number of output classes of the attribute, using data that is only relevant to that given output class and model. Generally, this hierarchical training and prediction structur takes the form of a tree with depth O (number of "nested" outputColumns).
    Having said this, the outputColumns array specifies one column per each depth of the recursion tree.

    outputColumn-terms are very similar to column-terms (see documentation for $this->columns a few lines above), with a few major differences:
    - The default treatment is "ForceCategorical": note, in fact, that output columns must generate categorical attributes (this module only supports classification and not regression). Also consider using "ForceCategoricalBinary", which breaks a nominal class attribute into k disjoint binary attributes.
    - Each output column can be derived from join operations (thus it can also belong to inputTables that are not in $this->inputTables).
    Additional join criteria can be specified using table-terms format (see documentation for $this->inputTables a few lines above).
    The format for an outputColumn is thus [columnName, tables=[], treatment="ForceCategorical", TODO attrName=columnName], where tables is an array of table-terms.

    As such, the following is a valid outputColumns array:
    [
      // first outputColumn
      ["report.Status",
        [
          ["RaccomandazioniTerapeuticheUnitarie", ["RaccomandazioniTerapeuticheUnitarie.ID_RACCOMANDAZIONE_TERAPEUTICA = RaccomandazioniTerapeutiche.ID"]]
        ],
        "ForceCategoricalBinary"
      ],
      // second outputColumn
      ["PrincipiAttivi.NOME",
        [
          ["ElementiTerapici", ["report.ID = Recommandations.reportID"]],
          ["PrincipiAttivi", "ElementiTerapici.PrAttID = PrincipiAttivi.ID"]
        ]
      ]
    ]

  */
  private $outputColumns;

  /*
    SQL WHERE clauses for the concerning inputTables (array of strings, or single string)
    For example:
    - "patient.Age > 30"
    - ["patient.Age > 30", "patient.Name IS NOT NULL"]
  */
  private $whereClauses;

  /* SQL LIMIT term in the SELECT query (integer) */
  // TODO remove? Just for debug? because note that rn we use the same value at every recursion level. Maybe we want to specify a different value for every outputLevel?
  private $limit;

  /* An identifier column, used during sql-based prediction
    A value for the identifier column identifies a set of data rows that are to be compressed into a single data instance before use.
  */
  private $identifierColumnName;

  /* Optimizer in use for training the models.
    This can be set via ->setLearningMethod(string) (only "PRip" available atm),
    or ->setLearner($learner)
  */
  private $learner;

  /* Array storing all the hierarchy of discriminative models trained (or loaded) */
  private $models;

  /*
    Training mode.
    Available values:
    - "FullTraining" (trains and test onto the same 100% of data)
    - [train_w, test_w] (train/test split according to these two weights)
  */
  private $trainingMode;

  /* Default options, to be set via ->setDefaultOption() */
  private $defaultOptions = [
    /* Default training mode in use */
    "trainingMode"  => [80, 20],
    /* Default text treatment. NULL treatment will raise error as soon as a text column is encountered. */
    "textTreatment" => NULL,
    /* Default language for text pre-processing */
    "textLanguage" => "en"
  ];

  /* Utility Map: Mysql column type -> attr type */
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
    $this->inputTables = [];
    $this->columns = [];
    $this->setOutputColumns([]);
    $this->setIdentifierColumnName(NULL);
    $this->whereClauses = NULL;
    $this->limit = NULL;

    $this->models = [];
    $this->learner = NULL;
    $this->trainingMode = NULL;
  }

  /** Read data & pre-process it */
  private function readData($idVal = NULL, array $recursionPath = []) : array {

    echo "DBFit->readData(" . toString($idVal) . ", " . toString($recursionPath) . ")" . PHP_EOL;

    /* Checks */
    if (!count($this->columns)) {
      die_error("Must specify the concerning input columns, through ->setColumns() or ->addColumn().");
    }
    if (!count($this->outputColumns)) {
      die_error("Must specify at least an output column, through ->setOutputColumns() or ->addOutputColumn().");
    }
    if (!count($this->inputTables)) {
      die_error("Must specify the concerning input tables, through ->setInputTables() or ->addInputTable().");
    }
    
    $recursionLevel = count($recursionPath);
    $outputColumnName = $this->getOutputColumnNames()[$recursionLevel];

    // var_dump($this->outputColumns);

    /* Refresh all attributes except for those at the previous levels, in order to profit from attributes that are more specific.
     */
    /*
      TODO figure out what's the best place where to assign column attributes. Question: Should I recompute the attributes when I recurse? I think so, because I might profit from attributes that are more specific. But I need to fix the outputAttributes at the previous levels. Thus, I recompute the remaining outputLevels, as well as the input attributes. On the other end, why do I need to refresh the lower-level outputColumns? At prediction time, I don't have values for those!
     */
    // for ($i_col = $recursionLevel; $i_col < count($this->outputColumns); $i_col++) {
    //   // var_dump($this->outputColumns[$i_col]);
    //   $this->assignColumnAttributes($this->outputColumns[$i_col], NULL, $recursionPath);
    //   // var_dump($this->outputColumns[$i_col]);
    // }
    // var_dump($this->outputColumns[$recursionLevel]);
    if ($idVal === NULL) {
      $this->assignColumnAttributes($this->outputColumns[$recursionLevel], $recursionPath);
      foreach ($this->getColumns(false) as &$column) {
        $this->assignColumnAttributes($column, $recursionPath);
      }
    }
    
    // var_dump($this->outputColumns[$recursionLevel]);
    // var_dump($this->outputColumns);
    // var_dump($this->columns);

    /* Select redundant columns by examining the SQL constaints,
        to be ignored when creating the dataframe */
    $columnsToIgnore = [];
    $constraints = $this->getSQLConstraints($idVal, $recursionPath);
    foreach ($constraints as $constraint) {
      /* If any WHERE/JOIN-ON constraint forces the equality between two columns,
        drop one of the resulting attributes. */
      if(preg_match("/\s*([a-z\d_\.]+)\s*=\s*([a-z\d_\.]+)\s*/i", $constraint, $matches)) {
        $fst = $matches[1];
        $snd = $matches[2];

        if (!in_array($fst, [$this->identifierColumnName, $outputColumnName])
          && !in_array($fst, $columnsToIgnore)) {
          $columnsToIgnore[] = $fst;
        } else if (!in_array($snd, [$this->identifierColumnName, $outputColumnName])
          && !in_array($snd, $columnsToIgnore)) {
          $columnsToIgnore[] = $snd;
        } else {
          die_error("Unexpected case encountered when removing redundant columns."); // What to do here?
        }
      }
      // Drop attribute when forcing equality to a constant (because then the attributes is not informative)
      if(preg_match("/\s*([a-z\d_\.]+)\s*=\s*('[a-z\d_\.]*')\s*/i", $constraint, $matches)) {
        $col = $matches[1];
        if (!in_array($col, [$this->identifierColumnName, $outputColumnName])
          && !in_array($col, $columnsToIgnore)) {
          $columnsToIgnore[] = $col;
        } else {
          die_error("Unexpected case encountered when removing redundant columns.");
        }
      }
      if(preg_match("/\s*('[a-z\d_\.]*')\s*=\s*([a-z\d_\.]+)\s*/i", $constraint, $matches)) {
        $col = $matches[2];
        if (!in_array($col, [$this->identifierColumnName, $outputColumnName])
          && !in_array($col, $columnsToIgnore)) {
          $columnsToIgnore[] = $col;
        } else {
          die_error("Unexpected case encountered when removing redundant columns.");
        }
      }
    }

    // echo "columnsToIgnore  "; var_dump($columnsToIgnore);

    /* Derive the input columns & output columns needed for the dataframes at this recursion level */
    // TODO figure out whether I should be using the previous outputColumns values,
    //  but I think they would hold the same value, and $recursionPath contains everything needed. I think they should be discarted. If so, then no need to include them in $columns and $colsNeeded.
    //  TODO the difference between columns and cols needed is just potentially the presence of the identifiercolumn. It'd be super simpler if we're sure that that column is not in $this->columns
    // $columns = array_slice($this->outputColumns, 0, $recursionLevel+1);
    // $thisOutputAttr = $columns[$recursionLevel];
    // array_splice($columns, $recursionLevel, 1);
    // array_unshift($columns, $thisOutputAttr);
    // $columns = array_merge($columns, $this->getColumns(false))
      
    // var_dump($inputColumns);

    echo "Recursion level: " . $recursionLevel . "(path: "
    . toString($recursionPath) . ")" . PHP_EOL;
    echo "Identifier value: " . toString($idVal) . PHP_EOL;

    /* Obtain output attribute */
    $outputColumn = $this->outputColumns[$recursionLevel];
    $outputAttributes = $this->getColumnAttributes($outputColumn, $recursionPath);

    $dataframes = [];
    
    /* Check that some data is found */
    if(!is_array($outputAttributes)) {
      warn("Couldn't derive output attributes for output column {$this->getColumnName($outputColumn)}!");
    }
    else {
      foreach ($outputAttributes as $i_attr => $outputAttribute) {
        if(!($outputAttribute instanceof DiscreteAttribute)) {
          die_error("All output attributes must be categorical! '"
            . $outputAttribute->getName() . "' ($i_attr-th of output column {$this->getColumnName($outputColumn)}) is not.");
        }
      }

      /* Obtain input attributes */
      $inputColumns = $this->getColumns(false);
      $inputAttributes = [];
      foreach ($inputColumns as &$column) {
        if (in_array($this->getColumnName($column), $columnsToIgnore)) {
          $attribute = NULL;
        }
        else {
          $attribute = $this->getColumnAttributes($column);
        }
        $inputAttributes[] = $attribute;
      }

      // echo "Reading " . count($inputColumns) . " inputColumns..." . PHP_EOL;

      $attributes = array_merge([$outputAttributes], $inputAttributes);
      $columns = array_merge([$outputColumn], $inputColumns);

      /* Finally obtain data */
      $res = $this->SQLSelectColumns($inputColumns, $idVal, $recursionPath, $outputColumn);
      $data = $this->readRawData($res, $attributes, $columns);

      // echo count($data) . " rows retrieved" . PHP_EOL;
      // echo get_var_dump($data);
      
      // TODO document from here
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

      // echo "this->columns: " . PHP_EOL; var_dump($this->columns);
      // echo "attributes: " . PHP_EOL; var_dump($attributes);
      // echo "final_attributes: " . PHP_EOL; var_dump($final_attributes);
      
      // var_dump($final_data);
      
      /* Unpacking: generate many dataframes, each with a single output attribute (one per each of the output attributes fore this column) */
      $numOutputAttributes = count($outputAttributes);
      // echo "Output attributes: ";
      // var_dump($outputAttributes);
      foreach ($outputAttributes as $i_attr => $outputAttribute) {
        // echo "Problem $i_attr/" . $numOutputAttributes . PHP_EOL;

        /* Build instances for this output attribute */
        $outputAttr = clone $final_attributes[$i_attr];
        $outputVals = array_column($final_data, $i_attr);
        $attrs = array_merge([$outputAttr], array_slice($final_attributes, $numOutputAttributes));
        $data = [];
        foreach ($final_data as $i => $row) {
          $data[] = array_merge([$outputVals[$i]], array_slice($row, $numOutputAttributes));
        }
        // var_dump($attrs);
        // var_dump($data);
        $dataframe = new Instances($attrs, $data);
        
        // echo $dataframe->toString(false);
        
        if (DEBUGMODE && $idVal === NULL) {
          $dataframe->save_ARFF("instances");
        }
        
        $dataframes[] = $dataframe;
      }

      echo count($dataframes) . " dataframes computed " . PHP_EOL;
    }

    return $dataframes;
  }

  function &readRawData(object &$res, array &$attributes, array &$columns) : array {

    $data = [];

    foreach ($res as $raw_row) {
      // echo get_var_dump($raw_row) . PHP_EOL;
      
      /* Pre-process data */
      $attr_vals = [];
      foreach ($columns as $i_col => &$column) {
        $attribute = $attributes[$i_col];
        
        if ($attribute === NULL) {
          // Ignore attribute/value
          $attr_val = NULL;
        }
        else {
          $attr_val = [];
          $raw_val = $raw_row[$this->getColNickname($this->getColumnName($column))];

          if ($raw_val === NULL) {
            foreach ($attribute as $attr) {
              $attr_val[] = NULL;
            }
          }
          else {
            switch (true) {
              /* ForceCategoricalBinary */
              case $this->getColumnTreatmentType($column) == "ForceCategoricalBinary":

                /* Append k values, one for each of the classes */
                $classes = $this->getColumnTreatmentArg($column, 0);
                foreach ($classes as $class) {
                  $val = intval($class == $raw_val);
                  $attr_val[] = $val;
                }
                break;
               
              /* Text column */
              case $this->getColumnTreatmentType($column) == "BinaryBagOfWords":

                /* Append k values, one for each word in the dictionary */
                $dict = $this->getColumnTreatmentArg($column, 0);
                foreach ($dict as $word) {
                  $val = intval(in_array($word, $this->text2words($raw_val)));
                  $attr_val[] = $val;
                }
                break;
               
              default: /* Single value */
                if (count($attribute) != 1) {
                  die_error("Something's off. Found multiple attributes for column "
                    . $this->getColumnName($column)
                    . " ($i_col)" . get_var_dump($attribute));
                }
                $attribute = $attribute[0];

                /* For categorical attributes, use the class index as value */
                if ($attribute instanceof DiscreteAttribute) {
                  if (is_bool($raw_val)) {
                    $raw_val = intval($raw_val);
                  }
                  $val = $attribute->getKey($raw_val);
                  if ($val === false) {
                    /* When forcing categorical, push the unfound values to the domain */
                    if (in_array($this->getColumnTreatmentType($column), ["ForceCategorical"])) {
                      $attribute->pushDomainVal($raw_val);
                      $val = $attribute->getKey($raw_val);
                    }
                    else {
                      die_error("Something's off. Couldn't find element \"" . toString($raw_val) . "\" in domain of attribute {$attribute->getName()}. ");
                    }
                  }
                }
                /* Dates & Datetime values */
                else if (in_array($this->getColumnMySQLType($column), ["date", "datetime"])) {
                  $type_to_format = [
                    "date"     => "Y-m-d"
                  , "datetime" => "Y-m-d H:i:s"
                  ];
                  $date = DateTime::createFromFormat($type_to_format[$this->getColumnMySQLType($column)], $raw_val);
                  if (!($date !== false))
                    die_error("Incorrect date string \"$raw_val\"");

                  switch ($this->getColumnTreatmentType($column)) {
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
                    die_error("Unknown treatment for {$this->getColumnMySQLType($column)} column \"" .
                      $this->getColumnTreatmentType($column) . "\"");
                      break;
                  };
                }
                $attr_val = [$val];
                break;
            }
          }
        }
        $attr_vals[] = $attr_val;
      } // foreach ($columns as $i_col => $column)

      // Check that the identifier column actually identifies single rows,
      //  and merge rows if needed.
      if ($this->identifierColumnName !== NULL) {
        $idVal = $raw_row[$this->getColNickname($this->identifierColumnName)];
        if (isset($data[$idVal])) {
          $attr_vals_orig = &$data[$idVal];

          foreach (zip($attr_vals_orig, $attr_vals) as $i_col => $z) {
            if ($z[0] !== $z[1]) {
              if ($i_col !== 0) {
                die_error("Found more than one row with same identifier value: '{$this->identifierColumnName}' = " . get_var_dump($idVal)
                  . ", but merging on column " . $this->getColumnName($columns[$i_col])
                  . " ($i_col) failed. "
                  . get_var_dump($z[0]) . get_var_dump($z[1])
                  // . get_var_dump($attr_vals_orig) . get_var_dump($attr_vals)
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
                      $attr_vals_orig[$i_col][$a] = intval($attr_vals_orig[$i_col][$a] || $z[1][$a]);
                    }
                    else {
                      die_error("Found more than one row with same identifier value: '{$this->identifierColumnName}' = " . get_var_dump($idVal)
                      . ", but I don't know how to merge values for column " . $this->getColumnName($columns[$i_col])
                      . " ($i_col) of type '{$attribute[$a]->getType()}'. "
                      . "Suggestion: specify ForceBinary/ForceCategoricalBinary treatment for this column (this will break categorical attributes into k binary attributes, easily mergeable via OR operation)."
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

  /* TODO explain */
  function getColumns($IncludeIdCol = false) {
    $cols = [];
    foreach ($this->columns as &$col) {
      $cols[] = &$col;
    }
    if ($IncludeIdCol && $this->identifierColumnName !== NULL) {
      if (!in_array($this->identifierColumnName, $this->getColumnNames(false))) {
        $cols[] = $this->readColumn($this->identifierColumnName);
      }
    }
    return $cols;
  }

  /* TODO explain */
  function getColumnNames($IncludeIdCol = false) {
    $cols = array_map([$this, "getColumnName"], $this->columns);
    if ($IncludeIdCol && $this->identifierColumnName !== NULL) {
      if (!in_array($this->identifierColumnName, $cols)) {
        $cols[] = $this->identifierColumnName;
      }
    }
    return $cols;
  }

  function getOutputColumnNames() {
    return array_map([$this, "getColumnName"], $this->outputColumns);
  }

  // function getOutputColumnAttributes() {
  //   // var_dump($this->outputColumns);
  //   return array_map([$this, "getColumnAttributes"], $this->outputColumns);
  // }

  /* Need a nickname for every column when using table.column format,
      since PHP MySQL connctions do not allow to access result fields
      using this format */
  function getColNickname($colName) {
    return str_replace(".", "_DOT_", $colName);
  }

  /* TODO explain */
  function SQLSelectColumns(array $columns, $idVal = NULL, array $recursionPath = [],
    array $outputColumn = NULL, bool $silent = !DEBUGMODE) : object {
    // if ($colNames === NULL) { ...columns
    //   $colNames = $this->getColumnNames(true);
    // }
    // listify($colNames);
    $cols_str = [];

    if ($outputColumn != NULL) {
      $name = $this->getColumnName($outputColumn);
      if ($idVal !== NULL) {
        $cols_str[] = "NULL AS " . $this->getColNickname($name);
      }
      else {
        $cols_str[] = $name . " AS " . $this->getColNickname($name);
      }
    }

    foreach ($columns as $col) {
      $name = $this->getColumnName($col);
      $cols_str[] = $name . " AS " . $this->getColNickname($name);
    }

    /* Add identifier column */
    $name = $this->identifierColumnName;
    $cols_str[] = $name . " AS " . $this->getColNickname($name);

    $sql = "SELECT " . mysql_list($cols_str, "noop") . " FROM";
    
    /* Join all input tables AND the output tables needed, depending on the recursion depth */
    $tables = $this->inputTables;
    if ($idVal === NULL) {
      $tables = array_merge($tables, $this->getColumnTables($this->outputColumns[count($recursionPath)]));
    }
    // for ($recursionLevel = 0; $recursionLevel < count($recursionPath)+1; $recursionLevel++) {
    // }
    
    /* Join all the tables needed */
    // $tables = $this->tables;
    // foreach ($columns as $col) {
    //   $tables = array_merge($tables, $this->getColumnTables($col));
    // }
    
    // echo "tables" . PHP_EOL . get_var_dump($tables);

    foreach ($tables as $k => $table) {
      $sql .= " ";
      if ($k == 0) {
        $sql .= $this->getTableName($table);
      }
      else {
        $sql .= $this->getTableJoinType($table) . " " . $this->getTableName($table);
        $clauses = $this->getTableJoinClauses($table);
        if (count($clauses)) {
          $sql .= " ON " . join(" AND ", $clauses);
        }
      }
    }

    $whereClauses = $this->getSQLWhereClauses($idVal, $recursionPath);

    if (count($whereClauses)) {
      $sql .= " WHERE " . join(" AND ", $whereClauses);
    }

    if ($this->limit !== NULL) {
      if ($idVal === NULL) {
        $sql .= " LIMIT {$this->limit}";
      }
      // else {
      //   warn("Limit term ignored at predict time");
      // }

    }

    $res = mysql_select($this->db, $sql, $silent);
    return $res;
  }

  private function getSQLConstraints($idVal, array $recursionPath) : array {
    $constraints = $this->getSQLWhereClauses($idVal, $recursionPath);
    foreach ($this->inputTables as $table) {
      $constraints = array_merge($constraints, $this->getTableJoinClauses($table));
    }
    return $constraints;
  }

  private function getSQLWhereClauses($idVal, array $recursionPath) : array {
    $whereClauses = [];
    if ($this->whereClauses !== NULL && count($this->whereClauses)) {
      $whereClauses = array_merge($whereClauses, $this->whereClauses);
    }
    if ($idVal !== NULL) {
      if($this->identifierColumnName === NULL)
        die_error("An identifier column name must be set. Use ->setIdentifierColumnName()");
      $whereClauses[] = $this->identifierColumnName . " = $idVal";
    }
    // TODO not sure, but I believe the "recursion where clauses" are only needed at train time, in order to select data that is more specific, more relevant. At test time, they really serve no purpose, don't they. Or do they actually select relevant stuff? Maybe it depends on where the identifierColumn lays in the tree hierarchy
    if ($idVal === NULL) {
      $outAttrs = $this->getOutputColumnNames();
      foreach ($recursionPath as $recursionLevel => $node) {
        // $this->getOutputColumnAttributes()[$recursionLevel][$node[0]]->getName();
        // var_dump([$node[0], $node[1]]);
        // var_dump($this->getOutputColumnAttributes()[$recursionLevel]);
        // var_dump($this->getOutputColumnAttributes()[$recursionLevel][$node[0]]);
        $whereClauses[] = $outAttrs[$recursionLevel]
        . " = '" . $node[1] . "'";
      }
    }
    return $whereClauses;
  }


  /* Create attribute(s) for a column */
  function assignColumnAttributes(array &$column, array $recursionPath = [])
  {
    $attrName = $this->getColumnAttrName($column);

    switch(true) {
      /* Forcing a set of binary categorical attributes */
      case $this->getColumnTreatmentType($column) == "ForceCategoricalBinary":
        
        /* Find classes */
        $classes = [];
        $res = $this->SQLSelectColumns([$column], NULL, $recursionPath, NULL, true);

        foreach ($res as $raw_row) {
          $class = $raw_row[$this->getColNickname($this->getColumnName($column))];
          $classes[$class] = 0;
        }
        $classes = array_keys($classes);

        if (!count($classes)) {
          warn("Couldn't apply ForceCategoricalBinary treatment to column " . $this->getColumnName($column) . ". No data instance found.");
          $attributes = NULL;
        }
        else {
          // var_dump($classes);
          $attributes = [];

          foreach ($classes as $class) {
            $attributes[] = new DiscreteAttribute($attrName . "/" . $class, "bool", ["NO_" . $class, $class]);
          }
          $this->setColumnTreatmentArg($column, 0, $classes);
        }
        break;
      /* Enum column */
      case $this->getColumnAttrType($column) == "enum":
        $domain_arr_str = (preg_replace("/enum\((.*)\)/i", "[$1]", $this->getColumnMySQLType($column)));
        eval("\$domain_arr = " . $domain_arr_str . ";");
        $attributes = [new DiscreteAttribute($attrName, "enum", $domain_arr)];
        break;
      /* Forcing a categorical attribute */
      case $this->getColumnTreatmentType($column) == "ForceCategorical":
        $attributes = [new DiscreteAttribute($attrName, "enum")];
        break;
      /* Numeric column */
      case in_array($this->getColumnAttrType($column), ["int", "float", "double"]):
        $attributes = [new ContinuousAttribute($attrName, $this->getColumnAttrType($column))];
        break;
      /* Boolean column */
      case in_array($this->getColumnAttrType($column), ["bool", "boolean"]):
        $attributes = [new DiscreteAttribute($attrName, "bool", ["0", "1"])];
        break;
      /* Text column */
      case $this->getColumnAttrType($column) == "text":
        switch($this->getColumnTreatmentType($column)) {
          case "BinaryBagOfWords":
            /* Binary attributes indicating the presence of each word */
            $generateDictAttrs = function($dict) use ($attrName, &$column) {
              $attributes = [];
              foreach ($dict as $word) {
                $attributes[] = new DiscreteAttribute("'$word' in $attrName",
                  "word_presence", ["N", "Y"]);
              }
              $this->setColumnTreatmentArg($column, 0, $dict);
              return $attributes;
            };

            /* The argument can be the dictionary size (k), or more directly the dictionary */
            if ( is_integer($this->getColumnTreatmentArg($column, 0))) {
              $k = $this->getColumnTreatmentArg($column, 0);

              /* Find $k most frequent words */
              $word_counts = [];
              $res = $this->SQLSelectColumns([$column], NULL, $recursionPath, NULL, true);
              
              if (!isset($this->stop_words)) {
                // TODO italian
                // TODO possibility to specify for each column
                $this->stop_words = explode("\n", file_get_contents($this->defaultOptions["textLanguage"] . "-stopwords.txt"));
              }
              foreach ($res as $raw_row) {
                $text = $raw_row[$this->getColNickname($this->getColumnName($column))];
                
                $words = $this->text2words($text);

                foreach ($words as $word) {
                  if (!isset($word_counts[$word]))
                    $word_counts[$word] = 0;
                  $word_counts[$word] += 1;
                }
              }
              // var_dump($word_counts);
              
              if (!count($word_counts)) {
                warn("Couldn't derive a BinaryBagOfWords dictionary for column \"" .
                  $this->getColumnName($column) . "\". This column will be ignored.");

                $attributes = NULL;
              } else {
                $dict = [];
                // optimize this?
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
                  warn("Couldn't derive a BinaryBagOfWords dictionary of size $k for column \"" 
                    . $this->getColumnName($column) . "\". Dictionary of size "
                    . count($dict) . " will be used.");
                }
                $attributes = $generateDictAttrs($dict);
              }
            }
            else if (is_array($this->getColumnTreatmentArg($column, 0))) {
              $dict = $this->getColumnTreatmentArg($column, 0);
              $attributes = $generateDictAttrs($dict);
            }
            else {
              die_error("Please specify a parameter (dictionary or dictionary size)"
                . " for bag-of-words"
                . " processing column '" . $this->getColumnName($column) . "'.");
            }
            break;
          default:
            die_error("Unknown treatment for text column \""
               . $this->getColumnName($column) . "\" : "
               . get_var_dump($this->getColumnTreatmentType($column)));
            break;
        }
        break;
      default:
        die_error("Unknown column type: " . $this->getColumnMySQLType($column));
        break;
    }
    
    if (is_array($attributes) and !count($attributes)) {
      die_error("Something's off. Attributes set for a column (here '"
        . $this->getColumnName($column) . "') can't be empty: " . get_var_dump($attributes) . PHP_EOL . get_var_dump($column) . PHP_EOL);
    }
    $column["attributes"][$this->getPathRepr($recursionPath)] = $attributes;
    // var_dump($column);
  }

  function getPathRepr(array $recursionPath) : string {
    return array_list($recursionPath, ";");
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


  function getTableName(array $tab) : string {
    return $tab["name"];
  }
  function &getTableJoinClauses(array $tab) {
    return $tab["joinClauses"];
  }
  function &getTableJoinType(array $tab) {
    return $tab["joinType"];
  }

  function getColumnName(array &$col, bool $force_no_table_name = false) : string {
    $n = $col["name"];
    return $force_no_table_name && count(explode(".", $n)) > 1 ? explode(".", $n)[1] : $n;
  }
  function &getColumnTreatment(array &$col) {
    if ($col["treatment"] !== NULL)
      listify($col["treatment"]);
    $tr = &$col["treatment"];
    if (($tr === NULL) && $this->getColumnAttrType($col, $tr) === "text") {
      if ($this->defaultOptions["textTreatment"] !== NULL) {
        $this->setColumnTreatment($col, $this->defaultOptions["textTreatment"]);
      }
      else {
        die_error("Please, specify a default treatment for text fields using ->setDefaultOption(\"textTreatment\", ...). For example, ->setDefaultOption(\"textTreatment\", [\"BinaryBagOfWords\", 10])");
      }
      return $this->getColumnTreatment($col);
    }

    return $tr;
  }
  function getColumnTreatmentType(array &$col) {
    $tr = $this->getColumnTreatment($col);
    $t = !is_array($tr) ? $tr : $tr[0];
    return $t;
  }
  function getColumnTreatmentArg(array &$col, int $j) {
    $tr = $this->getColumnTreatment($col);
    return !is_array($tr) || !isset($tr[1+$j]) ? NULL : $tr[1+$j];
  }
  function setColumnTreatment(array &$col, $val) {
    $col["treatment"] = $val;
  }
  function setColumnTreatmentArg(array &$col, int $j, $val) {
    $this->getColumnTreatment($col)[1+$j] = $val;
  }
  function getColumnAttrName(array &$col) {
    return $col["attrName"];
    // return !array_key_exists("attrName", $col) ?
    //     $this->getColumnName($col, true) : $col["attrName"];
  }

  function getColumnMySQLType(array &$col) {
    return $col["mysql_type"];
  }

  function getColumnAttributes(array &$col, array $recursionPath = []) {
    // var_dump($col);
    return isset($col["attributes"][$this->getPathRepr($recursionPath)]) ? $col["attributes"][$this->getPathRepr($recursionPath)] : NULL;
  }

  function getColumnTables(array &$col) {
    return $col["tables"];
  }

  function getColumnAttrType(array &$col, $tr = -1) {
    $mysql_type = $this->getColumnMySQLType($col);
    if (self::isEnumType($mysql_type)) {
      return "enum";
    }
    else if (self::isTextType($mysql_type)) {
      return "text";
    } else {
      if ($tr === -1) {
        $tr = $this->getColumnTreatmentType($col);
      }
      if (isset(self::$col2attr_type[$mysql_type])) {
        return self::$col2attr_type[$mysql_type][$tr];
      } else {
        die_error("Unknown column type: \"$mysql_type\"! Code must be expanded to cover this one!");
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

  function setInputTables($inputTables) : self
  {
    listify($inputTables);
    $this->inputTables = [];
    foreach ($inputTables as $table) {
      $this->addInputTable($table);
    }

    return $this;
  }

  function addInputTable($tab) : self
  {
    if (!is_array($this->inputTables)) {
      die_error("Can't addInputTable at this time! Use ->setInputTables() instead.");
    }
    
    $this->inputTables[] = $this->readTable($tab);
    
    return $this;
  }

  function readTable($tab) : array {
    $new_tab = [];
    $new_tab["name"] = NULL;
    $new_tab["joinClauses"] = [];
    $new_tab["joinType"] = count($this->inputTables) ? "INNER JOIN" : "";

    if (is_string($tab)) {
      $new_tab["name"] = $tab;
    } else if (is_array($tab)) {
      $new_tab["name"] = $tab[0];
      if (isset($tab[1])) {
        if (!count($this->inputTables)) {
          die_error("Join criteria can't be specified for the first specified inputTable: "
          . "\"{$tab[0]}\": ");
        }

        listify($tab[1]);
        $new_tab["joinClauses"] = $tab[1];
      }
      if (isset($tab[2])) {
        $new_tab["joinType"] = $tab[2];
      }
    } else {
      die_error("Malformed inputTable: " . toString($tab));
    }

    return $new_tab;
  }
  
  function setColumns($columns) : self
  {
    if ($columns === "*") {
      /* Obtain column names from database */
      $sql = "SELECT * FROM `information_schema`.`columns` WHERE `table_name` IN "
            . mysql_set(array_map([$this, "getTableName"], $this->inputTables)) . " ";
      $res = mysql_select($this->db, $sql, true);

      $colsNames = [];
      foreach ($res as $raw_col) {
        $colsNames[] = $raw_col["TABLE_NAME"].".".$raw_col["COLUMN_NAME"];
      }
      return $this->setColumns($colsNames);
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
    if (!count($this->inputTables)) {
      die_error("Must specify the concerning inputTables before the columns, through ->setInputTables() or ->addInputTable().");
    }

    if (!is_array($this->columns)) {
      die_error("Can't addColumn at this time! Use ->setColumns() instead.");
    }

    $new_col = $this->readColumn($col);

    $this->check_columnName($new_col["name"]);

    $this->assignColumnMySQLType($new_col);
    // $this->assignColumnAttributes($new_col);

    $this->columns[] = &$new_col;

    return $this;
  }

  function readColumn($col) : array {
    $new_col = [];
    $new_col["name"] = NULL;
    $new_col["treatment"] = NULL;
    $new_col["tables"] = [];
    $new_col["attrName"] = NULL;
    $new_col["mysql_type"] = NULL;

    if (is_string($col)) {
      $new_col["name"] = $col;
    } else if (is_array($col)) {
      if(!is_string($col[0])) {
        die_error("Malformed column name: " . toString($col[0])
          . ". The name must be a string.");
      }
      $new_col["name"] = $col[0];
      if (isset($col[1])) {
        listify($col[1]);
        $new_col["treatment"] = $col[1];
      }
      if (isset($col[2])) {
        if(!is_string($col[2])) {
          die_error("Malformed target attribute name for column: " . toString($col[2])
            . ". The target name must be a string.");
        }
        $new_col["attrName"] = $col[2];
      }
    } else {
      die_error("Malformed column term: " . toString($col));
    }
    
    if ($new_col["attrName"] === NULL) {
      $new_col["attrName"] = $new_col["name"];
    }

    return $new_col;
  }
  function setOutputColumns($outputColumns) : self
  {
    if ($outputColumns === NULL) {
      $this->outputColumns = [];
    } else {
      listify($outputColumns);
      $this->outputColumns = [];
      foreach ($outputColumns as $col) {
        $this->addOutputColumn($col);
      }
    }
    return $this;
  }

  function addOutputColumn($col) : self
  {
    if (!count($this->columns)) {
      die_error("You must set the columns in use before the output columns.");
    }

    if (!is_array($this->outputColumns)) {
      die_error("Can't addOutputColumn at this time! Use ->setOutputColumns() instead.");
    }

    $new_col = [];
    $new_col["name"] = NULL;
    $new_col["treatment"] = "ForceCategorical";
    $new_col["attributes"] = [];
    $new_col["tables"] = [];
    $new_col["attrName"] = NULL;
    $new_col["mysql_type"] = NULL;

    if (is_string($col)) {
      $new_col["name"] = $col;
    } else if (is_array($col)) {
      if(!is_string($col[0])) {
        die_error("Malformed output column name: " . get_var_dump($col[0])
          . ". The name must be a string.");
      }
      $new_col["name"] = $col[0];
      
      if (isset($col[1])) {
        $these_tables = array_map([$this, "readTable"], $col[1]);
        // tables also include all of the tables of the previous output layers? Can't think of a use-case, though
        $prev_tables = [];
        foreach ($this->outputColumns as $outputCol) {
          $prev_tables = array_merge($prev_tables, $this->getColumnTables($outputCol));
        }
        $new_col["tables"] = array_merge($prev_tables, $these_tables);
      }
      if (isset($col[2])) {
        $new_col["treatment"] = $col[2];
      }
      if (isset($col[3])) {
        if(!is_string($col[3])) {
          die_error("Malformed target attribute name for column: " . toString($col[3])
            . ". The target name must be a string.");
        }
        $new_col["attrName"] = $col[3];
      }
    } else {
      die_error("Malformed output column term: " . toString($col));
    }

    if ($new_col["attrName"] === NULL) {
      $new_col["attrName"] = $new_col["name"];
    }

    $this->check_columnName($new_col["name"]);

    if ($this->identifierColumnName !== NULL
      && $new_col["name"] == $this->identifierColumnName) {
      die_error("Output column ('" . $new_col["name"]
        . "') cannot be used as identifier.");
    }

    for ($i_col = count($this->columns)-1; $i_col >= 0; $i_col--) {
      $col = $this->columns[$i_col];
      if ($new_col["name"] == $this->getColumnName($col)) {
        warn("Found output column '" . $new_col["name"] . "' in input columns. Removing...");
        array_splice($this->columns, $i_col, 1);
        // die_error("Output column '" . $new_col["name"] .
        //   "' cannot also belong to inputColumns."
        //   // . get_var_dump($this->getColumnNames(true))
        //   );
      }
    }

    $this->assignColumnMySQLType($new_col);
    // $this->assignColumnAttributes($new_col);

    $this->outputColumns[] = &$new_col;

    return $this;
  }

  function check_columnName(string $colName) : self
  { 
    if (count($this->inputTables) > 1) {
      if (!preg_match("/.*\..*/i", $colName)) {
        die_error("Invalid column name: '"
          . $colName . "'. When reading more than one table, "
          . "please specify column names in their 'table_name.column_name' format.");
      }
    }

    return $this;
  }

  function assignColumnMySQLType(array &$column)
  {
    /* Obtain column type */
    $tables = array_merge($this->inputTables, $this->getColumnTables($column));

    // var_dump($tables);

    $sql = "SELECT * FROM `information_schema`.`columns` WHERE `table_name` IN "
          . mysql_set(array_map([$this, "getTableName"], $tables))
          . " AND (COLUMN_NAME = '" . $this->getColumnName($column) . "'"
          . " OR CONCAT(TABLE_NAME,'.',COLUMN_NAME) = '" . $this->getColumnName($column)
           . "')";
    $res = mysql_select($this->db, $sql, true);

    /* Find column */
    $mysql_column = NULL;
    foreach ($res as $col) {
      if (in_array($column["name"],
          [$col["TABLE_NAME"].".".$col["COLUMN_NAME"], $col["COLUMN_NAME"]])) {
        $mysql_column = $col;
        break;
      }
    }
    if ($mysql_column === NULL) {
      die_error("Couldn't retrieve information about column \""
        . $column["name"] . "\"");
    }
    $column["mysql_type"] = $mysql_column["COLUMN_TYPE"];
  }
  

  /**
   * Load an existing set of discriminative models.
   * Defaulted to the models trained the most recently
   */
  // function loadModel(?string $path = NULL) {
  //   echo "DBFit->loadModel($path)" . PHP_EOL;
    
  //   die_error("TODO loadModel, load the full hierarchy");
  //   /* Default path to that of the latest model */
  //   if ($path === NULL) {
  //     $models = filesin(MODELS_FOLDER);
  //     if (count($models) == 0) {
  //       die_error("loadModel: No model to load in folder: \"". MODELS_FOLDER . "\"");
  //     }
  //     sort($models, true);
  //     $path = $models[0];
  //     echo "$path";
  //   }

  //   $this->models = [DiscriminativeModel::loadFromFile($path)];
  // }

  /* Train and test all the model tree on the available data, and save to database */
  function updateModel(array $recursionPath = []) {
    echo "DBFit->updateModel(" . toString($recursionPath) . ")" . PHP_EOL;
    
    $recursionLevel = count($recursionPath);

    if(!($this->learner instanceof Learner))
      die_error("Learner is not initialized. Use ->setLearner() or ->setLearningMethod()");

    $dataframes = $this->readData(NULL, $recursionPath);
    
    // TODO move the recursion out of the loop? Also consider what's best memorywise
    if (!count($dataframes)) {
      echo "Train-time recursion stops here due to lack of data (recursionPath = " . toString($recursionPath)
         . "). " . PHP_EOL;
      if ($recursionLevel == 0) {
        die_error("Couldn't compute output attribute (at root level train-time).");
      }
      return;
    }

    $outputAttributes = $this->getColumnAttributes($this->outputColumns[$recursionLevel], $recursionPath);
    
    // TODO figure out, note: since the four root problems are independent, we use  splits that can be different (due to randomization)
    foreach ($dataframes as $i_prob => $data) {
      echo "Problem $i_prob/" . count($dataframes) . PHP_EOL;
      $outputAttribute = $outputAttributes[$i_prob];

      if (!$data->numInstances()) {
        echo "Skipping node due to lack of data." . PHP_EOL;
        if ($recursionLevel == 0) {
          die_error("No training data instance found (at root level prediction-time).");
        }
        continue;
      }
      
      list($trainData, $testData) = $this->getDataSplit($data);
      
      echo "TRAIN" . PHP_EOL . $trainData->toString(true) . PHP_EOL;
      echo "TEST" . PHP_EOL . $testData->toString(true) . PHP_EOL;
      
      /* Train */
      $model_name = $this->getModelName($recursionPath, $i_prob);
      $model = $this->learner->initModel();

      $model->fit($trainData, $this->learner);
      
      echo "Trained model '$model_name'." . PHP_EOL;
      // echo "Trained model '$model_name' : " . PHP_EOL . $model . PHP_EOL;

      /* Test */
      $this->test($model, $testData);

      $model->save(join_paths(MODELS_FOLDER, $model_name));
      // $model->save(join_paths(MODELS_FOLDER, date("Y-m-d_H:i:s") . $model_name));

      // TODO $model->dumpToDB($this->db, $model_name);
        // . "_" . join("", array_map([$this, "getColumnName"], ...).);
     
      // TODO $model->saveToDB($this->db, $model_name, $testData);

      $this->models[$model_name] = $model;
      
      /* Recursive step: for each output class value, recurse and train the subtree */
      if ($recursionLevel+1 == count($this->outputColumns)) {
        echo "Train-time recursion stops here (recursionPath = " . toString($recursionPath)
           . ", problem $i_prob/" . count($dataframes) . ") : '$model_name'. " . PHP_EOL;
      }
      else {
        // echo "outputAttributes";
        // var_dump($outputAttributes);
        echo "Branching at depth $recursionLevel on attribute \""
          . $outputAttribute->getName() . "\" ($i_prob/"
            . count($outputAttributes) . ")) "
          . " with domain " . toString($outputAttribute->getDomain())
          . ". " . PHP_EOL;
        foreach ($outputAttribute->getDomain() as $classValue) {
          echo "Recursion on classValue $classValue for attribute \""
          . $outputAttribute->getName() . "\". " . PHP_EOL;
          $this->updateModel(array_merge($recursionPath, [[$i_prob, $classValue]]));
        }
      }
    }
  }

  /* Use the model for predicting the value of the output columns for a new instance,
      identified by the identifier column */
  function predictByIdentifier(string $idVal, array $recursionPath = []) : array {
    echo "DBFit->predictByIdentifier($idVal, " . toString($recursionPath) . ")" . PHP_EOL;

    if($this->identifierColumnName === NULL) {
      die_error("In order to predictByIdentifier, an identifierColumnName must be set."
        . " Use ->setIdentifierColumnName()");
    }

    $recursionLevel = count($recursionPath);

    if ($recursionLevel == count($this->outputColumns)) {
      echo "Prediction-time recursion stops here due to reached bottom (recursionPath = " . toString($recursionPath) . ":" . PHP_EOL;
      return [];
    }

    $outputAttributes = $this->getColumnAttributes($this->outputColumns[$recursionLevel], $recursionPath);

    /* If no model was trained for the current node, stop the recursion */
    if ($outputAttributes !== NULL) {
      $atLeastOneModel = false;
      foreach ($outputAttributes as $i_prob => $outputAttribute) {
        $model_name = $this->getModelName($recursionPath, $i_prob);
        if((isset($this->models[$model_name]))) {
          $atLeastOneModel = true;
        }
      }
      if (!$atLeastOneModel) {
        echo "Prediction-time recursion stops here due to lack of models (recursionPath = " . toString($recursionPath) . ":" . PHP_EOL;

        foreach ($outputAttributes as $i_prob => $outputAttribute) {
          $model_name = $this->getModelName($recursionPath, $i_prob);
          echo "$model_name" . PHP_EOL;
        }
        return [];
      }
    }
    else {
      echo "Prediction-time recursion stops here due to lack of a model (recursionPath = " . toString($recursionPath) . ":" . PHP_EOL;
      return [];
    }
    
    $predictions = [];
    
    $dataframes = $this->readData($idVal, $recursionPath);

    // TODO move the recursion out of the loop? Also consider what's best memorywise
    if (!count($dataframes)) {
      echo "Prediction-time recursion stops here due to lack of data (recursionPath = " . toString($recursionPath)
         . "). " . PHP_EOL;
      if ($recursionLevel == 0) {
        die_error("Couldn't compute output attribute (at root level prediction-time).");
      }
      return [];
    }

    foreach ($dataframes as $i_prob => $data) {
      echo "Problem $i_prob/" . count($dataframes) . PHP_EOL;
      // echo "Data: " . $data->toString(true) . PHP_EOL;

      if (!$data->numInstances()) {
        die_error("No data instance found at prediction time. "
          . "Path: " . toString($recursionPath));
        continue;
      }

      if($idVal !== NULL && $data->numInstances() !== 1) {
        // TODO figure out, possible?
        die_error("Found more than one instance at predict time. Is this wanted? {$this->identifierColumnName} = $idVal");
      }

      /* Retrieve model */
      $model_name = $this->getModelName($recursionPath, $i_prob);
      if(!(isset($this->models[$model_name]))) {
        die_error("Model '$model_name' is not initialized");
      }
      $model = $this->models[$model_name];
      if(!($model instanceof DiscriminativeModel)) {
        die_error("Something's off. Model '$model_name' is not a DiscriminativeModel. " . get_var_dump($model));
      }
      echo "Using model '$model_name' for prediction." . PHP_EOL;
      // echo "Testing model '$model_name' : " . PHP_EOL . $model . PHP_EOL;

      // var_dump($data);
      // var_dump($model->getAttributes());
      $predictedVal = $model->predict($data);
      // Assuming a unique data instance is found
      $predictedVal = $predictedVal[0];
      echo "predictedVal: \"$predictedVal\"" . PHP_EOL;

      $predictions[] = [[$outputAttributes[$i_prob]->getName(), $predictedVal], $this->predictByIdentifier($idVal,
          array_merge($recursionPath, [[$i_prob, $predictedVal]]))];
    }

    if($recursionLevel == 0) {
      echo "Predictions: " . PHP_EOL;
      foreach ($predictions as $i_prob => $pred) {
        echo "[$i_prob]: " . toString($pred) . PHP_EOL;
      }
      echo PHP_EOL;
    }
    return $predictions;
  }

  /* TODO explain */
  function getModelName(array $recursionPath, int $i_prob) : string {

    $name_chunks = [];
    foreach ($recursionPath as $recursionLevel => $node) {
      $name_chunks[] =
        str_replace(".", ">", $this->getColumnAttributes($this->outputColumns[$recursionLevel], array_slice($recursionPath, 0, $recursionLevel))[$node[0]]->getName())
        . "=" . $node[1];
    }
    $path_name = join("-", $name_chunks);
    // var_dump($outAttrs);
    // var_dump($recursionPath);
    // var_dump(count($recursionPath));
    // var_dump($outAttrs[count($recursionPath)]);
    $recursionLevel = count($recursionPath);
    $currentLevelStr = str_replace(".", ":",
           $this->getColumnAttributes($this->outputColumns[$recursionLevel], $recursionPath)[$i_prob]->getName());
    return str_replace("/", ":", $path_name . "__" . $currentLevelStr);

  }
  /* Use the model for predicting on a set of instances */
  function predict(Instances $inputData) : array {
    echo "DBFit->predict(" . $inputData->toString(true) . ")" . PHP_EOL;

    if (count($this->models) > 1) {
      die_error("Can't use predict with multiple models. By the way, TODO this function has to go.");
    }
    $model = $this->models[array_key_last($this->models)];
    if(!($model instanceof DiscriminativeModel))
      die_error("Model is not initialized");

    die_error("TODO check if predict still works");
    return $model->predict($inputData);
  }

  // Test a model
  function test(DiscriminativeModel $model, Instances $testData) {
    echo "DBFit->test(" . $testData->toString(true) . ")" . PHP_EOL;

    $ground_truths = [];
    $classAttr = $testData->getClassAttribute();

    for ($x = 0; $x < $testData->numInstances(); $x++) {
      $ground_truths[] = $classAttr->reprVal($testData->inst_classValue($x));
    }

    // $testData->dropOutputAttr();
    $predictions = $model->predict($testData);
    
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
    
    echo "AVAILABLE MODELS" . PHP_EOL;
    var_dump(array_keys($this->models));
    // TODO
    // $start = microtime(TRUE);
    // $this->model->LoadFromDB($this->db, str_replace(".", ":", $this->getOutputColumnAttributes()[0]))->getName();
    // $end = microtime(TRUE);
    // echo "LoadFromDB took " . ($end - $start) . " seconds to complete." . PHP_EOL;

    if ($this->identifierColumnName !== NULL) {
      $start = microtime(TRUE);
      $this->predictByIdentifier(1);
      $end = microtime(TRUE);
      echo "predictByIdentifier took " . ($end - $start) . " seconds to complete." . PHP_EOL;
    }
  }

  function setOutputColumnName(?string $outputColumnName, $treatment = "ForceCategorical") : self
  {
    if ($outputColumnName !== NULL) {
      return $this->setOutputColumns([[$outputColumnName, $treatment]]);
    }
    else {
      return $this->setOutputColumns([]);
    }
  }

  // function getIdentifierColumnName() : string
  // {
  //   return $this->identifierColumnName;
  // }

  function setIdentifierColumnName(?string $identifierColumnName) : self
  {
    if ($identifierColumnName !== NULL) {
      if(in_array($identifierColumnName, $this->getOutputColumnNames())) {
        die_error("Identifier column ('{$identifierColumnName}') cannot be considered as the output column.");
      }
      $this->check_columnName($identifierColumnName);
    }
    $this->identifierColumnName = $identifierColumnName;
    return $this;
  }

  function setWhereClauses($whereClauses) : self
  {
    listify($whereClauses);
    foreach ($whereClauses as $jc) {
      if (!is_string($jc)) {
        die_error("Non-string value encountered in whereClauses: "
        . "\"$jc\": ");
      }
    }
    $this->whereClauses = $whereClauses;
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


  function &getDataSplit(Instances &$data) : array {
    if ($this->trainingMode === NULL) {
      $this->trainingMode = $this->defaultOptions["trainingMode"];
      echo "Training mode defaulted to " . toString($this->trainingMode);
    }

    $rt = NULL;
    /* training modes */
    switch (true) {
      /* Full training: use data for both training and testing */
      case $this->trainingMode == "FullTraining":
        $rt = [$data, $data];
        break;
      
      /* Train+test split */
      case is_array($this->trainingMode):
        $trRat = $this->trainingMode[0]/($this->trainingMode[0]+$this->trainingMode[1]);
        // TODO 
        // $data->randomize();
        $rt = Instances::partition($data, $trRat);
        
        break;
      
      default:
        die_error("Unknown training mode: " . toString($this->trainingMode));
        break;
    }
    return $rt;
  }

}

?>
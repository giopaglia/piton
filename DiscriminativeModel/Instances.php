<?php

/**
 * A set of data instances. Essentially, a table with metadata.
 * Each instance has values for the same number of attributes.
 * We assume that the attribute we want to predict is nominal
 * (i.e categorical). We also assume it to be placed in the first
 * position of the set of attributes.
 * Each instance is represented with an array, and has a weight (defaulted to 1).
 * The weight is stored at the end of each array.
 */
class Instances {
  /** Metadata for the attributes */
  private $attributes;
  
  /** The data table itself */
  private $data;

  /** The sum of weights */
  private $sumOfWeights;

  function __construct(array $attributes, array $data, $weights = 1) {
    // Checks
    if (is_array($weights)) {
      if(!(count($weights) == count($data)))
        die_error("Malformed data/weights pair encountered when building Instances(). "
        . "Need exactly " . count($data) . " weights, but "
        . count($weights) . " were found.");
    } else if(!(is_int($weights)) && $weights !== NULL) {
        die_error("Malformed weights encountered when building Instances(). "
          . "Weights argument can only be an integer value or an array (or NULL), but got \""
          . gettype($weights) . "\".");
    }
    $this->setAttributes($attributes);

    if(!($this->getClassAttribute() instanceof DiscreteAttribute))
      die_error("Instances' class attribute (here \"{$this->getClassAttribute()->toString()}\")"
      . " can only be nominal (i.e categorical).");

    $this->sumOfWeights = 0;
    
    if ($weights !== NULL) {
      # weights aren't included in data array
      foreach ($data as $instance_id => $inst) {
        if(!(count($this->attributes) == count($inst)))
          die_error("Malformed data encountered when building Instances(). "
          . "Need exactly " . count($this->attributes) . " columns, but "
          . count($inst) . " were found (on row/inst $instance_id).");
      }
      
      foreach ($data as $instance_id => &$inst) {
        if (is_array($weights)) {
          $w = $weights[$instance_id-1];
        } else if(is_numeric($weights)) {
          $w = $weights;
        }
        $inst[] = $w;
        // echo $w;
        $this->sumOfWeights += $w;
      }
    }
    else {
      # weights are included in data array
      foreach ($data as $instance_id => $inst) {
        if(!(count($this->attributes) == count($inst)-1))
          die_error("Malformed data encountered when building Instances(). "
          . "Need exactly " . count($this->attributes) . " columns, but "
          . count($inst) . " were found (on row/inst $instance_id).");
      }
      
      foreach ($data as $instance_id => &$inst) {
        $w = $inst[array_key_last($inst)];
        $this->sumOfWeights += $w;
      }
    }

    $this->data = $data;
  }

  /**
   * Static utils
   */
  static function createFromSlice(Instances &$insts, int $offset
    , int $length = NULL) : Instances {
    $data = $insts->data;
    $preserve_keys = true;
    $newData = array_slice($data, $offset, $length, $preserve_keys);
    return new Instances($insts->attributes, $newData, NULL);
  }

  static function createEmpty(Instances &$insts) : Instances {
    return new Instances($insts->attributes, []);
  }

  static function &partition(Instances &$data, float $firstRatio) : array {
    if (DEBUGMODE > 2) echo "Instances::partition(&[data], $firstRatio)" . PHP_EOL;
    if (DEBUGMODE & DEBUGMODE_DATA) echo "data : " . $data->toString() . PHP_EOL;
    
    $rt = [];

    $offset = round($data->numInstances()*$firstRatio);
    // var_dump($data->numInstances());
    // var_dump($firstRatio);
    // var_dump($offset);
    $rt[0] = Instances::createFromSlice($data, 0, $offset);
    if (DEBUGMODE & DEBUGMODE_DATA) echo "rt[0] : " . $rt[0]->toString() . PHP_EOL;
    $rt[1] = Instances::createFromSlice($data, $offset);
    if (DEBUGMODE & DEBUGMODE_DATA) echo "rt[1] : " . $rt[1]->toString() . PHP_EOL;

    return $rt;
  }

  /**
   * Read data from file, (dense) ARFF/Weka format
   */
  static function createFromARFF(string $path, string $csv_delimiter = "'") {
    if (DEBUGMODE > 2) echo "Instances::createFromARFF($path)" . PHP_EOL;
    $f = fopen($path, "r");
    
    $ID_piton_is_present = false;
    /* Attributes */
    $attributes = [];
    $key = 0;
    while(!feof($f))  {
      $line = /* mb_strtolower */ (fgets($f));
      if (startsWith(mb_strtolower($line), "@attribute")) {
        if (!startsWith(mb_strtolower($line), "@attribute '__id_piton__'")) {
          $attributes[] = Attribute::createFromARFF($line, $csv_delimiter);
          $key++;
        }
        else {
          $ID_piton_is_present = true;
          $id_key = $key;
        }
      }
      if (startsWith(mb_strtolower($line), "@data")) {
        break;
      }
    }
    $classAttr = array_pop($attributes);  // class Attribute must be in the last column
    array_unshift($attributes, $classAttr);
    // var_dump($attributes);
    // die_error("TODO");
    
    // echo get_var_dump($classAttr);
    /* Print the internal representation given the ARFF value read */
    $getVal = function ($ARFFVal, Attribute $attr) {
      $ARFFVal = trim($ARFFVal);
      if ($ARFFVal === "?") {
        return NULL;
      }
      $k = $attr->getKey($ARFFVal, true);
      return $k;
    };

    /* Data */
    $data = [];
    $weights = [];
    $i = 0;

    /** If the arff doesn't have an ID column, i create one, starting from 1 */
    if (!$ID_piton_is_present)
      $instance_id = 0;
    while(!feof($f) && $line = /* mb_strtolower */ (fgets($f)))  {
      // echo $i;
      $row = str_getcsv($line, ",", $csv_delimiter);

      if ($ID_piton_is_present) {
        preg_match("/\s*(\d+)\s*/", $row[$id_key], $id);
        $instance_id = intval($id[1]);
        array_splice($row, $id_key, 1);
      } else {
        $instance_id += 1;
      }

      if (count($row) == count($attributes) + 1) {  
        preg_match("/\s*\{\s*([\d\.]+)\s*\}\s*/", $row[array_key_last($row)], $w);

        $weights[] = floatval($w[1]);
        array_splice($row, array_key_last($row), 1);
      } else if (count($row) != count($attributes)) {
        die_error("ARFF data wrongfully encoded. Found data row [$i] with " . 
          count($row) . " values when there are " . count($attributes) .
          " attributes.");
      }
      

      $classVal = array_pop($row);
      array_unshift($row, $classVal);

      $data[$instance_id] = array_map($getVal, $row, $attributes);
      $i++;
    }
    // var_dump($data);

    if (!count($weights)) {
      $weights = 1;
    }

    fclose($f);

    return new Instances($attributes, $data, $weights);
  }

  static function createFromARFF2(string $path, string $classFeat, string $csv_delimiter = "'") {
    /** It is possible to specify the class attribute wittgenstein style */
    if (DEBUGMODE > 2) echo "Instances::createFromARFF($path)" . PHP_EOL;
    $f = fopen($path, "r");
    
    $ID_piton_is_present = false;
    /* Attributes */
    $attributes = [];
    $key = 0;
    while(!feof($f))  {
      $line = /* mb_strtolower */ (fgets($f));
      if (startsWith(mb_strtolower($line), "@attribute")) {
        if (!startsWith(mb_strtolower($line), "@attribute '__id_piton__'")) {
          $newAttribute = Attribute::createFromARFF($line, $csv_delimiter);
          $attributes[] = $newAttribute;
          if ($newAttribute->getName() === $classFeat) {
            $class_key = $key;
          }
          $key++;
        }
        else {
          $ID_piton_is_present = true;
          $id_key = $key;
        }
      }
      if (startsWith(mb_strtolower($line), "@data")) {
        break;
      }
    }

    if ($id_key === $class_key) {
      die_error("Unexpected error." . PHP_EOL);
    }

    $classAttr = $attributes[$class_key];
    array_splice($attributes, $class_key, 1);
    array_unshift($attributes, $classAttr);
    
    /* Print the internal representation given the ARFF value read */
    $getVal = function ($ARFFVal, Attribute $attr) {
      $ARFFVal = trim($ARFFVal);
      if ($ARFFVal === "?") {
        return NULL;
      }
      $k = $attr->getKey($ARFFVal, true);
      return $k;
    };

    /* Data */
    $data = [];
    $weights = [];
    $i = 0;

    /** If the arff doesn't have an ID column, i create one, starting from 1 */
    if (!$ID_piton_is_present)
      $instance_id = 0;
    while(!feof($f) && $line = /* mb_strtolower */ (fgets($f)))  {
      // echo $i;
      $row = str_getcsv($line, ",", $csv_delimiter);

      if ($ID_piton_is_present) {
        preg_match("/\s*(\d+)\s*/", $row[$id_key], $id);
        $instance_id = intval($id[1]);
        array_splice($row, $id_key, 1);
      } else {
        $instance_id += 1;
      }

      if (count($row) == count($attributes) + 1) {  
        preg_match("/\s*\{\s*([\d\.]+)\s*\}\s*/", $row[array_key_last($row)], $w);

        $weights[] = floatval($w[1]);
        array_splice($row, array_key_last($row), 1);
      } else if (count($row) != count($attributes)) {
        die_error("ARFF data wrongfully encoded. Found data row [$i] with " . 
          count($row) . " values when there are " . count($attributes) .
          " attributes.");
      }
      

      $classVal = $row[$class_key];
      array_splice($row, $class_key, 1);
      array_unshift($row, $classVal);

      $data[$instance_id] = array_map($getVal, $row, $attributes);
      $i++;
    }
    // var_dump($data);

    if (!count($weights)) {
      $weights = 1;
    }

    fclose($f);

    return new Instances($attributes, $data, $weights);
  }

  
  /**
   * Instances & attributes handling
   */
  function numAttributes() : int { return count($this->attributes); }
  function numInstances() : int { return count($this->data); }

  function pushInstancesFrom(Instances $data, $safety_check = false) {
    if ($safety_check) {
      if ($data->sortAttrsAs($this->getAttributes(), true) == false) {
      die_error("Couldn't pushInstancesFrom, since attribute sets do not match. "
        . $data->toString(true) . PHP_EOL . PHP_EOL
        . $this->toString(true));
      }
    }
    foreach ($data->iterateRows() as $instance_id => $row) {
      $this->pushInstanceFrom($data, $instance_id);
    }
  }

  function iterateRows() {
    foreach($this->data as $instance_id => $row) {
      yield $instance_id => $row;
    }
  }

  function iterateInsts() {
    foreach($this->data as $instance_id => $row) {
      yield $instance_id => array_slice($row, 0, -1);
    }
  }
  function iterateWeights() {
    $numAttrs = $this->numAttributes();
    foreach($this->data as $instance_id => $row) {
      yield $instance_id => $row[$numAttrs];
    }
  }

  function _getInstance(int $instance_id, bool $includeClassAttr) : array
  {
    if ($includeClassAttr) {
      return array_slice($this->data[$instance_id], 0, -1);
    }
    else {
      return array_slice($this->data[$instance_id], 1, -1);
    }
  }
  function getInstance(int $instance_id) : array { return array_slice($this->data[$instance_id], 0, -1); }

  private function getRow(int $instance_id) : array { return $this->data[$instance_id]; }
  // function getInstances() : array {
    // var_dump(range(0, ($this->numInstances() > 0 ? $this->numInstances()-1 : 0)));
    // return array_map([$this, "getInstance"], 
      // ($this->numInstances() > 0 ? $this->getIds() : [])
      // );
  // }


  /**
   * Functions for the single data instance
   */
  
  function inst_valueOfAttr(int $instance_id, Attribute $attr) {
    $j = $attr->getIndex();
    return $this->inst_val($instance_id, $j);
  }

  // function inst_isMissing(int $i, Attribute $attr) : bool {
  //   return ($this->inst_valueOfAttr($i, $attr) === NULL);
  // }
  
  function getRowInstance(array $row, bool $includeClassAttr) : array
  {
    if ($includeClassAttr) {
      return array_slice($row, 0, -1);
    }
    else {
      return array_slice($row, 1, -1);
    }
  }

  function inst_weight(int $instance_id) : int {
    return $this->data[$instance_id][$this->numAttributes()];
  }
  function getRowWeight(array $row) {
    return $row[$this->numAttributes()];
  }
  
  function reprClassVal($classVal) {
    return $this->getClassAttribute()->reprVal($classVal);
  }
  
  function inst_classValue(int $instance_id) : int {
    // Note: assuming the class attribute is the first
    return (int) $this->inst_val($instance_id, 0);
  }

  function inst_setClassValue(int $instance_id, int $cl) {
    // Note: assuming the class attribute is the first
    $this->data[$instance_id][0] = $cl;
  }

  // protected function inst_val(int $instance_id, int $j) {
  function inst_val(int $instance_id, int $j) {
    return $this->data[$instance_id][$j];
  }
  function getInstanceVal(array $inst, int $j) {
    return $inst[$j];
  }
  function getRowVal(array $row, int $j) {
    return $row[$j];
  }

  function pushColumn(array $column, Attribute $attribute)
  {
    foreach ($this->data as $instance_id => &$inst) { 
      array_splice($inst, 1, 0, [$column[$instance_id]]);
    }
    array_splice($this->attributes, 1, 0, [$attribute]);
    $this->reindexAttributes();
  }

  // TODO remove pushInstance per sicurezza? No dai
  private function pushRow(array $row, ?int $instance_id = NULL)
  {
    if(!(count($this->attributes)+1 == count($row)))
      die_error("Malformed data encountered when pushing an instance to Instances() object. "
      . "Need exactly " . count($this->attributes) . " columns, but "
      . count($row) . " were found.");
    if ($instance_id === NULL) {
      // TODO test che non sia indicizzato
      die_error("pushInstance without an instance_id is not allowed anymore.");
      $this->data[] = $row;
    } else {
      if (isset($this->data[$instance_id])) {
        die_error("instance of id $instance_id is already in Instances. " . get_var_dump($row) . PHP_EOL . get_var_dump($this->data)
      // . PHP_EOL . get_var_dump($this)
        );
      }
      $this->data[$instance_id] = $row;
    }

    $this->sumOfWeights += $this->inst_weight($instance_id);
  }

  function pushInstanceFrom(Instances $data, int $instance_id) {
    return $this->pushRow($data->getRow($instance_id), $instance_id);
  }

  function getWeights() : array {
    return array_column_assoc($this->data, $this->numAttributes());
  }
  
  function getSumOfWeights() : int {
    return $this->sumOfWeights;
    // return array_sum($this->getWeights());
  }
  function isWeighted() : bool {
    foreach ($this->iterateWeights() as $weight) {
      if ($weight != 1) {
        // echo $weight;
        return true;
      }
    }
    return false;
  }

  function getClassAttribute() : Attribute {
    // Note: assuming the class attribute is the first
    return $this->getAttributes()[0];
  }

  function getClassValues() : array {
    return array_map([$this, "inst_classValue"], $this->getIds());
  }

  /*
    // Dangerous
   function dropAttr(int $j) {
    array_splice($this->attributes, $j, 1);
    $this->reindexAttributes();
    foreach ($this->data as &$row) {
      array_splice($row, $j, 1);
    }
  }

  function dropOutputAttr() {
    $this->dropAttr(0);
  }
  */

  /**
   * Remove instances with missing values for the output column
   */
  function removeUselessInsts() {
    if (DEBUGMODE) $c = 0;
    $instance_ids = $this->getIds();
    for ($x = $this->numInstances() - 1; $x >= 0; $x--) {
      if ($this->inst_classValue($instance_ids[$x]) === NULL) {
        $this->sumOfWeights -= $this->inst_weight($instance_ids[$x]);
        array_splice($this->data, $instance_ids[$x], 1);
        if (DEBUGMODE) $c++;
      }
    }
    if (DEBUGMODE && $c)
      echo "Removed $c useless instances" . PHP_EOL;
  }

  function reindexAttributes() {
    foreach ($this->attributes as $k => &$attribute) {
      $attribute->setIndex($k);
    }
  }

  function numClasses() : int {
    return $this->getClassAttribute()->numValues();
  }

  function getAttributes(bool $includeClassAttr = true) : array
  {
    // Note: assuming the class attribute is the first
    return $includeClassAttr ? $this->attributes : array_slice($this->attributes, 1);
  }

  function _getAttributes(?array $attributesSubset = NULL) : array
  {
    // Note: assuming the class attribute is the first
    return $attributesSubset === NULL ? $this->attributes : sub_array($this->attributes, $attributesSubset);
  }
  
  protected function setAttributes(array $attributes)
  {
    $this->attributes = $attributes;
    $this->reindexAttributes();
  }

  function getIds() : array
  {
    return array_keys($this->data);
  }
  /**
   * Sort the instances by the values they hold for an attribute
   */
  function sortByAttr(Attribute $attr)
  {
    if (DEBUGMODE > 2) echo "Instances->sortByAttr(" . $attr->toString() . ")" . PHP_EOL;

    // if (DEBUGMODE > 2) echo $this->toString();
    // if (DEBUGMODE > 2) echo " => ";
    $j = $attr->getIndex();
    
    uasort($this->data, function ($a,$b) use($j) {
      $A = $a[$j];
      $B = $b[$j];
      if ($A == $B) return 0;
      if ($B === NULL) return -1;
      if ($A === NULL) return 1;
      return ($A < $B) ? -1 : 1;
    });
    // if (DEBUGMODE > 2) echo $this->toString();
  }

  /**
   * Resort attributes and data according to an extern attribute set 
   */
  function sortAttrsAs(array $newAttributes, bool $allowDataLoss = false) : bool {
    if (DEBUGMODE > 2) echo "Instances->sortAttrsAs([newAttributes], allowDataLoss=$allowDataLoss)" . PHP_EOL;
    if (DEBUGMODE > 2) echo $this;
    $sameAttributes = true;

    $copyMap = [];
    $newData = [];
    // var_dump($this->attributes);
    // echo PHP_EOL;
    // var_dump($newAttributes);
    // echo PHP_EOL;

    $attributes = $this->attributes;
    /* Find new attributes in the current list of attributes */
    foreach ($newAttributes as $i_attr => $newAttribute) {
      /* Look for the correspondent attribute */
      $oldAttribute = NULL;
      // $i_oldAttribute = NULL;
      foreach ($attributes as $i => $attr) {
        if ($newAttribute->getName() == $attr->getName()) {
          $oldAttribute = $attr;
          // $i_oldAttribute = $i;
          // unset($attributes[$i]);
          break;
        }
      }
      if ($oldAttribute === NULL) {
        die_error("Couldn't find attribute '{$newAttribute->getName()}' in the current attribute list " . get_arr_dump($this->attributes) . " in Instances->sortAttrsAs");
      }

      if (!$newAttribute->isEqualTo($oldAttribute)) {
        $sameAttributes = false;
      }

      if (!$newAttribute->isAtLeastAsExpressiveAs($oldAttribute)) {
        if(!$allowDataLoss) {
          // TODO a problem can arise when forcing categorical, for example on text data, using limit at train time. sometimes the full domain is not covered, then a new class may arise at test time. what to do then? In real life application, it shouldn't happen, at least for thec lass attribute, which field is empty
          die_error("Found a target attribute that is not as expressive as the requested one. This may cause loss of data. "
            . "\nnewAttribute: " . $newAttribute->toString(false)
            . "\noldAttribute: " . $oldAttribute->toString(false));
        }
      }

      $copyMap[] = [$oldAttribute, $newAttribute];
    }

    if (DEBUGMODE > 2) {
      echo "<pre>" . PHP_EOL;
      echo "copyMap:" . PHP_EOL;
      foreach ($copyMap as $i => $oldAndNewAttr) {
        $oldAttr = $oldAndNewAttr[0];
        $newAttr = $oldAndNewAttr[1];
        echo "[" . $oldAttr->getIndex() . "] " . $oldAttr->toString() . PHP_EOL;
        echo "[" . $newAttr->getIndex() . "] " . $newAttr->toString() . PHP_EOL . PHP_EOL;
      }
      echo "</pre>" . PHP_EOL;
    }


    // echo "<pre>";
    //   echo $this->toString(false);
    // echo "</pre>";

    $newData = [];
    foreach ($this->iterateRows() as $instance_id => $row) {

      // echo "<pre>";
      //   echo toString($row) . PHP_EOL;
      // echo "</pre>";
      $newRow = [];
      foreach ($copyMap as $i => $oldAndNewAttr) {
        $oldAttr = $oldAndNewAttr[0];
        $newAttr = $oldAndNewAttr[1];
        // echo $oldAttr->getIndex() . "->" . $newAttr->getIndex() . PHP_EOL;
        // echo $oldAttr . PHP_EOL;
        // echo $newAttr . PHP_EOL . PHP_EOL;
        $new_i = $newAttr->getIndex();
        $oldVal = $this->getRowVal($row, $oldAttr->getIndex());
        // $oldVal = $this->getRowVal($row, $i);
        if ($allowDataLoss) {
          $newRow[$new_i] = $newAttr->reprValAs($oldAttr, $oldVal, true);
        }
        else {
          $newRow[$new_i] = $newAttr->reprValAs($oldAttr, $oldVal);
        }
      }
      $newRow[] = $this->getRowWeight($row);
      
      // echo "<pre>";
      //   echo toString($newRow) . PHP_EOL;
      // echo "</pre>";
      $newData[$instance_id] = $newRow;
    }
    

    // if(count($attributes) && !$allowDataLoss) { doesn't work without that unset
    //   warn("Some attributes were not requested in the new attribute set"
    //      . " in Instances->sortAttrsAs. If this is desired, please use "
    //      . " the allowDataLoss flag. " . get_arr_dump($attributes));
    // }

    $this->data = $newData;
    $this->setAttributes($newAttributes);
    if (DEBUGMODE > 2) {
      echo $this;
      // $this->checkIntegrity();
    }

    // echo "<pre>";
    //   echo $this->toString(false);
    // echo "</pre>";

    return $sameAttributes;
  }

  /**
   * Randomize the order of the instances
   */
  function randomize()
  {
    if (DEBUGMODE > 2) echo "[ Instances->randomize() ]" . PHP_EOL;

    // if (DEBUGMODE > 2) echo $this->toString();
    shuffle_assoc($this->data);
    // if (DEBUGMODE > 2) echo $this->toString();
  }

  /**
   * Sort the classes of the attribute to predict by frequency
   */
  function sortClassesByCount() {
    if (DEBUGMODE > 2) echo "Instances->sortClassesByCount()" . PHP_EOL;
    if (DEBUGMODE > 2) echo get_arr_dump($this->getClassAttribute()->getDomain());
    $classes = $this->getClassAttribute()->getDomain();

    $class_counts = $this->getClassCounts();

    // if (DEBUGMODE > 2) echo $this->toString();

    $indices = range(0, count($classes) - 1);
    // if (DEBUGMODE > 2) echo get_var_dump($classes);
    if (DEBUGMODE > 2) echo get_var_dump($class_counts);
    
    array_multisort($class_counts, SORT_ASC, $classes, $indices);
    $class_map = array_flip($indices);

    // if (DEBUGMODE > 2) echo get_var_dump($classes);
    // if (DEBUGMODE > 2) echo get_var_dump($indices);
    // if (DEBUGMODE > 2) echo get_var_dump($class_map);

    foreach ($this->iterateRows() as $instance_id => $row) {
      $cl = $this->inst_classValue($instance_id);
      $this->inst_setClassValue($instance_id, $class_map[$cl]);
    }
    $this->getClassAttribute()->setDomain($classes);
    if (DEBUGMODE > 2) echo get_arr_dump($this->getClassAttribute()->getDomain());

    // if (DEBUGMODE > 2) echo $this->toString();

    return $class_counts;
  }

  /**
   * Number of unique values appearing in the data, for an attribute.
   */
  function numDistinctValues(Attribute $attr) : int {
    $j = $attr->getIndex();
    $valPresence = [];
    foreach ($this->iterateInsts() as $instance_id => $inst) {
      $val = $this->getInstanceVal($inst, $j);
      if (!isset($valPresence[$val])) {
        $valPresence[$val] = 1;
      }
    }
    return count($valPresence);
  }

  /**
   * TODO explain
   */
  function checkCutOff(float $cutOffValue) : bool {
    $class_counts = $this->getClassCounts();
    $total = array_sum($class_counts);
    foreach ($class_counts as $class => $counts) {
      if ((float) $counts/$total < $cutOffValue)
        return false; // $counts/$total;
    }
    return true;
  }
  function getClassShare(int $classId) : float {
    $class_counts = $this->getClassCounts();
    $total = array_sum($class_counts);
    return (float) $class_counts[$classId]/$total;
  }

  function getClassCounts() : array {
    $classes = $this->getClassAttribute()->getDomain();
    $class_counts = array_fill(0,count($classes),0);
    foreach ($this->iterateRows() as $instance_id => $row) {
      $val = $this->inst_classValue($instance_id);
      $class_counts[$val]++;
    }
    return $class_counts;
  }

  function checkIntegrity() : bool {
    die_error("checkIntegrity TODO.");
    foreach ($this->iterateRows() as $i => $row) {
    }
  }

  /* Perform prediction onto some data. */
  function appendPredictions(DiscriminativeModel $model) {
    $new_col = $model->predict($this, true)["predictions"];
    $newAttr = clone $this->getClassAttribute();
    $newAttr->setName($newAttr->getName() . "_" // . $model->getName()
     . "predictions");
    $this->pushColumn($new_col, $newAttr);
  }
  
  /**
   * Save data to file, (dense) ARFF/Weka format
   */
  function save_ARFF(string $path) {
    if (DEBUGMODE > 2) echo "Instances->save_ARFF($path)" . PHP_EOL;
    postfixisify($path, ".arff");
    $relName = $path;
    depostfixify($relName, ".arff");
    // die_error("TODO: save_ARFF is experimental and has to be tested.");
    $f = fopen($path, "w");
    fwrite($f, "% Generated with " . PACKAGE_NAME . "\n");
    fwrite($f, "\n");
    fwrite($f, "@RELATION '" . addcslashes(basename($relName), "'") . "'\n\n");

    // Move output attribute from first to last position
    $attributes = $this->getAttributes();
    $classAttr = array_shift($attributes);
    array_push($attributes, $classAttr);
    $ID_piton_is_present = false;

    /* Attributes */
    fwrite($f, "@ATTRIBUTE '__ID_piton__' numeric");
    fwrite($f, "\n");
    foreach ($attributes as $attr) {
      if ($attr->getName() === '__ID_piton__') {
        $ID_piton_is_present = true;
      } else {
        fwrite($f, "@ATTRIBUTE '" . addcslashes($attr->getName(), "'") . "' {$attr->getARFFType()}");
        fwrite($f, "\n");
      }
    }
    
    /* Print the ARFF representation of a value of the attribute */
    $getARFFRepr = function ($val, Attribute $attr) {
      return $val === NULL ? "?" : ($attr instanceof DiscreteAttribute ? "'" . addcslashes($attr->reprVal($val), "'") . "'" : $attr->reprVal($val));
    };
    
    /* Data */
    fwrite($f, "\n@DATA\n");
    foreach ($this->iterateRows() as $instance_id => $row) {
      $row_perm = array_map($getARFFRepr, $this->getInstance($instance_id), $this->getAttributes());
      $classVal = array_shift($row_perm);
      array_push($row_perm, $classVal);

      if ($ID_piton_is_present === false) {
        fwrite($f, "$instance_id, " . join(",", $row_perm) . ", {" . $this->inst_weight($instance_id) . "}\n");
      } else {
        fwrite($f, "" . join(",", $row_perm) . ", {" . $this->inst_weight($instance_id) . "}\n");
      }
    }

    fclose($f);
  }

  /**
   * Save data to file, CSV format
   * TODO: createFromCSV(...) but note that a csv doesn't tell the data types.
   */
  function save_CSV(string $path, bool $includeClassAttr = true) {
    if (DEBUGMODE > 2) echo "Instances->save_CSV($path)" . PHP_EOL;
    postfixisify($path, ".csv");
    $f = fopen($path, "w");

    /* Attributes */
    $attributes = $this->getAttributes($includeClassAttr);
    $header_row = ["ID"];
    foreach ($attributes as $attr) {
      $header_row[] = $attr->getName();
    }
    if($this->isWeighted()) {
      $header_row[] = "WEIGHT";
    }
    fputcsv($f, $header_row);

    /* Print the CSV representation of a value of the attribute */
    $getCSVAttrRepr = function ($val, Attribute $attr) {
      return $val === NULL ? "" : $attr->reprVal($val);
    };

    $getCSVRepr = function ($val, Attribute $attr) {
      return $val === NULL ? "" : $val;
    };
    
    /* Data */
    
    if(!$this->isWeighted()) {
      foreach ($this->iterateRows() as $instance_id => $row) {
        fputcsv($f, array_merge([$instance_id], array_map($getCSVAttrRepr, $this->getRowInstance($row, $includeClassAttr), $attributes)));
      }
    }
    else {
      foreach ($this->iterateRows() as $instance_id => $row) {
        fputcsv($f, array_merge([$instance_id], array_map($getCSVAttrRepr, $this->getRowInstance($row, $includeClassAttr), $attributes), [$this->getRowWeight($row)]));
      }
    }

    /* Stats */

    if(!$this->isWeighted()) {
      foreach ($this->computeStats() as $statName => $statRow) {
        fputcsv($f, array_merge([$statName], array_map($getCSVRepr, $this->getRowInstance($statRow, $includeClassAttr), $attributes)));
      }
    }
    else {
      foreach ($this->computeStats() as $statName => $statRow) {
        fputcsv($f, array_merge([$statName], array_map($getCSVRepr, $this->getRowInstance($statRow, $includeClassAttr), $attributes), [$this->getRowWeight($row)]));
      }
    }

    fclose($f);
  }

  /**
   * Compute statistical indicators such as min, max, average and standard deviation
   *  for numerical attributes.
   */
  function computeStats() : array {
    // TODO ignore discrete attributes when computing stats
    $attrs = $this->getAttributes();

    $fillNonNumWithNull = function (&$arr) use($attrs) {
      foreach ($attrs as $i => $attr) {
        if(!($attr instanceof ContinuousAttribute)) {
          $arr[$i] = NULL;
        }
      }
    };

    $row_min = [];
    $row_max = [];
    $fillNonNumWithNull($row_min);
    $fillNonNumWithNull($row_max);
    $row_sum = array_fill(0,count($attrs)+1,0);
    $fillNonNumWithNull($row_sum);
    $row_count = array_fill(0,count($attrs)+1,0);;
    $row_weight = array_fill(0,count($attrs)+1,0);;
    foreach ($this->iterateRows() as $instance_id => $row) {
      $weight = $this->getRowWeight($row);
      foreach ($row as $i => $val) {
        if ($val !== NULL) {

          if($row_sum[$i] !== NULL) {
            if (!isset($row_min[$i])) {
              $row_min[$i] = $val;
            } else {
              $row_min[$i] = min($row_min[$i], $val);
            }

            if (!isset($row_max[$i])) {
              $row_max[$i] = $val;
            } else {
              $row_max[$i] = max($row_max[$i], $val);
            }

            $row_sum[$i] += $weight * $val;
          }
          
          $row_count[$i]++;
          $row_weight[$i] += $weight;
        }
      }
    }

    ksort($row_min);
    ksort($row_max);

    $row_avg = array_fill(0,count($attrs)+1,0);
    $fillNonNumWithNull($row_avg);
    foreach ($row_sum as $i => $val) {
      if ($row_sum[$i] !== NULL) {
        $row_avg[$i] = safe_div($val, $row_weight[$i]);
      }
    }

    $row_stdev = array_fill(0,count($attrs)+1,0);
    $fillNonNumWithNull($row_stdev);
    foreach ($this->iterateRows() as $instance_id => $row) {
      foreach ($row as $i => $val) {
        if ($row_sum[$i] !== NULL && $row_avg[$i] !== NULL && $row_avg[$i] !== NAN && $val !== NULL) {
          $row_stdev[$i] += pow(($row_avg[$i] - $val), 2);
        }
      }
    }

    foreach ($row_stdev as $i => $val) {
      if ($row_sum[$i] !== NULL) {
        $row_stdev[$i] = sqrt(safe_div($val, $row_weight[$i]));
      }
    }

    return ["MIN" => $row_min,
            "MAX" => $row_max,
            "AVG" => $row_avg,
            "STDEV" => $row_stdev,
            "COUNT" => $row_count,
            "WCOUNT" => $row_weight];
  }

  /**
   * Print a textual representation of the instances
   */
  function inst_toString(int $instance_id, bool $short = true) : string {
    $out_str = "";
    if (!$short) {
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", 1+$this->numAttributes()+1) . "|\n";
      $out_str .= "";
      foreach ($this->getAttributes() as $att) {
        $out_str .= substr($att->toString(), 0, 7) . "\t";
      }
      $out_str .= "weight";
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", 1+$this->numAttributes()+1) . "|\n";
    }
    $out_str .= str_pad($instance_id, 7, " ", STR_PAD_BOTH) . "\t";
    foreach ($this->getInstance($instance_id) as $val) {
      if ($val === NULL) {
        $x = "N/A";
      }
      else {
        $x = "{$val}";
      }
      $out_str .= str_pad($x, 7, " ", STR_PAD_BOTH) . "\t";
    }
    $out_str .= "{" . $this->inst_weight($instance_id) . "}";
    if (!$short) {
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", 1+$this->numAttributes()+1) . "|\n";
    }
    return $out_str;
  }

  /**
   * Print a textual representation of the instances
   */
  function __toString() : string {
    return $this->toString();
  }
  function toString(bool $short = false, ?array $attributesSubset = NULL) : string {
    $attributes = $this->_getAttributes($attributesSubset);
    $out_str = "";
    $atts_str = [];
    foreach ($attributes as $i => $att) {
      // $atts_str[] = substr($att->toString(), 0, 7);
      $atts_str[] = "[$i]:" . $att->toString(false) . PHP_EOL;
    }
    if ($short) {
      $out_str .= "Instances{{$this->numInstances()} instances; "
        . ($this->numAttributes()-1) . "+1 attributes (classAttribute: " . $this->getClassAttribute() . ")}";
    } else {
      $out_str .= "\n";
      $out_str .= "Instances{{$this->numInstances()} instances; "
        . ($this->numAttributes()-1) . "+1 attributes [" . PHP_EOL . join(";", $atts_str) . "]}";
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", 1+count($attributes)+1) . "|\n";
      $out_str .= "";
      $out_str .= str_pad("ID", 7, " ", STR_PAD_BOTH) . "\t";
      foreach ($attributes as $i => $att) {
        // $out_str .= substr($att->toString(), 0, 7) . "\t";
        $out_str .= str_pad("[$i]", 7, " ", STR_PAD_BOTH) . "\t";
      }
      $out_str .= "weight";
      $out_str .= "\n";
      // TODO reuse inst_toString
      $out_str .= str_repeat("======|=", 1+count($attributes)+1) . "|\n";
      foreach ($this->iterateRows() as $instance_id => $row) {
        $out_str .= str_pad($instance_id, 7, " ", STR_PAD_BOTH) . "\t";
        foreach ($this->getInstance($instance_id) as $j => $val) {
          if ($attributesSubset !== NULL && !in_array($j, $attributesSubset)) {
            continue;
          }
          if ($val === NULL) {
            $x = "N/A";
          }
          else {
            $x = toString($val);
          }
          $out_str .= str_pad($x, 7, " ", STR_PAD_BOTH) . "\t";
        }
        $out_str .= "{" . $this->inst_weight($instance_id) . "}";
        $out_str .= "\n";
      }
      $out_str .= str_repeat("======|=", 1+count($attributes)+1) . "|\n";
    }
    return $out_str;
  }

  function __clone()
  {
    $this->attributes = array_map("clone_object", $this->attributes);
  }

/*
/**
 * A single data instance.
 * Some attributes are in the form of class labels (categorical attributes).
 * These we "deflate" into numerical values for easier handling.
 /
class Instance extends ArrayObject {
  /** The data instance itself. /
  public $inst;

  function __construct($inst) {
    $this->inst = $inst;
  }

  //valueOfAttr($attribute)
  //isMissing($attribute)
  //weight() // I guess this is 1?
  //classValue() // Value of the attribute to predict

  function valueOfAttr($att) {  }
  function isMissing($att) {  }
  function weight($att) { return 1; 
    /**
     * @param mixed $attributes
     *
     * @return self
     */

  /**
   * Saves the current Instances in a table into the database.
   * 
   * If there is a table in the database with the same name of $tableName, it is overwritten.
   * It adds `instances__` as a prefix to $tableName, so it is possible to group them in a
   * database administrator tool such as PhpMyAdmin.
   */
  function saveToDB(object &$db, string $tableName) {

    $sql = "DROP TABLE IF EXISTS `instances__$tableName`"; // Drop the table if it already exists

    // Query execution
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql" . PHP_EOL);
    if (!$stmt->execute())
      die_error("Query failed: $sql" . PHP_EOL);
    $stmt->close();

    $sql = "CREATE TABLE `instances__$tableName`"; // Name of the table/relation

    /**
     * Attributes
     * The categorical attributes' domain is stored inside column comments in the database;
     * for continuos attributes, 'numeric' is stored as a column comment instead.
     * This semplifies the reading and reconstruction from a database of an object of type Instances.
     */
    $attributes = $this->getAttributes();
    $classAttr = array_shift($attributes);
    array_push($attributes, $classAttr);
    $ID_piton_is_present = false;

    $sql .= " (__ID_piton__ INT AUTO_INCREMENT PRIMARY KEY";
    foreach ($attributes as $attr) {
      if ($attr->getName() === '__ID_piton__') {
        $ID_piton_is_present = true;
      } else if ($attr instanceof DiscreteAttribute) {
        $sql .= ", {$attr->getName()} VARCHAR(256) DEFAULT NULL COMMENT \"{'" . $attr->getDomainString() . "'}\"";;
      } else if ($attr instanceof ContinuousAttribute) {
        $sql .= ", {$attr->getName()} DECIMAL(10,2) NOT NULL COMMENT 'numeric'";
      } else {
        die_error("Error: couldn't decide the type of attribute {$attr->getName()}." . PHP_EOL);
      }
    }
    $sql .= ", weight INT DEFAULT 1)";

    // Query execution
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql" . PHP_EOL);
    if (!$stmt->execute())
      die_error("Query failed: $sql" . PHP_EOL);
    $stmt->close();
    
    /* Print the ARFF representation of a value of the attribute, which can be reused for the database */
    $getARFFRepr = function ($val, Attribute $attr) {
      return $val === NULL ? "?" : $attr->reprVal($val);
    };

    // Data
    $arr_vals = [];
    if ($ID_piton_is_present) {
      foreach ($this->iterateRows() as $instance_id => $row) {
        $row_perm = array_map($getARFFRepr, $this->getInstance($instance_id), $this->getAttributes());
        $classVal = array_shift($row_perm);
        array_push($row_perm, $classVal);
        $str = "'" . join("', '", $row_perm) . "', '{$this->inst_weight($instance_id)}'";
        $arr_vals[] = $str;
      }
    } else {
      // If ID_piton isn't present, a new ID is given instead (starting from 1)
      $i = 0;
      foreach ($this->iterateRows() as $instance_id => $row) {
        $row_perm = array_map($getARFFRepr, $this->getInstance($instance_id), $this->getAttributes());
        $classVal = array_shift($row_perm);
        array_push($row_perm, $classVal);
        $str = "'" . (++$i) . "', '" . join("', '", $row_perm) . "', '{$this->inst_weight($instance_id)}'";
        $arr_vals[] = $str;
      }
    }

    $sql = "INSERT INTO `instances__$tableName` (__ID_piton__, ";
    foreach ($attributes as $attr) {   
        $sql .= "{$attr->getName()}, ";
    }
    $sql .= "weight) VALUES (" . join("), (", $arr_vals) . ")";

    // Query execution
    $stmt = $db->prepare($sql);
    if (!$stmt)
      die_error("Incorrect SQL query: $sql" . PHP_EOL);
    if (!$stmt->execute())
      die_error("Query failed: $sql" . PHP_EOL);
    $stmt->close();
  }

  /**
   * (Re)Creates an object of class Instances from a table of the database
   */
  function createFromDB(object &$db, string $tableName) {
    
    $ID_piton_is_present = false;

    /* Attributes */
    $attributes = [];

    // Read the name of the attributes
    $sql = "SELECT group_concat(COLUMN_NAME)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'instances__$tableName';";

    // Query execution
    if (!($result = mysqli_query($db, $sql))) {
      die_error("Error in SQL query!" . PHP_EOL);
    }
    
    $tableAttributes = mysqli_fetch_array($result)[0];
    mysqli_free_result($result);
    $tableAttributes = explode(',', $tableAttributes);

    foreach ($tableAttributes as $attr) {
      if ($attr !== "__ID_piton__" && $attr !== "weight") {
        // Recostruction of the domain of the attribute, stored in column comments in the database
        $sql = "SELECT COLUMN_COMMENT
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'instances__$tableName'
                AND COLUMN_NAME = '$attr'";

        // Query execution
        if (!($result = mysqli_query($db, $sql))) {
          die_error("Error in SQL query!" . PHP_EOL);
        }

        $domain = mysqli_fetch_array($result)[0];
        mysqli_free_result($result);  
        $attributes[] = Attribute::createFromDB($attr, $domain);
      } else if ($attr === "__ID_piton__") {
        $ID_piton_is_present = true;
      } else if ($attr === "weight") {
        break;
      } else {
        die_error("Unexpected error when reading attribute $attr from the database" . PHP_EOL);
      }
    }

    /* Print the internal representation given the ARFF value read, which can be reused for the database */
    $getVal = function ($ARFFVal, Attribute $attr) {
      $ARFFVal = trim($ARFFVal);
      if ($ARFFVal === "?") {
        return NULL;
      }
      $k = $attr->getKey($ARFFVal, true);
      return $k;
    };

    /* Data */
    $data = [];
    $weights = [];
    $i = 0;

    /** If the table doesn't have an ID column, i create one starting from 1;
     *  it should have it if it is a recostruction of an object of class Instances
     */
    if (!$ID_piton_is_present)
      $instance_id = 0;

    $sql = "SELECT * FROM instances__$tableName";
    if (!($result = mysqli_query($db, $sql))) {
      die_error("Error in SQL query" . PHP_EOL);
    }    

    while ($row_data = mysqli_fetch_array($result))  {
      $row = [];
      // Must clear the array
      if ($ID_piton_is_present) {
        $row[] = $row_data['__ID_piton__'];
      }
      foreach ($attributes as $attr) {
        $row[] = $row_data[$attr->getName()];
      }
      if (isset($row_data['weight'])) {
        $row[] = $row_data['weight'];
      }

      if ($ID_piton_is_present) {
        $instance_id = $row[array_key_first($row)];
        array_shift($row);
      } else {
        $instance_id++;
      }

      if (count($row) == count($attributes) + 1) { 
        // Case weight is present 
        $w = $row[array_key_last($row)];
        $weights[] = floatval($w);
        array_splice($row, array_key_last($row), 1);
      } else if (count($row) != count($attributes)) {
        die_error("Unexpected error when creating Instances' data from database at row $i" . PHP_EOL);
      }

      $data[$instance_id] = array_map($getVal, $row, $attributes);
      $i++;
    }

    mysqli_free_result($result); 

    if (!count($weights)) {
      $weights = 1;
    }

    return new Instances($attributes, $data, $weights);
  }
}
?>

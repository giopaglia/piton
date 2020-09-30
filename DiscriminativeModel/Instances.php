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
    } else {
      if(!(is_int($weights)))
        die_error("Malformed weights encountered when building Instances(). "
          . "Weights argument can only be an integer value or an array, but got \""
          . gettype($weights) . "\".");
    }

    $this->setAttributes($attributes);

    if(!($this->getClassAttribute() instanceof DiscreteAttribute))
      die_error("Instances' class attribute (here \"{$this->getClassAttribute()->toString()}\")"
      . " can only be nominal (i.e categorical).");

    foreach ($data as $i => $inst) {
      if(!(count($this->attributes) == count($inst)))
        die_error("Malformed data encountered when building Instances(). "
        . "Need exactly " . count($this->attributes) . " columns, but "
        . count($inst) . " were found (on row/inst $i).");
    }

    $this->sumOfWeights = 0;
    
    foreach ($data as $k => &$inst) {
      $w = (!is_array($weights) ? $weights : $weights[$k]);
      $inst[] = $w;
      $this->sumOfWeights += $w;
    }
    $this->data = $data;
  }

  /**
   * Static utils
   */
  static function createFromSlice(Instances &$insts, int $offset
    , int $length = NULL) : Instances {
    $data = $insts->getInstances();
    $weights = $insts->getWeights();
    $newData = array_slice($data, $offset, $length);
    $newWeights = array_slice($weights, $offset, $length);
    return new Instances($insts->attributes, $newData, $newWeights);
  }

  static function createEmpty(Instances &$insts) : Instances {
    return new Instances($insts->attributes, []);
  }

  static function &partition(Instances &$data, float $firstRatio) : array {
    if (DEBUGMODE > 2) echo "Instances::partition(&[data], $firstRatio)" . PHP_EOL;
    if (DEBUGMODE > 2) echo "data : " . $data->toString() . PHP_EOL;
    
    $rt = [];

    $offset = round($data->numInstances()*$firstRatio);
    // var_dump($data->numInstances());
    // var_dump($firstRatio);
    // var_dump($offset);
    $rt[0] = Instances::createFromSlice($data, 0, $offset);
    if (DEBUGMODE > 2) echo "rt[0] : " . $rt[0]->toString() . PHP_EOL;
    $rt[1] = Instances::createFromSlice($data, $offset);
    if (DEBUGMODE > 2) echo "rt[1] : " . $rt[1]->toString() . PHP_EOL;

    return $rt;
  }

  function pushInstancesFrom(Instances $insts) {
    // TODO check if attrs are the same?
    foreach ($insts->instsGenerator() as $inst) {
      $this->pushInstance($inst);
    }
  }

  /**
   * Read data from file, (dense) ARFF/Weka format
   */
  function createFromARFF(string $path) {
    if (DEBUGMODE > 2) echo "Instances::createFromARFF($path)" . PHP_EOL;
    $f = fopen($path, "r");
    
    /* Attributes */
    $attributes = [];
    while(!feof($f))  {
      $line = strtolower(fgets($f));
      if (startsWith($line, "@attribute")) {
        $attributes[] = Attribute::createFromARFF($line);
      }
      if (startsWith($line, "@data")) {
        break;
      }
    }
    $classAttr = array_pop($attributes);
    array_unshift($attributes, $classAttr);

    /* Print the internal representation given the ARFF value read */
    $getVal = function ($ARFFVal, Attribute $attr)
    {
      $ARFFVal = trim($ARFFVal);
      if ($ARFFVal === "?") {
        return NULL;
      }
      $k = $attr->getKey($ARFFVal, true);
      // $k = $attr->getKey($ARFFVal);
      // echo ($k);
      // if ($k === false) {
      //   die_error("ARFF data wrongfully encoded. Couldn't find element \""
      //     . get_var_dump($ARFFVal)
      //     . "\" in domain of attribute {$attr->getName()} ("
      //     . get_arr_dump($attr->getDomain()) . "). ");
      // }
      return $k;
    };

    /* Data */
    $data = [];
    $weights = [];
    while(!feof($f) && $line = strtolower(fgets($f)))  {
      // TODO fix cuz dis not safe for text fields
      $row = explode(",", $line);
      if (count($row) == count($attributes) + 1) {
        preg_match("/\{(.*)\}/", $row[array_key_last($row)], $w);
        $weights[] = $w;
        array_splice($row, array_key_last($row), 1);
      } else if (count($row) != count($attributes)) {
        die_error("ARFF data wrongfully encoded. Found data row with " . 
          count($row) . " values when there are " . count($attributes) .
          " attributes.");
      }

      $classVal = array_pop($row);
      array_unshift($row, $classVal);
      $data[] = array_map($getVal, $row, $attributes);
    }

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

  function instsGenerator() {
    foreach($this->data as $row) {
      yield array_slice($row, 0, -1);
    }
  }
  function weightsGenerator() {
    $numAttrs = $this->numAttributes();
    foreach($this->data as $row) {
      yield $row[$numAttrs];
    }
  }

  function _getInstance(int $i, bool $includeClassAttr) : array
  {
    if ($includeClassAttr) {
      return array_slice($this->data[$i], 0, -1);
    }
    else {
      return array_slice($this->data[$i], 1, -1);
    }
  }
  function getInstance(int $i) : array { return array_slice($this->data[$i], 0, -1); }
  function getInstances() : array {
    // var_dump(range(0, ($this->numInstances() > 0 ? $this->numInstances()-1 : 0)));
    return array_map([$this, "getInstance"], 
      ($this->numInstances() > 0 ? range(0, $this->numInstances()-1) : [])
      );
  }


  /**
   * Functions for the single data instance
   */
  
  function inst_valueOfAttr(int $i, Attribute $attr) {
    $j = $attr->getIndex();
    return $this->inst_val($i, $j);
  }

  // function inst_isMissing(int $i, Attribute $attr) : bool {
  //   return ($this->inst_valueOfAttr($i, $attr) === NULL);
  // }
  
  function inst_weight(int $i) : int {
    return $this->data[$i][$this->numAttributes()];
  }
  
  function inst_classValue(int $i) : int {
    // Note: assuming the class attribute is the first
    return (int) $this->inst_val($i, 0);
  }

  function inst_setClassValue(int $i, int $cl) {
    // Note: assuming the class attribute is the first
    $this->data[$i][0] = $cl;
  }

  // protected function inst_val(int $i, int $j) {
  function inst_val(int $i, int $j) {
    return $this->data[$i][$j];
  }

  function pushInstance(array $inst, int $weight = 1)
  {
    if(!(count($this->attributes) == count($inst)))
      die_error("Malformed data encountered when pushing an instance to Instances() object. "
      . "Need exactly " . count($this->attributes) . " columns, but "
      . count($inst) . " were found.");
    $inst[]       = $weight;
    $this->data[] = $inst;
    $this->sumOfWeights += $weight;
  }

  function getWeights() : array {
    return array_column($this->data, $this->numAttributes());
  }
  
  function getSumOfWeights() : int {
    return $this->sumOfWeights;
    // return array_sum($this->getWeights());
  }
  function isWeighted() : bool {
    // TODO explain that weights cannot be null
    // $a = NULL;
    // foreach ($insts->weightsGenerator() as $weight) {
    //   if ($a === NULL) {
    //     $a = $weight;
    //   } else if ($a !== $weight) {
    //     return true;
    //   }
    // }
    foreach ($this->weightsGenerator() as $weight) {
      if ($weight !== 1) {
        return true;
      }
    }
    return false;
  }

  /*
    // Dangerous
   function dropAttr(int $j) {
    array_splice($this->attributes, $j, 1);
    $this->reindexAttributes();
    foreach ($this->data as &$inst) {
      array_splice($inst, $j, 1);
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
    for ($x = $this->numInstances() - 1; $x >= 0; $x--) {
      if ($this->inst_classValue($x) === NULL) {
        $this->sumOfWeights -= $this->inst_weight($x);
        array_splice($this->data, $x, 1);
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
  function getClassAttribute() : Attribute {
    // Note: assuming the class attribute is the first
    return $this->getAttributes()[0];
  }

  function getClassValues() : array {
    return array_map([$this, "inst_classValue"], range(0, $this->numInstances()-1));
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

  /**
   * Sort the instances by the values they hold for an attribute
   */
  function sortByAttr(Attribute $attr)
  {
    if (DEBUGMODE > 2) echo "Instances->sortByAttr(" . $attr->toString() . ")" . PHP_EOL;

    // if (DEBUGMODE > 2) echo $this->toString();
    // if (DEBUGMODE > 2) echo " => ";
    $j = $attr->getIndex();
    
    usort($this->data, function ($a,$b) use($j) {
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
   * Randomize the order of the instances
   */
  function randomize()
  {
    if (DEBUGMODE > 2) echo "[ Instances->randomize() ]" . PHP_EOL;

    // if (DEBUGMODE > 2) echo $this->toString();
    shuffle($this->data);
    // if (DEBUGMODE > 2) echo $this->toString();
  }

  /**
   * Resort attributes and data according to an extern attribute set 
   */
  function sortAttrsAs(array $newAttributes, bool $allowDataLoss = false) {
    if (DEBUGMODE > 2) echo "Instances->sortAttrsAs([newAttributes], allowDataLoss=$allowDataLoss)" . PHP_EOL;
    if (DEBUGMODE > 2) echo $this;
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
      $i_oldAttribute = NULL;
      foreach ($attributes as $i => $attr) {
        if ($newAttribute->getName() == $attr->getName()) {
          $oldAttribute = $attr;
          $i_oldAttribute = $i;
          unset($attributes[$i]);
          break;
        }
      }
      if ($oldAttribute === NULL) {
        die_error("Couldn't find attribute '{$newAttribute->getName()}' in the current attribute list " . get_arr_dump($this->attributes) . " in Instances->sortAttrsAs");
      }

      if (!$newAttribute->isAtLeastAsExpressiveAs($oldAttribute) && !$allowDataLoss) {
        // TODO a problem can arise when forcing categorical, for example on text data, using limit at train time. sometimes the full domain is not covered, then a new class may arise at test time. what to do then? In real life application, it shouldn't happen, at least for thec lass attribute, which field is empty
        die_error("Found a target attribute that is not as expressive as the requested one. This may cause loss of data. "
          . "\nnewAttribute: " . $newAttribute->toString(false)
          . "\noldAttribute: " . $oldAttribute->toString(false));
      }

      $copyMap[] = [$oldAttribute, $newAttribute];
    }

    $newData = [];

    for ($x = 0; $x < $this->numInstances(); $x++) {
      $newRow = [];
      foreach ($copyMap as $oldAndNewAttr) {
        $i = $oldAndNewAttr[0]->getIndex();
        $new_i = $oldAndNewAttr[1]->getIndex();
        $oldVal = $this->inst_val($x, $i);
        $newRow[$new_i] = $oldAndNewAttr[1]->reprValAs($oldAndNewAttr[0], $oldVal);
        $newRow[] = $this->inst_weight($x);
      }
      $newData[] = $newRow;
    }

    if(count($attributes) && !$allowDataLoss) {
      warn("Some attributes were not requested in the new attribute set in Instances->sortAttrsAs. If this is desired, please use the allowDataLoss flag. " . get_arr_dump($attributes));
    }

    $this->data = $newData;
    $this->setAttributes($newAttributes);
    if (DEBUGMODE > 2) echo $this;
  }
  
  /**
   * Sort the classes of the attribute to predict by frequency
   */
  function resortClassesByCount() {
    if (DEBUGMODE > 2) echo "Instances->resortClassesByCount()" . PHP_EOL;
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

    for ($x = 0; $x < $this->numInstances(); $x++) {
      $cl = $this->inst_classValue($x);
      $this->inst_setClassValue($x, $class_map[$cl]);
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
    for ($x = 0; $x < $this->numInstances(); $x++) {
      $val = $this->inst_val($x, $j);
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
    for ($x = 0; $x < $this->numInstances(); $x++) {
      $val = $this->inst_classValue($x);
      $class_counts[$val]++;
    }
    return $class_counts;
  }

  /**
   * Save data to file, (dense) ARFF/Weka format
   */
  function save_ARFF(string $path) {
    if (DEBUGMODE > 2) echo "Instances->save_ARFF($path)" . PHP_EOL;
    postfixisify($path, ".arff");
    die_error("TODO: save_ARFF is experimental and has to be tested.");
    $f = fopen($path, "w");
    fwrite($f, "% Generated with \"" . PACKAGE_NAME . "\"\n");
    fwrite($f, "\n");
    fwrite($f, "@RELATION " . basename($path) . "\n\n");

    /* Attributes */
    foreach ($this->getAttributes() as $attr) {
      fwrite($f, "@ATTRIBUTE {$attr->getName()} {$attr->getARFFType()}");
      fwrite($f, "\n");
    }
    
    /* Print the ARFF representation of a value of the attribute */
    $getARFFRepr = function ($val, Attribute $attr)
    {
      return $val === NULL ? "?" : $attr->reprVal($val);
    };
    
    /* Data */
    fwrite($f, "\n@DATA\n");
    foreach ($this->data as $k => $inst) {
      fwrite($f, join(",", array_map($getARFFRepr, $this->getInstance($k), $this->getAttributes())) . ", {" . $this->inst_weight($k) . "}\n");
    }

    fclose($f);
  }

  /**
   * Save data to file, CSV format
   */
  function save_CSV(string $path, bool $includeClassAttr = true) {
    if (DEBUGMODE > 2) echo "Instances->save_CSV($path)" . PHP_EOL;
    postfixisify($path, ".csv");
    $f = fopen($path, "w");

    /* Attributes */
    $attributes = $this->getAttributes($includeClassAttr);
    $attrs_str = [];
    foreach ($attributes as $attr) {
      $attrs_str[] = $attr->getName();
    }
    if($this->isWeighted()) {
      $attrs_str[] = "WEIGHT";
    }
    fputcsv($f, $attrs_str);

    /* Print the CSV representation of a value of the attribute */
    $getCSVRepr = function ($val, Attribute $attr)
    {
      return $val === NULL ? "" : $attr->reprVal($val);
    };
    
    /* Data */
    
    if(!$this->isWeighted()) {
      foreach ($this->data as $k => $inst) {
        fputcsv($f, array_map($getCSVRepr, $this->_getInstance($k, $includeClassAttr), $attributes));
      }
    }
    else {
      foreach ($this->data as $k => $inst) {
        fputcsv($f, array_merge(array_map($getCSVRepr, $this->_getInstance($k, $includeClassAttr), $attributes), [$this->inst_weight($k)]));
      }
    }

    fclose($f);
  }

  /**
   * Print a textual representation of the instances
   */
  function inst_toString(int $i, bool $short = true) : string {
    $out_str = "";
    if (!$short) {
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", $this->numAttributes()+1) . "|\n";
      $out_str .= "";
      foreach ($this->getAttributes() as $att) {
        $out_str .= substr($att->toString(), 0, 7) . "\t";
      }
      $out_str .= "weight";
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", $this->numAttributes()+1) . "|\n";
    }
    foreach ($this->getInstance($i) as $val) {
      if ($val === NULL) {
        $x = "N/A";
      }
      else {
        $x = "{$val}";
      }
      $out_str .= str_pad($x, 7, " ", STR_PAD_BOTH) . "\t";
    }
    $out_str .= "{" . $this->inst_weight($i) . "}";
    if (!$short) {
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", $this->numAttributes()+1) . "|\n";
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
      $out_str .= str_repeat("======|=", count($attributes)+1) . "|\n";
      $out_str .= "";
      foreach ($attributes as $i => $att) {
        // $out_str .= substr($att->toString(), 0, 7) . "\t";
        $out_str .= str_pad("[$i]", 7, " ", STR_PAD_BOTH) . "\t";
      }
      $out_str .= "weight";
      $out_str .= "\n";
      // TODO reuse inst_toString
      $out_str .= str_repeat("======|=", count($attributes)+1) . "|\n";
      foreach ($this->data as $i => $inst) {
        foreach ($this->getInstance($i) as $j => $val) {
          if (!in_array($j, $attributesSubset)) {
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
        $out_str .= "{" . $this->inst_weight($i) . "}";
        $out_str .= "\n";
      }
      $out_str .= str_repeat("======|=", count($attributes)+1) . "|\n";
    }
    return $out_str;
  }

  function __clone()
  {
    $this->attributes = array_map("clone_object", $this->attributes);
  }

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
?>

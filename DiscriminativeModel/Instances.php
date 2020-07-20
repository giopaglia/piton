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
    echo "Instances::partition(&[data], $firstRatio)" . PHP_EOL;
    echo "data : " . $data->toString() . PHP_EOL;
    
    $rt = [];

    $rt[0] = Instances::createFromSlice($data, 0, $data->numInstances()*$firstRatio);
    echo "rt[0] : " . $rt[0]->toString() . PHP_EOL;
    $rt[1] = Instances::createFromSlice($data, $data->numInstances()*$firstRatio);
    echo "rt[1] : " . $rt[1]->toString() . PHP_EOL;

    return $rt;
  }

  /**
   * Instances & attributes handling
   */
  function numAttributes() : int { return count($this->attributes); }
  function numInstances() : int { return count($this->data); }

  function getInstance(int $i) : array { return array_slice($this->data[$i], 0, -1); }
  function getInstances() : array {
    return array_map([$this, "getInstance"], range(0, $this->numInstances()-1));
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
    for ($x = $this->numInstances() - 1; $x >= 0; $x--) {
      if ($this->inst_classValue($x) === NULL) {
        $this->sumOfWeights -= $this->inst_weight($x);
        array_splice($this->data, $x, 1);
      }
    }
  }

  function reindexAttributes() {
    foreach ($this->attributes as $k => &$attribute) {
      $attribute->setIndex($k);
    }
  }
  function getClassAttribute() : _Attribute {
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
  
  protected function setAttributes(array $attributes)
  {
    $this->attributes = $attributes;
    $this->reindexAttributes();
  }
  

  /**
   * Functions for the single data instance
   */
  
  function inst_valueOfAttr(int $i, _Attribute $attr) {
    $j = $attr->getIndex();
    return $this->inst_val($i, $j);
  }

  function inst_isMissing(int $i, _Attribute $attr) : bool {
    return ($this->inst_valueOfAttr($i, $attr) === NULL);
  }
  
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

  protected function inst_val(int $i, int $j) {
    return $this->data[$i][$j];
  }

  /**
   * Sort the instances by the values they hold for an attribute
   */
  function sortByAttr(_Attribute $attr)
  {
    echo "Instances->sortByAttr(" . $attr->toString() . ")" . PHP_EOL;

    // echo $this->toString();
    // echo " => ";
    $j = $attr->getIndex();
    
    usort($this->data, function ($a,$b) use($j) {
      $A = $a[$j];
      $B = $b[$j];
      if ($A == $B) return 0;
      if ($B === NULL) return -1;
      if ($A === NULL) return 1;
      return ($A < $B) ? -1 : 1;
    });
    // echo $this->toString();
  }

  /**
   * Randomize the order of the instances
   */
  function randomize()
  {
    echo "[ Instances->randomize() ]" . PHP_EOL;

    // echo $this->toString();
    shuffle($this->data);
    // echo $this->toString();
  }

  /**
   * Sort the classes of the attribute to predict by frequency
   */
  function resortClassesByCount() {
    // echo "Instances->resortClassesByCount()" . PHP_EOL;
    $classes = $this->getClassAttribute()->getDomain();

    $class_counts =  array_fill(0,count($classes),0);
    for ($x = 0; $x < $this->numInstances(); $x++) {
      $class_counts[$this->inst_classValue($x)]++;
    }

    // echo $this->toString();

    $indices = range(0, count($classes) - 1);
    // echo get_var_dump($classes);
    
    array_multisort($class_counts, SORT_ASC, $classes, $indices);
    $class_map = array_flip($indices);

    // echo get_var_dump($classes);
    // echo get_var_dump($indices);
    // echo get_var_dump($class_map);

    for ($x = 0; $x < $this->numInstances(); $x++) {
      $cl = $this->inst_classValue($x);
      $this->inst_setClassValue($x, $class_map[$cl]);
    }

    // echo $this->toString();

    return $class_counts;
  }

  /**
   * Number of unique values appearing in the data, for an attribute.
   */
  function numDistinctValues(_Attribute $attr) : int {
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
   * Save data to file, (dense) ARFF/Weka format
   */
  function save_ARFF(string $path) {
    echo "Instances->save_ARFF($path)" . PHP_EOL;
    $f = fopen($path, "w");
    fwrite($f, "% Generated with \"" . PACKAGE_NAME . "\"\n");
    fwrite($f, "\n");
    fwrite($f, "@RELATION " . basename($path) . "\n\n");

    /* Attributes */
    foreach($this->getAttributes() as $attr) {
      fwrite($f, "@ATTRIBUTE {$attr->getName()} {$attr->getARFFType()}");
      fwrite($f, "\n");
    }
    
    /* Print the ARFF representation of a value of the attribute */
    $getARFFRepr = function($val, _Attribute $attr)
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
   * Print a textual representation of the instances
   */
  function toString(bool $short = false) : string {
    $out_str = "";
    if ($short) {
      $atts_str = [];
      foreach ($this->getAttributes() as $att) {
        $atts_str[] = substr($att->toString(), 0, 7);
      }
      $out_str .= "Data{{$this->numInstances()}} instances; [" . join(",", $atts_str) . "]}";
    } else {
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", $this->numAttributes()+1) . "|\n";
      $out_str .= "";
      foreach ($this->getAttributes() as $att) {
        $out_str .= substr($att->toString(), 0, 7) . "\t";
      }
      $out_str .= "weight";
      $out_str .= "\n";
      $out_str .= str_repeat("======|=", $this->numAttributes()+1) . "|\n";
      foreach ($this->data as $k => $inst) {
        foreach ($this->getInstance($k) as $val) {
          if ($val === NULL) {
            $x = "N/A";
          }
          else {
            $x = "{$val}";
          }
          $out_str .= str_pad($x, 7, " ", STR_PAD_BOTH) . "\t";
        }
        $out_str .= "{" . $this->inst_weight($k) . "}";
        $out_str .= "\n";
      }
      $out_str .= str_repeat("======|=", $this->numAttributes()+1) . "|\n";
    }
    return $out_str;
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

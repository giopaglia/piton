<?php

/**
 * A set of data instances. Essentially, a table with metadata.
 * Each instance has values for the same number of attributes.
 * We assume that the last attribute is the one to predict
 */
class Instances {
  /** Metadata for the attributes */
  private $attributes;
  
  /** The data table itself */
  private $data;

  /** The weights for the data instances */
  // private $weights;
  // function getWeights() { return $this->weights; }
  // function setWeights($w) { $this->weights = $w; }

  function __construct($attributes, $data, $weights = NULL) {
    $this->attributes = $attributes;
    foreach ($data as $k => &$inst) {
      $inst[] = ($weights === NULL ? 1 : $weights[$k]);
    }
    $this->data = $data;
  }

  static function createFromSlice(Instances &$data, int $offset, int $length = NULL) {
    return new Instances($data->getAttrs(), array_slice($data->getData(), $offset, $length));
  }

  static function createEmpty(Instances &$data) {
    return new Instances($data->getAttrs(), []);
  }

  function numAttributes() { return count($this->getAttrs()); }
  function numInstances() { return count($this->data); }
  function getInstance($i) { return array_slice($this->data[$i], 0, -1); }
  function pushInstance($inst, $weight = 1)
  {
    $inst[]       = $weight;
    $this->data[] = $inst;
  }
  
  function dropAttr($j) {
    array_splice($this->attributes, $j, $j+1);
    foreach ($this->data as &$inst) {
      array_splice($inst, $j, $j+1);
    }
  }
  function dropOutputAttr() {
    $this->dropAttr(0);
  }

  /* Remove instances with missing values for the output column */
  function removeUselessInsts() {
    for ($x = $this->numInstances() - 1; $x >= 0; $x--) {
      if ($this->inst_classValue($x) === NULL) {
        array_splice($this->data,    $x, $x+1);
      }
    }
  }

  function sumOfWeights() {
    echo "Instances->sumOfWeights()" . PHP_EOL;
    $sum = 0;
    for ($x = 0; $x < $this->numInstances(); $x++) {
      $sum += $this->inst_weight($x);
    }
    echo "\$sum : $sum" . PHP_EOL;
    return $sum;
  }

  function sortByAttr($attr)
  {
    echo "Instances->sortByAttr(" . get_var_dump($attr) . ")" . PHP_EOL;

    echo $this->toString();
    echo " => ";
    $j = $this->getAttrIdx($attr);
    
    usort($this->data, function ($a,$b) use($j) {
      $A = $a[$j];
      $B = $b[$j];
      if ($A == $B) return 0;
      if ($B === NULL) return -1;
      if ($A === NULL) return 1;
      return ($A < $B) ? -1 : 1;
    });
    echo $this->toString();
  }

  function randomize()
  {
    echo "Instances->randomize()" . PHP_EOL;

    echo $this->toString();
    shuffle($this->data);
    echo $this->toString();
  }

  /*
   * Functions for single data instances
   */
  
  function inst_valueOfAttr($i, $attr) {
    // TODO maybe at some point this won't be necessary, and I'll directly use attr indices?
    $j = $this->getAttrIdx($attr);
    return $this->inst_val($i, $j);
  }

  function inst_isMissing($i, $attr) {
    return ($this->inst_valueOfAttr($i, $attr) === NULL);
  }
  
  function inst_weight($i) {
    $inst = $this->data[$i];
    return $inst[array_key_last($inst)];
  }
  
  function inst_val($i, $j) {
    return $this->data[$i][$j];
  }

  function inst_classValue($i) {
    // Note: assuming the class attribute is the first
    return $this->inst_val($i, 0);
  }

  function inst_setClassValue($i, $cl) {
    $inst = &$this->data[$i];
    // Note: assuming the class attribute is the first
    $inst[0] = $cl;
  }

  function getAttrIdx($attr) {
    return array_search($attr, $this->getAttrs());
  }

  function getClassAttribute() {
    // Note: assuming the class attribute is the first
    return $this->getAttrs()[0];
  }

  function numClasses() {
    return $this->getClassAttribute()->numValues();
  }

  function sortClassesByCount() {
    echo "Instances->sortClassesByCount()" . PHP_EOL;
    $classes = $this->getClassAttribute()->getDomain();

    $class_counts =  array_fill(0,count($classes),0);
    for ($x = 0; $x < $this->numInstances(); $x++) {
      $class_counts[$this->inst_classValue($x)]++;
    }

    echo $this->toString();

    $indices = range(0, count($classes) - 1);
    // echo get_var_dump($classes);
    
    // TODO check that this approach works with many classes
    array_multisort($class_counts, SORT_DESC, $classes, $indices);
    $class_map = array_flip($indices);

    // echo get_var_dump($classes);
    // echo get_var_dump($indices);
    // echo get_var_dump($class_map);

    for ($x = 0; $x < $this->numInstances(); $x++) {
      $cl = $this->inst_classValue($x);
      $this->inst_setClassValue($x, $class_map[$cl]);
    }

    echo $this->toString();

    return $class_counts;
  }

  function numDistinctValues($attr) {
    echo "Instances->numDistinctValues(" . get_var_dump($attr) . ")" . PHP_EOL;
    $j = $this->getAttrIdx($attr);
    $valCounts = [];
    for ($x = 0; $x < $this->numInstances(); $x++) {
      $val = $this->inst_val($x, $j);
      if (! isset($valCounts[$val])) {
        $valCounts[$val] = 1;
      }
    }
    // echo "count : " . count($valCounts) .
    " (" . get_var_dump($valCounts) . ")" . PHP_EOL;
    return count($valCounts);
  }

  /** Save data to file, (dense) ARFF/Weka format */
  function save_ARFF($path) {
    echo "Instances->save_ARFF($path)" . PHP_EOL;
    $f = fopen($path, "w");
    fwrite($f, "% Generated with \"" . PACKAGE_NAME . "\"\n");
    fwrite($f, "\n");
    fwrite($f, "@RELATION " . basename($path) . "\n\n");

    /* Attributes */
    foreach($this->getAttrs() as $attr) {
      fwrite($f, "@ATTRIBUTE {$attr->getName()} {$attr->getARFFType()}");
      fwrite($f, "\n");
    }
    
    /* Print the ARFF representation of a value of the attribute */
    $getARFFRepr = function($val, $attr)
    {
      return $val === NULL ? "?" : $attr->reprVal($val);
    };
    
    /* Data */
    fwrite($f, "\n@DATA\n");
    foreach ($this->data as $k => $inst) {
      fwrite($f, join(",", array_map($getARFFRepr, $this->getInstance($k), $this->getAttrs())) . ", {" . $this->inst_weight($k) . "}\n");
    }

    fclose($f);
  }

  /**
   * Print a textual representation of the instances
   */
  function toString() {
    $out_str = "";
    foreach ($this->getAttrs() as $att) {
      $out_str .= substr($att->toString(), 0, 7) . "\t";
    }
    $out_str .= "\n";
    foreach ($this->data as $k => $inst) {
      foreach ($this->getInstance($k) as $val) {
        if ($val === NULL) {
          $out_str .= "N/A\t";
        }
        else {
          $out_str .= "{$val}\t";
        }
      }
      $out_str .= "{" . $this->inst_weight($k) . "}";
      $out_str .= "\n";
    }
    return $out_str;
  }

  /**
   * @return mixed
   */
  public function getAttrs($includeClassAttr = true)
  {
    // Note: assuming the class attribute is the first
    return $includeClassAttr ? $this->attributes : array_slice($this->attributes, 1);
  }

  /**
   * @param mixed $attributes
   *
   * @return self
   */
  public function setAttrs($attributes)
  {
    $this->attributes = $attributes;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   * @param mixed $data
   *
   * @return self
   */
  public function setData($data)
  {
    $this->data = $data;

    return $this;
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
  function weight($att) { return 1; } // TODO extend so that we can have different weights
  function classValue() { ... 
 */
?>

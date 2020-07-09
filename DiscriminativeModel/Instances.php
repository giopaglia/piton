<?php

/**
 * A set of data instances. Essentially, a table with metadata.
 * Each instance has values for the same number of attributes.
 * We assume that the last attribute is the one to predict
 */
class Instances {
  /** Metadata for the attributes */
  private $attributes;
  function getAttrs() { return $this->attributes; }
  function setAttrs($a) { $this->attributes = $a; }

  /** The data table itself */
  private $data;
  function getData() { return $this->data; }
  function setData($d) { $this->data = $d; }

  /** The weights for the data instances */
  private $weights;
  function getWeights() { return $this->weights; }
  function setWeights($w) { $this->weights = $w; }

  function __construct($attributes, $data, $weights = NULL) {
    $this->attributes = $attributes;
    $this->data       = $data;
    if ($weights == NULL) {
      $weights = array_fill(0, count($data), 1);
    }
    $this->weights    = $weights;
  }

  function numAttributes() { return count($this->attributes); }
  function numInstances() { return count($this->data); }
  function getInstance($i) { return $this->data[$i]; }
  function pushInstance($inst, $weight = 1)
  {
    $this->data[]    = $inst;
    $this->weights[] = $weight;
  }
  
  function dropAttr($j) {
    unset($this->attributes[$j]);
    foreach ($this->data as $i => $row) {
      unset($this->data[$i][$j]);
    }
  }
  function dropOutputAttr() {
    $this->dropAttr(0);
  }

  /* Remove instances with missing values for the output column */
  function removeUselessInsts() {
    for ($x = $this->numInstances() - 1; $x >= 0; $x--) {
      if ($this->inst_classValue($x) === NULL) {
        unset($this->weights[$x]);
        unset($this->data[$x]);
      }
    }
  }

  function sortByAttr($attr)
  {
    echo "Instances->sortByAttr(" . get_var_dump($attr) . ")" . PHP_EOL;

    echo $this->toString($this->data);
    echo " => ";
    $j = array_search($attr, $this->attributes);
    
    usort($this->data, function ($a,$b) use($j) {
      $A = $a[$j];
      $B = $b[$j];
      if ($A == $B) return 0;
      if ($B == NULL) return -1;
      if ($A == NULL) return 1;
      return ($A < $B) ? -1 : 1;
    });
    echo $this->toString($this->data);
  }

  /*
   * Functions for single data instances
   */
  
  function inst_valueOfAttr($i, $attr) {
    // TODO maybe at some point this won't be necessary, and I'll directly use attr indices?
    $j = array_search($attr, $this->attributes);
    return $this->data[$i][$j];
  }

  function inst_isMissing($i, $attr) {
    // TODO maybe at some point this won't be necessary, and I'll directly use attr indices?
    $j = array_search($attr, $this->attributes);
    return ($this->data[$i][$j] === NULL);
  }
  
  function inst_weight($i) { return $this->weights[$i]; }

  function inst_classValue($i) {
    $row = $this->data[$i];
    return $row[0];
  }

  /**
   * Print a textual representation of the instances
   */
  function toString() {
    $out_str = "";
    foreach ($this->attributes as $att) {
      $out_str .= $att->toString() . "\t";
    }
    $out_str .= "\n";
    foreach ($this->data as $row) {
      foreach ($row as $val) {
        if ($val === NULL) {
          $out_str .= "N/A\t";
        }
        else {
          $out_str .= $val . "\t";
        }
      }
      $out_str .= "\n";
    }
    return $out_str;
  }

}

//$instance->valueOfAttr($attribute)
//$instance->isMissing($attribute)
//$instance->weight() // I guess this is 1?
//$instance->classValue() // Value of the attribute to predict


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

  function valueOfAttr($att) {  }
  function isMissing($att) {  }
  function weight($att) { return 1; } // TODO extend so that we can have different weights
  function classValue() { ... }

//$instance->valueOfAttr($attribute)
//$instance->isMissing($attribute)
//$instance->weight() // I guess this is 1?
//$instance->classValue() // Value of the attribute to predict

}
*/


?>

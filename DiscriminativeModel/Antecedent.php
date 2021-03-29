<?php

include "Attribute.php";
include "Instances.php";

/**
 * A single antecedent in the rule, composed of an attribute and a value for it.
 */
abstract class _Antecedent {
  /** The attribute of the antecedent */
  protected $attribute;
  // protected $attributeIndex;
  
  /**
  * The attribute value of the antecedent. For numeric attribute, it represents the operator (<= or >=)
  */
  protected $value;
  
  /**
  * The maximum infoGain achieved by this antecedent test in the growing data
  */
  protected $maxInfoGaitn;
  
  /** The accurate rate of this antecedent test on the growing data */
  protected $accuRate;
  
  /** The coverage of this antecedent in the growing data */
  protected $cover;
  
  /** The accurate data for this antecedent in the growing data */
  protected $accu;


  /**
   * Constructor
   */
  function __construct(Attribute $attribute) {    
    $this->attribute   = $attribute;
    // $this->attributeIndex   = $attribute->getIndex();
    $this->value       = NAN;
    $this->maxInfoGain = 0;
    $this->accuRate    = NAN;
    $this->cover       = NAN;
    $this->accu        = NAN;
  }

  static function createFromAttribute(Attribute $attribute) : _Antecedent {
    /* if ($attribute->getIndex() === NULL) {
      die_error("Attribute $attribute of antecedent not indexed." . PHP_EOL);
    } */
    switch (true) {
      case $attribute instanceof DiscreteAttribute:
        $antecedent = new DiscreteAntecedent($attribute);
        break;
      case $attribute instanceof ContinuousAttribute:
        $antecedent = new ContinuousAntecedent($attribute);
        break;
      default:
        die_error("Unknown type of attribute encountered! " . get_class($attribute));
        break;
    }
    return $antecedent;
  }

  static function fromString(string $str, ?array $attrs_map = NULL) : _Antecedent {
    switch (true) {
      case preg_match("/^\s*\(?\s*(.*(?:\S))\s+(!=|=)\s+(.*(?:[^\s\)]))\s*\)?\s*$/", $str):
        $antecedent = DiscreteAntecedent::fromString($str, $attrs_map);
        break;
      case preg_match("/^\s*\(?\s*(.*(?:\S))\s*(>=|<=)\s*(.*(?:[^\s\)]))\s*\)?\s*$/", $str):
        $antecedent = ContinuousAntecedent::fromString($str, $attrs_map);
        break;
      default:
        die_error("Invalid antecedent string encountered: " . PHP_EOL . $str);
        break;
    }
    return $antecedent;
  }

  /**
   * Functions for the single data instance
   */
  
  // function inst_valueOfAttr(Instances $data, int $i) {
  //   // return $data->inst_val($i, $this->attributeIndex);
  //   // TODO find a solution to this!
  //   return $data->inst_val($i, $this->attribute->getIndex());
  // }

  /* The abstract members for inheritance */
  abstract function splitData(Instances &$data, float $defAcRt, int $cla) : ?array;

  abstract function covers(Instances &$data, int $i) : bool;

  /* Print a textual representation of the antecedent */
  function __toString() : string {
    return $this->toString();
  }
  abstract function toString() : string;


  /**
   * Print a serialized representation of the antecedent
   */
  abstract function serialize() : string;

  function __clone()
  {
    $this->attribute = clone $this->attribute;
  }

  function getAttribute() : Attribute
  {
    return $this->attribute;
  }

  function getValue()
  {
    return $this->value;
  }

  function getMaxInfoGain() : float
  {
    return $this->maxInfoGain;
  }

  function getAccuRate() : float
  {
    return $this->accuRate;
  }

  function getCover() : float
  {
    return $this->cover;
  }

  function getAccu() : float
  {
    return $this->accu;
  }
}

/**
 * An antecedent with discrete attribute
 */
class DiscreteAntecedent extends _Antecedent {

  protected $sign;

  /**
   * Constructor
   */
  function __construct(Attribute $attribute) {
    if(!($attribute instanceof DiscreteAttribute))
      die_error("DiscreteAntecedent requires a DiscreteAttribute. Got "
      . get_class($attribute) . " instead.");
    parent::__construct($attribute);
  }

  /**
   * Splits the data into bags according to the nominal attribute value.
   * The infoGain for each bag is also calculated.
   * 
   * @param data the data to be split
   * @param defAcRt the default accuracy rate for data
   * @param cl the class label to be predicted
   * @return the array of data after split
   *
   * NOTE: JRip rules only allow sign=0
   */
  function splitData(Instances &$data, float $defAcRt, int $cla) : ?array {
    if (DEBUGMODE > 2) {
      echo "DiscreteAntecedent->splitData(&[data], defAcRt=$defAcRt, cla=$cla)" . PHP_EOL;
      if (DEBUGMODE & DEBUGMODE_DATA) {
        echo $data->toString() . PHP_EOL;
      }
    }
    $bag = $this->attribute->numValues();

    $splitData = [];
    for ($i = 0; $i < $bag; $i++) {
      $splitData[] = Instances::createEmpty($data);
    }
    $accurate  = array_fill(0,$bag,0);
    $coverage  = array_fill(0,$bag,0);

    $index = $this->attribute->getIndex();

    /* Split data */
    foreach ($data->iterateInsts() as $instance_id => $inst) {
      // $val = $this->inst_valueOfAttr($data, $x);
      $val = $data->inst_val($instance_id, $index);
      if ($val !== NULL) {
        $splitData[$val]->pushInstanceFrom($data, $instance_id);
        $w = $data->inst_weight($instance_id);
        $coverage[$val] += $w;
        if ($data->inst_classValue($instance_id) == $cla) {
          $accurate[$val] += $w;
        }
      }
    }

    /* Compute goodness and find best bag */
    for ($x = 0; $x < $bag; $x++) {
      $t = $coverage[$x] + 1.0;
      $p = $accurate[$x] + 1.0;
      $infoGain =
      // Utils.eq(defAcRt, 1.0) ?
      // accurate[x]/(double)numConds :
        $accurate[$x] * (log($p / $t, 2) - log($defAcRt, 2));

      if ($infoGain > $this->maxInfoGain) {
        $this->value       = $x;
        $this->maxInfoGain = $infoGain;
        $this->accuRate    = $p / $t;
        $this->cover       = $coverage[$x];
        $this->accu        = $accurate[$x];
      }
    }

    if (DEBUGMODE & DEBUGMODE_DATA) {
      foreach ($splitData as $k => $s) {
        echo "splitData[$k] : \n" . $splitData[$k]->toString() . PHP_EOL;
      }
    }
    // NOTE: JRip rules only allow sign=0
    $this->sign = 0;
    return $splitData;
  }

  /**
   * Whether the instance is covered by this antecedent
   * 
   * @param data the set of instances
   * @param i the index of the instance in question
   * @return the boolean value indicating whether the instance is covered by
   *         this antecedent
   */
  function covers(Instances &$data, int $instance_id) : bool {
    $isCover = false;

    // $val = $this->inst_valueOfAttr($data, $instance_id);
    $index = $this->attribute->getIndex();
    $val = $data->inst_val($instance_id, $index);

    if ($val !== NULL) {
      if (! ( $val == $this->value xor ($this->sign == 0) )) {
        $isCover = true;
      }
    }
    return $isCover;
  }

  static function fromString(string $str, ?array $attrs_map = NULL) : DiscreteAntecedent {
    if (DEBUGMODE > 2)
      echo "DiscreteAntecedent->fromString($str)" . PHP_EOL;
    
    if (!preg_match("/^\s*\(?\s*(.*(?:\S))\s+(!=|=)\s+(.*(?:[^\s\)]))\s*\)?\s*$/", $str, $w)) {
      die_error("Couldn't parse DiscreteAntecedent string \"$str\".");
    }
    if (DEBUGMODE > 2)
      echo "w: " . get_var_dump($w) . PHP_EOL;

    $name = $w[1];
    $sign = $w[2];
    $reprvalue = $w[3];
    
    if (DEBUGMODE > 2)
      echo "name: " . get_var_dump($name) . PHP_EOL;
    if (DEBUGMODE > 2)
      echo "reprvalue: " . get_var_dump($reprvalue) . PHP_EOL;

    $attribute = new DiscreteAttribute($name, "parsed", [strval($reprvalue)]);
    if($attrs_map !== NULL) {
      $attribute->setIndex($attrs_map[$name]);
    }
    $ant = _Antecedent::createFromAttribute($attribute);
    $ant->sign = ($sign == "=" ? 0 : 1);
    $ant->value = 0;
    
    if (DEBUGMODE > 2)
      echo $ant->toString(true);
    
    return $ant;
  }

  /**
   * Print a textual representation of the antecedent
   */
  function toString(bool $short = false) : string {
    if ($short) {
      return "{$this->attribute->getName()}" . ($this->sign == 0 ? " = " : " != ") . "{$this->attribute->reprVal($this->value)}";
    }
    else {
      return "DiscreteAntecedent: ({$this->attribute->getName()}" . ($this->sign == 0 ? " == " : " != ") . "\"{$this->attribute->reprVal($this->value)}\") (maxInfoGain={$this->maxInfoGain}, accuRate={$this->accuRate}, cover={$this->cover}, accu={$this->accu})";
    }
  }

  /**
   * Print a serialized representation of the antecedent
   */
  function serialize() : string {
    return "{$this->attribute->getName()} == '{$this->attribute->reprVal($this->value)}'";
  }
}



/**
 * An antecedent with continuous attribute
 */
class ContinuousAntecedent extends _Antecedent {

  /** The split point for this numeric antecedent */
  private $splitPoint;

  /**
   * Constructor
   */
  function __construct(Attribute $attribute) {
    if(!($attribute instanceof ContinuousAttribute))
      die_error("ContinuousAntecedent requires a ContinuousAttribute. Got "
      . get_class($attribute) . " instead.");
    parent::__construct($attribute);
    $this->splitPoint  = NAN;
  }

  /**
   * Splits the data into two bags according to the
   * information gain of the numeric attribute value.
   * The infoGain for each bag is also calculated.
   * 
   * @param data the data to be split
   * @param defAcRt the default accuracy rate for data
   * @param cl the class label to be predicted
   * @return the array of data after split
   */
  function splitData(Instances &$data, float $defAcRt, int $cla) : ?array {
    if (DEBUGMODE > 2) {
      echo "ContinuousAntecedent->splitData(&[data], defAcRt=$defAcRt, cla=$cla)" . PHP_EOL;
      if (DEBUGMODE & DEBUGMODE_DATA) {
        echo $data->toString() . PHP_EOL;
      }
    }
    
    $split = 1; // Current split position
    $prev  = 0; // Previous split position
    $finalSplit = $split; // Final split position
    $this->maxInfoGain = 0;
    $this->value = 0;

    $fstCover = 0;
    $sndCover = 0;
    $fstAccu = 0;
    $sndAccu = 0;

    $data->sortByAttr($this->attribute);
    $instance_ids = $data->getIds();
    
    // Total number of instances without missing value for att
    $total = $data->numInstances();
    $index = $this->attribute->getIndex();
    
    // Find the last instance without missing value
    $i = 0;
    foreach ($data->iterateInsts() as $instance_id => $inst) {
      // if ($this->inst_valueOfAttr($data, $x) === NULL) {
      if ($data->inst_val($instance_id, $index) === NULL) {
        $total = $i;
        break;
      }

      $w = $data->inst_weight($instance_id);
      $sndCover += $w;
      if ($data->inst_classValue($instance_id) == $cla) {
        $sndAccu += $w;
      }
      $i++;
    }

    if ($total == 0) {
      return NULL; // Data all missing for the attribute
    }
    // $this->splitPoint = $this->inst_valueOfAttr($data, $total - 1);
    $this->splitPoint = $data->inst_val($instance_ids[$total-1], $index);

    // echo "splitPoint: " . $this->splitPoint . PHP_EOL;
    // echo "total: " . $total . PHP_EOL;

    for (; $split <= $total; $split++) {
      if (($split == $total) ||
        // Can't split within same value
          // ($this->inst_valueOfAttr($data, $split) >
          //  $this->inst_valueOfAttr($data, $prev))) {
          ($data->inst_val($instance_ids[$split], $index) >
           $data->inst_val($instance_ids[$prev], $index))) {

        for ($y = $prev; $y < $split; $y++) {
          $w = $data->inst_weight($instance_ids[$y]);
          $fstCover += $w;
          if ($data->inst_classValue($instance_ids[$y]) == $cla) {
            $fstAccu += $w; // First bag positive# ++
          }
        }

        $fstAccuRate = ($fstAccu + 1.0) / ($fstCover + 1.0);
        $sndAccuRate = ($sndAccu + 1.0) / ($sndCover + 1.0);

        // echo "fstAccuRate: " . $fstAccuRate . PHP_EOL;
        // echo "sndAccuRate: " . $sndAccuRate . PHP_EOL;

        /* Which bag has higher information gain? */
        $isFirst;
        $fstInfoGain; $sndInfoGain;
        $accRate; $infoGain; $coverage; $accurate;

        $fstInfoGain =
        // Utils.eq(defAcRt, 1.0) ?
        // fstAccu/(double)numConds :
        $fstAccu * (log($fstAccuRate, 2) - log($defAcRt, 2));

        $sndInfoGain =
        // Utils.eq(defAcRt, 1.0) ?
        // sndAccu/(double)numConds :
        $sndAccu * (log($sndAccuRate, 2) - log($defAcRt, 2));

        if ($fstInfoGain > $sndInfoGain) {
          $isFirst  = true;
          $infoGain = $fstInfoGain;
          $accRate  = $fstAccuRate;
          $accurate = $fstAccu;
          $coverage = $fstCover;
        } else {
          $isFirst  = false;
          $infoGain = $sndInfoGain;
          $accRate  = $sndAccuRate;
          $accurate = $sndAccu;
          $coverage = $sndCover;
        }

        /* Check whether so far the max infoGain */
        if ($infoGain > $this->maxInfoGain) {
          $this->value = ($isFirst) ? 0 : 1;
          $this->maxInfoGain = $infoGain;
          $this->accuRate = $accRate;
          $this->cover = $coverage;
          $this->accu = $accurate;
          // $this->splitPoint = $this->inst_valueOfAttr($data, $prev);
          $this->splitPoint = $data->inst_val($instance_ids[$prev], $index);
          $finalSplit = ($isFirst) ? $split : $prev;
        }

        // echo "value: "       . $this->value . PHP_EOL;
        // echo "maxInfoGain: " . $this->maxInfoGain . PHP_EOL;
        // echo "accuRate: "    . $this->accuRate . PHP_EOL;
        // echo "cover: "       . $this->cover . PHP_EOL;
        // echo "accu: "        . $this->accu . PHP_EOL;
        // echo "splitPoint: "  . $this->splitPoint . PHP_EOL;
        // echo "finalSplit: "  . $finalSplit . PHP_EOL;

        for ($y = $prev; $y < $split; $y++) {
          $w = $data->inst_weight($instance_ids[$y]);
          $sndCover -= $w;
          if ($data->inst_classValue($instance_ids[$y]) == $cla) {
            $sndAccu -= $w; // Second bag positive# --
          }
        }
        $prev = $split;
      }
    }

    /* Split the data */
    $splitData = [];
    $splitData[] = Instances::createFromSlice($data, 0, $finalSplit);
    $splitData[] = Instances::createFromSlice($data, $finalSplit, $total - $finalSplit);

    if (DEBUGMODE & DEBUGMODE_DATA) {
      foreach ($splitData as $k => $s) {
        echo "splitData[$k] : \n" . $splitData[$k]->toString() . PHP_EOL;
      }
    }
    return $splitData;
  }

  /**
   * Whether the instance is covered by this antecedent
   * 
   * @param data the set of instances
   * @param i the index of the instance in question
   * @return the boolean value indicating whether the instance is covered by
   *         this antecedent
   */
  function covers(Instances &$data, int $instance_id) : bool {
    $isCover = true;
    echo $this->attribute;
    $index = $this->attribute->getIndex();
    $val = $data->inst_val($instance_id, $index);
    // $val = $this->inst_valueOfAttr($data, $instance_id);
    // echo "covers" . PHP_EOL;
    // echo "[$instance_id]" . $data->inst_toString($instance_id) . PHP_EOL;
    // echo "VAL " . toString($val) . PHP_EOL;
    // echo "sign " . toString($this->value) . PHP_EOL;
    // echo "splitPoint " . toString($this->splitPoint) . PHP_EOL;
    // echo "this " . $this->toString(true) . PHP_EOL;
    if ($val !== NULL) {
      if ($this->value == 0) { // First bag
        if (!($val <= $this->splitPoint)) {
          $isCover = false;
        }
      } else if (!($val >= $this->splitPoint)) {
        $isCover = false;
      }
    } else {
      $isCover = false;
    }
    // echo "[$instance_id]" . $data->inst_toString($instance_id) . PHP_EOL; "antd doesn't cover: " . $this->toString()
    //       . "[$instance_id]" . $data->inst_toString($instance_id) . PHP_EOL;
    
    return $isCover;
  }

  static function fromString(string $str, ?array $attrs_map = NULL) : ContinuousAntecedent {
    if (DEBUGMODE > 2)
      echo "ContinuousAntecedent->fromString($str)" . PHP_EOL;
    
    if (!preg_match("/^\s*\(?\s*(.*(?:\S))\s*(>=|<=)\s*(.*(?:[^\s\)]))\s*\)?\s*$/", $str, $w)) {
      die_error("Couldn't parse ContinuousAntecedent string \"$str\".");
    }
    $name = $w[1];
    $sign = $w[2];
    $reprvalue = $w[3];
    
    if (DEBUGMODE > 2)
      echo "name: " . get_var_dump($name) . PHP_EOL;
    if (DEBUGMODE > 2)
      echo "sign: " . get_var_dump($sign) . PHP_EOL;
    if (DEBUGMODE > 2)
      echo "reprvalue: " . get_var_dump($reprvalue) . PHP_EOL;
    
    $attribute = new ContinuousAttribute($name, "parsed");
    if($attrs_map !== NULL) {
      $attribute->setIndex($attrs_map[$name]);
    }
    $ant = _Antecedent::createFromAttribute($attribute);

    /* if ($attrs_map !== NULL) {
      $attr_index = $attrs_map[$name];
      $ant->setIndex($attr_index);
    } */

    $ant->value = ($sign == "<=" ? 0 : 1);
    $ant->splitPoint = $reprvalue;
    if (DEBUGMODE > 2)
      echo $ant->toString(true);
    return $ant;
  }

  /**
   * Print a textual representation of the antecedent
   */
  function toString(bool $short = false) : string {
    if ($short) {
      return "{$this->attribute->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
        $this->splitPoint
        // number_format($this->splitPoint, 6)
        ;
    }
    else {
      return "ContinuousAntecedent: ({$this->attribute->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
        $this->splitPoint
        // number_format($this->splitPoint, 6)
        . ") (maxInfoGain={$this->maxInfoGain}, accuRate={$this->accuRate}, cover={$this->cover}, accu={$this->accu})";
    }
  }

  /**
   * Print a serialized representation of the antecedent
   */
  function serialize() : string {
    return "{$this->attribute->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
      $this->splitPoint
      // number_format($this->splitPoint, 6)
      ;
  }


  function getSplitPoint() : float
  {
    return $this->splitPoint;
  }

}




?>

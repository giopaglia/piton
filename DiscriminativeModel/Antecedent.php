<?php

include "Attribute.php";
include "Instances.php";

/**
* A single antecedent in the rule, composed of an attribute and a value for it.
*/
abstract class _Antecedent {
  /** The attribute of the antecedent */
  protected $attribute;
  
  /**
  * The attribute value of the antecedent. For numeric attribute, it represents the operator (<= or >=)
  */
  protected $value;
  
  /**
  * The maximum infoGain achieved by this antecedent test in the growing data
  */
  protected $maxInfoGain;
  
  /** The accurate rate of this antecedent test on the growing data */
  protected $accuRate;
  
  /** The coverage of this antecedent in the growing data */
  protected $cover;
  
  /** The accurate data for this antecedent in the growing data */
  protected $accu;


  /**
   * Constructor
   */
  function __construct(_Attribute $attribute) {
    $this->attribute   = $attribute;
    $this->value       = NAN;
    $this->maxInfoGain = 0;
    $this->accuRate    = NAN;
    $this->cover       = NAN;
    $this->accu        = NAN;
  }


  static function createFromAttribute(_Attribute $attribute) {
    switch (true) {
      case $attribute instanceof DiscreteAttribute:
        $antecedent = new DiscreteAntecedent($attribute);
        break;
      case $attribute instanceof ContinuousAttribute:
        $antecedent = new ContinuousAntecedent($attribute);
        break;
      default:
        die("ERROR: unknown type of attribute encountered!");
        break;
    }
    return $antecedent;
  }

  /* The abstract members for inheritance */
  abstract function splitData(&$data, $defAcRt, $cla);

  abstract function covers(&$data, $i);

  /* Print a textual representation of the antecedent */
  abstract function toString();

  function __clone()
  {
    $this->attribute = clone $this->attribute;
  }

  /**
   * @return mixed
   */
  public function getAttribute()
  {
      return $this->attribute;
  }

  /**
   * @param mixed $attribute
   *
   * @return self
   */
  public function setAttribute($attribute)
  {
      $this->attribute = $attribute;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getValue()
  {
      return $this->value;
  }

  /**
   * @param mixed $value
   *
   * @return self
   */
  public function setValue($value)
  {
      $this->value = $value;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getMaxInfoGain()
  {
      return $this->maxInfoGain;
  }

  /**
   * @param mixed $maxInfoGain
   *
   * @return self
   */
  public function setMaxInfoGain($maxInfoGain)
  {
      $this->maxInfoGain = $maxInfoGain;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getAccuRate()
  {
      return $this->accuRate;
  }

  /**
   * @param mixed $accuRate
   *
   * @return self
   */
  public function setAccuRate($accuRate)
  {
      $this->accuRate = $accuRate;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getCover()
  {
      return $this->cover;
  }

  /**
   * @param mixed $cover
   *
   * @return self
   */
  public function setCover($cover)
  {
      $this->cover = $cover;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getAccu()
  {
      return $this->accu;
  }

  /**
   * @param mixed $accu
   *
   * @return self
   */
  public function setAccu($accu)
  {
      $this->accu = $accu;

      return $this;
  }
}

/**
 * An antecedent with discrete attribute
 */
class DiscreteAntecedent extends _Antecedent {

  /**
   * Constructor
   */
  function __construct(_Attribute $attribute) {
    assert($attribute instanceof DiscreteAttribute, "DiscreteAntecedent requires a DiscreteAttribute. Got " . get_class($attribute) . " instead.");
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
   */
  function splitData(&$data, $defAcRt, $cla) {
    echo "DiscreteAntecedent->splitData(&[data], defAcRt=$defAcRt, cla=$cla)" . PHP_EOL;
    echo $data->toString() . PHP_EOL;

    $bag = $this->attribute->numValues();

    $splitData = [];
    for ($i = 0; $i < $bag; $i++) {
      $splitData[] = Instances::createEmpty($data);
    }
    $accurate  = array_fill(0,$bag,0);
    $coverage  = array_fill(0,$bag,0);

    /* Split data */
    for ($x = 0; $x < $data->numInstances(); $x++) {
      if (!$data->inst_isMissing($x, $this->attribute)) {
        $v = $data->inst_valueOfAttr($x, $this->attribute);
        $splitData[$v]->pushInstance($data->getInstance($x));
        $coverage[$v] += $data->inst_weight($x);
        if ($data->inst_classValue($x) == $cla) {
          $accurate[$v] += $data->inst_weight($x);
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

    foreach ($splitData as $k => $s) {
      echo "splitData[$k] : \n" . $splitData[$k]->toString() . PHP_EOL;
    }
    return $splitData;
  }

  /**
   * Whether the instance is covered by this antecedent
   * 
   * @param inst the instance in question
   * @return the boolean value indicating whether the instance is covered by
   *         this antecedent
   */
  function covers(&$data, $i) {
    $isCover = false;
    if (!$data->inst_isMissing($i, $this->attribute)) {
      if ($data->inst_valueOfAttr($i, $this->attribute) == $this->value) {
        $isCover = true;
      }
    }
    return $isCover;
  }

  /**
   * Print a textual representation of the antecedent
   */
  function toString($short = false) {
    if ($short) {
      return "{$this->attribute->getName()} == \"{$this->attribute->getDomain()[$this->value]}\"";
    }
    else {
      return "DiscreteAntecedent: ({$this->attribute->getName()} == \"{$this->attribute->getDomain()[$this->value]}\") (maxInfoGain={$this->maxInfoGain}, accuRate={$this->accuRate}, cover={$this->cover}, accu={$this->accu})";
    }
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
  function __construct(_Attribute $attribute) {
    assert($attribute instanceof ContinuousAttribute, "ContinuousAntecedent requires a ContinuousAttribute. Got " . get_class($attribute) . " instead.");
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
  function splitData(&$data, $defAcRt, $cla) {
    echo "ContinuousAntecedent->splitData(&[data], defAcRt=$defAcRt, cla=$cla)" . PHP_EOL;
    echo $data->toString() . PHP_EOL;

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

    // Total number of instances without missing value for att
    $total = $data->numInstances();
    // Find the last instance without missing value
    for ($x = 0; $x < $data->numInstances(); $x++) {
      if ($data->inst_isMissing($x, $this->attribute)) {
        $total = $x;
        break;
      }

      $sndCover += $data->inst_weight($x);
      if ($data->inst_classValue($x) == $cla) {
        $sndAccu += $data->inst_weight($x);
      }
    }

    if ($total == 0) {
      return NULL; // Data all missing for the attribute
    }
    $this->splitPoint = $data->inst_valueOfAttr($total - 1, $this->attribute);
    
    // echo "splitPoint: " . $this->splitPoint . PHP_EOL;
    // echo "total: " . $total . PHP_EOL;

    for (; $split <= $total; $split++) {
      if (($split == $total) ||
          ($data->inst_valueOfAttr($split, $this->attribute) > // Can't split within
           $data->inst_valueOfAttr($prev, $this->attribute))) { // same value

        for ($y = $prev; $y < $split; $y++) {
          $fstCover += $data->inst_weight($y);
          if ($data->inst_classValue($y) == $cla) {
            $fstAccu += $data->inst_weight($y); // First bag positive# ++
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
          $this->splitPoint = $data->inst_valueOfAttr($prev, $this->attribute);
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
          $sndCover -= $data->inst_weight($y);
          if ($data->inst_classValue($y) == $cla) {
            $sndAccu -= $data->inst_weight($y); // Second bag positive# --
          }
        }
        $prev = $split;
      }
    }

    /* Split the data */
    $splitData = [];
    $splitData[] = Instances::createFromSlice($data, 0, $finalSplit);
    $splitData[] = Instances::createFromSlice($data, $finalSplit, $total - $finalSplit);

    foreach ($splitData as $k => $s) {
      echo "splitData[$k] : \n" . $splitData[$k]->toString() . PHP_EOL;
    }
    return $splitData;
  }

  /**
   * Whether the instance is covered by this antecedent
   * 
   * @param inst the instance in question
   * @return the boolean value indicating whether the instance is covered by
   *         this antecedent
   */
  function covers(&$data, $i) {
    $isCover = true;
    if (!$data->inst_isMissing($i, $this->attribute)) {
      if ($this->value == 0) { // First bag
        if ($data->inst_valueOfAttr($i, $this->attribute) > $this->splitPoint) {
          $isCover = false;
        }
      } else if ($data->inst_valueOfAttr($i, $this->attribute) < $this->splitPoint) {
        $isCover = false;
      }
    } else {
      $isCover = false;
    }
    return $isCover;
  }

  /**
   * Print a textual representation of the antecedent
   */
  function toString($short = false) {
    if ($short) {
      return "{$this->attribute->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
        // number_format($this->splitPoint, 6)
        number_format($this->splitPoint)
        ;
    }
    else {
      return "ContinuousAntecedent: ({$this->attribute->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
        // number_format($this->splitPoint, 6)
        number_format($this->splitPoint)
        . ") (maxInfoGain={$this->maxInfoGain}, accuRate={$this->accuRate}, cover={$this->cover}, accu={$this->accu})";
    }
  }

  /**
   * @return mixed
   */
  public function getSplitPoint()
  {
      return $this->splitPoint;
  }

  /**
   * @param mixed $splitPoint
   *
   * @return self
   */
  public function setSplitPoint($splitPoint)
  {
      $this->splitPoint = $splitPoint;

      return $this;
  }
}




?>
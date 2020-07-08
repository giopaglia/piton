<?php

/**
 * An antecedent with discrete attribute
 */
class DiscreteAntecedent implements Antecedent {

  /** The attribute of the antecedent */
  private $att;
  function getAtt() { return $this->att; }
  function setAtt($a) { $this->att = $a; }

  /**
  * The attribute value of the antecedent. For numeric attribute, it represents the operator (<= or >=)
  */
  private $value;
  function getValue() { return $this->value; }
  function setValue($v) { $this->value = $v; }

  /**
  * The maximum infoGain achieved by this antecedent test in the growing data
  */
  private $maxInfoGain;
  function getMaxInfoGain() { return $this->maxInfoGain; }
  function setMaxInfoGain($m) { $this->maxInfoGain = $m; }

  /** The accurate rate of this antecedent test on the growing data */
  private $accuRate;
  function getAccuRate() { return $this->accuRate; }
  function setAccuRate($a) { $this->accuRate = $a; }

  /** The coverage of this antecedent in the growing data */
  private $cover;
  function getCover() { return $this->cover; }
  function setCover($c) { $this->cover = $c; }

  /** The accurate data for this antecedent in the growing data */
  private $accu;
  function getAccu() { return $this->accu; }
  function setAccu($a) { $this->accu = $a; }

  /**
   * Constructor
   */
  function __construct($attribute) {
    assert($attribute instanceof DiscreteAttribute, "DiscreteAntecedent requires a DiscreteAttribute. Got " . get_class($attribute) . " instead.");
    $this->att         = $attribute;
    $this->value       = NAN;
    $this->maxInfoGain = 0;
    $this->accuRate    = NAN;
    $this->cover       = NAN;
    $this->accu        = NAN;
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
  function splitData($data, $defAcRt, $cla) {

    $bag = $this->att->numValues();

    $splitData = [];
    for ($i = 0; $i < $bag; $i++) {
      $splitData[] = new Instances($data->getAttrs(), []);
    }
    $accurate  = array_fill(0,$bag,0);
    $coverage  = array_fill(0,$bag,0);

    /* Split data */
    for ($x = 0; $x < $data->numInstances(); $x++) {
      if (!$data->inst_isMissing($x, $this->att)) {
        $v = $data->inst_valueOfAttr($x, $this->att);
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
    if (!$data->inst_isMissing($i, $this->att)) {
      if ($data->inst_valueOfAttr($i, $this->att) == $this->value) {
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
      return "{$this->att->getName()} == \"{$this->att->getDomain()[$this->value]}\"";
    }
    else {
      return "DiscreteAntecedent: ({$this->att->getName()} == \"{$this->att->getDomain()[$this->value]}\") (maxInfoGain={$this->maxInfoGain}, accuRate={$this->accuRate}, cover={$this->cover}, accu={$this->accu})" . PHP_EOL;
    }
  }
}

?>
<?php

/**
 * An antecedent with continuous attribute
 */
class ContinuousAntecedent implements Antecedent {

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

  /** The split point for this numeric antecedent */
  private $splitPoint;

  /**
   * Constructor
   */
  function __construct($attribute) {
    assert($attribute instanceof ContinuousAttribute, "ContinuousAntecedent requires a ContinuousAttribute. Got " . get_class($attribute) . " instead.");
    $this->att         = $attribute;
    $this->value       = NAN;
    $this->maxInfoGain = 0;
    $this->accuRate    = NAN;
    $this->cover       = NAN;
    $this->accu        = NAN;
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
  function splitData($data, $defAcRt, $cla) {
    $split = 1; // Current split position
    $prev  = 0; // Previous split position
    $finalSplit = $split; // Final split position
    $this->maxInfoGain = 0;
    $this->value = 0;

    $fstCover = 0;
    $sndCover = 0;
    $fstAccu = 0;
    $sndAccu = 0;

    $data->sortByAttr($this->att);

    // Total number of instances without missing value for att
    $total = $data->numInstances();
    // Find the last instance without missing value
    for ($x = 0; $x < $data->numInstances(); $x++) {
      if ($data->inst_isMissing($x, $this->att)) {
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
    $this->splitPoint = $data->inst_valueOfAttr($total - 1, $this->att);
    
    // echo "splitPoint: " . $this->splitPoint . PHP_EOL;
    // echo "total: " . $total . PHP_EOL;

    for (; $split <= $total; $split++) {
      if (($split == $total) ||
          ($data->inst_valueOfAttr($split, $this->att) > // Can't split within
           $data->inst_valueOfAttr($prev, $this->att))) { // same value

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
          $this->splitPoint = $data->inst_valueOfAttr($prev, $this->att);
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
    $splitData[] = new Instances($data->getAttrs(), array_slice($data->getData(), 0, $finalSplit));
    $splitData[] = new Instances($data->getAttrs(), array_slice($data->getData(), $finalSplit, $total - $finalSplit));

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
    if (!$data->inst_isMissing($i, $this->att)) {
      if ($this->value == 0) { // First bag
        if ($data->inst_valueOfAttr($i, $this->att) > $this->splitPoint) {
          $isCover = false;
        }
      } else if ($data->inst_valueOfAttr($i, $this->att) < $this->splitPoint) {
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
      return "{$this->att->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
        // number_format($this->splitPoint, 6)
        number_format($this->splitPoint)
        ;
    }
    else {
      return "ContinuousAntecedent: ({$this->att->getName()}" . (($this->value == 0) ? " <= " : " >= ") .
        // number_format($this->splitPoint, 6)
        number_format($this->splitPoint)
        . ") (maxInfoGain={$this->maxInfoGain}, accuRate={$this->accuRate}, cover={$this->cover}, accu={$this->accu})" . PHP_EOL;
    }
  }
}

?>
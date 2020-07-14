<?php

/**
 * This class implements the statistics functions used in the propositional rule
 * learner, from the simpler ones like count of true/false positive/negatives,
 * filter data based on the ruleset, etc. to the more sophisticated ones such as
 * MDL calculation and rule variants generation for each rule in the ruleset.
 * <p>
 * 
 * Obviously the statistics functions listed above need the specific data and
 * the specific ruleset, which are given in order to instantiate an object of
 * this class.
 * <p>
 * 
 * @author Xin Xu (xx5@cs.waikato.ac.nz)
 * @version $Revision$
 */
class RuleStats {

  /** The data on which the stats calculation is based */
  private $data;

  /**
   * The total number of possible conditions that could appear in a rule
   */
  private $numAllConds;

  /** The redundancy factor in theory description length */
  private static $REDUNDANCY_FACTOR = 0.5;

  /** The theory weight in the MDL calculation */
  private $MDL_THEORY_WEIGHT = 1.0;

  /** Constructor */
  function __construct() {
    $this->data = NULL;
    $this->numAllConds = NULL;
  }

  /**
   * The description length of data given the parameters of the data based on
   * the ruleset.
   * <p>
   * Details see Quinlan: "MDL and categorical theories (Continued)",ML95
   * <p>
   * 
   * @param expFPOverErr expected FP/(FP+FN)
   * @param cover coverage
   * @param uncover uncoverage
   * @param fp False Positive
   * @param fn False Negative
   * @return the description length
   */
  static function dataDL($expFPOverErr, $cover, $uncover, $fp, $fn) {
    $totalBits = log($cover + $uncover + 1.0, 2); // how much data?
    $coverBits = 0.0;
    $uncoverBits = 0.0; // What's the error?
    $expErr = 0.0; // Expected FP or FN

    if ($cover > $uncover) {
      $expErr = $expFPOverErr * ($fp + $fn);
      $coverBits = self::subsetDL($cover, $fp, $expErr / $cover);
      $uncoverBits = ($uncover > 0.0) ? self::subsetDL($uncover, $fn, $fn / $uncover)
        : 0.0;
    } else {
      $expErr = (1.0 - $expFPOverErr) * ($fp + $fn);
      $coverBits = ($cover > 0.0) ? self::subsetDL($cover, $fp, $fp / $cover) : 0.0;
      $uncoverBits = self::subsetDL($uncover, $fn, $expErr / $uncover);
    }

    /*
     * System.err.println("!!!cover: " + cover + "|uncover" + uncover +
     * "|coverBits: "+coverBits+"|uncBits: "+ uncoverBits+
     * "|FPRate: "+expFPOverErr + "|expErr: "+expErr+
     * "|fp: "+fp+"|fn: "+fn+"|total: "+totalBits);
     */
    return ($totalBits + $coverBits + $uncoverBits);
  }

  /**
   * Subset description length: <br>
   * S(t,k,p) = -k*log2(p)-(n-k)log2(1-p)
   * 
   * Details see Quilan: "MDL and categorical theories (Continued)",ML95
   * 
   * @param t the number of elements in a known set
   * @param k the number of elements in a subset
   * @param p the expected proportion of subset known by recipient
   * @return the subset description length
   */
  static function subsetDL($t, $k, $p) {
    $rt = ($p > 0.0) ? (-$k * log($p, 2)) : 0.0;
    $rt -= ($t - $k) * log(1 - $p, 2);
    return $rt;
  }


  /**
   * Stratify the given data into the given number of bags based on the class
   * values. It differs from the <code>Instances.stratify(int fold)</code> that
   * before stratification it sorts the instances according to the class order
   * in the header file. It assumes no missing values in the class.
   * 
   * @param data the given data
   * @param folds the given number of folds
   * @return the stratified instances
   */
  static function stratify(Instances &$data, int $numFolds) {
    echo "RuleStats->stratify(&[data], numFolds=$numFolds)" . PHP_EOL;
    echo "data : " . $data->toString() . PHP_EOL;
    if (!($data->getClassAttribute() instanceof DiscreteAttribute)) {
      return $data;
    }

    $data_out = Instances::createEmpty($data);
    $bagsByClasses = [];
    for ($i = 0; $i < $data->numClasses(); $i++) {
      $bagsByClasses[] = Instances::createEmpty($data);
    }

    // Sort by class
    for ($j = 0; $j < $data->numInstances(); $j++) {
      $bagsByClasses[$data->inst_classValue($j)]->pushInstance($data->getInstance($j));
    }

    // Randomize each class
    foreach ($bagsByClasses as &$bag) {
      $bag->randomize();
    }

    for ($k = 0; $k < $numFolds; $k++) {
      $offset = $k;
      $i_bag = 0;
      while (true) {
        while ($offset >= $bagsByClasses[$i_bag]->numInstances()) {
          $offset -= $bagsByClasses[$i_bag]->numInstances();
          if (++$i_bag >= count($bagsByClasses)) {
            break 2;
          }
        }

        $data_out->pushInstance($bagsByClasses[$i_bag]->getInstance($offset));
        $offset += $numFolds;
      }
    }
    echo "data_out : " . $data_out->toString() . PHP_EOL;

    return $data_out;
  }

  /**
   * Patition the data into 2, first of which has (numFolds-1)/numFolds of the
   * data and the second has 1/numFolds of the data
   * 
   * 
   * @param data the given data
   * @param numFolds the given number of folds
   * @return the partitioned instances
   */
  static function partition(Instances &$data, int $numFolds) {
    echo "RuleStats->partition(&[data], numFolds=$numFolds)" . PHP_EOL;
    echo "data : " . $data->toString() . PHP_EOL;
    $rt = [];
    $splits = $data->numInstances() * ($numFolds - 1) / $numFolds;

    $rt[0] = Instances::createFromSlice($data, 0, $splits);
    $rt[1] = Instances::createFromSlice($data, $splits, $data->numInstances() - $splits);
    echo "rt[0] : " . $rt[0]->toString() . PHP_EOL;
    echo "rt[1] : " . $rt[1]->toString() . PHP_EOL;

    return $rt;
  }

  /**
   * Compute the number of all possible conditions that could appear in a rule
   * of a given data. For nominal attributes, it's the number of values that
   * could appear; for numeric attributes, it's the number of values * 2, i.e.
   * <= and >= are counted as different possible conditions.
   * 
   * @param data the given data
   * @return number of all conditions of the data
   */
  static function numAllConditions(Instances &$data) {
    echo "RuleStats->numAllConditions(&[data])" . PHP_EOL;
    $total = 0.0;
    foreach ($data->getAttrs(false) as $attr) {
      // echo $attr->toString() . PHP_EOL;
      switch (true) {
        case $attr instanceof DiscreteAttribute:
          $total += $attr->numValues();
          break;
        case $attr instanceof ContinuousAttribute:
          $total += 2.0 * $data->numDistinctValues($attr);
          break;
        default:
          die("ERROR: unknown type of attribute encountered!");
          break;
      }
    }
    echo "-> \$total : $total" . PHP_EOL;
    return $total;
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

    /**
     * @return mixed
     */
    public function getNumAllConds()
    {
        return $this->numAllConds;
    }

    /**
     * @param mixed $numAllConds
     *
     * @return self
     */
    public function setNumAllConds($numAllConds)
    {
        $this->numAllConds = $numAllConds;

        return $this;
    }
}

?>
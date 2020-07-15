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

  /** The specific ruleset in question */
  private $ruleset;

  /** The data on which the stats calculation is based */
  private $data;

  /** The set of instances filtered by the ruleset */
  private $filtered;

  /**
   * The total number of possible conditions that could appear in a rule
   */
  private $numAllConds;

  /** The redundancy factor in theory description length */
  private static $REDUNDANCY_FACTOR = 0.5;

  /** The theory weight in the MDL calculation */
  private $MDL_THEORY_WEIGHT = 1.0;

  /** The simple stats of each rule */
  private $simpleStats;

  /** The class distributions predicted by each rule */
  private $distributions;

  /** Constructor */
  function __construct() {
    $this->data = NULL;
    $this->numAllConds = NULL;

    $this->ruleset = NULL;
    $this->filtered = NULL;
    $this->simpleStats = NULL;
    $this->distributions = NULL;
  }

  /**
   * Get the simple stats of one rule, including 6 parameters: 0: coverage;
   * 1:uncoverage; 2: true positive; 3: true negatives; 4: false positives; 5:
   * false negatives
   * 
   * @param index the index of the rule
   * @return the stats
   */
  function getSimpleStats(int $index) {
    if (($this->simpleStats !== NULL)
    && ($index < $this->getRulesetSize())) {
      return $this->simpleStats[$index];
    }
    return NULL;
  }

  /**
   * Get the data after filtering the given rule
   * 
   * @param index the index of the rule
   * @return the data covered and uncovered by the rule
   */
  function getFiltered(int $index) {
    if (($this->filtered !== NULL)
    && ($index < $this->getRulesetSize())) {
      return $this->filtered[$index];
    }
    return NULL;
  }

  function getRulesetSize() {
    return count($this->getRuleset());
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
    foreach ($data->getAttributes(false) as $attr) {
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
   * Add a rule to the ruleset and update the stats
   * 
   * @param rule the rule to be added
   */
  function pushRule(_Rule $rule) {
    echo "RuleStats->pushRule({$rule->toString()})" . PHP_EOL;
    
    $data = ($this->filtered === NULL) ? $this->data : $this->filtered[array_key_last($this->filtered)][1];
    $stats = array_fill(0, 6, 0.0);
    $classCounts = array_fill(0, $this->data->getClassAttribute()->numValues(), 0.0);
    $filtered = self::computeSimpleStats($rule, $data, $stats, $classCounts);

    echo "filtered : { 0 => " . $filtered[0]->toString() . "\n 1 => " . $filtered[1]->toString() . " }" . PHP_EOL;
    echo "stats : " . get_var_dump($stats) . "" . PHP_EOL;
    echo "classCounts : " . get_var_dump($classCounts) . "" . PHP_EOL;

    if ($this->ruleset === NULL) {
      $this->ruleset = [];
    }
    $this->ruleset[] = $rule;

    if ($this->filtered === NULL) {
      $this->filtered = [];
    }
    $this->filtered[] = $filtered;

    if ($this->simpleStats === NULL) {
      $this->simpleStats = [];
    }
    $this->simpleStats[] = $stats;

    if ($this->distributions === NULL) {
      $this->distributions = [];
    }
    $this->distributions[] = $classCounts;
  }

  /**
   * Remove the last rule in the ruleset as well as it's stats. It might be
   * useful when the last rule was added for testing purpose and then the test
   * failed
   */
  function popRule() {
    array_pop($this->ruleset);
    array_pop($this->filtered);
    array_pop($this->simpleStats);
    array_pop($this->distributions);
  }

  /**
   * Find all the instances in the dataset covered/not covered by the rule in
   * given index, and the correponding simple statistics and predicted class
   * distributions are stored in the given double array, which can be obtained
   * by getSimpleStats() and getDistributions().<br>
   * 
   * @param index the given index, assuming correct
   * @param data the dataset to be covered by the rule
   * @param stats the given double array to hold stats, side-effected
   * @param dist the given array to hold class distributions, side-effected if
   *          null, the distribution is not necessary
   * @return the instances covered and not covered by the rule
   */
  static private function computeSimpleStats(_Rule $rule, Instances $data,
    array &$stats, array &$dist = NULL) {
    
    $out_data = [Instances::createEmpty($data), Instances::createEmpty($data)];

    for ($i = 0; $i < $data->numInstances(); $i++) {
      $weight = $data->inst_weight($i);
      if ($rule->covers($data, $i)) {
        $out_data[0]->pushInstance($data->getInstance($i)); // Covered by this rule
        $stats[0] += $weight; // Coverage
        if ($data->inst_classValue($i) == $rule->getConsequent()) {
          $stats[2] += $weight; // True positives
        } else {
          $stats[4] += $weight; // False positives
        }
        if ($dist !== NULL) {
          $dist[$data->inst_classValue($i)] += $weight;
        }
      } else {
        $out_data[1]->pushInstance($data->getInstance($i)); // Not covered by this rule
        $stats[1] += $weight;
        if ($data->inst_classValue($i) != $rule->getConsequent()) {
          $stats[3] += $weight; // True negatives
        } else {
          $stats[5] += $weight; // False negatives
        }
      }
    }

    return $out_data;
  }


  /**
   * The description length (DL) of the ruleset relative to if the rule in the
   * given position is deleted, which is obtained by: <br>
   * MDL if the rule exists - MDL if the rule does not exist <br>
   * Note the minimal possible DL of the ruleset is calculated(i.e. some other
   * rules may also be deleted) instead of the DL of the current ruleset.
   * <p>
   * 
   * @param index the given position of the rule in question (assuming correct)
   * @param expFPRate expected FP/(FP+FN), used in dataDL calculation
   * @param checkErr whether check if error rate >= 0.5
   * @return the relative DL
   */
  function relativeDL(int $index, float $expFPRate, bool $checkErr) {

    return ($this->minDataDLIfExists($index, $expFPRate, $checkErr)
          + $this->theoryDL($index)
          - $this->minDataDLIfDeleted($index, $expFPRate, $checkErr));
  }


  /**
   * The description length of the theory for a given rule. Computed as:<br>
   * 0.5* [||k||+ S(t, k, k/t)]<br>
   * where k is the number of antecedents of the rule; t is the total possible
   * antecedents that could appear in a rule; ||K|| is the universal prior for k
   * , log2*(k) and S(t,k,p) = -k*log2(p)-(n-k)log2(1-p) is the subset encoding
   * length.
   * <p>
   * 
   * Details see Quilan: "MDL and categorical theories (Continued)",ML95
   * 
   * @param index the index of the given rule (assuming correct)
   * @return the theory DL, weighted if weight != 1.0
   */
  function theoryDL(int $index) {

    $k = $this->ruleset[$index]->size();

    if ($k == 0) {
      return 0.0;
    }

    $tdl = log($k, 2);
    if ($k > 1) {
      $tdl += 2.0 * log($tdl, 2); // of log2 star
    }
    $tdl += $this->subsetDL($this->numAllConds, $k, $k / $this->numAllConds);
    // System.out.println("!!!theory: "+MDL_THEORY_WEIGHT * REDUNDANCY_FACTOR *
    // tdl);
    return $this->MDL_THEORY_WEIGHT * $this->REDUNDANCY_FACTOR * $tdl;
  }
  
  /**
   * Compute the minimal data description length of the ruleset if the rule in
   * the given position is deleted.<br>
   * The min_data_DL_if_deleted = data_DL_if_deleted - potential
   * 
   * @param index the index of the rule in question
   * @param expFPRate expected FP/(FP+FN), used in dataDL calculation
   * @param checkErr whether check if error rate >= 0.5
   * @return the minDataDL
   */
  function minDataDLIfDeleted(int $index, float $expFPRate, bool $checkErr) {
    // System.out.println("!!!Enter without: ");
    $rulesetStat = array_fill(0, 6, 0.0); // Stats of ruleset if deleted
    $more = $this->getRulesetSize() - 1 - $index; // How many rules after?
    $indexPlus = []; // Their stats

    // 0...(index-1) are OK
    for ($j = 0; $j < $index; $j++) {
      // Covered stats are cumulative
      $rulesetStat[0] += $this->simpleStats[$j][0];
      $rulesetStat[2] += $this->simpleStats[$j][2];
      $rulesetStat[4] += $this->simpleStats[$j][4];
    }

    // Recount data from index+1
    $data = ($index == 0) ? $this->data : $this->filtered[$index - 1][1];
    // System.out.println("!!!without: " + data.sumOfWeights());

    for ($j = ($index + 1); $j < $this->getRulesetSize(); $j++) {
      $stats = array_fill(0, 6, 0.0);
      $split = self::computeSimpleStats($j, $data, $stats, $tmp = NULL);
      $indexPlus[] = $stats;
      $rulesetStat[0] += $stats[0];
      $rulesetStat[2] += $stats[2];
      $rulesetStat[4] += $stats[4];
      $data = $split[1];
    }
    // Uncovered stats are those of the last rule
    if ($more > 0) {
      $rulesetStat[1] = $indexPlus[array_key_last($indexPlus)][1];
      $rulesetStat[3] = $indexPlus[array_key_last($indexPlus)][3];
      $rulesetStat[5] = $indexPlus[array_key_last($indexPlus)][5];
    } else if ($index > 0) {
      $rulesetStat[1] = $this->simpleStats[$index - 1][1];
      $rulesetStat[3] = $this->simpleStats[$index - 1][3];
      $rulesetStat[5] = $this->simpleStats[$index - 1][5];
    } else { // Null coverage
      $rulesetStat[1] = $this->simpleStats[0][0] + $this->simpleStats[0][1];
      $rulesetStat[3] = $this->simpleStats[0][3] + $this->simpleStats[0][4];
      $rulesetStat[5] = $this->simpleStats[0][2] + $this->simpleStats[0][5];
    }

    // Potential
    $potential = 0;
    for ($k = $index + 1; $k < $this->getRulesetSize(); $k++) {
      $ruleStat = $this->getSimpleStats($k - $index - 1);
      $ifDeleted = $this->potential($k, $expFPRate, $rulesetStat, $ruleStat,
        $checkErr);
      if (!is_nan($ifDeleted)) {
        $potential += $ifDeleted;
      }
    }

    // Data DL of the ruleset without the rule
    // Note that ruleset stats has already been updated to reflect
    // deletion if any potential
    $dataDLWithout = self::dataDL($expFPRate, $rulesetStat[0], $rulesetStat[1],
      $rulesetStat[4], $rulesetStat[5]);
    // System.out.println("!!!without: "+dataDLWithout + " |potential: "+
    // potential);
    // Why subtract potential again? To reflect change of theory DL??
    return ($dataDLWithout - $potential);
  }

  /**
   * Compute the minimal data description length of the ruleset if the rule in
   * the given position is NOT deleted.<br>
   * The min_data_DL_if_n_deleted = data_DL_if_n_deleted - potential
   * 
   * @param index the index of the rule in question
   * @param expFPRate expected FP/(FP+FN), used in dataDL calculation
   * @param checkErr whether check if error rate >= 0.5
   * @return the minDataDL
   */
  function minDataDLIfExists(int $index, float $expFPRate, bool $checkErr) {
    // System.out.println("!!!Enter with: ");
    $rulesetStat = array_fill(0, 6, 0.0); // Stats of ruleset if rule exists
    for ($j = 0; $j < $this->getRulesetSize(); $j++) {
      // Covered stats are cumulative
      $rulesetStat[0] += $this->simpleStats[$j][0];
      $rulesetStat[2] += $this->simpleStats[$j][2];
      $rulesetStat[4] += $this->simpleStats[$j][4];
      if ($j == $this->getRulesetSize() - 1) { // Last rule
        $rulesetStat[1] = $this->simpleStats[$j][1];
        $rulesetStat[3] = $this->simpleStats[$j][3];
        $rulesetStat[5] = $this->simpleStats[$j][5];
      }
    }

    // Potential
    $potential = 0;
    for ($k = $index + 1; $k < $this->getRulesetSize(); $k++) {
      $ruleStat = $this->getSimpleStats($k);
      $ifDeleted = $this->potential($k, $expFPRate, $rulesetStat, $ruleStat,
        $checkErr);
      if (!is_nan($ifDeleted)) {
        $potential += $ifDeleted;
      }
    }

    // Data DL of the ruleset without the rule
    // Note that ruleset stats has already been updated to reflect deletion
    // if any potential
    $dataDLWith = self::dataDL($expFPRate, $rulesetStat[0], $rulesetStat[1],
      $rulesetStat[4], $rulesetStat[5]);
    // System.out.println("!!!with: "+dataDLWith + " |potential: "+
    // potential);
    return ($dataDLWith - $potential);
  }


  /**
   * Calculate the potential to decrease DL of the ruleset, i.e. the possible DL
   * that could be decreased by deleting the rule whose index and simple
   * statstics are given. If there's no potentials (i.e. smOrEq 0 && error rate
   * < 0.5), it returns NaN.
   * <p>
   * 
   * The way this procedure does is copied from original RIPPER implementation
   * and is quite bizzare because it does not update the following rules' stats
   * recursively any more when testing each rule, which means it assumes after
   * deletion no data covered by the following rules (or regards the deleted
   * rule as the last rule). Reasonable assumption?
   * <p>
   * 
   * @param index the index of the rule in m_Ruleset to be deleted
   * @param expFPOverErr expected FP/(FP+FN)
   * @param rulesetStat the simple statistics of the ruleset, updated if the
   *          rule should be deleted
   * @param ruleStat the simple statistics of the rule to be deleted
   * @param checkErr whether check if error rate >= 0.5
   * @return the potential DL that could be decreased
   */
  static function potential(int $index, float $expFPOverErr, array $rulesetStat,
    array $ruleStat, bool $checkErr) {
    // System.out.println("!!!inside potential: ");
    // Restore the stats if deleted
    $pcov = $rulesetStat[0] - $ruleStat[0];
    $puncov = $rulesetStat[1] + $ruleStat[0];
    $pfp = $rulesetStat[4] - $ruleStat[4];
    $pfn = $rulesetStat[5] + $ruleStat[2];

    $dataDLWith = $this->dataDL($expFPOverErr, $rulesetStat[0], $rulesetStat[1],
      $rulesetStat[4], $rulesetStat[5]);
    $theoryDLWith = $this->theoryDL($index);
    $dataDLWithout = $this->dataDL($expFPOverErr, $pcov, $puncov, $pfp, $pfn);

    $potential = $dataDLWith + $theoryDLWith - $dataDLWithout;
    $err = $ruleStat[4] / $ruleStat[0];
    /*
     * System.out.println("!!!"+dataDLWith +" | "+ theoryDLWith + " | "
     * +dataDLWithout+"|"+ruleStat[4] + " / " + ruleStat[0]);
     */
    $overErr = $err >= 0.5;
    if (!$checkErr) {
      $overErr = false;
    }

    if ($potential >= 0.0 || $overErr) {
      // If deleted, update ruleset stats. Other stats do not matter
      $rulesetStat[0] = $pcov;
      $rulesetStat[1] = $puncov;
      $rulesetStat[4] = $pfp;
      $rulesetStat[5] = $pfn;
      return $potential;
    } else {
      return NAN;
    }
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

  /**
   * @return mixed
   */
  public function getRuleset()
  {
      return $this->ruleset;
  }

  /**
   * @param mixed $ruleset
   *
   * @return self
   */
  public function setRuleset($ruleset)
  {
      $this->ruleset = $ruleset;

      return $this;
  }
}

?>
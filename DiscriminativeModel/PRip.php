<?php

/*
 * Interface for learner/optimizers
 */
interface Learner {
    function teach(_DiscriminativeModel $model, Instances $data);
}

/*
 * Repeated Incremental Pruning to Produce Error Reduction (RIPPER),
 * which was proposed by William W. Cohen as an optimized version of IREP. 
 *
 * The algorithm is briefly described as follows: 

 * Initialize RS = {}, and for each class from the less prevalent one to the more frequent one, DO: 

 * 1. Building stage:
 * Repeat 1.1 and 1.2 until the descrition length (DL) of the ruleset and examples
 *  is 64 bits greater than the smallest DL met so far,
 *  or there are no positive examples, or the error rate >= 50%. 

 * 1.1. Grow phase:
 * Grow one rule by greedily adding antecedents (or conditions) to the rule until the rule is perfect (i.e. 100% accurate).  The procedure tries every possible value of each attribute and selects the condition with highest information gain: p(log(p/t)-log(P/T)).

 * 1.2. Prune phase:
 * Incrementally prune each rule and allow the pruning of any final sequences of the antecedents;The pruning metric is (p-n)/(p+n) -- but it's actually 2p/(p+n) -1, so in this implementation we simply use p/(p+n) (actually (p+1)/(p+n+2), thus if p+n is 0, it's 0.5).

 * 2. Optimization stage:
 *  after generating the initial ruleset {Ri}, generate and prune two variants of each rule Ri from randomized data using procedure 1.1 and 1.2. But one variant is generated from an empty rule while the other is generated by greedily adding antecedents to the original rule. Moreover, the pruning metric used here is (TP+TN)/(P+N).Then the smallest possible DL for each variant and the original rule is computed.  The variant with the minimal DL is selected as the final representative of Ri in the ruleset.After all the rules in {Ri} have been examined and if there are still residual positives, more rules are generated based on the residual positives using Building Stage again. 
 * 3. Delete the rules from the ruleset that would increase the DL of the whole ruleset if it were in it. and add resultant ruleset to RS. 
 * ENDDO

 * // (nota: l'allenamento dice anche quanto il modello e' buono. Nel caso di RuleBasedModel() ci sono dei metodi per valutare ogni singola regola. Come si valuta? vedremo)
 */
class PRip implements Learner {

  /** The limit of description length surplus in ruleset generation */
  static private $MAX_DL_SURPLUS = 64.0;

  /* Whether to turn on the debug mode (Default: false) */
  private $debug;

  /** Number of runs of optimizations */
  private $optimizations;

  /** Randomization seed */
  private $seed;

  /** The number of folds to split data into Grow and Prune for IREP
    * (One fold is used as pruning set.)
    */
  private $folds;

  /** Minimal weights of instance weights within a split */
  private $minNo;

  /** Whether check the error rate >= 0.5 in stopping criteria */
  private $checkErr;

  /** Whether use pruning, i.e. the data is clean or not */
  private $usePruning;

  function __construct($random_seed = 1) { // TODO: in the end use seed = NULL.
    if ($random_seed == NULL) {
      $random_seed = make_seed();
    }

    $this->debug = false;
    $this->optimizations = 2;
    $this->seed = $random_seed;
    $this->folds = 3;
    $this->minNo = 2.0;
    $this->checkErr = true;
    $this->usePruning = true;
  }

  /**
   * Builds a model through RIPPER in the order of class frequencies.
   * For each class it's built in two stages: building and optimization
   * 
   * @param instances the training data
   * @throws Exception if classifier can't be built successfully
   */
  function teach($model, $data) {
    echo "PRip->teach([model], [data])" . PHP_EOL;

    /* Remove instances with missing class */
    $data->removeUselessInsts();
    echo $data->toString() . PHP_EOL;

    srand($this->seed);

    $model->resetRules();
    
    /*
    // Sort by class FREQ_ASCEND
    $classAttr = $this->data->getClassAttribute();
    $orderedClassCounts = $this->data->getOrderedClassCounts();
    // m_RulesetStats = new ArrayList<RuleStats>();
    // m_Distributions = new ArrayList<double[]>();

    // Sort by classes frequency
    $orderedClasses = ((ClassOrder) m_Filter).getClassCounts();
    if (m_Debug) {
      System.err.println("Sorted classes:");
      for (int x = 0; x < m_Class.numValues(); x++) {
        System.err.println(x + ": " + m_Class.value(x) + " has "
          + orderedClasses[x] + " instances.");
      }
    }

    // Iterate from less prevalent class to more frequent one
    for ($y = 0; $y < $this->data.numClasses() - 1; $y++) { // For each
                                                                // class

      $classIndex = $y;
      if ($this->debug) {
        echo "\n\nClass " . m_Class.value($classIndex) . "(" . $classIndex . "): "
          . $orderedClasses[$y] . "instances\n"
          . "=====================================\n");
      }

      // Ignore classes with no members.
      if ($orderedClasses[$y] == 0) {
        continue;
      }

      // The expected FP/err is the proportion of the class
      $all = array_sum(array_slice($orderedClasses, $y));
      $expFPRate = $orderedClasses[$y] / $all;

      $classYWeights = 0; $totalWeights = 0;
      for (int j = 0; j < data.numInstances(); j++) {
        Instance datum = data.getInstance(j);
        totalWeights += datum.weight();
        if ((int) datum.classValue() == y) {
          classYWeights += datum.weight();
        }
      }

      // DL of default rule, no theory DL, only data DL
      double defDL;
      if (classYWeights > 0) {
        defDL = RuleStats.dataDL(expFPRate, 0.0, totalWeights, 0.0,
          classYWeights);
      } else {
        continue; // Subsumed by previous rules
      }

      if (Double.isNaN(defDL) || Double.isInfinite(defDL)) {
        throw new Exception("Should never happen: " + "defDL NaN or infinite!");
      }
      if (m_Debug) {
        System.err.println("The default DL = " + defDL);
      }

      data = rulesetForOneClass(expFPRate, data, classIndex, defDL);
    }

    // Remove redundant numeric tests from the rules
    for (Rule rule : m_Ruleset) {
      ((RipperRule)rule).cleanUp(data);
    }

    // Set the default rule
    RipperRule defRule = new RipperRule();
    defRule.setConsequent(data.numClasses() - 1);
    m_Ruleset.add(defRule);

    RuleStats defRuleStat = new RuleStats();
    defRuleStat.setData(data);
    defRuleStat.setNumAllConds(m_Total);
    defRuleStat.addAndUpdate(defRule);
    m_RulesetStats.add(defRuleStat);

    for (int z = 0; z < m_RulesetStats.size(); z++) {
      RuleStats oneClass = m_RulesetStats.get(z);
      for (int xyz = 0; xyz < oneClass.getRulesetSize(); xyz++) {
        double[] classDist = oneClass.getDistributions(xyz);
        Utils.normalize(classDist);
        if (classDist != null) {
          m_Distributions.add(((ClassOrder) m_Filter)
            .distributionsByOriginalIndex(classDist));
        }
      }
    }

    // free up memory
    for (int i = 0; i < m_RulesetStats.size(); i++) {
      (m_RulesetStats.get(i)).cleanUp();
    }
    */
  }

}

?>
<?php

/*
 * Interface for predictive models
 */
interface Learner {
    function teach($model, $data);
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
  
  private $options;
  // Options:
  // - Folds           Number of folds for REP. One fold is used as pruning set. (default 3)
  // - MinNo           Minimal weights of instances within a split. (default 2.0)
  // - Optimizations   Number of runs of optimizations. (Default: 2)
  // - Seed            The seed of randomization (Default: 1)
  // - Debug           Whether turn on the debug mode (Default: false)
  // - CheckErr        Whether NOT check the error rate>=0.5 in stopping criteria  (default: check)
  // - UsePruning      Whether NOT use pruning (Default: use pruning)

  // TODO
  function __construct() {
  }

  function teach($model, $data) {
    return;
  }
}

?>
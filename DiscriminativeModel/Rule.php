<?php

/*
 * Interface for rules
 */
interface Rule {
  /** The internal representation of the class label to be predicted */
  function getConsequent();
  function setConsequent($c);

  function getAntecedents();
  function setAntecedents($a);
}

/**
 * A single rule that predicts specified class.
 * 
 * A rule consists of antecedents "AND"-ed together and the consequent (class
 * value) for the classification. In this class, the Information Gain
 * (p*[log(p/t) - log(P/T)]) is used to select an antecedent and Reduced Error
 * Prunning (REP) with the metric of accuracy rate p/(p+n) or (TP+TN)/(P+N) is
 * used to prune the rule.
 */
class RipperRule implements Rule {

  /** The internal representation of the class label to be predicted */
  private $consequent;
  function getConsequent() { return $this->consequent; }
  function setConsequent($c) { $this->consequent = $c; }

  /** The vector of antecedents of this rule */
  private $antecedents;
  function getAntecedents() { return $this->antecedents; }
  function setAntecedents($a) { $this->antecedents = $a; }

  /** Constructor */
  function __construct() {
    $this->consequent = NULL;
    $this->antecedents = NULL;
  }

  /**
   * Whether the instance covered by this rule.
   * Note that an empty rule covers everything.
   * 
   * @param datum the instance in question
   * @return the boolean value indicating whether the instance is covered by
   *         this rule
   */
  function covers(&$data, $i) {
    $isCover = true;

    for ($x = 0; $x < count($this->antecedents); $x++) {
      if (!$this->antecedents[$x]->covers($data, $i)) {
        $isCover = false;
        break;
      }
    }
    return $isCover;
  }

  /**
   * Whether this rule has antecedents, i.e. whether it is a default rule
   * 
   * @return the boolean value indicating whether the rule has antecedents
   */
  function hasAntds() {
    if ($this->antecedents === NULL) {
      return false;
    }
    else {
      return ($this->size() > 0);
    }
  }

  /**
   * the number of antecedents of the rule
   * 
   * @return the size of this rule
   */
  function size() {
    return count($this->antecedents);
  }

  /**
   * Private function to compute default number of accurate instances in the
   * specified data for the consequent of the rule
   * 
   * @param data the data in question
   * @return the default accuracy number
   */
  function computeDefAccu($data) {
    echo "RipperRule->computeDefAccu(" . get_var_dump($data) . ")" . PHP_EOL;
    $defAccu = 0;
    for ($i = 0; $i < $data->numInstances(); $i++) {
      if ($data->inst_classValue($i) == $this->consequent) {
        $defAccu += $data->inst_weight($i);
      }
    }
    echo "\$defAccu : $defAccu" . PHP_EOL;
    return $defAccu;
  }

  /**
   * Build one rule using the growing data
   * 
   * @param data the growing data used to build the rule
   */
  function grow(Instances &$growData) {
    echo "RipperRule->grow(&[growData])" . PHP_EOL;
    if ($this->consequent === NULL) {
      throw new Exception(" Consequent not set yet.");
    }

    $sumOfWeights = $growData->sumOfWeights();
    if (!($sumOfWeights > 0.0)) {
      return;
    }

    /* Compute the default accurate rate of the growing data */
    $defAccu = $this->computeDefAccu($growData);
    $defAcRt = ($defAccu + 1.0) / ($sumOfWeights + 1.0);

    /* Keep the record of which attributes have already been used */
    $used = array_fill(0, $growData->numAttributes(), false);
    $numUnused = count($used);

    // If there are already antecedents existing
    foreach ($this->antecedents as &$antecedent) {
      if (!($antecedent instanceof ContinuousAntecedent)) {
        $used[antecedent.getAttr().index()] = true;
        $numUnused--;
      }
    }

    $maxInfoGain;
    while ($growData->numInstances() > 0
      && $numUnused > 0
      && $defAcRt < 1.0) {

      // We require that infoGain be positive
      /*
       * if(numAntds == originalSize) maxInfoGain = 0.0; // At least one
       * condition allowed else maxInfoGain = Utils.eq(defAcRt, 1.0) ?
       * defAccu/(double)numAntds : 0.0;
       */
      $maxInfoGain = 0.0;

      /* Build a list of antecedents */
      Antd oneAntd = NULL;
      Instances coverData = NULL;
      Enumeration<Attribute> enumAttr = growData.enumerateAttributes();

      /* Build one condition based on all attributes not used yet */
      while (enumAttr.hasMoreElements()) {
        Attribute att = (enumAttr.nextElement());

        if (m_Debug) {
          System.err.println("\nOne condition: size = "
            + growData.sumOfWeights());
        }

        $antd = Antecedent::createFromAttribute(att);

        if (!used[att.index()]) {
          /*
           * Compute the best information gain for each attribute, it's stored
           * in the antecedent formed by this attribute. This procedure
           * returns the data covered by the antecedent
           */
          Instances coveredData = computeInfoGain(growData, defAcRt, antd);
          if (coveredData != NULL) {
            double infoGain = antd.getMaxInfoGain();
            if (m_Debug) {
              System.err.println("Test of \'" + antd.toString()
                + "\': infoGain = " + infoGain + " | Accuracy = "
                + antd.getAccuRate() + "=" + antd.getAccu() + "/"
                + antd.getCover() + " def. accuracy: " + defAcRt);
            }

            if (infoGain > maxInfoGain) {
              oneAntd = antd;
              coverData = coveredData;
              maxInfoGain = infoGain;
            }
          }
        }
      }

      if (oneAntd === NULL) {
        break; // Cannot find antds
      }
      if (smaller(oneAntd.getAccu(), m_MinNo)) {
        break;// Too low coverage
      }

      // Numeric attributes can be used more than once
      if (!oneAntd.getAttr().isNumeric()) {
        used[oneAntd.getAttr().index()] = true;
        numUnused--;
      }

      m_Antds.add(oneAntd);
      growData = coverData;// Grow data size is shrinking
      defAcRt = oneAntd.getAccuRate();
    }
  }

  /**
   * Removes redundant tests in the rule.
   *
   * @param data an instance object that contains the appropriate header information for the attributes.
   */
  function cleanUp(&$data) {
    echo "RipperRule->cleanUp(" . get_var_dump($data) . ")" . PHP_EOL;
    $mins = array_fill(0,$data->numAttributes(),INF);
    $maxs = array_fill(0,$data->numAttributes(),-INF);
    
    for ($i = count($this->antecedents) - 1; $i >= 0; $i--) {
      // TODO maybe at some point this won't be necessary, and I'll directly use attr indices?
      $j = array_search($this->antecedents[$i]->getAttr(), $data->getAttributes());
      if ($this->antecedents[$i] instanceof ContinuousAntecedent) {
        $splitPoint = $this->antecedents[$i]->getSplitPoint();
        if ($this->antecedents[$i]->getValue() == 0) {
          if ($splitPoint < $mins[$attribute_idx]) {
            $mins[$attribute_idx] = $splitPoint;
          } else {
            array_splice($this->antecedents, $i, $i+1);
          }
        } else {
          if ($splitPoint > $maxs[$attribute_idx]) {
            $maxs[$attribute_idx] = $splitPoint;
          } else {
            array_splice($this->antecedents, $i, $i+1);
          }
        }
      }
    }
  }
  
  /* Print a textual representation of the antecedent */
  function toString($classAttr) {
    if (count($this->antecedents) > 0) {
      $ants = [];
      for ($j = 0; $j < count($this->antecedents); $j++) {
        $ants[] = "(" . $this->antecedents[$j].toString(true) . ")";
      }
    }
    $out_str = join($ants, " and ") . " => " . $classAttr->getName() . "=" . classAttr.value((int) m_Consequent);

    return $out_str;
  }
}

?>
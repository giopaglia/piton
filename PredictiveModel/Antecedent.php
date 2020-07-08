<?php

include "Attribute.php";
include "Instances.php";

/**
* A single antecedent in the rule, composed of an attribute and a value for it.
*/
interface Antecedent {

  /** The attribute of the antecedent */
  function getAtt();
  function setAtt($a);

  /**
  * The attribute value of the antecedent. For numeric attribute, it represents the operator (<= or >=)
  */
  function getValue();
  function setValue($v);

  /**
  * The maximum infoGain achieved by this antecedent test in the growing data
  */
  function getMaxInfoGain();
  function setMaxInfoGain($m);

  /** The accurate rate of this antecedent test on the growing data */
  function getAccuRate();
  function setAccuRate($a);

  /** The coverage of this antecedent in the growing data */
  function getCover();
  function setCover($c);

  /** The accurate data for this antecedent in the growing data */
  function getAccu();
  function setAccu($a);

  /** Constructor */
  function __construct($attribute);

  /* The abstract members for inheritance */
  function splitData($data, $defAcRt, $cla);
  function covers($data, $i);

  /* Print a textual representation of the antecedent */
  function toString();
}

include "DiscreteAntecedent.php";
include "ContinuousAntecedent.php";

?>
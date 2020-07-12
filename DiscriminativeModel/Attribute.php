<?php

/*
 * Interface for continuous/discrete attributes
 */
interface Attribute {

  /** The name of the attribute */
  function getName();
  function setName($n);

  /** The type of the attribute */
  function getType();
  function setType($n);

  /** The type of the attribute (ARFF/Weka style)  */
  function getARFFType();

  /* Print a textual representation of a value of the attribute */
  function reprVal($val);

  /* Print a textual representation of the attribute */
  function toString();
}

/*
 * Discrete attribute
 */
class DiscreteAttribute implements Attribute {

  /** The name of the attribute */
  private $name;
  function getName() { return $this->name; }
  function setName($n) { $this->name = $n; }

  /** The type of the attribute */
  private $type;
  function getType() { return $this->type; }
  function setType($t) { $this->type = $t; }
  
  /** The type of the attribute (ARFF/Weka style)  */
  function getARFFType() {
    return "{" . join(",", $this->getDomain()) . "}";
  }

  /** Domain: discrete set of values that an instance can show for the attribute */
  private $domain;
  function getDomain() { return $this->domain; }
  function setDomain($d) { $this->domain = $d; }

  function __construct($name, $type, $domain) {
    $this->name   = $name;
    $this->type   = $type;
    $this->domain = $domain;
  }

  function numValues() { return count($this->domain); }

  /* Print a textual representation of a value of the attribute */
  function reprVal($val) {
    return $this->domain[$val];
  }

  /* Print a textual representation of the attribute */
  function toString() {
    return $this->getName();
  }
}


/*
 * Continuous attribute
 */
class ContinuousAttribute implements Attribute {

  /** The name of the attribute */
  private $name;
  function getName() { return $this->name; }
  function setName($n) { $this->name = $n; }

  /** The type of the attribute */
  private $type;
  function getType() { return $this->type; }
  function setType($t) { $this->type = $t; }

  /** The type of the attribute (ARFF/Weka style)  */
  static $type2ARFFtype = [
    "int"     => "numeric"
  , "float"   => "numeric"
  , "double"  => "numeric"
  //, "bool"    => "numeric"
  , "date"    => "date"
  ];
  function getARFFType() {
    return self::$type2ARFFtype[$this->getType()];
  }

  function __construct($name, $type) {
    $this->name   = $name;
    $this->type   = $type;
  }

  /* Print a textual representation of a value of the attribute */
  function reprVal($val) {
    return strval($val);
  }

  /* Print a textual representation of the attribute */
  function toString() {
    return $this->getName();
  }
}

?>
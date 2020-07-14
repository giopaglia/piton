<?php

/*
 * Interface for continuous/discrete attributes
 */
abstract class _Attribute {

  /** The name of the attribute */
  private $name;

  /** The type of the attribute */
  private $type;

  function __construct(string $name, string $type) {
    $this->name = $name;
    $this->type = $type;
  }

  /** The type of the attribute (ARFF/Weka style)  */
  abstract function getARFFType();

  /* Print a textual representation of a value of the attribute */
  abstract function reprVal($val);

  /* Print a textual representation of the attribute */
  abstract function toString();

  /**
   * @return mixed
   */
  public function getName()
  {
      return $this->name;
  }

  /**
   * @param mixed $name
   *
   * @return self
   */
  public function setName($name)
  {
      $this->name = $name;

      return $this;
  }

  /**
   * @return mixed
   */
  public function getType()
  {
      return $this->type;
  }

  /**
   * @param mixed $type
   *
   * @return self
   */
  public function setType($type)
  {
      $this->type = $type;

      return $this;
  }
}

/*
 * Discrete attribute
 */
class DiscreteAttribute extends _Attribute {

  function __construct($name, $type, $domain = []) {
    parent::__construct($name, $type);
    $this->domain = $domain;
  }

  /** Domain: discrete set of values that an instance can show for the attribute */
  private $domain;
  function pushDomainVal($v) { $this->domain[] = $v; }

  function numValues() { return count($this->domain); }

  /* Print a textual representation of a value of the attribute */
  function reprVal($val) {
    return strval($this->domain[$val]);
  }

  /** The type of the attribute (ARFF/Weka style)  */
  function getARFFType() {
    return "{" . join(",", $this->getDomain()) . "}";
  }
  

  /* Print a textual representation of the attribute */
  function toString() {
    return $this->getName();
  }


  /**
   * @return mixed
   */
  public function getDomain()
  {
      return $this->domain;
  }

  /**
   * @param mixed $domain
   *
   * @return self
   */
  public function setDomain($domain)
  {
      $this->domain = $domain;

      return $this;
  }
}


/*
 * Continuous attribute
 */
class ContinuousAttribute extends _Attribute {

  /** The type of the attribute (ARFF/Weka style)  */
  static $type2ARFFtype = [
    "int"       => "numeric"
  , "float"     => "numeric"
  , "double"    => "numeric"
  //, "bool"      => "numeric"
  , "date"      => "date \"yyyy-MM-dd\""
  , "datetime"  => "date \"yyyy-MM-dd'T'HH:mm:ss\""
  ];
  function getARFFType() {
    return self::$type2ARFFtype[$this->getType()];
  }

  // function __construct($name, $type) {
      // parent::__construct($name, $type);
  // }

  /* Print a textual representation of a value of the attribute */
  function reprVal($val) {
    switch ($this->getARFFType()) {
      case "date \"yyyy-MM-dd\"":
        $date = new DateTime();
        $date->setTimestamp($val);
        return $date->format("Y-m-d");
        break;
      case "date \"yyyy-MM-dd'T'HH:mm:ss\"":
        $date = new DateTime();
        $date->setTimestamp($val);
        return $date->format("Y-m-d\TH:i:s");
        break;
      default:
        return strval($val);
        break;
    }
  }

  /* Print a textual representation of the attribute */
  function toString() {
    return $this->getName();
  }
}

?>
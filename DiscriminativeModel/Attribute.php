<?php

/**
 * Interface for continuous/discrete attributes
 */
abstract class _Attribute {

  /** The name of the attribute */
  protected $name;

  /** The type of the attribute */
  protected $type;

  /** The index of the attribute (useful when dealing with many attributes) */
  protected $index;

  function __construct(string $name, string $type) {
    $this->name  = $name;
    $this->type  = $type;
  }

  /** The type of the attribute (ARFF/Weka style)  */
  abstract function getARFFType() : string;

  /** Print a textual representation of a value of the attribute */
  abstract function reprVal($val) : string;

  /** Print a textual representation of the attribute */
  abstract function toString() : string;

  function getName() : string
  {
    return $this->name;
  }

  function setName(string $name)
  {
    $this->name = $name;
  }

  function getType() : string
  {
    return $this->type;
  }

  function setType($type)
  {
    $this->type = $type;
  }

  function getIndex() : int
  {
    if(!($this->index !== NULL))
      die_error("Attribute with un-initialized index");
    return $this->index;
  }

  function setIndex(int $index)
  {
    $this->index = $index;
  }
}

/**
 * Discrete attribute
 */
class DiscreteAttribute extends _Attribute {

  function __construct(string $name, string $type, array $domain = []) {
    parent::__construct($name, $type);
    $this->setDomain($domain);
  }

  /** Domain: discrete set of values that an instance can show for the attribute */
  private $domain;

  function numValues() : int { return count($this->domain); }
  function pushDomainVal(string $v) { $this->domain[] = $v; }

  /** Print a textual representation of a value of the attribute */
  function reprVal($val) : string {
    return $val < 0 || $val === NULL ? $val : strval($this->domain[$val]);
  }

  /** The type of the attribute (ARFF/Weka style)  */
  function getARFFType() : string {
    return "{" . join(",", $this->domain) . "}";
  }
  
  /** Print a textual representation of the attribute */
  function toString($short = true) : string {
    return $short ? $this->name : "[DiscreteAttribute '{$this->name}' (type {$this->type}): " . get_arr_dump($this->domain) . " ]";
  }

  function getDomain() : array
  {
    return $this->domain;
  }

  function setDomain(array $domain)
  {
    foreach ($domain as $val) {
      if(!(is_string($val)))
        die_error("Non-string value encountered in domain when setting domain "
        . "for DiscreteAttribute \"{$this->getName()}\": " . gettype($val));
    }
    $this->domain = $domain;
  }
}


/**
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
  function getARFFType() : string {
    return self::$type2ARFFtype[$this->type];
  }

  // function __construct(string $name, string $type) {
      // parent::__construct($name, $type);
  // }

  /** Print a textual representation of a value of the attribute */
  function reprVal($val) : string {
    if ($val < 0 || $val === NULL)
      return $val;
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

  /** Print a textual representation of the attribute */
  function toString() : string {
    return $this->name;
  }
}

?>
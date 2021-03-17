<?php

/**
 * Interface for continuous/discrete attributes
 */
abstract class Attribute {

  /** The name of the attribute */
  protected $name;

  /** The type of the attribute */
  protected $type;

  /** The index of the attribute (useful when dealing with many attributes) */
  protected $index;

  /** Metadata */
  protected $metadata;

  function __construct(string $name, string $type) {
    $this->name  = $name;
    $this->type  = $type;
  }

  /** The type of the attribute (ARFF/Weka style)  */
  abstract function getARFFType() : string;

  /** Obtain the value for the representation of the attribute */
  abstract function getKey($cl);

  /** Obtain the representation of a value of the attribute */
  abstract function reprVal($val) : string;

  /** Print a textual representation of the attribute */
  abstract function toString() : string;
  

  /** The type of the attribute (ARFF/Weka style)  */
  static $ARFFtype2type = [
    "numeric"  => "float"
  , "real"     => "float"
  ];

  static function createFromARFF(string $line, string $csv_delimiter = "'") : Attribute {
    if (DEBUGMODE > 2) echo "$line" . PHP_EOL;
    
    $wordChars = "\wA-Za-zÀ-ÖØ-öø-ÿ\/()-’";
    if (preg_match("/@attribute\s+'/i", $line)) {
      $regExp =  "/@attribute\s+'([\s$wordChars]+)'\s+(.*)/i";
    } else if (preg_match("/@attribute\s+\"/i", $line)) {
      # $regExp =  "/@attribute\s+\"([\s$wordChars]+)\"\s+(.*)/i";
      # https://stackoverflow.com/a/5696141/5646732
      // $regExp =  "/@attribute\s+\"(" . '[^"\\\\]*(?:\\\\.[^"\\\\]*)*' . ")\"\s+(.*)/i";
      $regExp =  "/@attribute\s+\"(" . '[^"\\\\]*(?:\\\\.[^"\\\\]+)*' . ")\"\s+(.*)/i";
    } else {
      $regExp =  "/@attribute\s+([$wordChars]+)\s+(.*)/i";
    }

    preg_match($regExp, $line, $matches);
    # preg_match("/@attribute\s+([\S_]+|)\s+(.*)/", $line, $matches);
    if (count($matches) < 2) {
      die_error("Malformed ARFF attribute line:" . PHP_EOL . $line . PHP_EOL . $regExp);
    }

    $name = $matches[1];
    $type = $matches[2];
    if (DEBUGMODE > 2) echo "name: $name" . PHP_EOL;
    if (DEBUGMODE > 2) echo "type: $type" . PHP_EOL;
    switch (true) {
      case preg_match("/\{\s*(.*)\s*\}/", $type, $domain_str):
        $domain_arr = array_map("trim",  array_map("trim", str_getcsv($domain_str[1], ",", $csv_delimiter)));
        $attribute = new DiscreteAttribute($name, "enum", $domain_arr);
        break;
      case isset(self::$ARFFtype2type[$type])
       && in_array(self::$ARFFtype2type[$type], ["float", "int"]):
        $attribute = new ContinuousAttribute($name, self::$ARFFtype2type[$type]);
        break;
      default:
        die_error("Unknown ARFF type encountered: " . $type);
        break;
    }
    return $attribute;
  }

  /**
   * Recreate the type and eventual domain for an attribute when reading it from a database table,
   * making use of column comments
   */
  static function createFromDB(string $name, string $type, string $csv_delimiter = "'") : Attribute {        
    switch (true) {
      case preg_match("/\{\s*(.*)\s*\}/", $type, $domain_str);
        $domain_arr = array_map("trim",  array_map("trim", str_getcsv($domain_str[1], ",", $csv_delimiter)));
        $attribute = new DiscreteAttribute($name, "enum", $domain_arr);
        break;
      case isset(self::$ARFFtype2type[$type])
       && in_array(self::$ARFFtype2type[$type], ["float", "int"]):
        $attribute = new ContinuousAttribute($name, self::$ARFFtype2type[$type]);
        break;
      default:
        die_error("Unknown ARFF type encountered: " . $type);
        break;
    }
    return $attribute;
  }

  /** Whether two attributes are equal (completely interchangeable) */
  function isEqualTo(Attribute $otherAttr) : bool {
    return (get_class($this) === get_class($otherAttr))
        && ($this->name === $otherAttr->name)
        && ($this->type === $otherAttr->type)
        && ($this->index === $otherAttr->index);
  }

  // * Whether there can be a bijective mapping between two attributes 
  // function isEquivalentTo(Attribute $otherAttr) : bool {
  //   return get_class($this) == get_class($otherAttr)
  //       && $this->getName() == $otherAttr->getName()
  //       && $this->getType() == $otherAttr->getType();
  // }

  /** Whether there can be a mapping from one attribute to another */
  abstract function isAtLeastAsExpressiveAs(Attribute $otherAttr);

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

  function setType(string $type)
  {
    $this->type = $type;
  }

  function getIndex() : int
  {
    if(!($this->index !== NULL))
      die_error("Attribute with uninitialized index");
    return $this->index;
  }

  function setIndex(int $index)
  {
    $this->index = $index;
  }

  function getMetadata()
  {
    return $this->metadata;
  }

  function setMetadata($metadata)
  {
    $this->metadata = $metadata;
  }
}

/**
 * Discrete attribute
 */
class DiscreteAttribute extends Attribute {

  function __construct(string $name, string $type, array $domain = []) {
    parent::__construct($name, $type);
    $this->setDomain($domain);
  }

  /** Domain: discrete set of values that an instance can show for the attribute */
  private $domain;

  // /** Whether two attributes are equal (completely interchangeable) */
  function isEqualTo(Attribute $otherAttr) : bool {
    return $this->getDomain() == $otherAttr->getDomain()
       && parent::isEqualTo($otherAttr);
  }

  // /** Whether there can be a mapping between two attributes */
  // function isEquivalentTo(Attribute $otherAttr) : bool {
  //   if (DEBUGMODE > 2) echo get_arr_dump($this->getDomain());
  //   if (DEBUGMODE > 2) echo get_arr_dump($otherAttr->getDomain());
  //   if (DEBUGMODE > 2) echo array_equiv($this->getDomain(), $otherAttr->getDomain());
  //   return array_equiv($this->getDomain(), $otherAttr->getDomain())
  //      && parent::isEquivalentTo($otherAttr);
  // }

  /** Whether there can be a mapping from one attribute to another */
  function isAtLeastAsExpressiveAs(Attribute $otherAttr) {
    return get_class($this) == get_class($otherAttr)
        && $this->getName() == $otherAttr->getName()
        // && $this->getType() == $otherAttr->getType()
        && !count(array_diff($otherAttr->getDomain(), $this->getDomain()));
  }

  function numValues() : int { return count($this->domain); }
  function pushDomainVal(string $cl) : int { $this->domain[] = $cl; return $this->getKey($cl); }

  /** Obtain the value for the representation of the attribute */
  function getKey($cl, $safety_check = false) {
    $i = array_search($cl, $this->getDomain());
    if ($i === false && $safety_check) {
      die_error("Couldn't find element \"" . get_var_dump($cl)
        . "\" in domain of attribute {$this->getName()} ("
        . get_arr_dump($this->getDomain()) . "). ");
    }
    return $i;
  }

  /** Obtain the representation of a value of the attribute */
  function reprVal($val) : string {
    return $val < 0 || $val === NULL ? $val : strval($this->domain[$val]);
  }

  /** Obtain the representation for the attribute of a value
    that belonged to a different domain */
  function reprValAs(DiscreteAttribute $oldAttr, ?int $oldVal, bool $force = false) {
    if ($oldVal === NULL) return NULL;
    $cl = $oldAttr->reprVal($oldVal);
    $i = $this->getKey($cl);
    if ($i === false) {
      if ($force) {
        return $this->pushDomainVal($cl);
      } else {
        die_error("Can't represent nominal value \"$cl\" ($oldVal) within domain " . get_arr_dump($this->getDomain()));
      }
    }
    return $i;
  }


  /** The type of the attribute (ARFF/Weka style)  */
  function getARFFType() : string {
    return "{" . join(",", array_map(function ($val) { return "'" . addcslashes($val, "'") . "'"; }, $this->domain)) . "}";
  }
  
  /** Print a textual representation of the attribute */
  function __toString() : string {
    return $this->toString();
  }
  function toString($short = false) : string {
    return $short ? $this->name : "[DiscreteAttribute '{$this->name}' (type {$this->type}): " . get_arr_dump($this->domain) . " ]";
  }

  function getDomain() : array
  {
    return $this->domain;
  }

  /** Get the domain in the form 'value1','value2',...,'valueN' */
  function getDomainString() : string {
    return implode("','",$this->domain);
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
class ContinuousAttribute extends Attribute {

  /** The type of the attribute (ARFF/Weka style)  */
  static $type2ARFFtype = [
    "parsed"    => "numeric"
  , "int"       => "numeric"
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

  /** Whether there can be a mapping from one attribute to another */
  function isAtLeastAsExpressiveAs(Attribute $otherAttr) {
    return get_class($this) == get_class($otherAttr)
        && $this->getName() == $otherAttr->getName()
        // && $this->getType() == $otherAttr->getType()
        ;
  }

  /** Obtain the value for the representation of the attribute */
  function getKey($cl) {
    return $cl;
  }

  /** Obtain the representation of a value of the attribute */
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

  function reprValAs(ContinuousAttribute $oldAttr, $oldVal) {
    return $oldVal;
  }
  

  /** Print a textual representation of the attribute */
  function __toString() : string {
    return $this->toString();
  }
  function toString($short = false) : string {
    return $short ? $this->name : "[ContinuousAttribute '{$this->name}' (type {$this->type}) ]";
  }
}

?>

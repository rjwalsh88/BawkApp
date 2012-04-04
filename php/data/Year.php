<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');

/**
 * Data class for years
 */

class Year extends ReadOnlyDBRecord {

  protected $id, $current;

  public function getID() {
    return $this->id;
  }

	private static $currentYearID;
	private static $cache = array();
  public static function getAll() {
    $items = query(__CLASS__)->selectMultiple();
    self::$cache = array();
    foreach ($items as $item) {
      self::$cache[$item->id] = $item;
      if ($item->current) self::$currentYearID = $item->id;
    }
    return $items;
  }

  public static function current() {
    if (!self::$currentYearID) {
      self::getAll();
    }
    return self::$currentYearID;
  }

}

?>
<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Year.php');

class Challenge extends AdminEditableDBRecord {

	protected $id, $year, $name, $points, $code;

	public function getID() {
    return $this->id;
  }
	public function getName() {
    return $this->name ? htmlize($this->name) : '(No name)';
  }
  public function getYear() {
    return $this->year;
  }

	public function getPoints() {
	  return $this->points;
	}

	public function getCode() {
    return $this->code ? htmlize($this->code) : '(None)';
  }

	protected
	  $allWinnersFetched = false,
    $winners = array();

  public function getWinners() {
    if (!$this->allWinnersFetched) {
      $items = query('ChallengeWinner')
        ->where(array('challenge' => $this->id))
        ->sort('time')->selectMultiple();
      $this->winners = array();
      foreach ($items as $item) {
	      $this->winners[$item->getID()] = $item;
	    }
	    $this->allWinnersFetched = true;
    }
    return $this->winners;
  }

  private static $cache = array();
	private static $allFetched = false;
  public static function getChallenge($id) {
    if (!self::$allFetched && !isset(self::$cache[$id])) {
	    $item = query(__CLASS__)
	      ->where(array('id' => $id, 'year' => Year::current()))
	      ->selectSingle();
	    if ($item) {
	      self::$cache[$id] = $item;
	    }
	  }
    return idx(self::$cache, $id);
  }

  public static function getChallengeByCode($code) {
    if (!$code) {
      return null;
    }
    return query(__CLASS__)
      ->where(array('code' => stripslashes($code), 'year' => Year::current()))
      ->selectSingle();
  }

  public static function getAllChallenges() {
		if (!self::$allFetched) {
			$items = query(__CLASS__)
			  ->where(array('year' => Year::current()))
  		  ->sort('name')->selectMultiple();
	    self::$cache = array();
	    foreach ($items as $item) {
	      self::$cache[$item->id] = $item;
	    }
			self::$allFetched = true;
		}
    return self::$cache;
  }
}


?>
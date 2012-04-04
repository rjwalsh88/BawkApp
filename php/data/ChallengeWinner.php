<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Team.php');
require_once('Challenge.php');

class ChallengeWinner extends TeamEditableDBRecord {

	protected $id, $challenge, $team, $time;

	// related data
	protected
	  $challengeObj = UNPREPARED,
		$teamObj = UNPREPARED;

	public function getID() {
    return $this->id;
  }
	public function getTime() {
	  return htmlize(id(new Date($this->time))->toBawkString());
  }

  protected function getTeamID() { // @Override
    return $this->team;
  }

	public function getTeam() {
	  if ($this->teamObj == UNPREPARED) {
      $this->teamObj = Team::getTeam($this->team);
	  }
    return $this->teamObj;
	}
	public function getChallenge() {
	  if ($this->challengeObj == UNPREPARED) {
      $this->challengeObj = Challenge::getChallenge($this->challenge);
	  }
    return $this->challengeObj;
	}

	private static $cache = array();
	private static $allFetched = false;
  public static function getChallengeWinner($id) {
    if (!self::$allFetched && !isset(self::$cache[$id])) {
	    $item = query(__CLASS__)->where(array('id' => $id))->selectSingle();
	    if ($item) {
	      self::$cache[$id] = $item;
	    }
	  }
    return idx(self::$cache, $id);
  }

  public static function getAllChallengeWinners() {
    Challenge::getAllChallenges();
		if (!self::$allFetched) {
			$items = query(__CLASS__)->sortDesc('time')->selectMultiple();
	    self::$cache = array();
	    foreach ($items as $item) {
	      if (!$item->getChallenge()) {
	        continue;
	      }
	      self::$cache[$item->id] = $item;
	    }
			self::$allFetched = true;
		}
    return self::$cache;
  }
}


?>
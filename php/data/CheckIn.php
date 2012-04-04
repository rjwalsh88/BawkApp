<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Team.php');

class CheckIn extends TeamEditableDBRecord {

  // ID and foreign keys
	protected $id, $team;

	// data fields
	protected $time, $latitude, $longitude, $guess;

	// related data
	protected
		$teamObj = UNPREPARED;

	protected function getTeamID() { // @Override
	  return $this->team;
  }

	public function getID() {
    return $this->id;
  }
  public function getLat() {
    return $this->latitude;
  }
  public function getLong() {
    return $this->longitude;
  }
	public function getTime() {
	  return htmlize(id(new Date($this->time))->toBawkString());
	}

	public function getTeam() {
	  if ($this->teamObj == UNPREPARED) {
      $this->teamObj = Team::getTeam($this->team);
	  }
    return $this->teamObj;
	}

	public static function doCheckIn(Team $team, $latitude, $longitude, $guess = null) {
    $data = array(
      'team' => $team->getID(),
      'latitude' => $latitude,
      'longitude' => $longitude,
      'guess' => $guess,
    );
    $checkIn = new CheckIn($data);
    $checkIn->doAdd();
	}

	public static function getCheckIns(Team $team, $minTime = null) {
		$query = query(__CLASS__)->where(array('team' => $team->getID()));
    if ($minTime != null) $query->whereGE(array('time' => $minTime));
    return $query->selectMultiple();
	}

	public static function getMostRecent(Team $team) {
		return query(__CLASS__)
		  ->where(array('team' => $team->getID()))
		  ->sortDesc('time')
		  ->limit(1)
		  ->selectSingle();
	}
}


?>
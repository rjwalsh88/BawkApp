<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Team.php');
require_once('Clue.php');

class ClueState extends TeamEditableDBRecord {

  // ID and foreign keys
	protected $id, $clue, $team;
	
	// data fields
	protected $state, $answer;
	
	// related data
	protected 
	  $clueObj = UNPREPARED,
		$teamObj = UNPREPARED;
	
	protected function getTeamID() { // @Override
	  return $this->team;
  }

	public function getID() {
    return $this->id;
  }
	public function getState() {
    return $this->state;
  }
	public function getAnswer() {
	  return htmlize($this->answer);
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
	public function getClue() {
	  if ($this->clueObj == UNPREPARED) {
      $this->clueObj = Clue::getClue($this->clue);
	  }
    return $this->clueObj;
	}
}


?>
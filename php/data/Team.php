<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Year.php');
require_once('Clue.php');
require_once('Utils.php');

/**
 * Data class for teams
 */

class Team extends AdminEditableDBRecord {

  protected $id, $year, $name, $color;

  public function getID() {
    return $this->id;
  }

  protected function getTeamID() { // @Override
	    return $this->id;
  }

  public function getName() {
    return htmlize($this->name);
  }

	public function getMembers() {
		return query('Person')->where(array('team' => $this->id))->selectMultiple();
	}

	public function getLocationLink() {
		$checkIn = CheckIn::getMostRecent($this);
		if ($checkIn) {
			$lat = $checkIn->getLat();
			$long = $checkIn->getLong();
			$time = $checkIn->getTime();
			$url = "http://maps.google.com/maps?q=$lat,+$long";
			return "<br /><a href=\"$url\">Most Recent Check-In: $lat, $long (at $time)</a>";
		} else {
			return '';
		}
	}

	private static $cache = array();
  public static function getTeam($id) {
    if (!isset(self::$cache[$id])) {
	    $item = query(__CLASS__)
	      ->where(array('id' => $id, 'year' => Year::current()))
	      ->selectSingle();
	    if ($item) {
	      self::$cache[$id] = $item;
	    }
	  }
    return idx(self::$cache, $id);
  }

  public static function getAllTeams() {
    $items = query(__CLASS__)
      ->where(array('year' => Year::current()))
      ->sort('name')->selectMultiple();
    self::$cache = array();
    foreach ($items as $item) {
      self::$cache[$item->id] = $item;
    }
    return $items;
  }
	public static function getAllTeamNames() {
		$items = self::getAllTeams();
		$names = array();
		foreach ($items as $key => $item) $names[$key] = $item->getName();
		return $names;
	}

  public function shouldRefreshClueStates() {
    return $this->doRefreshClueStates(false);
  }

  public function doRefreshClueStates($commitChanges = true) {
	  $clue = $this->getCurrentClue();
	  $everChanged = false;
	  while ($clue != null) {
      $changed = $this->doRefreshClueState($clue, $commitChanges);
      $everChanged = $everChanged || $changed;
      if (!$changed && !$clue->isAnswered($this)) break;
	    $clue = $clue->getNextClue();
	  }
	  return $everChanged;
	}

	public function doRefreshClueState(Clue $clue, $commitChanges = true) {
	  $clueState = $clue->getClueState($this);

	  $currentState = $clueState ? $clueState->getState() : STATE_HIDDEN;
	  $actualState = $clue->calculateClueState($this);

	  if ($actualState > $currentState) {
	    if ($commitChanges) {
	      if ($clueState == null) {
  	      $clueState = new ClueState(array(
  	        'team' => $this->id,
  	        'clue' => $clue->getID()
  	      ));
  	      $clueState->doAdd();
  	      $clue->setClueState($this, $clueState);
  	    }

  	    $notifications = array(
  	      '',
  	      'A new clue has been unlocked.',
  	      'You are now able to answer this clue.',
  	      'You have unlocked a hint for this clue.',
  	      'A clue was just answered for you automatically.',
  	    );
  	    for ($i = $currentState + 1; $i <= $actualState; $i++) {
  	      if ($i != STATE_ANSWERABLE && ($i != STATE_HINTED || $i == $actualState))
  	        add_notification($notifications[$i]);
  	    }

  	    $clueState->makeChanges(array('state' => $actualState));
  	    $clueState->doUpdate();
	    }
	    return true;
	  }
	  return false;
	}

	public function doGuessAnswer(Clue $clue, $guess) {
	  $clueState = $clue->getClueState($this);
	  $currentState = $clue->calculateClueState($this);

	  if ($currentState >= STATE_ANSWERED) {
	    add_notification('This clue has already been answered.');
	    return false;
	  } else if ($currentState < STATE_ANSWERABLE) {
	    add_notification('This clue cannot be answered yet. Try waiting or changing your location as indicated.');
	    return false;
	  }

	  if ($clue->isGuessCorrect($guess)) {
	    add_notification('Correct answer!');

	    if ($clueState == null) {
	      $clueState = new ClueState(array(
	        'team' => $this->id,
	        'clue' => $clue->getID()
	      ));
	      $clueState->doAdd();
	      $clue->setClueState($this, $clueState);
	    }
	    $clueState->makeChanges(array('state' => STATE_ANSWERED, 'answer' => $guess));
	    $clueState->doUpdate();

	    return true;
	  } else {
	    add_notification('Incorrect guess!');
	    return false;
	  }
	}

	public function getCurrentClue() {
	  Clue::getAllClues();

	  $clueStates = query('ClueState')
	    ->where(array('team' => $this->id))
	    ->selectMultiple();

    if (!$clueStates) {
      return query('Clue')
  	    ->where(array('year' => Year::current()))
  	    ->sort('time')
  	    ->limit(1)
  	    ->selectSingle();
    }

    $currentClue = head($clueStates)->getClue();
    foreach ($clueStates as $clueState) {
      $clue = $clueState->getClue();
      if ($clue && (!$currentClue || $clue->getRawTime() > $currentClue->getRawTime())) {
        $currentClue = $clue;
      }
    }

    return $currentClue;
	}
}

?>
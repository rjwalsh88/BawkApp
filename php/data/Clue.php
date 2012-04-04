<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Session.php');
require_once('Date.php');
require_once('Year.php');
require_once('ClueState.php');
require_once('CheckIn.php');
require_once('City.php');

define('STATE_HIDDEN', 0);
define('STATE_UNLOCKED', 1);
define('STATE_ANSWERABLE', 2);
define('STATE_HINTED', 3);
define('STATE_ANSWERED', 4);

class State {
	public static function getAllStates() {
		return array(
			'Hidden', 'Unlocked', 'Answerable', 'Hinted', 'Answered'
		);
	}
	public static function getStateName($state) {
		$answer = array(
			'Hidden', 'Unlocked', 'Answerable', 'Hinted', 'Answered'
		);
		return $answer[$state];
	}
}

class Answer {
	private $text;

	public function __construct($text) {
		$this->text = $text;
	}

	public function __toString() {
		if ($this->text == '*') {
			return "<i>[Anything]</i>";
		} else {
			return htmlize($this->text);
		}
	}
	public function isGuessCorrect($guess) {
		return $this->text == '*' || trim(strtolower($this->text)) == trim(strtolower($guess));
	}
	public function hashAnswer($salt) {
		if ($this->text == '*') {
			return '*';
		} else {
			return md5($salt . trim(strtolower($this->text)));
		}
	}
}

class Clue extends AdminEditableDBRecord {

  // ID, essential fields
	protected $id, $year, $name, $defaultState, $time;

	// text fields
	protected $question, $hint, $answer;

	public function getID() {
    return $this->id;
  }
	public function getName() {
		$text = $this->name ? $this->name : '(No name)';
	  return htmlize($text);
	}
	public function getDefaultState() {
    return $this->defaultState;
  }
	public function getTime() {
	  return htmlize(id(new Date($this->time))->toBawkString());
	}
	public function getRawTime() {
	  return $this->time;
	}
	public function getFormDate() {
	  return htmlize(id(new Date($this->time))->toFormDate());
	}
	public function getFormTime() {
	  return htmlize(id(new Date($this->time))->toFormTime());
	}
	public function getQuestion() {
		return htmlize($this->question);
	}
	public function getHint() {
		return htmlize($this->hint);
	}
	public function getAnswerText() {
		return htmlize($this->answer);
	}
	private function getAnswers() {
		$answers = array();
		foreach (explode("\n", $this->answer) as $answer) {
			if(trim($answer) != '') $answers[] = new Answer($answer);
		}
		return $answers;
	}

	public function getAnswerList() {
		$answers = $this->getAnswers();
		if (count($answers) == 0) return '<i>No answers.</i>';
		$text = '';
		foreach ($answers as $answer) {
			if ($text != '') $text .= ', ';
			$text .= $answer->__toString();
		}
		return $text;
	}
	public function getHashedAnswers() {
		$answers = $this->getAnswers();
    $salt = $this->getSalt();
		$text = '';
		foreach ($answers as $answer) {
			if ($text != '') $text .= ' ';
			$text .= $answer->hashAnswer($salt);
		}
		return $text;
	}
	public function getSalt() {
    return substr(md5('clue' . $this->getID()), 0, 8);
	}

	// CLUE STATES

	protected
	  $allStatesFetched = false,
    $clueStates = array();

	public function isGuessCorrect($guess) {
		$answers = $this->getAnswers();
		foreach ($answers as $answer) {
		  if($answer->isGuessCorrect($guess)) return true;
		}
		return false;
	}

  public function getClueState(Team $team) {
		$id = $team->getID();
    if (!isset($this->clueStates[$id])) {
	    $this->clueStates[$id] = query('ClueState')->where(array('team' => $id, 'clue' => $this->id))->selectSingle();
	  }
    return $this->clueStates[$id];
  }

  public function getClueStates() {
    if (!$this->allStatesFetched) {
      $items = query('ClueState')
        ->where(array('clue' => $this->id))
        ->sortDesc('time')->selectMultiple();
      $this->clueStates = array();
      foreach ($items as $item) {
	      $this->clueStates[$item->getID()] = $item;
	    }
	    $this->allStatesFetched = true;

    }
    return $this->clueStates;
  }

  public function getGameProgress() {
		$clueStates = $this->getClueStates();
		$text = '';
		$stateNames = State::getAllStates();
		foreach ($clueStates as $clueState) {
			$team = $clueState->getTeam()->getName();
			$state = fn_idx($stateNames, $clueState->getState());
			$answer = $clueState->getAnswer();
			$answer = $answer ? "\"$answer\"" : '';
			$time = $clueState->getTime();
			$text .= "$team $state $answer (at $time)<br />";
		}
		return $text;
  }

	public function getHistogram() {
		$clueStates = $this->getClueStates();
		$teams = Team::getAllTeams();

		if (count($clueStates) == 0) {
			return "<span style=\"font-size: 12px;\"><b>All hidden</b></span>";
		}

		$counts = array(0, 0, 0, 0, 0);
		foreach ($teams as $team) {
			$clueState = $this->getClueState($team);
			$state = $clueState ? $clueState->getState() : STATE_HIDDEN;
			$counts[$state]++;
		}
		if ($counts[STATE_ANSWERED] == count($teams)) {
			return "<span style=\"font-size: 12px;\"><b>All answered</b></span>";
		}

		$text = '';
		foreach (State::getAllStates() as $key => $value) {
			if ($text != '') $text .= ', ';
			$text .= "<b>" . $counts[$key] . "</b> " . $value;
		}
		return '<span style="font-size: 12px;">' . $text . "</span>";
	}

  public function setClueState(Team $team, ClueState $state) {
    $id = $team->getID();
    $this->clueStates[$id] = $state;
  }

	public function isAnswered(Team $team) {
		$clueState = $this->getClueState($team);
		return $clueState != null && $clueState->getState() == STATE_ANSWERED;
	}

	public function calculateClueState(Team $team) {
		$state = $this->defaultState;
		if ($state == STATE_ANSWERED) return $state;

		// cached as determined by prior correct answers/unlocks
		$clueState = $this->getClueState($team);
		if ($clueState != null && $clueState->getState() > $state) {
			$state = $clueState->getState();
			if ($state == STATE_ANSWERED) return $state;
		}

		if ($state < STATE_UNLOCKED && $this->qualifiesForUnlock($team)) {
  		$state = STATE_UNLOCKED;
		}

    // if unlocked, can qualify for being answerable, hint, answer
		if ($state >= STATE_UNLOCKED) {
		  if ($this->qualifiesForAnswer($team)) {
  		  return STATE_ANSWERED;
  		} else if ($state == STATE_HINTED || $this->qualifiesForHint($team)) {
  		  return STATE_HINTED;
  		} else if ($state == STATE_ANSWERABLE || $this->qualifiesForStart($team)) {
  		  return STATE_ANSWERABLE;
  		}
		}

		// fall back to the greatest thing we found so far
		return $state;
	}


	// UNLOCKING CRITERIA


  // fields
	protected $startTime, $startCity, $startDirection, $startDistance;
	protected $hintTime, $hintCity, $hintDirection, $hintDistance;
	protected $answerTime, $answerCity, $answerDirection, $answerDistance;
	protected
	  $startCityObj = UNPREPARED,
	  $hintCityObj = UNPREPARED,
	  $answerCityObj = UNPREPARED;

	public function getStartCityID() { return $this->startCity; }
	public function getStartTime() { return $this->startTime; }
	public function getStartDirection() { return $this->startDirection; }
	public function getStartDistance() { return $this->startDistance; }
	public function getStartFormDate() { return $this->startTime == null ? null : htmlize(id(new Date($this->startTime))->toFormDate()); }
	public function getStartFormTime() { return $this->startTime == null ? null : htmlize(id(new Date($this->startTime))->toFormTime()); }
	public function getStartCity() {
	  if ($this->startCityObj == UNPREPARED) {
      $this->startCityObj = $this->startCity == null ? null : City::getCity($this->startCity);
	  }
    return $this->startCityObj;
	}

	public function getHintCityID() { return $this->hintCity; }
	public function getHintTime() { return $this->hintTime; }
	public function getHintDirection() { return $this->hintDirection; }
	public function getHintDistance() { return $this->hintDistance; }
	public function getHintFormDate() { return $this->hintTime == null ? null : htmlize(id(new Date($this->hintTime))->toFormDate()); }
	public function getHintFormTime() { return $this->hintTime == null ? null : htmlize(id(new Date($this->hintTime))->toFormTime()); }
	public function getHintCity() {
	  if ($this->hintCityObj == UNPREPARED) {
      $this->hintCityObj = $this->hintCity == null ? null : City::getCity($this->hintCity);
	  }
    return $this->hintCityObj;
	}

	public function getAnswerCityID() { return $this->answerCity; }
	public function getAnswerTime() { return $this->answerTime; }
	public function getAnswerDirection() { return $this->answerDirection; }
	public function getAnswerDistance() { return $this->answerDistance; }
	public function getAnswerFormDate() { return $this->answerTime == null ? null : htmlize(id(new Date($this->answerTime))->toFormDate()); }
	public function getAnswerFormTime() { return $this->answerTime == null ? null : htmlize(id(new Date($this->answerTime))->toFormTime()); }
	public function getAnswerCity() {
	  if ($this->answerCityObj == UNPREPARED) {
      $this->answerCityObj = $this->answerCity == null ? null : City::getCity($this->answerCity);
	  }
    return $this->answerCityObj;
	}

  public function qualifiesForUnlock(Team $team) {
    $prev = $this->getPreviousClue();
    return $prev == null || $prev->isAnswered($team);
  }
  public function qualifiesForStart(Team $team) {
    return $this->qualifies($team, $this->startTime, $this->getStartCity(), $this->startDirection, $this->startDistance, true);
  }
  public function qualifiesForHint(Team $team) {
    return $this->qualifies($team, $this->hintTime, $this->getHintCity(), $this->hintDirection, $this->hintDistance, false);
  }
  public function qualifiesForAnswer(Team $team) {
    return $this->qualifies($team, $this->answerTime, $this->getAnswerCity(), $this->answerDirection, $this->answerDistance, false);
  }
  public function qualifies(Team $team, $time, $city, $direction, $distance, $defaultIfNull) {
    if ($time != null) {
			if (current_mysql_time() >= $time) return true;
		}

		if ($direction != null && $distance != null && $city != null) {
			$checkIn = CheckIn::getMostRecent($team);
			if ($checkIn != null && Direction::inRange($checkIn, $city, $direction, $distance))
		    return true;
		}

		if ($time == null && ($direction == null || $distance == null || $city == null)) return $defaultIfNull;

		return false;
  }

  // CLUE FETCHING

	private static $cache = array();
	private static $allFetched = false;

  public static function getClue($id) {
    if (!isset(self::$cache[$id])) {
	    $item = query(__CLASS__)
	      ->where(array('id' => $id, 'year' => Year::current()))->selectSingle();
	    if ($item) {
	      self::$cache[$id] = $item;
	    }
	  }
    return idx(self::$cache, $id);
  }

  public function getNextClue() {
    return query(__CLASS__)
		  ->where(array('year' => Year::current()))
      ->whereGT(array('time' => $this->time))
      ->sort('time')
      ->limit(1)
      ->selectSingle();
  }

  public function getPreviousClue() {
    return query(__CLASS__)
		  ->where(array('year' => Year::current()))
      ->whereLT(array('time' => $this->time))
      ->sortDesc('time')
      ->limit(1)
      ->selectSingle();
  }

  public static function getAllClues() {
		if (!self::$allFetched) {
			$items = query(__CLASS__)
  		  ->where(array('year' => Year::current()))
			  ->sort('time')->selectMultiple();
	    self::$cache = array();
	    foreach ($items as $item) {
	      self::$cache[$item->id] = $item;
	    }
			self::$allFetched = true;
		}
    return self::$cache;
  }

	public static function getAllClueNames() {
		$clues = self::getAllClues();
		$names = array('' => '[None]');
		foreach ($clues as $key => $clue) $names[$key] = $clue->getName();
		return $names;
	}
}

?>
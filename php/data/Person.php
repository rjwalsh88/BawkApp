<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBRecord.php');
require_once('Session.php');
require_once('Link.php');

/**
 * Data class for CS198 users, one per SUNetID that is registered with us.
 * Persons are identified by their SUNetID, or CS198 ID (id field).
 *
 * Contains unchanging information like name, contact info
 */

class Person extends OwnerAndAdminEditableDBRecord {

  // data fields
	protected $id, $sunetid, $email, $phoneNumber;
	protected $fullName, $team, $admin;

	// related data
	protected
	  $teamObj = UNPREPARED;

	public function getID() {
    return $this->id;
  }
	protected function getOwnerID() {
    return $this->id;
  }

	public function getDisplayName() {
	  return htmlize($this->fullName);
	}
	public function getEmail($link = false) {
		$text = $this->email;
		return ($link && $text) ? urlize("mailto:$text", $text) : htmlize($text);
	}
	public function getPhoneNumber() {
	  return htmlize($this->phoneNumber);
	}


	public function getTeam() {
	  if ($this->teamObj == UNPREPARED) {
      $this->teamObj = $this->team == null ? null : Team::getTeam($this->team);
	  }
    return $this->teamObj;
	}

	public function isAdmin() {
		// TODO: make browser-specific lol
    return $this->admin ? 1 : 0;
	}

	private static $personCache = array();

	public static function personExists($id) {
		return(self::getPerson($id) != null);
	}
	public static function getPerson($id) {
	  if (!isset(self::$personCache[$id])) {
	    self::$personCache[$id] = query(__CLASS__)->where(array('id' => $id))->selectSingle();
	  }
	  return self::$personCache[$id];
	}

	public static function getPersonBySUNetID($sunetid) {
	  $person = query(__CLASS__)->where(array('sunetid' => $sunetid))->selectSingle();
	  if ($person != null) {
	    self::$personCache[$person->id] = $person;
	  }
	  return $person;
	}

  public function toArray() {
    return array(
      'id' => $this->id,
      'sunetid' => $this->sunetid,
      'displayName' => $this->fullName,
    );
  }
}


?>
<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

/**
 * DateInt
 *
 * CS198 throws a lot of Dates around. The full object-oriented Date object is the Date
 * class, but just as frequently we need a lightweight representation of a Date for
 * database storage or for serialization to client-side scripts.
 */

function current_mysql_time() {
  return id(new Date())->toMySQLDate();
}

function construct_datetime($date, $time) {
  $time = strtolower(trim($time));
  $last = substr($time, -1);
  if ($last == 'a' || $last == 'p') {
    $time .= 'm';
  }

  // find the first non-digit, non-colon character, and add a missing :00
  $i = 0; $len = strlen($time);
  while ($i < $len && (ctype_digit($time[$i]) || $time[$i] == ':')) {
    $i++;
  }

  if ($i < 2) {
	  $time = substr($time, 0, $i) . ':00' . substr($time, $i);
  }

  // if AM/PM is missing, assume PM
  if (strpos($time, 'm') === false) {
    $time .= 'pm';
  }

  $dates = array(
    '3/23/2012',
    '3/24/2012',
    '3/25/2012',
  );
  $date = (int) $date;
  $time = $dates[$date] . ' ' . $time;

  return id(new Date($time))->toMySQLDate();
}

function reverseDate($dateStr) {
	$dates = array(
		'2012-03-23' => 0,
		'2012-03-24' => 1,
		'2012-03-25' => 2,
	);
	return $dates[$dateStr];
}

/**
 * Utility class to convert between MySQL dates, Unix dates, and date/time strings.
 */
class Date {
  public static function getAllDates() {
    return array(
      'Friday',
      'Saturday',
      'Sunday'
    );
  }
  public static function getAllDatesNullable() {
    return array(
      '' => '[None]',
      0 => 'Friday',
      1 => 'Saturday',
      2 => 'Sunday'
    );
  }

  private $unixTime;

  /**
   * Constructs a Date from the provided MySQLDate format, or timestamp, or now if nothing is provided
   */
  public function __construct($mysqlDate = null) {
    if ($mysqlDate === null) {
      $this->unixTime = time();
      return;
    }
    $this->unixTime = strtotime($mysqlDate);
  }

  /**
   * Converts this Date back into its underlying MySQL format.
   */
  public function toMySQLDate() {
    return date('Y-m-d H:i:s', $this->unixTime);
  }

	public function toFormDate() {
		return reverseDate(date('Y-m-d', $this->unixTime));
	}

	public function toFormTime() {
    return date('g:ia', $this->unixTime);
	}

  /**
   * Converts this Date into Unix Time, based on this CS198's time zone.
   */
  public function getUnixTime() {
    return $this->unixTime;
  }

  public function augment($deltaSeconds) {
    $this->unixTime += $deltaSeconds;
    return $this;
  }

  public function augmentHours($deltaHours) {
    $this->augment($deltaHours * 60 * 60);
    return $this;
  }

  public function augmentMinutes($deltaMinutes) {
    $this->augment($deltaMinutes * 60);
    return $this;
  }


  /*  String output: day of the week  */
  public function getDayOfWeek() {
    return date('l', $this->getUnixTime());
  }

  /*  String output: 3-letter day of the week  */
  public function getMiniDayOfWeek() {
    return substr(date('l', $this->getUnixTime()), 0, 3);
  }

  /*  String output: January 1 [this year] or December 31, 2010  */
  public function __toString() {
    $time = $this->getUnixTime();
    if (self::isThisYear($time)) return date('F j', $time);
    else return date('F j, Y', $time);
  }

  /*  String output: Saturday, January 1 [this year] or Friday, December 31, 2010  */
  public function toLongString() {
    return $this->getDayOfWeek() . ', ' . $this->__toString();
  }

  /*  String output: 1/1 or 12/31/10  */
  public function toSlashString($includeYearIfNotThisYear = true) {
    $time = $this->getUnixTime();
    if (!$includeYearIfNotThisYear) return date('n/j', $time);
    else return date('n/j/y', $time);
  }

  /*  String output: 2/14/2011  */
  public function toDatePickerString() {
    $time = $this->getUnixTime();
    return date('n/j/Y', $time);
  }

  public function toTimeString() {
    return date('g:i A', $this->unixTime);
  }

  public function toBawkString() {
    return date('l g:ia', $this->unixTime);
  }
}

date_default_timezone_set('America/Los_Angeles');
//Date::$timeZone = new DateTimeZone('America/Los_Angeles');

?>
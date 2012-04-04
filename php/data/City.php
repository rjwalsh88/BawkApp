<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('Person.php');
require_once('Session.php');
require_once('DBRecord.php');

define('DIRECTION_NORTH', 0);
define('DIRECTION_EAST', 1);
define('DIRECTION_SOUTH', 2);
define('DIRECTION_WEST', 3);
define('DIRECTION_NEAR', 4);

define('MILES_PER_DEGREE_LAT', 69.1);
define('MILES_PER_DEGREE_LONG_EQUATOR', 69.1);


class Direction {
	public static function getAllDirections() {
		return array(
			'' => '[None]',
			DIRECTION_NEAR => 'near',
			DIRECTION_NORTH => 'north of', 
			DIRECTION_SOUTH => 'south of', 
			DIRECTION_WEST => 'west of',
			DIRECTION_EAST => 'east of', 
		);
	}
	
	private static function modifyLatitude($lat,  $deltaMiles) {
	  return $lat + $deltaMiles / MILES_PER_DEGREE_LAT;
	}
	private static function modifyLongitude($lat, $long, $deltaMiles) {
	  // TODO: is cos degrees or radians?
	  return $long + $deltaMiles / (MILES_PER_DEGREE_LONG_EQUATOR * cos(deg2rad($lat)));
	}
	private static function distMiles($lat1, $long1, $lat2, $long2) {
	  $avgLat = ($lat1 + $lat2) / 2;
	  $latDist = MILES_PER_DEGREE_LAT * ($lat1 - $lat2);
	  $longDist = (MILES_PER_DEGREE_LONG_EQUATOR * cos($avgLat)) * ($long1 - $long2);
	  return sqrt($latDist * $latDist + $longDist * $longDist);
	}
	
	public static function inRange(CheckIn $checkIn, City $city, $direction, $distance) {
	  switch ($direction) {
	    case DIRECTION_NORTH:
	      $lat = self::modifyLatitude($city->getLat(), $distance);
	      return $checkIn->getLat() >= $lat;
	      
	    case DIRECTION_SOUTH:
        $lat = self::modifyLatitude($city->getLat(), -$distance);
        return $checkIn->getLat() <= $lat;
	      
      case DIRECTION_WEST:
	      $long = self::modifyLongitude($city->getLat(), $city->getLong(), -$distance);
	      return $checkIn->getLong() <= $long;
	      
	    case DIRECTION_EAST:
        $long = self::modifyLongitude($city->getLat(), $city->getLong(), $distance);
        return $checkIn->getLong() >= $long;
	      
      case DIRECTION_NEAR:
        $actualDistance = self::distMiles($checkIn->getLat(), $checkIn->getLong(),
                                          $city->getLat(), $city->getLong());
	      return $actualDistance <= $distance;
	  }
	}
}

class City extends ReadOnlyDBRecord {

  protected $id, $city, $state, $latitude, $longitude;

  public function getID() {
    return $this->id;
  }

  public function getName() {
    return htmlize($this->city . ', ' . $this->getStateAbbreviation());
  }
  
  public function getLat() {
    return $this->latitude;
  }
  public function getLong() {
    return $this->longitude;
  }

	public function getStateAbbreviation() {
		$state = $this->state;
		if ($state == 'Nevada') $state = 'Nvada';
		if ($state == 'Arizona') $state = 'Azona';
		return strtoupper(substr($state, 0, 2)); // MAD HAX. Yay Western states.
	}

	private static $cache = array();
	private static $allFetched = false;
  public static function getCity($id) {
    if (!self::$allFetched && !isset(self::$cache[$id])) {
	    self::$cache[$id] = query(__CLASS__)->where(array('id' => $id))->selectSingle();
	  }
    return self::$cache[$id];
  }
  
  public static function lookupCity($name) {
    $city = query(__CLASS__)->where(array('city' => $name))->selectSingle();

    if (!isset(self::$cache[$city->id])) {
      self::$cache[$city->id] = $city;
	  }
    return $city;
  }

  public static function getAllCities() {
		if (!self::$allFetched) {
			$items = query(__CLASS__)->sort('city')->selectMultiple();
	    self::$cache = array();
	    foreach ($items as $item) {
	      self::$cache[$item->id] = $item;
	    }
			self::$allFetched = true;
		}
    return self::$cache;
  }

	public static function getAllCityNames() {
		$cities = self::getAllCities();
		$names = array('' => '[None]');
		foreach ($cities as $key => $item) $names[$key] = $item->getName();
		return $names;
	}
}

?>
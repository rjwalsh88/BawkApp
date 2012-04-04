<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

ini_set('include_path', ini_get('include_path') . ':../php/data:../php/ui/:../php/misc');

require_once('Session.php');
require_once('Person.php');
require_once('Date.php');

require_once('CheckIn.php');

if (Session::currentPerson() && 
    Session::currentPerson()->getTeam() &&
    key_exists('latitude', $_POST) &&
    key_exists('longitude', $_POST)) {
  
  $team = Session::currentPerson()->getTeam();
  $momentsAgo = id(new Date())->augment(-30)->toMySQLDate();
  $recentCheckIns = CheckIn::getCheckIns($team, $momentsAgo);
  
  // recent check-ins, so break the cycle
  if (count($recentCheckIns) > 0) {
    echo 0;
    
  } else {
    CheckIn::doCheckIn($team, $_POST['latitude'], $_POST['longitude']);  
    
    // TODO: check to see if there is anything new, and if so, send back refresh code
    $refresh = $team->shouldRefreshClueStates();
    
    $changes = $refresh ? 1 : 0;

    echo $changes;
  }
} else {
  echo -1;
}

?>
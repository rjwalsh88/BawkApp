<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

ini_set('include_path', ini_get('include_path') . ':./php/data:./php/ui:./php/misc');

require_once('Session.php');
require_once('Person.php');
require_once('ChallengeWinner.php');
require_once('Challenge.php');

require_once('UIPage.php');
require_once('UIForm.php');


// SETUP/CONTEXT

$action = null;
$position = Session::defaultPosition();

if (key_exists('new', $_POST) && $position >= POSITION_ADMIN) {
	$challenge_name = $_POST['name'];
	$challenge_points = $_POST['points'];
	$challenge_code = $_POST['code'] ? $_POST['code'] : null;
	$action = 'new';
} else if (key_exists('delete', $_POST) && $position >= POSITION_ADMIN) {
	$challenge_id = $_POST['challenge_id'];
	$action = 'delete';
}	else if (key_exists('add', $_POST) && $position >= POSITION_ADMIN) {
	$team_id = $_POST['team_id'];
	$challenge_id = $_POST['challenge_id'];
	$action = 'add';
}	else if (key_exists('remove', $_POST) && $position >= POSITION_ADMIN) {
	$winner_id = $_POST['winner_id'];
	$action = 'remove';
} else if (key_exists('claim', $_POST)) {
	$code = $_POST['code'];
	$action = 'claim';
}

// ACTION


switch ($action) {
	case 'new':
		$challenge = new Challenge(array(
		  'year' => Year::current(),
		  'name' => $challenge_name,
		  'points' => $challenge_points,
		  'code' => $challenge_code,
		));
		$challenge->doAdd("Created challenge $challenge_name successfully.");
	  break;

	case 'delete':
		$challenge = Challenge::getChallenge($challenge_id);
		foreach ($challenge->getWinners() as $winner) {
      $winner->doRemove();
    }
		$challenge->doRemove("Deleted challenge successfully.");
	  break;

	case 'add':
	  $winner = new ChallengeWinner(array('team' => $team_id, 'challenge' => $challenge_id));
		$winner->doAdd("Added challenge winner successfully.");
	  break;

	case 'remove':
    $winner = ChallengeWinner::getChallengeWinner($winner_id);
	  $winner->doRemove("Deleted challenge winner successfully.");
	  break;

	case 'claim':
    $team = Session::currentTeam();
	  $challenge = Challenge::getChallengeByCode($code);
	  if (!$team) {
	    add_notification('You cannot claim a challenge if you have no team.');
	  } else if (!$challenge) {
	    add_notification('No challenge with this code found. If you think you received this message in error, bring your code to a coordinator in person.');
	  } else if ($challenge->getWinners()) {
	    add_notification('Someone else has already claimed the code you entered.');
	  } else {
  	  $winner = new ChallengeWinner(array('team' => $team->getID(), 'challenge' => $challenge->getID()));
  		$winner->doAdd("Claimed code successfully.");
	  }
}

// VIEW

$page = new UIPage();


function challenge_claim_form() {
  return <<<EOT
<form method="POST" action="challenges.php">
Enter Claim Code: <input type="text" name="code" />
<input type="submit" name="claim" value="Claim!" />
</form>
EOT;
}

// condition on whether user is admin
function challenge_winner($position, ChallengeWinner $winner) {
	switch ($position) {
		case POSITION_NONE:
		  return '';

		case POSITION_ACCOUNT:
		case POSITION_PLAYER:

		  return $winner->getTeam()->getName() . ' won ' . $winner->getChallenge()->getName() . ' for '
       . $winner->getChallenge()->getPoints() . ' points.';

		case POSITION_ADMIN:
			$text = $winner->getTeam()->getName() . ' won ' . $winner->getChallenge()->getName() . ' for '
	     . $winner->getChallenge()->getPoints() . ' points.';

			$id = $winner->getID();
			return <<<EOT
$text
<form method="POST" action="challenges.php">
<input type="hidden" name="winner_id" value="$id" />
<input type="submit" name="remove" value="Remove Winner" />
</form>
EOT;
	}
}
function challenge_list(Challenge $challenge) {
	$text = $challenge->getName() . ' for ' . $challenge->getPoints() . ' points, code ' . $challenge->getCode() . '.';
	$team_select = ui_select('team_id', Team::getAllTeamNames());

	$id = $challenge->getID();
	return <<<EOT
$text
<form method="POST" action="challenges.php">
$team_select
<input type="hidden" name="challenge_id" value="$id" />
<input type="submit" name="add" value="Add Winner" />
<input type="submit" name="delete" value="Delete Challenge" />
</form>
EOT;
}

if ($position >= POSITION_PLAYER) {
  $element = id(new UIContainer())->setTitle('Claim A Challenge/Prize');
	$element->addElement(UIText::exactText(challenge_claim_form()));
	$page->addElement($element);
}

$element = id(new UIContainer())->setTitle('Challenge Dashboard');
$winners = ChallengeWinner::getAllChallengeWinners();
if (count($winners) == 0) {
	$element->addElement('No challenges have been won yet.');
}
foreach ($winners as $winner) {
	$element->addElement(UIText::exactText(challenge_winner($position, $winner)));
}
$page->addElement($element);

if ($position >= POSITION_ADMIN) {
	$element = id(new UIContainer())->setTitle('All Challenges (Admin only)');
	$challenges = Challenge::getAllChallenges();
	if (count($challenges) == 0) {
		$element->addElement('No challenges.');
	}
	foreach ($challenges as $challenge) {
		$element->addElement(UIText::exactText(challenge_list($challenge)));
	}
	$page->addElement($element);

	$page->addElement(UIText::exactText(<<<EOT
<form method="POST" action="challenges.php">
Name: <input type="text" name="name" />
Points: <input type="text" name="points" />
Claim Code (optional): <input type="text" name="code" />
<input type="submit" name="new" value="Create New Challenge" />
</form>
EOT
  )->setTitle('Create Challenge (Admin only)'));
}

// STEP 5: OUTPUT PAGE

echo $page->fullPageHTML();

?>
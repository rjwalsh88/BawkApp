<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

ini_set('include_path', ini_get('include_path') . ':./php/data:./php/ui:./php/misc');

require_once('Session.php');
require_once('Person.php');
require_once('Team.php');

require_once('UIPage.php');

// SETUP/CONTEXT

$action = null;

if (key_exists('new', $_POST)) {
	$team_name = $_POST['name'];
	$action = 'new';
} else if (key_exists('delete', $_POST)) {
	$team_id = $_POST['id'];
	$action = 'delete';
}	else if (key_exists('add', $_POST)) {
	$team_id = $_POST['id'];
	$sunetid = $_POST['text'];
	$action = 'add';
}	else if (key_exists('remove', $_POST)) {
	$team_id = $_POST['id'];
	$sunetid = $_POST['text'];
	$action = 'remove';
}	else if (key_exists('rename', $_POST)) {
	$team_id = $_POST['id'];
	$team_name = $_POST['text'];
	$action = 'rename';
}

// ACTION

$position = Session::defaultPosition();
if ($position < POSITION_ADMIN) {
  $action = null;
}

switch ($action) {
	case 'new':
		$team = new Team(array(
		  'year' => Year::current(),
		  'name' => $team_name,
		));
		$team->doAdd("Created team $team_name successfully.");
	  break;

	case 'delete':
		$team = Team::getTeam($team_id);
		$team->doRemove("Deleted team successfully.");
	  break;

	case 'add':
		$user = Person::getPersonBySUNetID($sunetid);
		if ($user) {
			$user->makeChanges(array('team' => $team_id));
			$user->doUpdate("Added user $sunetid successfully.");
		} else {
			add_notification("No user with SUNetID $sunetid exists!");
		}
	  break;

	case 'remove':
		$user = Person::getPersonBySUNetID($sunetid);
		if ($user) {
			if ($user->getTeam()->getID() == $team_id) {
				$user->makeChanges(array('team' => null));
				$user->doUpdate("Removed user $sunetid successfully.");
			} else {
				add_notification("User $sunetid is not on this team!");
			}
		} else {
			add_notification("No user with SUNetID $sunetid exists!");
		}
	  break;

	case 'rename':
		$team = Team::getTeam($team_id);
		$team->makeChanges(array('name' => $team_name));
		$team->doUpdate("Changed name to $team_name successfully.");
    break;

}

// VIEW

$page = new UIPage();

// condition on whether user is admin

function team_summary($position, Team $team) {
	switch ($position) {
		case POSITION_NONE:
		  return '';

		case POSITION_ACCOUNT:
		case POSITION_PLAYER:
		  return '<b>' . $team->getName() . '</b>';

		case POSITION_ADMIN:
			$name = '<b>' . $team->getName() . '</b>';
			$id = $team->getID();
			$locLink = $team->getLocationLink();
			return <<<EOT
$name
&nbsp;&nbsp;
<form method="POST" action="teams.php">
<input type="hidden" name="id" value="$id" />
<input type="text" name="text" size="12" />
<input type="submit" name="add" value="Add Member" />
<input type="submit" name="remove" value="Remove Member" />
<input type="submit" name="rename" value="Rename" />
<input type="submit" name="delete" value="Delete Team" />
</form>
$locLink
EOT;
	}
}
function team_members($position, Team $team) {
	$members = $team->getMembers();
	if (count($members) == 0 && $position >= POSITION_ACCOUNT) {
		return 'No team members';
	}
	$text = '';
	foreach ($members as $member) {
		switch ($position) {
			case POSITION_NONE:
			  return '';

			case POSITION_ACCOUNT:
			  $text .= $member->getDisplayName() . ' ';

			case POSITION_PLAYER:
			case POSITION_ADMIN:
			  $text .= $member->getDisplayName() . ' (<i>';
				$text .= $member->getPhoneNumber() . '</i>) ';
				$text .= '<br />';
		}
	}
	return $text;
}

$teams = Team::getAllTeams();
if (count($teams) == 0 && $position >= POSITION_ACCOUNT) {
	$page->addElement('No teams');
}
foreach ($teams as $team) {
	$page->addElement(UIText::exactText(team_summary($position, $team)));
	$page->addElement(UIText::exactText(team_members($position, $team)));
}

if ($position >= POSITION_ADMIN) {
	$page->addElement(UIText::exactText(<<<EOT
<form method="POST" action="teams.php">
Name: <input type="text" name="name" />
<input type="submit" name="new" value="Create New Team" />
</form>
EOT
)->setTitle('Create Team (Admin only)'));
}

// STEP 5: OUTPUT PAGE

echo $page->fullPageHTML();

?>
<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

ini_set('include_path', ini_get('include_path') . ':./php/data:./php/ui:./php/misc');

require_once('Session.php');
require_once('Clue.php');
require_once('City.php');

require_once('UIPage.php');
require_once('UIForm.php');

// SETUP/CONTEXT

$action = null;
$view = null;

if (key_exists('new', $_POST) && Session::defaultPosition() >= POSITION_ADMIN) {
	$clue_name = $_POST['name'];
	$clue_time = construct_datetime($_POST['date'], $_POST['time']);
	$action = 'new';
	$view = 'edit';

} else if (key_exists('delete', $_POST) && Session::defaultPosition() >= POSITION_ADMIN) {
	$clue_id = $_POST['id'];
	$action = 'delete';
	$view = null;

}	else if (key_exists('edit', $_POST) && Session::defaultPosition() >= POSITION_ADMIN) {
	$clue_id = $_POST['id'];
	$action = 'edit';
	$view = 'edit';

}	else if (key_exists('answer', $_POST) && Session::defaultPosition() >= POSITION_PLAYER) {
	$clue_id = $_POST['id'];
	$latitude = $_POST['latitude'];
	$longitude = $_POST['longitude'];
	$guess = $_POST['guess'];
	$action = 'answer';
	$view = null;

}	else if (key_exists('id', $_GET) && Session::defaultPosition() >= POSITION_ADMIN) {
	$clue_id = $_GET['id'];
	$clue = Clue::getClue($clue_id);
	if ($clue != null) $view = 'edit';
}


// ACTION

function setNullIfEmpty(array $formData) {
  foreach ($formData as $field => $data) {
    if ($data === '') $formData[$field] = null;
  }
  return $formData;
}
function coagulateTimes(array $formData, array $prefixes) {
  foreach ($prefixes as $prefix) {
    $caps = ($prefix != '');
    $formDate = $prefix . ($caps ? 'Date' : 'date');
    $formTime = $prefix . ($caps ? 'Time' : 'time');
    $isNull = $formData[$formDate] == null || $formData[$formTime] == null;
    $formData[$formTime] = $isNull ? null : construct_datetime($formData[$formDate], $formData[$formTime]);
    unset($formData[$formDate]);
  }
  return $formData;
}
function coagulateLocs(array $formData, array $prefixes) {
  foreach ($prefixes as $prefix) {
    $formDistance = $prefix . 'Distance';
    $formDirection = $prefix . 'Direction';
    $formCity = $prefix . 'City';
    $isNull = $formData[$formDistance] == null || $formData[$formDirection] == null || $formData[$formCity] == null;
    if ($isNull) {
      $formData[$formDistance] = null;
      $formData[$formDirection] = null;
      $formData[$formCity] = null;
    }
  }
  return $formData;
}

switch ($action) {
  case 'new':
    $clue = new Clue(array(
      'year' => Year::current(),
	    'name' => $clue_name,
	    'time' => $clue_time,
	  ));
    $clue->doAdd('Created clue successfully.');
    break;

  case 'edit':
    $clue = Clue::getClue($clue_id);
    $form_data = setNullIfEmpty($_POST);
    $form_data = coagulateTimes($form_data, array('', 'start', 'hint', 'answer'));
    $form_data = coagulateLocs($form_data, array('start', 'hint', 'answer'));

		$clue->makeChanges($form_data);
		$clue->doUpdate('Edited clue successfully.');
    break;

  case 'delete':
    $clue = Clue::getClue($clue_id);
    foreach ($clue->getClueStates() as $clueState) {
      $clueState->doRemove();
    }
    $clue->doRemove('Deleted clue successfully.');
    break;

  case 'answer':
    $team = Session::currentTeam();
    if ($team) {
      if ($latitude && $longitude) {
    		$clue = Clue::getClue($clue_id);
    		if ($clue) {
          CheckIn::doCheckIn($team, $latitude, $longitude, $guess);
    			$team->doGuessAnswer($clue, $guess);
    		}
      } else {
        add_notification('You must have location turned on to submit clue answers.');
      }
    } else {
      add_notification('You must have a team to submit clue answers.');
    }
}

// VIEW

$page = new UIPage();
$position = Session::defaultPosition();

function answerForm($clueID, $clueSalt, $hashedAnswers, $serialized) {
	return <<<EOT
<form id="answerform" method="POST" action="clues.php">
<input type="text" name="guess" size=30 id="g$clueID" />
<input type="submit" name="answer" value="Submit" />
<br />
<span id="feedback" style="font-size: 12px; color: #FF0000;"></span>
<input type="hidden" id="clueID" name="id" value="$clueID" />
<input type="hidden" id="latitude" name="latitude" />
<input type="hidden" id="longitude" name="longitude" />
<input type="hidden" name="salt" id="s$clueID" value="$clueSalt" />
<input type="hidden" name="hashedAnswers" id="a$clueID" value="$hashedAnswers" />
</form>
<script type="text/javascript">
        var clueModel = $serialized;
</script>
EOT;
}
function displayCurrentClue(Clue $clue, $team, $clueState = STATE_UNLOCKED) {
	$text = '<span id="question">' . str_replace("\n", "<br />\n", $clue->getQuestion()) .'</span>';
	if ($clueState >= STATE_HINTED && $clue->getHint() != '') {
		$text .= '<br /><b>HINT:</b> ' . $clue->getHint();
	}
	return $text . '<br />' . answerForm(
	  $clue->getID(),
	  $clueState >= STATE_ANSWERABLE ? $clue->getSalt() : '',
	  $clue->getHashedAnswers(),
      $clue->serialize() 
	);
}
function displayPastClue(Clue $clue, $team, $clueState = STATE_UNLOCKED) {
	$text = $clue->getQuestion();
	$clueState = $team ? $clue->getClueState($team) : null;
	$answerText = $clueState ? $clueState->getAnswer() : null;
	$answerTime = $clueState ? $clueState->getTime() : null;
	$answerText = $answerText ? $answerText : '(Out of time!)';
	$answer = "<br /><i>Your Answer:</i> $answerText (at $answerTime)";
  return $text . $answer . '<br /><br />';
}
function displayClueSummary(Clue $clue) {
	$name = $clue->getName();
	$id = $clue->getID();
	$name = "<b><a href=\"clues.php?id=$id\">$name</a></b>";
  return $name . ', ' . $clue->getTime() . ': ' . $clue->getHistogram();
}
function displayClueDetails(Clue $clue) {
  return $clue->getName() . ', ' . $clue->getTime() . '<br />Valid Answers: ' . $clue->getAnswerList() .
		"<br><br>" .
		$clue->getGameProgress();
}
function displayClueEditForm(Clue $clue) {
	$dateSelect = ui_select('date', Date::getAllDates(), $clue->getFormDate());
  $defaultStateSelect = ui_select('defaultState', State::getAllStates(), $clue->getDefaultState());

	$startDateSelect = ui_select("startDate", Date::getAllDatesNullable(), $clue->getStartFormDate());
	$hintDateSelect = ui_select("hintDate", Date::getAllDatesNullable(), $clue->getHintFormDate());
	$answerDateSelect = ui_select("answerDate", Date::getAllDatesNullable(), $clue->getAnswerFormDate());

  $startCitySelect = ui_select("startCity", City::getAllCityNames(), $clue->getStartCityID());
  $hintCitySelect = ui_select("hintCity", City::getAllCityNames(), $clue->getHintCityID());
  $answerCitySelect = ui_select("answerCity", City::getAllCityNames(), $clue->getAnswerCityID());

  $startDirectionSelect = ui_select("startDirection", Direction::getAllDirections(), $clue->getStartDirection());
  $hintDirectionSelect = ui_select("hintDirection", Direction::getAllDirections(), $clue->getHintDirection());
  $answerDirectionSelect = ui_select("answerDirection", Direction::getAllDirections(), $clue->getAnswerDirection());

  return <<<EOT
<form method="POST" action="clues.php">
Name (admin use only): <input type="hidden" name="id" value="{$clue->getID()}" />
<input type="text" name="name" value="{$clue->getName()}" />
<br />
Date (for sorting): $dateSelect
<input type="text" size="8" name="time" value="{$clue->getFormTime()}" />
<br />
Default state: $defaultStateSelect
<br />
Question: <br />
<textarea name="question" cols="45" rows="8">{$clue->getQuestion()}</textarea><br />
Hint: <br />
<textarea name="hint" cols="45" rows="4">{$clue->getHint()}</textarea><br />
Answers (1 per line, * for wildcard): <br />
<textarea name="answer" cols="45" rows="4">{$clue->getAnswerText()}</textarea><br />
<br /><br />

Answering prerequisites:<br />
<input type="text" size="4" name="startDistance" value="{$clue->getStartDistance()}" />
miles {$startDirectionSelect} {$startCitySelect} or after time
{$startDateSelect} <input type="text" size="8" name="startTime" value="{$clue->getStartFormTime()}" />.<br /><br />

Hint conditions:<br />
<input type="text" size="4" name="hintDistance" value="{$clue->getHintDistance()}" />
miles {$hintDirectionSelect} {$hintCitySelect} or after time
{$hintDateSelect} <input type="text" size="8" name="hintTime" value="{$clue->getHintFormTime()}" />.<br /><br />

Auto-answering conditions:<br />
<input type="text" size="4" name="answerDistance" value="{$clue->getAnswerDistance()}" />
miles {$answerDirectionSelect} {$answerCitySelect} or after time
{$answerDateSelect} <input type="text" size="8" name="answerTime" value="{$clue->getAnswerFormTime()}" />.<br /><br />


<input type="submit" name="edit" value="Edit Clue" />
<br /><br />
Delete clue: <input type="submit" name="delete" value="Delete Clue" />
</form>
EOT;
}

if ($position < POSITION_ADMIN) {
  if ($view == 'edit') $view = null;
}

if ($position >= POSITION_PLAYER) {

  switch ($view) {
    case 'edit':
      $page->addElement(UIText::exactText(displayClueDetails($clue))->setTitle('Clue Details'));
      $page->addElement(UIText::exactText(displayClueEditForm($clue))->setTitle('Edit Clue'));
      break;

    default:
      require_js('jquery-1.5.1.min');
      require_js('jquery.form');
      require_js('checkin');
      require_js('answering');
      require_js('sjcl');

      $team = Session::currentPerson()->getTeam();
      if ($team) $team->doRefreshClueStates();
			$displayed = 0;

      $clues = Clue::getAllClues();
      $curClue = $team ? $team->getCurrentClue() : null;
      $all_clues = '';
      $past_clues = '';

      $clueState = $curClue ? $curClue->getClueState($team) : null;
      $state = $clueState ? $clueState->getState() : STATE_HIDDEN;
      if ($state >= STATE_UNLOCKED) {
        $page->addElement(UIText::exactText(displayCurrentClue($curClue, $team, $state)));
      } else {
        $page->addElement('No clues are available. Check back soon!');
      }

      foreach (array_reverse($clues) as $clue) {
        $clueState = $team ? $clue->getClueState($team) : null;
        $state = $clueState ? $clueState->getState() : STATE_HIDDEN;

        if ($state >= STATE_UNLOCKED && ($curClue == null || $curClue->getID() != $clue->getID())) {
          $past_clues .= displayPastClue($clue, $team, $state);
  				$displayed++;
        }
      }
      foreach ($clues as $clue) {
        if ($position >= POSITION_ADMIN) {
          $all_clues .= displayClueSummary($clue) . '<br />';
        }
      }

			if ($displayed == 0) $past_clues = 'No past clues.';
            $past_clues = '<div id="pastclues">' . $past_clues . '</div>';
			$text = UIText::exactText($past_clues);
			$page->addElement($text->setTitle('Past Clues'));

			if ($position >= POSITION_ADMIN) {
			  $text = UIText::exactText($all_clues);
				$page->addElement($text->setTitle('All Clues (Admin only)'));


				$datePicker = ui_select('date', Date::getAllDates());
				$page->addElement(UIText::exactText(<<<EOT
			<form method="POST" action="clues.php">
			Name (admin use only): <input type="text" name="name" />
			Date (for sorting): $datePicker <input type="text" size="8" name="time" />
			<input type="submit" name="new" value="Create New Clue" />
			</form>
EOT
			  )->setTitle('Create Clue (Admin only)'));
			}

  }
} else {
  $page->addElement("You don't have permission to view this page.");
}


// OUTPUT

echo $page->fullPageHTML();

?>
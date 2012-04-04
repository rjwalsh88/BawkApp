<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('UIBase.php');
require_once('Link.php');

function ui_text($name, $value = '') {
	return "<input type=\"text\" name=\"$name\" value=\"$value\" />";
}

function ui_select($name, $options, $selected = null) {
	$text = "<select name=\"$name\" />";
	foreach ($options as $key => $value) {
		$sel = ((string)$key == (string)$selected) ? ' selected' : '';
		$text .= "<option value=\"$key\"$sel>$value</option>";
	}
	$text .= "</select>";
	return $text;
}

/**
 * UI element class that contains and manages a HTML form.
 */
class UIForm extends UIContainer {
  private $method;
  private $action;
  private $name;
  private $encoding;
  private $hasDatePicker;
  private $hasMultiSelect;
  
  /**
   * Constructs the UIForm.
   *
   * $name : Form name. Currently not used for anything important.
   * $method : Form method. GET by default
   * $action : Form action. Current page by default
   */
  public function __construct($name, $method = 'GET', $action = null) {
    parent::__construct();
    if ($action == null) $action = Server::currentPageName();
    $this->method = $method;
    $this->action = $action;
    $this->name = $name;
    $this->encoding = '';
    $this->hasDatePicker = false;
    $this->hasMultiSelect = false;
    require_js('validate');
    require_css('form');
  }
  
  /**
   * Changes the encoding type of the form, e.g. to allow file uploads.
   */
  public function setEncoding($encoding) {
    $this->encoding = " enctype=\"" . $encoding . "\"";
  }
  protected function html() {
    $text = "<form method=\"" . $this->method . "\" action=\""
      . $this->action . "\"" . $this->encoding . " name=\"" . $this->name . 
      "\" onSubmit=\"return validate(document." . $this->name . ")\" >";
    $text .= parent::html();
    return $text . "</form>";
  }
}


/**
 * Customized UI helper class for selecting groups of people
 *
 * Works closely with ~/js/multiselect.js
 */
class UIMultiSelect {
  private static $counter = 0;
  
  private $name;
  private $context;
  private $fieldId;
  private $people;
  private $selected;
  
  /**
   * Creates a MultiSelect.
   *
   * $name : name of the form input that will be given the data
   * $people : an array of Person objects that can be selected
   * $context : a Context object describing the context that people are being
   *   picked in, to determine which people are Staff, Residents, EAs
   */
  public function __construct($name, $people, QuarterContext $context) {
    self::$counter++;
    $this->name = $name;
    $this->context = $context;
    $this->fieldId = 'multiselect' . self::$counter;
    $this->people = $people;
    $this->selected = array();
    require_js('multiselect');
  }
  
  /**
   * Marks certain users as selected by default
   * $selected : an array of Person IDs that are selected by default
   */
  public function setSelected($selected) {
    $this->selected = $selected;
  }
  
  /**
   * Serializes this people selector into valid, escaped HTML.
   */
  public function html() {
    $text = '<div class="multiselect">';
    $formname = $this->name . '[]';
    $fieldId = $this->fieldId;
    $counter = 0;
    foreach ($this->people as $user) {
      $id = $user->id;
      $personName = $user->getDisplayName();
      $position = $user->getPosition($this->context);
      if ($position >= MIN_STAFF) $position = MIN_STAFF;
      
      $present = (in_array($id, $this->selected));
      $sel = $present ? ' checked' : '';
      $style = $present ? 'multiselecty' : 'multiselectn';
      $sharedid = $fieldId . '_' . $counter;
      $aid = $sharedid . 'a';
      $iid = $sharedid . 'i';
      $pid = $sharedid . 'p';
      $text .= "<input id=\"$pid\" type=\"hidden\" value=\"$position\" />";
      $text .= "<label id=\"$aid\" class=\"$style\">";
      $text .= "<input id=\"$iid\" type=\"checkbox\" name=\"$formname\" value=\"$id\" ".
               "onclick=\"multiSelectCheckbox('$sharedid')\"$sel />";
      $text .= $personName . "</label>";
      $counter++;
    }
    $categories = array(
      -1 => 'All',
      MIN_STAFF => 'Staff',
      POSITION_RESIDENT => 'Residents',
      POSITION_EA => 'EAs',
    );
    $text .= '</div>' . 'Select: ';
    foreach ($categories as $cat => $catName) {
      if ($cat != -1) $text .= ' &middot; ';
      $text .= "<a onclick=\"multiSelect('$fieldId', $counter, $cat)\">$catName</a>";
    }
    $text .= '<br />' . 'Deselect: ';
    foreach ($categories as $cat => $catName) {
      if ($cat != -1) $text .= ' &middot; ';
      $text .= "<a onclick=\"multiDeselect('$fieldId', $counter, $cat)\">$catName</a>";
    }
    return $text;
  }
}

?>
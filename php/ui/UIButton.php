<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('Link.php');

/**
 * UI helper class that represents a navigational button that can be easily
 * attached to a UIBase-implementing element or UITableRow.
 *
 * Buttons are tightly interwoven with images, forms, CSS, and JS.
 * This class helps with a lot of the grimy details.
 *
 * Buttons are used for three tasks:
 * 1) Links to other pages, e.g. edit or help
 * 2) Javascript events (e.g. buttons for move up/down)
 * 3) Form submission (use the UIConfirmButton subclass)
 */
class UIButton {
  private $name;
  private $link;
  private $javascript;
  private $icon;
  private $iconName;
  private $hideText;
  
  /**
   * Constructs a Hyperlink button.
   *
   * $name : raw text of the button
   * $script : raw Javascript command
   * $icon : name of the icon: ~/images/icons/button_[$icon].png. Uses $name if not present
   * $hideText : true to have a round text-less button
   */
  public static function linkButton($name, $link, $icon = null, $hideText = false) {
    return new UIButton($name, htmlspecialchars($link), null, $icon, $hideText);    
  }
  
  /**
   * Constructs a Javascript button.
   *
   * $name : raw text of the button
   * $script : raw Javascript command
   * $icon : name of the icon: ~/images/icons/button_[$icon].png. Uses $name if not present
   * $hideText : true to have a round text-less button
   */
  public static function javascriptButton($name, $script, $icon = null, $hideText = false) {
    return new UIButton($name, '#', $script, $icon, $hideText);
  }
  protected function __construct($name, $safeLink, $javascript, $icon = null, $hideText = false) {
    if ($icon === null) $icon = $name;
    $this->name = htmlize($name);
    $this->link = ' href="' . $safeLink . '"';
    if ($javascript == null) {
      $this->javascript = '';
    } else {
      $this->javascript = ' onclick="' . $javascript . '"';     
    }
    $this->hideText = $hideText;
    $this->iconName = strtolower($icon);
    $this->icon = 'images/buttons/' . str_replace(' ', '_', strtolower($icon)) . '.png';
    if (!file_exists($this->icon) && !file_exists('../' . $this->icon)) $this->icon = null;
    require_css('button');
  }
  
  /**
   * Generates the HTML for this button. Pass the HTML into a UIBase separately if needed.
   */
  public function html() {
    $linkClass = $this->hideText ? 'roundbutton' : 'button';
    $imgClass = $this->hideText ? 'roundbuttonicon' : 'buttonicon';
    $text = "<a class=\"$linkClass button". $this->iconName . "\"" . $this->link . $this->javascript . ">";
    $text .=
      "<img alt=\"" . $this->name . "\" title=\"" . $this->name . "\" class=\"$imgClass\" border=0 src=\"" . 
      $this->icon . "\" />";
    if (!$this->hideText) $text .= $this->name;
    $text .= "</a>";
    return $text;
  }
  
  /**
   * Generates the HTML for this button, forcing exclusion of text.
   */
  public function icon() {
    return "<a class=\"roundbutton button". $this->iconName . "\"" . $this->link . $this->javascript . ">" .
      "<img alt=\"" . $this->name . "\" title=\"" . $this->name . "\" class=\"roundbuttonicon\" border=0 src=\""
      . $this->icon . "\" /></a>";
  }
}

/**
 * Variant of UIButton that embeds and submits a form when clicked, asking the
 * user for confirmation.
 */
class UIConfirmButton extends UIButton {
  private static $id = 0;
  private static function nextConfirmID() {
    self::$sunetid++;
    return self::$sunetid;
  }
  private $form;
  
  /**
   * Constructor.
   *
   * $name : raw button text
   * $message : dialog message to ask user
   * $formParams : associate array of form parameters
   * $icon : name of the icon: ~/images/icons/button_[$icon].png. Uses $name if not present
   * $hideText : true to have a text-less round button
   * $formMethod : the method of the form, POST by default
   * $formAction : the action of the form, current page by default
   */
  public function __construct($name, $message, $formParams, $icon = null, $hideText = false, $formMethod = 'POST', $formAction = null) {
    parent::__construct($name, '#', "doConfirm('" . js_reencode($message)
      . "', document." . 
      ($id = 'confirm' . self::nextConfirmID()) . ");", $icon, $hideText);
    if ($formAction == null) $formAction = Server::currentPageName();
    $this->form = "<form class=inline name=\"$id\" method=\"$formMethod\" action=\"$formAction\">";
    foreach ($formParams as $name => $value) {
      $valueEsc = inputize($value);
      $this->form .= "<input type=\"hidden\" name=\"$name\" value=\"$valueEsc\" />";
    }
    $this->form .= "</form>";
    require_js('confirm');
    require_css('form');
  }
  public function html() {
    return $this->form . parent::html();
  }
  public function icon() {
    return $this->form . parent::icon();
  }
}

/**
 * Handy UI element class that contains and displays UIButtons laid out vertically.
 */
class UIToolBox extends UIBase {
  private $buttons = null;
  
  public function __construct($title = null) {
    parent::__construct($title);
  }
  public function addButton(UIButton $button) {
    $this->buttons[] = $button;
    return $this;
  }
  protected function html() {
    $text = '<div class="toolbox">';
    foreach ($this->buttons as $button) $text .= $button->html();
    $text .= '</div>';
    return $text;
  }
}

?>
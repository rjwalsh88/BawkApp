<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('Session.php');
require_once('UIBase.php');

/**
 * Site navigation global array. Determines the tabs that appear.
 *
 * Maps the name of a php page to a 2-element array of its title and min visibility level.
 *   example.php => array('Example Tab Title', POSITION_SL)
 * 
 * The order that these appear here is the same as the order on the final page.
 *
 * The names of icons are generated automatically from the name of the PHP file,
 * and should be located at ~/images/icons/[example].png
 */

// feature URL => title, minimum visibility level
$GLOBAL_NAVIGATION = array(
  'clues.php' => array('Clues', POSITION_ACCOUNT),
  'challenges.php' => array('Challenges', POSITION_ACCOUNT),	
  'teams.php' => array('Teams', POSITION_ACCOUNT),
);

/**
 * UI element that contains and manages all elements of an entire CS198 page.
 *
 * Handles the page title, JS and CSS code, header, navigation, and layout.
 */

class UIPage extends UIContainer {

  private $pageTitle = null;
  private $modal = false;
  
  public function __construct() {
    parent::__construct();
    require_css('header');
    require_css('body');
  }
  
  /**
   * Makes this page "modal", so no navigation or breadcrumb appears.
   */
  public function setModal($modal) {
    $this->modal = $modal;
    return $this;
  }

  /**
   * Sets the page's title, which renders as the HTML title.
   *
   * Overrides setTitle in UIBase
   */
  public function setTitle($title) {
    $this->pageTitle = $title;
    return $this;
  }
  
  private function getLoginInfo() {
    $text = '';
    if ($this->modal) return $text;
    $id = Session::currentSUNetID();
    if (!$id) return 'Not logged in.';
    $user = Session::currentPerson();
    if (!$user) return 'No account.';
    $text .= '<span class="loginname">'.$user->getDisplayName().'</span>';
    $pos = Session::defaultPosition();
    $text .= "<br />\n" . Session::positionOrTeamName($pos);
    return $text;
  }
  private function getNavigationHTML() {
    $login = $this->getLoginInfo();
    $text = "<div class=\"logininfo\">$login</div>";
    if ($this->modal) return $text;
    $level = Session::defaultPosition();
    global $GLOBAL_NAVIGATION;
    $name = Server::currentPageName();
    foreach ($GLOBAL_NAVIGATION as $link => $array) {
      $disp = $array[0];
      $imageFile = "images/nav/" . substr($link, 0, -4) . ".png";
      if (file_exists(realpath($imageFile))) {
        $imageHtml = "<img class=\"largeicon\" src=\"$imageFile\" />&nbsp;";
      } else {
        $imageHtml = '';
      }
      if ($level >= $array[1]) {
        if ($link == $name) {
          $text .= "<a class=navsel href=\"$link\">";
        } else {
          $text .= "<a class=nav href=\"$link\">";
        }
        $text .= $imageHtml . $disp . "</a>";	
      }
    }
    return $text;

  }
  
  /**
   * Woohoo! This serializes your entire CS198 page into a string.
   *
   * Only call this when you've added everything you want to add to your page.
   * Go ahead and echo it!
   */
  public function fullPageHTML() {
    // HEAD with TITLE, JAVASCRIPT
    $text = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
    $text .= "\n<html>\n\t<head>\n\t\t<title>";
    $text .= $this->pageTitle ? $this->pageTitle . " | BAWK" : "BAWK";
    $text .= "</title>\n";
    $text .= render_required_js();
    $text .= render_required_css();

    if (Session::isMobile()) {
      $text .= <<<EOT
        <meta name="viewport"
          content="width=device-width,
          minimum-scale=1.0, maximum-scale=1.0" />
EOT;
    }

    // BREADCRUMB/NAVIGATION
    $navigation = $this->getNavigationHTML();
    $text .= "\t</head>\n\t<body>";
    $text .= "<link rel=\"shortcut icon\" href=\"images/site/favicon.png\" />";
    $text .= "<link rel=\"apple-touch-icon\" href=\"images/site/touch-icon.png\" />";
    $text .= "<table border=0 cellspacing=0 cellpadding=0 width=100%>";
    $text .= <<<EOT
<tr class=header><td class=headerside></td>
<td class=header><div class="navigation">$navigation</div></td>
<td class=headerside></td></tr>
<tr><td class=bodyside>&nbsp;</td><td class=body>
EOT;

    $text .= "<div class=notifications>";
    $text .= render_all_notifications(); // NOTIFICATIONS
    $text .= "</div>";

    $text .= parent::fullHTML(1); // ELEMENTS

    // FOOTER
    $year = date('Y');
    $text .= <<<EOT
</td><td class=bodyside>&nbsp;</td></tr>
EOT;

    $text .= <<<EOT
<tr><td colspan=3 class=footer>&nbsp;<br />&nbsp;<br />
  &copy; $year. All Rights Reserved.<br />&nbsp;
</td></tr>
EOT;

    $text .= <<<EOT
</table>
</body>
</html>
EOT;
    return $text;
  }
}



?>
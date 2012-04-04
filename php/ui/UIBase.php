<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */


/**
 * Base class of CS198 UI Library.
 * Every class that extends UIBase must implement the abstract html() function,
 * which must return the inner HTML of the body of this UI.
 *
 * All UIBase-extending classes are meant to be block-level. Individual table cells, 
 * small text snippits, subsections of forms, and buttons are not part of the UIBase family.
 * For those, create a UIText, UITable, or UIForm and place the elements in that.
 *
 * Methods for setting a title, block size, style, and adding buttons are provided.
 * These are generated automatically in the HTML surrounding the HTML returned by
 * html().
 * 
 * To create a UI, you can nest any UIBase-extending element within a
 * UIContainer, and then make sure everything is contained within a UIPage.
 */

abstract class UIBase {

  protected $title = null;
  protected $headerLevel = null;
  private $buttons = null;
  private $id = null;
  private $inline = '';
  private $pagelet = '';
  private $blockWidth = '';
  private $blockHeight = '';
  private $hideButtons = false;

  protected function __construct($title = null) {
    $this->title = $title;
    $this->id = null;
    $buttons = array();
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * Hides buttons attached to this block unless the block is being hovered over.
   */
  public function hideButtons() {
    $this->hideButtons = true;
    return $this;
  }
  
  /**
   * Renders this element with CSS style display:block and specfied width/height.
   * display:block is the default for UI elements, so this just forces a width/height.
   */
  public function setBlock($blockWidth, $blockHeight = null) {
    $this->blockWidth = "width:{$blockWidth}px;";
    if ($blockHeight) $this->blockHeight = "height:{$blockHeight}px;";
    $this->inline = '';
    return $this;
  }

  /**
   * Renders this element with CSS display:inline-block and specfied width/height.
   * Use this to line up elements side-by-side if space provides.
   */
  public function setInlineBlock($blockWidth, $blockHeight = null) {
    $this->blockWidth = "width:{$blockWidth}px;";
    if ($blockHeight) $this->blockHeight = "height:{$blockHeight}px;";
    $this->inline = ' ib';
    return $this;
  }
  
  /**
   * Adds a CSS class to this element's containing div,
   * to be used for adding a "pagelet"-like color/border.
   * See pggray, pghelp, etc. in body.css
   */
  public function setPageletClass($class) {
    $this->pagelet = ' ' . $class;
    return $this;
  }
  
  /**
   * Makes this component reloadable by AJAX calls, by ensuring that
   * reloading scripts are included and the containing div is given a DOM id.
   */
  public function setReloadable($id) {
    $this->id = $id;
    require_js('reload');
    return $this;
  }
  
  /**
   * Renders only the HTML within this element's containing div; to be used
   * by AJAX scripts for repopulating this element.
   */
  public function getReloadHTML($headerLevel) {
    $this->headerLevel = $headerLevel;
    return $this->html();
  }
  
  /**
   * Adds a button to the right of the title of this element.
   */
  public function addButton(UIButton $button) {
    $this->buttons[] = $button;
    return $this;
  }
  
  /**
   * Renders this entire element into valid, complete, escaped HTML,
   * including its scaffolding (title, buttons, etc).
   */
  protected final function fullHTML($headerLevel) {
    $text = "";
    $style = "";
    $reveal = ($this->hideButtons && $this->buttons) ? ' revealbutton' : '';
    if ($this->blockWidth || $this->blockHeight) {
      $style = " style=\"{$this->blockWidth}{$this->blockHeight}\"";
    }
    if ($headerLevel > 1) {
      $text .= "<div class=\"uic$headerLevel{$this->pagelet}{$this->inline}{$reveal}\"$style>\n";
    }

    $this->headerLevel = $headerLevel;
    if ($this->title != null || count($this->buttons) > 0) {
      $text .= "<div class=t$headerLevel>";
      if ($this->title != null) $text .= "<h$headerLevel>" . $this->title . "</h$headerLevel>";
      if (count($this->buttons) > 0) {
        $hide = ($this->hideButtons) ? ' hidebutton nofloat' : '';
        $text .= "<span class=\"buttons$hide\">&nbsp;&nbsp;";
        foreach ($this->buttons as $button) $text .= $button->html();
        $text .= "</span>";
      }			
      $text .= "</div>";
    }
    if ($this->id != null) $text .= "<span id=\"" . $this->id . "\">";
    $text .= $this->html();
    if ($this->id != null) $text .= "</span>";
    if ($headerLevel > 1) {
      $text .= "</div>\n";
    }

    return $text;
  }
  
  /**
   * Implementing classes must extending this function.
   * Must return valid, complete, escaped HTML for the interior of this element.
   * 
   * Invoked by the fullHTML() function, which is invoked by the html() function
   * of this element's container.
   */
  protected abstract function html();
}


/**
 * UI element class that can have other UI elements nested within it.
 *
 * Manages the "header level" system, where nested elements with titles have
 * successively smaller titles the deeper the nesting.
 */
class UIContainer extends UIBase {
  protected $elements;
  public function __construct($title = null) {
    parent::__construct($title);
    $this->elements = array();
  }
  
  /**
   * Adds a child element to this UIContainer, in top-to-bottom order.
   * 
   * Can also be used as a shortcut of creating a new UIText wrapped around a string
   * provided as a parameter. See UIText class.
   */
  public function addElement($element) {
    if (is_string($element)) {
      $this->elements[] = new UIText($element);
    } else {
      assert($element instanceof UIBase);
      $this->elements[] = $element;
    }
    return $this;
  }
  
  protected function html() {
    $text = '';
    foreach ($this->elements as $element) {
      $nextHeaderLevel = $this->headerLevel;
      if (($this->title != null || ($this instanceof UIPage)) && $nextHeaderLevel < 4)
        $nextHeaderLevel++;
      $text .= $element->fullHTML($nextHeaderLevel);
    }
    return $text;
  }
}

/**
 * UI element class that renders simple text, and optionally handles columns.
 */
class UIText extends UIBase {
  private $text;
  private $cols;
  private $replace;
  
  /**
   * Constructs a new UIText that does not replace newline chars with HTML line breaks.
   * 
   * $text : must be valid, escaped HTML
   */
  public static function exactText($text) {
    $text = new UIText($text);
    $text->replace = false;
    return $text;
  }

  /**
   * Constructs a new UIText that replaces newline chars with HTML line breaks.
   * 
   * $text : must be valid, escaped HTML, but newlines are OK and converted to HTML breaks
   * $title : optional
   * $cols : number of columns to render, 1 by default
   */
  public function __construct($text, $title = null, $cols = 1) {
    parent::__construct();
    if (is_numeric($title)) {
      $cols = $title;
      $title = null;
    }
    $this->text = $text;
    $this->cols = $cols;
    $this->replace = true;
    if ($title != null) $this->title = $title;
  }
  protected function html() {
    if ($this->cols == 1) {
      if ($this->replace) return str_replace("\n", "<br />\n", $this->text);
      else return $this->text;
    } else {
      return $this->htmlColumns();
    }
  }
  private function htmlColumns() {
    $arr = explode("\n", $this->text);
    $ncols = $this->cols;
    $result = '';
    if (count($arr) < $ncols * 2) {
      $first = true;
      $result .= "<table border=0><tr><td valign=top>\n";
      foreach ($arr as $item) {
        if (!$first) $result .= "<br />\n";
        else $first = false;
        $result .= $item;
      }
      $result .= "</td></tr></table>\n";
    } else {
      $j = 0;
      $result .= "<table class=\"columns\"><tr>\n";
      for ($i = 0; $i < $ncols; $i++) {
        $result .= "<td valign=top>";
        while ($j < count($arr) * ($i + 1) / $ncols) {
          $result .= $arr[$j] . "<br />";
          $j++;
        }
        $result .= "</td>\n";
      }
      $result .= "</tr></table>\n";
    }
    return $result;
  }
}

$GLOBAL_PAGE_NOTIFICATIONS = array();

/**
 * Add a text notification to be displayed at the top of the page, e.g. a
 * successful database transaction or an error.
 *
 * $notification : plaintext to be displayed.
 */
function add_notification($notification, $icon = null) {
  global $GLOBAL_PAGE_NOTIFICATIONS;
  $GLOBAL_PAGE_NOTIFICATIONS[] = htmlize($notification);
}

/**
 * Retrieves the notifications supplied to add_notification as valid, escaped HTML.
 */
function render_all_notifications() {
  global $GLOBAL_PAGE_NOTIFICATIONS;
  return implode('<br />', $GLOBAL_PAGE_NOTIFICATIONS);
}

$GLOBAL_JS_INCLUDES = array();

/**
 * Ensures that a JavaScript file is referenced by the resulting HTML page.
 *
 * $js : filename of js file to include in ~/js/, minus the extension
 */
function require_js($js) {
  global $GLOBAL_JS_INCLUDES;
  $GLOBAL_JS_INCLUDES[$js] = true;
}

/**
 * Generates HTML to fetch the JS files supplied to require_js as valid, escaped HTML.
 */
function render_required_js() {
  global $GLOBAL_JS_INCLUDES;
  $text = '';
  foreach ($GLOBAL_JS_INCLUDES as $name => $_) {
    $text .= "<script language=\"JavaScript\" type=\"text/javascript\" " .
             "src=\"js/$name.js\"></script>\n";
  }
  return $text;
}

$GLOBAL_CSS_INCLUDES = array();

/**
 * Ensures that a Stylesheet file is referenced by the resulting HTML page.
 *
 * $css : filename of css file to include in ~/css/, minus the file extension.
 */
function require_css($css) {
  global $GLOBAL_CSS_INCLUDES;
  $GLOBAL_CSS_INCLUDES[$css] = true;
}

/**
 * Generates HTML to fetch the CSS files supplied to require_css as valid, escaped HTML.
 */
function render_required_css() {
  global $GLOBAL_CSS_INCLUDES;
  $text = '';
  foreach ($GLOBAL_CSS_INCLUDES as $name => $_) {
    $text .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"css/$name.css\" />\n";
  }
  if (Session::isMobile()) {
    $text .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"css/spacing_mobile.css\" />\n"; 
  } else {
    $text .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"css/spacing_web.css\" />\n";
  }
  return $text;
}

?>
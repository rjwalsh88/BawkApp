<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

error_reporting(0);

include_once('Session.php');

/**
 * Awesome utility class that tells you everything you need to know about
 * your execution environment!
 */
class Server {
  const PRODUCTION_ROOT = 'https://stanford.edu/class/cs198/cgi-bin/bawk/';
  const ERROR_LOG_LOCATION = '/afs/ir/class/cs198/cgi-bin/bawklog/error.log';
  
  private static $scriptName = null;
  private static $scriptNameParts = null;
  private static $coopWebRoot = null;
  private static $pageName = null;
  static $parentFolderName = null;

  public static function initialize() {
    self::$scriptName = $_ENV['SCRIPT_URI'];
    assert(!empty(self::$scriptName));
    $parts = self::$scriptNameParts = explode('/', self::$scriptName);
    $count = count($parts);
    self::$pageName = $parts[$count - 1];
    assert(substr(self::$pageName, -4) == '.php');
    self::$parentFolderName = $parts[$count - 2];
    if (self::isCron()) {
      self::$coopWebRoot = implode('/', array_slice($parts, 0, $count - 2)) . '/bawk/';
    } else {
      $rootDepth = (self::isAsync() || self::isCron()) ? ($count - 2) : ($count - 1);
      self::$coopWebRoot = implode('/', array_slice($parts, 0, $rootDepth)) . '/';
    }
    if (self::isProduction()) {
      set_error_handler(array('Server', 'logError'));
    } else {
      error_reporting(E_ALL | E_STRICT | E_NOTICE);
    }
  }
  
  public static function logError($errno, $errmsg, $filename, $linenum, $vars) {
    $errortype = array(
      E_ERROR              => 'Error',
      E_WARNING            => 'Warning',
      E_PARSE              => 'Parsing Error',
      E_CORE_ERROR         => 'Core Error',
      E_CORE_WARNING       => 'Core Warning',
      E_COMPILE_ERROR      => 'Compile Error',
      E_COMPILE_WARNING    => 'Compile Warning',
      E_USER_ERROR         => 'Person Error',
      E_USER_WARNING       => 'Person Warning',
      E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
    );  
    if (!array_key_exists($errno, $errortype)) return;
    $type = $errortype[$errno];
    $dt = date("Y-m-d H:i:s (T)");
    $id = Session::currentSUNetID();
    
    $err = "[$dt|$id] $type: $errmsg, in $filename at line $linenum\n";
    error_log($err, 3, self::ERROR_LOG_LOCATION);
    
    // shouldn't trigger this handler, but just in case
    if ($errno == E_PARSE || $errno == E_CORE_ERROR) exit();
  }

  /**
   * The page knows its page name by default. Sometimes you want to override
   * it with a different name, because forms look to Server for the current
   * page name to submit to.
   *
   * This mostly used with async calls.
   */
  public static function setPageName($name) {
    self::$pageName = $name;
  }
  
  /**
   * The current page name, always [something].php
   */
  public static function currentPageName() {
    return self::$pageName;
  }
  
  public static function coopWebRoot() {
    return self::$coopWebRoot;
  }
  public static function isAsync() {
    return self::$parentFolderName == 'async';
  }
  public static function isCron() {
    return self::$parentFolderName == 'bawkcron';
  }
  public static function isProduction() {
    return self::$coopWebRoot == self::PRODUCTION_ROOT;
  }
}

Server::initialize();

/**
 * Escapes the specified raw text for use in HTML. Always use this function
 * or a variant of it when displaying user-supplied strings!
 *
 * A small number of UI classes handle this automatically (e.g. UIButton, Hyperlink),
 * but when in doubt, do it yourself.
 *
 * Optionally, pass in a character limit to truncate the text.
 */
function htmlize($text, $charLimit = null) {
  if ($charLimit) $text = truncate($text, $charLimit);
  return htmlentities($text, ENT_QUOTES);
}

/**
 * Escapes the specified raw text for use within input tags.
 * Same as htmlize(), but it's semantically distinct...
 */
function inputize($text) {
  return htmlentities($text, ENT_QUOTES);
}

/**
 * Turns the provided link data into a proper, escaped HTML hyperlink.
 * 
 * $link : the URL as a string, or a Linkable object that provides URL and text information
 * $text : the raw text, of the URL is a string
 * $class : optional CSS class to add to the link
 * $charLimit : optional character limit to truncate the text to
 */
function urlize($link, $text = null, $class = null, $charLimit = null) {
  if (is_string($link)) {
    assert($text !== null);
    $linkObject = new Hyperlink(new LinkData($link, $text));
  } else {
    assert($link instanceof Linkable);
    $linkObject = new Hyperlink($link, $text);
  }
  return $linkObject->getLinkHTML($class, $charLimit);
}

/**
 * Weird edge-case helper function that takes already HTML escaped text
 * and also encodes it for inclusion in a JavaScript string constant
 */
function js_reencode($text) {
  return htmlspecialchars(addslashes(htmlspecialchars_decode($text, ENT_QUOTES)), ENT_QUOTES);  
}

/**
 * Interface that helps generate and manage links.
 * If a database object (e.g. person, menu, job) has its own URL, this should
 * link to the URL and its text should be its name or title.
 *
 * Both the link and the text should be be raw, not escaped.
 */
interface Linkable {
  public function getLinkText();
  public function getLink();
}

/**
 * Interface that helps generate and manage links.
 * If a database object (e.g. person, menu, job) can be edited, this should
 * link to the main editing URL and its text should be "Edit [something]".
 *
 * Both the link and the text should be be raw, not escaped.
 */
interface Editable {
  public function getEditLinkText();
  public function getEditLink();
}

/**
 * Since there's no such thing as a "static" interface, there's no way to have
 * Classes themselves (as opposed to Objects) easily export a link that can
 * be used to create them, or visit them in a certain Context.
 *
 * Instead, we have functions like the following in classes:
 *   public static function getContextLinkData(QuarterContext $context);
 *   public static function getCreateLinkData();
 *   public static function getClassLinkData();
 *
 * These should all return a Linkable, and classes can easily implement these 
 * by returning a LinkData.
 *
 * Both the link and the text provided to the constructor should be be raw, not escaped.
 */
class LinkData implements Linkable {
  private $text, $url;
  public function __construct($url, $text) {
    $this->text = $text;
    $this->url = $url;
  }
  public function getLinkText() { return $this->text; }
  public function getLink() { return $this->url; }
}


/**
 * A Hyperlink wraps around a Link. Think of a Link/Linkable/LinkData as the
 * data itself--URL and text, and a Hyperlink as a full, consistent representation
 * of the data, for use on the Breadcrumb.
 *
 * The urlize() function also uses this same mechanism.
 */
abstract class IHyperlink {
  public final function getLinkHTML($class = null, $charLimit = null) {
    $link = $this->getLink();
    $text = $this->getLinkText();
    if ($charLimit) $text = truncate($text, $charLimit);
    $classHTML = $class ? " class=\"$class\"" : '';
    $url = htmlspecialchars($link);
    $text = htmlentities($text, ENT_QUOTES);
    return "<a href=\"$url\"$classHTML>$text</a>";
  }
  public abstract function getLink();
  public abstract function getLinkText();
}

/**
 * Hyperlink that wraps itself around a Linkable.
 */
class Hyperlink extends IHyperlink {
  protected $linkable;
  private $customText;
  public function __construct(Linkable $linkable = null, $customText = null) {
    $this->linkable = $linkable;
    $this->customText = $customText;
  }
  public function getLink() { return $this->linkable->getLink(); }
  public function getLinkText() {
    if ($this->customText != null) return $this->customText; 
    return $this->linkable->getLinkText();
  }
}

/**
 * Hyperlink that wraps itself around an Editable.
 */
class EditHyperlink extends IHyperlink {
  protected $linkable;
  public function __construct(Editable $linkable = null) {
    $this->linkable = $linkable;
  }
  public function getLink() { return $this->linkable->getEditLink(); }
  public function getLinkText() { return $this->linkable->getEditLinkText(); }
}

/**
 * Hyperlink that wraps itself around an arbitrary method of your choice, on
 * a specific object.
 *   e.g. new CustomHyperlink($myRecord, 'Coffee')
 *   would invoke the following methods for its URL:
 *   $myRecord->getCoffeeLink()
 *   $myRecord->getCoffeeLinkText()
 *
 * If you provide extra arguments, those get passed on to the function:
 *   e.g. new CustomHyperlink($myRecord, 'Fruit', 'blueberries')
 *   would invoke the following methods for its URL:
 *   $myRecord->getFruitLink('blueberries')
 *   $myRecord->getFruitLinkText('blueberries')
 */
class CustomHyperlink extends IHyperlink {
  private $record, $linkClass, $args;
  public function __construct(DBRecord $record, $linkClass) { // and more args if necessary
    $this->record = $record;
    $this->linkClass = $linkClass;
    $this->args = array_slice(func_get_args(), 2);    
  }
  public function getLink() { 
    return call_user_func_array(array($this->record, 'get' . $this->linkClass . 'Link'), $this->args);
  }
  public function getLinkText() { 
    return call_user_func_array(array($this->record, 'get' . $this->linkClass . 'LinkText'), $this->args);
  }
}

/**
 * Hyperlink class family that invokes a static method of a class, rather
 * than an instance method of an object to retrieve a link.
 */
class StaticHyperlink extends Hyperlink {
  public function __construct($typeClass) { // and more args if necessary
    $linkClass = get_class($this);
    $args = array_slice(func_get_args(), 1);
    $linkable = call_user_func_array(array($typeClass, 'get' . substr($linkClass, 0, -9) . 'LinkData'), $args);
    assert($linkable instanceof Linkable);
    $this->linkable = $linkable;
  }
}

/**
 * General static hyperlink, e.g. for linking to a dashboard page.
 *
 * new ClassHyperlink('HelpRequest') invokes the following method for its URL:
 *   HelpRequest::getClassLinkData()
 *
 * If you provide extra arguments, those get passed on to the function.
 */
class ClassHyperlink extends StaticHyperlink { }

/**
 * General creation hyperlink, e.g. for creating a new object with no parent.
 *
 * new CreateHyperlink('HelpRequest') invokes the following method for its URL:
 *   HelpRequest::getCreateLinkData()
 *
 * If you provide extra arguments, those get passed on to the function.
 */
class CreateHyperlink extends StaticHyperlink { }

/**
 * General context hyperlink, e.g. for a dashboard page that describes a context.
 * E.g. 'Person', context of Terra 2010-2011 would link to the Directory page for that house/year.
 *
 * new ContextHyperlink('HelpRequest', $context) invokes the following method for its URL:
 *   HelpRequest::getContextLinkData($context)
 */
class ContextHyperlink extends StaticHyperlink { 
  public function __construct($typeClass, QuarterContext $context) {
    parent::__construct($typeClass, $context);
  }
}

/**
 * For categories not covered above, provides a flexible static link method lookup.
 *
 * new CustomClassHyperlink('HelpRequest', 'Penguin') invoke the following methods for its URL:
 *   HelpRequest::getPenguinLinkData($context)
 * 
 * If you provide extra arguments, those get passed on to the function.
 */
class CustomClassHyperlink extends Hyperlink {
  public function __construct($typeClass, $linkClass) { // and more args if necessary
    $args = array_slice(func_get_args(), 2);
    $linkable = call_user_func_array(array($typeClass, 'get' . $linkClass . 'LinkData'), $args);
    assert($linkable instanceof Linkable);
    $this->linkable = $linkable;
  }
}

?>
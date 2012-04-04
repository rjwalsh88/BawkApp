<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

require_once('DBTable.php');
require_once('Utils.php');


/**
 * Base class for objects in the Database.
 *
 * Handles object uniqueness, and insert/update/delete transactions, and keeps
 * track of object dirtiness.
 * Requires that a specific security model be used with this object to determine
 * which users are able to edit them.
 */
abstract class DBRecord {

  private $dirty = false;
  private function getDBTable() {
    global $GLOBAL_DATATABLES;
    return DBTable::getTable($GLOBAL_DATATABLES[get_class($this)]);
  }

  /**
   * Must return 'id' for CS198 except in weird cases
   */
  protected function getIdColumns() {
    return 'id';
  }

  /**
   * "Security" methods. Before any database writes, these are checked, and
   * if they return false, an exception is thrown. See the EditingDBRecord
   * classes below for all the common implementations of these.
   */
  public abstract function canAdd();
  public abstract function canEdit();
  public abstract function canRemove();

  // switched off temporarily when doing DB imports
  public static $fromPostData = true;
  public static function filterValue($value) {
    if (is_string($value) && self::$fromPostData) {
      return str_replace("\r\n", "\n", stripslashes($value));
    }
    return $value;
  }

  public static function camelCase($key) {
    if (substr($key, -2) == 'ID') { // id or sunetid
      return strtolower($key);
    }
    return strtolower(substr($key, 0, 1)) . substr($key, 1);
  }

  /**
   * Constructs a database record. Does not do any database writes.
   *
   * $arr : associative array of initial values. These should come directly
   *        from POST requests, after any format conversion (e.g. checkboxes to bitmap).
   *        This constructor handles stripping slashes.
   */
  public function __construct(array $arr) {

    $columns = $this->getDBTable()->getRawColumns();
    $camelColumns = $this->getDBTable()->getCamelColumns();

    foreach ($arr as $key => $value) {
      if (is_int($key)) continue;
      if (in_array($key, $columns)) {
        $key = self::camelCase($key);
        $this->$key = self::filterValue($value);
      } else if (in_array($key, $camelColumns)) {
        $this->$key = self::filterValue($value);
      }
    }
  }

  /**
   * Returns true if makeChanges() has been called.
   */
  public final function isDirty() {
    return $this->dirty;
  }

  /**
   * Preferred method to change the non-ID columns of an existing record
   * fetched from the database.
   *
   * $arr : associative array of new values. These should come directly
   *        from POST requests, after any format conversion (e.g. checkboxes to bitmap).
   *        This method handles stripping slashes.
   */
  public final function makeChanges(array $arr) {
    $columns = $this->getDBTable()->getCamelColumns();
    $ids = (array)($this->getIDColumns());
    foreach ($arr as $key => $value) {
      if (in_array($key, $columns) && (!in_array($key, $ids)))
        $this->$key = ($value === null) ? null : str_replace("\r\n", "\n", stripslashes($value));
    }
    $this->dirty = true;
  }

  private function asArray() {
    $array = array();
    $columns = $this->getDBTable()->getCamelColumns();
    foreach (get_object_vars($this) as $key => $value) {
      if (!in_array($key, $columns)) continue;
      if ($this->getDBTable()->isAutoIncrement($key)) continue; // ignore, is assigned by database
      if ($this->getDBTable()->isTimestamp($key)) continue; // ignore, is assigned by database
      $array[$key] = $value;
    }
    return $array;
  }

  private function asSubArray($idPart) {
    $columns = $this->getDBTable()->getCamelColumns();
    $idColumns = (array)($this->getIdColumns());
    $array = array();
    foreach (get_object_vars($this) as $key => $value) {
      if (!in_array($key, $columns)) continue;
      if ($idPart && !in_array($key, $idColumns)) continue;
      if (!$idPart && in_array($key, $idColumns)) continue;
      if (!$idPart && $this->getDBTable()->isAutoIncrement($key)) continue;
      if (!$idPart && $this->getDBTable()->isTimestamp($key)) continue;
      $array[$key] = $value;
    }
    return $array;
  }

  /**
   * Inserts this object into its database.
   * Invoked by doAdd, which handles permissions. Call doAdd to do the inserting.
   * Override this method to add behavior (e.g. updating something else)
   */
  protected function add() {
    if ($this->canAdd()) {
      $insertID = $this->getDBTable()->add($this->asArray());
      if ($insertID) {
        $primaryKey = $this->getDBTable()->getCamelPrimaryKey();
        if ($primaryKey) $this->$primaryKey = $insertID;
      }
      $this->dirty = false;
    } else {
      $class = get_class($this);
      throw new DBPermissionException("You do not have privileges to create a $class.");
    }
  }

  /**
   * Updates this object in its database.
   * Invoked by doUpdate, which handles permissions. Call doUpdate to do the updating.
   * Override this method to add behavior (e.g. updating something else)
   */
  protected function update() {
    if ($this->canEdit()) {
      $this->getDBTable()->update($this->asSubArray(true), $this->asSubArray(false));
      $this->dirty = false;
    } else {
      $class = get_class($this);
      throw new DBPermissionException("You do not have privileges to edit this $class.");
    }
  }

  /**
   * Deletes this object from its database.
   * Invoked by doRemove, which handles permissions. Call doRemove to do the deleting.
   * Override this method to add behavior (e.g. updating something else)
   */
  protected function remove() {
    if ($this->canRemove()) {
      $this->getDBTable()->remove($this->asSubArray(true));
    } else {
      $class = get_class($this);
      throw new DBPermissionException("You do not have privileges to remove this $class.");
    }
  }

  /**
   * Inserts this object into its database, if allowed.
   * Optionally adds a notification message on success. Always adds a notification on error.
   */
  public function doAdd($successMessage = null) {
    try {
      $this->add();
      if ($successMessage) add_notification($successMessage);
      return true;
    } catch (DBException $ex) {
      $this->handleException($ex);
      return false;
    }
  }

  /**
   * Updates this object in its database, if allowed.
   * Optionally adds a notification message on success. Always adds a notification on error.
   */
  public function doUpdate($successMessage = null) {
    try {
      $this->update();
      if ($successMessage) add_notification($successMessage);
      return true;
    } catch (DBException $ex) {
      $this->handleException($ex);
      return false;
    }
  }

  /**
   * Deletes this object from its database, if allowed.
   * Optionally adds a notification message on success. Always adds a notification on error.
   */
  public function doRemove($successMessage = null) {
    try {
      $this->remove();
      if ($successMessage) add_notification($successMessage);
      return true;
    } catch (DBException $ex) {
      $this->handleException($ex);
      return false;
    }
  }

  protected function handleException(DBException $ex) {
    // for now, print it. Maybe later, log it.
    add_notification($ex->getMessage());
  }
}

/**
 * Security model for DBRecord objects.
 *
 * An object can be added, edited, or deleted if the user is staff in the
 * context of this object.
 * Implementing classes simply need to implement QuarterContext so a Level for
 * the user can be calculated.
 */
abstract class TeamEditableDBRecord extends DBRecord {

  /*  Override and return true to prevent important items from being deleted.  */
  protected function inUse() { return false; }

  /*  returns a single team ID */
  protected abstract function getTeamID();

  private function isDBRecordEditable() {
    return (Session::currentTeam() && Session::currentTeam()->getID() == $this->getTeamID()) ||
      Session::defaultPosition() >= POSITION_ADMIN;
  }
  public function canAdd() {
    return $this->isDBRecordEditable();
  }
  public function canEdit() {
    return $this->isDBRecordEditable();
  }
  public function canRemove() {
    return !$this->inUse() && $this->isDBRecordEditable();
  }
}



/**
 * Security model for DBRecord objects.
 *
 * An object can be added, edited, or deleted if the user is staff in the
 * context of this object, or is one of the owners of this object.
 * Implementing classes simply need to implement QuarterContext so a Level for
 * the user can be calculated, and getOwnerID to know who the owners are.
 */
abstract class OwnerAndAdminEditableDBRecord extends DBRecord {
  // public abstract function getQuarterContext(); -> already in QuarterContext

  /*  returns a single owner ID, or an array of owner IDs  */
  protected abstract function getOwnerID();

  /*  Override and return true to prevent important items from being deleted.  */
  protected function inUse() { return false; }

  private function isDBRecordEditable() {
    return in_array(Session::currentPerson()->getID(), arrayize($this->getOwnerID())) ||
      Session::defaultPosition() >= POSITION_ADMIN;
  }
  public function canAdd() {
    return $this->isDBRecordEditable();
  }
  public function canEdit() {
    return $this->isDBRecordEditable();
  }
  public function canRemove() {
    return !$this->inUse() && $this->isDBRecordEditable();
  }
}

/**
 * Security model for DBRecord objects.
 *
 * An object can be added, edited, or deleted if the user is a site admin.
 */
abstract class AdminEditableDBRecord extends DBRecord {

  /*  Override and return true to prevent important items from being deleted.  */
  protected function inUse() { return false; }

  public function canAdd() {
    return Session::defaultPosition() >= POSITION_ADMIN;
  }
  public function canEdit() {
    return Session::defaultPosition() >= POSITION_ADMIN;
  }
  public function canRemove() {
    return !$this->inUse() && Session::defaultPosition() >= POSITION_ADMIN;
  }
}

/**
 * Security model for DBRecord objects.
 *
 * An object cannot be edited, added, or deleted.
 */
abstract class ReadOnlyDBRecord extends DBRecord {
  public function canAdd() {
    return false;
  }
  public function canEdit() {
    return false;
  }
  public function canRemove() {
    return false;
  }
}

class DBException extends Exception {
  // nothing unique, just a class hierarchy
}

class DBPermissionException extends DBException {
  // nothing unique, just a class hierarchy
}

class DBLogicException extends DBException {
  // nothing unique, just a class hierarchy
}

?>
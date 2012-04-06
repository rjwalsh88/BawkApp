<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */


/**
 * Super-important mapping of class names to database table names.
 */
$GLOBAL_DATATABLES = array(
  'Person' => 'People',
  'Team' => 'Teams',
  'City' => 'Cities',
  'Clue' => 'Clues',
  'CheckIn' => 'CheckIns',
  'ClueState' => 'ClueStates',
  'Challenge' => 'Challenges',
  'ChallengeWinner' => 'ChallengeWinners',
  'Year' => 'Years',
);

/**
 * Shorthand for DBQuery constructor.
 */
function query($className) {
  return new DBQuery($className);
}

/**
 * SQL query builder. Looks really neat as PHP, and makes queries difficult to mess up,
 * easy to reuse and rewrite. Uses CS198 PHP Class names, instead of database table
 * names, for flexibility and consistency. Handles parameter escaping, so SQL injection
 * is protected against.
 *
 * Retrieves DBRecord objects of the class provided in the constructor. Does some neat
 * post processing tasks such as re-mapping and re-indexing query result arrays.
 *
 * It's pretty limited for complex queries, so please add to its functionality! It's
 * also hard to figure out what it does from reading the class, so look for examples
 * of usage in the code base for best results.
 */
class DBQuery {
  private
    $className,
    $tableName,
    $table,
    $join = array(),
    $outerJoin = array(),
    $where = array(),
    $conj = ' AND ',
    $sort = array(),
    $descending = '',
    $filters = array(),
    $limit = null,
    $indexBy = null,
    $map = null;

  /**
   * Begin a query by specifying the table you want to access, by its Class name rather
   * than its table name. __CLASS__ is useful, so you can just copy-paste queries between
   * classes. The objects that are ultimately returned by this query are of this class.
   *
   * This is equivalent to the FROM part of a SQL query.
   */
  public function __construct($className) {
    global $GLOBAL_DATATABLES;
    $this->className = $className;
    $this->tableName = $GLOBAL_DATATABLES[$className];
    $this->table = DBTable::getTable($this->tableName);
  }

  /**
   * LEFT JOIN another table specified by className ON the fields specified by $field are equal.
   * They have to have the same name in both tables...
   */
  public function outerJoin($className, $field = null) {
    return $this->joinHelper($className, $field, $this->outerJoin);
  }

  /**
   * JOIN another table specified by className ON the fields specified by $field are equal.
   * They have to have the same name in both tables...
   */
  public function join($className, $field = null) {
    return $this->joinHelper($className, $field, $this->join);
  }

  private function joinHelper($className, $field, &$target) {
    global $GLOBAL_DATATABLES;
    $otherTableName = $GLOBAL_DATATABLES[$className];
    if ($field === null) {
      $field = $className;
    }
    if (is_array($field)) {
      $on = '';
      foreach ($field as $key => $value) {
        if ($on != '') $on .= ' AND ';
        $on .= $this->tableName . '.' . $key . ' = ' . $otherTableName . '.' . $value;
      }
    } else {
      $on = $this->tableName . '.' . $field . ' = ' . $otherTableName . '.ID';
    }
    $target[$otherTableName] = $on;
    return $this;
  }

  /*
   * where___() calls add WHERE clauses:
   *
   * $constraints : associative array of columns to values
   *   e.g. where(array($key1 => $value1, $key2 => $value2)) results in:
   *   WHERE $key1 = $value1 AND $key2 = $value2
   *
   * $inClassName : if doing a JOIN, specifies the class
   *   e.g. where(array($key => $value), $className) (and $className => TableName) results in:
   *  WHERE TableName.$key = $value
   *
   * Multiple calls to where___() are AND'ed.
   */
  public function where(array $constraints, $inClassName = null) {
    return $this->whereOperator('=', $constraints, $inClassName);
  }
  /*  Like where(), WHERE $key >= $value  */
  public function whereGE(array $constraints, $inClassName = null) {
    return $this->whereOperator('>=', $constraints, $inClassName);
  }
  /*  Like where(), WHERE $key <= $value  */
  public function whereLE(array $constraints, $inClassName = null) {
    return $this->whereOperator('<=', $constraints, $inClassName);
  }
  /*  Like where(), WHERE $key > $value  */
  public function whereGT(array $constraints, $inClassName = null) {
    return $this->whereOperator('>', $constraints, $inClassName);
  }
  /*  Like where(), WHERE $key < $value  */
  public function whereLT(array $constraints, $inClassName = null) {
    return $this->whereOperator('<', $constraints, $inClassName);
  }
  /*  Like where(), but ORs everything. Doesn't work well with other calls to where___()  */
  public function whereAny(array $constraints, $inClassName = null) {
    $this->conj = ' OR ';
    return $this->whereOperator('=', $constraints, $inClassName);
  }

  private function whereOperator($operator, array $constraints, $inClassName = null) {
    global $GLOBAL_DATATABLES;
    $inClass = $inClassName ? ($GLOBAL_DATATABLES[$inClassName] . '.') : '';
    foreach ($constraints as $key => $value) {
      if ($value === null) {
        assert($operator == '='); // cannot be >, >=, etc with 'null'
        $this->where[] = $inClass.$key . ' IS NULL';
      } else {
        $this->where[] = $inClass.$key . " $operator '" . mysql_real_escape_string($value) . "'";
      }
    }
    return $this;
  }

  /**
   * Sorts by the specified columns.
   */
  public function sort(/* variable arguments */) {
    $this->sort = func_get_args();
    return $this;
  }

  /**
   * Sorts by the specified columns, all descending.
   * (Currently no way to sort by some ascending, some descending)
   */
  public function sortDesc(/* variable arguments */) {
    $this->sort = func_get_args();
    $this->descending = ' DESC';
    return $this;
  }

  /* Does a MYSQL Limit on this number */
  public function limit($limit) {
    $this->limit = (int) $limit;
    return $this;
  }

  /**
   * Specifies a field of each result object to use as its key in the associative
   * array returned by selectMultiple().
   */
  public function indexBy($index) {
    $this->indexBy = $index;
    return $this;
  }

  /**
   * Specifies a field of each result object to use as its value in place of the object,
   *  in the associative array returned by selectMultiple().
   */
  public function map($index) {
    $this->map = $index;
    return $this;
  }

  /**
   * Specifies a predicate method of each result object to use as a criteria for
   * whether to include it in the result array. The first parameter is the name of
   * the method, the following parameters are passed as paramters to this method.
   */
  public function filter(/* variable arguments */) {
    $args = func_get_args();
    $filterName = array_shift($args);
    $this->filters[$filterName] = $args;
    return $this;
  }

  private function formQuerySuffix() {
    $suffix = ' ';
    foreach ($this->join as $table => $on) {
      $suffix .= " JOIN $table ON $on";
    }
    foreach ($this->outerJoin as $table => $on) {
      $suffix .= " LEFT JOIN $table ON $on";
    }

    $whereSuffix = '';
    foreach ($this->where as $constraint) {
      if (strlen($whereSuffix) > 0) $whereSuffix .= $this->conj;
      else $whereSuffix .= ' WHERE ';
      $whereSuffix .= $constraint;
    }
    $suffix .= $whereSuffix;

    if (count($this->sort) > 0) {
      $sortSuffix = '';
      foreach ($this->sort as $sort) {
        if (strlen($sortSuffix) > 0) $sortSuffix .= ", ";
        $sortSuffix .= $sort . $this->descending;
      }
      $suffix .= ' ORDER BY ' . $sortSuffix;
    }
    if ($this->limit !== NULL) {
      $suffix .= ' LIMIT ' . $this->limit;
    }

    return $suffix;
  }

  /**
   * Returns an object if it matches the query, or null if no object matched.
   */
  public function selectSingle() {
    $suffix = $this->formQuerySuffix();
    $arr = $this->table->selectSingle($suffix);
    $class = $this->className;
    $result = null;

    DBRecord::$fromPostData = false;
    if ($arr) $result = new $class($arr);
    DBRecord::$fromPostData = true;

    return $result;
  }

  /**
   * Returns an associative array of all objects matching, or an empty array
   * if no objects match.
   */
  public function selectMultiple() {
    $suffix = $this->formQuerySuffix();
    $select = $this->outerJoin ? $this->tableName : null;
    $arrs = $this->table->selectMultiple($suffix, $select);
    $class = $this->className;
    $result = array();

    foreach ($arrs as $key => $arr) {
      DBRecord::$fromPostData = false;
      $obj = new $class($arr);
      DBRecord::$fromPostData = true;

      $pass = true;
      foreach ($this->filters as $filterMethod => $params) {
        if (!call_user_func_array(array($obj, $filterMethod), $params)) $pass = false;
      }
      if ($this->indexBy) {
        $index = $this->indexBy;
        if (method_exists($obj, $index)) {
          $key = call_user_func(array($obj, $index));
        } else {
          $key = $obj->$index;
        }
      }
      if ($this->map) {
        $index = $this->map;
        if (method_exists($obj, $index)) {
          $obj = call_user_func(array($obj, $index));
        } else {
          $obj = $obj->$index;
        }
      }
      if ($pass) $result[$key] = $obj;
    }


    return $result;
  }
}

/**
 * DBTable operates on the closest to MySQL level, executing queries directly
 * and dealing with data as arrays, and table names (rather than PHP class names).
 * As much as possible, use the DBRecord and DBQuery classes as abstractions
 * that deal with queries and records as Objects.
 */
class DBTable {
  public static function connect() {
    mysql_connect("localhost", "root", "3w5e11264sgsf");
    mysql_select_db("bawkapp") or die(mysql_error());
  }
  private static $connected = false;
  private static $tables;

  public static function getTable($tableName) {
    if (!self::$connected) {
      self::$connected = true;
      self::connect();
      self::$tables = array();
    }
    if (!array_key_exists($tableName, self::$tables)) {
      self::$tables[$tableName] = new DBTable($tableName);
    }
    return self::$tables[$tableName];
  }

  private $tableName;
  private $columns = UNPREPARED;
  private $camelColumns = UNPREPARED;

  private function __construct($tableName) {
    $this->tableName = $tableName;
  }

  private function fetchColumns() {
    $result = mysql_query("SHOW COLUMNS FROM " . $this->tableName);
    $this->columns = array();
    $this->camelColumns = array();
    while ($row = mysql_fetch_array($result)) {
      $this->columns[$row['Field']] = $row;
      $camelColumn = DBRecord::camelCase($row['Field']);
      $this->camelColumns[$camelColumn] = $row;
    }
  }

  public function getRawColumns() {
    if ($this->columns == UNPREPARED) $this->fetchColumns();
    return array_keys($this->columns);
  }

  public function getCamelColumns() {
    if ($this->columns == UNPREPARED) $this->fetchColumns();
    return array_keys($this->camelColumns);
  }

  public function isAutoIncrement($field) {
    if ($this->columns == UNPREPARED) $this->fetchColumns();
    return $this->camelColumns[$field]['Extra'] == 'auto_increment';
  }
  public function isTimestamp($field) {
    if ($this->columns == UNPREPARED) $this->fetchColumns();
    return $this->camelColumns[$field]['Extra'] == 'on update CURRENT_TIMESTAMP';
  }
  public function getPrimaryKey() {
    if ($this->columns == UNPREPARED) $this->fetchColumns();
    $pri = null;
    foreach ($this->columns as $field => $column) {
      if ($column['Key'] == 'PRI') {
        if ($pri == null) $pri = $field; // 1 pri key we can handle
        else return null; // 2+ pri keys we can't handle
      }
    }
    return $pri;
  }
  public function getCamelPrimaryKey() {
    if ($this->columns == UNPREPARED) $this->fetchColumns();
    $pri = null;
    foreach ($this->camelColumns as $field => $column) {
      if ($column['Key'] == 'PRI') {
        if ($pri == null) $pri = $field; // 1 pri key we can handle
        else return null; // 2+ pri keys we can't handle
      }
    }
    return $pri;
  }

  public function selectSingle($suffix, $select = null) {
    $result = $this->selectQuery($suffix, $select);
    return mysql_fetch_array($result);
  }
  public function selectMultiple($suffix, $select = null) {
    $result = $this->selectQuery($suffix, $select);
    $arr = array();
    $primaryKey = $this->getPrimaryKey();

    while ($row = mysql_fetch_array($result)) {
      if ($primaryKey) $arr[$row[$primaryKey]] = $row; // associative!
      else $arr[] = $row; // non-associative
    }
    return $arr;
  }
  private function selectQuery($suffix, $select = null) {
    if ($select == null) {
      $selectStr = '*';
    } else {
      $selects = (array) $select;
      foreach ($selects as &$selectIndiv) $selectIndiv .= '.*';
      $selectStr = implode(', ', $selects);
    }
    $query = "SELECT " . $selectStr . " FROM " . $this->tableName . $suffix;
    $result = mysql_query($query);
    if (!$result) {
      echo "Error with query: " . $query . "<br />\n";
      echo mysql_error() . "<br />\n";
      debug_print_backtrace();
    }
    return $result;
  }

  public function errorMsg() {
    return mysql_error();
  }

  public function add($keyvalues) {
    $keys = ''; $values = '';
    foreach ($keyvalues as $key => $value) {
      if ($value === null) continue; // do nothing. let it be the default value chosen by the DB
      if ($keys != '') $keys .= ', ';
      if ($values != '') $values .= ', ';
      $keys .= $key;
      $value = mysql_real_escape_string(stripslashes($value));
      $values .= '\'' . $value . '\'';
    }
    $table = $this->tableName;
    $query = "INSERT INTO $table ($keys) VALUES($values)";
    $success = mysql_query($query);
    if ($success) {
      return mysql_insert_id();
    } else {
      throw new DBLogicException('Add: ' . $query . ': '. mysql_error());
    }
  }

  public function update($idkeyvalues, $changekeyvalues) {
    $id = '';
    $change = '';
    foreach ($idkeyvalues as $key => $value) {
      if ($id != '') $id .= ' AND ';
      $value = mysql_real_escape_string(stripslashes($value));
      $id .= $key . ' = \'' . $value . '\'';
    }
    foreach ($changekeyvalues as $key => $value) {
      if ($change != '') $change .= ', ';
      if ($value === null) {
        $change .= $key . ' = NULL';
      } else {
        $value = mysql_real_escape_string(stripslashes($value));
        $change .= $key . ' = \'' . $value . '\'';
      }
    }
    $table = $this->tableName;
    $query = "UPDATE $table SET $change WHERE $id";
    $success = mysql_query($query);
    if (!$success) {
      throw new DBLogicException('Update: ' . $query . ': ' . mysql_error());
    }
  }

  public function remove($idkeyvalues) {
    $id = '';
    foreach ($idkeyvalues as $key => $value) {
      if ($id != '') $id .= ' AND ';
      $value = mysql_real_escape_string(stripslashes($value));
      $id .= $key . ' = \'' . $value . '\'';
    }
    $table = $this->tableName;
    $query = "DELETE FROM $table WHERE $id";
    $success = mysql_query($query);
    if (!$success) {
      throw new DBLogicException('Remove: ' . $query . ': ' . mysql_error());
    }
  }
}

?>
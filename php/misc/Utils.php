<?php

/**
 * @author Matt Bush
 * (c) 2011 Stanford CS198 program. All rights reserved.
 */

/* 
 * invalid UTF-8 sequence that can be used as a unique constant for
 * un-fetched/stale fields of Database objects in cases where
 * null would not suffice (e.g. null is a valid fetched state)
 */
define('UNPREPARED', utf8_decode("(unprepared\xEE\xFF\xFF)"));

/* 
 * identity function.
 * Disallowed by PHP: new Foo()->bar()  and  (new Foo())->bar
 * Worse: $x = new Foo(); $x->bar();
 * Better: id(new Foo())->bar();
 */
function id($x) {
  return $x;
}

/* 
 * index into array.
 * Disallowed by PHP: $elem = $x->someArray()['index'];
 * Too wordy: $arr = $x->someArray(); $elem = $arr['index'];
 * Better: fn_idx($x->someArray(), 'index');
 */
function fn_idx(array $arr, $index) {
  return $arr[$index];
}

/* 
 * Index into array, with default.
 * Too wordy: (array_key_exists('index', $arr)) ? $arr['index'] : null;
 * Better: idx($arr, 'index');
 */
function idx(array $arr, $index, $default = null) {
  if (array_key_exists($index, $arr)) {
    return $arr[$index];
  }
  return $default;
}

/* 
 * Returns the first value of the array.
 *
 * Like PHP's builtin reset, but doesn't require a reference to the array, or
 * cause side effects.
 */
function head(array $arr) {
  return reset($arr);
}

/* 
 * if $var is not already an array, make an array with:
 * 0 elements if the arg is null or false
 * 1 element otherwise (int, str, object)
 *
 * like (array) $arg, but different behavior for false and object
 */
function arrayize($var) {
  if (is_array($var)) return $var;
  if ($var === null || $var === false) return array();
  return array($var);
}

/* 
 * Calculates and returns the mode of the provided array.
 *
 * Returns only 1 mode, so only use this if you're OK with it arbitrarily breaking ties.
 */
function array_mode(array $arr) {
  $histogram = array_count_values($arr);
  arsort($histogram);
  return head(array_keys($histogram));
}

/* 
 * Replaces "My Super Long String is so very Super Super Long" with
 * with "My Super Long String is ..."
 *
 * Good for navigation, titles, and other places you don't want an extraordinarily
 * long user-supplied name to interfere with a layout.
 */
function truncate($string, $length) {
  if (strlen($string) > $length) return substr($string, 0, $length-2) . '...';
  else return $string;
}

?>
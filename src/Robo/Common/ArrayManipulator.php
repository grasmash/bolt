<?php

namespace Acquia\Blt\Robo\Common;

use Dflydev\DotAccessData\Data;

/**
 *
 */
class ArrayManipulator {

  /**
   * Merges arrays recursively while preserving.
   *
   * @param array $array1
   * @param array $array2
   *
   * @return array
   *
   * @see http://php.net/manual/en/function.array-merge-recursive.php#92195
   */
  public static function arrayMergeRecursiveDistinct(
    array &$array1,
    array &$array2
  ) {
    $merged = $array1;
    foreach ($array2 as $key => &$value) {
      if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
        $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key],
          $value);
      }
      else {
        $merged[$key] = $value;
      }
    }
    return $merged;
  }

  /**
   * Converts dot-notated keys to proper associative nested keys.
   *
   * @param $array
   *
   * @return array
   */
  public static function expandFromDotNotatedKeys($array) {
    $data = new Data();

    // @todo Make this work at all levels of array.
    foreach ($array as $key => $value) {
      $data->set($key, $value);
    }

    return $data->export();
  }

  /**
   * @param $array
   *
   * @return array
   */
  public static function flattenToDotNotatedKeys($array) {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
    $result = array();
    foreach ($iterator as $leafValue) {
      $keys = array();
      foreach (range(0, $iterator->getDepth()) as $depth) {
        $keys[] = $iterator->getSubIterator($depth)->key();
      }
      $result[join('.', $keys)] = $leafValue;
    }

    return $result;
  }


  /**
   * Converts a multi-dimensional array to a human-readable flat array.
   *
   * Used primarily for rendering tables via Symfony Console commands.
   *
   * @param $array
   *
   * @return array
   */
  public static function convertArrayToFlatTextArray($array) {
    $rows = [];
    $max_line_length = 80;
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        $flattened_array = self::flattenToDotNotatedKeys($value);
        foreach ($flattened_array as $sub_key => $sub_value) {
          $rows[] = [
            "$key.$sub_key",
            wordwrap($sub_value, $max_line_length, "\n", TRUE)
          ];
        }
      }
      else {
        if ($value === TRUE) {
          $contents = 'true';
        }
        elseif ($value === FALSE) {
          $contents = 'false';
        }
        else {
          $contents = wordwrap($value, $max_line_length, "\n", TRUE);
        }
        $rows[] = [$key, $contents];
      }
    }

    return $rows;
  }
}

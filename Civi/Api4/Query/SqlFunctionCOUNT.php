<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

namespace Civi\Api4\Query;

/**
 * Sql function
 */
class SqlFunctionCOUNT extends SqlFunction {

  protected static $category = self::CATEGORY_AGGREGATE;

  protected static $params = [
    [
      'prefix' => ['', 'DISTINCT', 'ALL'],
      'expr' => 1,
      'must_be' => ['SqlField', 'SqlWild'],
      'cant_be' => [],
    ],
  ];

  /**
   * Reformat result as array if using default separator
   *
   * @see \Civi\Api4\Utils\FormattingUtil::formatOutputValues
   * @param string $value
   * @return string|array
   */
  public function formatOutputValue($value) {
    return (int) $value;
  }

  /**
   * @return string
   */
  public static function getTitle(): string {
    return ts('Count');
  }

}

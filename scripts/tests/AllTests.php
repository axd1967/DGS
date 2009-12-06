<?php

require_once 'PHPUnit/Framework.php';

// list all tests
require_once 'BitSetTest.php';
require_once 'ProfileTest.php';
require_once 'TimeFormatTest.php';
require_once 'GameAddTimeTest.php';
require_once 'Game_SettingsHelperTest.php';

/*!
 * \class AllTests
 * \brief Suite including all phpunit-tests for DGS.
 */
class AllTests
{
   public static function suite()
   {
      $suite = new PHPUnit_Framework_TestSuite('DGS Test-Suite');

      // list all tests
      $suite->addTestSuite('BitSetTest');
      $suite->addTestSuite('ProfileTest');
      $suite->addTestSuite('TimeFormatTest');
      $suite->addTestSuite('GameAddTimeTest');
      $suite->addTestSuite('Game_SettingsHelperTest');

      return $suite;
   }
}

?>

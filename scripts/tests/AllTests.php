<?php

require_once 'PHPUnit/Framework.php';

require_once 'include/connect2mysql.php';

// list all tests
require_once 'BitSetTest.php';
require_once 'ProfileTest.php';
require_once 'TimeFormatTest.php';
require_once 'GameAddTimeTest.php';
require_once 'Game_SettingsHelperTest.php';
require_once 'EntityTest.php';
require_once 'EntityDataTest.php';

/*!
 * \class AllTests
 * \brief Suite including all phpunit-tests for DGS.
 */
class AllTests
{
   public static function suite()
   {
      global $TheErrors;
      $TheErrors->set_mode(ERROR_MODE_TEST);

      connect2mysql();

      $suite = new PHPUnit_Framework_TestSuite('DGS Test-Suite');

      // list all tests
      $suite->addTestSuite('BitSetTest');
      $suite->addTestSuite('ProfileTest');
      $suite->addTestSuite('TimeFormatTest');
      $suite->addTestSuite('GameAddTimeTest');
      $suite->addTestSuite('Game_SettingsHelperTest');
      $suite->addTestSuite('EntityTest');
      $suite->addTestSuite('EntityDataTest');

      return $suite;
   }

} //end of 'AllTests'

?>

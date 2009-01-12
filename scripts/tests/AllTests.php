<?php

require_once 'PHPUnit/Framework.php';

// list all tests
require_once 'BitSetTest.php';
require_once 'ProfileTest.php';

/*!
 * \class Suite including all phpunit-tests for DGS.
 */
class AllTests
{
   public static function suite()
   {
      $suite = new PHPUnit_Framework_TestSuite('DGS Test-Suite');

      // list all tests
      $suite->addTestSuite('BitSetTest');
      $suite->addTestSuite('ProfileTest');

      return $suite;
   }
}

?>

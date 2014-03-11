<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once 'PHPUnit/Framework.php';

require_once 'include/connect2mysql.php';

// list all tests
require_once 'BitSetTest.php';
require_once 'DefaultMaxHandicapTest.php';
require_once 'DeprecatedGameSetupTest.php';
require_once 'EntityDataTest.php';
require_once 'EntityTest.php';
require_once 'GameAddTimeTest.php';
require_once 'GameSettingsTest.php';
require_once 'GameSetupTest.php';
require_once 'GuiFunctionsTest.php';
require_once 'ProfileTest.php';
require_once 'TimeFormatTest.php';
require_once 'TournamentPointsTest.php';

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
      $suite->addTestSuite('DefaultMaxHandicapTest');
      $suite->addTestSuite('DeprecatedGameSetupTest');
      $suite->addTestSuite('EntityDataTest');
      $suite->addTestSuite('EntityTest');
      $suite->addTestSuite('GameAddTimeTest');
      $suite->addTestSuite('GameSettingsTest');
      $suite->addTestSuite('GameSetupTest');
      $suite->addTestSuite('GuiFunctionsTest');
      $suite->addTestSuite('ProfileTest');
      $suite->addTestSuite('TimeFormatTest');
      $suite->addTestSuite('TournamentPointsTest');

      return $suite;
   }

} //end of 'AllTests'

?>

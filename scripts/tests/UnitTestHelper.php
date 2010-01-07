<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/error_functions.php';


/*!
 * \class UnitTestHelper
 * \brief Helper-function for DGS tests.
 */
class UnitTestHelper
{
   public static function clearErrors( $mode=ERROR_MODE_TEST )
   {
      global $TheErrors;
      $TheErrors->error_clear();
   }

   public static function countErrors()
   {
      global $TheErrors;
      return $TheErrors->error_count();
   }

} //end of 'UnitTestHelper'

?>

<?php

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

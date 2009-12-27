<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival

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

/* Author: Rod Ival */

/* Test for ob_start()-grabbing */


chdir("../../");
//require_once( "include/quick_common.php" );
//require_once( "include/connect2mysql.php" );
require_once( "include/std_functions.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

{
   $tid= get_request_arg( 'test', '1' );

   connect2mysql();

   $title= 'test - grabout';
   start_html($title, true, 'dragon',
         "pre { background: #dddddd; padding: 8px;}" );

   echo "<br>$title - Hello.";

   $repeat= 10000;
   $res= array();

   //Test 1: string concatenation
   $str= '';
   $n= $repeat;
   sleep(1);
   $stim= getmicrotime();
   while( --$n >= 0 )
   {
      $str.="loop("
         .$n
         .")";
   }
   $stim= getmicrotime()-$stim;
   $slen= strlen($str);
   $smd5= md5($str);
   unset($str);
   $res['str']= array( 'tim' => $stim, 'len' => $slen, 'md5' => $smd5);

   //Test 2: output grab. >>> N.B.: echo does not use dots but COMMAS
   ob_start();
   $n= $repeat;
   sleep(1);
   $stim= getmicrotime();
   while( --$n >= 0 )
   {
       echo "loop("
         ,$n //=> commas
         ,")";  //=> commas
   }
   $str= ob_get_contents(); //grab it
   ob_end_clean(); //don't copy it
   $stim= getmicrotime()-$stim;
   $slen= strlen($str);
   $smd5= md5($str);
   unset($str);
   $res['out']= array( 'tim' => $stim, 'len' => $slen, 'md5' => $smd5);


   echo "<pre>Results:";
   var_export($res);
   echo "</pre>";

   if(   $res['str']['len'] != $res['out']['len']
         || $res['str']['md5'] != $res['out']['md5'] )
   {
      echo "<br>Error: results different!";
      exit;
   }
   else
   {
      echo "<br>Ratio (str_concat/outbuf_grab) =", $res['str']['tim'] / $res['out']['tim'];
   }

   echo "<br>Done";
   end_html();
   exit;
}
?>

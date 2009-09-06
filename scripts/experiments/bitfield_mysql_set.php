<?php
/*
Dragon Go Server
Copyright (C) 2007  Erik Ouchterlony, Rod Ival

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

chdir("../../");
//require_once( "include/quick_common.php" );
//require_once( "include/connect2mysql.php" );
require_once( "include/std_functions.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

   $tid= get_request_arg( 'test', '1' );

   connect2mysql();

   $title= 'test - bitfield';
   start_html($title, true, 'dragon',
         "pre { background: #dddddd; padding: 8px;}" );

   echo "<br>$title - Hello.";

   $field_size= 39;
   $set = "'".implode("','", range(1,$field_size) )."'";

   //ColumnSet SET( '1', '2', '3', '4', '5', '6', ... ) DEFAULT 0 NOT NULL
   db_query( 'test.create',
      "CREATE TEMPORARY TABLE test (ID INT NOT NULL"
      .", ColumnSet SET($set) NOT NULL DEFAULT ''"
      .") ENGINE=MEMORY")
      or $TheErrors->dump_exit('test');

   db_query( 'test.insert',
      'INSERT INTO test (ID) VALUES (1)')
      or $TheErrors->dump_exit('test');

   echo "<pre># $field_size bits size<br># default";
   foreach( array(
         array( 3, -1),
         array( 3, 1),
         array( 3, -1),
         array( 1, 1),
         array( 4, 1),
         array( 1, 1),
         array( 4, 1),
         array(34, -1),
         array(34, 1),
         array(34, -1),
         array(35, 1),
         array( 3, 0),
         array( 1, 0),
         array( 4, 0),
         array(-1, 1), //set all
         array(39, -1),
         array(39, 1),
         array(38, -1),
         array(38, 0),
         array(38, -1),
         array(-1, 0), //clear all
         array( 0, -1),
         array( 0, 1),
         array( 0, -1),
         array(-1, -1), //stop
      ) as $sub )
   {
      $row= mysql_single_fetch( 'test.read',
         "SELECT ColumnSet,CAST(ColumnSet AS UNSIGNED),CONV(CAST(ColumnSet AS UNSIGNED),10,16) AS X_ColumnSet FROM test WHERE ID=1 LIMIT 1")
         //"SELECT CONV(CAST(ColumnSet AS UNSIGNED),10,16) AS X_ColumnSet FROM test WHERE ID=1 LIMIT 1")
         or $TheErrors->dump_exit('test');

      $X_ColumnSet= $row['X_ColumnSet'];
      echo " =&gt; $X_ColumnSet ";
      //echo "<br>"; var_export($row);

      list( $nr, $op)= $sub;
      if( $nr < 0 )
      {
         if( $op < 0 )
            break;
         echo "<br># ", ($op ? 'set' : 'clr'), " all";
         if( $op )
            db_query( 'test.set_all',
               "UPDATE test SET ColumnSet=CAST(-1 AS UNSIGNED) WHERE ID=1 LIMIT 1");
         else
            db_query( 'test.clr_all',
               "UPDATE test SET ColumnSet=CAST(0 AS UNSIGNED) WHERE ID=1 LIMIT 1");
         continue;
      }
      if( $op < 0 )
      {
         $res= bitfield_op( $X_ColumnSet, $nr);
         echo "<br># bit.$nr is ", (int)$res;
         continue;
      }
      $X_ColumnSet= bitfield_op( $X_ColumnSet, $nr, $op);
      echo "<br># ", ($op ? 'set' : 'clr'), " bit.$nr";

      db_query( 'test.update',
         "UPDATE test SET ColumnSet=CAST(0x$X_ColumnSet AS UNSIGNED) WHERE ID=1 LIMIT 1");
         //"UPDATE test SET ColumnSet=(0+0x$X_ColumnSet) WHERE ID=1 LIMIT 1");
   }
   echo "</pre>";

   echo "<br>Done";
   end_html();
   exit;

//Handle bit-operations on an hexadecimal string (size unlimited)
//$hexstr: e.g. "FE" for -2 or 254 (byte size)
//$bitnr: starting at 0, up to the length of the field used
//$op: 0=clr, 1=set, -1=tst
function bitfield_op( $hexstr, $bitnr=0, $op=-1)
{
   if( $bitnr < 0 )
      return null;
   $i= ($bitnr >> 2) + 1;
   $s= str_pad( (string)$hexstr, $i, '0', STR_PAD_LEFT);
   $i= strlen( $s) - $i;
   //hex digit to value
   $c= ord($s[$i]);
   if( $c > 0x60 )
      $c-= 0x61-10;
   else if( $c > 0x40 )
      $c-= 0x41-10;
   else
      $c-= 0x30;
   //operation
   $b= $bitnr & 0x3;
   if( $op < 0 ) //tst
      return (bool)($c & (1<<$b));
   if( $op > 0 ) //set
      $c|= (1<<$b);
   else //clr
      $c&=~(1<<$b);
   //value to hex digit
   if( $c > 9 )
      $c+= 0x41-10;
   else
      $c+= 0x30;
   $s[$i]= chr($c);
   //$s= ltrim( $s, '0'); if( $s == '' ) $s= '0';
   return $s;
} //bitfield_op

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

// Checks and fix the PosIndex bug in the Posts database.

require_once( "../forum/forum_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $player_level = (int)$player_row['admin_level'];
   if( !($player_level & ADMIN_DATABASE) )
      error("adminlevel_too_low");

   start_html('convert_posindex', 0);

echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor($_SERVER['PHP_SELF']           , 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p>(just show needed queries)"
         ."<br>".anchor($_SERVER['PHP_SELF']           , 'Show it again')
         ."<br>".anchor($_SERVER['PHP_SELF'].'?do_it=1', '[Validate it]')
         ."</p>";
   }

   $new_order_str = "*+-/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

   $old_order_str = "*+-/0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz";

   $query = "SELECT ID, PosIndex " .
      "FROM Posts WHERE PosIndex IS NOT NULL AND LENGTH(PosIndex) > 0 ";

   $result = mysql_query($query) or die(mysql_error());

   $n= (int)mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p></p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $old_posindex = $row['PosIndex'];
      $new_posindex = $old_posindex;

      $l = strlen($old_posindex);

      for($i=0; $i<$l; $i++)
      {
         $pos = strpos($old_order_str, $old_posindex[$i]);
         $new_posindex[$i] = $new_order_str[$pos];
      }

      if( $new_posindex !== $old_posindex )
      {
         echo '<hr>' . $row["ID"] . ": " .
            $old_posindex . "  --->  "  . $new_posindex . "<br>\n";

         dbg_query("UPDATE Posts " .
                   "SET PosIndex='" . $new_posindex . "' " .
                   "WHERE ID=" . $row['ID'] . " LIMIT 1");
      }
   }

   echo "<hr>Done!!!\n";

   end_html();
}

?>
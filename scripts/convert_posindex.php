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

// Checks and fix the PosIndex bug in the Posts database.

require_once( "../forum/forum_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

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
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }

   $new_order_str = "*+-/0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

   $old_order_str = "*+-/0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz";

   $query = "SELECT ID, PosIndex " .
      "FROM Posts WHERE PosIndex IS NOT NULL AND PosIndex>'' ";

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

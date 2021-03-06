<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir( '../' );
require_once 'include/std_functions.php';
require_once 'forum/forum_functions.php';


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_ADM_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.convert_from_old_forum');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.convert_from_old_forum');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.convert_from_old_forum');

   start_html('convert_from_old_forum', 0);

echo '>>>> Should not be used now. Fix TODO before using it!! Do not run it before a check ' .
   "(run script 'scripts/forum_consistency.php' afterwards to fix all counters and triggers). " .
   "Caution: no 'do_it' option"; end_html(); exit;


   $res0 = mysql_query( "SELECT * FROM forums" ) or die(mysql_error());
   while ( $row0 = mysql_fetch_array( $res0 ) )
   {
      $fname = $row0['table_name'];

      $r = mysql_single_fetch( 'conv_forum', "SELECT MAX(SortOrder) FROM Forums", FETCHTYPE_ROW );
      $SortOrder = $r[0] + 1;

      mysql_query("INSERT INTO Forums SET " .
                  "Name=\"" . $row0["name"] . "\", " .
                  "Description=\"" . $row0['description'] . "\", " .
                  "Options=" . ($row0["moderation"] == 'y' ? FORUMOPT_MODERATED : 0 ) . ", " .
                  "SortOrder=" . $SortOrder) or die(mysql_error());

      if ( mysql_affected_rows() != 1)
         die(mysql_error());

      $new_forum_ID = mysql_insert_id();


      $tbl = $fname . '_bodies';
      $query = "SELECT $fname.*, $tbl.body " .
         "FROM $fname INNER JOIN $tbl ON $tbl.id=$fname.id " .
         "WHERE approved='Y' " .
         "ORDER by $fname.id";
      $result_1 = mysql_query( $query ) or die(mysql_error());


      echo "\nStart $fname\n\n";
      while ( $row = mysql_fetch_array( $result_1 ) )
      {
         $old_parent = $row["parent"];
         $Text = preg_replace( array( "#^<html>#i", "#</html>$#i" ), '', $row['body'] );
         $Subject = $row["subject"];
         $old_ID = $row["id"];
         $uid = $row["pid"];
         $Time = $row["datestamp"];

         echo $old_ID;

         if ( $old_parent > 0 )
         {
            $result = mysql_query("SELECT ID as parent,PosIndex,Depth,Thread_ID FROM Posts " .
                                     "WHERE old_ID=$old_parent AND Forum_ID=$new_forum_ID")
               or die(mysql_error());

            if ( mysql_num_rows($result) != 1 )
            {
               echo "Unknown parent post\n";   //die( "Unknown parent post" );
               mysql_free_result($result);
               continue;
            }
            extract(mysql_fetch_array($result));
            mysql_free_result($result);

            $result = mysql_query("SELECT MAX(AnswerNr) AS answer_nr " .
                                  "FROM Posts WHERE Parent_ID=$parent")
               or die(mysql_error());

            extract(mysql_fetch_array($result));
            mysql_free_result($result);

            if ( !($answer_nr > 0) ) $answer_nr=0;
         }
         else
         {
            // New thread
            $answer_nr = 0;
            $PosIndex = ''; //just right now...
            $Depth = 0;
            $Thread_ID = -1;
            $parent = 0;
         }

         $order_str = ORDER_STR;
         $PosIndex .= $order_str[$answer_nr/64] . $order_str[$answer_nr%64];
         $Depth++;
         $Text = trim($Text);
         $Subject = trim($Subject);

         $query = "INSERT INTO Posts SET " .
            "Forum_ID=$new_forum_ID, " .
            "Thread_ID=$Thread_ID, " .
            "Time=\"$Time\", " .
            "Lastchanged=\"$Time\", " .
            "Subject='" . mysql_addslashes($Subject) . "', " .
            "Text='" . mysql_addslashes($Text) . "', " .
            "User_ID=$uid, " .
            "Parent_ID=$parent, " .
            "AnswerNr=" . ($answer_nr+1) . ", " .
            "Depth=$Depth, " .
            "Approved=\"" . $row['approved'] . "\", " .
            "crc32=" . crc32($Text) . ", " .
            "PosIndex='$PosIndex', " .
            "old_ID=$old_ID";

         mysql_query( $query ) or die(mysql_error());

         if ( mysql_affected_rows() != 1)
            die( "mysql_insert_post" );

         echo "->" . mysql_insert_id() . "\n";

         if ( !($parent > 0) )
         {
            $Thread_ID = mysql_insert_id();
            mysql_query("UPDATE Posts SET Thread_ID=ID WHERE ID=$Thread_ID")
                or die(mysql_error());

            if ( mysql_affected_rows() != 1)
               die("mysql_update_post");
         }

      }
      mysql_free_result($result_1);

      //TODO: needs to be re-implemented if needed
      //recalculate_postsinforum($new_forum_ID);

      echo "\n\nFinished $fname\n";
   }

   echo "<hr>Done!!!\n";
   end_html();
}
?>

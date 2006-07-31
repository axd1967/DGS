<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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


function post_message($player_row, $moderated_forum)
{
   global $NOW, $order_str;

   if( $player_row['MayPostOnForum'] == 'N' )
      return T_('Sorry, you are not allowed to post on the forum');

   $forum = @$_POST['forum']+0;
   $parent = @$_POST['parent']+0;
   $edit = @$_POST['edit']+0;

   $Subject = trim(@$_POST['Subject']);
   $Text = trim(@$_POST['Text']);
//   $GoDiagrams = create_godiagrams($Text);
   $Subject = addslashes($Subject);
   $Text = addslashes($Text);

   $moderated = ($moderated_forum or $player_row['MayPostOnForum'] == 'M');

   // -------   Edit old post  ----------

   if( $edit > 0 )
   {
      $result = mysql_query("SELECT Subject,Text,Forum_ID,GREATEST(Time,Lastedited) AS Time ".
                            "FROM Posts WHERE ID=$edit AND User_ID=" . $player_row['ID'])
         or error("unknown_parent_post");

       if( mysql_num_rows($result) != 1 )
         error("unknown_parent_post");

       $row = mysql_fetch_array($result);

       //Update old record with new text
       mysql_query("UPDATE Posts SET " .
                   "Lastedited=FROM_UNIXTIME($NOW), " .
                   "Subject=\"$Subject\", " .
                   "Text=\"$Text\" " .
                   "WHERE ID=$edit LIMIT 1") or die(mysql_error());

       //Insert new record with old text
       mysql_query("INSERT INTO Posts SET " .
                   'Time="' . $row['Time'] .'", ' .
                   "Parent_ID=$edit, " .
                   "Forum_ID=" . $row['Forum_ID'] . ", " .
                   "User_ID=" . $player_row['ID'] . ", " .
                   'Subject="' . $row['Subject'] . '", ' .
                   'Text="' . $row['Text'] . '"') or die(mysql_error());

       return;
   }
   else
   {
   // -------   Else add post  ----------


   // -------   Reply  ----------

      if( $parent > 0 )
      {
         $result = mysql_query("SELECT PosIndex,Depth,Thread_ID FROM Posts " .
                               "WHERE ID=$parent AND Forum_ID=$forum")
            or error('unknown_parent_post');

         if( mysql_num_rows($result) != 1 )
            error("unknown_parent_post");

         extract(mysql_fetch_array($result));

         $result = mysql_query("SELECT MAX(AnswerNr) AS answer_nr " .
                               "FROM Posts WHERE Parent_ID=$parent")
            or die(mysql_error());

         extract(mysql_fetch_array($result));

         $lastchanged_string = '';

         if( !($answer_nr > 0) ) $answer_nr=0;
      }


      // -------   New thread  ----------

      else
      {
         // New thread
         $answer_nr = 0;
         $PosIndex = '';
         $Depth = 0;
         $Thread_ID = -1;
         $lastchanged_string = "LastChanged=FROM_UNIXTIME($NOW), ";
      }




      // -------   Update database   -------

      if( $answer_nr >= 64*64 )
         error("internal_error", "AnswerNr too large: $answer_nr" );

      $PosIndex .= $order_str[$answer_nr/64] . $order_str[$answer_nr%64];
      $Depth++;

      if( $Depth >= 40 )
         error("internal_error", "Depth too large: $Depth" );

      $query = "INSERT INTO Posts SET " .
         "Forum_ID=$forum, " .
         "Thread_ID=$Thread_ID, " .
         "Time=FROM_UNIXTIME($NOW), " .
         $lastchanged_string .
         "Subject=\"$Subject\", " .
         "Text=\"$Text\", " .
         "User_ID=" . $player_row["ID"] . ", " .
         "Parent_ID=$parent, " .
         "AnswerNr=" . ($answer_nr+1) . ", " .
         "Depth=$Depth, " .
         "Approved=" . ($moderated ? "'N'" : "'Y'")  . ", " .
         "PendingApproval=" . ($moderated ? "'Y'" : "'N'")  . ", " .
         "crc32=" . crc32($Text) . ", " .
         "PosIndex=\"$PosIndex\"";

      mysql_query( $query ) or die(mysql_error());

      if( mysql_affected_rows() != 1)
         error("mysql_insert_post");

      $New_ID = mysql_insert_id();

      if( !($parent > 0) )
      {
         mysql_query( "UPDATE Posts SET Thread_ID=ID WHERE ID=$New_ID LIMIT 1" )
            or die(mysql_error());

         if( mysql_affected_rows() != 1)
            error("mysql_insert_post");

         $Thread_ID = $New_ID;
      }

//   save_diagrams($GoDiagrams);

      if( $moderated )
      {
         return T_('This post is subject to moderation. It will be shown once the moderators have approved it.');
      }
      else
      {
         mysql_query("UPDATE Posts " .
                     "SET PostsInThread=PostsInThread+1, " .
                     "LastPost=$New_ID, LastChanged=FROM_UNIXTIME($NOW) " .
                     "WHERE ID=$Thread_ID LIMIT 1" ) or die(mysql_error());

         mysql_query("UPDATE Forums " .
                     "SET PostsInForum=PostsInForum+1, LastPost=$New_ID " .
                     "WHERE ID=$forum LIMIT 1" ) or die(mysql_error());

         return T_('Message sent!');
      }
   }
}

?>
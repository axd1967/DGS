<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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


function post_message($player_row, $moderated)
{
   global $NOW, $order_str;

   $forum = $_POST['forum']+0;
   $parent = $_POST['parent']+0;
   $edit = $_POST['edit']+0;

   $Text = trim($_POST['Text']);
   $Subject = trim($_POST['Subject']);

   // -------   Edit old post  ----------

   if( $edit > 0 )
   {
      $result = mysql_query("SELECT Subject,Text,Time,Forum_ID FROM Posts WHERE ID=$edit")
         or error("unknown_parent_post");

       if( mysql_num_rows($result) != 1 )
         error("unknown_parent_post");

       $row = mysql_fetch_array($result);

       mysql_query("UPDATE Posts SET " .
                   "Lastedited=FROM_UNIXTIME($NOW), " .
                   "Subject=\"$Subject\", " .
                   "Text=\"$Text\" " .
                   "WHERE ID=$edit LIMIT 1") or die(mysql_error());

       mysql_query("INSERT INTO Posts SET " .
                   'Time="' . $row['Time'] .'", ' .
                   "Parent_ID=$edit, " .
                   "Forum_ID=" . $row['Forum_ID'] . ", " .
                   "User_ID=" . $player_row['ID'] . ", " .
                   'Subject="' . $row['Subject'] . '", ' .
                   'Text="' . $row['Text'] . '"') or die(mysql_error());

       return;
   }



   // -------   Reply  ----------

   else if( $parent > 0 )
   {
      $result = mysql_query("SELECT PosIndex,Depth,Thread_ID FROM Posts " .
                            "WHERE ID=$parent AND Forum_ID=$forum")
         or error('unknown_parent_post');

      if( mysql_num_rows($result) != 1 )
         error("unknown_parent_post");

      extract(mysql_fetch_array($result));

      $result = mysql_query("SELECT MAX(AnswerNr) AS answer_nr " .
                            "FROM Posts WHERE Parent_ID=$parent");

      extract(mysql_fetch_array($result));

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
   }




   // -------   Update database   -------

   $PosIndex .= $order_str[$answer_nr];
   $Depth++;

   $query = "INSERT INTO Posts SET " .
       "Forum_ID=$forum, " .
       "Thread_ID=$Thread_ID, " .
       "Time=FROM_UNIXTIME($NOW), " .
       "Lastchanged=FROM_UNIXTIME($NOW), " .
       "Subject=\"$Subject\", " .
       "Text=\"$Text\", " .
       "User_ID=" . $player_row["ID"] . ", " .
       "Parent_ID=$parent, " .
       "AnswerNr=" . ($answer_nr+1) . ", " .
       "Depth=$Depth, " .
       "Approved=" . ($moderated ? "'N'" : "'Y'")  . ", " .
       "crc32=" . crc32($Text) . ", " .
       "PosIndex=\"$PosIndex\"";

   mysql_query( $query );

   if( mysql_affected_rows() != 1)
      error("mysql_insert_post");

   $New_ID = mysql_insert_id();

   if( !($parent > 0) )
   {
      mysql_query( "UPDATE Posts SET Thread_ID=ID WHERE ID=$New_ID" );

      if( mysql_affected_rows() != 1)
         error("mysql_insert_post");
   }

   if( $moderated )
   {
      // TODO: Notify moderators;
   }
   else
      mysql_query( "UPDATE Posts SET Lastchanged=FROM_UNIXTIME($NOW), Replies=Replies+1 " .
                   "WHERE Forum_ID=$forum AND Thread_ID=$Thread_ID " .
                   "AND LEFT(PosIndex,Depth)=LEFT(\"$PosIndex\",DEPTH)" );

}

?>
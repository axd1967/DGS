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


include("forum_functions.php");

{
   disable_cache();
   connect2mysql();


   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

//  input: $Text, $Subject, $parent, $forum

   if( $parent > 0 )
   {
      $result = mysql_query("SELECT PosIndex,Depth,Thread_ID FROM Posts " .
                            "WHERE ID=$parent AND Forum_ID=$forum");

      if( mysql_num_rows($result) != 1 )
         error("Unknown parent post");

      extract(mysql_fetch_array($result));

      $result = mysql_query("SELECT MAX(AnswerNr) AS answer_nr " .
                            "FROM Posts WHERE Parent_ID=$parent");

      extract(mysql_fetch_array($result));

      if( !($answer_nr > 0) ) $answer_nr=0;
   }
   else
   {
      // New thread
      $answer_nr = 0;
      $PosIndex = '';
      $Depth = 0;
      $Thread_ID = -1;
   }

   $PosIndex .= $order_str[$answer_nr];
   $Depth++;
   $Text = trim($Text);
   $Subject = trim($Subject);

   $query = "INSERT INTO Posts SET " .
       "Forum_ID=$forum, " .
       "Thread_ID=$Thread_ID, " .
       "Time=FROM_UNIXTIME($NOW), " .
       "Lastchanged=FROM_UNIXTIME($NOW), " .
       "Subject=\"$Subject\", " .
       "Text=\"$Text\", " .
       "User_ID=" . $player_row["ID"] . ", " .
       "Parent_ID=$parent, " .
       "AnswerNr=" . ($answernr+1) . ", " .
       "Depth=$Depth, " .
       "crc32=" . crc32($Text) . ", " .
       "PosIndex=\"$PosIndex\"";

   mysql_query( $query );

   if( mysql_affected_rows() != 1)
      error("mysql_insert_post");

   if( !($parent > 0) )
   {
      $Thread_ID = mysql_insert_id();
      mysql_query( "UPDATE Posts SET Thread_ID=ID WHERE ID=$Thread_ID" );

      if( mysql_affected_rows() != 1)
         error("mysql_insert_post");
   }

   mysql_query( "UPDATE Posts SET Lastchanged=FROM_UNIXTIME($NOW), Replies=Replies+1 " .
                "WHERE Forum_ID=$forum AND Thread_ID=$Thread_ID " .
                "AND LEFT(PosIndex,Depth)=LEFT(\"$PosIndex\",DEPTH)" );


   jump_to("forum/list.php?forum=$forum");
}
?>

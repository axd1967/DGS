<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

//  input: $Text, $Subject, $parent, $forum

   if( $parent != -1 )
   {
      $result = mysql_query("SELECT Threads,PosIndex from Posts WHERE ID=$parent");
      
      if( mysql_num_rows($result) != 1 )
         error("Unknown parent post");

      extract(mysql_fetch_array($result));

      $result = mysql_query("SELECT MAX(AnswerNr) AS next_answernr " .
                            "FROM Posts WHERE Parent_ID=$parent");

      extract(mysql_fetch_array($result));

      if( !($next_answernr > 0) ) $next_answernr=0;

      $next_answernr++;
   }
   else
   {
      // New thread
      $result = mysql_query("INSERT INTO Threads SET Forum_ID=$forum, Lastchanged=NOW()");

      if( mysql_affected_rows() != 1 )
         error("New thread failed");

      $Thread_ID = mysql_insert_id();
      $next_answernr = 1;
      $PosIndex = '';
   }

   $PosIndex .= $order_str[$AnswerNr];

   $query = "INSERT INTO Posts SET " .
       "Thread_ID=$Thread_ID, " .
       "Time=NOW(), " .
       "Subject=\"$Subject\", " .
       "Text=\"$Text\", " .
       "User_ID=" . $player_row["ID"] . ", " .
       "Parent_ID=$parent, " .
       "AnswerNr=$next_answernr, " .
       "crc32=" . crc32($Text) . ", " .
       "PosIndex=\"$PosIndex\"";

   mysql_query( $query );
   
   if( mysql_affected_rows() != 1)
      error("mysql_insert_post");


   jump_to("forum/list.php?forum=$forum");
}
?>

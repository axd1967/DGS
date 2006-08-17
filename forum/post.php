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


function post_message($player_row, $moderated_forum, &$thread)
{
   global $NOW, $order_str;

   if( $player_row['MayPostOnForum'] == 'N' )
      return T_('Sorry, you are not allowed to post on the forum');

   $forum = @$_POST['forum']+0;
   $parent = @$_POST['parent']+0;
   $edit = @$_POST['edit']+0;

   $Subject = get_request_arg('Subject');
   $Text = get_request_arg('Text');
//   $GoDiagrams = create_godiagrams($Text);
   $Subject = mysql_escape_string( trim($Subject));
   $Text = mysql_escape_string( trim($Text));

   if( $Text == '' )
      return '';
   if( $Subject == '' )
      $Subject = '???';

   $moderated = ($moderated_forum or $player_row['MayPostOnForum'] == 'M');

   // -------   Edit old post  ----------

   if( $edit > 0 )
   {
      $row = mysql_single_fetch(
                  "SELECT Subject,Text,Forum_ID,GREATEST(Time,Lastedited) AS Time ".
                  "FROM Posts WHERE ID=$edit AND User_ID=" . $player_row['ID'],
                  'assoc', 'forum_post.post_message.edit.find')
         or error("unknown_parent_post");

      $oldSubject = mysql_escape_string( trim($row['Subject']));
      $oldText = mysql_escape_string( trim($row['Text']));

      if( $oldSubject != $Subject or $oldText != $Text )
      {
       //Update old record with new text
       mysql_query("UPDATE Posts SET " .
                   "Lastedited=FROM_UNIXTIME($NOW), " .
                   "Subject=\"$Subject\", " .
                   "Text=\"$Text\" " .
                   "WHERE ID=$edit LIMIT 1")
          or error('mysql_query_failed','forum_post.post_message.edit.update');

       //Insert new record with old text
       mysql_query("INSERT INTO Posts SET " .
                   'Time="' . $row['Time'] .'", ' .
                   "Parent_ID=$edit, " .
                   "Forum_ID=" . $row['Forum_ID'] . ", " .
                   "User_ID=" . $player_row['ID'] . ", " .
                      //PosIndex=NULL
                            "Subject=\"$oldSubject\", " .
                      "Text=\"$oldText\"" )
          or error('mysql_query_failed','forum_post.post_message.edit.insert');
      }
      return $edit;
   }
   else
   {
   // -------   Else add post  ----------


   // -------   Reply  ----------

      if( $parent > 0 )
      {
         $row = mysql_single_fetch("SELECT PosIndex,Depth,Thread_ID FROM Posts " .
                                   "WHERE ID=$parent AND Forum_ID=$forum",
                                   'assoc', 'forum_post.reply.find')
            or error('unknown_parent_post', 'forum_post.reply.find');

         extract( $row);

         $row = mysql_single_fetch("SELECT MAX(AnswerNr) AS answer_nr " .
                                   "FROM Posts WHERE Parent_ID=$parent",
                                   'assoc', 'forum_post.reply.max')
            or error('unknown_parent_post', 'forum_post.reply.max');

         extract( $row);

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

      mysql_query( $query )
         or error('mysql_query_failed','forum_post.insert_new_post');

      if( mysql_affected_rows() != 1)
         error("mysql_insert_post", 'forum_post.insert_new_post');

      $New_ID = mysql_insert_id();

      if( !($parent > 0) )
      {
         mysql_query( "UPDATE Posts SET Thread_ID=ID WHERE ID=$New_ID LIMIT 1" )
            or error('mysql_query_failed','forum_post.new_thread');

         if( mysql_affected_rows() != 1)
            error("mysql_insert_post", 'forum_post.new_thread');

         $thread = $Thread_ID = $New_ID;
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
                     "WHERE ID=$Thread_ID LIMIT 1" )
            or error('mysql_query_failed','forum_post.moderated.postsinthread');

         mysql_query("UPDATE Forums " .
                     "SET PostsInForum=PostsInForum+1, LastPost=$New_ID " .
                     "WHERE ID=$forum LIMIT 1" )
          or error('mysql_query_failed','forum_post.moderated.postsinforum');

         return T_('Message sent!');
      }
   }
}

?>
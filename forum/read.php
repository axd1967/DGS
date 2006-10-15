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

$TranslateGroups[] = "Forum";

require_once( "forum_functions.php" );
require_once( "post.php" );


function revision_history($post_id)
{
   global $links, $cols, $Name, $Handle, $User_ID,
      $Lasteditedstamp, $Timestamp, $Lastread, $NOW;


   $headline = array(T_("Revision history") => "colspan=$cols");
   $links |= LINK_BACK_TO_THREAD;
   $Lastread = $NOW;

   start_table($headline, $links, 'width="99%"', $cols);
   $cur_depth= -1;


   $query_select = "SELECT Posts.*, " .
      "Players.ID AS User_ID, Players.Name, Players.Handle, " .
      "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
      "UNIX_TIMESTAMP(GREATEST(Posts.Time,Posts.Lastedited)) AS Timestamp " .
      "FROM (Posts) LEFT JOIN Players ON Posts.User_ID=Players.ID ";


   $row = mysql_single_fetch( $query_select . "WHERE Posts.ID='$post_id'",
                              'array', 'forum_read.revision_history.find_post' )
      or error("unknown_post");

   extract($row);
   change_depth( $cur_depth, 1, $cols);
   draw_post( 'reply', true, $row['Subject'], $row['Text']);
   echo "<tr><td colspan=$cols height=2></td></tr>";
   change_depth( $cur_depth, 2, $cols);

   $result = mysql_query( $query_select .
                          "WHERE Parent_ID='$post_id' AND PosIndex IS NULL " .
                          "ORDER BY Timestamp DESC")
      or error('mysql_query_failed','forum_read.revision_history.find_edits');


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      draw_post( 'edit' , true, $row['Subject'], $row['Text']);
      echo "<tr><td colspan=$cols height=2></td></tr>";
   }


   change_depth( $cur_depth, -1, $cols);
   end_table($links, $cols);
   end_page();
   exit;
}


function change_depth( &$cur_depth, $new_depth, $cols)
{
   if( $new_depth < 1 && $cur_depth < 1 )
   {
      return;      
   }

   if( $cur_depth >= 1 ) //this means that a cell table is already opened
   {
      echo "</table></td></tr>";
   }

   if( $new_depth < 1 ) //this means close it
   {
      echo "</table></td></tr>";
      $cur_depth = -1;
      return;
   }

   if( $cur_depth < 1 ) //this means opened it
   {
      echo "<tr><td colspan=$cols><table width=\"100%\" border=0 cellspacing=0 cellpadding=0>";
   }

   // then build the indenting row
   $cur_depth = $new_depth;
   echo "<tr>";
   $i= min( $cur_depth, FORUM_MAXIMUM_DEPTH);
   $c= FORUM_MAXIMUM_DEPTH+1 - $i;
   $indent= "<td class=\"indent\">&nbsp;</td>";
   switch( $i )
   {
      case 1:
      break;
      case 2:
         echo "$indent";
      break;
      case 3:
         echo "<td>&nbsp;</td>$indent";
      break;
      default:
         echo "<td colspan=".($i-2).">&nbsp;</td>$indent";
      break;
   }

   // finally, open the cell table
   echo "<td colspan=$c><table width=\"100%\" border=0 cellspacing=0 cellpadding=3>";
}





{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

   $reply = @$_REQUEST['reply']+0;

   $forum = @$_REQUEST['forum']+0;
   $thread = @$_REQUEST['thread']+0;
   $edit = @$_REQUEST['edit']+0;

   $Forumname = forum_name($forum, $moderated);

   if( isset($_POST['post']) )
   {
      $msg = post_message($player_row, $moderated, $thread);
      if( is_numeric( $msg) && $msg>0 )
         jump_to("forum/read.php?forum=$forum".URI_AMP."thread=$thread"
            . "#$msg");
      else
         jump_to("forum/read.php?forum=$forum".URI_AMP."thread=$thread"
            . URI_AMP."sysmsg=".urlencode($msg)."#new1");
   }

   $preview = isset($_POST['preview']);
   $preview_ID = ($edit > 0 ? $edit : @$_REQUEST['parent']+0 );
   if( $preview )
   {
      $preview_Subject = trim(get_request_arg('Subject'));
      $preview_Text = trim(get_request_arg('Text'));
      if( !$edit > 0 )
         $reply = @$_REQUEST['parent']+0;
//      $preview_GoDiagrams = create_godiagrams($preview_Text);
   }

   $cols= 2;
   $links = LINKPAGE_READ;

   $headline = array(T_("Reading thread") => "colspan=$cols");
   $links |= LINK_FORUMS | LINK_THREADS | LINK_SEARCH;
   $is_moderator = false;

   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      //toggle moderator and preview does not work together.
      //(else add $_POST in the moderator link buid)
      if( !$preview )
         $links |= LINK_TOGGLE_MODERATOR;

      $is_moderator = set_moderator_cookie($player_row['ID']);

      if( (int)@$_GET['show'] > 0 )
         approve_message( (int)@$_GET['show'], $thread, $forum, true );
      else if( (int)@$_GET['hide'] > 0 )
         approve_message( (int)@$_GET['hide'], $thread, $forum, false );
      else if( (int)@$_GET['approve'] > 0 )
         approve_message( (int)@$_GET['approve'], $thread, $forum, true, true );
      else if( (int)@$_GET['reject'] > 0 )
         approve_message( (int)@$_GET['reject'], $thread, $forum, false, true );
   }

   $title = T_('Forum').' - '.$Forumname;
   start_page($title, true, $logged_in, $player_row,
      "td.indent{ width:" . FORUM_INDENTATION_PIXELS .
      "px; min-width:" . FORUM_INDENTATION_PIXELS . "px;}\n"
      );
   echo "<center><h3><font color=$h3_color>$title</font></h3></center>\n";

   print_moderation_note($is_moderator, '99%');

   if( @$_GET['revision_history'] > 0 )
      revision_history(@$_GET['revision_history']); //set $Lastread

// The table structure of the list:
// level 1: the header, body and footer TABLE of the list
// level 2: the boby of the list: one row per post managing its indent
// level 3: the post cell TABLE

   start_table($headline, $links, 'width="99%"', $cols);
   $cur_depth= -1;


   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) AS Lastread FROM Forumreads " .
                         "WHERE User_ID=" . $player_row["ID"] . " AND Thread_ID=$thread")
      or error('mysql_query_failed','forum_read.forumreads');

   if( @mysql_num_rows($result) == 1 )
      extract( mysql_fetch_array( $result ) );
   else
      $Lastread = NULL;

   $result = mysql_query("SELECT Posts.*, " .
                         "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Time) AS Timestamp, " .
                         "Players.ID AS uid, Players.Name, Players.Handle " .
                         "FROM (Posts) LEFT JOIN Players ON Posts.User_ID=Players.ID " .
                         "WHERE Forum_ID=$forum AND Thread_ID=$thread " .
                         "AND PosIndex IS NOT NULL " .
                         "ORDER BY PosIndex")
      or error('mysql_query_failed','forum_read.find_posts');


   $thread_Subject = '';
   $Lastchangedthread = 0 ;
   while( $row = mysql_fetch_array( $result ) )
   {
      $Name = '?';
      extract($row);

      if( $thread == $ID ) //Initial post of the thread
      {
         $thread_Subject = $Subject;
         $Lastchangedthread = $Lastchangedstamp;
      }

      $hidden = ($Approved == 'N');

      if( $hidden and !$is_moderator and $uid !== $player_row['ID'] )
         continue;


      change_depth( $cur_depth, $Depth, $cols);


      $post_type = 'normal';

      if( $hidden )
         $post_type = 'hidden';

      if( $reply == $ID )
         $post_type = 'reply';

      if( $edit == $ID )
         $post_type = 'edit';

//      $GoDiagrams = find_godiagrams($Text);

      $post_reference =
         draw_post($post_type, $uid == $player_row['ID'], $Subject, $Text); //, $GoDiagrams);

      if( $preview and $preview_ID == $ID )
      {
         change_depth( $cur_depth, $cur_depth + 1, $cols);
         $Subject = $preview_Subject;
         $Text = $preview_Text;
//         $GoDiagrams = $preview_GoDiagrams;
         draw_post('preview', false, $Subject, $Text); //, $GoDiagrams);
      }

      if( $post_type != 'normal' and $post_type != 'hidden' and !$is_moderator )
      {
         if( $post_type == 'reply' and !($preview and $preview_ID == $ID) )
         {
            if( @$_REQUEST['quote'] )
            {
               $Text = "<quote>$post_reference\n\n$Text</quote>\n";
            }
            else
               $Text = '';
//            $GoDiagrams = null;
         }
         echo "<tr><td colspan=$cols align=center>\n";
         message_box($post_type, $ID, NULL /*$GoDiagrams*/, $Subject, $Text);
         echo "</td></tr>\n";
      }
   } //posts loop

   if( $preview and $preview_ID == 0 and !$is_moderator )
   {
      change_depth( $cur_depth, $cur_depth + 1, $cols);
      $Subject = $preview_Subject;
      $Text = $preview_Text;
//      $GoDiagrams = $preview_GoDiagrams;
      draw_post('preview', false, $Subject, $Text); //, $GoDiagrams);
      echo "<tr><td colspan=$cols align=center>\n";
      message_box('preview', $thread, NULL /*$GoDiagrams*/, $Subject, $Text);
      echo "</td></tr>\n";
   }

   if( !($reply > 0) and !$preview and !($edit>0) and !$is_moderator )
   {
      change_depth( $cur_depth, 1, $cols);
      echo "<tr><td colspan=$cols align=center>\n";
      if( $thread > 0 )
         echo '<hr>';
      message_box('normal', $thread, null, $thread_Subject);
      echo "</td></tr>\n";
   }

   change_depth( $cur_depth, -1, $cols);
   end_table($links, $cols);


// Update Forumreads to remove the 'new' flag

   if( !$Lastread or $Lastread < $Lastchangedthread )
   {
      mysql_query( "REPLACE INTO Forumreads SET " .
                   "User_ID=" . $player_row["ID"] . ", " .
                   "Thread_ID=$thread, " .
                   "Time=FROM_UNIXTIME($NOW)" )
         or error('mysql_query_failed','forum_read.replace_forumreads');
   }

   end_page();
}
?>

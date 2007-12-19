<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Forum";

require_once( "forum_functions.php" );
require_once( "post.php" );


function revision_history($post_id, $rx_term='')
{
   global $NOW;
/* Those globals are used by draw_post():
   global $ID, $User_ID, $HOSTBASE, $forum, $Name, $Handle, $Lasteditedstamp, $Lastedited,
      $thread, $Timestamp, $date_fmt, $Lastread, $is_moderator, $NOW, $player_row,
      $ForumName, $Score, $Forum_ID, $Thread_ID, $show_score, $PendingApproval;
*/
   //TODO: remove those globals!
   global $links, $cols, $Name, $Handle, $ID, $User_ID,
      $Lasteditedstamp, $Timestamp, $Lastread, $Forum_ID, $Thread_ID;


   $headline = array(T_("Revision history") => "colspan=$cols");
   global $back_post_id;
   $back_post_id = $post_id;
   $links |= LINK_BACK_TO_THREAD;
   $Lastread = $NOW;

   forum_start_table('Revision', $headline, $links, $cols);
   $cur_depth= -1;


   $query_select = "SELECT Posts.*, " .
      "Players.ID AS User_ID, Players.Name, Players.Handle, " .
      "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
      "UNIX_TIMESTAMP(GREATEST(Posts.Time,Posts.Lastedited)) AS Timestamp " .
      "FROM (Posts) LEFT JOIN Players ON Posts.User_ID=Players.ID ";


   $row = mysql_single_fetch( 'forum_read.revision_history.find_post',
            $query_select . "WHERE Posts.ID='$post_id'" )
      or error('unknown_post');

   extract($row);
   change_depth( $cur_depth, 1, $cols);
   draw_post( 'Reply', true, $row['Subject'], $row['Text'], null, $rx_term);
   echo "<tr><td colspan=$cols height=2></td></tr>";
   change_depth( $cur_depth, 2, $cols);

   $result = mysql_query( $query_select .
           "WHERE Parent_ID='$post_id' AND PosIndex='' " . // '' == inactivated (edited)
           "ORDER BY Timestamp DESC")
      or error('mysql_query_failed','forum_read.revision_history.find_edits');


   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      $Thread_ID=0; //already so in database... used in draw_post() to remove the subject link
      draw_post( 'Edit' , true, $row['Subject'], $row['Text'], null, $rx_term);
      echo "<tr><td colspan=$cols height=2></td></tr>";
   }
   mysql_free_result($result);

   change_depth( $cur_depth, -1, $cols);
   forum_end_table($links, $cols);
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
   $indent= "<td class=Indent>&nbsp;</td>";
   switch( $i )
   {
      case 1:
      break;
      case 2:
         echo "$indent";
      break;
      case 3:
         echo "<td class=Indent2></td>$indent";
      break;
      default:
         echo "<td class=Indent2 colspan=".($i-2)."></td>$indent";
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
   $rx_term = get_request_arg('xterm', '');

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

   if( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      //toggle moderator and preview does not work together.
      //(else add $_POST in the moderator link build)
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
   start_page($title, true, $logged_in, $player_row);
   echo "<h3 class=Header>$title</h3>\n";

   print_moderation_note($is_moderator, '99%');

   if( @$_GET['revision_history'] > 0 )
   {
      revision_history(@$_GET['revision_history'], $rx_term); //set $Lastread
      exit; //done in revision_history
   }

// The table structure of the list:
// level 1: the header, body and footer TABLE of the list
// level 2: the boby of the list: one row per post managing its indent
// level 3: the post cell TABLE

   forum_start_table('Read', $headline, $links, $cols);
   $cur_depth= -1;


   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) AS Lastread FROM Forumreads " .
                         "WHERE User_ID=" . $player_row["ID"] . " AND Thread_ID=$thread")
      or error('mysql_query_failed','forum_read.forumreads');

   if( @mysql_num_rows($result) == 1 )
      extract( mysql_fetch_array( $result ) );
   else
      $Lastread = NULL;
   mysql_free_result($result);

   $result = mysql_query("SELECT Posts.*, " .
                         "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Time) AS Timestamp, " .
                         "Players.ID AS uid, Players.Name, Players.Handle " .
                         "FROM (Posts) LEFT JOIN Players ON Posts.User_ID=Players.ID " .
                         "WHERE Forum_ID=$forum AND Thread_ID=$thread " .
                         "AND PosIndex>'' " . // '' == inactivated (edited)
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


      $postClass = 'Normal';

      if( $hidden )
         $postClass = 'Hidden';

      if( $reply == $ID )
         $postClass = 'Reply';

      if( $edit == $ID )
         $postClass = 'Edit';

//      $GoDiagrams = find_godiagrams($Text);

      $post_reference =
         draw_post($postClass, $uid == $player_row['ID'], $Subject, $Text, NULL /*$GoDiagrams*/, $rx_term);

      if( $preview and $preview_ID == $ID )
      {
         change_depth( $cur_depth, $cur_depth + 1, $cols);
         $Subject = $preview_Subject;
         $Text = $preview_Text;
//         $GoDiagrams = $preview_GoDiagrams;
         draw_post('Preview', false, $Subject, $Text, NULL /*$GoDiagrams*/, $rx_term);
      }

      if( $postClass != 'Normal' and $postClass != 'Hidden' and !$is_moderator )
      {
         if( $postClass == 'Reply' and !($preview and $preview_ID == $ID) )
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
         //used by forum_message_box(): global $forum, $thread;
         forum_message_box($postClass, $ID, NULL /*$GoDiagrams*/, $Subject, $Text);
         echo "</td></tr>\n";
      }
   } //posts loop
   mysql_free_result($result);

   if( $preview and $preview_ID == 0 and !$is_moderator )
   {
      change_depth( $cur_depth, $cur_depth + 1, $cols);
      $Subject = $preview_Subject;
      $Text = $preview_Text;
//      $GoDiagrams = $preview_GoDiagrams;
      draw_post('Preview', false, $Subject, $Text, NULL /*$GoDiagrams*/, $rx_term);
      echo "<tr><td colspan=$cols align=center>\n";
      //used by forum_message_box(): global $forum, $thread;
      forum_message_box('Preview', $thread, NULL /*$GoDiagrams*/, $Subject, $Text);
      echo "</td></tr>\n";
   }

   if( !($reply > 0) and !$preview and !($edit>0) and !$is_moderator )
   {
      change_depth( $cur_depth, 1, $cols);
      echo "<tr><td colspan=$cols align=center>\n";
      if( $thread > 0 )
         echo '<hr>';
      //used by forum_message_box(): global $forum, $thread;
      forum_message_box('Normal', $thread, null, $thread_Subject);
      echo "</td></tr>\n";
   }

   change_depth( $cur_depth, -1, $cols);
   forum_end_table($links, $cols);


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

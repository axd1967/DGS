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


require_once( "forum_functions.php" );
require_once( "post.php" );



function draw_post($post_type, $my_post, $Subject='', $Text='', $GoDiagrams=null )
{
   global $ID, $User_ID, $HOSTBASE, $forum, $Name, $Handle, $Lasteditedstamp, $Lastedited,
      $thread, $Timestamp, $date_fmt, $Lastread, $is_editor, $NOW, $player_row;

   $post_colors = array( 'normal' => 'cccccc',
                         'hidden' => 'eecccc',
                         'reply' => 'cccccc',
                         'preview' => 'cceecc',
                         'edit' => 'eeeecc' );

   $sbj = make_html_safe( $Subject );
   $txt = make_html_safe( $Text, true);
   $txt = replace_goban_tags_with_boards($txt, $GoDiagrams);

   if( strlen($txt) == 0 ) $txt = '&nbsp;';

   $color = "ff0000";
   $new = get_new_string($Timestamp, $Lastread);


   if( $post_type == 'preview' )
      echo '<tr><td bgcolor="#' . $post_colors[ $post_type ] .
         "\"><a name=\"preview\"><font size=\"+1\"><b>$sbj</b></font></a><br> " . 
         T_('by')." " . user_reference( true, true, "black", $player_row) .
         ' &nbsp;&nbsp;&nbsp;' . date($date_fmt, $NOW) . "</td></tr>\n" .
         '<tr><td bgcolor=white>' . $txt . "</td></tr>\n";
   else
   {
      echo '<tr><td bgcolor="#' . $post_colors[ $post_type ] .
         "\"><a name=\"$ID\"><font size=\"+1\"><b>$sbj</b></font>$new</a><br> " .
         T_('by')." " . user_reference( true, true, "black", $User_ID, $Name, $Handle) .
         ' &nbsp;&nbsp;&nbsp;' . date($date_fmt, $Timestamp);
      if( $Lastedited > 0 )
         echo "&nbsp;&nbsp;&nbsp;(<a href=\"read.php?forum=$forum&thread=$thread&revision_history=$ID\">" . T_('edited') .
            "</a> " . date($date_fmt, $Lasteditedstamp) . ")";
      echo "</td></tr>\n" .
         '<tr><td bgcolor=white>' . $txt . "</td></tr>\n";
   }

   if( $post_type == 'normal' or $post_type == 'hidden' )
   {
      $hidden = $post_type == 'hidden';
      echo "<tr><td bgcolor=white align=left>";
      if(  $post_type == 'normal' ) // reply link
         echo "<a href =\"read.php?forum=$forum&thread=$thread&reply=$ID#$ID\">[ " .
            T_('reply') . " ]</a>&nbsp;&nbsp;";
      if( $my_post ) // edit link
         echo "<a href =\"read.php?forum=$forum&thread=$thread&edit=$ID#$ID\">" .
            "<font color=\"#ee6666\">[ " . T_('edit') . " ]</font></a>&nbsp;&nbsp;";
      if( $is_editor ) // hide/show link
         echo "<a href =\"read.php?forum=$forum&thread=$thread&" .
            ( $hidden ? 'show' : 'hide' ) . "=$ID#$ID\"><font color=\"#ee6666\">[ " .
            ( $hidden ? T_('show') : T_('hide') ) . " ]</font></a>";

      echo "</td></tr>\n";
   }
}


function revision_history($post_id)
{
   global $links, $cols, $Name, $Handle, $Lasteditedstamp, $Timestamp, $Lastread, $NOW;

   $result = mysql_query(
      "SELECT Posts.*, " .
      "Players.ID AS uid, Players.Name, Players.Handle, " .
      "UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp, " .
      "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
      "UNIX_TIMESTAMP(GREATEST(Posts.Time,Posts.Lastedited)) AS Timestamp " .
      "FROM Posts LEFT JOIN Players ON Posts.User_ID=Players.ID " .
      "WHERE Posts.ID='$post_id' OR (Depth=0 AND Parent_ID='$post_id') " .
      "ORDER BY Timestamp DESC") or die(mysql_error());


   $headline = array(T_("Revision history") => "colspan=$cols");
   $links |= LINK_BACK_TO_THREAD;
   $Lastread = $NOW;

   start_table($headline, $links, 'width="99%"', $cols);

   echo "<tr><td colspan=$cols><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";

   $cur_depth = 1;
   change_depth($cur_depth,1);
   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      draw_post(($cur_depth==1 ? 'reply' : 'edit' ), true, $row['Subject'], $row['Text'], null);
      change_depth($cur_depth,2);
   }

   change_depth($cur_depth,1);

   echo "</table></td></tr>\n";
   end_table($links, $cols);
   end_page();
   exit;
}


function change_depth(&$cur_depth, $new_depth)
{
   while( $cur_depth < $new_depth )
   {
      echo "<tr><td><ul><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";
      $cur_depth++;
   }

   while( $cur_depth > $new_depth )
   {
      echo "</table></ul></td></tr>\n";
      $cur_depth--;
   }
}








{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in and ( ($reply > 0) or isset($_POST['post']) ) )
      error("not_logged_in");

   $forum = @$_REQUEST['forum']+0;
   $thread = @$_REQUEST['thread']+0;
   $reply = @$_REQUEST['reply']+0;
   $edit = @$_REQUEST['edit']+0;

   $Forumname = forum_name($forum, $moderated);

   if( isset($_POST['post']) )
   {
      post_message($player_row, $moderated);
      jump_to("forum/list.php?forum=$forum");
   }

   $preview = isset($_POST['preview']);
   $preview_ID = ($edit > 0 ? $edit : @$_POST['parent']);
   if( $preview )
   {
      $preview_Subject = stripslashes(trim(@$_POST['Subject']));
      $preview_Text = stripslashes(trim(@$_POST['Text']));
      $preview_GoDiagrams = create_godiagrams($preview_Text);
   }

   $cols=2;
   $headline = array(T_("Reading thread") => "colspan=$cols");
   $links = LINK_FORUMS | LINK_THREADS;

   if( ($player_row['admin_level'] & ADMIN_FORUM) > 0 )
   {
      $links |= LINK_TOGGLE_EDITOR;

      if( @$_GET['show'] > 0 )
         approve_message( @$_GET['show'], $thread, true );
      else if( @$_GET['hide'] > 0 )
         approve_message( @$_GET['hide'], $thread, false );

      toggle_editor_cookie();
      $is_editor = ($_COOKIE[COOKIE_PREFIX.'forumeditor'] === 'y');
   }

   start_page(T_('Forum') . " - $Forumname", true, $logged_in, $player_row );

   echo "<center><h4><font color=$h3_color>$Forumname</font></H4></center>\n";

   if( @$_GET['revision_history'] > 0 )
      revision_history(@$_GET['revision_history']); //set $Lastread

   start_table($headline, $links, 'width="99%"', $cols);

   $result = mysql_query("SELECT UNIX_TIMESTAMP(Time) AS Lastread FROM Forumreads " .
                         "WHERE User_ID=" . $player_row["ID"] . " AND Thread_ID=$thread");

   if( mysql_num_rows($result) == 1 )
      extract( mysql_fetch_array( $result ) );

   $result = mysql_query("SELECT Posts.*, " .
                         "UNIX_TIMESTAMP(Posts.Lastchanged) AS Lastchangedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Lastedited) AS Lasteditedstamp, " .
                         "UNIX_TIMESTAMP(Posts.Time) AS Timestamp, " .
                         "Players.ID AS uid, Players.Name, Players.Handle " .
                         "FROM Posts LEFT JOIN Players ON Posts.User_ID=Players.ID " .
                         "WHERE Forum_ID=$forum AND Thread_ID=$thread " .
                         "ORDER BY PosIndex");

   echo "<tr><td colspan=$cols><table width=\"100%\" cellpadding=2 cellspacing=0 border=0>\n";

   $thread_Subject = '';
   $Lastchangedthread = 0 ;
   $cur_depth=1;
   while( $row = mysql_fetch_array( $result ) )
   {
      $Name = '?';
      extract($row);

      $hidden = ($Approved == 'N');

      if( $hidden and !$is_editor )
         continue;

      if( $thread == $ID ) //Initial post of the thread
         $thread_Subject = $Subject;

      change_depth( $cur_depth, $Depth );

      if( !$Lastchangedthread )
         $Lastchangedthread = $Lastchangedstamp;



      $post_type = 'normal';

      if( $hidden )
         $post_type = 'hidden';

      if( $reply == $ID )
         $post_type = 'reply';

      if( $edit == $ID )
         $post_type = 'edit';

      $GoDiagrams = find_godiagrams($Text);

      draw_post($post_type, $uid == $player_row['ID'], $Subject, $Text, $GoDiagrams);

      if( $preview and $preview_ID == $ID )
      {
         change_depth( $cur_depth, $cur_depth + 1 );
         $Subject = $preview_Subject;
         $Text = $preview_Text;
         $GoDiagrams = $preview_GoDiagrams;
         $post_type = 'preview';
         draw_post($post_type, false, $Subject, $Text, $GoDiagrams);
      }

      if( $post_type != 'normal' and $post_type != 'hidden' )
      {
         if( $post_type == 'reply' )
         {
            $Text = '';
            $GoDiagrams = null;
         }
         echo "<tr><td>\n";
         message_box($post_type, $ID, $GoDiagrams, $Subject, $Text);
         echo "</td></tr>\n";
      }
   }

   if( $preview and $preview_ID == 0 )
   {
      change_depth( $cur_depth, $cur_depth + 1 );
      $Subject = $preview_Subject;
      $Text = $preview_Text;
      $GoDiagrams = $preview_GoDiagrams;
      draw_post('preview', false, $Subject, $Text, $GoDiagrams);
      echo "<tr><td>\n";
      message_box('preview', $thread, $GoDiagrams, $Subject, $Text);
      echo "</td></tr>\n";
   }

   change_depth($cur_depth, 1);

   if( !($reply > 0) and !$preview and !($edit>0))
   {
      echo "<tr><td>\n";
      if( $thread > 0 )
         echo '<hr>';
      message_box('normal', $thread, null, $thread_Subject);
      echo "</td></tr>\n";
   }

   echo "</table></td></tr>\n";

   end_table($links, $cols);


// Update Forumreads to remove the 'new' flag

   if( $Lastchangedthread + $new_end > $NOW )
   {
      mysql_query( "REPLACE INTO Forumreads SET " .
                   "User_ID=" . $player_row["ID"] . ", " .
                   "Thread_ID=$thread, " .
                   "Time=FROM_UNIXTIME($NOW)" );
   }

   end_page();
}
?>

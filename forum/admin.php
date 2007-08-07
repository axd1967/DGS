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
require_once( "../include/faq_functions.php" ); //for TD_button()

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_FORUM) )
      error('adminlevel_too_low');

   $fid = max(0,@$_REQUEST['id']);

   $show_list = true;
   $page = 'admin.php';
   $abspage = 'forum/'.$page;
   $ThePage['class']= 'ForumAdmin'; //temporary solution to CSS problem


   // ***********        Move entry       ****************

   // args: id, move=u|d, dir=length of the move (int, pos or neg)
   if( ($action=@$_REQUEST['move']) == 'u' or $action == 'd' )
   {
      $dir = isset($_REQUEST['dir']) ? (int)$_REQUEST['dir'] : 1;
      $dir = $action == 'd' ? $dir : -$dir; //because ID top < ID bottom

      $row = mysql_single_fetch( 'forum_admin.move.find',
                "SELECT * FROM Forums WHERE ID=$fid")
          or error('admin_no_such_entry',"forum_admin.move.$action.read($fid)");

      $row2 = mysql_single_fetch( 'forum_admin.move.max',
                "SELECT COUNT(*) as max FROM Forums")
          or error('mysql_query_failed',"forum_admin.move.$action.max");
      $max = $row2['max'];

      $start = $row['SortOrder'];
      $end = max( 1, min( $max, $start + $dir));
      $cnt = abs($end - $start);
      if( $cnt )
      {
         $dir = $dir>0 ? 1 : -1;
         $start+= $dir;

         //shift the neighbours backward, reference by SortOrder
         mysql_query("UPDATE Forums SET SortOrder=SortOrder-($dir)"
                     . " WHERE SortOrder BETWEEN "
                        .($start>$end?"$end AND $start":"$start AND $end")
                     . " LIMIT $cnt" )
            or error('mysql_query_failed','forum_admin.move.update_sortorder1');

         //move the entry forward, reference by ID
         mysql_query("UPDATE Forums SET SortOrder=$end"
                     . " WHERE ID=$fid LIMIT 1")
            or error('mysql_query_failed','forum_admin.move.update_sortorder2');
      }
      jump_to($abspage); //clean URL
   } //move


   // ***********        Edit entry       ****************

   // args: id, edit=t [ do_edit=? ]
   // keep it tested before 'do_edit'
   else if( @$_REQUEST['edit'] )
   {
      $title = T_('Forum Admin').' - '.T_('Edit forum');
      start_page($title, true, $logged_in, $player_row );
      echo "<h3 class=Header>$title</h3>\n";

      $show_list = false;

      $row = mysql_single_fetch( 'forum_admin.edit.find',
                "SELECT * FROM Forums WHERE ID=$fid")
          or error('admin_no_such_entry',"forum_admin.edit.find($fid)");

      $edit_form = new Form('forumeditform', "$page?id=$fid", FORM_POST );

      //$edit_form->add_row( array( 'HEADER', T_//('Edit Forum') ) );
      $edit_form->add_row( array( 'DESCRIPTION', T_('Name'),
                                  'TEXTINPUT', 'name', 50, 80, $row['Name'] ) );
      $edit_form->add_row( array( 'DESCRIPTION', T_('Description'),
                                  'TEXTAREA', 'description', 50, 4, $row['Description'] ) );
      $edit_form->add_row( array( 'DESCRIPTION' , T_('Moderated'),
                                  'CHECKBOX', 'moderated', 1, '', $row['Moderated'] == 'Y'));
      $edit_form->add_row( array(
                           'SUBMITBUTTONX', 'do_edit', T_('Save entry'),
                              array('accesskey'=>'x'),
                           'SUBMITBUTTON', 'back', T_('Back to list'),
                           ));
      $edit_form->echo_string(1);
   } //edit


   // ***********        Save edited entry       ****************

   // args: id, do_edit=t
   // keep it tested after 'edit'
   else if( @$_REQUEST['do_edit'] )
   {
      $row = mysql_single_fetch( 'forum_admin.do_edit.find',
                "SELECT * FROM Forums WHERE ID=$fid")
          or error('admin_no_such_entry',"forum_admin.do_edit.find($fid)");

      $name = trim( get_request_arg('name') );
      $description = trim( get_request_arg('description') );

      // Delete or update ?
      if( !$name && !$description )
      { // Delete
         if( mysql_single_fetch( 'forum_admin.do_edit.empty',
            "SELECT ID FROM Posts WHERE Forum_ID=".$row['ID']." LIMIT 1") )
         {
            $msg = urlencode('Error: forum not empty');
            jump_to("$abspage?sysmsg=$msg");
         }

         mysql_query("DELETE FROM Forums WHERE ID=$fid LIMIT 1")
            or error('mysql_query_failed','forum_admin.do_edit.delete');
         mysql_query("UPDATE Forums SET SortOrder=SortOrder-1 " .
                     "WHERE SortOrder>" . $row["SortOrder"])
            or error('mysql_query_failed','forum_admin.do_edit.update_sortorder');
      }
      else
      { //Update
         if( !$name )
         {
            $msg = urlencode('Error: an entry must be given');
            jump_to("$abspage?sysmsg=$msg");
         }

         mysql_query("UPDATE Forums SET"
                  . " Name='".mysql_addslashes($name)."'"
                  . ",Description='".mysql_addslashes($description)."'"
                  . ",Moderated=" . (@$_REQUEST['moderated'] ? "'Y'" : "'N'")
                  . " WHERE ID=" . $row['ID'] . " LIMIT 1")
            or error('mysql_query_failed','forum_admin.do_edit.update_forums');
      }

      jump_to($abspage); //clean URL
   } //do_edit


   // ***********        New entry       ****************

   // args: id, new=t
   // keep it tested before 'do_new'
   else if( @$_REQUEST['new'] )
   {
      $title = T_('Forum Admin').' - '.T_('New forum');
      start_page($title, true, $logged_in, $player_row );
      echo "<h3 class=Header>$title</h3>\n";

      $show_list = false;

      $edit_form = new Form('forumnewform', "$page?id=$fid", FORM_POST );

      //$edit_form->add_row( array( 'HEADER', T_//('New Forum') ) );
      $edit_form->add_row( array( 'DESCRIPTION', T_('Name'),
                                  'TEXTINPUT', 'name', 50, 80, '' ) );
      $edit_form->add_row( array( 'DESCRIPTION', T_('Description'),
                                  'TEXTAREA', 'description', 50, 4, '' ) );
      $edit_form->add_row( array( 'DESCRIPTION' , T_('Moderated'),
                                  'CHECKBOX', 'moderated', 1, '', false));
      $edit_form->add_row( array(
                           'SUBMITBUTTONX', 'do_new', T_('Add entry'),
                              array('accesskey'=>'x'),
                           'SUBMITBUTTON', 'back', T_('Back to list'),
                           ));
      $edit_form->echo_string(1);
   } //new


    // ***********        Save new entry       ****************

   // args: id, do_new=t (insert after entry #id, 0=first)
   // keep it tested after 'new'
   else if( @$_REQUEST['do_new'] )
   {
      $name = trim( get_request_arg('name') );
      $description = trim( get_request_arg('description') );
      if( !$name )
      {
         $msg = urlencode('Error: an entry must be given');
         jump_to("$abspage?sysmsg=$msg");
      }

      $query = "SELECT * FROM Forums WHERE ID=$fid";
      $row = mysql_single_fetch( 'forum_admin.do_new.find', $query );
      if( $row )
         $SortOrder = $row['SortOrder'];
      else
         $SortOrder = 0;

      mysql_query("UPDATE Forums SET SortOrder=SortOrder+1 " .
                  'WHERE SortOrder>' . $SortOrder )
         or error('mysql_query_failed','forum_admin.update_sortorder');

      mysql_query("INSERT INTO Forums SET"
               . " Name='".mysql_addslashes($name)."'"
               . ",Description='".mysql_addslashes($description)."'"
               . ",Moderated=" . (@$_REQUEST['moderated'] ? "'Y'" : "'N'")
               . ",SortOrder=" . ($SortOrder+1))
         or error('mysql_query_failed','forum_admin.insert');

      jump_to($abspage); //clean URL
   } //do_new



   // ***********       Show whole list       ****************

   if( $show_list )
   {
      $title = T_('Forum Admin');
      start_page($title, true, $logged_in, $player_row );

      $query =
         "SELECT Forums.ID,Description,Name"
         . " FROM (Forums)"
         //. " LEFT JOIN Posts ON Posts.Forum_ID=Forums.ID"
         . " GROUP BY Forums.ID"
         . " ORDER BY SortOrder";

      #echo "<br>QUERY: $query<br>\n"; // debug
      $result = mysql_query($query)
         or error('mysql_query_failed','forum_admin.list');

      echo "<h3 class=Header>$title</h3>\n";

      $nbcol = 5;
      echo "<a name=\"general\"></a><table class=ForumAdmin>\n";

      // table-columns:

      echo "<tr>"
         . TD_button( T_('Add new forum'),
               "$page?new=1".URI_AMP."id=0",
               '../images/new.png', 'N')
         . '<td>(first entry)</td>'
         . '<td colspan='.($nbcol-2).'></td></tr>';

      while( $row = mysql_fetch_assoc( $result ) )
      {
         $name = (empty($row['Name']) ? '---' : $row['Name']);

         echo '<tr>';
         echo '<td colspan='.($nbcol-3).' class=Entry>'
            ."<A href=\"$page?edit=1".URI_AMP."id=" . $row['ID']
            .'" title="' . T_("Edit") . "\">$name</A></td>";

         echo TD_button( T_('Move up'),
               "$page?move=u".URI_AMP.'id=' . $row['ID'],
               '../images/up.png', 'u');
         echo TD_button( T_('Move down'),
               "$page?move=d".URI_AMP.'id=' . $row['ID'],
               '../images/down.png', 'd');
         echo TD_button( T_('Add new forum'),
               "$page?new=1".URI_AMP."id=" . $row['ID'],
               '../images/new.png', 'N');
         echo '</tr>';
      }
      mysql_free_result($result);

      echo "</table>\n";
   } //show_list

   end_page();
}
?>

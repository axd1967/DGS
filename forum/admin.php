<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// translations removed for this page: $TranslateGroups[] = "Forum";
// may be changed to:
// translations removed for this page: $TranslateGroups[] = "Admin";

chdir('..');
require_once( "forum/forum_functions.php" );
require_once( "include/gui_functions.php" );

$ThePage = new Page('ForumAdmin');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DEVELOPER ) )
      error('adminlevel_too_low');

   $fid = max(0,@$_REQUEST['id']);

   $show_list = true;
   $page = 'admin.php';
   $abspage = 'forum/'.$page;

   $ARR_FORUMOPTS = array( // maskval => [ argname, bit-text, label, descr ]
      FORUMOPT_MODERATED   => array( 'moderated', 'MODERATED', T_('Moderated'), T_('all posts need moderation') ),
      FORUMOPT_GROUP_ADMIN => array( 'fgr_admin', 'FGR_ADMIN', '', T_('ADMIN - mark as admin-forum') ),
      FORUMOPT_GROUP_DEV   => array( 'fgr_dev',   'FGR_DEV',   '', T_('DEV - mark as development-forum') ),
   );

   // ***********        Move entry       ****************

   // args: id, move=u|d, dir=length of the move (int, pos or neg)
   if( ($action=@$_REQUEST['move']) == 'u' || $action == 'd' )
   {
      $dir = isset($_REQUEST['dir']) ? (int)$_REQUEST['dir'] : 1;
      $dir = $action == 'd' ? $dir : -$dir; //because ID top < ID bottom

      $row = mysql_single_fetch( 'forum_admin.move.find',
                "SELECT * FROM Forums WHERE ID=$fid")
          or error('admin_no_such_entry',"forum_admin.move.$action.read($fid)");

      $row2 = mysql_single_fetch( 'forum_admin.move.max',
                "SELECT COUNT(*) AS max FROM Forums")
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
         db_query( "forum_admin.move.update_sortorder1($start:$end)",
               "UPDATE Forums SET SortOrder=SortOrder-($dir)"
                     . " WHERE SortOrder BETWEEN "
                        .($start>$end?"$end AND $start":"$start AND $end")
                     . " LIMIT $cnt" );

         //move the entry forward, reference by ID
         db_query( "forum_admin.move.update_sortorder2($fid,$end)",
               "UPDATE Forums SET SortOrder=$end WHERE ID=$fid LIMIT 1" );
      }
      jump_to($abspage); //clean URL
   } //move


   // ***********        Edit entry       ****************

   // args: id, edit=t [ do_edit=? ]
   // keep it tested before 'do_edit'
   else if( @$_REQUEST['edit'] )
   {
      $title = /*T_*/('Forum Admin').' - './*T_*/('Edit forum');
      start_page($title, true, $logged_in, $player_row );
      echo "<h3 class=Header>$title</h3>\n";

      $show_list = false;

      $row = mysql_single_fetch( 'forum_admin.edit.find',
                "SELECT * FROM Forums WHERE ID=$fid")
          or error('admin_no_such_entry',"forum_admin.edit.find($fid)");

      $edit_form = new Form('forumeditform', "$page?id=$fid", FORM_POST );

      //$edit_form->add_row( array( 'HEADER', /*T_*///('Edit Forum') ) );
      $edit_form->add_row( array( 'DESCRIPTION', /*T_*/('Name'),
                                  'TEXTINPUT', 'name', 50, 80, $row['Name'] ) );
      $edit_form->add_row( array( 'DESCRIPTION', /*T_*/('Description'),
                                  'TEXTAREA', 'description', 50, 4, $row['Description'] ) );
      add_form_forum_options( $edit_form, $row['Options'] );
      $edit_form->add_row( array(
                           'SUBMITBUTTONX', 'do_edit', /*T_*/('Save entry'),
                              array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
                           'SUBMITBUTTON', 'back', /*T_*/('Back to list'),
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

         db_query( "forum_admin.do_edit.delete($fid)",
               "DELETE FROM Forums WHERE ID=$fid LIMIT 1" );
         db_query( "forum_admin.do_edit.update_sortorder",
               "UPDATE Forums SET SortOrder=SortOrder-1 WHERE SortOrder>" . $row["SortOrder"] );
      }
      else
      { //Update
         if( !$name )
         {
            $msg = urlencode('Error: an entry must be given');
            jump_to("$abspage?sysmsg=$msg");
         }

         db_query( "forum_admin.do_edit.update_forums({$row['ID']})",
               "UPDATE Forums SET"
                  . " Name='".mysql_addslashes($name)."'"
                  . ",Description='".mysql_addslashes($description)."'"
                  . ',Options='. build_forum_options( @$_REQUEST )
                  . " WHERE ID=" . $row['ID'] . " LIMIT 1" );
      }

      jump_to($abspage); //clean URL
   } //do_edit


   // ***********        New entry       ****************

   // args: id, new=t
   // keep it tested before 'do_new'
   else if( @$_REQUEST['new'] )
   {
      $title = /*T_*/('Forum Admin').' - './*T_*/('New forum');
      start_page($title, true, $logged_in, $player_row );
      echo "<h3 class=Header>$title</h3>\n";

      $show_list = false;

      $edit_form = new Form('forumnewform', "$page?id=$fid", FORM_POST );

      //$edit_form->add_row( array( 'HEADER', /*T_*///('New Forum') ) );
      $edit_form->add_row( array( 'DESCRIPTION', /*T_*/('Name'),
                                  'TEXTINPUT', 'name', 50, 80, '' ) );
      $edit_form->add_row( array( 'DESCRIPTION', /*T_*/('Description'),
                                  'TEXTAREA', 'description', 50, 4, '' ) );
      add_form_forum_options( $edit_form, 0 );
      $edit_form->add_row( array(
                           'SUBMITBUTTONX', 'do_new', /*T_*/('Add entry'),
                              array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
                           'SUBMITBUTTON', 'back', /*T_*/('Back to list'),
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

      db_query( 'forum_admin.update_sortorder',
            'UPDATE Forums SET SortOrder=SortOrder+1 WHERE SortOrder>' . $SortOrder );

      db_query( 'forum_admin.insert',
            "INSERT INTO Forums SET"
               . " Name='".mysql_addslashes($name)."'"
               . ",Description='".mysql_addslashes($description)."'"
               . ",Options=" . build_forum_options( @$_REQUEST )
               . ",SortOrder=" . ($SortOrder+1) );

      jump_to($abspage); //clean URL
   } //do_new



   // ***********       Show whole list       ****************

   if( $show_list )
   {
      $title = /*T_*/('Forum Admin');
      start_page($title, true, $logged_in, $player_row );

      $query = 'SELECT * FROM Forums ORDER BY SortOrder';
      #echo "<br>QUERY: $query<br>\n"; // debug
      $result = db_query( 'forum_admin.list', $query );

      echo "<h3 class=Header>$title</h3>\n";

      $nbcol = 6;
      echo "<a name=\"general\"></a><table class=ForumAdmin>\n";

      // table-columns:

      echo "<tr>"
         . TD_button( /*T_*/('Add new forum'),
               "$page?new=1".URI_AMP."id=0",
               '../images/new.png', 'N')
         . '<td>(first entry)</td>'
         . '<td colspan='.($nbcol-2).'></td></tr>';

      while( $row = mysql_fetch_assoc( $result ) )
      {
         $name = (empty($row['Name']) ? NO_VALUE : $row['Name']);

         echo '<tr>';
         echo '<td colspan='.($nbcol-3).' class=Entry>'
            ."<A href=\"$page?edit=1".URI_AMP."id=" . $row['ID']
            .'" title="' . /*T_*/('Edit') . "\">$name</A></td>";

         echo TD_button( /*T_*/('Move up'),
               "$page?move=u".URI_AMP.'id=' . $row['ID'],
               '../images/up.png', 'u');
         echo TD_button( /*T_*/('Move down'),
               "$page?move=d".URI_AMP.'id=' . $row['ID'],
               '../images/down.png', 'd');
         echo TD_button( /*T_*/('Add new forum'),
               "$page?new=1".URI_AMP."id=" . $row['ID'],
               '../images/new.png', 'N');

         echo '<td>' . build_forum_options_text( $row['Options'] ) . '</td>';
         echo '</tr>';
      }
      mysql_free_result($result);

      echo "</table>\n";
   } //show_list

   end_page();
}

// Returns bitmask for Forums.Options
// param urlargs array with URL-args from admin-form
function build_forum_options( $urlargs )
{
   global $ARR_FORUMOPTS;
   $fopts = 0;
   foreach( $ARR_FORUMOPTS as $maskval => $arr )
   {
      $fopts |= (@$_REQUEST[$arr[0]] ? $maskval : 0);
   }
   return $fopts;
}

// Returns string from forum-options
function build_forum_options_text( $opts )
{
   global $ARR_FORUMOPTS;
   $arrout = array();
   foreach( $ARR_FORUMOPTS as $maskval => $arr )
   {
      if( $opts & $maskval )
         $arrout[] = $arr[1];
   }
   return '['.implode(', ', $arrout).']';
}

// Adds checkboxes for forum-options to form
function add_form_forum_options( &$form, $fopts )
{
   global $ARR_FORUMOPTS;
   foreach( $ARR_FORUMOPTS as $maskval => $arr )
   {
      $rowarr = array();

      if( (string)$arr[2] != '' )
         array_push( $rowarr,
            'DESCRIPTION', $arr[2] );
      else
         $rowarr[] = 'TAB';

      array_push( $rowarr,
         'CHECKBOX', $arr[0], 1, $arr[3], ($fopts & $maskval) );

      $form->add_row( $rowarr );
   }
}

?>

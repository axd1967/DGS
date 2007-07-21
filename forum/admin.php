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

require_once( "forum_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
     error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_FORUM) )
      error('adminlevel_too_low');

   $id = @$_GET["id"]+0;

   $show_list = true;

  // ***********        New forum       ****************

  if( @$_GET["new"] == 't' )
  {
     $title = T_('Forum Admin').' - '.T_('New forum');
     start_page($title, true, $logged_in, $player_row );
     echo "<h3 class=Header>$title</h3>\n";

     $show_list = false;
     echo "<center>\n";

     $forum_edit_form = new Form( 'forumform', "admin.php?do_new=t".URI_AMP."id=$id", FORM_POST );

     $forum_edit_form->add_row( array( 'HEADER', T_('New Forum') ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Name'),
                                       'TEXTINPUT', 'name', 50, 80, '' ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Description'),
                                       'TEXTAREA', 'description', 50, 4, '' ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION' , T_('Moderated'),
                                       'CHECKBOX', 'moderated', 1, '', false));
     $forum_edit_form->add_row( array( 'SUBMITBUTTON', 'submit', T_('Submit') ) );
     $forum_edit_form->echo_string(1);
  }

  // ***********        Save new forum       ****************

  else if( @$_GET["do_new"] == 't' )
  {
     $SortOrder = 0;
     $result = mysql_query( "SELECT * FROM Forums WHERE ID=" . (@$_GET["id"]+0) )
        or error("mysql_query_failed",'forum_admin.do_new.find_forums');

     if( @mysql_num_rows($result) == 1 )
     {
        $row = mysql_fetch_array( $result );
        $SortOrder = $row['SortOrder'];
     }

     $name = trim( @$_POST["name"] );
     $description = trim( @$_POST["description"] );

     if( !empty($name) )
     {
        mysql_query("UPDATE Forums SET SortOrder=SortOrder+1 " .
                    'WHERE SortOrder>' . $SortOrder )
        or error("mysql_query_failed",'forum_admin.update_sortorder');

        mysql_query("INSERT INTO Forums SET " .
                    "Name=\"$name\", " .
                    "Description=\"$description\", " .
                    "Moderated=" . (@$_POST["moderated"] ? '"Y"' : '"N"') . ", " .
                    "SortOrder=" . ($SortOrder+1))
           or error("mysql_query_failed",'forum_admin.insert');
     }
     else
     {
        $msg = urlencode('Error: A Forum name must be given');
        jump_to("forum/admin.php?sysmsg=$msg");
     }

     jump_to("forum/admin.php");
  }

  // ***********        Edit forum       ****************

  else if( @$_GET["edit"] == 't' )
  {
     $title = T_('Forum Admin').' - '.T_('Edit forum');
     start_page($title, true, $logged_in, $player_row );
     echo "<h3 class=Header>$title</h3>\n";

     $show_list = false;
     echo "<center>\n";

     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" )
           or error("mysql_query_failed",'forum_admin.edit.find_forums');

     if( @mysql_num_rows($result) != 1 )
        error("admin_no_such_entry",'forum_admin.edit.find_forums');

     $row = mysql_fetch_array( $result );

     $forum_edit_form = new Form( 'forumform', "admin.php?do_edit=t".URI_AMP."id=$id", FORM_POST );

     $forum_edit_form->add_row( array( 'HEADER', T_('Edit Forum') ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Name'),
                                       'TEXTINPUT', 'name', 50, 80, $row['Name'] ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Description'),
                                       'TEXTAREA', 'description', 50, 4, $row['Description'] ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION' , T_('Moderated'),
                                       'CHECKBOX', 'moderated', 1, '', $row['Moderated'] == 'Y'));
     $forum_edit_form->add_row( array( 'SUBMITBUTTON', 'submit', T_('Submit') ) );
     $forum_edit_form->echo_string(1);
  }


  // ***********        Save edited forum       ****************

  else if( @$_GET["do_edit"] == 't' )
  {
     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" )
        or error("mysql_query_failed",'forum_admin.do_edit.find_forums');

     if( @mysql_num_rows($result) != 1 )
        error("admin_no_such_entry",'forum_admin.do_edit.find_forums');

     $row = mysql_fetch_array( $result );

     if( !isset( $_POST["name"] ) )
        error("No data");

     $name = trim( $_POST["name"] );
     $description = trim( @$_POST["description"] );


     // Delete ?
     if( empty($name) and empty($description) and
         @mysql_num_rows(mysql_query("SELECT ID FROM Posts " .
                                    "WHERE Forum_ID=" . $row["ID"] . " LIMIT 1")) == 0 )
     {
        mysql_query("DELETE FROM Forums WHERE ID=$id LIMIT 1")
           or error("mysql_query_failed",'forum_admin.do_edit.delete');
        mysql_query("UPDATE Forums SET SortOrder=SortOrder-1 " .
                    "WHERE SortOrder>" . $row["SortOrder"])
           or error("mysql_query_failed",'forum_admin.do_edit.update_sortorder');
     }
     else
     {
        mysql_query("UPDATE Forums SET ".
                    "Name=\"$name\", " .
                    "Description=\"$description\", " .
                    "Moderated=" . (@$_POST['moderated'] ? '"Y"' : '"N"') . " " .
                    "WHERE ID=" . $row['ID'] . " LIMIT 1")
           or error("mysql_query_failed",'forum_admin.do_edit.update_forums');
     }

     jump_to("forum/admin.php");
  }



  // ***********        Move forum       ****************

  else if( @$_GET["move"] == 'u' or @$_GET["move"] == 'd' )
  {
     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" )
        or error('mysql_query_failed','forum_admin.move.find_forums');

     if( @mysql_num_rows($result) != 1 )
        error("admin_no_such_entry",'admin3');

     $row = mysql_fetch_array( $result );

     $result = mysql_query( "SELECT MAX(SortOrder) as max FROM Forums")
        or error('mysql_query_failed','forum_admin.move.max_sortorder');

     $row2 = mysql_fetch_array( $result );
     $max = $row2["max"];

     if( ( @$_GET["move"] != 'u' or $row["SortOrder"] > 1 ) and
         ( @$_GET["move"] != 'd' or $row["SortOrder"] < $max ) )
     {
        $dir = (@$_GET["move"] == 'd' ? 1 : -1 );

        mysql_query( "UPDATE Forums SET SortOrder=SortOrder-($dir) " .
                     'WHERE SortOrder=' . ($row["SortOrder"]+$dir) )
           or error('mysql_query_failed','forum_admin.move.update_sortorder1');

        mysql_query( "UPDATE Forums SET SortOrder=SortOrder+($dir) " .
                     "WHERE ID=" . $row["ID"] . " LIMIT 1")
           or error('mysql_query_failed','forum_admin.move.update_sortorder2');
     }
     jump_to("forum/admin.php");
  }



  // ***********       Show forum list       ****************

  if( $show_list )
  {
     $title = T_('Forum Admin');
     start_page($title, true, $logged_in, $player_row );
     echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
        T_('Forum Admin') . "</font></h3>\n";

     echo "<center>\n";

     echo "<table align=center width=\"85%\" border=0><tr><td>\n";

     $result =
        mysql_query("SELECT Forums.ID,Description,Name " .
                    "FROM (Forums) LEFT JOIN Posts ON Posts.Forum_ID=Forums.ID " .
                    "GROUP BY Forums.ID " .
                    "ORDER BY SortOrder")
        or error('mysql_query_failed','forum_admin.list.find_forums');

     echo "<table>\n";


     echo "<tr><td colspan=4 align=right><a href=\"admin.php?new=t".URI_AMP."id=0\">" .
        '<img border=0 title="' . T_('Add new forum') . '" src="../images/new.png" alt="N"></a>';

     while( $row = mysql_fetch_array( $result ) )
     {
        $name = (empty($row['Name']) ? '-' : $row['Name']);

        echo '<tr><td>';
        echo "<A href=\"admin.php?edit=t".URI_AMP."id=" . $row['ID'] .
           '" title="' . T_("Edit") . "\">$name</A>\n";

        echo '<td width=40 align=right><a href="admin.php?move=u'.URI_AMP.'id=' . $row['ID'] .
           '"><img border=0 title="' . T_("Move up") . '" src="../images/up.png" alt="u"></a>';
        echo '<td><a href="admin.php?move=d'.URI_AMP.'id=' . $row['ID'] .
           '"><img border=0 title="' . T_("Move down") . '" src="../images/down.png" alt="d"></a>';
        echo "<td><a href=\"admin.php?new=t".URI_AMP."id=" . $row['ID'] .
           '"><img border=0 title="' . T_('Add new forum') . '" src="../images/new.png" alt="N"></a>';
     }


     echo "</table></table>\n";
  }

  echo "</center>";
  end_page();
}
?>

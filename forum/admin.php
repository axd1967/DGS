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

require( "forum_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $adm = $player_row['admin_level'];

  if( !( $adm & ADMIN_FORUM ) )
     error("adminlevel_too_low");

  $show_list = true;

  // ***********        New forum       ****************

  if( $_GET["new"] == 't' )
  {
     start_page(T_("Forum Admin").' - '.T_('New forum'), true, $logged_in, $player_row );

     echo "<center>\n";


     $forum_edit_form = new Form( 'forumform', "admin.php?do_new=t&id=$id", FORM_POST );

     $forum_edit_form->add_row( array( 'HEADER', T_('New Forum') ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Name'),
                                       'TEXTINPUT', 'name', 50, 80, '' ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Description'),
                                       'TEXTAREA', 'description', 50, 4, '' ) );
     $forum_edit_form->add_row( array( 'SUBMITBUTTON', 'submit', T_('Submit') ) );
     $forum_edit_form->echo_string();

     $show_list = false;
  }

  // ***********        Save new forum       ****************

  else if( $_GET["do_new"] == 't' )
  {
     $SortOrder = 0;
     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" );

     if( mysql_num_rows($result) == 1 )
     {
        $row = mysql_fetch_array( $result );
        $SortOrder = $row['SortOrder'];
     }

     $name = trim( $_POST["name"] );
     $description = trim( $_POST["description"] );

     if( !empty($name) and !empty($description))
     {
        mysql_query("UPDATE Forums SET SortOrder=SortOrder+1 " .
                    'WHERE SortOrder>' . $row["SortOrder"] );

        mysql_query("INSERT INTO Forums SET " .
                    "Name=\"$name\", " .
                    "Description=\"$description\", " .
                    "SortOrder=" . ($row["SortOrder"]+1));
     }

     jump_to("forum/admin.php");
  }

  // ***********        Edit forum       ****************

  else if( $_GET["edit"] == 't' )
  {
     start_page(T_("Forum Admin").' - '.T_('Edit forum'), true, $logged_in, $player_row );

     echo "<center>\n";

     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     $forum_edit_form = new Form( 'forumform', "admin.php?do_edit=t&id=$id", FORM_POST );

     $forum_edit_form->add_row( array( 'HEADER', T_('New Forum') ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Name'),
                                       'TEXTINPUT', 'name', 50, 80, $row['Name'] ) );
     $forum_edit_form->add_row( array( 'DESCRIPTION', T_('Description'),
                                       'TEXTAREA', 'description', 50, 4, $row['Description'] ) );
     $forum_edit_form->add_row( array( 'SUBMITBUTTON', 'submit', T_('Submit') ) );
     $forum_edit_form->echo_string();

     $show_list = false;
  }


  // ***********        Save edited forum       ****************

  else if( $_GET["do_edit"] == 't' )
  {
     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     if( !isset( $_POST["name"] ) )
        error("No data");

     $name = trim( $_POST["name"] );
     $description = trim( $_POST["description"] );


     // Delete ?
     if( empty($name) and empty($description) and
         mysql_num_rows(mysql_query("SELECT ID FROM Posts " .
                                    "WHERE Forum_ID=" . $row["ID"] . " LIMIT 1")) == 0 )
     {
        mysql_query("DELETE FROM Forums WHERE ID=$id LIMIT 1");
        mysql_query("UPDATE Forums SET SortOrder=SortOrder-1 " .
                    "WHERE SortOrder>" . $row["SortOrder"]);
     }
     else
     {
        mysql_query("UPDATE Forums SET Name=\"$name\", Description=\"$description\" " .
                    "WHERE ID=" . $row['ID']);
     }

     jump_to("forum/admin.php");
  }



  // ***********        Move forum       ****************

  else if( $_GET["move"] == 'u' or $_GET["move"] == 'd' )
  {
     $result = mysql_query( "SELECT * FROM Forums WHERE ID=$id" );

     if( mysql_num_rows($result) != 1 )
        error("admin_no_such_entry");

     $row = mysql_fetch_array( $result );

     $result = mysql_query( "SELECT MAX(SortOrder) as max FROM Forums");
     $row2 = mysql_fetch_array( $result );
     $max = $row2["max"];

     if( ( $_GET["move"] != 'u' or $row["SortOrder"] > 1 ) and
         ( $_GET["move"] != 'd' or $row["SortOrder"] < $max ) )
     {
        $dir = ($_GET["move"] == 'd' ? 1 : -1 );

        mysql_query( "UPDATE Forums SET SortOrder=SortOrder-($dir) " .
                     'WHERE SortOrder=' . ($row["SortOrder"]+$dir) );
        mysql_query( "UPDATE Forums SET SortOrder=SortOrder+($dir) " .
                     "WHERE ID=" . $row["ID"] );
     }
     jump_to("forum/admin.php");
  }



  // ***********       Show forum list       ****************

  if( $show_list )
  {
     start_page(T_("Forum Admin"), true, $logged_in, $player_row );

     echo "<table align=center width=\"85%\" border=0><tr><td>\n";

     echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
        T_('Forum Admin') . "</font></h3>\n";

     $result =
        mysql_query("SELECT Forums.ID,Description,Name, " .
                    "UNIX_TIMESTAMP(MAX(Lastchanged)) AS Timestamp,Count(*) AS Count " .
                    "FROM Forums LEFT JOIN Posts ON Posts.Forum_ID=Forums.ID " .
                    "GROUP BY Forums.ID " .
                    "ORDER BY SortOrder");

     echo "<table>\n";


     echo "<tr><td colspan=4 align=right><a href=\"admin.php?new=t&id=1\">" .
        '<img border=0 title="' . T_('Add new forum') . '" src="../images/new.png"></a>';

     while( $row = mysql_fetch_array( $result ) )
     {
        $name = (empty($row['Name']) ? '-' : $row['Name']);

        echo '<tr><td>';
        echo "<A href=\"admin.php?edit=t&id=" . $row['ID'] .
           '" title="' . T_("Edit") . "\">$name</A>\n";

        echo '<td width=40 align=right><a href="admin.php?move=u&id=' . $row['ID'] .
           '"><img border=0 title="' . T_("Move up") . '" src="../images/up.png"></a>';
        echo '<td><a href="admin.php?move=d&id=' . $row['ID'] .
           '"><img border=0 title="' . T_("Move down") . '" src="../images/down.png"></a>';
        echo "<td><a href=\"admin.php?new=t&id=" . $row['ID'] .
           '"><img border=0 title="' . T_('Add new forum') . '" src="../images/new.png"></a>';
     }


     echo "</table></table>\n";
  }


  end_page();
}
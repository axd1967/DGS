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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/table_columns.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_ADMINS) )
    error("adminlevel_too_low");

  $admin_tasks = array( 'TRANS' => ADMIN_TRANSLATORS,
                        'FAQ' => ADMIN_FAQ,
                        'Forum' => ADMIN_FORUM,
                        'ADMIN' => ADMIN_ADMINS,
                        'TIME' => ADMIN_TIME );

// Make sure all previous admins gets into the Admin array
  $result = mysql_query("SELECT ID, Adminlevel+0 AS admin_level FROM Players " .
                        "WHERE Adminlevel > 0");

  while( $row = mysql_fetch_array($result) )
  {
     $Admin[$row['ID']] = 0;
     $AdminOldLevel[$row['ID']] = $row['admin_level'];
  }


  if( $_GET["update"] == 't' )
  {
     foreach( $_POST as $item => $value )
     {
        if( $value != 'Y' )
           continue;

        list($type, $id) = explode('_', $item, 2);

        $val = $admin_tasks[$type];

        if( !($id > 0 or $id=='new') or !($val > 0))
           error("bad_data");

        $Admin[$id] |= $val;
     }

     if( !($Admin[$player_row["ID"]] & ADMIN_ADMINS) )
        error("admin_no_longer_admin_admin");

     if( $Admin['new'] > 0 and !empty($_POST["newadmin"]))
     {
        $result = mysql_query("SELECT ID,Adminlevel+0 AS admin_level FROM Players " .
                              "WHERE Handle=\"" . $_POST["newadmin"] . "\"");

        if( mysql_num_rows($result) != 1 )
           error("unknown_user");

        $row = mysql_fetch_array($result);
        if( $row["admin_level"] > 0 )
           error("new_admin_already_admin");

        $Admin[$row['ID']] = $Admin['new'];
        unset($Admin['new']);
     }

     foreach( $Admin as $id => $adm_level )
     {
        if( $adm_level != $AdminOldLevel[$id] )
           mysql_query("UPDATE Players SET Adminlevel=$adm_level WHERE ID=$id LIMIT 1");
     }
  }

  start_page(T_("Admin").' - '.T_('Edit admin staff'), true, $logged_in, $player_row );

  $result = mysql_query("SELECT ID, Handle, Name, Adminlevel+0 AS admin_level FROM Players " .
                        "WHERE Adminlevel > 0");


   if( mysql_num_rows($result) > 0 )
   {
      echo "<center><p><h3><font color=$h3_color><B>" . T_('Admins') . ":</B></font></h3><p>\n";

      echo '<form name="admform" action="admin_admins.php?update=t" method="POST">'."\n";

      $atable = new Table( '', '', '', true );

      $atable->add_tablehead(1, T_('ID'), NULL, true, true);
      $atable->add_tablehead(2, T_('Nick'), NULL, false, true);
      $atable->add_tablehead(3, T_('Name'), NULL, false, true);
      $atable->add_tablehead(4, T_('Translators'), NULL, true, true);
      $atable->add_tablehead(5, T_('FAQ'), NULL, true, true);
      $atable->add_tablehead(6, T_('Forum'), NULL, true, true);
      $atable->add_tablehead(7, T_('Admins'), NULL, true, true);
      $atable->add_tablehead(8, T_('Time'), NULL, true, true);

      while( $row = mysql_fetch_array( $result ) )
      {
         $id = $row["ID"];

         $arow_strings = array();
         $arow_strings[1] = "<td><A href=\"userinfo.php?uid=$id\">$id</A></td>";
         $arow_strings[2] = "<td><A href=\"userinfo.php?uid=$id\">" . $row["Handle"] . "</A></td>";
         $arow_strings[3] = "<td><A href=\"userinfo.php?uid=$id\">" .
            make_html_safe($row["Name"]) . "</A></td>";
         $arow_strings[4] = "<td align=center>" .
            "<input type=\"checkbox\" name=\"TRANS_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_TRANSLATORS) ? ' checked' : '') . "></td>";
         $arow_strings[5] = "<td align=center>" .
            "<input type=\"checkbox\" name=\"FAQ_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_FAQ) ? ' checked' : '') . "></td>";
         $arow_strings[6] = "<td align=center>" .
            "<input type=\"checkbox\" name=\"Forum_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_FORUM) ? ' checked' : '') . "></td>";
         $arow_strings[7] = "<td align=center>" .
            "<input type=\"checkbox\" name=\"ADMIN_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_ADMINS) ? ' checked' : '') . "></td>";
         $arow_strings[8] = "<td align=center>" .
            "<input type=\"checkbox\" name=\"TIME_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_TIME) ? ' checked' : '') . "></td>";

         $atable->add_row( $arow_strings );
      }

      $atable->add_row(
         array( 1 => "<td colspan=3>" . T_('New admin') .
                ': <input type="text" name="newadmin" value="" ' .
                'size="16" maxlength="16">',

                '',

                '',

                "<td align=center>" .
                "<input type=\"checkbox\" name=\"TRANS_new\" value=\"Y\"></td>",

                "<td align=center>" .
                "<input type=\"checkbox\" name=\"FAQ_new\" value=\"Y\"></td>",

                "<td align=center>" .
                "<input type=\"checkbox\" name=\"Forum_new\" value=\"Y\"></td>",

                "<td align=center>" .
                "<input type=\"checkbox\" name=\"ADMIN_new\" value=\"Y\"></td>",

                "<td align=center>" .
                "<input type=\"checkbox\" name=\"TIME_new\" value=\"Y\"></td>" ) );

      $atable->add_row(
         array( 1 => '<td align=right colspan=8>' .
                '<input type="submit" name="action" value="' . T_('Update changes') . "\">",

                '', '', '', '', '', '', '' ) );

      $atable->echo_table();
      echo "<p>\n";
   }



  end_page();
}
?>

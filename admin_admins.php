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

require( "include/std_functions.php" );
require( "include/table_columns.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_ADMINS) )
    error("adminlevel_too_low");

  $admin_tasks = array( 'TRANS' => ADMIN_TRANSLATORS,
                        'FAQ' => ADMIN_FAQ,
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

        list($type, $id) = split('_', $item, 2);

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

      echo start_end_column_table(true);
      echo tablehead(1, T_('ID'), NULL, true, true);
      echo tablehead(1, T_('Handle'), NULL, false, true);
      echo tablehead(1, T_('Name'), NULL, false, true);
      echo tablehead(1, T_('Translators'), NULL, true, true);
      echo tablehead(1, T_('FAQ'), NULL, true, true);
      echo tablehead(1, T_('Admins'), NULL, true, true);
      echo tablehead(1, T_('Time'), NULL, true, true);
      echo "</tr>\n";

      $row_color=2;
      while( $row = mysql_fetch_array( $result ) )
      {
         $row_color=3-$row_color;
         $bgcolor = ${"table_row_color$row_color"};

         echo "<tr bgcolor=$bgcolor>";

         $id = $row["ID"];

         echo "<td><A href=\"userinfo.php?uid=$id\">$id</A></td>\n";
         echo "<td><A href=\"userinfo.php?uid=$id\">" . $row["Handle"] . "</td>\n";
         echo "<td><A href=\"userinfo.php?uid=$id\">" .
            make_html_safe($row["Name"]) . "</A></td>\n";

         echo "<td align=center><input type=\"checkbox\" name=\"TRANS_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_TRANSLATORS) ? ' checked' : '') . "></td>\n";
         echo "<td align=center><input type=\"checkbox\" name=\"FAQ_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_FAQ) ? ' checked' : '') . "></td>\n";
         echo "<td align=center><input type=\"checkbox\" name=\"ADMIN_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_ADMINS) ? ' checked' : '') . "></td>\n";
         echo "<td align=center><input type=\"checkbox\" name=\"TIME_$id\" value=\"Y\"" .
            (($row["admin_level"] & ADMIN_TIME) ? ' checked' : '') . "></td>\n";

      }

      $row_color=3-$row_color;
      $bgcolor = ${"table_row_color$row_color"};
      echo "<tr bgcolor=$bgcolor><td colspan=3>" . T_('New admin') .
         ': <input type="text" name="newadmin" value="" size="16" maxlength="16">'."\n";
      echo "<td align=center><input type=\"checkbox\" name=\"TRANS_new\" value=\"Y\"></td>\n";
      echo "<td align=center><input type=\"checkbox\" name=\"FAQ_new\" value=\"Y\"></td>\n";
      echo "<td align=center><input type=\"checkbox\" name=\"ADMIN_new\" value=\"Y\"></td>\n";
      echo "<td align=center><input type=\"checkbox\" name=\"TIME_new\" value=\"Y\"></td>\n";



      echo '<tr><td align=right colspan=7>' .
         '<input type="submit" name="action" value="' . T_('Update changes') . "\">\n";
      echo "</table><p>\n";
   }



  end_page();
}
?>

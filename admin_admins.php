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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/table_columns.php" );

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_ADMINS) )
    error("adminlevel_too_low");

  $admin_tasks = array(
                        'ADMIN'  => array( ADMIN_ADMINS, T_('Admins')),
                        'AddAdm' => array( ADMIN_ADD_ADMIN, T_('New admin')),
                        'Passwd' => array( ADMIN_PASSWORD, T_('New password')),
                        'TRANS'  => array( ADMIN_TRANSLATORS, T_('Translators')),
                        'Forum'  => array( ADMIN_FORUM, T_('Forum')),
                        'FAQ'    => array( ADMIN_FAQ, T_('FAQ')),
                        'Dbase'  => array( ADMIN_DATABASE, T_('Database')),
                        'TIME'   => array( ADMIN_TIME, T_('Time')),
                      );

// Make sure all previous admins gets into the Admin array
  $result = mysql_query("SELECT ID, Adminlevel+0 AS admin_level FROM Players " .
                        "WHERE Adminlevel != 0")
     or error('mysql_query_failed','admin_admins.find_admins');

  while( $row = mysql_fetch_array($result) )
  {
     $Admin[$row['ID']] = ~$player_level & (int)$row['admin_level'] ;
     $AdminOldLevel[$row['ID']] = (int)$row['admin_level'];
  }


  if( @$_GET['update'] == 't' && @$_POST['action'] )
  {
     $Admin['new'] = 0;
     foreach( $_POST as $item => $value )
     {
        if( $value != 'Y' )
           continue;

        list($type, $id) = explode('_', $item, 2);

        $val = $player_level & (int)$admin_tasks[$type][0];

        if( !($id > 0 or $id=='new') or !$val)
           error("bad_data");

        $Admin[$id] |= $val;
     }

     if( !($Admin[$player_row["ID"]] & ADMIN_ADMINS) )
        error("admin_no_longer_admin_admin");

     $newadmin= get_request_arg('newadmin');
     if( $Admin['new'] != 0 and !empty($newadmin))
     {
        $result = mysql_query("SELECT ID,Adminlevel+0 AS admin_level FROM Players " .
                              "WHERE Handle='".addslashes($newadmin)."'")
           or error('mysql_query_failed','admin_admins.find_new_admin');

        if( @mysql_num_rows($result) != 1 )
           error("unknown_user");

        $row = mysql_fetch_array($result);
        if( $row["admin_level"] != 0 )
           error("new_admin_already_admin");

        $Admin[$row['ID']] = $Admin['new'];
        $AdminOldLevel[$row['ID']] = 0;
     }
     unset($Admin['new']);

     foreach( $Admin as $id => $adm_level )
     {
        $adm_level = (int)$adm_level;
        if( !($adm_level & ADMIN_ADMINS) )
           $adm_level &= ~ADMIN_ADD_ADMIN;
        $Admin[$id] = $adm_level;

        if( $adm_level != $AdminOldLevel[$id] )
        {
           admin_log( @$player_row['ID'], @$player_row['Handle'], 
                sprintf("grants %s from 0x%x to 0x%x.", (string)$id, $AdminOldLevel[$id], $adm_level) );

           mysql_query("UPDATE Players SET Adminlevel=$adm_level WHERE ID=$id LIMIT 1")
              or error('mysql_query_failed','admin_admins.update_admin');
        }
     }

     $player_level = (int)$Admin[$player_row["ID"]];
  }

  $result = mysql_query("SELECT ID, Handle, Name, Adminlevel+0 AS admin_level FROM Players " .
                        "WHERE Adminlevel != 0")
     or error('mysql_query_failed','admin_admins.find_admins2');

  start_page(T_("Admin").' - '.T_('Edit admin staff'), true, $logged_in, $player_row );


   echo "<center>&nbsp;<p></p><h3><font color=$h3_color><B>" . T_('Admins') . ":</B></font></h3>\n";

   echo '<form name="admform" action="admin_admins.php?update=t" method="POST">'."\n";

   $atable = new Table( 'admin', '');

   $atable->add_tablehead(1, T_('ID'), NULL, true, true);
   $atable->add_tablehead(2, T_('Userid'), NULL, true, true);
   $atable->add_tablehead(3, T_('Name'), NULL, true, true);

   $col = 4;
   foreach( $admin_tasks as $aid => $tmp )
   {
      list( $amask, $aname) = $tmp;
      $atable->add_tablehead($col++, $aname, NULL, true, true, '10pc');
   }

   $new_admin = ($player_level & ADMIN_ADD_ADMIN);
   while( $row = mysql_fetch_assoc( $result ) or $new_admin )
   {
      $arow_strings = array();

      if( is_array($row) )
      {
         $id = $row["ID"];
         $level = $row["admin_level"];
         $arow_strings[1] = "<td><A href=\"userinfo.php?uid=$id\">$id</A></td>";
         $arow_strings[2] = "<td><A href=\"userinfo.php?uid=$id\">" . $row["Handle"] . "</A></td>";
         $arow_strings[3] = "<td><A href=\"userinfo.php?uid=$id\">" .
            make_html_safe($row["Name"]) . "</A></td>";
      }
      else
      {
         $new_admin = false;
         $id = 'new';
         $level = 0;
         $arow_strings[1] = "<td colspan=3 nowrap>" . T_('New admin') . ": " .
             '<input type="text" name="newadmin"' .
             ' value="" size="16" maxlength="16"></td>';
         $arow_strings[2] = "";
         $arow_strings[3] = "";
      }

      $col = 4;
      foreach( $admin_tasks as $aid => $tmp )
      {
         list( $amask, $aname) = $tmp;

         if( $amask & $player_level )
            $tmp = '';
         else
            $tmp = ' disabled';

         if( $amask & $level )
            $tmp.= ' checked';

         $tmp = "\n  <input type=\"checkbox\" name=\"${aid}_$id\" value=\"Y\"$tmp>";

         $arow_strings[$col++] = "<td align=center>$tmp</td>";
      }

      $atable->add_row( $arow_strings );
   }

   $atable->add_row(
      array( 1 => '<td align=right colspan=99>' .
             '<input type="submit" name="action" value="' . T_('Update changes') . "\">"
           , 'BG_Color' => $bg_color ) );

   $atable->echo_table();

   echo "\n</form>";

   echo "</center>\n";

  end_page();
}
?>

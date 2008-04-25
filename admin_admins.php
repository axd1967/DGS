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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/table_columns.php" );

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)@$player_row['admin_level']; //local modifications
  if( !($player_level & ADMIN_SUPERADMIN) )
    error("adminlevel_too_low");

  $admin_tasks = array(
                        'AddAdm' => array( ADMIN_ADD_ADMIN, T_('New admin')),
                        'ADMIN'  => array( ADMIN_SUPERADMIN, T_('Admins')),
                        'Passwd' => array( ADMIN_PASSWORD, T_('Password')),
                        'TRANS'  => array( ADMIN_TRANSLATORS, T_('Translators')),
                        'Forum'  => array( ADMIN_FORUM, T_('Forum')),
                        'FAQ'    => array( ADMIN_FAQ, T_('FAQ')),
                        'Skin'   => array( ADMIN_SKINNER, T_('Skin')),
                        'Devel'  => array( ADMIN_DEVELOPER, T_('Developer')),
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
   mysql_free_result($result);


  if( @$_GET['update'] == 't' && @$_POST['action'] )
  {
     $Admin['new'] = 0;
     foreach( $_POST as $item => $value )
     {
        if( $value != 'Y' )
           continue;

        list($type, $id) = explode('_', $item, 2);

        $val = $player_level & (int)$admin_tasks[$type][0];

        if( !($id > 0 || $id=='new') || !$val)
           error('bad_data');

        $Admin[$id] |= $val;
     }

     if( !($Admin[$player_row["ID"]] & ADMIN_SUPERADMIN) )
        error("admin_no_longer_admin_admin");

     $newadmin= get_request_arg('newadmin');
     if( $Admin['new'] != 0 && !empty($newadmin))
     {
        $result = mysql_query("SELECT ID,Adminlevel+0 AS admin_level FROM Players " .
                              "WHERE Handle='".mysql_addslashes($newadmin)."'")
           or error('mysql_query_failed','admin_admins.find_new_admin');

        if( @mysql_num_rows($result) != 1 )
           error("unknown_user");

        $row = mysql_fetch_array($result);
        mysql_free_result($result);
        if( $row["admin_level"] != 0 )
           error("new_admin_already_admin");

        $Admin[$row['ID']] = $Admin['new'];
        $AdminOldLevel[$row['ID']] = 0;
     }
     unset($Admin['new']);

     foreach( $Admin as $id => $adm_level )
     {
        $adm_level = (int)$adm_level;
        //remove the capacity to add an admin if not already an ADMIN_SUPERADMIN
        if( !($adm_level & ADMIN_SUPERADMIN) )
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
                        "WHERE Adminlevel != 0 ORDER BY ID")
     or error('mysql_query_failed','admin_admins.find_admins2');


   $atable = new Table( 'admin', '');


   start_page(T_("Admin").' - '.T_('Edit admin staff'), true, $logged_in, $player_row );

   echo "<h3 class=Header>" . T_('Admins') . "</h3>\n";


   $marked_form = new Form('admin','admin_admins.php?update=t', FORM_POST, true, 'FormTable');
   $marked_form->attach_table( $atable);
   $marked_form->set_tabindex(1);

   // add_tablehead($nr, $descr, $sort='', $desc_def=0, $undeletable=0, $attbs=null)
   $atable->add_tablehead(1, T_('##header'), '', 0, 1, 'ID');
   $atable->add_tablehead(2, T_('Userid#header'), '', 0, 1, 'User');
   $atable->add_tablehead(3, T_('Name#header'), '', 0, 1, 'User');

   $col = 4;
   foreach( $admin_tasks as $aid => $tmp )
   {
      list( $amask, $aname) = $tmp;
      $atable->add_tablehead($col++, $aname, '', 0, 1, 'Mark');
   }

   //can't add an admin if had not the privlege
   $new_admin = ($player_level & ADMIN_ADD_ADMIN);
   while( ($row=mysql_fetch_assoc( $result )) or $new_admin )
   {
      $arow_strings = array();

      $col = 3;
      if( is_array($row) )
      {
         $id = $row["ID"];
         $level = $row['admin_level'];
         $arow_strings[1]= "<A href=\"userinfo.php?uid=$id\">$id</A>";
         $arow_strings[2]= "<A href=\"userinfo.php?uid=$id\">" . $row["Handle"] . "</A>";
         $arow_strings[3]= "<A href=\"userinfo.php?uid=$id\">" .
            make_html_safe($row["Name"]) . "</A>";
      }
      else
      {
         $new_admin = false;
         $id = 'new';
         $arow_strings[1]= array(
            'attbs' => array( 'colspan' => $col, 'class'=>'nowrap'),
            'text' => T_('New admin') . ": "
               . '<input type="text" name="newadmin"'
               . ' value="" size="16" maxlength="16">',
            );
         $level = 0;
      }

      foreach( $admin_tasks as $aid => $tmp )
      {
         list( $amask, $aname) = $tmp;

         if( $amask & $player_level )
            $tmp = '';
         else
            $tmp = ' disabled';

         //only ADMIN_SUPERADMIN can grant ADMIN_ADD_ADMIN
         if( $amask == ADMIN_ADD_ADMIN
            && !($player_level & ADMIN_SUPERADMIN) )
            $tmp = ' disabled';

         if( $amask & $level )
            $tmp.= ' checked';

         $tmp = "\n  <input type=\"checkbox\" name=\"${aid}_$id\" value=\"Y\"$tmp>";

         $arow_strings[++$col]= $tmp;
      }

      $atable->add_row( $arow_strings );
   }
   mysql_free_result($result);

   $atable->echo_table();

   echo $marked_form->print_insert_submit_buttonx( 'action',
            T_('Update changes'), array('accesskey'=>'x'));

   echo $marked_form->print_end();

   end_page();
}
?>

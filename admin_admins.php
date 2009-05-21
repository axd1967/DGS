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

// translations removed for this page: $TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/table_columns.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   // only SUPERADMIN can manage admins
   $player_level = (int)@$player_row['admin_level']; //local modifications
   if( !($player_level & ADMIN_SUPERADMIN) )
      error('adminlevel_too_low');

   $admin_tasks = array( // admin-level-id => arr( admin-bitmask, admin-text )
         'ADMIN'  => array( ADMIN_SUPERADMIN, /*T_*/('Admins')),
         'Passwd' => array( ADMIN_PASSWORD, /*T_*/('Password')),
         'TRANS'  => array( ADMIN_TRANSLATORS, /*T_*/('Translators')),
         'Forum'  => array( ADMIN_FORUM, /*T_*/('Forum')),
         'FAQ'    => array( ADMIN_FAQ, /*T_*/('FAQ')),
         'Skin'   => array( ADMIN_SKINNER, /*T_*/('Skin')),
         'TRNEY'  => array( ADMIN_TOURNAMENT, /*T_*/('Tournament')),
         'Vote'   => array( ADMIN_VOTE, /*T_*/('Voting')),
         'Devel'  => array( ADMIN_DEVELOPER, /*T_*/('Developer')),
         'Dbase'  => array( ADMIN_DATABASE, /*T_*/('Database')),
      );

   // Make sure all previous admins gets into the Admin array
   $result = db_query( 'admin_admins.find_admins',
         "SELECT ID, Adminlevel+0 AS admin_level FROM Players WHERE Adminlevel > 0" );
   while( $row = mysql_fetch_array($result) )
   {
      $uid = $row['ID'];
      $AdminOldLevel[$uid] = (int)$row['admin_level'];
      $Admin[$uid] = 0;
   }
   mysql_free_result($result);

   // SUPERADMIN can't remove SUPERADMIN-flag for himself
   $Admin[$my_id] |= ADMIN_SUPERADMIN;


/* Actual POST/REQUEST calls used:
     (no args) | refresh=                     : show admin-users and admin-levels
     update=&<aid>_<uid>=''|Y                 : update admins with changed admin-levels
     update=&...&newadmin=handle&<aid>_new=.. : add new admin with uid='new'
*/

   if( @$_REQUEST['update'] )
   {
      // update admin-levels? -> Admin[uid] = new admin-levels
      $Admin['new'] = 0;
      foreach( $_POST as $item => $value )
      {
         if( $value != 'Y' )
            continue;

         // uid = Players.ID | 'new'
         list($type, $uid) = explode('_', $item, 2);
         $amask = (int)@$admin_tasks[$type][0];
         if( $amask == 0 || ($uid !== 'new' && $uid <= GUESTS_ID_MAX) )
            error('bad_data', "admin_admins.update($uid,$type,$amask)");

         $Admin[$uid] |= $amask;
      }

      // add new admin?
      $newadmin= get_request_arg('newadmin');
      if( $Admin['new'] != 0 && !empty($newadmin))
      {
         $row = mysql_fetch_row( "admin_admins.find_new_admin($newadmin)",
               "SELECT ID,Adminlevel+0 AS admin_level FROM Players "
               . "WHERE Handle='".mysql_addslashes($newadmin)."' LIMIT 1" );
         if( !$row )
            error('unknown_user', "admin_admin.check.user($newadmin)");
         if( $row["admin_level"] != 0 )
            error('new_admin_already_admin');

         $uid = $row['ID'];
         $AdminOldLevel[$uid] = 0;
         $Admin[$uid] = $Admin['new'];
      }
      unset($Admin['new']);

      // update admin-levels
      foreach( $Admin as $uid => $adm_level )
      {
         $adm_level = (int)$adm_level;
         if( $adm_level != $AdminOldLevel[$uid] )
         {
            admin_log( $my_id, @$player_row['Handle'],
               sprintf("grants %s from 0x%x to 0x%x.", (string)$uid, $AdminOldLevel[$uid], $adm_level) );

            db_query( "admin_admins.update_admin($my_id,$uid,$adm_level)",
               "UPDATE Players SET Adminlevel=$adm_level WHERE ID=$uid LIMIT 1" );
         }
      }

      $player_level = (int)$Admin[$my_id];
   } //update


   // reload admin-users with Handle,Name and changed admin-levels
   $result = db_query( 'admin_admins.find_admins2',
         "SELECT ID, Handle, Name, Adminlevel+0 AS admin_level FROM Players " .
         "WHERE Adminlevel > 0 ORDER BY ID" );

   $atable = new Table( 'admin', '', '', '', TABLE_NO_SIZE );


   start_page(/*T_*/("Admin").' - './*T_*/('Edit admin staff'), true, $logged_in, $player_row );
   echo "<h3 class=Header>" . /*T_*/('Admins') . "</h3>\n";


   $marked_form = new Form('admin','admin_admins.php', FORM_POST, true, 'FormTable');
   $marked_form->attach_table( $atable);
   $marked_form->set_tabindex(1);

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $atable->add_tablehead(1, /*T_*/('ID'), 'ID');
   $atable->add_tablehead(2, /*T_*/('Userid'), 'User');
   $atable->add_tablehead(3, /*T_*/('Name'), 'User');

   $col = 4;
   foreach( $admin_tasks as $aid => $tmp )
   {
      list( $amask, $aname) = $tmp;
      $atable->add_tablehead($col++, $aname, 'Mark');
   }
   $atable->set_default_sort( 1); //on ID

   $new_admin = true;
   while( ($row=mysql_fetch_assoc( $result )) || $new_admin )
   {
      $arow_strings = array();

      $col = 3;
      if( is_array($row) )
      {
         $uid = $row['ID'];
         $level = $row['admin_level'];
         $arow_strings[1]= "<A href=\"userinfo.php?uid=$uid\">$uid</A>";
         $arow_strings[2]= "<A href=\"userinfo.php?uid=$uid\">" . $row["Handle"] . "</A>";
         $arow_strings[3]= "<A href=\"userinfo.php?uid=$uid\">" .
               make_html_safe($row["Name"]) . "</A>";
      }
      else
      {
         $new_admin = false; // last-line built
         $uid = 'new';
         $arow_strings[1]= array(
            'attbs' => array( 'colspan' => $col, 'class'=>'nowrap'),
            'text' => /*T_*/('New admin') . ": "
               . '<input type="text" name="newadmin" value="" size="16" maxlength="16">',
            );
         $level = 0;
      }

      foreach( $admin_tasks as $aid => $tmp )
      {
         list( $amask, $aname) = $tmp;

         $attr = '';
         // SUPERADMIN can't change SUPERADMIN-flag for himself
         if( ($amask & $level & ADMIN_SUPERADMIN) && ($uid == $my_id) )
            $attr .= ' disabled';
         if( $amask & $level )
            $attr .= ' checked';

         $chkname = $aid . '_' . $uid;
         $arow_strings[++$col] =
            "\n  <input type=\"checkbox\" name=\"{$chkname}\" value=\"Y\"$attr>";
      }

      $atable->add_row( $arow_strings );
   }
   mysql_free_result($result);

   $atable->echo_table();

   echo $marked_form->print_insert_submit_buttonx( 'update',
            /*T_*/('Update changes'), array( 'accesskey' => ACCKEY_ACT_EXECUTE )),
        $marked_form->print_insert_submit_button( 'refresh', /*T_*/('Refresh')),
        $marked_form->print_end();

   end_page();
}
?>

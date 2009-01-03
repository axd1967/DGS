<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/**
 * PURPOSE:
 * Show admins (Adminlevel>0) and administrated users (AdminOptions>0),
 * needs ADMIN_DEVELOPER/PASSWORD/FORUM or /FAQ rights
 */

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low');
   $show_ip = ( @$player_row['admin_level'] & ADMIN_DEVELOPER ); // only for adm-dev!

   // init
   $page = 'admin_show_adminlog.php';

   start_page(T_('Show admin log'), true, $logged_in, $player_row);

   section( 'adminlog', T_('Admin log') );

   $atable = new Table( 'adminlog', $page, '' );
   $limit = $atable->current_limit_string();

   // add_tablehead($nr, $descr, $sort=NULL, $desc_def=false, $undeletable=false, $attbs=NULL)
   $atable->add_tablehead( 1, T_('ID#header'));
   $atable->add_tablehead( 2, T_('Admin#header'));
   $atable->add_tablehead( 3, T_('User#header'));
   $atable->add_tablehead( 4, T_('Time#header'));
   $atable->add_tablehead( 5, T_('Message#header'));
   if( $show_ip )
      $atable->add_tablehead( 6, T_('IP#header'));

   $result = mysql_query(
         'SELECT AL.*, ' .
            'IFNULL(UNIX_TIMESTAMP(AL.Date),0) AS X_Date, ' .
            'PAdm.Handle AS PAdm_Handle, ' .
            'PUser.ID AS PUser_ID, PUser.Name AS PUser_Name, PUser.Handle AS PUser_Handle ' .
         'FROM Adminlog AS AL ' .
            'LEFT JOIN Players AS PAdm ON PAdm.ID=AL.uid ' .
            'LEFT JOIN Players AS PUser ON PUser.Handle=AL.Handle '.
         'ORDER BY ID DESC ' . $limit )
      or error('mysql_query_failed', 'admin_show_adminlog.find_data');

   $show_rows = $atable->compute_show_rows(mysql_num_rows($result));

   while( $show_rows-- > 0 && ($row = mysql_fetch_assoc( $result )) )
   {
      $arow_str = array();
      if( $atable->Is_Column_Displayed[1] )
         $arow_str[1] = '<td>' . @$row['ID'] . '</td>';
      if( $atable->Is_Column_Displayed[2] )
      {
         $userstr = ((string)@$row['PAdm_Handle'] != '') ? @$row['PAdm_Handle'] : '';
         $arow_str[2] = '<td>' . $userstr . '</td>';
      }
      if( $atable->Is_Column_Displayed[3] )
         $arow_str[3] = '<td>' . user_reference( REF_LINK, 1, '',
            $row['PUser_ID'], $row['PUser_Name'], $row['PUser_Handle'] ) . '</td>';
      if( $atable->Is_Column_Displayed[4] )
      {
         $datestr = ($row['X_Date'] > 0 ? date($date_fmt2, $row['X_Date']) : NULL );
         $arow_str[4] = '<td>' . $datestr . '&nbsp;</td>';
      }
      if( $atable->Is_Column_Displayed[5] )
         $arow_str[5] = '<td>' . wordwrap(@$row['Message'], 60, "<br>\n", false) . '&nbsp;</td>';
      if( $show_ip && $atable->Is_Column_Displayed[6] )
         $arow_str[6] = '<td>' . @$row['IP'] . '&nbsp;</td>';
      $atable->add_row( $arow_str );
   }
   mysql_free_result($result);

   $atable->echo_table();

   echo "<br>\n",
      "NOTE: Log shows user logins and all changes by admins on user fields ",
      "(e.g. password, admin-level, admin-option).<br>\n";

   end_page();
}

?>

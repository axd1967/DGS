<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
 * needs ADMINGROUP_EXECUTIVE rights
 */

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMINGROUP_EXECUTIVE) )
      error('adminlevel_too_low');
   $edit_user = (@$player_row['admin_level'] & ADMIN_DEVELOPER);

   // init
   $page = 'admin_show_users.php';

   $ARR_ADMLEVELS = array( // maskval => [ bit-text, descr ]
      ADMIN_SUPERADMIN     => array( 'SUPERADMIN',       T_('Can manage admins (add, edit, delete)') ),
      ADMIN_DATABASE       => array( 'DATABASE',         T_('Can execute special server scripts') ),
      ADMIN_DEVELOPER      => array( 'DEVELOPER',        T_('Can see more admin info, manage forums, edit user attributes') ),
      ADMIN_PASSWORD       => array( 'PASSWORD',         T_('Can create and send new passwords') ),
      ADMIN_TOURNAMENT     => array( 'TOURNAMENT',       T_('Can administrate tournaments') ),
      ADMIN_FORUM          => array( 'MODERATOR',        T_('Can moderate forum (approve, reject, show, hide posts)') ),
      ADMIN_FAQ            => array( 'FAQ_EDITOR',       T_('Can edit FAQ') ),
      ADMIN_VOTE           => array( 'VOTE',             T_('Can administrate voting') ),
      ADMIN_SKINNER        => array( 'SKINNER',          T_('Can choose CSS-skin (experimental)') ),
      ADMIN_TRANSLATORS    => array( 'TRANSLATOR',       T_('Can translate texts') ),
   );

   $ARR_ADMOPTS = array( // maskval => [ bit-text, descr ]
      //ADMOPT_BYPASS_IP_BLOCK  => array( 'BYPASS_IP_BLOCK', T_('Bypass IP-Block to allow login for accidentally blocked user') ),
      ADMOPT_DENY_LOGIN             => array( 'DENY_LOGIN',    T_('Deny login (user can not use site)') ),
      ADMOPT_DENY_EDIT_BIO          => array( 'DENY_EDIT_BIO', T_('Deny edit bio and user picture (user can not edit bio or user picture)') ),
      ADMOPT_DENY_VOTE              => array( 'DENY_VOTE',     T_('Deny voting (user can not vote on features)') ),
      ADMOPT_DENY_TOURNEY_CREATE    => array( 'DENY_TNEY_CREATE', T_('Deny create tournament (user can not create new tournaments)') ),
      ADMOPT_DENY_TOURNEY_REGISTER  => array( 'DENY_TNEY_REG', T_('Deny tournament registration (user can not register to any new tournament)') ),
      ADMOPT_HIDE_BIO               => array( 'HIDE_BIO',      T_('Hide bio and picture (users bio and picture is hidden)') ),
      ADMOPT_SHOW_TIME              => array( 'SHOW_TIME',     T_('Show "time needed" for page-requests (in bottom bar)') ),
      ADMOPT_FGROUP_ADMIN           => array( 'FGR_ADMIN',     T_('View ADMIN-forums (which are normally hidden)') ),
      ADMOPT_FGROUP_DEV             => array( 'FGR_DEV',       T_('View DEV-forums (which are normally hidden)') ),
   );

   // fields to load
   $query_fields = 'ID,Handle,Name, Adminlevel,AdminOptions,AdminNote, ' .
      'UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess';

   start_page(T_('Show user admin'), true, $logged_in, $player_row);

   //---------------
   section( 'adminuser', T_('Admin users') );
   $table = create_table( $edit_user, $page, true, 'admin_show_users.find_admins',
      "SELECT $query_fields FROM Players WHERE Adminlevel<>0 ORDER BY ID" );
   $table->echo_table();

   //---------------
   section( 'adminuser', T_('Administrated users') );
   $table = create_table( $edit_user, $page, false, 'admin_show_users.find_administrated',
      "SELECT $query_fields FROM Players WHERE Adminlevel=0 AND AdminOptions>0 ORDER BY ID" );
   $table->echo_table();

   //--------------- Legends
   $aform = new Form( 'adminuser', $page, FORM_GET, false);
   $aform->set_layout( FLAYOUT_GLOBAL, '1,2,3' );

   $aform->set_area(1);
   $aform->add_row( array( 'SPACE' ));
   $aform->add_row( array(
      'CELL', 2, '',
      'SUBMITBUTTON', 'refresh', T_('Refresh') ));
   $aform->add_row( array( 'SPACE' ));

   $aform->set_area(2);
   $aform->set_layout( FLAYOUT_AREACONF, 2, array( 'title' => T_('Legend for admin levels') ));
   foreach( $ARR_ADMLEVELS as $maskval => $arr )
   {
      $aform->add_row( array(
         'TEXT', sprintf( '<span class="LegendItem">%s</span>', $arr[0]), 'TAB',
         'TEXT', sprintf( '<span class="LegendDescr">%s</span>', $arr[1]) ));
   }
   $aform->add_row( array( 'SPACE' ));

   $aform->set_area(3);
   $aform->set_layout( FLAYOUT_AREACONF, 3, array( 'title' => T_('Legend for admin options') ));
   foreach( $ARR_ADMOPTS as $maskval => $arr )
   {
      $aform->add_row( array(
         'TEXT', sprintf( '<span class="LegendItem">%s</span>', $arr[0]), 'TAB',
         'TEXT', sprintf( '<span class="LegendDescr">%s</span>', $arr[1]) ));
   }

   $aform->echo_string();

   $menu_array = array();
   if( $edit_user )
      $menu_array[T_('Edit user attributes')] = 'admin_users.php';

   end_page(@$menu_array);
}

/* \brief Creates table for specified query containing admin/administrated users. */
function create_table( $show_edit_user, $page, $with_adminlevel, $query_msg, $query )
{
   global $ARR_ADMLEVELS, $ARR_ADMOPTS;

   $atable = new Table( 'admins', $page, '', '', TABLE_NO_SIZE );
   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $atable->add_tablehead( 1, T_('User#header'), 'User');
   $atable->add_tablehead( 2, T_('Last access#header'), 'Date');
   if( $with_adminlevel )
      $atable->add_tablehead( 3, T_('Admin level#header'));
   $atable->add_tablehead( 4, T_('Admin options#header'));
   $atable->add_tablehead( 5, T_('Admin note#header'));

   $result = db_query( $query_msg, $query );

   $edit_link = '';
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $arow_str = array();
      if( $atable->Is_Column_Displayed[1] )
      {
         if( $show_edit_user )
         {
            $edit_link = anchor( 'admin_users.php?show_user=1'.URI_AMP.'user='.urlencode(@$row['Handle']),
               image( 'images/edit.gif', 'E'),
               T_('Edit user attributes'), 'class=ButIcon') . '&nbsp;';
         }
         $arow_str[1] = $edit_link . user_reference( REF_LINK, 1, '', $row );
      }
      if( $atable->Is_Column_Displayed[2] )
         $arow_str[2] = ($row['X_Lastaccess'] > 0 ? date(DATE_FMT2, $row['X_Lastaccess']) : NULL );
      if( $with_adminlevel && $atable->Is_Column_Displayed[3] )
         $arow_str[3] = '<span class="Flags">'
            . build_admin_flags( $ARR_ADMLEVELS, @$row['Adminlevel']+0 ) . '</span>';
      if( $atable->Is_Column_Displayed[4] )
         $arow_str[4] = '<span class="Flags">'
            . build_admin_flags( $ARR_ADMOPTS, @$row['AdminOptions']+0 ) . '</span>';
      if( $atable->Is_Column_Displayed[5] )
         $arow_str[5] = @$row['AdminNote'];
      $atable->add_row( $arow_str );
   }
   mysql_free_result($result);

   return $atable;
}

/*
 * \brief Builds list of textual values for bit-based value
 * param arr_desc expected array: bitmask => [ bit-text, ... ]
 * param value current value with set bits
 */
function build_admin_flags( $arr_desc, $value )
{
   $maxflags = 2; // max flags per column
   $arrout = array();
   $cnt = 0;
   foreach( $arr_desc as $maskval => $arr )
   {
      if( $value & $maskval )
         $arrout[] = $arr[0] . ((++$cnt % $maxflags) == 0 ? ',<br>' : ', ');
   }
   return (count($arrout) > 0)
      ? preg_replace( "/,(<br>|\s+)$/", '', implode('', $arrout) )
      : '---';
}

?>

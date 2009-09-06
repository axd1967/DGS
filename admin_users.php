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

/* PURPOSE: Edit user attributes except admin-related fields, needs ADMIN_DEVELOPER rights */

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low', 'admin_users');

/* URL-syntax for this page:
      cancel                : jump to show-user-form
      show_user=1&user=handle : shows data of user
      refresh               : reloads current user (same as show_user=1&user=..)
      save_user=1&uid=ID&f_<field>=val... : updates Players.<field>=val for Players.ID=uid
*/

   // init
   $page = 'admin_users.php';
   if( @$_REQUEST['cancel'] )
      jump_to($page);

   $user = trim(get_request_arg('user'));
   $uid  = (int) get_request_arg('uid', 0);

   if( @$_REQUEST['refresh'] )
      jump_to($page.'?show_user=1'.URI_AMP.'user='.urlencode($user));

   // note: '*' marks field that may have changed while editing (because of cron-jobs)
   $attributes_edit = array( // fieldnames => type[*][:width,maxlen]|(regex)
      'Type'            => 'mask',
      'Handle'          => 'text',
      'Name'            => 'text',
      'VaultCnt'        => 'int*',
      'VaultTime'       => 'time*',
      'VacationDays'    => 'int*',
      'OnVacation'      => 'int*',
      'AdminOptions'    => 'mask',
      'MayPostOnForum'  => array( 'Y' => 'Y = can post',
                                  'N' => 'N = can NOT post',
                                  'M' => 'M = post moderated' ),
      'AdminNote'       => 'text:60,100',
      'BlockReason'     => 'textarea:60,6',
   );

   $attributes_show = array( // fieldname, ...
      'Registerdate', 'Lastaccess', 'LastMove', 'Activity',
      'Lang',
      'Email', 'IP', 'Browser',
      'Sessionexpire',
      'SendEmail', 'Notify',
   );
   $user_fields =
        implode(',', array_keys($attributes_edit)) . ','
      . implode(',', $attributes_show);

   $arr_mask_type = array( // bitmask for Type: maskval => [ fieldname, opt-text, descr ]
      USERTYPE_PRO      => array( 'fl_utype1', 'PRO',       T_('Professional') ),
      USERTYPE_TEACHER  => array( 'fl_utype2', 'TEACHER',   T_('Teacher') ),
      USERTYPE_ROBOT    => array( 'fl_utype3', 'ROBOT',     T_('Robot') ),
      //USERTYPE_TEAM     => array( 'fl_utype4', 'TEAM',      T_('Team') ),
   );
   $arr_mask_admopts = array( // bitmask for AdminOptions: maskval => [ fieldname, opt-text, descr ]
      //ADMOPT_BYPASS_IP_BLOCK  => array( 'fl_admopt1', 'BYPASS_IP_BLOCK', T_('Bypass IP-Block') ),
      ADMOPT_DENY_LOGIN       => array( 'fl_admopt2', 'DENY_LOGIN',     T_('Deny login') ),
      ADMOPT_DENY_EDIT_BIO    => array( 'fl_admopt3', 'DENY_EDIT_BIO',  T_('Deny edit bio and user picture') ),
      ADMOPT_DENY_VOTE        => array( 'fl_admopt4', 'DENY_VOTE',      T_('Deny vote') ),
      ADMOPT_HIDE_BIO         => array( 'fl_admopt5', 'HIDE_BIO',       T_('Hide bio and user picture') ),
      ADMOPT_SHOW_TIME        => array( 'fl_admopt6', 'SHOW_TIME',      T_('Show time') ),
      ADMOPT_FGROUP_ADMIN     => array( 'fl_admopt7', 'FGR_ADMIN',      T_('View ADMIN-forums') ),
      ADMOPT_FGROUP_DEV       => array( 'fl_admopt8', 'FGR_DEV',        T_('View DEV-forums') ),
   );

   // set field-values to change
   $fvalues = array();
   foreach( $attributes_edit as $field => $type )
      $fvalues[$field] = ( $uid ) ? trim(get_request_arg("f_$field", '')) : '';

   $bitmask = 0;
   foreach( $arr_mask_type as $maskval => $arr )
   {
      $flagname = $arr[0];
      $fvalues[$flagname] = ( $uid ) ? trim(get_request_arg($flagname, '')) : '';
      if( $fvalues[$flagname] )
         $bitmask |= $maskval;
   }
   $fvalues['Type'] = ( $uid ) ? $bitmask : '';

   $bitmask = 0;
   foreach( $arr_mask_admopts as $maskval => $arr )
   {
      $flagname = $arr[0];
      $fvalues[$flagname] = ( $uid ) ? trim(get_request_arg($flagname, '')) : '';
      if( $fvalues[$flagname] )
         $bitmask |= $maskval;
   }
   $fvalues['AdminOptions'] = ( $uid ) ? $bitmask : '';

   // update user
   $errmsg = '';
   if( @$_REQUEST['save_user'] )
   {
      $errmsg = update_user( $uid, $user, $fvalues );
      if( $errmsg === 0 )
      {
         $msg = sprintf('User [%s] updated!', $user);
         // show updated user
         jump_to($page.'?user='.urlencode($user) . URI_AMP."sysmsg=".urlencode($msg));
      }
   }

   // load user
   $urow = null; // row-arr=on user-found, false=if no user-found, null=no-user-specified
   if( (string)$user != '' )
   {
      $urow = mysql_single_fetch( 'admin_users.find_user',
         "SELECT ID,$user_fields FROM Players WHERE Handle='".mysql_addslashes($user)."' LIMIT 1" );
   }


   start_page(T_('Edit user admin'), true, $logged_in, $player_row);

   $uform = new Form( 'adminuserform', 'admin_users.php', FORM_GET );
   $uform->add_row( array(
      'HEADER', T_('Modify user attributes') ));

   if( (@$_REQUEST['show_user'] || (string)$user != '' ) && is_array($urow) )
   {
      $uform->add_row( array(
         'HIDDEN', 'user', $user,
         'HIDDEN', 'uid',  @$urow['ID'],
         'DESCRIPTION', 'NOTE',
         'TEXT',        'Update overwrites attributes(*) that may have changed while editing!' ));
      $uform->add_row( array( 'SPACE' ));
   }
   else
   { // show-user-form or user not found
      $uform->add_row( array(
         'DESCRIPTION',    'User id',
         'TEXTINPUT',      'user', 20, -1, $user,
         'SUBMITBUTTON',   'show_user', 'Show user' ));
      $uform->add_row( array( 'SPACE' ));
   }

   if( $urow === false )
   { // unknown user
      $uform->add_row( array( 'TAB', 'TEXT',
         make_html_safe( '<color darkred>'
            . sprintf('Found no user with handle [%s]', $user)
            . '</color>', true ) ));
   }
   elseif( is_array($urow) )
   { // show known user
      // show error-message from (optional) update
      if( $errmsg && !is_numeric($errmsg) )
      {
         $uform->add_row( array(
            'TAB', 'TEXT', make_html_safe(
               "<color darkred><u>Error:</u> $errmsg </color>", true ) ));
         $uform->add_row( array( 'SPACE' ));
      }

      $uform->add_row( array(
         'DESCRIPTION', 'ID',
         'TEXT',        $urow['ID'] . ' = ' . user_reference( REF_LINK, 1, '', $urow ) ));

      // read-write fields
      foreach( $attributes_edit as $field => $type )
      {
         $fname = "f_$field";
         $fval = ( (string)$fvalues[$field] != '' ) ? $fvalues[$field] : @$urow[$field];

         if( is_array($type) )
         { // selectbox
            $uform->add_row( array(
               'DESCRIPTION', $field,
               'SELECTBOX',   $fname, 1, $type, $fval, false,
               'TEXT',        sprintf(' [%s]', @$urow[$field]) ));
         }
         elseif( $type === 'mask' && ($field === 'Type' || $field === 'AdminOptions') )
         { // bitmask -> checkboxes
            if( $field === 'Type' )
               $arr_src = $arr_mask_type;
            elseif( $field === 'AdminOptions' )
               $arr_src = $arr_mask_admopts;

            $fval = ( (string)$fvalues[$field] != '' ) ? $fvalues[$field] : @$urow[$field];
            $arr_formrow = array( 'DESCRIPTION', $field, 'TEXT', sprintf('[0x%x]', $fval+0) );
            foreach( $arr_src as $maskval => $arr )
            {
               list( $flagname, $opttext, $descr) = $arr;
               array_push( $arr_formrow,
                  'TEXT',     '<BR>',
                  'CHECKBOX', $flagname, 1, $descr, ($fval & $maskval) ? 1 : 0,
                  'TEXT',     sprintf(' [%s]', (@$urow[$field] & $maskval) ? 1 : 0 ) );
            }
            $uform->add_row( $arr_formrow );
         }
         elseif( preg_match( "/^textarea:/", $type ) )
         {
            $args = array();
            $width = 60; $height = 6;
            if( preg_match( "/:(\d+),(\d+)/", $type, $args ) )
               list( $dummy, $width, $height ) = $args;

            $uform->add_row( array(
               'DESCRIPTION', $field,
               'TEXTAREA',    $fname, $width, $height, $fval,
               'TEXT',        '<br>[' . make_html_safe($fval, 'msg') . ']' ));
         }
         else
         { // text-input-box
            $fieldtext = ( strpos($type,"*") === false ) ? $field : "(*) $field";
            $args = array();
            $wid = 20; $maxlen = -1;
            if( preg_match( "/:(\d+),(-?\d+)/", $type, $args ) )
               list( $dummy, $wid, $maxlen ) = $args;

            $uform->add_row( array(
               'DESCRIPTION', $fieldtext,
               'TEXTINPUT',   $fname, $wid, $maxlen, $fval,
               'TEXT',        (($wid > 35 && strlen(@$urow[$field]) > 10) ? '<br>' : '')
                              . sprintf(' [%s]', @$urow[$field]) ));
         }
      }

      $uform->add_row( array( 'SPACE' ));
      $uform->add_row( array(
         'SUBMITBUTTON', 'save_user', 'Update user',
         'SUBMITBUTTON', 'refresh',   'Refresh',
         'SUBMITBUTTON', 'cancel',    'Cancel / New' ));
      $uform->add_row( array( 'SPACE' ));
      $uform->add_row( array( 'OWNHTML', '<td colspan="2"><hr></td>' ));

      // read-only fields
      foreach( $attributes_show as $field )
      {
         $uform->add_row( array(
            'DESCRIPTION', $field,
            'TEXT',        @$urow[$field] ));
      }
   }

   $uform->echo_string();

   $menu_array = array();
   $menu_array[T_('Show administrated users')] = 'admin_show_users.php';
   if( @$player_row['admin_level'] & ADMIN_DATABASE )
      $menu_array[T_('Check Block-IP config')] = 'scripts/check_block_ip.php';

   end_page(@$menu_array);
}

/*
 * \brief Updates specified user.
 * param uid Players.ID to update
 * param user non-empty user
 * param fv array with field-values: fieldname => value
 * \return 0=user-updated, -1=nothing to do, otherwise error-message
 */
function update_user( $uid, $user, $fv )
{
   global $user_fields;

   if( (string)$user == '' || !is_numeric($uid) || $uid <= 1 )
      return 'Missing user id to update';

   // check vars for update
   global $player_row;
   if( strlen( $fv['Handle'] ) < 3 )
      return 'Handle is too short';
   if( illegal_chars( $fv['Handle'] ) )
      return sprintf( 'Handle contains illegal chars (begin with letter, allowed [%s])', HANDLE_LEGAL_REGS );
   if( $user == $player_row['Handle'] && $user != $fv['Handle'] )
      return 'Forbidden to rename Handle of yourself! That would end your session.';

   if( (string)$fv['Name'] == '' )
      return 'Missing Name-field';

   if( !preg_match( "/^\d+$/", $fv['VaultCnt'] ) )
      return 'Bad syntax for VaultCnt-field (must be positive number)';

   if( !preg_match( "/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/", $fv['VaultTime'] ) )
      return 'Bad syntax for VaultTime-field (must be a date of format [YYYY-MM-DD hh:mm:ss])';

   if( !preg_match( "/^(\d+|\d+\.\d+)$/", $fv['VacationDays'] ) || (double)$fv['VacationDays'] >= 31.0 )
      return 'Bad syntax for VacationDays-field (must be positive real number below 31 days)';

   if( !preg_match( "/^(\d+|\d+\.\d+)$/", $fv['OnVacation'] ) || (double)$fv['OnVacation'] >= 31.0 )
      return 'Bad syntax for OnVacation-field (must be positive real number below 31 days)';

   if( !preg_match( "/^[YNM]$/", $fv['MayPostOnForum'] ) )
      return 'Bad value for MayPostOnForum-field (must be one of [Y,N,M])';

   global $arr_mask_type;
   $bitmaskall = 0;
   foreach( $arr_mask_type as $maskval => $arr )
      $bitmaskall |= $maskval;
   $chk_bitmask = (int)($fv['Type']+0) & ~$bitmaskall;
   if( $chk_bitmask != 0 )
      return sprintf( 'Unknown bit-mask value [0x%x] used for Type-field', $chk_bitmask );

   global $arr_mask_admopts;
   $bitmaskall = 0;
   foreach( $arr_mask_admopts as $maskval => $arr )
      $bitmaskall |= $maskval;
   $chk_bitmask = (int)($fv['AdminOptions']+0) & ~$bitmaskall;
   if( $chk_bitmask != 0 )
      return sprintf( 'Unknown bit-mask value [0x%x] used for AdminOptions-field', $chk_bitmask );
   $chk_arr = @$arr_mask_admopts[ADMOPT_DENY_LOGIN];
   if( is_array($chk_arr) && $fv[$chk_arr[0]] && $uid == $player_row['ID'] )
      return 'Forbidden to change admin option DENY_LOGIN for yourself! That would kick you from the server.';

   // check for user
   $row = mysql_single_fetch( "admin_users.save_user_find($user)",
      "SELECT ID,$user_fields FROM Players WHERE Handle='".mysql_addslashes($user)."' LIMIT 1" );
   if( $row === false )
      return sprintf( 'Found no user with handle [%s]', $user );

   $arrdiff = array();

   // update user in DB
   $arr_sql = array();
   global $attributes_edit;
   foreach( $attributes_edit as $field => $type )
   {
      $hasdiff = ( strcmp( @$row[$field], $fv[$field] ) != 0 );
      if( $hasdiff )
         $arr_sql[] = "$field='".mysql_addslashes($fv[$field])."'";

      // create diffs for admin-log
      if( $type === 'mask' && ($field === 'Type' || $field === 'AdminOptions') )
      {
         if( $field === 'Type' )
            $arr_src = $arr_mask_type;
         elseif( $field === 'AdminOptions' )
            $arr_src = $arr_mask_admopts;

         $optdiff = array();
         foreach( $arr_src as $maskval => $arr )
         {
            $old = (@$row[$field] & $maskval);
            $new = ($fv[$field] & $maskval);
            if( $old != $new )
               $optdiff[] = (( $old && !$new ) ? '-' : '+') . $arr[1];
         }
         if( count($optdiff) > 0 )
            $arrdiff[] = sprintf( '%s[%s]', $field, implode(' ', $optdiff) );
      }
      else {
         if( $hasdiff )
            $arrdiff[] = sprintf( '%s[%s]>[%s]', $field, @$row[$field], $fv[$field] );
      }
   }
   if( count($arr_sql) == 0 )
      return -1; // no update

   db_query( "admin_users.save_user($user)",
      'UPDATE Players SET ' . implode(', ', $arr_sql)
         . " WHERE ID='".mysql_addslashes($uid)."'"
         . " AND Handle='".mysql_addslashes($user)."' LIMIT 1" ); // double(user+uid) for safety

   admin_log( @$player_row['ID'], $user, 'updated_user: ' . implode(', ', $arrdiff) );

   return 0; // no error
}

?>

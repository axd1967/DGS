<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

/* PURPOSE: Edit user contributions, needs ADMIN_DEVELOPER rights */

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'include/classlib_user.php';
require_once 'include/db/contribution.php';
require_once 'include/error_codes.php';


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin_contrib');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_contrib');
   if ( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low', 'admin_contrib');

/* URL-syntax for this page:
      uid=0                            : show list of all contributions
      uid=999                          : show list of contributions of specific user
      cancel=1&uid=X                   : jump to show all|user-specific contributions
      edit=new|777&uid=999             : edit new or existing user contribution
      edit=new|777&uid=999&preview=1   : preview new/edit user contribution
      edit=new|777&uid=999&save=1      : save new/changed user contribution
      del=777&uid=999                  : remove existing user contribution
      del=777&uid=999&confirm=1        : remove user contribution (confirmed)
*/

   // init
   $page = 'admin_contrib.php';

   $uid = (int) get_request_arg('uid', 0);
   if ( !is_numeric($uid) || $uid <= GUESTS_ID_MAX )
      $uid = 0;
   if ( @$_REQUEST['cancel'] )
      jump_to($page."?uid=$uid");

   $edit_id = get_request_arg('edit', 0); // 0=no-edit, new=new-contrib, >0=edit-existing-contrib
   $is_edit = ( (string)$edit_id == 'new' ) || ( is_numeric($edit_id) && $edit_id > 0 );
   if ( !$is_edit || (string)$edit_id == 'new' )
      $edit_id = 0;

   $del_id = (int)get_request_arg('del', 0);
   if ( $del_id < 0 )
      $del_id = 0;

   $errors = array();
   $contrib = $ctb_user = null;
   if ( $edit_id > 0 )
      $contrib = Contribution::load_contribution( $edit_id );
   elseif ( $del_id > 0 )
      $contrib = Contribution::load_contribution( $del_id );
   if ( $is_edit && $uid > 0 )
   {
      $ctb_user = User::load_user( $uid );
      if ( is_null($ctb_user) )
         $errors[] = ErrorCode::get_error_text('unknown_user');
   }
   elseif ( $del_id > 0 && is_null($contrib) )
   {
      $del_id = 0;
      $errors[] = T_('Can\'t find user contribution entry to remove.');
   }
   if ( is_null($contrib) )
      $contrib = new Contribution( 0, $uid );


   // delete user contribution
   if ( $del_id > 0 && $contrib->ID > 0 && @$_REQUEST['confirm'] && count($errors) == 0 )
   {
      if ( $contrib->delete() )
         jump_to($page."?uid=$uid".URI_AMP."sysmsg=". urlencode(T_('User contribution removed!')) );
      else
         $errors[] = T_('Removal of user contribution failed!');
   }


   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $contrib );
   $errors = array_merge( $errors, $input_errors );

   // update user contribution
   if ( @$_REQUEST['save'] && !@$_REQUEST['preview'] && count($errors) == 0 )
   {
      if ( count($edits) == 0 )
         $errors[] = T_('Sorry, there\'s nothing to save.');
      else
      {
         if ( $contrib->persist() )
            jump_to($page."?uid=$uid".URI_AMP."sysmsg=". urlencode(T_('User contribution saved!')) );
         else
            $errors[] = T_('Saving user contribution failed!');
      }
   }

   // load contributions (always)
   $contrib_iterator = new ListIterator( "admin_contrib.list($uid)",
         null,
         'ORDER BY CTB.Category ASC, CTB.uid ASC' );
   $contrib_iterator = Contribution::load_contributions( $contrib_iterator, $uid, true );

   $ctable = new Table( 'contrib', $page, null, '',
         TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ctable->add_tablehead( 1, T_('Actions#header'), 'Image', TABLE_NO_HIDE, '');
   $ctable->add_tablehead( 2, T_('Name#header'), 'User', 0);
   $ctable->add_tablehead( 3, T_('Userid#header'), 'User', TABLE_NO_HIDE);
   $ctable->add_tablehead( 4, T_('Category#header'), 'Enum', TABLE_NO_HIDE, 'CTB.Category+');
   $ctable->add_tablehead( 5, T_('Comment#header'), '', 0);
   $ctable->add_tablehead( 6, T_('Created#header'), 'Date', 0, 'CTB.Created-');
   $ctable->add_tablehead( 7, T_('Updated#header'), 'Date', 0, 'CTB.Updated-');

   $ctable->set_default_sort( 4 ); //on Category


   $title = T_('Admin contributions');
   start_page($title, true, $logged_in, $player_row);

   if ( $uid > 0 )
      $title .= " - " . user_reference( REF_LINK, 1, '', $uid );
   echo "<h3 class=Header>", $title, "</h3>\n";

   while ( list(,$arr_item) = $contrib_iterator->getListIterator() )
   {
      list( $ctb, $orow ) = $arr_item;
      $row_str = array();

      if ( $ctable->Is_Column_Displayed[ 1] )
      {
         $links = array();
         $links[] = anchor( "$page?uid={$ctb->uid}",
               image( 'images/table.gif', 'E', '', 'class="Action"' ), T_('Admin user contributions'));
         $links[] = anchor( "$page?edit={$ctb->ID}".URI_AMP."uid={$ctb->uid}",
               image( 'images/edit.gif', 'E', '', 'class="Action"' ), T_('Edit user contribution'));
         $links[] = anchor( "$page?del={$ctb->ID}".URI_AMP."uid={$ctb->uid}",
               image( 'images/trashcan.gif', 'E', '', 'class="Action"' ), T_('Remove user contribution'));
         $row_str[ 1] = implode(MED_SPACING, $links) . MED_SPACING;
      }
      if ( $ctable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = user_reference( REF_LINK, 1, '', $ctb->uid, $ctb->crow['CTB_Name'], '');
      if ( $ctable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = user_reference( REF_LINK, 1, '', $ctb->uid, $ctb->crow['CTB_Handle'], '');
      if ( $ctable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = Contribution::getCategoryText($ctb->Category);
      if ( $ctable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = make_html_safe( $ctb->Comment, true);
      if ( $ctable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = ($ctb->Created > 0) ? date(DATE_FMT2, $ctb->Created) : '';
      if ( $ctable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ($ctb->Updated > 0) ? date(DATE_FMT2, $ctb->Updated) : '';

      $ctable->add_row( $row_str );
   }

   $ctable->echo_table();

   $cform = new Form( 'contrib', $page, FORM_POST );

   if ( count($errors) )
   {
      $cform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $cform->add_row( array( 'HR' ));
      $cform->add_empty_row();
   }

   if ( ($is_edit || $del_id > 0) && !is_null($contrib) )
   {
      section( 'editcontrib', T_('Edit user contribution'));
      $cform->add_hidden('uid', $uid );

      if ( !is_null($ctb_user) )
         $cform->add_row( array(
               'DESCRIPTION',  T_('User'),
               'TEXT', ( is_null($ctb_user) ? NO_VALUE : $ctb_user->user_reference() ), ));

      if ( $del_id > 0 ) // DELETE
      {
         $cform->add_hidden( 'del', $del_id );
         $cform->add_hidden( 'confirm', 1 );

         $cform->add_row( array(
               'DESCRIPTION', T_('Category'),
               'TEXT', Contribution::getCategoryText($contrib->Category), ));
         $cform->add_row( array(
               'DESCRIPTION', T_('Comment'),
               'OWNHTML', '<td class="Preview">' . make_html_safe( $contrib->Comment, true) . '</td>', ));
         if ( $contrib->Created > 0 )
            $cform->add_row( array(
                  'DESCRIPTION', T_('Created'),
                  'TEXT', date(DATE_FMT2, $ctb->Created), ));
         if ( $contrib->Updated > 0 )
            $cform->add_row( array(
                  'DESCRIPTION', T_('Updated'),
                  'TEXT', date(DATE_FMT2, $ctb->Updated), ));

         $cform->add_row( array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'ctb_delete', T_('Remove user contribution'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'cancel', T_('Cancel'), ));
      }
      else // EDIT
      {
         $cform->add_hidden('edit', ( $contrib->ID > 0 ? $contrib->ID : 'new' ) );

         $cform->add_row( array(
               'DESCRIPTION', T_('Category'),
               'SELECTBOX', 'category', 1, Contribution::getCategoryText(), $vars['category'], false, ));
         $cform->add_row( array(
               'DESCRIPTION', T_('Comment'),
               'TEXTAREA', 'comment', 60, 3, $vars['comment'], ));

         $cform->add_empty_row();
         $cform->add_row( array(
               'DESCRIPTION', T_('Unsaved edits'),
               'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));
         $cform->add_row( array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'save', T_('Save Contribution'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'preview', T_('Preview'), ));
      }

      if ( @$_REQUEST['preview'] )
      {
         $cform->add_empty_row();
         $cform->add_row( array(
               'DESCRIPTION', T_('Preview'),
               'OWNHTML', '<td class="Preview">' . make_html_safe( $contrib->Comment, true) . '</td>', ));
      }
   }

   if ( $cform->has_rows() )
      $cform->echo_string();


   $menu_array = array();
   $menu_array[T_('Admin contributions')] = $page."?uid=0";
   if ( $uid > 0 )
   {
      $menu_array[T_('New user contributions')] = $page."?uid=$uid".URI_AMP."edit=new";
      $menu_array[T_('Admin user contributions')] = $page."?uid=$uid";
   }
   $menu_array[T_('People')] = 'people.php';

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$contrib )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['save'] || @$_REQUEST['preview'] );

   // read from props or set defaults
   $vars = array(
      'category'  => $contrib->Category,
      'comment'   => $contrib->Comment,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if ( $is_posted )
   {
      $contrib->setCategory( $vars['category'] );

      $new_value = trim( $vars['comment'] );
      if ( strlen($new_value) > 255 )
         $errors[] = sprintf( T_('Comment too long (max. %s chars allowed)#contrib'), 255 );
      else
         $contrib->Comment = $new_value;

      // determine edits
      if ( $old_vals['category'] != $contrib->Category || $contrib->ID == 0 ) $edits[] = T_('Category');
      if ( $old_vals['comment'] != $contrib->Comment ) $edits[] = T_('Comment');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

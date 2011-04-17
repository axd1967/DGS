<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Bulletin";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/db/bulletin.php';

$GLOBALS['ThePage'] = new Page('BulletinAdmin');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);
   if( !$is_admin )
      error('adminlevel_too_low', "admin_bulletin");

/* Actual REQUEST calls used:
     ''                       : add new bulletin
     bid=                     : edit existing bulletin
     preview&bid=             : preview for bulletin-save
     save&bid=                : save new/updated bulletin
*/

   $bid = (int) @$_REQUEST['bid'];
   if( $bid < 0 ) $bid = 0;

   // init
   $bulletin = ( $bid > 0 ) ? Bulletin::load_bulletin($bid) : null;
   if( is_null($bulletin) )
      $bulletin = Bulletin::new_bulletin( $my_id, $is_admin );

   $b_old_status = $bulletin->Status;
   $b_old_category = $bulletin->Category;
   $b_old_target_type = $bulletin->TargetType;
   $arr_status = Bulletin::getStatusText();
   $arr_categories = Bulletin::getCategoryText();
   $arr_target_types = Bulletin::getTargetTypeText();

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $bulletin, $is_admin );
   $errors = $input_errors;

   // save bulletin-object with values from edit-form
   if( @$_REQUEST['save'] && !@$_REQUEST['preview'] && count($errors) == 0 )
   {
      $bulletin->persist();
      $bid = $bulletin->ID;
      jump_to("admin_bulletin.php?bid=$bid".URI_AMP."sysmsg=". urlencode(T_('Bulletin saved!')) );
   }

   $page = "admin_bulletin.php";
   $title = T_('Admin Bulletin');


   // ---------- Tournament EDIT form ------------------------------

   $bform = new Form( 'bulletinEdit', $page, FORM_POST );
   $bform->add_hidden( 'bid', $bid );

   if( count($errors) )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $bform->add_empty_row();
   }

   $bform->add_row( array(
         'DESCRIPTION', T_('Current Category#bulletin'),
         'TEXT',        Bulletin::getCategoryText($b_old_category) ));
   $bform->add_row( array(
         'TAB',
         'SELECTBOX',    'category', 1, $arr_categories, $vars['category'], false, ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Current Status#bulletin'),
         'TEXT',        Bulletin::getStatusText($b_old_status) ));
   $bform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $vars['status'], false, ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Current Target Type#bulletin'),
         'TEXT',        Bulletin::getTargetTypeText($b_old_target_type) ));
   $bform->add_row( array(
         'TAB',
         'SELECTBOX',    'target_type', 1, $arr_target_types, $vars['target_type'], false, ));

   $bform->add_empty_row();
   $bform->add_row( array(
         'DESCRIPTION', T_('Publish Time'),
         'TEXTINPUT',   'publish_time', 20, 30, $vars['publish_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE)), ));
   /* //TODO
   $bform->add_row( array(
         'DESCRIPTION', T_('Expire Time'),
         'TEXTINPUT',   'expire_time', 20, 30, $vars['expire_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE )), ));
    */
   $bform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 255, $vars['subject'] ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Text'),
         'TEXTAREA',    'text', 80, 10, $vars['text'] ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $bform->add_empty_row();
   $bform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'save', T_('Save bulletin'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'preview', T_('Preview'),
      ));

   if( @$_REQUEST['preview'] || $bulletin->Subject . $bulletin->Text != '' )
   {
      $bform->add_empty_row();
      $bform->add_row( array(
            'DESCRIPTION', T_('Preview'),
            'OWNHTML', '<td class="Preview">' . Bulletin::build_view_bulletin($bulletin) . '</td>', ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $bform->echo_string();


   $menu_array = array();
   $menu_array[T_('Bulletins')] = "list_bulletins.php";
   $menu_array[T_('New admin bulletin')] =
      array( 'url' => "admin_bulletin.php", 'class' => 'AdminLink' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$bulletin, $is_admin )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['save'] || @$_REQUEST['preview'] );

   // read from props or set defaults
   $vars = array(
      'category'        => $bulletin->Category,
      'status'          => $bulletin->Status,
      'target_type'     => $bulletin->TargetType,
      'publish_time'    => formatDate($bulletin->PublishTime),
      //TODO'expire_time'     => formatDate($bulletin->ExpireTime),
      'subject'         => $bulletin->Subject,
      'text'            => $bulletin->Text,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $old_vals['publish_time'] = $bulletin->PublishTime;
      //TODO $old_vals['expire_time'] = $bulletin->ExpireTime;

      $bulletin->setCategory($vars['category']);
      $bulletin->setStatus($vars['status']);

      $bulletin->setTargetType($vars['target_type']);
      if( $bulletin->TargetType == BULLETIN_TRG_UNSET )
         $errors[] = sprintf( T_('Bulletin target-type [%s] is only for safety and must be changed!'),
            Bulletin::getTargetTypeText(BULLETIN_TRG_UNSET) );

      $parsed_value = parseDate( T_('Publish time for bulletin'), $vars['publish_time'] );
      if( is_numeric($parsed_value) )
      {
         $bulletin->PublishTime = $parsed_value;
         $vars['publish_time'] = formatDate($bulletin->PublishTime);
      }
      else
         $errors[] = $parsed_value;

      /* TODO
      $parsed_value = parseDate( T_('Expire time for bulletin'), $vars['expire_time'] );
      if( is_numeric($parsed_value) )
      {
         $bulletin->ExpireTime = $parsed_value;
         $vars['expire_time'] = formatDate($bulletin->ExpireTime);
      }
      else
         $errors[] = $parsed_value;
      */

      $new_value = trim($vars['subject']);
      if( strlen($new_value) < 8 )
         $errors[] = T_('Bulletin subject missing or too short');
      else
         $bulletin->Subject = $new_value;

      $new_value = trim($vars['text']);
      $bulletin->Text = $new_value;


      // determine edits
      if( $old_vals['category'] != $bulletin->Category ) $edits[] = T_('Category#edits');
      if( $old_vals['status'] != $bulletin->Status ) $edits[] = T_('Status#edits');
      if( $old_vals['target_type'] != $bulletin->TargetType ) $edits[] = T_('TargetType#edits');
      if( $old_vals['publish_time'] != $bulletin->PublishTime ) $edits[] = T_('PublishTime#edits');
      //TODO if( $old_vals['expire_time'] != $bulletin->ExpireTime ) $edits[] = T_('ExpireTime#edits');
      if( $old_vals['subject'] != $bulletin->Subject ) $edits[] = T_('Subject#edits');
      if( $old_vals['text'] != $bulletin->Text ) $edits[] = T_('Text#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

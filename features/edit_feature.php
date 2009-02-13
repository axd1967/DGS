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

$TranslateGroups[] = "Common";

chdir('../');
require_once( "include/std_functions.php" );
require_once( 'include/utilities.php' );
require_once( "include/form_functions.php" );
require_once( "features/lib_votes.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !ALLOW_FEATURE_VOTE )
      error('feature_disabled', 'feature_vote(edit)');

   $my_id = (int)@$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');
   if ( !Feature::is_admin() )
      error('feature_edit_not_allowed');

   $is_super_admin = Feature::is_super_admin();

/* Actual REQUEST calls used:
     (no args)             : add new feature
     fid=                  : edit new or existing feature
     feature_save&fid=     : update (replace) feature in database
     feature_preview&fid=  : preview for update feature
     feature_delete&fid=   : remove feature (need confirm)
     feature_delete&confirm=1&fid= : remove feature (confirmed)
     feature_cancel        : cancel remove-confirmation
*/

   $fid = get_request_arg('fid'); //feature-ID
   if( $fid < 0 )
      $fid = 0;

   if( @$_REQUEST['feature_cancel'] ) // cancel delete
      jump_to("features/edit_feature.php?fid=$fid");

   // error-check on feature to save
   $errormsg = null;
   if( @$_REQUEST['feature_save'] || @$_REQUEST['feature_preview'] )
   {
      if( strlen(trim(get_request_arg('subject'))) == 0 )
         $errormsg = '('.T_('Missing subject of feature').')';
   }

   $feature = null;
   if( is_null($errormsg) && $fid )
      $feature = Feature::load_feature( $fid ); // existing feature ?
   if( is_null($feature) )
      $feature = Feature::new_feature( $my_id, $fid ); // new feature

   // check access right of user (only super-admin can edit anytime)
   if( !$feature->allow_edit() )
      error('feature_edit_bad_status', "edit_feature.feature($fid,$my_id)");

   $is_feature_delete = ( $fid && @$_REQUEST['feature_delete'] );
   if( $is_feature_delete && @$_REQUEST['confirm'] )
   {
      $feature->delete_feature();
      jump_to("features/list_features.php?sysmsg=". urlencode(T_('Feature removed!')) );
   }

   $new_status = get_request_arg('new_status');

   // insert/update feature-object with values from edit-form if no error
   if( @$_REQUEST['feature_save'] || @$_REQUEST['feature_preview'] )
   {
      $feature->set_subject( get_request_arg('subject') );
      $feature->set_description( get_request_arg('description') );

      if( !@$_REQUEST['feature_preview'] && is_null($errormsg) )
      {
         if( $is_super_admin )
            $feature->set_status( $new_status );

         $feature->update_feature();
         // if new feature added, add next; if edit feature, edit again
         jump_to("features/edit_feature.php?fid=$fid".URI_AMP."sysmsg=". urlencode(T_('Feature saved!')) );
      }
   }

   $can_delete_feature = $feature->can_delete_feature();


   $page = 'edit_feature.php';
   if( $is_feature_delete )
      $title = T_('Feature delete');
   elseif( $fid )
      $title = T_('Feature update');
   else
      $title = T_('Feature add');

   $fform = new Form( 'feature', $page, FORM_POST );

   // edit feature
   $fform->add_row( array(
      'DESCRIPTION',  T_('ID'),
      'TEXT',         ($fid ? $fid : '-') ));
   $arr_status = array(
      'DESCRIPTION',  T_('Status'),
      'TEXT',         $feature->status );
   if( $is_super_admin )
   {
      // status-change
      $status_values = array_value_to_key_and_value(
            array( FEATSTAT_NEW , FEATSTAT_WORK, FEATSTAT_DONE, FEATSTAT_LIVE, FEATSTAT_NACK ) );
      array_push( $arr_status,
         'TEXT', '&nbsp;-&gt;&nbsp;',
         'SELECTBOX', 'new_status', 1, $status_values, $new_status, false );
   }
   $fform->add_row( $arr_status );
   if( $is_super_admin )
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Editor'),
         'TEXT', user_reference( REF_LINK, 1, '', $player_row ) ));
   }
   $fform->add_row( array(
      'DESCRIPTION',  T_('Created'),
      'TEXT',         date(DATEFMT_FEATURE, $feature->created) ));
   if( $feature->lastchanged )
   {
      $fform->add_row( array(
         'DESCRIPTION',  T_('Lastchanged'),
         'TEXT',         date(DATEFMT_FEATURE, $feature->lastchanged) ));
   }

   if( $is_feature_delete && !$can_delete_feature )
   {
      $errormsg = ( is_null($errormsg) ) ? '' : $errormsg . "<br>\n";
      $errormsg .= T_('Feature can\'t be deleted because of existing votes!');
   }

   if( !is_null($errormsg) )
      $fform->add_row( array(
         'DESCRIPTION', T_('Error'),
         'TEXT', '<span class="ErrorMsg">' . $errormsg . '</span>' ));

   if( @$_REQUEST['feature_delete'] ) // delete
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXT',        $feature->subject,
         ));
      $fform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXT',        $feature->description,
         ));

      if( $can_delete_feature )
      {
         $fform->add_hidden( 'confirm', 1 );
         $fform->add_row( array(
            'TAB',
            'SUBMITBUTTON', 'feature_delete', T_('Remove feature'),
            'SUBMITBUTTON', 'feature_cancel', T_('Cancel') ));
      }
   }
   else // add or edit
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 120, $feature->subject,
         ));
      $fform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXTAREA',    'description', 70, 10, $feature->description,
         ));

      $fform->add_row( array(
         'DESCRIPTION', T_('Preview'),
         'TEXT',        make_html_safe($feature->subject, SUBJECT_HTML),
         ));
      $fform->add_row( array(
         'TAB',
         'TEXT',        $feature->description,
         ));

      $fform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'feature_preview', T_('Preview'),
         'TEXT', '&nbsp;&nbsp;&nbsp;',
         'SUBMITBUTTON', 'feature_save', T_('Save feature'),
         ));
   }

   $fform->add_hidden( 'fid', $fid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $fform->echo_string();

   $notes = Feature::build_feature_notes( null, false );
   if( Feature::is_admin() )
      array_unshift( $notes,
         T_('Add related URLs using &lt;home&gt;-tag or &lt;http://...&gt; in description.'),
         T_('Add reason and properly adjust description on status changes.') );
   Feature::echo_feature_notes( 'featurenotesTable', $notes );

   $menu_array = array();
   $menu_array[T_('Show features')] = "features/list_features.php";
   $menu_array[T_('Show votes')]    = "features/list_votes.php";
   if( Feature::is_admin() )
      $menu_array[T_('Add new feature')] = "features/edit_feature.php";
   if( !$is_feature_delete && $can_delete_feature && $fid > 0 && Feature::is_super_admin() )
      $menu_array[T_('Delete this feature')] =
         "features/edit_feature.php?fid=$fid".URI_AMP.'feature_delete=1';
   if( $is_feature_delete && $fid > 0 && $feature->allow_edit() )
      $menu_array[T_('Edit this feature')] = "features/edit_feature.php?fid=$fid";

   end_page(@$menu_array);
}
?>

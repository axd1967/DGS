<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir("../../");
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "features/vote/lib_votes.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( $player_row['Handle'] == 'guest' )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     (no args)             : add new feature
     fid=                  : edit new or existing feature
     feature_save&fid=     : update (replace) feature in database
     feature_delete&fid=   : remove feature (need confirm)
     feature_delete&confirm=1&fid= : remove feature (confirmed)
     feature_cancel        : cancel remove-confirmation
*/

   if ( @$_REQUEST['feature_cancel'] ) // cancel delete
      jump_to("features/vote/list_features.php");

   $my_id = $player_row['ID'];
   $is_admin = Feature::is_admin();

   $fid = get_request_arg('fid'); //feature-ID
   if ( $fid < 0 )
      $fid = 0;

   // error-check on feature to save
   $errormsg = null;
   if ( @$_REQUEST['feature_save'] )
   {
      if ( strlen(trim(get_request_arg('subject'))) == 0 )
         $errormsg = '('.T_('Missing subject of feature').')';
   }

   $feature = null;
   if ( is_null($errormsg) && $fid )
      $feature = Feature::load_feature( $fid ); // existing feature ?
   if ( is_null($feature) )
      $feature = Feature::new_feature( $my_id, $fid ); // new feature

   // check access right of user
   if ( !$feature->allow_edit( $my_id ) )
      error('action_not_allowed', "edit_feature.feature($fid,$my_id)");

   if ( $fid && @$_REQUEST['feature_delete'] && @$_REQUEST['confirm'] )
   {
      $feature->delete_feature();
      jump_to("features/vote/list_features.php?sysmsg=". urlencode(T_('Feature removed!')) );
   }

   $new_status = get_request_arg('new_status');

   // insert/update feature-object with values from edit-form if no error
   if ( @$_REQUEST['feature_save'] )
   {
      if ( $is_admin )
         $feature->set_status( $new_status );
      $feature->set_subject( get_request_arg('subject') );
      $feature->set_description( get_request_arg('description') );

      if ( is_null($errormsg) )
      {
         $feature->update_feature();
         // if new feature added, add next; if edit feature, edit again
         jump_to("features/vote/edit_feature.php?fid=$fid".URI_AMP."sysmsg=". urlencode(T_('Feature saved!')) );
      }
   }


   $page = 'edit_feature.php';
   if ( $fid )
      $title = T_('Feature add');
   else
      $title = T_('Feature update');

   $fform = new Form( 'feature', $page, FORM_POST );

   // edit feature
   $fform->add_row( array(
      'DESCRIPTION',  T_('ID'),
      'TEXT',         ($fid ? $fid : '-') ));
   $arr_status = array(
      'DESCRIPTION',  T_('Status'),
      'TEXT',         $feature->status );
   if ( $is_admin )
   {
      // status-change
      $status_values = array(
         FEATSTAT_NEW  => T_('New (feature check, then choose ACK or NACK)'),
         FEATSTAT_NACK => T_('NACK (feature not accepted)'),
         FEATSTAT_ACK  => T_('ACK (feature accepted, can be voted)'),
         FEATSTAT_WORK => T_('Work (feature in work, can be voted'),
         FEATSTAT_DONE => T_('Done (feature implemented)'),
      );
      array_push( $arr_status,
         'TEXT', '&nbsp;-&gt;&nbsp;',
         'SELECTBOX', 'new_status', 1, $status_values, $new_status, false );
   }
   $fform->add_row( $arr_status );
   if ( $is_admin )
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Editor'),
         'TEXT', user_reference( REF_LINK, 1, '', $player_row ) ));
   }
   $fform->add_row( array(
      'DESCRIPTION',  T_('Created'),
      'TEXT',         date(DATEFMT_FEATURE, $feature->created) ));
   if ( $feature->lastchanged )
   {
      $fform->add_row( array(
         'DESCRIPTION',  T_('Lastchanged'),
         'TEXT',         date(DATEFMT_FEATURE, $feature->lastchanged) ));
   }

   if ( !is_null($errormsg) )
      $fform->add_row( array( 'TAB', 'TEXT', '<font color=darkred>' . $errormsg . '</font>' ));


   if ( @$_REQUEST['feature_delete'] ) // delete
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXT',        $feature->subject,
         ));
      $fform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXT',        $feature->description,
         ));

      $fform->add_hidden( 'confirm', 1 );
      $fform->add_row( array(
         'SUBMITBUTTON', 'feature_delete', T_('Remove feature'),
         'SUBMITBUTTON', 'feature_cancel', T_('Cancel') ));
   }
   else // add or edit
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 120, $feature->subject,
         ));
      $fform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXTAREA',    'description', 70, 10, textarea_safe($feature->description),
         ));

      $fform->add_row( array(
         'SUBMITBUTTON', 'feature_save', T_('Save feature'),
         ));
   }

   $fform->add_hidden( 'fid', $fid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo "<CENTER>\n";
   $fform->echo_string();
   echo "</CENTER><BR>\n";

   $menu_array[T_('Show features')] = "features/vote/list_features.php";
   if ( Feature::allow_user_edit( $my_id ) )
      $menu_array[ T_('Add new feature') ] = "features/vote/edit_feature.php";

   end_page(@$menu_array);
}
?>

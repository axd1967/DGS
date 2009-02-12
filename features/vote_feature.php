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
     view=1&fid=             : view existing feature (for description)
     fid=                    : edit new or existing feature-vote
     vote_save&fid=&points= : update (replace) feature-vote in database
*/

   $my_id = $player_row['ID'];
   $is_admin = Feature::is_admin();

   $fid = get_request_arg('fid'); //feature-ID
   if ( $fid < 0 )
      $fid = 0;
   $points = get_request_arg('points', '');
   $viewmode = get_request_arg('view', ''); // can be forced

   // error-check on feature to save
   $errormsg = null;
   if ( @$_REQUEST['vote_save'] )
   {
      $errormsg = FeatureVote::check_points( $points );
   }

   // load feature + vote
   $feature = null;
   if ( $fid )
   {
      $feature = Feature::load_feature( $fid );
      if ( !is_null($feature) )
      {
         $fvote = $feature->load_featurevote( $my_id );
         if ( !is_null($fvote) && $points == '' )
            $points = $fvote->get_points();
      }
   }
   if ( is_null($feature) )
      error('unknown_object', "featurevote.no_featureid($fid)");

   $allow_voting    = Feature::allow_voting(); // user pre-conditions: if false no view of user-vote (only feature-description)
   $allow_vote_edit = $feature->allow_vote( $my_id ); // user allowed to edit vote
   if ( $viewmode )
      $allow_vote_edit = false;

   // insert/update feature-vote-object with values from edit-form if no error
   if ( is_null($errormsg) && @$_REQUEST['vote_save'] && $allow_vote_edit )
   {
      $feature->update_vote( $my_id, $points );
      jump_to("features/vote/vote_feature.php?fid=$fid".URI_AMP."sysmsg=". urlencode(T_('Feature vote saved!')) );
   }

   $page = 'vote_feature.php';
   if ( $allow_vote_edit )
      $title = T_('Feature vote');
   else
      $title = T_('Feature view');


   $fform = new Form( 'featurevote', $page, FORM_POST );

   // edit feature vote
   $fform->add_row( array(
      'DESCRIPTION',  T_('ID'),
      'TEXT',         ($fid ? $fid : '-') ));
   $fform->add_row( array(
      'DESCRIPTION',  T_('Status'),
      'TEXT',         $feature->status ));
   if ( $is_admin )
   {
      $fform->add_row( array(
         'DESCRIPTION', T_('Editor'),
         'TEXT', user_reference( REF_LINK, 1, '', $player_row ) ));
   }
   $fform->add_row( array(
      'DESCRIPTION',  T_('Created'),
      'TEXT',         date(DATEFMT_FEATURE, $feature->created) ));
   $fform->add_row( array(
      'DESCRIPTION',  T_('Lastchanged'),
      'TEXT',         date(DATEFMT_FEATURE, $feature->lastchanged) ));
   if ( $allow_vote_edit && $fvote->lastchanged > 0 )
   {
      $fform->add_row( array(
         'DESCRIPTION',  T_('Lastvoted'),
         'TEXT',         date(DATEFMT_FEATURE, $fvote->lastchanged) ));
   }

   $fform->add_row( array(
      'DESCRIPTION', T_('Subject'),
      'TEXT',        $feature->subject ));
   $fform->add_row( array(
      'DESCRIPTION', T_('Description'),
      'TEXT',        $feature->description ));

   if ( !is_null($errormsg) )
      $fform->add_row( array( 'TAB', 'TEXT', '<font color=darkred>' . $errormsg . '</font>' ));

   if ( $allow_vote_edit )
   {
      $vote_values = array();
      for ( $i = -FEATVOTE_MAXPOINTS; $i <= FEATVOTE_MAXPOINTS; $i++)
         $vote_values[$i] = (($i > 0) ? '+' : '') . $i;
      $vote_values['0'] = "=0";
      $fform->add_row( array(
         'DESCRIPTION', T_('Vote'),
         'SELECTBOX',   'points', 1, $vote_values, $points, false,
         'TEXT',        T_('points#feature') . ' (' . T_('0=neutral, <0=not wanted, >0=wanted feature') . ')' ));
      $fform->add_row( array(
         'SUBMITBUTTON', 'vote_save', T_('Save vote'),
         ));

      $fform->add_hidden( 'fid', $fid );
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo "<CENTER>\n";
   $fform->echo_string();
   echo "</CENTER><BR>\n";

   $menu_array[T_('Show features')] = "features/vote/list_features.php";
   if ( Feature::allow_user_edit( $my_id ) )
      $menu_array[T_('Add new feature')] = "features/vote/edit_feature.php";
   $menu_array[ T_('Show votes') ] = "features/vote/list_votes.php";

   end_page(@$menu_array);
}
?>

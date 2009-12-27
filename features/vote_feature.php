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

$TranslateGroups[] = "Common";

chdir('../');
require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( 'include/classlib_userquota.php' );
require_once( "features/lib_votes.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_FEATURE_VOTE )
      error('feature_disabled', 'feature_vote(vote)');

   $my_id = (int)@$player_row['ID'];

   $is_admin = Feature::is_admin();
   $user_quota = UserQuota::load_user_quota($my_id);
   if( is_null($user_quota) )
      error('miss_user_quota', "vote_feature.user_quota.check($my_id)");

/* Actual REQUEST calls used:
     view=1&fid=             : view existing feature (for description)
     fid=                    : edit new or existing feature-vote
     vote_save&fid=&points= : update (replace) feature-vote in database
*/

   $fid = get_request_arg('fid'); //feature-ID
   if( $fid < 0 )
      $fid = 0;
   $points = get_request_arg('points', '');
   $viewmode = get_request_arg('view', ''); // can be forced

   // error-check on feature to save
   $errormsg = null;
   if( @$_REQUEST['vote_save'] )
   {
      $errormsg = FeatureVote::check_points( $points, $user_quota->feature_points );
   }

   // load feature + vote
   $feature = $fvote = null;
   if( $fid )
   {
      $feature = Feature::load_feature( $fid );
      if( !is_null($feature) )
      {
         $fvote = $feature->load_featurevote( $my_id );
         if( !is_null($fvote) && $points == '' )
            $points = $fvote->get_points();
      }
   }
   if( is_null($feature) )
      error('unknown_object', "featurevote.no_featureid($fid)");

   // check user pre-conditions
   $user_vote_reason = Feature::allow_vote_check();
   $allow_vote_edit = is_null($user_vote_reason) && $feature->allow_vote();
   if( $viewmode )
      $allow_vote_edit = false;

   // insert/update feature-vote-object with values from edit-form if no error
   if( is_null($errormsg) && @$_REQUEST['vote_save'] && $allow_vote_edit )
   {
      $add_fpoints = $feature->update_vote( $my_id, $points );
      $user_quota->modify_feature_points( $add_fpoints );
      $user_quota->update_feature_points();

      if( is_null($fvote) ) // is new vote by user
         update_count_feature_new( "vote_feature.save_vote($fid,$my_id)", $my_id, -1 ); // one NEW less

      jump_to("features/vote_feature.php?fid=$fid".URI_AMP."sysmsg=". urlencode(T_('Feature vote saved!')) );
   }

   $page = 'vote_feature.php';
   $title = ( $allow_vote_edit ) ? T_('Feature vote') : T_('Feature vote view');

   $fform = new Form( 'featurevote', $page, FORM_POST );

   // edit feature vote
   $fform->add_row( array(
      'DESCRIPTION',  T_('ID'),
      'TEXT',         ($fid ? $fid : NO_VALUE) ));
   $fform->add_row( array(
      'DESCRIPTION',  T_('Status'),
      'TEXT',         $feature->status ));
   if( $is_admin )
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
   if( $allow_vote_edit && !is_null($fvote) && $fvote->lastchanged > 0 )
   {
      $fform->add_row( array(
         'DESCRIPTION',  T_('Lastvoted'),
         'TEXT',         date(DATEFMT_FEATURE, $fvote->lastchanged) ));
   }

   $fform->add_row( array(
      'DESCRIPTION', T_('Subject'),
      'TEXT',        make_html_safe( wordwrap($feature->subject,FEAT_SUBJECT_WRAPLEN), true) ));
   $fform->add_row( array(
      'DESCRIPTION', T_('Description'),
      'TEXT',        make_html_safe( $feature->description, true) ));

   if( !is_null($errormsg) )
      $fform->add_row( array(
         'DESCRIPTION', T_('Error'),
         'TEXT',        '<span class="ErrorMsg">' . $errormsg . '</span>' ));

   if( $allow_vote_edit && $user_quota->feature_points > 0 )
   {
      $vote_values = array();
      $max_points = min( FEATVOTE_MAXPOINTS, $user_quota->feature_points );
      for( $i = +$max_points; $i >= -$max_points; $i--)
         $vote_values[$i] = (($i > 0) ? '+' : '') . $i;
      $vote_values['0'] = '=0';

      $fform->add_empty_row();
      $fform->add_row( array(
         'DESCRIPTION', T_('Vote'),
         'SELECTBOX',   'points', 1, $vote_values, $points, false,
         'TEXT',        T_('points#feature') . ' (' . T_('0=neutral, <0=not wanted, >0=wanted feature') . ')' ));
      $fform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-button
         'SUBMITBUTTON', 'vote_save', T_('Save vote'),
         ));
      $fform->add_hidden( 'fid', $fid );
   }
   else
   {// only view
      $point_str = ( is_numeric($points) )
         ? sprintf( T_('%s points#feature'), ($points > 0 ? '+' : '') . $points )
         : sprintf( T_('%s (no vote)'), NO_VALUE );
      $fform->add_row( array(
         'DESCRIPTION', T_('Vote'),
         'TEXT',        $point_str,
         ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n",
      FeatureVote::getFeaturePointsText( $user_quota->feature_points ),
      "<br><br>\n";

   $fform->echo_string();


   $menu_array = array();
   $menu_array[T_('Vote on features')] = "features/list_features.php";
   $menu_array[T_('Show feature votes')] = "features/list_votes.php";
   if( $is_admin )
   {
      $menu_array[T_('Add new feature')] =
         array( 'url' => "features/edit_feature.php", 'class' => 'AdminLink' );
      if( $fid > 0 && $feature->allow_edit() )
         $menu_array[T_('Edit this feature')] =
            array( 'url' => "features/edit_feature.php?fid=$fid", 'class' => 'AdminLink' );
   }

   end_page(@$menu_array);
}
?>

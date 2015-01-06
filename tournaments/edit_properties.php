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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/rating.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPropertiesEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_properties');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_properties');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_properties');

/* Actual REQUEST calls used:
     tid=                : edit tournament properties
     tp_preview&tid=     : preview for properties-save
     tp_save&tid=        : update (replace) properties in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_properties.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   $t_limits = $ttype->getTournamentLimits();
   $is_admin = TournamentUtils::isAdmin();

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_properties.edit_tournament($tid,$my_id)");

   $tprops = TournamentCache::load_cache_tournament_properties( 'Tournament.edit_properties', $tid );

   // init
   $errors = $tstatus->check_edit_status( TournamentProperties::get_edit_tournament_status() );
   $arr_rating_use_modes = TournamentHelper::get_restricted_RatingUseModeTexts($t_limits);
   $rating_array = getRatingArray();
   $allow_custom_round = ( $is_admin || $ttype->getMaxRounds() > 1 );
   if ( !$is_admin && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check + parse edit-form
   $old_tprops = clone $tprops;
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tprops, $t_limits, $ttype );
   $errors = array_merge( $errors, $input_errors );

   // save tournament-properties-object with values from edit-form
   if ( @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && count($errors) == 0 )
   {
      $tprops->update();
      TournamentLogHelper::log_change_tournament_props( $tid, $allow_edit_tourney, $edits, $old_tprops, $tprops );

      jump_to("tournaments/edit_properties.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Properties saved!')) );
   }

   $page = "edit_properties.php";
   $title = T_('Tournament Registration-Properties Editor');


   // ---------- Tournament-Properties EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_POST );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   if ( $tprops->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tprops->Lastchanged, $tprops->ChangedBy) ));
   $tform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   // ----- tournament-type-specific -----

   $reg_end_time = trim(get_request_arg('reg_end_time'));
   $tform->add_row( array(
         'DESCRIPTION', T_('Registration end time#tourney'),
         'TEXTINPUT',   'reg_end_time', 20, 20, $vars['reg_end_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s], local timezone)'), FMT_PARSE_DATE )), ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Restrict participants#tourney'),
         'TEXTINPUT',   'min_participants', 5, 5, $vars['min_participants'],
         'TEXT',        MINI_SPACING . T_('(Minimum)'), ));
   $tform->add_row( array(
         'TAB',
         'TEXTINPUT',   'max_participants', 5, 5, $vars['max_participants'],
         'TEXT',        MINI_SPACING . T_('(Maximum)'),
         'TEXT',        $t_limits->getLimitRangeTextAdmin(TLIMITS_MAX_TP), ));

   if ( $allow_custom_round )
   {
      $max_rounds = $ttype->determineLimitMaxStartRound( $tprops->MaxParticipants );
      $range_text = sprintf( T_('Range %s, depends on max. participants#tourney'), build_range_text(1, $max_rounds));
      if ( $is_admin )
         $range_text = span('TWarning', $range_text);
      $tform->add_row( array(
            'DESCRIPTION', T_('Max. Start Round'),
            'TEXTINPUT',   'max_start_round', 5, 5, $vars['max_start_round'],
            'TEXT',        MINI_SPACING . $range_text, ));

      $rating = $vars['min_rat_start_round'];
      $valid_rat = is_valid_rating($rating);
      $tform->add_row( array(
            'DESCRIPTION', T_('Min. Rating Start Round'),
            'TEXTINPUT',   'min_rat_start_round', 12, 12, ( $rating == NO_RATING
                  ? ''
                  : ( !$valid_rat ? $rating : echo_rating($rating, 1, 0, true, 1) ) ),
            'TEXT', ( $valid_rat ? sprintf(' (=%s %s), ', T_('ELO#rating'), echo_rating_elo($rating)) : '' ),
            'TEXT', T_('empty = forbid user to change start-round#tourney'), ));
   }

   $tform->add_row( array(
         'DESCRIPTION', T_('Rating Use Mode#tourney'),
         'SELECTBOX',   'rating_use_mode', 1, $arr_rating_use_modes, $vars['rating_use_mode'], false, ));
   $tform->add_empty_row();

   // ----- user-specific -----

   $tform->add_row( array(
         'DESCRIPTION', T_('Restrict user rating#tourney'),
         'CHECKBOX',    'user_rated', '1', '', $vars['user_rated'],
         'TEXT',        sptext(T_('If yes, rating between'), 1),
         'SELECTBOX',   'user_min_rating', 1, $rating_array, $vars['user_min_rating'], false,
         'TEXT',        sptext(T_('and')),
         'SELECTBOX',   'user_max_rating', 1, $rating_array, $vars['user_max_rating'], false, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Minimum finished games'),
         'TEXTINPUT',   'min_games_finished', 5, 5, $vars['min_games_finished'],
         'TEXT',        MINI_SPACING . T_('(rated and unrated)'), ));
   $tform->add_row( array(
         'TAB',
         'TEXTINPUT',   'min_games_rated', 5, 5, $vars['min_games_rated'],
         'TEXT',        MINI_SPACING . T_('(rated only)'), ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Notes'),
         'TEXTAREA',    'notes', 70, 10, $vars['notes'] ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tp_save', T_('Save Tournament Properties'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tp_preview', T_('Preview'),
      ));

   if ( @$_REQUEST['tp_preview'] || $tprops->Notes != '' )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Preview Notes'),
            'TEXT', make_html_safe( $tprops->Notes, true ) ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   echo_notes( 'edittournamentpropsnotesTable', T_('Tournament Properties notes'),
               build_properties_notes($t_limits) );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tpr, $t_limits, $ttype )
{
   global $allow_custom_round;

   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] );

   // read from props or set defaults
   $vars = array(
      'reg_end_time'          => formatDate($tpr->RegisterEndTime),
      'min_participants'      => $tpr->MinParticipants,
      'max_participants'      => $tpr->MaxParticipants,
      'max_start_round'       => $tpr->MaxStartRound,
      'min_rat_start_round'   => $tpr->MinRatingStartRound,
      'rating_use_mode'       => $tpr->RatingUseMode,
      'user_rated'            => $tpr->UserRated,
      'user_min_rating'       => echo_rating( $tpr->UserMinRating, false, 0, true, false ),
      'user_max_rating'       => echo_rating( $tpr->UserMaxRating, false, 0, true, false ),
      'min_games_finished'    => $tpr->UserMinGamesFinished,
      'min_games_rated'       => $tpr->UserMinGamesRated,
      'notes'                 => $tpr->Notes,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if ( $is_posted )
   {
      foreach ( array( 'user_rated' ) as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   if ( $is_posted )
   {
      $old_vals['reg_end_time'] = $tpr->RegisterEndTime;
      $old_vals['user_min_rating'] = $tpr->UserMinRating;
      $old_vals['user_max_rating'] = $tpr->UserMaxRating;

      $parsed_value = parseDate( T_('End time for registration#tourney'), $vars['reg_end_time'] );
      if ( is_numeric($parsed_value) )
      {
         $tpr->RegisterEndTime = $parsed_value;
         $vars['reg_end_time'] = formatDate($tpr->RegisterEndTime);
      }
      else
         $errors[] = $parsed_value;

      $new_value = $vars['min_participants'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >=0 && $new_value <= TP_MAX_COUNT )
         $tpr->MinParticipants = limit( $new_value, 0, TP_MAX_COUNT, 0 );
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('minimum participants#tourney'),
            build_range_text(0, TP_MAX_COUNT) );

      $new_value = $vars['max_participants'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >=0 && $new_value <= TP_MAX_COUNT )
      {
         $limit_errors = $t_limits->check_MaxParticipants( $new_value, $tpr->MaxParticipants );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $tpr->MaxParticipants = limit( $new_value, 0, TP_MAX_COUNT, 0 );
      }
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('maximum participants#tourney'),
            $t_limits->getLimitRangeText(TLIMITS_MAX_TP, TP_MAX_COUNT) ); // check for general MAX, but show specific max

      if ( $tpr->MinParticipants > 0 && $tpr->MaxParticipants > 0 && $tpr->MinParticipants > $tpr->MaxParticipants )
         $errors[] = T_('Maximum participants must be greater than minimum participants');

      if ( $vars['rating_use_mode'] == TPROP_RUMODE_COPY_CUSTOM
            && ($t_limits->getMinLimit(TLIMITS_TPR_RATING_USE_MODE) & TLIM_TPR_RUM_NO_COPY_CUSTOM) )
         $errors[] = sprintf( T_('%s [%s] is not allowed for this tournament.'), T_('Rating Use Mode#tourney'),
            TournamentProperties::getRatingUseModeText($vars['rating_use_mode']) );
      else
         $tpr->setRatingUseMode( $vars['rating_use_mode'] );

      $tpr->UserRated = (bool)$vars['user_rated'];
      $tpr->setUserMinRating( read_rating( $vars['user_min_rating'], /*ignore%*/true ));
      $tpr->setUserMaxRating( read_rating( $vars['user_max_rating'], /*ignore%*/true ));

      if ( $allow_custom_round )
      {
         if ( (string)$vars['min_rat_start_round'] == '' )
            $old_vals['min_rat_start_round'] = NO_RATING;

         $max_start_round = $ttype->determineLimitMaxStartRound( $tpr->MaxParticipants );
         $new_value = $vars['max_start_round'];
         if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= 1 && $new_value <= $max_start_round )
            $tpr->MaxStartRound = limit( $new_value, 1, $max_start_round, 1 );
         else
            $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Max. Start Round'),
               build_range_text(1, $max_start_round) );

         $new_value = trim( $vars['min_rat_start_round'] );
         if ( (string)$new_value != '' )
         {
            $new_rating = ( is_numeric($new_value) ) ? (int)$new_value : read_rating($new_value);
            if ( is_valid_rating($new_rating) )
            {
               $tpr->setMinRatingStartRound( $new_rating );
               $vars['min_rat_start_round'] = (int)$new_rating;
            }
            else
               $errors[] = sprintf( T_('Invalid rating [%s] specified for %s.#tourney'),
                  $new_value, T_('Min. Rating Start Round'));
         }
         else
            $tpr->setMinRatingStartRound( NO_RATING );

         if ( $tpr->MinRatingStartRound != NO_RATING && $tpr->MaxStartRound == 1 )
            $errors[] = sprintf( T_('%s can not be set if %s is only %s.#tourney'),
               T_('Min. Rating Start Round'), T_('Max. Start Round'), 1 );
      }

      $new_value = $vars['min_games_finished'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >=0 )
         $tpr->UserMinGamesFinished = limit( $new_value, 0, 9999, 0 );
      else
         $errors[] = T_('Expecting positive number of finished games.');

      $new_value = $vars['min_games_rated'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >=0 )
         $tpr->UserMinGamesRated = limit( $new_value, 0, 9999, 0 );
      else
         $errors[] = T_('Expecting positive number of rated finished games.');

      $tpr->Notes = $vars['notes'];

      // reformat
      $vars['user_min_rating'] = echo_rating( $tpr->UserMinRating, false, 0, true, false );
      $vars['user_max_rating'] = echo_rating( $tpr->UserMaxRating, false, 0, true, false );

      // determine edits
      if ( $old_vals['reg_end_time'] != $tpr->RegisterEndTime ) $edits[] = T_('Registration end time#tourney');
      if ( $old_vals['min_participants'] != $tpr->MinParticipants ) $edits[] = T_('Participants');
      if ( $old_vals['max_participants'] != $tpr->MaxParticipants ) $edits[] = T_('Participants');
      if ( $allow_custom_round )
      {
         if ( $old_vals['max_start_round'] != $tpr->MaxStartRound ) $edits[] = T_('Max. Start Round');
         if ( $old_vals['min_rat_start_round'] != $tpr->MinRatingStartRound ) $edits[] = T_('Min. Rating Start Round');
      }
      if ( $old_vals['rating_use_mode'] != $tpr->RatingUseMode ) $edits[] = T_('Rating Use Mode#tourney');
      if ( $old_vals['user_rated'] != $tpr->UserRated ) $edits[] = T_('User Rating');
      if ( $old_vals['user_min_rating'] != $tpr->UserMinRating ) $edits[] = T_('User Rating');
      if ( $old_vals['user_max_rating'] != $tpr->UserMaxRating ) $edits[] = T_('User Rating');
      if ( $old_vals['min_games_finished'] != $tpr->UserMinGamesFinished ) $edits[] = T_('User-Games');
      if ( $old_vals['min_games_rated'] != $tpr->UserMinGamesRated ) $edits[] = T_('User-Games');
      if ( $old_vals['notes'] != $tpr->Notes ) $edits[] = T_('Notes');
   }

   if ( ($tpr->MinParticipants + $tpr->MaxParticipants == 0) && ($tpr->MinParticipants > $tpr->MaxParticipants) )
   {
      swap( $tpr->MinParticipants, $tpr->MaxParticipants );
      swap( $vars['min_participants'], $vars['max_participants'] );
   }

   if ( $tpr->UserMinRating > $tpr->UserMaxRating )
   {
      swap( $tpr->UserMinRating, $tpr->UserMaxRating );
      swap( $vars['user_min_rating'], $vars['user_max_rating'] );
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

/*! \brief Returns array with notes about tournament properties. */
function build_properties_notes( $t_limits )
{
   $notes = array();
   $notes[] = T_('To disable restrictions, you may use 0-value in (some) fields.');
   $notes[] = null; // empty line

   $narr = array( T_('Rating Use Mode#tourney') . ':' );
   $arr_rating_use_modes = TournamentHelper::get_restricted_RatingUseModeTexts($t_limits, /*short*/false);
   foreach ( $arr_rating_use_modes as $usemode => $descr )
      $narr[] = TournamentProperties::getRatingUseModeText($usemode) . ' = ' . $descr;;
   $notes[] = $narr;

   return $notes;
}//build_properties_notes

?>

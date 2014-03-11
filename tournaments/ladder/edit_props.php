<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir('../..');
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderPropsEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.ladder.edit_props');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.edit_props');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.ladder.edit_props');

   $page = "edit_props.php";

/* Actual REQUEST calls used:
     tid=                 : edit ladder properties
     tlp_preview&tid=     : preview for properties-save
     tlp_save&tid=        : update (replace) properties in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.ladder.edit_props.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   $t_limits = $ttype->getTournamentLimits();

   // create/edit allowed?
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.ladder.edit_props.edit_tournament($tid,$my_id)");

   $tl_props = TournamentCache::load_cache_tournament_ladder_props( 'Tournament.ladder.edit_props', $tid );

   // init
   $errors = $tstatus->check_edit_status( TournamentLadderProps::get_edit_tournament_status() );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check + parse edit-form
   $old_tl_props = clone $tl_props;
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tl_props, $t_limits );
   $errors = array_merge( $errors, $input_errors, $tl_props->check_properties() );

   // save properties-object with values from edit-form
   if ( @$_REQUEST['tlp_save'] && !@$_REQUEST['tlp_preview'] && count($edits) && count($errors) == 0 )
   {
      ta_begin();
      {//HOT-section to update TournamentLadderProps
         $tl_props->update();
         TournamentLadder::delete_cache_tournament_ladder( "Tournament.ladder.edit_props($tid)", $tid );
         TournamentLogHelper::log_change_tournament_ladder_props( $tid, $allow_edit_tourney, $edits, $old_tl_props, $tl_props );
      }
      ta_end();
      jump_to("tournaments/ladder/edit_props.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Ladder Properties saved!')) );
   }


   // ---------- Tournament-Ladder-Properties EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_POST );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   if ( $tl_props->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tl_props->Lastchanged, $tl_props->ChangedBy) ));
   $tform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   // challenge range
   $tform->add_row( array(
         'DESCRIPTION', T_('Challenge Range Absolute#T_ladder'),
         'TEXTINPUT',   'chall_range_abs', 5, 5, $vars['chall_range_abs'], ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Challenge Range Relative#T_ladder'),
         'TEXTINPUT',   'chall_range_rel', 3, 3, $vars['chall_range_rel'],
         'TEXT',        MINI_SPACING . '%', ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Challenge Range Rating#T_ladder'),
         'TEXTINPUT',   'chall_range_rat', 6, 6, $vars['chall_range_rat'],
         'TEXT',        MINI_SPACING . '('.T_('clear to deactivate').')', ));

   // challenge rematch
   $tform->add_row( array(
         'DESCRIPTION', T_('Challenge Rematch Wait-Time#T_ladder'),
         'TEXTINPUT',   'chall_rematch', 5, 5, $vars['chall_rematch'],
         'TEXT',        MINI_SPACING . T_('hours'), ));
   $tform->add_empty_row();

   // max. defenses
   $tform->add_row( array(
         'DESCRIPTION', T_('Max. Defenses#T_ladder'),
         'TEXT',        T_('Start Rank#T_ladder') . ': ',
         'TEXTINPUT',   'max_def_start1', 3, 3, $vars['max_def_start1'],
         'TEXT',        MED_SPACING . T_('Max. Defenses#T_ladder') . ': ',
         'TEXTINPUT',   'max_def1', 3, 3, $vars['max_def1'],
         'TEXT',        sprintf( T_('(Group #%s)#T_ladder'), 1) ));
   $tform->add_row( array(
         'TAB',
         'TEXT',        T_('Start Rank#T_ladder') . ': ',
         'TEXTINPUT',   'max_def_start2', 3, 3, $vars['max_def_start2'],
         'TEXT',        MED_SPACING . T_('Max. Defenses#T_ladder') . ': ',
         'TEXTINPUT',   'max_def2', 3, 3, $vars['max_def2'],
         'TEXT',        sprintf( T_('(Group #%s)#T_ladder'), 2) ));
   $tform->add_row( array(
         'TAB',
         'TEXT',        T_('For remaining ranks restrict max. defenses to#T_ladder') . ': ',
         'TEXTINPUT',   'max_def', 3, 3, $vars['max_def'],
         'TEXT',        $t_limits->getLimitRangeTextAdmin(TLIMITS_TL_MAX_DF), ));

   // max. challenges
   $tform->add_row( array(
         'DESCRIPTION', T_('Max. outgoing Challenges#T_ladder'),
         'TEXTINPUT',   'max_chall', 5, 5, $vars['max_chall'],
         'TEXT',        $t_limits->getLimitRangeTextAdmin(TLIMITS_TL_MAX_CH), ));
   $tform->add_empty_row();

   // determine challenger
   $arr_determine_challenger = array(
         TLP_DETERMINE_CHALL_GEND   => sptext(T_('at game-end#T_ladder'), 2),
         TLP_DETERMINE_CHALL_GSTART => sptext(T_('at game-start#T_ladder')),
      );
   $tform->add_row( array(
         'DESCRIPTION', T_('Determine Challenger#T_ladder'),
         'RADIOBUTTONS', 'det_chall', $arr_determine_challenger, $vars['det_chall'], ));

   // game-end
   $tform->add_row( array(
         'DESCRIPTION', T_('Game End handling'),
         'SELECTBOX',   'gend_normal', 1, TournamentLadderProps::getGameEndText(null, TGE_NORMAL),
                        $vars['gend_normal'], false,
         'TEXT',        sprintf( '(%s)', T_('Challenger wins by score or resignation#T_ladder')), ));
   $tform->add_row( array(
         'TAB',
         'SELECTBOX',   'gend_timeout_w', 1, TournamentLadderProps::getGameEndText(null, TGE_TIMEOUT_WIN),
                        $vars['gend_timeout_w'], false,
         'TEXT',        sprintf( '(%s)', T_('Challenger wins by timeout#T_ladder')), ));
   $tform->add_row( array(
         'TAB',
         'SELECTBOX',   'gend_timeout_l', 1, TournamentLadderProps::getGameEndText(null, TGE_TIMEOUT_LOSS),
                        $vars['gend_timeout_l'], false,
         'TEXT',        sprintf( '(%s)', T_('Challenger loses by timeout#T_ladder')), ));
   $tform->add_row( array(
         'TAB',
         'SELECTBOX',   'gend_jigo', 1, TournamentLadderProps::getGameEndText(null, TGE_JIGO),
                        $vars['gend_jigo'], false,
         'TEXT',        sprintf( '(%s)', T_('Jigo')), ));
   $tform->add_empty_row();

   // user-join-order
   $tform->add_row( array(
         'DESCRIPTION', T_('User Join Order#T_ladder'),
         'SELECTBOX',   'join_order', 1, TournamentLadderProps::getUserJoinOrderText(),
                        $vars['join_order'], false,
         'TEXT',        T_('order determines ladder-position for newly joined user#T_ladder'), ));
   $tform->add_empty_row();

   // user-absence
   $tform->add_row( array(
         'DESCRIPTION', T_('User Absence handling#T_ladder'),
         'TEXTINPUT',   'uabs_days', 4, 4, $vars['uabs_days'],
         'TEXT',        T_('[days] user can be absent without being removed from ladder'), ));
   $tform->add_empty_row();

   // rank-archiving-period
   $tform->add_row( array(
         'DESCRIPTION', T_('Rank Archiving Period length#T_ladder'),
         'TEXTINPUT',   'rankplen', 4, 4, $vars['rankplen'],
         'TEXT',        T_('months'), ));
   $tform->add_empty_row();

   // crowning king
   $tform->add_row( array(
         'DESCRIPTION', T_('Crown King#T_ladder'),
         'TEXTINPUT',   'crownking', 8, 8, $vars['crownking'],
         'TEXT',
            sprintf( '[%s], %s',
               TimeFormat::_echo_time( $tl_props->CrownKingHours, 24, TIMEFMT_SHORT|TIMEFMT_ZERO, 0 ),
               T_('time in hours top rank must be kept, format: "99d 99h"#T_ladder') ), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Crown King Check time#T_ladder'),
         'TEXTINPUT',   'crownstart', 20, 20, $vars['crownstart'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE )), ));
   $tform->add_empty_row();

   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Notes'),
         'TEXT',        T_('Use value of "0" to disable features!'), ));


   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tlp_save', T_('Save Ladder Properties'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tlp_preview', T_('Preview'),
      ));


   $title = T_('Tournament Ladder Properties Editor');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   $tt_notes = $tl_props->build_notes_props();
   echo "<p></p>\n";
   section( 'preview', T_('Preview Properties') );
   echo_notes( 'ttprops', $tt_notes[0], $tt_notes[1], false );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tlp, $t_limits )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tlp_save'] || @$_REQUEST['tlp_preview'] );

   // read from props or set defaults
   $vars = array(
      'chall_range_abs' => $tlp->ChallengeRangeAbsolute,
      'chall_range_rel' => $tlp->ChallengeRangeRelative,
      'chall_range_rat' => TournamentLadderProps::formatChallengeRangeRating($tlp->ChallengeRangeRating),
      'chall_rematch'   => $tlp->ChallengeRematchWaitHours,
      'max_def'         => $tlp->MaxDefenses,
      'max_def1'        => $tlp->MaxDefenses1,
      'max_def2'        => $tlp->MaxDefenses2,
      'max_def_start1'  => $tlp->MaxDefensesStart1,
      'max_def_start2'  => $tlp->MaxDefensesStart2,
      'max_chall'       => $tlp->MaxChallenges,
      'det_chall'       => $tlp->DetermineChallenger,
      'gend_normal'     => $tlp->GameEndNormal,
      'gend_jigo'       => $tlp->GameEndJigo,
      'gend_timeout_w'  => $tlp->GameEndTimeoutWin,
      'gend_timeout_l'  => $tlp->GameEndTimeoutLoss,
      'join_order'      => $tlp->UserJoinOrder,
      'uabs_days'       => $tlp->UserAbsenceDays,
      'rankplen'        => $tlp->RankPeriodLength,
      'crownking'       => $tlp->CrownKingHours,
      'crownstart'      => formatDate($tlp->CrownKingStart),
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if ( $is_posted )
   {
      $old_vals['chall_range_rat'] = $tlp->ChallengeRangeRating;
      $old_vals['crownstart'] = $tlp->CrownKingStart;

      $new_value = $vars['chall_range_abs'];
      if ( TournamentUtils::isNumberOrEmpty($new_value, true)
            && $new_value >= -1 && $new_value <= TLADDER_MAX_CHRNG_ABS )
         $tlp->ChallengeRangeAbsolute = limit( $new_value, -1, TLADDER_MAX_CHRNG_ABS, 10 );
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Challenge Range Absolute#T_ladder'),
            build_range_text(-1, TLADDER_MAX_CHRNG_ABS) );

      $new_value = $vars['chall_range_rel'];
      if ( TournamentUtils::isNumberOrEmpty($new_value, true) )
         $tlp->ChallengeRangeRelative = (int)$new_value;
      else
         $errors[] = sprintf( T_('Expecting number for relative challenge range in percentage range %s.#T_ladder'),
            build_range_text(0, 100) );

      $new_value = trim($vars['chall_range_rat']);
      $is_error = false;
      if ( (string)$new_value == '' )
         $tlp->ChallengeRangeRating = TLADDER_CHRNG_RATING_UNUSED;
      elseif ( TournamentUtils::isNumberOrEmpty($new_value, true) )
         $tlp->ChallengeRangeRating = (int)$new_value;
      else
      {
         $is_error = true;
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Challenge Range Rating#T_ladder'),
            build_range_text( -TLADDER_MAX_CHRNG_RATING, TLADDER_MAX_CHRNG_RATING ) );
      }
      if ( !$is_error ) // reformat
         $vars['chall_range_rat'] = TournamentLadderProps::formatChallengeRangeRating($tlp->ChallengeRangeRating);

      $new_value = $vars['chall_rematch'];
      if ( is_numeric($new_value) && $new_value >= 0 && $new_value <= TLADDER_MAX_WAIT_REMATCH )
         $tlp->ChallengeRematchWaitHours = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for rematch waiting time in hours range %s.#T_ladder'),
            build_range_text(0, TLADDER_MAX_WAIT_REMATCH) );


      $new_value = $vars['max_def'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value > 0 && $new_value <= TLADDER_MAX_DEFENSES )
      {
         $limit_errors = $t_limits->checkLadder_MaxDefenses( $new_value, $tlp->MaxDefenses, null );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $tlp->MaxDefenses = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Max. Defenses#T_ladder'),
            // check for general MAX, but show specific max
            build_range_text(1, $t_limits->getMaxLimit(TLIMITS_TL_MAX_DF) ));

      $new_value = $vars['max_def1'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= 0 && $new_value <= TLADDER_MAX_DEFENSES )
      {
         $limit_errors = $t_limits->checkLadder_MaxDefenses( $new_value, $tlp->MaxDefenses1, sprintf( T_('of group #%s#T_ladder'), 1) );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $tlp->MaxDefenses1 = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses of group #%s in range %s.#T_ladder'), 1,
            $t_limits->getLimitRangeText(TLIMITS_TL_MAX_DF) ); // check for general MAX, but show specific max

      $new_value = $vars['max_def2'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= 0 && $new_value <= TLADDER_MAX_DEFENSES )
      {
         $limit_errors = $t_limits->checkLadder_MaxDefenses( $new_value, $tlp->MaxDefenses2, sprintf( T_('of group #%s#T_ladder'), 2) );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $tlp->MaxDefenses2 = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses of group #%s in range %s.#T_ladder'), 2,
            $t_limits->getLimitRangeText(TLIMITS_TL_MAX_DF) ); // check for general MAX, but show specific max

      $new_value = $vars['max_def_start1'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefensesStart1 = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses start-rank of group #%s#T_ladder'), 1 );

      $new_value = $vars['max_def_start2'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->MaxDefensesStart2 = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for max. defenses start-rank of group #%s#T_ladder'), 2 );


      $new_value = $vars['max_chall'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) )
      {
         $limit_errors = $t_limits->checkLadder_MaxChallenges( $new_value, $tlp->MaxChallenges );
         if ( count($limit_errors) )
            $errors = array_merge( $errors, $limit_errors );
         else
            $tlp->MaxChallenges = $new_value;
      }
      else
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Max. outgoing Challenges#T_ladder'),
            $t_limits->getLimitRangeText(TLIMITS_TL_MAX_CH) ); // check for general MAX, but show specific max


      $new_value = $vars['uabs_days'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->UserAbsenceDays = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for user absence in range %s days#T_ladder'),
            build_range_text(0, 255) );

      $new_value = $vars['rankplen'];
      if ( TournamentUtils::isNumberOrEmpty($new_value) )
         $tlp->RankPeriodLength = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for rank archiving period length in range %s months#T_ladder'),
            build_range_text(1, 255) );

      $new_value = $vars['crownking'];
      $parsed_hours = TimeFormat::parse_time_days_hours( $new_value );
      if ( !is_null($parsed_hours) )
         $tlp->CrownKingHours = $parsed_hours;
      else
         $errors[] = T_('Expecting time for crowning king.#T_ladder');

      $parsed_value = parseDate( T_('Crown King Check time#T_ladder'), $vars['crownstart'] );
      if ( is_numeric($parsed_value) )
      {
         $tlp->CrownKingStart = $parsed_value;
         $vars['crownstart'] = formatDate($tlp->CrownKingStart);
      }
      else
         $errors[] = $parsed_value;


      $tlp->setDetermineChallenger( $vars['det_chall'] );
      $tlp->setGameEndNormal( $vars['gend_normal'] );
      $tlp->setGameEndTimeoutWin( $vars['gend_timeout_w'] );
      $tlp->setGameEndTimeoutLoss( $vars['gend_timeout_l'] );
      $tlp->setGameEndJigo( $vars['gend_jigo'] );

      $tlp->setUserJoinOrder( $vars['join_order'] );

      // determine edits
      if ( $old_vals['chall_range_abs'] != $tlp->ChallengeRangeAbsolute ) $edits[] = T_('Challenge Range#T_ladder');
      if ( $old_vals['chall_range_rel'] != $tlp->ChallengeRangeRelative ) $edits[] = T_('Challenge Range#T_ladder');
      if ( $old_vals['chall_range_rat'] != $tlp->ChallengeRangeRating ) $edits[] = T_('Challenge Range#T_ladder');
      if ( $old_vals['chall_rematch'] != $tlp->ChallengeRematchWaitHours ) $edits[] = T_('Challenge Rematch Wait#T_ladder');
      if ( $old_vals['max_def'] != $tlp->MaxDefenses ) $edits[] = T_('Max. Defenses#T_ladder');
      if ( $old_vals['max_def1'] != $tlp->MaxDefenses1 ) $edits[] = T_('Max. Defenses#T_ladder');
      if ( $old_vals['max_def2'] != $tlp->MaxDefenses2 ) $edits[] = T_('Max. Defenses#T_ladder');
      if ( $old_vals['max_def_start1'] != $tlp->MaxDefensesStart1 ) $edits[] = T_('Max. Defenses#T_ladder');
      if ( $old_vals['max_def_start2'] != $tlp->MaxDefensesStart2 ) $edits[] = T_('Max. Defenses#T_ladder');
      if ( $old_vals['max_chall'] != $tlp->MaxChallenges ) $edits[] = T_('Max. Challenges#T_ladder');
      if ( $old_vals['det_chall'] != $tlp->DetermineChallenger ) $edits[] = T_('Determine Challenger#T_ladder');
      if ( $old_vals['gend_normal'] != $tlp->GameEndNormal ) $edits[] = T_('Game End');
      if ( $old_vals['gend_timeout_w'] != $tlp->GameEndTimeoutWin ) $edits[] = T_('Game End');
      if ( $old_vals['gend_timeout_l'] != $tlp->GameEndTimeoutLoss ) $edits[] = T_('Game End');
      if ( $old_vals['gend_jigo'] != $tlp->GameEndJigo ) $edits[] = T_('Game End');
      if ( $old_vals['join_order'] != $tlp->UserJoinOrder ) $edits[] = T_('User Join Order#T_ladder');
      if ( $old_vals['uabs_days'] != $tlp->UserAbsenceDays ) $edits[] = T_('User Absence#T_ladder');
      if ( $old_vals['rankplen'] != $tlp->RankPeriodLength ) $edits[] = T_('Rank Archiving Period length#T_ladder');
      if ( $old_vals['crownking'] != $tlp->CrownKingHours ) $edits[] = T_('Crown King#T_ladder');
      if ( $old_vals['crownstart'] != $tlp->CrownKingStart ) $edits[] = T_('Crown King Check time#T_ladder');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>

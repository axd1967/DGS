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
require_once 'include/error_codes.php';
require_once 'include/form_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/rating.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_result.php';
require_once 'tournaments/include/tournament_result_control.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_status.php';

$GLOBALS['ThePage'] = new Page('TournamentEditResults');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_results');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_results');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_results');

   $page = "edit_results.php";

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                                    : show user-input to add new TR
     tid=&trid=                              : show existing TR (given by TournamentResult.ID = trid)

     tr_show_user=&user=&user_round=         : show tournament-info about user; user='123' (uid) or '=abc' (handle)
     tr_use_info=&user=                      : fill-in info-data overwriting new/current tournament-result-fields
     tr_create_pw=&user_round=               : create TRs from pool-winners (need confirm; for round-robins only)
     tr_create_pw_confirm=&user_round=       : create TRs from pool-winners (confirmed)
     tr_preview&tid=[&trid=]                 : preview new [or update] TR
     tr_save&tid=[&trid=]                    : add new [/update] TR in database
     tr_del&tid=&trid=                       : remove TR (need confirm)
     tr_del_confirm=1&tid=&trid=             : remove TR (confirmed)
     tr_cancel&tid=&trid=&user=&user_round=  : cancel operation (update/delete)
*/

   $tid = (int) @$_REQUEST['tid'];
   $trid = (int) @$_REQUEST['trid'];

   if ( @$_REQUEST['tr_cancel'] ) // cancel delete or edit
      jump_to("tournaments/edit_results.php?tid=$tid".URI_AMP."trid=$trid".URI_AMP
         . "user=".urlencode(@$_REQUEST['user']).URI_AMP."user_round=".urlencode(@$_REQUEST['user_round']));

   $tourney = TournamentCache::load_cache_tournament( "Tournament.edit_results.find_tournament($my_id)", $tid );
   $tstatus = new TournamentStatus( $tourney );

   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_results($tid,$my_id)");

   $errors = $tstatus->check_edit_status( array( TOURNEY_STATUS_PLAY ) );
   if ( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();


   // existing entry ?
   $tresult = ( $trid > 0 )
      ? TournamentResult::load_tournament_result( 'Tournament.edit_results', $trid, $tid )
      : null;
   if ( is_null($tresult) )
      $tresult = new TournamentResult( 0, $tid );
   $is_new = ($tresult->ID == 0);
   $is_trr = ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN );

   // init
   $actset_get_tinfo = ( @$_REQUEST['tr_show_user'] || @$_REQUEST['tr_use_info'] || @$_REQUEST['tr_save'] );
   $actset_new_tresult = ( $actset_get_tinfo || @$_REQUEST['tr_preview'] );
   $actset_check_tresult = ( @$_REQUEST['tr_use_info'] || @$_REQUEST['tr_preview'] || @$_REQUEST['tr_save'] );
   $actset_show_tr_form = ( $actset_check_tresult || @$_REQUEST['tr_del'] );
   $actset_create_pw = ( $is_trr && @$_REQUEST['tr_create_pw'] || @$_REQUEST['tr_create_pw_confirm'] );

   // check + load user + user-tournament-info (participant/ladder/pool)
   $user_vars = array( // defaults
         'user' => trim( get_request_arg('user',
               ( $actset_get_tinfo || $is_new ? '' : $tresult->uid ) ) ),
         'user_round' => trim( get_request_arg('user_round',
               ( $actset_get_tinfo || $actset_create_pw ? '' : ( $is_new ? $tourney->Rounds : $tresult->Round ) ) ) ),
      );
   $user = load_user_info( $errors, $user_vars, $is_new );
   list( $warnings, $tp, $tladder, $tpool, $info_str ) = load_tournament_info( $tourney, $user, $user_vars, $is_new );

   // check + parse edit-form
   $old_tresult = clone $tresult;
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tresult, $tourney, $user_vars );
   $errors = array_merge( $errors, $input_errors );
   $user_round = (int)$user_vars['user_round'];

   // check + fill in TResult.uid/rid/Round
   if ( !is_null($user) && $actset_check_tresult )
   {
      if ( $tresult->uid != $user->ID )
         $errors[] = sprintf( T_('Specified uid [%s] for user [%s] must match the new/stored result uid [%s].#tourney'),
            $user->ID, $user->Handle, $tresult->uid );
      if ( !is_null($tp) && $tresult->rid != $tp->ID )
         $errors[] = sprintf( T_('Tournament participant rid [%s] for specified user [%s] must match the new/stored result rid [%s].#tourney'),
            $tp->ID, $user->Handle, $tresult->rid );

      $errors = array_merge( $errors,
         TournamentHelper::check_tournament_result($tourney, $tresult) );
   }

   // ---------- Process inputs into actions ------------------------------------

   if ( !$is_new && @$_REQUEST['tr_del_confirm'] && count($errors) == 0 ) // delete result confirmed
   {
      ta_begin();
      {//HOT-section to delete tournament-result
         $success = $tresult->delete();
         if ( $success )
            TournamentLogHelper::log_delete_tournament_result( $tid, $allow_edit_tourney, $tresult );
      }
      ta_end();

      if ( $success )
         jump_to("tournaments/edit_results.php?tid=$tid".URI_AMP."sysmsg=".urlencode(T_('Tournament result deleted!')));
   }
   elseif ( @$_REQUEST['tr_save'] && count($errors) == 0 ) // add + update result
   {
      ta_begin();
      {//HOT-section to update tournament-result
         $success = $tresult->persist();
         if ( $success )
            TournamentLogHelper::log_change_tournament_result( $tid, $allow_edit_tourney,
               $is_new, $edits, $old_tresult, $tresult );
      }
      ta_end();

      if ( $success )
         jump_to("tournaments/edit_results.php?tid=$tid".URI_AMP."trid={$tresult->ID}".URI_AMP
            . "sysmsg=" . urlencode(T_('Tournament result saved!')) );
   }
   elseif ( $is_trr && @$_REQUEST['tr_create_pw_confirm'] && count($errors) == 0 ) // create results from pool-winners
   {
      $add_count = TournamentResultControl::create_tournament_result_pool_winners( $tid, $user_round, $allow_edit_tourney );
      if ( $add_count > 0 )
      {
         $sysmsg = urlencode(sprintf( T_('Created %s tournament result entries from pool-winners of round %s!'),
            $add_count, $user_round ));
         jump_to("tournaments/list_results.php?tid=$tid".URI_AMP."sysmsg=$sysmsg");
      }
   }


   // ---------- Tournament-Result EDIT form ------------------------------------

   $trform = new Form( 'tournamenteditresult', $page, FORM_POST );
   $trform->add_hidden( 'tid', $tid );
   $trform->add_hidden( 'trid', $tresult->ID );

   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   $trform->add_row( array(
         'DESCRIPTION', T_('Tournament Round'),
         'TEXT',        $tourney->formatRound(), ));

   $trform->add_row( array( 'HR' ));
   $row_arr = array(
         'DESCRIPTION', T_('Result Round#tourney'),
         'TEXTINPUT',   'user_round', 3, 3, $user_vars['user_round'], );
   if ( $is_trr )
      array_push( $row_arr,
         'SUBMITBUTTON', 'tr_create_pw', T_('Create tournament results from pool-winners') );
   $trform->add_row( $row_arr );
   $trform->add_row( array(
         'DESCRIPTION', T_('Result User#tourney'),
         'TEXTINPUT',   'user', 16, 16, $user_vars['user'],
         'SUBMITBUTTON', 'tr_show_user', build_show_user_text($tourney),
         'TEXT', MED_SPACING,
         'SUBMITBUTTON', 'tr_use_info', T_('Use info data#tourney'), ));
   $trform->add_row( array(
         'TAB',
         'TEXT', T_('Syntax: uid, \'Userid\' or \'=Userid\', e.g. \'123\', \'abc\' or \'=abc\'#tourney'), ));

   // show tournament-info about user
   if ( $info_str )
      $trform->add_row( array( 'CELL', 2, '', 'TEXT', $info_str, ));

   if ( count($errors) )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
   }
   if ( count($warnings) )
   {
      $trform->add_row( array(
            'DESCRIPTION', T_('Warning'),
            'TEXT', buildWarnListString( T_('There are some warnings'), $warnings ) ));
   }

   $trform->add_row( array( 'HR' ));
   if ( !is_null($user) && ( !$is_new || $actset_show_tr_form ) )
   {
      $arr_result_types = TournamentResult::getTypeText();
      $trform->add_row( array(
            'DESCRIPTION', T_('Tournament Result ID'),
            'TEXT',        ( $is_new ? T_('New entry#tourney') : $tresult->ID ), ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Result Type#tourney'),
            'SELECTBOX',   'type', 1, $arr_result_types, $vars['type'], false, ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Result uid#tresult'),
            'TEXTINPUT',   'uid', 8, 8, $vars['uid'], ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Result rid#tourney'),
            'TEXTINPUT',   'rid', 8, 8, $vars['rid'],
            'TEXT',        ' = ' . T_('Tournament Participant registration-ID'), ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Result Round#tourney'),
            'TEXTINPUT',   'round', 3, 3, $vars['round'], ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Result Rating#tourney'),
            'TEXTINPUT',   'rating', 16, 16, $vars['rating'],
            'TEXT',        ( is_valid_rating($vars['rating']) ? ' = ' . echo_rating($vars['rating'], true) : '' ), ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Start time#tourney'),
            'TEXTINPUT',   'start_time', 20, 20, $vars['start_time'],
            'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s], local timezone)'), FMT_PARSE_DATE2 )), ));
      $trform->add_row( array(
            'DESCRIPTION', T_('End time#tourney'),
            'TEXTINPUT',   'end_time', 20, 20, $vars['end_time'],
            'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s], local timezone)'), FMT_PARSE_DATE2 )), ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Result#tresult'),
            'TEXTINPUT',   'result', 10, 10, $vars['result'], ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Rank#tresult'),
            'TEXTINPUT',   'rank', 8, 8, $vars['rank'], ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Comment (public)#tourney'),
            'TEXTINPUT',   'comment', 70, 128, $vars['comment'], ));
      $trform->add_row( array(
            'DESCRIPTION', T_('Note (only for directors)#tourney'),
            'TEXTAREA',    'note', 70, 2, $vars['note'], ));
   }

   if ( $is_trr && @$_REQUEST['tr_create_pw'] && count($errors) == 0 )
   {
      echo_create_pool_winners_form( $trform, $tid, $user_round );
   }
   else if ( !is_null($user) )
   {
      if ( @$_REQUEST['tr_del'] )
      {
         $trform->add_row( array(
               'TAB',
               'TEXT', span('TWarning', T_('Please confirm deletion of the tournament result!')), ));
         $trform->add_row( array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'tr_del_confirm', T_('Confirm deletion of result#tourney'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tr_cancel', T_('Cancel') ));
      }
      elseif ( !$is_new || $actset_check_tresult )
      {
         $trform->add_row( array(
               'DESCRIPTION', T_('Unsaved edits'),
               'TEXT',        span('TWarning', wordwrap( implode(', ', $edits), 80, "<br>\n"), '[%s]'), ));
         $trform->add_row( array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'tr_save', ($is_new ? T_('Add tournament result') : T_('Save tournament result')),
               'SUBMITBUTTON', 'tr_preview', T_('Preview'),
               'TEXT', SMALL_SPACING.SMALL_SPACING,
               'SUBMITBUTTON', 'tr_del', T_('Delete tournament result'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'tr_cancel', T_('Cancel') ));
      }
   }


   $title = sprintf( T_('Tournament Results Editor for [%s]'), $tourney->Title );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>", $title, "</h3>\n";

   $trform->echo_string();

   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('All tournament results')] = "tournaments/list_results.php?tid=$tid";
   $tourney->build_data_link( $menu_array );
   $menu_array[T_('Edit results#tourney')] =
         array( 'url' => "tournaments/edit_results.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


function load_user_info( &$errors, &$uvars, $is_new )
{
   global $actset_new_tresult;
   $user = null;

   if ( ( $is_new && count($errors) == 0 && $actset_new_tresult ) || !$is_new )
   {
      $new_value = $uvars['user'];
      if ( (string)$new_value == '' || (is_numeric($new_value) && $new_value <= GUESTS_ID_MAX) )
         $errors[] = ErrorCode::get_error_text('invalid_user');
      elseif ( is_numeric($new_value) && $new_value > GUESTS_ID_MAX )
         $user = User::load_user( (int)$new_value );
      else
      {
         $chk_value = ( substr($new_value, 0, 1) == '=' ) ? substr($new_value,1) : $new_value;
         if ( !preg_match("/^\\d+$/", $chk_value) ) // '='-prefix only needed for number-only user-ids
            $uvars['user'] = $chk_value;
         $user = User::load_user_by_handle( $chk_value );
      }
      if ( is_null($user) && count($errors) == 0 )
         $errors[] = ErrorCode::get_error_text('unknown_user');
   }

   return $user;
}//load_user_info

function load_tournament_info( $tourney, $user, &$uvars, $is_new )
{
   global $actset_new_tresult, $actset_create_pw;
   $warnings = array();
   $tp = $tladder = $tpool = null;
   $info_str = '';

   // parse URL-vars for user-round
   $new_value = $uvars['user_round'];
   if ( ( $is_new && ($actset_new_tresult || $actset_create_pw) && is_numeric($new_value) && $new_value >= 1 && $new_value <= $tourney->Rounds )
         || !$is_new )
      $uvars['user_round'] = (int)$new_value;
   else
      $uvars['user_round'] = $tourney->CurrentRound; // default and fallback if value has error

   // load T-info about user: TournamentParticipant, TournamentLadder (for TL), TournamentPool (for TRR)
   if ( !is_null($user) )
   {
      $tid = $tourney->ID;
      $uid = $user->ID;

      $tp = TournamentCache::load_cache_tournament_participant( 'Tournament.edit_results.load_tournament_info',
         $tid, $uid );
      if ( is_null($tp) )
         $warnings[] = sprintf( T_('User [%s] is not participating in this tournament.'), $user->Handle );
      else
      {
         if ( $tourney->Type == TOURNEY_TYPE_LADDER )
         {
            $tladder = TournamentLadder::load_tournament_ladder_by_user( $tid, 0, $tp->ID );
            if ( is_null($tladder) )
               $warnings[] = sprintf( T_('No ladder entry found for user [%s].#tourney'), $user->Handle );
         }
         elseif ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         {
            $user_round = (int)$uvars['user_round'];
            $tpool = TournamentPool::load_tournament_pool_user( $tid, $user_round, $uid );
            if ( is_null($tpool) )
               $warnings[] = sprintf( T_('No pool entry found for round #%s and user [%s].#tourney'),
                  $user_round, $user->Handle );
         }
      }

      // build info-array with T-infos about user
      $info = array();
      if ( !is_null($user) )
         $info[] = echo_image_info( "userinfo.php?uid=$uid", T_('User') )
            . MINI_SPACING
            . span('bold', T_('User info'), '%s: ')
               . $user->user_reference() . ', ' . echo_rating($user->Rating, true, $uid)
               . sprintf(', %s %1.2f', T_('ELO#rating'),$user->Rating );
      if ( !is_null($tp) )
         $info[] = str_replace("\n", "<br>\n", $tp->build_result_info() );
      if ( !is_null($tladder) )
         $info[] = str_replace("\n", "<br>\n", $tladder->build_result_info() );
      if ( !is_null($tpool) )
         $info[] = str_replace("\n", "<br>\n", $tpool->build_result_info() );
      $info_str = ( count($info) ) ? "<ul><li>" . implode("<br><br></li>\n<li>", $info) . "</ul>\n" : '';
   }

   return array( $warnings, $tp, $tladder, $tpool, $info_str );
}//load_tournament_info

function build_show_user_text( $tourney )
{
   if ( $tourney->Type == TOURNEY_TYPE_LADDER )
      return T_('Show ladder info#tourney');
   else if ( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
      return T_('Show pool info#tourney');
   else
      error('invalid_args', "edit_results.build_show_user_text({$tourney->ID})");
}

// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tresult, $tourney, $uvars )
{
   global $actset_show_tr_form, $is_new, $user, $tp, $tladder, $tpool;

   $edits = array();
   $errors = array();

   $is_posted = ( !$is_new || $actset_show_tr_form );

   // read from props or set defaults
   $vars = array(
      'type'         => $tresult->Type,
      'uid'          => $tresult->uid,
      'rid'          => $tresult->rid,
      'rating'       => $tresult->Rating,
      'round'        => $tresult->Round,
      'start_time'   => formatDate( $tresult->StartTime, '', DATE_FMT_QUICK ),
      'end_time'     => formatDate( $tresult->EndTime, '', DATE_FMT_QUICK ),
      'result'       => $tresult->Result,
      'rank'         => $tresult->Rank,
      'comment'      => $tresult->Comment,
      'note'         => $tresult->Note,
   );
   if ( $is_new ) // for new entry clear all values as default
   {
      foreach ( $vars as $key => $val )
         $vars[$key] = '';
   }

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // overwrite fields with T-info-date (user/participant/ladder/pool)
   if ( @$_REQUEST['tr_use_info'] && !is_null($user) )
      fill_tournament_info( $vars, $uvars, $tourney, $user, $tp, $tladder, $tpool );


   if ( $is_posted && !is_null($user) )
   {
      if ( (string)$vars['rating'] == '' )
         $old_vals['rating'] = NO_RATING;
      $old_vals['start_time'] = $tresult->StartTime;
      $old_vals['end_time'] = $tresult->EndTime;

      $new_value = $vars['type'];
      if ( !is_numeric($new_value) || $new_value < 1 || $new_value > CHECK_MAX_TRESULTYPE )
         $errors[] = sprintf( T_('Used unknown result type [%s]#tourney'), $new_value );
      else
         $tresult->Type = (int)$new_value;

      $new_value = $vars['uid'];
      if ( !is_numeric($new_value) || $new_value <= GUESTS_ID_MAX )
         $errors[] = T_('Invalid value for result uid.#tourney');
      else
         $tresult->uid = (int)$new_value;

      $new_value = $vars['rid'];
      if ( !is_numeric($new_value) || $new_value <= 0 )
         $errors[] = T_('Invalid value for result participant id.#tourney');
      else
         $tresult->rid = (int)$new_value;

      $new_value = $vars['round'];
      if ( !is_numeric($new_value) || $new_value <= 0 || $new_value > $tourney->Rounds )
         $errors[] = sprintf( T_('Expecting number for %s in range %s.'), T_('Result Round#tourney'),
            build_range_text(1, $tourney->Rounds) );
      else
         $tresult->Round = (int)$new_value;

      $new_value = trim($vars['rating']);
      if ( (string)$new_value != '' )
      {
         $rating = convert_to_rating( $new_value, 'dragonrating', MAX_ABS_RATING, true );
         if ( !is_valid_rating($rating) )
            $errors[] = ErrorCode::get_error_text('invalid_rating');
         else
            $tresult->Rating = $rating;
      }

      $parsed_value = parseDate( T_('Start time for tournament result'), $vars['start_time'], /*secs*/true );
      if ( is_numeric($parsed_value) )
      {
         $tresult->StartTime = $parsed_value;
         $vars['start_time'] = formatDate($tresult->StartTime, '', DATE_FMT_QUICK);
      }
      else
         $errors[] = $parsed_value;

      $parsed_value = parseDate( T_('End time for tournament result'), $vars['end_time'], /*secs*/true );
      if ( is_numeric($parsed_value) )
      {
         $tresult->EndTime = $parsed_value;
         $vars['end_time'] = formatDate($tresult->EndTime, '', DATE_FMT_QUICK);
      }
      else
         $errors[] = $parsed_value;

      if ( $tresult->StartTime > 0 && $tresult->EndTime > 0 && $tresult->StartTime > $tresult->EndTime )
         $errors[] = T_('Start time should be before end time for tournament result.');

      $new_value = $vars['result'];
      if ( (string)$new_value != '' && !is_numeric($new_value) )
         $errors[] = T_('Result value must be numeric#tresult');
      else
         $tresult->Result = (int)$new_value;

      $new_value = $vars['rank'];
      if ( (string)$new_value != '' && ( !is_numeric($new_value) || $new_value <= 0 ) )
         $errors[] = T_('Rank must be a positive number#tresult');
      else
         $tresult->Rank = (int)$new_value;

      $new_value = trim($vars['comment']);
      if ( strlen($new_value) > 128 )
         $errors[] = sprintf( T_('Comment is too long (max. %s chars allowed).#tourney'), 128 );
      else
         $tresult->Comment = $new_value;

      $new_value = trim($vars['note']);
      if ( strlen($new_value) > 255 )
         $errors[] = sprintf( T_('Note is too long (max. %s chars allowed).#tourney'), 255 );
      else
         $tresult->Note = $new_value;

      // determine edits
      if ( $old_vals['type'] != $tresult->Type ) $edits[] = T_('Result Type#tourney');
      if ( $old_vals['uid'] != $tresult->uid ) $edits[] = T_('Result uid#tourney');
      if ( $old_vals['rid'] != $tresult->rid ) $edits[] = T_('Tournament Participant');
      if ( $old_vals['round'] != $tresult->Round ) $edits[] = T_('Result Round#tourney');
      if ( $old_vals['rating'] != $tresult->Rating ) $edits[] = T_('Result Rating#tourney');
      if ( $old_vals['start_time'] != $tresult->StartTime ) $edits[] = T_('Start time#tourney');
      if ( $old_vals['end_time'] != $tresult->EndTime ) $edits[] = T_('End time#tourney');
      if ( $old_vals['result'] != $tresult->Result ) $edits[] = T_('Result#tresult');
      if ( $old_vals['rank'] != $tresult->Rank ) $edits[] = T_('Rank#tresult');
      if ( $old_vals['comment'] != $tresult->Comment ) $edits[] = T_('Comment');
      if ( $old_vals['note'] != $tresult->Note ) $edits[] = T_('Note');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

// overwrites vars-input with data from various tournament-info-entries
// NOTE: keep in sync with TournamentResultControl::build_tournament_result_pool_winner()
function fill_tournament_info( &$vars, $uvars, $tourney, $user, $tp, $tladder, $tpool )
{
   global $NOW, $player_row;

   $vars['round'] = (int)$uvars['user_round'];
   $vars['end_time'] = formatDate( $NOW, '', DATE_FMT_QUICK );
   $vars['note'] = sprintf( 'auto-filled by [%s]', $player_row['Handle'] ); // no translation

   if ( !is_null($user) )
   {
      $vars['uid'] = $user->ID;
   }

   if ( !is_null($tp) )
   {
      $vars['rid'] = $tp->ID;
      if ( is_valid_rating($tp->Rating) )
         $vars['rating'] = $tp->Rating;
      elseif ( is_valid_rating($user->Rating) )
         $vars['rating'] = $user->Rating;
   }

   if ( !is_null($tladder) )
   {
      $vars['type'] = TRESULTTYPE_TL_SEQWINS;
      if ( $tladder->RankChanged > 0 )
         $vars['start_time'] = formatDate( $tladder->RankChanged, '', DATE_FMT_QUICK );
      $vars['result'] = $tladder->SeqWinsBest;
      $vars['rank'] = $tladder->Rank;
      $vars['comment'] = 'Consecutive Wins';
   }
   else if ( !is_null($tpool) )
   {
      $vars['type'] = TRESULTTYPE_TRR_POOL_WINNER;
      $tround = TournamentCache::load_cache_tournament_round( 'Tournament.edit_results.fill_tournament_info',
         $tourney->ID, (int)$vars['round'], /*chk*/false );
      if ( !is_null($tround) )
         $vars['start_time'] = formatDate( $tround->Lastchanged, '', DATE_FMT_QUICK );
      if ( $tpool->Rank > TPOOLRK_RANK_ZONE && $tpool->Rank != TPOOLRK_WITHDRAW )
         $vars['rank'] = abs($tpool->Rank);
      $vars['comment'] = 'Pool Winner';
   }
}//fill_tournament_info

// adds form-part with info for creating tournament-results from pool-winners
function echo_create_pool_winners_form( &$form, $tid, $round )
{
   $tround = TournamentCache::load_cache_tournament_round( 'Tournament.edit_results.create_poolwinners',
      $tid, $round, /*chk*/false );
   $cnt_pool_winners = TournamentPool::count_tournament_pool_next_rounders( $tid, $round );

   $form->add_row( array(
         'DESCRIPTION', T_('Tournament Round'),
         'TEXT',        $round, ));
   $form->add_row( array(
         'DESCRIPTION', T_('Tournament Round Status'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));
   $form->add_row( array(
         'DESCRIPTION', T_('Infos & Warnings#tourney'),
         'TEXT', span('TInfo', str_replace("\n", "<br>\n",
               T_("This is best done after the tournament round is finished and the pool-winners have been finally set,\n" .
                  "because all pool-winners will be copied and there are no checks for double result entries!"))), ));
   $form->add_row( array(
         'TAB',
         'TEXT', span('TInfo', sprintf( T_('Found %s pool-winners for round #%s in %s pools.'),
               $cnt_pool_winners, $round, $tround->Pools )), ));
   if ( $tround->Status != TROUND_STATUS_DONE )
   {
      $form->add_row( array(
            'TAB',
            'TEXT', span('TInfo', sprintf( T_('Round #%s is not finished yet.#tourney'), $round )), ));
   }

   $form->add_empty_row();
   if ( $cnt_pool_winners > 0 )
   {
      $form->add_row( array(
            'TAB',
            'TEXT', span('TWarning', T_('Please confirm creating tournament results from pool-winners!')), ));
      $form->add_row( array(
            'TAB', 'CELL', 1, '', // align submit-buttons
            'SUBMITBUTTON', 'tr_create_pw_confirm', T_('Confirm creation of results#tourney'),
            'TEXT', SMALL_SPACING,
            'SUBMITBUTTON', 'tr_cancel', T_('Cancel') ));
   }
   else
   {
      $form->add_row( array(
            'TAB',
            'TEXT', span('TWarning', T_('There are no pool-winners found for this round.')), ));
   }
}//echo_create_pool_winners_form

?>

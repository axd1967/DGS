<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/classlib_user.php';
require_once 'include/rating.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/db/games.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_status.php';

$GLOBALS['ThePage'] = new Page('TournamentGameAdmin');

define('GA_RES_SCORE',  1);
define('GA_RES_RESIGN', 2);
define('GA_RES_TIMOUT', 3);


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.game_admin');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "game_admin.php";

/* Actual REQUEST calls used
     tid=&gid=                : admin T-game
     gend_save&tid/gid=       : update T-game-score/status for admin-game-end
     addtime_save&tid/gid=    : add time for T-game
*/

   $tid = (int) @$_REQUEST['tid'];
   $gid = (int) @$_REQUEST['gid'];
   if( $tid < 0 ) $tid = 0;
   if( $gid < 0 ) $gid = 0;

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.game_admin.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   $game = Games::load_game($gid);
   if( is_null($game) )
      error('unknown_game', "Tournament.game_admin.find_tournament($tid)");

   $tgame = TournamentGames::load_tournament_game_by_gid($gid);
   if( is_null($tgame) )
      error('bad_tournament', "Tournament.game_admin.find_tgame($tid,$gid)");
   if( $tgame->tid != $tid )
      error('bad_tournament', "Tournament.game_admin.check_tgame.tid($tid,$gid)");

   // edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.game_admin.edit($tid,$my_id)");

   $errors = $tstatus->check_edit_status( TournamentGames::get_admin_tournament_status() );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();
   $authorise_game_end = $tourney->allow_edit_tournaments($my_id, TD_FLAG_GAME_END);
   $authorise_add_time = $tourney->allow_edit_tournaments($my_id, TD_FLAG_GAME_ADD_TIME);

   // init
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tgame, $game );
   $errors = array_merge( $errors, $input_errors );
   $user_black = User::load_user( $game->Black_ID );
   $user_white = User::load_user( $game->White_ID );

   // ---------- Process actions ------------------------------------------------

   if( count($errors) == 0 )
   {
      if( @$_REQUEST['gend_save'] && $authorise_game_end )
      {
         $tgame->Flags |= TG_FLAG_GAME_END_TD;
         $tgame->setStatus(TG_STATUS_SCORE);
         if( $tgame->update_score( 'Tournament.game_admin', TG_STATUS_PLAY ) )
         {
            $sys_msg = urlencode( T_('Tournament game result set!#tourney') );
            jump_to("tournaments/game_admin.php?tid=$tid".URI_AMP."gid=$gid".URI_AMP."sysmsg=$sys_msg");
         }
      }

      if( @$_REQUEST['addtime_save'] && $authorise_add_time )
      {
         $color = @$vars['color'];
         $opp = calc_opponent( $game, $color );
         if( is_null($opp) ) // shouldn't happen
            error('assert', "Tournament.game_admin.addtime.check.opp($tid,$gid,$color)");
         $add_days  = (int) @$vars['add_days'];
         $reset_byo = (bool) @$vars['reset_byoyomi'];

         $game_data = $game->fillEntityData();
         $game_row = $game_data->make_row();
         // fill clock-used for NextGameOrder::make_timeout_date()
         $game_row['X_BlackClock'] = $user_black->urow['ClockUsed'];
         $game_row['X_WhiteClock'] = $user_white->urow['ClockUsed'];

         ta_begin();
         {//HOT-section to add time
            $add_hours = GameAddTime::add_time_opponent( $game_row, $opp,
                  time_convert_to_hours($add_days, 'days'), $reset_byo, /*by_td*/$my_id );
         }
         ta_end();

         if( !is_numeric($add_hours) ) // error occured
            $errors[] = $add_hours;
         else
         {
            $sys_msg = urlencode( T_('Time added!') );
            jump_to("tournaments/game_admin.php?tid=$tid".URI_AMP."gid=$gid".URI_AMP."sysmsg=$sys_msg");
         }
      }
   }


   $title = T_('Tournament Game Admin');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   // ---------- Tournament Form -----------------------------------

   $tform = new Form( 'tournament1', $page, FORM_GET );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT',        Tournament::getStatusText($tourney->Status) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Game Status'),
         'TEXT',        TournamentGames::getStatusText($tgame->Status) ));
   $tform->add_empty_row();

   $tform->add_row( array(
         'DESCRIPTION', T_('Game ID'),
         'TEXT',        anchor($base_path."game.php?gid=$gid", "#$gid"),
         'TEXT',        echo_image_gameinfo($gid, true) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Game Status'),
         'TEXT',        Games::getStatusText($game->Status) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Black player'),
         'TEXT',        $user_black->user_reference() . SEP_SPACING .
                        echo_rating($user_black->Rating, true, $user_black->ID), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('White player'),
         'TEXT',        $user_white->user_reference() . SEP_SPACING .
                        echo_rating($user_white->Rating, true, $user_white->ID), ));

   if( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString( T_('There are some errors'), $errors ) ));
   }
   $tform->add_row( array( 'HR' ));

   $tform->echo_string();


   // ADMIN: End game ------------------

   if( $authorise_game_end )
      draw_game_end( $tgame );


   // ADMIN: Add time ------------------

   if( $authorise_add_time && $game->is_status_running() )
      draw_add_time( $tgame, $game, $authorise_add_time );


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Admin tournament game')] =
      array( 'url' => "tournaments/game_admin.php?tid=$tid".URI_AMP."gid=$gid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tgame, $game )
{
   $edits = array();
   $errors = array();
   $tid = $tgame->tid;
   $gid = $tgame->gid;

   // read from props or set defaults
   $vars = array(
      // all
      'color'     => '',
      // game-end
      'TG_Score'  => '',
      'score'     => '',
      'result'    => '',
      // add-time
      'add_days'  => 1,
      'reset_byoyomi' => '',
   );

   // init for game-end
   if( $tgame->Status == TG_STATUS_SCORE || $tgame->Status == TG_STATUS_WAIT || $tgame->Status == TG_STATUS_DONE )
      $game_score = (($tgame->Challenger_uid == $game->Black_ID) ? 1 : -1) * $tgame->Score;
   elseif( $game->Status == GAME_STATUS_FINISHED )
      $game_score = $game->Score;
   else
      $game_score = null;
   if( !is_null($game_score) )
   {
      $vars['TG_Score'] = (($tgame->Challenger_uid == $game->Black_ID) ? 1 : -1) * $game_score;
      $vars['color'] = ($game_score <= 0) ? BLACK : WHITE;
      $vars['score'] = '';
      if( abs($tgame->Score) == SCORE_RESIGN )
         $vars['result'] = GA_RES_RESIGN;
      elseif( abs($tgame->Score) == SCORE_TIME )
         $vars['result'] = GA_RES_TIMOUT;
      else
      {
         $vars['result'] = GA_RES_SCORE;
         $vars['score'] = abs($game_score);
      }
   }

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if( @$_REQUEST['addtime_save'] )
   {
      foreach( array( 'reset_byoyomi' ) as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   $mask_gend = 0;
   if( @$_REQUEST['gend_save'] || @$_REQUEST['addtime_save'] )
   {
      $new_value = $vars['color'];
      if( (string)$new_value != '' )
      {
         if( $new_value != BLACK && $new_value != WHITE ) // shouldn't happen with radio-buttons
            error('assert', "Tournament.game_admin.parse_edit_form.check.color($tid,$gid,$new_value)");
         else
            $mask_gend |= 1;
      }
   }

   if( @$_REQUEST['gend_save'] )
   {
      $new_value = (int)$vars['result'];
      if( $new_value )
      {
         if( $new_value != GA_RES_SCORE && $new_value != GA_RES_RESIGN
               && $new_value != GA_RES_TIMOUT ) // shouldn't happen with radio-buttons
            error('assert', "Tournament.game_admin.parse_edit_form.check.result($tid,$gid,$new_value)");
         else
         {
            $vars['result'] = (int)$new_value;
            $mask_gend |= 2;
         }
      }

      $new_value = trim($vars['score']);
      if( (string)$new_value != '' )
      {
         if( !preg_match("/^\\d+(\\.[05])?$/", $new_value) || $new_value > SCORE_MAX )
            $errors[] = sprintf( T_('Expecting number in format %s.5 for game score'), SCORE_MAX );
         else
         {
            $vars['score'] = (float)$new_value;
            $mask_gend |= 4;
         }
      }

      if( ($mask_gend & 3) == 3 ) // expected color, result [,score]
      {
         if( $vars['result'] == GA_RES_RESIGN )
            $game_score = SCORE_RESIGN;
         elseif( $vars['result'] == GA_RES_TIMOUT )
            $game_score = SCORE_TIME;
         else
         {
            if( $mask_gend & 4 )
               $game_score = $vars['score'];
            else
            {
               $errors[] = T_('Missing score for game result');
               $game_score = null;
            }
         }

         if( !is_null($game_score) )
         {
            if( $vars['color'] == BLACK ) // normalize to BLACK(<0), WHITE(>0)
               $game_score = -$game_score;
            if( $tgame->Challenger_uid == $game->White_ID ) // adjust to Challenger/Defender-color
               $game_score = -$game_score;
            $vars['TG_Score'] = $tgame->Score = $game_score;
         }
      }
      else
         $errors[] = T_('Missing color, result and score for game result');


      // determine edits
      if( $old_vals['TG_Score'] != $tgame->Score ) $edits[] = T_('Score#edits');
   }//game-end
   elseif( @$_REQUEST['addtime_save'] )
   {
      if( ($mask_gend & 1) != 1 ) // expected color
         $errors[] = T_('Missing color for add-time');

      $new_value = $vars['add_days'];
      if( !is_numeric($new_value) || $new_value < 1 || $new_value > MAX_ADD_DAYS )
         error('assert', "Tournament.game_admin.parse_edit_form.check.add_days($tid,$gid,$new_value)");
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

function draw_game_end( $tgame )
{
   global $page;
   $allow_edit = ( $tgame->Status == TG_STATUS_PLAY );
   $disabled = ( !$allow_edit ) ? 'disabled=1' : '';

   $tform = new Form( 'tournament2', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tgame->tid );
   $tform->add_hidden( 'gid', $tgame->gid );

   $tform->add_row( array(
         'CELL', 3, '',
         'HEADER', T_('End Tournament Game') ));

   $tform->add_row( array(
         'TEXT', ($allow_edit ? T_('Set game result') : T_('View game result') ).':', ));
   $tform->add_row( array(
         'CELL', 1, '',
         'TEXT', str_repeat(SMALL_SPACING,3) . T_('Winner is#TG_admin').': ',
         'CELL', 1, '',
         'RADIOBUTTONSX', 'color', array( BLACK => T_('Black') ), @$vars['color'], $disabled,
         'TEXT', SMALL_SPACING . T_('wins by#TG_admin') . SMALL_SPACING,
         'CELL', 1, '',
         'RADIOBUTTONSX', 'result', array( GA_RES_SCORE => T_('Score#TG_admin') ), @$vars['result'], $disabled,
         'TEXT', MED_SPACING,
         'TEXTINPUTX', 'score', 6, 6, @$vars['score'], $disabled,
         'TEXT', sprintf( ' (%s)', T_('0=Jigo#TG_admin') ), ));
   $tform->add_row( array(
         'TAB',
         'RADIOBUTTONSX', 'color', array( WHITE => T_('White') ), @$vars['color'], $disabled,
         'CELL', 1, '',
         'RADIOBUTTONSX', 'result', array( GA_RES_RESIGN => T_('Resignation#TG_admin') ), @$vars['result'], $disabled, ));
   $tform->add_row( array(
         'TAB', 'TAB',
         'RADIOBUTTONSX', 'result', array( GA_RES_TIMOUT => T_('Timeout#TG_admin') ), @$vars['result'], $disabled, ));

   if( $allow_edit )
   {
      $tform->add_row( array(
            'CELL', 3, '',
            'TEXT', span('TWarning', T_('This operation is irreversible, so please be careful!')), ));
      $tform->add_row( array(
            'CELL', 3, '', // align submit-buttons
            'SUBMITBUTTON', 'gend_save', T_('Save game result#TG_admin'), ));
   }

   $tform->add_empty_row();
   $tform->add_row( array( 'HR' ));
   $tform->echo_string();
}//draw_game_end

function calc_opponent( $game, $color )
{
   if( $color == WHITE )
      return $game->Black_ID;
   elseif( $color == BLACK )
      return $game->White_ID;
   return null;
}

// keep in sync with game.php#draw_add_time()-func
function draw_add_time( $tgame, $game, $allow_add_time )
{
   global $page, $vars;
   $game_data = $game->fillEntityData();
   $game_row = $game_data->make_row();

   $opp = calc_opponent( $game, @$vars['color'] );
   $allow_edit = (is_null($opp))
      ? true
      : GameAddTime::allow_add_time_opponent($game_row, $opp, $allow_add_time);

   $black_to_move = ( $game->ToMove_ID == $game->Black_ID );
   $timefmt = TIMEFMT_ADDTYPE | TIMEFMT_ZERO | TIMEFMT_ADDEXTRA;
   $black_remtime = build_time_remaining( $game_row, BLACK, $black_to_move, $timefmt );
   $white_remtime = build_time_remaining( $game_row, WHITE, !$black_to_move, $timefmt );

   $tform = new Form( 'tournament3', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tgame->tid );
   $tform->add_hidden( 'gid', $tgame->gid );

   $tform->add_row( array( 'HEADER', T_('Add time for Tournament Game') ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Time limit'),
         'CELL', 2, '',
         'TEXT', TimeFormat::echo_time_limit( $game->Maintime, $game->Byotype, $game->Byotime, $game->Byoperiods ), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Add time for'),
         'RADIOBUTTONS', 'color', array( BLACK => T_('Black') ), @$vars['color'],
         'TEXT', SEP_SPACING . T_('Time Remaining') . MED_SPACING . $black_remtime['text'],
         'TAB', ));
   $tform->add_row( array(
         'TAB',
         'RADIOBUTTONS', 'color', array( WHITE => T_('White') ), @$vars['color'],
         'TEXT', SEP_SPACING . T_('Time Remaining') . MED_SPACING . $white_remtime['text'],
         'TAB', ));
   $tform->add_empty_row();

   if( $allow_edit )
   {
      $info = GameAddTime::make_add_time_info( $game_row, ($black_to_move) ? BLACK : WHITE );

      $tform->add_row( array(
            'CELL', 3, '',
            'TEXT', T_('Choose how much additional time you wish to give the selected player').':', ));
      $tform->add_row( array(
            'TAB', 'CELL', 2, '',
            'SELECTBOX', 'add_days', 1, $info['days'], @$vars['add_days'], false,
            'TEXT', MINI_SPACING . T_('added to maintime.'), ));

      // no byoyomi-reset if no byoyomi
      if( $info['byo_reset'] )
      {
         $tform->add_row( array(
               'TAB', 'CELL', 2, '',
               'CHECKBOX', 'reset_byoyomi', 1, T_('Reset byoyomi settings when re-entering'), @$vars['reset_byoyomi'], ));
         $tform->add_row( array(
               'TAB', 'CELL', 2, '',
               'TEXT', T_('Note: Current byoyomi period is resetted regardless of full reset.'), ));
      }
   }

   $tform->add_row( array(
         'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'addtime_save', T_('Add time#TG_admin'), ));

   $tform->add_empty_row();
   $tform->echo_string();
}//draw_add_time
?>

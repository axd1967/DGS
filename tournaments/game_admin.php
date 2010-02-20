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
     tid=&gid=             : admin T-game
     gend_save&tid=        : update T-game-score/status for admin-game-end
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
   {
      $tform = new Form( 'tournament2', $page, FORM_GET );
      $tform->add_hidden( 'tid', $tid );
      $tform->add_hidden( 'gid', $gid );

      $allow_edit = ( $tgame->Status == TG_STATUS_PLAY );
      $disabled = ( !$allow_edit ) ? 'disabled=1' : '';

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

      $tform->echo_string();
   }


   // ADMIN: Add time ------------------

   if( $authorise_add_time )
   {
      $tform = new Form( 'tournament3', $page, FORM_GET );
      $tform->add_hidden( 'tid', $tid );
      $tform->add_hidden( 'gid', $gid );

      $tform->add_row( array( 'HEADER', T_('Add time for Tournament Game') ));

      $tform->echo_string();
   }


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
      'TG_Score'  => '',
      'color'     => '',
      'score'     => '',
      'result'    => '',
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

   // parse URL-vars
   if( @$_REQUEST['gend_save'] )
   {
      $mask_gend = 0;
      $new_value = $vars['color'];
      if( (string)$new_value != '' )
      {
         if( $new_value != BLACK && $new_value != WHITE ) // shouldn't happen with radio-buttons
            error('assert', "Tournament.game_admin.parse_edit_form.check.color($tid,$gid,$new_value)");
         else
            $mask_gend |= 1;
      }

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
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>

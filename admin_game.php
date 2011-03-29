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

$TranslateGroups[] = "Admin";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/rating.php';
require_once 'include/game_functions.php';
require_once 'include/db/games.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_games.php';

$GLOBALS['ThePage'] = new Page('GameAdmin');

define('GA_RES_SCORE',  1);
define('GA_RES_RESIGN', 2);
define('GA_RES_TIMOUT', 3);


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !(@$player_row['admin_level'] & ADMIN_GAME) )
      error('adminlevel_too_low');

   $page = "admin_game.php";

/* Actual REQUEST calls used
     gid=                : load game
     gend_save&gid=      : update game-score/status for game ending game
     grated_save&gid=    : toggle game-Rated-status for game
*/

   $gid = (int) @$_REQUEST['gid'];
   if( $gid <= 0 )
      error('unknown_game'); // need gid (use link in game-info-page)

   $game = Games::load_game($gid);
   if( is_null($game) )
      error('unknown_game', "admin_game.find_game($gid)");

   $tourney = $tgame = null;
   if( !is_null($game) && $game->tid > 0 )
   {
      $tid = $game->tid;
      $tourney = Tournament::load_tournament($tid);
      if( is_null($tourney) )
         error('unknown_tournament', "admin_game.find_tournament($tid)");
      $tgame = TournamentGames::load_tournament_game_by_gid($gid);
   }

   // init
   $errors = array();
   list( $vars, $input_errors ) = parse_edit_form( $game );
   $errors = array_merge( $errors, $input_errors );
   $user_black = User::load_user( $game->Black_ID );
   $user_white = User::load_user( $game->White_ID );

   // ---------- Process actions ------------------------------------------------

   if( count($errors) == 0 )
   {
      if( @$_REQUEST['gend_save'] )
      {
         // TODO: check for finished games, check for tourney-games -> see cron for timeout
         //TODO end-game, write admin-log
         jump_to("$page?gid=$gid".URI_AMP.'sysmsg='.urlencode(T_('Game result set!#gameadm')) );
      }
      elseif( @$_REQUEST['grated_save'] )
      {
         $toggled_rated = toggle_rated( $game->Rated );
         db_query( "admin_game.toggle_rated($gid,$game_rated)",
            "UPDATE Games SET Rated='$toggled_rated' WHERE ID=$gid AND Rated='{$game->Rated}' LIMIT 1" );
         jump_to("$page?gid=$gid".URI_AMP.'sysmsg='.urlencode(T_('Game rated-status updated!#gameadm')) );
      }
   }


   $title = T_('Game Admin#gameadm');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   // ---------- Form -----------------------------------

   $iform = new Form( 'gameadmin', $page, FORM_GET );

   $iform->add_row( array(
         'DESCRIPTION', T_('Game ID#gameadm'),
         'TEXT',        anchor($base_path."game.php?gid=$gid", "#$gid"),
         'TEXT',        echo_image_gameinfo($gid, true) ));

   if( !is_null($tourney) )
   {
      $iform->add_row( array(
            'DESCRIPTION', T_('Tournament ID'),
            'TEXT',        $tourney->build_info(4), ));
   }
   if( !is_null($tgame) )
   {
      $iform->add_row( array(
            'DESCRIPTION', T_('Tournament Game Status'),
            'TEXT',        TournamentGames::getStatusText($tgame->Status) ));
   }

   $iform->add_row( array(
         'DESCRIPTION', T_('Game Status#gameadm'),
         'TEXT',        $game->Status ));
   $iform->add_row( array(
         'DESCRIPTION', T_('Rated#gameadm'),
         'TEXT',        yesno($game->Rated) ));
   $iform->add_row( array(
         'DESCRIPTION', T_('Black player#gameadm'),
         'TEXT',        $user_black->user_reference() . SEP_SPACING .
                        echo_rating($user_black->Rating, true, $user_black->ID), ));
   $iform->add_row( array(
         'DESCRIPTION', T_('White player#gameadm'),
         'TEXT',        $user_white->user_reference() . SEP_SPACING .
                        echo_rating($user_white->Rating, true, $user_white->ID), ));

   if( count($errors) )
   {
      $iform->add_row( array( 'HR' ));
      $iform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
   }
   $iform->add_row( array( 'HR' ));

   $iform->echo_string();


   // ADMIN: End game ------------------

   if( isRunningGame($game->Status) )
      draw_game_admin_form( $game );

   end_page();
}//main


// return [ vars-hash, errorlist ]
function parse_edit_form( &$game )
{
   $errors = array();
   $gid = $game->ID;

   // read from props or set defaults
   $vars = array(
      'color'     => '', // game-end
      'score'     => '', // game-end
      'result'    => '', // game-end
   );

   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // checks
   if( !isRunningGame($game->Status) )
      $errors[] = T_('Game-result can only be changed for running game!#gameadm');
   if( $game->tid > 0 && @$_REQUEST['grated_save'] )
      $errors[] = T_('Rated-status can not be changed for tournament-game!#gameadm');

   // parse URL-vars
   $mask_gend = 0;
   if( @$_REQUEST['gend_save'] )
   {
      $new_value = $vars['color'];
      if( (string)$new_value != '' )
      {
         if( $new_value != BLACK && $new_value != WHITE ) // shouldn't happen with radio-buttons
            error('assert', "admin_game.parse_edit_form.check.color($gid,$new_value)");
         else
            $mask_gend |= 1;
      }

      $new_value = (int)$vars['result'];
      if( $new_value )
      {
         if( $new_value != GA_RES_SCORE && $new_value != GA_RES_RESIGN
               && $new_value != GA_RES_TIMOUT ) // shouldn't happen with radio-buttons
            error('assert', "admin_game.parse_edit_form.check.result($gid,$new_value)");
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
            $errors[] = sprintf( T_('Expecting number in format %s.5 for game score#gameadm'), SCORE_MAX );
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
               $errors[] = T_('Missing score for game result#gameadm');
               $game_score = null;
            }
         }

         if( !is_null($game_score) )
         {
            if( $vars['color'] == BLACK ) // normalize to BLACK(<0), WHITE(>0)
               $game_score = -$game_score;
         }
      }
      else
         $errors[] = T_('Missing color, result and score for game result#gameadm');

      if( count($errors) == 0 )
         $game->Score = $game_score;
   }//game-end

   return array( $vars, $errors );
}//parse_edit_form

function draw_game_admin_form( $game )
{
   global $page, $vars;

   $gaform = new Form( 'gameadmin', $page, FORM_GET );
   $gaform->add_hidden( 'gid', $game->ID );

   $gaform->add_row( array(
         'CELL', 2, '',
         'HEADER', T_('Set game result#gameadm'), ));
   $gaform->add_row( array(
         'CELL', 2, '',
         'TEXT', span('TWarning', T_('This operation is irreversible, so please be careful!#gameadm')), ));

   $gaform->add_row( array(
         'CELL', 1, '',
         'RADIOBUTTONS', 'color', array( BLACK => T_('Black') ), @$vars['color'],
         'TEXT', SMALL_SPACING . T_('wins by#gameadm') . SMALL_SPACING,
         'CELL', 1, '',
         'RADIOBUTTONS', 'result', array( GA_RES_SCORE => T_('Score#gameadm') ), @$vars['result'],
         'TEXT', MED_SPACING,
         'TEXTINPUT', 'score', 6, 6, @$vars['score'],
         'TEXT', sprintf( ' (%s)', T_('0=Jigo#gameadm') ), ));
   $gaform->add_row( array(
         'RADIOBUTTONS', 'color', array( WHITE => T_('White') ), @$vars['color'],
         'CELL', 1, '',
         'RADIOBUTTONS', 'result', array( GA_RES_RESIGN => T_('Resignation#gameadm') ), @$vars['result'], ));
   $gaform->add_row( array(
         'TAB',
         'RADIOBUTTONS', 'result', array( GA_RES_TIMOUT => T_('Timeout#gameadm') ), @$vars['result'], ));

   $gaform->add_empty_row();
   $gaform->add_row( array(
         'SUBMITBUTTON', 'gend_save', T_('Save game result#gameadm'), ));

   // ---------- Change rated-status ----------

   if( $game->tid == 0 )
   {
      $gaform->add_row( array(
            'CELL', 2, '',
            'HEADER', T_('Change game rated-status#gameadm'), ));
      $gaform->add_row( array(
            'DESCRIPTION', T_('Rated#gameadm'),
            'TEXT', sprintf( '%s => %s', yesno($game->Rated), yesno(toggle_rated($game->Rated)) ), ));
      $gaform->add_row( array(
            'SUBMITBUTTON', 'grated_save', T_('Toggle game rated-status#gameadm'), ));
   }

   $gaform->echo_string();
}//draw_game_admin_form

function toggle_rated( $yesno )
{
   return ($yesno == 'Y') ? 'N' : 'Y';
}

?>

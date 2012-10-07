<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/rating.php';
require_once 'include/game_functions.php';
require_once 'include/db/games.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_rules.php';

$GLOBALS['ThePage'] = new Page('GameAdmin');

define('GA_RES_SCORE',  1);
define('GA_RES_RESIGN', 2);
define('GA_RES_TIMOUT', 3);


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'admin_game');
   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_game');
   if( !(@$player_row['admin_level'] & ADMIN_GAME) )
      error('adminlevel_too_low', 'admin_game');

   $page = "admin_game.php";

/* Actual REQUEST calls used
     gid=                     : load game
     gend_save&gid=&resmsg=   : update game-score/status for game ending game
     grated_save&gid=         : toggle game-Rated-status for game
     gdel&gid=&delmsg=        : delete game (ask for confirmation)
     gdel_save&gid=&delmsg=   : delete game, execution
     cancel&gid=              : cancel operation, reload page
*/

   $gid = (int) @$_REQUEST['gid'];
   if( $gid <= 0 )
      error('unknown_game', "admin_game($gid)"); // need gid (use link in game-info-page)

   if( @$_REQUEST['cancel'] )
      jump_to("$page?gid=$gid");

   $game = Games::load_game($gid);
   if( is_null($game) )
      error('unknown_game', "admin_game.find_game($gid)");

   $tourney = $tgame = null;
   if( !is_null($game) && $game->tid > 0 )
   {
      $tid = $game->tid;
      $tourney = TournamentCache::load_cache_tournament( "admin_game.find_tournament($gid)", $tid );
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
         $game_finalizer = new GameFinalizer( ACTBY_ADMIN, $my_id, $gid, $game->tid, $game->Status,
            $game->GameType, $game->GamePlayers, $game->Flags, $game->Black_ID, $game->White_ID, $game->Moves,
            ($game->Rated != 'N') );
         $score_text = ($game->Score == 0.0) ? 'jigo' : ( $game->Score < 0 ? 'B' : 'W' ) . ' win';

         ta_begin();
         {//HOT-section to finish game
            admin_log( $my_id, $player_row['Handle'], "End game #$gid with result=[{$game->Score}][$score_text]" );

            $game_finalizer->finish_game( "admin_game", /*del*/false, null, $game->Score, trim(get_request_arg('resmsg')) );
         }
         ta_end();

         jump_to("$page?gid=$gid".URI_AMP.'sysmsg='.urlencode(T_('Game result set!')) );
      }
      elseif( @$_REQUEST['grated_save'] )
      {
         $toggled_rated = toggle_rated( $game->Rated );

         ta_begin();
         {//HOT-section to change game-rated-status
            admin_log( $my_id, $player_row['Handle'], "Update game #$gid with Rated=[{$game->Rated} -> $toggled_rated]" );
            db_query( "admin_game.toggle_rated($gid,$toggle_rated)",
               "UPDATE Games SET Rated='$toggled_rated' WHERE ID=$gid AND Rated='{$game->Rated}' LIMIT 1" );

            GameHelper::delete_cache_game_row( "admin_game.toggle_rated.del_cache($gid,$toggle_rated)", $gid );
         }
         ta_end();

         jump_to("$page?gid=$gid".URI_AMP.'sysmsg='.urlencode(T_('Game rated-status updated!')) );
      }
      elseif( @$_REQUEST['gdel_save'] )
      {
         // send message to my opponent / all-players / observers about the result
         $game_notify = new GameNotify( $gid, /*adm*/0, $game->Status, $game->GameType, $game->GamePlayers,
            $game->Flags, $game->Black_ID, $game->White_ID, $game->Score, /*rej-timeout*/false,
            trim(get_request_arg('delmsg')) );

         ta_begin();
         {//HOT-section to ...
            if( $game->Status == GAME_STATUS_FINISHED )
               $del_result = GameHelper::delete_finished_unrated_game($gid);
            else
               $del_result = GameHelper::delete_running_game($gid);

            if( $del_result )
            {
               admin_log( $my_id, $player_row['Handle'],
                  "Deleted game #$gid by admin: {$game->GameType}({$game->GamePlayers})[{$game->Status}], " .
                  "S{$game->Size}, H{$game->Handicap}, B{$game->Black_ID}, W{$game->White_ID}, " .
                  "#M={$game->Moves}, R[{$game->Rated}]" );

               // notify all players about deletion
               list( $Subject, $Text ) = $game_notify->get_text_game_deleted( ACTBY_ADMIN );
               send_message( 'confirm', $Text, $Subject,
                  /*to*/$game_notify->get_recipients(), '',
                  /*notify*/true, /*system-msg*/0, MSGTYPE_RESULT, $gid );

               $message = sprintf( T_('Game #%s deleted!'), $gid );
               jump_to("admin.php?sysmsg=".urlencode($message));
            }
         }
         ta_end();
      }
   }//actions


   $title = T_('Game Admin');
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
            'TEXT',        $tourney->build_info(4) . echo_image_tournament_info($tid, true), ));
   }
   if( !is_null($tgame) )
   {
      $iform->add_row( array(
            'DESCRIPTION', T_('Tournament Game Status'),
            'TEXT',        TournamentGames::getStatusText($tgame->Status) ));
      if( $tgame->Flags )
         $iform->add_row( array(
               'DESCRIPTION', T_('Tournament Game Flags'),
               'TEXT',        $tgame->formatFlags() ));
   }

   $gflags = ($game->Flags & ~GAMEFLAGS_KO );
   $iform->add_row( array(
         'DESCRIPTION', T_('Game Type & Status & Flags'),
         'TEXT', sprintf( '%s [%s] %s| %s',
                          GameTexts::format_game_type( $game->GameType, $game->GamePlayers )
                              . ($game->GameType == GAMETYPE_GO ? '' : MINI_SPACING . echo_image_game_players($gid)),
                          $game->Status, SMALL_SPACING, ($gflags ? Games::buildFlags($gflags) : NO_VALUE) ), ));

   $iform->add_row( array(
         'DESCRIPTION', T_('Rated'),
         'TEXT',        yesno($game->Rated) ));
   if( !is_null($user_black) )
      $iform->add_row( array(
            'DESCRIPTION', T_('Black player'),
            'TEXT',        $user_black->user_reference() . SEP_SPACING .
                           echo_rating($user_black->Rating, true, $user_black->ID), ));
   if( !is_null($user_white) )
      $iform->add_row( array(
            'DESCRIPTION', T_('White player'),
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
      'resmsg'    => '', // game-end
      'delmsg'    => '', // game-delete
   );

   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // checks
   if( $game->Status == GAME_STATUS_FINISHED )
   {
      if( $game->tid > 0 )
         $errors[] = T_('Finished tournament-game can not be changed!');
      elseif( $game->Rated != 'N' )
         $errors[] = T_('Finished rated game can not be changed!');
   }
   if( @$_REQUEST['gend_save'] && !isRunningGame($game->Status) )
      $errors[] = T_('Game-result can only be changed for running game!');
   if( @$_REQUEST['grated_save'] )
   {
      if( $game->tid > 0 )
         $errors[] = T_('Rated-status can not be changed for tournament-game!');
      if( $game->GameType != GAMETYPE_GO )
         $errors[] = T_('Rated-status can not be changed for multi-player-game!');
   }
   if( @$_REQUEST['gdel'] || @$_REQUEST['gdel_save'] )
   {
      if( $game->tid > 0 )
         $errors[] = T_('Tournament-game can not be deleted!');
      elseif( $game->Status == GAME_STATUS_FINISHED && $game->Rated != 'N' )
         $errors[] = T_('Finished rated game can not be deleted!');
   }

   // parse URL-vars
   $mask_gend = 0;
   if( @$_REQUEST['gend_save'] ) // set game-result
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
         $score_errors = array();
         if( !preg_match("/^\\d+(\\.[05])?$/", $new_value) || $new_value > SCORE_MAX )
            $score_errors[] = sprintf( T_('Expecting number in format %s.5 for game score'), SCORE_MAX );
         elseif( $game->tid > 0 )
         {
            $trule = TournamentCache::load_cache_tournament_rules( 'admin_game', $game->tid );
            $jigo_behaviour = $trule->determineJigoBehaviour();
            $chk_score = floor( abs( 2 * (float)$new_value ) );
            if( ( $jigo_behaviour > 0 && !($chk_score & 1) ) || ( $jigo_behaviour == 0 && ($chk_score & 1) ) )
               $score_errors[] = TournamentRules::getJigoBehaviourText( $jigo_behaviour );
         }

         if( count($score_errors) > 0 )
            $errors = array_merge( $errors, $score_errors );
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
         }
      }
      else
         $errors[] = T_('Missing color, result and score for game result');

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

   // ---------- Set game-result ----------

   $draw_hr = false;
   if( !@$_REQUEST['gdel'] && isRunningGame($game->Status) )
   {
      $draw_hr = true;
      $gaform->add_row( array(
            'CELL', 2, '',
            'HEADER', T_('Set game result'), ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'TEXT', span('TWarning', T_('This operation is irreversible, so please be careful!')), ));

      $gaform->add_row( array(
            'CELL', 1, '',
            'RADIOBUTTONS', 'color', array( BLACK => T_('Black') ), @$vars['color'],
            'TEXT', SMALL_SPACING . T_('wins by#gameadm') . SMALL_SPACING,
            'CELL', 1, '',
            'RADIOBUTTONS', 'result', array( GA_RES_SCORE => T_('Score') ), @$vars['result'],
            'TEXT', MED_SPACING,
            'TEXTINPUT', 'score', 6, 6, @$vars['score'],
            'TEXT', sprintf( ' (%s)', T_('0=Jigo#gameadm') ), ));
      $gaform->add_row( array(
            'RADIOBUTTONS', 'color', array( WHITE => T_('White') ), @$vars['color'],
            'CELL', 1, '',
            'RADIOBUTTONS', 'result', array( GA_RES_RESIGN => T_('Resignation') ), @$vars['result'], ));
      $gaform->add_row( array(
            'TAB',
            'RADIOBUTTONS', 'result', array( GA_RES_TIMOUT => T_('Timeout') ), @$vars['result'], ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'BR', 'TEXT', T_('Message to players').':', ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'TEXTAREA', 'resmsg', 50, 2, @$vars['resmsg'], ));

      $gaform->add_empty_row();
      $gaform->add_row( array(
            'SUBMITBUTTON', 'gend_save', T_('Save Game Result'), ));
   }

   // ---------- Change rated-status ----------

   if( !@$_REQUEST['gdel'] && $game->tid == 0 && $game->ShapeID == 0 && $game->GameType == GAMETYPE_GO && isStartedGame($game->Status) )
   {
      if( $draw_hr )
         $gaform->add_row( array( 'HR' ));
      $draw_hr = true;

      $gaform->add_row( array(
            'CELL', 2, '',
            'HEADER', T_('Change game rated-status'), ));
      $gaform->add_row( array(
            'DESCRIPTION', T_('Rated'),
            'TEXT', sprintf( '%s => %s', yesno($game->Rated), yesno(toggle_rated($game->Rated)) ), ));
      $gaform->add_row( array(
            'SUBMITBUTTON', 'grated_save', T_('Toggle game rated-status'), ));
      $draw_hr = true;
   }

   // ---------- Delete game ----------

   if( $game->tid == 0 || ($game->Status == GAME_STATUS_FINISHED && $game->tid == 0 && $game->Rated == 'N') )
   {
      $too_few_moves = ( $game->Moves < DELETE_LIMIT + $game->Handicap );
      if( $draw_hr )
         $gaform->add_row( array( 'HR' ));
      $draw_hr = true;

      $gaform->add_row( array(
            'CELL', 2, '',
            'HEADER', T_('Delete game'), ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'TEXT', sprintf( T_('Game has %s moves with handicap %s.'), $game->Moves, $game->Handicap ), ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'TEXT', ' => ' .
                    ( ( $too_few_moves && $game->GameType == GAMETYPE_GO && isStartedGame($game->Status) )
                        ? T_('Players can delete game too!')
                        : T_('Only admin can delete game!')), ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'BR', 'TEXT', T_('Message to players').':', ));
      $gaform->add_row( array(
            'CELL', 2, '',
            'TEXTAREA', 'delmsg', 50, 2, @$vars['delmsg'], ));
      if( @$_REQUEST['gdel'] ) // ask for confirmation
      {
         $gaform->add_row( array(
               'CELL', 2, '',
               'BR',
               'TEXT', span('FormWarning', T_('Do you really want to delete the game?')), ));
         $gaform->add_row( array(
               'SUBMITBUTTON', 'gdel_save', T_('Yes'),
               'SUBMITBUTTON', 'cancel', T_('No'), ));
      }
      else
      {
         $gaform->add_row( array(
               'SUBMITBUTTON', 'gdel', T_('Delete game'), ));
      }
   }

   $gaform->add_empty_row();
   $gaform->echo_string();
}//draw_game_admin_form

function toggle_rated( $yesno )
{
   return ($yesno == 'Y') ? 'N' : 'Y';
}

?>

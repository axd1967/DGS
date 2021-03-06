<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// jump to confirm.php (=form-submits)
if ( @$_REQUEST['nextgame']
      || @$_REQUEST['nextstatus']
      || @$_REQUEST['staygame']
      || @$_REQUEST['cancel']
      || @$_REQUEST['nextskip']
      || @$_REQUEST['nextaddtime']
      || @$_REQUEST['komi_save']
      || @$_REQUEST['fk_start'] )
{
//confirm use $_REQUEST: gid, move, action, coord, stonestring
   include_once 'confirm.php';
   exit; //should not be executed
}

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/game_functions.php';
require_once 'include/game_actions.php';
require_once 'include/game_comments.php';
require_once 'include/form_functions.php';
require_once 'include/board.php';
require_once 'include/move.php';
require_once 'include/classlib_user.php';
require_once 'include/time_functions.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/table_infos.php';
require_once 'include/classlib_goban.php';
require_once 'include/game_sgf_control.php';
require_once 'include/conditional_moves.php';
require_once 'include/db/move_sequence.php';
require_once 'include/sgf_parser.php';
if ( ALLOW_TOURNAMENTS ) {
   require_once 'tournaments/include/tournament_cache.php';
}

$GLOBALS['ThePage'] = new Page('Game');


{
/* Actual REQUEST calls used:  g=gid (mandatory), a=action, m=move, s=stonestring, c=coord
     gid=&a=''          : show game
     a=add_time         : show add-time dialog -> confirm-page with 'nextaddtime'-action on submit
     a=choose_move      : resume playing in scoring-mode
     a=delete           : show delete-game dialog -> confirm-page on submit
     a=domove           : show submit-dialog for validation after 'choose_move' and for normal move on board
     a=done             : show submit-dialog for validation after 'remove', done marking dead-stones in scoring-mode -> confirm-page on submit
     a=handicap         : multiple input step + validation, to input free handicap-stones
     a=negotiate_komi&komibid=&fkerr=  : show fair-komi-negotiation dialog
     a=pass             : pass-move -> confirm-page on submit
     a=remove           : multiple input step, dead-stone-marking in scoring-mode
     a=resign           : show resign dialog -> confirm-page on submit

     gid=&cma=action    : conditional-move action: edit, show
     gid=&cm_preview=   : preview cond-moves
     gid=&cm_save=      : save cond-moves (save inactive only)
     gid=&cm_activate=  : save and activate cond-moves; set cm_act_id for confirm-page

     toggleobserve=y|n  : toggle observing game
     movechange=1&gotomove= : view selected move (for view-move selectbox)
     move|m=            : view specific move (alternative for selecting move)

     tm=1               : hide territory markers (1=hide, 0=view with marked territory like in score-mode) for finished game
*/
   // NOTE: using page: confirm.php
   // NOTE: allowed for guest-user: toggle-observe

   $gid = (int)get_alt_arg( 'gid', 'g');
   $action = (string)get_alt_arg( 'action', 'a');
   $arg_move = get_alt_arg( 'move', 'm'); //move number, incl. MOVE_SETUP for shape-game
   $coord = (string)get_alt_arg( 'coord', 'c');
   $stonestring = (string)get_alt_arg( 'stonestring', 's');
   $preview = (bool)@$_REQUEST['preview'];
   $terr_marker = (@$_REQUEST['tm']) ? 1 : 0;
   $cm_action = get_request_arg('cma');
   $cm_activate_id = (int)get_request_arg('cm_act_id');

   $message = get_request_arg( 'message');
   $message = replace_move_tag( $message, $gid );

   disable_cache();

   connect2mysql();

   if ( $gid <= 0 )
      error('unknown_game', "game($gid)");

   $logged_in = who_is_logged( $player_row);
   show_maintenance_page();
   if ( $logged_in )
   {
      $my_id = $player_row['ID'];
      $cfg_board = ConfigBoard::load_config_board_or_default($my_id);
   }
   else
   {// for quick-suite //FIXME still needed ?
      $my_id = 0;
      $cfg_board = new ConfigBoard($my_id); // use defaults
   }
   $is_guest = ( $my_id <= GUESTS_ID_MAX );


   $game_row = GameHelper::load_cache_game_row( 'game', $gid );
   if ( !$game_row )
      error('unknown_game', "game.bad_game_id($gid)");
   extract($game_row);
   $is_shape = ($ShapeID > 0);
   $is_my_turn = ( $my_id == $ToMove_ID );

   if ( $Status == GAME_STATUS_INVITED || $Status == GAME_STATUS_SETUP )
      error('game_not_started', "game.check.bad_status($gid,$Status)");

   $game_setup = GameSetup::new_from_game_setup($game_row['GameSetup']);
   $is_fairkomi = $game_setup->is_fairkomi();
   if ( $Status == GAME_STATUS_KOMI && !$is_fairkomi )
      error('internal_error', "game.check.status_komi.no_fairkomi($gid,$Status,{$game_setup->Handicaptype})");
   $is_fairkomi_negotiation = ( $is_fairkomi && $Status == GAME_STATUS_KOMI );

   if ( ALLOW_TOURNAMENTS && ($tid > 0) )
      $tourney = TournamentCache::load_cache_tournament( "game.find_tournament($gid)", $tid );
   else
      $tourney = null;

   $score_mode = Ruleset::getRulesetScoring($Ruleset);

   if ( @$_REQUEST['movechange'] )
   {
      $arg_move = @$_REQUEST['gotomove'];
      if ( $arg_move !== MOVE_SETUP ) // shape-game
         $arg_move = (int)$arg_move;
   }
   if ( !$is_shape && $arg_move === MOVE_SETUP )
      $arg_move = $Moves;
   if ( $arg_move === MOVE_SETUP ) // shape-game
   {
      $move = 0;
      $move_setup = true;
   }
   else
   {
      $move = $arg_move = (int)$arg_move;
      $move_setup = false;
      if ( $move <= 0 )
         $move = $arg_move = $Moves;
   }

   if ( $Status == GAME_STATUS_KOMI )
   {
      $may_play = ( $logged_in && $is_my_turn );
      if ( !$action )
         $action = 'negotiate_komi';
   }
   elseif ( $Status == GAME_STATUS_FINISHED || $move < $Moves )
      $may_play = false;
   else
   {
      $may_play = ( $logged_in && $is_my_turn );
      if ( $may_play )
      {
         if ( !$action )
         {
            if ( $Status == GAME_STATUS_PLAY )
            {
               if ( $Handicap>1 && $Moves==0 )
                  $action = GAMEACT_SET_HANDICAP;
               else
                  $action = 'choose_move';
            }
            else if ( $Status == GAME_STATUS_PASS )
               $action = 'choose_move';
            else if ( $Status == GAME_STATUS_SCORE || $Status == GAME_STATUS_SCORE2 )
               $action = 'remove';
         }
      }
   }

   // allow validation
   $just_looking = !$may_play;
   if ( $just_looking && ( $action == 'add_time' || $action == GAMEACT_DELETE || $action == GAMEACT_RESIGN ) )
      $just_looking = false;

   $my_game = ( $logged_in && ( $my_id == $Black_ID || $my_id == $White_ID ) );
   $is_mp_game = ( $GameType != GAMETYPE_GO );

   // only for players and normal games (no FK, no MPG)
   $allow_cond_moves = ( ALLOW_CONDITIONAL_MOVES && $my_game && !$is_mp_game && !$is_fairkomi_negotiation
         && ($Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS) && $Moves >= $Handicap );
   $cm_move_seq = ( $allow_cond_moves )
      ? MoveSequence::load_cache_last_move_sequence( 'game', $gid, $my_id )
      : null;

   $mpg_users = array();
   $mpg_active_user = null;
   if ( $is_mp_game )
   {
      GamePlayer::load_users_for_mpgame( $gid, '', false, $mpg_users );
      $mpg_active_user = GamePlayer::find_mpg_user( $mpg_users, $my_id );
      $my_mpgame = !is_null($mpg_active_user);
   }
   else
      $my_mpgame = $my_game;

   $gc_helper = new GameCommentHelper( $gid, $Status, $GameType, $GamePlayers, $Handicap, $mpg_users, $mpg_active_user );


   // toggle observing (also allowed for my-game)
   $chk_observers = true;
   $chk_my_observe = null; // null = to-check
   if ( $logged_in && ($Status != GAME_STATUS_FINISHED) && @$_REQUEST['toggleobserve'] )
      $chk_my_observe = toggle_observe_list($gid, $my_id, @$_REQUEST['toggleobserve'] ); // Y|N
   elseif ( !$logged_in || ($Status == GAME_STATUS_FINISHED) )
      $chk_observers = false;

   if ( $chk_observers )
      list( $has_observers, $my_observe ) = check_for_observers( $gid, $my_id, $chk_my_observe );
   else
      $has_observers = $my_observe = null;


   $too_few_moves = ( $Moves < DELETE_LIMIT+$Handicap );
   $may_del_game = $my_game && $too_few_moves && isStartedGame($Status) && !$is_mp_game
      && ( $tid == 0 ) && !($Flags & GAMEFLAGS_TG_DETACHED); // delete ok if is and was no tournament-game

   $is_running_game = isRunningGame($Status);
   $may_resign_game = ( $action == 'choose_move')
      || ( $my_game && $is_running_game && ( $action == '' || $action == GAMEACT_RESIGN ) );

   if ( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if ( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else if ( $ToMove_ID )
      error('database_corrupted', "game.bad_ToMove_ID($gid,$ToMove_ID,$Black_ID,$White_ID)");

   if ( $Moves < $Handicap && ($action == 'choose_move' || $action == GAMEACT_DO_MOVE ) )
      error('invalid_action', "game.check.miss_handicap($gid,$my_id,$action,$Moves,$Handicap)");

   if ( $Status != GAME_STATUS_FINISHED && ($Maintime > 0 || $Byotime > 0) )
   {
      // LastTicks may handle -(time spend) at the moment of the start of vacations
      $clock_ticks = get_clock_ticks( "game($gid,$action)", $ClockUsed );
      $hours = ticks_to_hours( $clock_ticks - $LastTicks);

      if ( $to_move == BLACK )
      {
         time_remaining($hours, $game_row['Black_Maintime'], $game_row['Black_Byotime'],
                        $game_row['Black_Byoperiods'], $Maintime,
                        $Byotype, $Byotime, $Byoperiods, false);
      }
      else
      {
         time_remaining($hours, $game_row['White_Maintime'], $game_row['White_Byotime'],
                        $game_row['White_Byoperiods'], $Maintime,
                        $Byotype, $Byotime, $Byoperiods, false);
      }
   }

   $may_add_time = $my_game && GameAddTime::allow_add_time_opponent($game_row, $my_id);


   $enable_game_viewer = $show_game_tools = ( ALLOW_JAVASCRIPT && ENABLE_GAME_VIEWER );
   if ( $enable_game_viewer ) // temporary switch for enable/disable JS-game-viewer
   {
      $cmd = @$_REQUEST['jsgv'];
      if ( $cmd == 'on' )
         safe_setcookie("js_gv", 1, $NOW + 30*SECS_PER_DAY);
      elseif ( $cmd == 'off' )
         safe_setcookie("js_gv");
      $show_game_tools = (bool)safe_getcookie("js_gv");
   }

   $no_marked_dead = ( $Status == GAME_STATUS_KOMI || $Status == GAME_STATUS_PLAY || $Status == GAME_STATUS_PASS ||
                       $action == 'choose_move' || $action == GAMEACT_DO_MOVE );
   $board_opts = ( $no_marked_dead ? 0 : BOARDOPT_MARK_DEAD )
      | ( $show_game_tools ? BOARDOPT_LOAD_ALL_MSG : BOARDOPT_LOAD_LAST_MSG )
      | BOARDOPT_USE_CACHE;
   $cache_ttl = ( $Status == GAME_STATUS_KOMI || $Status == GAME_STATUS_FINISHED )
      ? 5*SECS_PER_MIN
      : 0; // use default

   $TheBoard = new Board( );
   if ( !$TheBoard->load_from_db( $game_row, $arg_move, $board_opts, $cache_ttl) )
      error('internal_error', "game.load_from_db($gid)");
   $movecol = $TheBoard->movecol;
   $last_move_msg = ( is_array($TheBoard->movemsg) ) ? @$TheBoard->movemsg[$move] : $TheBoard->movemsg;
   $last_move_arr = @$TheBoard->moves[$move];

   if ( $allow_cond_moves )
   {
      //TODO TODO handle finished games (no $to_move)
      list( $cm_sgf_parser, $cm_errors, $cm_var_names, $cond_moves, $new_action, $new_coord ) =
         handle_conditional_moves( $cm_move_seq, $game_row, $cm_action, $TheBoard, $to_move, $my_id, $is_my_turn );
      if ( $new_action )
      {
         $action = $new_action;
         if ( $new_coord )
            $coord = $new_coord;
         $cm_activate_id = ( is_null($cm_move_seq) ) ? 0 : $cm_move_seq->ID;
      }
   }

   $extra_infos = array();
   $game_score = null;

   if ( $just_looking || $is_guest ) //no process except 'movechange'
   {
      $validation_step = false;
      $may_play = false;
      if ( $Status == GAME_STATUS_FINISHED )
      {
         if ( abs($Score) <= SCORE_MAX && $move == $Moves && !($Flags & GAMEFLAGS_NO_RESULT) ) // don't calc for resign/time-out/forfeit/no-result
         {
            if ( $terr_marker )
               $score_board = clone $TheBoard; // hide territory-marker
            else
               $score_board = $TheBoard;
            list( $score, $game_score ) =
               GameActionHelper::calculate_game_score( $score_board, $stonestring, $Ruleset, $coord );
         }
         $admResult = ( $Flags & GAMEFLAGS_ADMIN_RESULT ) ? span('ScoreWarning', sprintf(' (%s)', T_('set by admin#game'))) : '';
         $score_text = score2text($Score, $Flags, /*verbose*/true) . $admResult;
         $extra_infos[$score_text] = 'Score';
      }
      elseif ( $TheBoard->is_scoring_step($move, $Status) )
      {
         $score_board = $TheBoard;
         list( $score, $game_score ) =
            GameActionHelper::calculate_game_score( $score_board, $stonestring, $Ruleset );
      }
   }
   else
   {
      switch ( (string)$action )
      {
         case 'choose_move': //single input pass; resume playing in scoring-mode
         {
            if ( !$is_running_game )
               error('invalid_action',"game.choose_move.check_status($gid,$Status)");

            $validation_step = false;
            break;
         }

         case GAMEACT_DO_MOVE: //for validation after 'choose_move' and for normal move on board
         {
            if ( !$is_running_game ) //after resume
               error('invalid_action',"game.domove.check_status($gid,$Status)");

            $validation_step = true;
            {//to fix old way Ko detect. Could be removed when no more old way games.
               if ( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
            }
            $gchkmove = new GameCheckMove( $TheBoard );
            $gchkmove->check_move( $coord, $to_move, $Last_Move, $Flags );
            $gchkmove->update_prisoners( $Black_Prisoners, $White_Prisoners );
            $game_row['Black_Prisoners'] = $Black_Prisoners;
            $game_row['White_Prisoners'] = $White_Prisoners;

            $stonestring = '';
            foreach ($gchkmove->prisoners as $tmp)
            {
               list($x,$y) = $tmp;
               $stonestring .= number2sgf_coords($x, $y, $Size);
            }

            if ( strlen($stonestring) != $gchkmove->nr_prisoners*2 )
               error('move_problem', "game.domove.check_prisoners($gid,$stonestring,{$gchkmove->nr_prisoners})");

            $TheBoard->set_move_mark( $gchkmove->colnr, $gchkmove->rownr);
            //$coord must be kept for validation by confirm.php
            break;
         }//case 'domove'

         case GAMEACT_SET_HANDICAP: //multiple input step + validation, to input free handicap-stones
         {
            if ( $Status != GAME_STATUS_PLAY || !( $Handicap>1 && $Moves==0 ) )
               error('invalid_action',"game.handicap.check_status($gid,$Status)");

            $paterr = '';
            $patdone = 0;
            if ( ENABLE_STDHANDICAP && !$stonestring && !$coord && ( $StdHandicap=='Y' || @$_REQUEST['stdhandicap'] ) )
            {
               $extra_infos[T_('A standard placement of handicap stones has been requested.')] = 'Info';
               $stonestring = get_handicap_pattern( $Size, $Handicap, $paterr);
               if ( $paterr )
                  $extra_infos[$paterr] = 'Important';
               //$coord = ''; // $coord is incoherent with the following
               $patdone = 1;
            }

            $stonestring = check_handicap( $TheBoard, $stonestring, $coord); //adjust $stonestring
            if ( strlen($stonestring) < 2*$Handicap )
            {
               $validation_step = false;
               $extra_infos[T_('Place your handicap stones, please!')] = 'Info';
               if ( ENABLE_STDHANDICAP && !$patdone && strlen($stonestring)<2 )
               {
                  $strtmp = "<a href=\"game.php?gid=$gid".URI_AMP."stdhandicap=t\">" . T_('Standard placement') . "</a>";
                  $extra_infos[$strtmp] = '';
               }
            }
            else
               $validation_step = true;
            $coord = ''; // already processed/stored in $stonestring
         }//case set-handicap
         break;

         case GAMEACT_RESIGN: //for validation
            if ( !$may_resign_game )
               error('invalid_action', "game.resign.check_status($gid,$Status,$my_id)");

            $validation_step = true;
            $extra_infos[T_('Resigning')] = 'Important';
            if ( $is_mp_game && !MultiPlayerGame::is_single_player( $GamePlayers, ($Black_ID == $my_id) ) )
            {
               $extra_infos[T_('You should have the consent of your team-members for resigning a multi-player-game!')] = 'Important';

               if ( $GameType == GAMETYPE_TEAM_GO )
                  $grcol = ($Black_ID == $my_id) ? GPCOL_B : GPCOL_W;
               else
                  $grcol = ''; // all for ZenGo
               $str = image( $base_path."images/send.gif", T_('Send message'), null, 'class=InTextImage' ) . ' '
                  . anchor( "message.php?mode=NewMessage".URI_AMP."mpgid=$gid".URI_AMP."mpmt=".MPGMSG_RESIGN
                              . URI_AMP."mpcol=$grcol".URI_AMP."mpmove=$Moves".URI_AMP."preview=1",
                            T_('Ask your team-members') );
               $extra_infos[$str] = 'Important';
            }
            break;

         case 'add_time': //add-time for opponent
            if ( !$may_add_time )
               error('invalid_action', "game.add_time.check_status($gid)");

            $validation_step = true;
            break;

         case GAMEACT_PASS: //for validation, pass-move
            if ( $Status != GAME_STATUS_PLAY && $Status != GAME_STATUS_PASS )
               error('invalid_action', "game.pass.check_status($gid,$Status)");

            $validation_step = true;
            $extra_infos[T_('Passing')] = 'Info';
            $extra_infos[T_('Ensure that all boundaries of your territory are closed before ending the game!')] = 'Important';
            if ( $score_mode == GSMODE_AREA_SCORING )
            {
               $extra_infos[T_('This game uses area-scoring, so dame (neutral points) count!') . "<br>\n" .
                            T_('Please ensure you don\'t leave an odd number of dame points!')] = 'Important';
            }
            break;

         case GAMEACT_DELETE: //for validation, delete-game
            if ( !$may_del_game )
               error('invalid_action',"game.delete.check_status($gid,$Status,$my_id)");

            $validation_step = true;
            $extra_infos[T_('Deleting game')] = 'Important';
            break;

         case 'remove': //multiple input step, dead-stone-marking in scoring-mode
         {
            if ( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
               error('invalid_action', "game.remove.check_status($gid,$Status)");

            $validation_step = false;
            list( $score, $game_score ) =
               GameActionHelper::calculate_game_score( $TheBoard, $stonestring, $Ruleset, $coord );

            $done_url = "game.php?gid=$gid".URI_AMP."a=done"
               . ( $stonestring ? URI_AMP."stonestring=$stonestring" : '' );

            $extra_infos[T_('Preliminary Score') . ": " . score2text($score, $Flags, /*verbose*/true)] = 'Score';

            $strtmp = span('NoPrint',
               sprintf( T_("Please mark dead stones and click %s'done'%s when finished."),
                  "<a href=\"$done_url\">", '</a>'));
            $extra_infos[$strtmp] = 'Info';
            $coord = ''; // already processed/stored in $stonestring
            break;
         }// case 'remove'

         case GAMEACT_SCORE: // ='done': for validation after 'remove', done marking dead-stones in scoring-mode
         {
            if ( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
               error('invalid_action', "game.done.check_status($gid,$Status)");

            $validation_step = true;
            list( $score, $game_score ) =
               GameActionHelper::calculate_game_score( $TheBoard, $stonestring, $Ruleset );

            $extra_infos[T_('Preliminary Score') . ": " . score2text($score, $Flags, /*verbose*/true)] = 'Score';
            break;
         }//case 'done'

         case 'negotiate_komi': //single input pass; negotiate-fair-komi
         {
            if ( $Status != GAME_STATUS_KOMI )
               error('invalid_action', "game.negotiate_komi.check_status($gid,$Status)");

            $validation_step = false;
            break;
         }//case 'negotiate_komi'

         default:
            error('invalid_action', "game.noaction.check($gid,$action,$Status)");
            break;
      }//switch $action
   }// !$just_looking

   if ( $validation_step )
   {
      $may_play = false;

      // auto-comment option
      if ( defined('AUTO_COMMENT_UID') && AUTO_COMMENT_UID && ($Black_ID == AUTO_COMMENT_UID || $White_ID == AUTO_COMMENT_UID) )
      {
         if ( (string)trim($message) == '' )
            $message = "<c>\n\n</c>";
      }
   }
   else if ( preg_match("/^(show|edit)$/", $cm_action) )
   {
      $may_play = false;
   }


   //----------------------------------------

   $view_comment = DAME; // watch game-comments as observer
   $opponent_ID = 0;
   if ( $my_game || $my_mpgame )
   {
      if ( $is_mp_game )
         $view_comment = BLACK;
      else // std-game
      {
         if ( $my_id == $Black_ID )
         {
            $my_color = 'B';
            $opponent_ID = $White_ID;
            $view_comment = BLACK;
         }
         elseif ( $my_id == $White_ID )
         {
            $my_color = 'W';
            $opponent_ID = $Black_ID;
            $view_comment = WHITE;
         }
      }


      // private-game-notes

      $cfgsize_notes = $cfg_board->get_cfgsize_notes( $Size );
      $notesheight = $cfg_board->get_notes_height( $cfgsize_notes );
      $noteswidth = $cfg_board->get_notes_width( $cfgsize_notes );
      $notesmode = $cfg_board->get_notes_mode( $cfgsize_notes );
      if ( isset($_REQUEST['notesmode']) )
         $notesmode= strtoupper($_REQUEST['notesmode']);

      $show_notes = true;
      $notes = '';
      $noteshide = (substr( $notesmode, -3) == 'OFF') ? 'Y' : 'N';

      $gn_row = GameHelper::load_cache_game_notes( 'game', $gid, $my_id );
      if ( $gn_row )
      {
         $notes = $gn_row['Notes'];
         $noteshide = $gn_row['Hidden'];
      }
      else if ( $is_fairkomi_negotiation )
         $noteshide = 'Y'; // default off for fair-komi
      if ( $noteshide == 'Y' )
         $notesmode = substr( $notesmode, 0, -3);

      $savenotes = false;
      if ( @$_REQUEST['togglenotes'] )
      {
         $tmp = ( (@$_REQUEST['hidenotes'] == 'N') ? 'N' : 'Y' );
         if ( $tmp != $noteshide )
         {
            $noteshide = $tmp;
            $savenotes = true;
         }
      }
      if ( @$_REQUEST['savenotes'] )
      {
         $tmp = rtrim(get_request_arg('gamenotes'));
         if ( $tmp != $notes )
         {
            $notes = $tmp;
            $savenotes = true;
         }
      }
      if ( $savenotes )
      {
         if ( $is_guest )
            error('not_allowed_for_guest', 'game.save_notes');

         GameHelper::update_game_notes( 'game', $gid, $my_id, $noteshide, $notes );
      }
   }
   else // !$my_game
   {
      $show_notes = false;
      $noteshide = 'Y';
   }

   $last_move_msg = $gc_helper->filter_comment( $last_move_msg, $move, $movecol, $view_comment, /*html*/true );
   $last_move_msg = MarkupHandlerGoban::replace_igoban_tags( $last_move_msg );
   $last_move_title = build_last_move_title( $gc_helper, $game_row, $last_move_arr );

   if ( ENA_MOVENUMBERS && !$show_game_tools )
   {
      $movenumbers = $cfg_board->get_move_numbers();
      if ( isset($_REQUEST['movenumbers']) )
         $movenumbers= (int)$_REQUEST['movenumbers'];
   }
   else
      $movenumbers= 0;

   if ( $logged_in )
      $TheBoard->set_style( $cfg_board );


   if ( $show_game_tools )
   {
      // set translated texts as global JS-vars
      $js  = add_js_var( 'T_gametools', dgs_json_encode(
         array(
            'hide_comment' => T_('Hide comments#ged'),
            'show_comment' => T_('Show comments#ged'),
            'curr_move'    => T_('Current Move#ged'),
         )), true );

      $js .= sprintf( "DGS.run.gamePageEditor = new DGS.GamePageEditor(%d,%d,%d,%d,%d,%d);\n",
         $gid, $cfg_board->get_stone_size(), $cfg_board->get_wood_color(), $Size, $Moves, $move );
      $js .= sprintf( "DGS.run.gamePageEditor.parseGameTree(%s);\n", $TheBoard->make_js_game_tree() );
   }
   else
      $js = null;

   $cnt_attached_sgf = ( $Flags & GAMEFLAGS_ATTACHED_SGF ) ? GameSgfControl::count_cache_game_sgfs( $gid ) : 0;


   $title = T_("Game") ." #$gid,$arg_move";
   start_page($title, 0, $logged_in, $player_row, $TheBoard->style_string(), null, $js);



   $jumpanchor = ( $validation_step ) ? '#msgbox' : '';
   echo "\n<FORM name=\"game_form\" action=\"game.php?gid=$gid$jumpanchor\" method=\"POST\" enctype=\"multipart/form-data\">";
   $gform = new Form( 'game_form', "game.php?gid=$gid$jumpanchor", FORM_POST, false ); //NOTE: only used indirectly
   $gform->set_config(FEC_BLOCK_FORM, true);
   $page_hiddens = array();
   // [ game_form start

   echo "\n<table id=GamePage align=center>\n<tr><td>"; //board & associates table {--------

   if ( $TheBoard->has_conditional_moves() )
   {
      $TheBoard->move_marks( 1, 0 ); // preview variation
      $TheBoard->set_move_mark(); // remove last-move-mark
      $TheBoard->draw_captures_box( T_('Captures'));
   }
   else
   {
      if ( $movenumbers>0 )
      {
         $movemodulo = $cfg_board->get_move_modulo();
         if ( $movemodulo >= 0 )
         {
            $TheBoard->move_marks( $move - $movenumbers, $move, $movemodulo, ((string)$action == GAMEACT_DO_MOVE) );
            $TheBoard->draw_captures_box( T_('Captures'));
            echo "<br>\n";
         }
      }
      if ( !$show_game_tools && ($cfg_board->get_board_flags() & BOARDFLAG_MARK_LAST_CAPTURE) )
      {
         $TheBoard->mark_last_captures( $move );
         $TheBoard->draw_last_captures_box( T_('Last Move Capture') );
         echo "<br>\n";
      }
   }//!cond-moves

   if ( !is_null($game_score) )
   {
      GameScore::draw_score_box( $game_score, $Flags, $Ruleset );
      //FIXME for debugging show other ruleset:
      //$other_ruleset = ( $Ruleset == RULESET_JAPANESE ) ? RULESET_CHINESE : RULESET_JAPANESE;
      //GameScore::draw_score_box( $game_score, $Flags, $other_ruleset );

      // only shown if there is a calculated game-score after game is finished
      if ( $Status == GAME_STATUS_FINISHED )
      {
         echo anchor( $base_path."game.php?gid=$gid".URI_AMP."tm=".(1-$terr_marker),
            T_('Toggle Territory Markers#game'), '', 'class="smaller"');
      }
   }
   echo "</td><td>";

   if ( $is_fairkomi_negotiation )
      draw_fairkomi_negotiation( $my_id, $gform, $game_row, $game_setup );
   else
   {
      $TheBoard->draw_board( $may_play, $action, $stonestring,
         ( $show_game_tools ? null : array( $last_move_title, $last_move_msg ) ) );
   }

   //messages about actions
   if ( $validation_step )
   {
      if ( $cm_activate_id )
         $extra_infos[T_('Hit "Submit" to confirm and activate conditional-moves!')] = 'Guidance';
      else
         $extra_infos[T_('Hit "Submit" to confirm')] = 'Guidance';
   }

   if ( count($extra_infos) )
   {
      echo "\n<dl class=ExtraInfos>";
      foreach ($extra_infos as $txt => $class)
         echo "<dd".($class ? " class=\"$class\"" : '').">$txt</dd>";
      echo "</dl>\n";
   }
   else
      echo "<br>\n";

   $cols = 2;
   if ( $show_game_tools )
   {
      draw_game_tools();
      $show_notes = false;
      ++$cols;
   }

   if ( $show_notes && $noteshide != 'Y' )
   {
      if ( $notesmode == 'BELOW' )
         echo "</td></tr>\n<tr><td colspan=$cols class=GameNotesBelow>";
      else //default 'RIGHT'
      {
         $cols++;
         echo "</td>\n<td class=GameNotesRight>";
      }
      draw_notes( 'N', $notes, $notesheight, $noteswidth);
      $show_notes = false;
   }

   // colspan = captures+board column
   echo "</td></tr>\n<tr><td colspan=$cols class=UnderBoard>";

   if ( $validation_step )
   {
      if ( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }

      if ( $action == 'add_time' )
         draw_add_time( $game_row, $to_move );
      else
      {
         draw_message_box( $cfg_board, $message );

         if ( $preview )
         {
            $preview_msg = make_html_safe( $message, 'gamehs' );
            $preview_msg = MarkupHandlerGoban::replace_igoban_tags( $preview_msg );
            $TheBoard->draw_move_message( $preview_msg, T_('Preview').':' );
         }
      }
   }
   else if ( $Moves > 1 || $is_shape )
   {
      if ( !$show_game_tools )
      {
         if ( $allow_cond_moves )
         {
            if ( $cm_action == 'edit' || $cm_action == 'show' )
               draw_conditional_moves_input( $gform, $gid, $my_id, $cm_action, $cm_move_seq, $cm_sgf_parser, $cm_errors, $cond_moves, $cm_var_names );
            else
               draw_conditional_moves_links( $gid, $my_id, $cm_move_seq );
         }

         draw_moves( $gid, $arg_move, $game_row['Handicap'] );
      }
      if ( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }
   }

   if ( $show_notes )
   {
      draw_notes('Y');
      $show_notes = false;
   }

   if ( $Status != GAME_STATUS_KOMI )
   {
      echo SMALL_SPACING,
         anchor( 'http://eidogo.com/#url:'.HOSTBASE."sgf.php?gid=$gid",
            image( 'images/eidogo.gif', T_('EidoGo Game Player'), null, 'class=InTextImage' ),
            '', 'class=NoPrint' );
   }

   if ( $cnt_attached_sgf > 0 )
      echo SMALL_SPACING, echo_image_game_sgf( $gid, $cnt_attached_sgf );

   // observers may view the comments in the sgf files, so not restricted to own games
   if ( $Status != GAME_STATUS_KOMI )
   {
      echo SMALL_SPACING,
         anchor( "game_comments.php?gid=$gid", T_('Comments'), '',
                 array( 'accesskey' => ACCKEYP_GAME_COMMENT,
                        'target' => FRIENDLY_SHORT_NAME.'_game_comments',
                        'class' => 'NoPrint' ));
   }
   if ( $Status == GAME_STATUS_FINISHED && ($Flags & (GAMEFLAGS_HIDDEN_MSG|GAMEFLAGS_SECRET_MSG) ) )
   {
      echo MED_SPACING . echo_image_gamecomment( $gid,
            ($Flags & GAMEFLAGS_HIDDEN_MSG), $my_mpgame && ($Flags & GAMEFLAGS_SECRET_MSG) );
   }
   if ( $enable_game_viewer ) //TODO remove/replace later
   {
      $cmd = ($show_game_tools) ? 'off' : 'on';
      echo SMALL_SPACING, anchor( "game.php?gid=$gid".URI_AMP."jsgv=$cmd", "JS-GameEditor ".strtoupper($cmd) );
   }


   echo "\n</td></tr>\n</table>"; //board & associates table }--------


   // ] game_form end
   if ( $show_game_tools ) // a hidden gid is already set by draw_moves(), but not if $show_game_tools set
      $page_hiddens['gid'] = $gid;
   $page_hiddens['action'] = $action;
   $page_hiddens['move'] = $arg_move;
   if ( @$coord )
      $page_hiddens['coord'] = $coord;
   if ( @$stonestring )
      $page_hiddens['stonestring'] = $stonestring;
   $page_hiddens['movenumbers'] = @$_REQUEST['movenumbers'];
   $page_hiddens['notesmode'] = @$_REQUEST['notesmode'];
   $page_hiddens['cma'] = $cm_action;
   $page_hiddens['cm_act_id'] = $cm_activate_id;

   echo build_hidden( $page_hiddens);
   echo "\n</FORM>";

   echo "\n<HR>";
   draw_game_info($game_row, $game_setup, $TheBoard, $tourney); // with board-info



   if ( $may_play || $validation_step ) //should be "from status page" as the nextgame option
      $menu_array[T_('Skip to next game')] = "confirm.php?gid=$gid".URI_AMP."nextskip=t";

   if ( !$validation_step )
   {
      if ( $action == 'choose_move' )
      {
         if ( $Status != GAME_STATUS_SCORE && $Status != GAME_STATUS_SCORE2 )
            $menu_array[T_('Pass')] = "game.php?gid=$gid".URI_AMP."a=".GAMEACT_PASS;
      }
      else if ( $action == 'remove' )
      {
         if ( @$done_url )
            $menu_array[T_('Done')] = $done_url;

         $menu_array[T_('Resume playing')] = "game.php?gid=$gid".URI_AMP."a=choose_move";
      }
      else if ( $action == GAMEACT_SET_HANDICAP )
      {
         ; // none (at the moment)
      }
      else if ( $Status == GAME_STATUS_FINISHED && $my_game && $opponent_ID > 0) //&& $just_looking
      {
         $menu_array[T_('Send message to user')] = "message.php?mode=NewMessage".URI_AMP."uid=$opponent_ID" ;
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$opponent_ID" ;
      }

      if ( $may_resign_game )
         $menu_array[T_('Resign')] = "game.php?gid=$gid".URI_AMP."a=".GAMEACT_RESIGN;

      if ( $may_del_game )
         $menu_array[T_('Delete game')] = "game.php?gid=$gid".URI_AMP."a=".GAMEACT_DELETE;

      if ( $action != 'add_time' && $may_add_time )
         $menu_array[T_('Add time for opponent')] = "game.php?gid=$gid".URI_AMP."a=add_time#addtime";

      if ( !$is_fairkomi_negotiation )
      {
         $menu_array[T_('Download sgf')] = "sgf.php?gid=$gid";
         if ( ($my_game || $my_mpgame) && ($Moves>0 || $is_shape) )
            $menu_array[T_('Download sgf with all comments')] = "sgf.php?gid=$gid".URI_AMP."owned_comments=1" ;
         if ( ALLOW_CONDITIONAL_MOVES && $Status == GAME_STATUS_FINISHED )
            $menu_array[T_('Download SGF with conditional moves')] = "sgf.php?gid=$gid".URI_AMP."cm=3";
      }

      if ( !is_null($my_observe) )
      {
         if ( $my_observe )
            $menu_array[T_('Remove from observe list')] = "game.php?gid=$gid".URI_AMP."toggleobserve=N";
         else
            $menu_array[T_('Add to observe list')] = "game.php?gid=$gid".URI_AMP."toggleobserve=Y";
      }

      if ( $has_observers )
         $menu_array[T_('Show observers')] = "users.php?observe=$gid";
   }

   if ( $is_fairkomi_negotiation )
      $menu_array[T_('Refresh#gamepage')] = "game.php?gid=$gid";
   $menu_array[T_('Show game info')] = "gameinfo.php?gid=$gid";
   if ( $is_mp_game )
      $menu_array[T_('Show game-players')] = "game_players.php?gid=$gid";
   $menu_array[T_('Attach SGF')] = "manage_sgf.php?gid=$gid";

   if ( @$player_row['admin_level'] & ADMIN_GAME )
      $menu_array[T_('Admin game')] =
         array( 'url' => "admin_game.php?gid=$gid", 'class' => 'AdminLink' );

   end_page(@$menu_array, -4);
}// main



// abbreviations used to reduce file size
function get_alt_arg( $n1, $n2)
{
// $_GET must have priority at least for those used in the board links
// for instance, $_POST['coord'] is the current (last used) coord
// while $_GET['coord'] is the coord selected in the board (next coord)

   if ( isset( $_GET[$n1]) )
      return $_GET[$n1];
   if ( isset( $_GET[$n2]) )
      return $_GET[$n2];

   if ( isset( $_POST[$n1]) )
      return $_POST[$n1];
   if ( isset( $_POST[$n2]) )
      return $_POST[$n2];

   return '';
}//get_alt_arg

// draw select-box with moves to go-to selected move
// \param $move == global $arg_move including MOVE_SETUP for shape-game
function draw_moves( $gid, $move, $handicap )
{
   global $TheBoard, $player_row;

   $Size= $TheBoard->size;
   $Moves= $TheBoard->max_moves;
   if ( $move === MOVE_SETUP )
   {
      $move = 0;
      $is_move_setup = true;
   }
   else
      $is_move_setup = false;

   //basic_safe() because inside <option></option>
   $trmov = basic_safe(T_('Move'));
   $trpas = basic_safe(T_('Pass'));
   $trsco = basic_safe(T_('Scoring'));
   $trres = basic_safe(T_('Resign'));
   $trsetup_handi = basic_safe(T_('(H)#viewmove'));

   $ctab = '&nbsp;';
   $wtab = str_repeat($ctab, 8);
   // args: movenr, 'selected'; 'Move'-text (translated), movenr, move
   $ofmt = "<OPTION value=\"%d\"%s>%s%s$ctab%s:$ctab$ctab%s</OPTION>\n";

   /**
     make the <OPTION> list smaller:
     - e.g.: 100 moves, move #50 selected, step=sqrt(100)=10
       => option lines provided, relative to the move selected:
       +50 ... +40 ... +30 ... +20 ... +10 +9 +8 +7 +6 +5 +4 +3 +2 +1
       selected -1 -2 -3 -4 -5 -6 -7 -8 -9 -10 ... -20 ... -30 ... -40 ... -50
     - Near (2*step+1) + (step-2) option lines => 2*step < num-lines < 3*step,
       and always show some start- and end-moves
    **/
   $step= 5 * max( 1, round(sqrt($Moves)/5) );

   $mvs_start = 5; // num of first moves showed, 0 = ignore start
   $mvs_end = 8; // num of last moves showed, at least 4 end-moves (+ 2x pass + 2x score)
   $firstskip = 0;

   if ( ($mvs_start > 0) || ($move <= $step) )
   {
      $i= 0;
      $firstskip = (($move - 1) % $step) + 2;
   }
   else
      $i= ($move-1) % $step;

   if ( is_array(@$TheBoard->moves[MOVE_SETUP]) ) // handle shape-game setup
   {
      $str = sprintf( "<OPTION value=\"%s\"%s>%s</OPTION>\n",
                      MOVE_SETUP,
                      ( $is_move_setup ? ' selected' : '' ),
                      basic_safe(T_('Setup: Shape#moves')) );
   }
   else
      $str = '';

   while ( $i++ < $Moves )
   {
      $dlt= abs($move-$i);
      if ( ($i <= $mvs_start || $i >= $Moves - $mvs_end) || ($dlt < $step) || ($dlt % $step) == 0 )
      {
         list( $Stone, $PosX, $PosY)= @$TheBoard->moves[$i];
         if ( $Stone != BLACK && $Stone != WHITE )
            continue;

         switch ( (int)$PosX )
         {
            case POSX_PASS :
               $c = $trpas;
               break;
            case POSX_SCORE :
               $c = $trsco;
               break;
            case POSX_RESIGN :
               $c = $trres;
               break;
            default :
               if ( $PosX < 0)
                  continue;
               $c = number2board_coords($PosX, $PosY, $Size);
               break;
         }

         $c= str_repeat($ctab, strlen($Moves)-strlen($i)).$c;
         if ( $Stone == WHITE ) $c= $wtab.$c;
         $setup = ( $handicap > 0 && $i <= $handicap )
            ? $trsetup_handi .' '
            : '';

         $c= sprintf( $ofmt
                    , $i, ( ($i == $move) ? ' selected' : '' )
                    , $setup
                    , $trmov // keep to prevent mixup, if $trmov contains '%'-sprintf-fmttext
                    , $i, $c );
      }
      else
      {
         //$c= "<OPTION value=\"$move\">---</OPTION>\n";
         //$c= "<OPTION>---</OPTION>\n";
         $c= "<OPTION value=\"$move\" disabled>---</OPTION>\n";

         //here $step is >1 because of ($dlt % $step)==0
         if ( $firstskip > 0 && $i - $firstskip < $step )
         {
            $i -= ($i - $firstskip);
            $firstskip = 0;
         }
         $i += $step - 2;
         if ( $i >= $Moves - $mvs_end ) $i = $Moves - $mvs_end - 1;
      }

      //first move at bottom:
      $str= $c.$str;
   }

   // show SGF-move-num
   echo "\n";
   if ( $move <= $handicap )
      $sgf_move = 0;
   else {
      $sgf_move = get_final_score_move( $move );
      if ( $handicap > 0 )
         $sgf_move -= $handicap;
   }

   echo span('SgfMove', sprintf( T_('(SGF-Move %s)'), $sgf_move )), MINI_SPACING;

   // add selectbox to show specific move
   echo "<SELECT name=\"gotomove\" size=\"1\"";
   if ( is_javascript_enabled() )
   {
      echo " onchange=\"this.form['movechange'].click();\"";
   }
   echo ">\n$str</SELECT>";
   echo '<INPUT type="HIDDEN" name="gid" value="' . $gid . "\" class=NoPrint>";
   echo '<INPUT type="submit" name="movechange" value="' . T_('View move') . "\" class=NoPrint>";
} //draw_moves

function draw_game_tools()
{
   global $base_path, $game_row, $game_setup, $TheBoard, $tourney, $notes, $my_game, $my_mpgame;
   $img_chg_note = image($base_path."images/star3.gif", T_('Notes have unsaved changes!#ged'), null, 'id=NotesChanged');
   $show_notes = ( $my_game || $my_mpgame );

   echo "</td>\n",
      "<td id=ToolsArea class=\"GameTools NoPrint\">",
         "<div id=tabs>\n",
            "<ul>\n",
               "<li>", anchor('#tab_GameAnalysis', T_('Analyse#ged'), T_('Analyse game#ged')), "</li>\n",
               ( $show_notes
                  ? "<li>" . anchor('#tab_GameNotes', T_('Notes#ged') . ' ' . $img_chg_note, T_('Private game notes')) . "</li>\n"
                  : '' ),
            "</ul>\n",
            "<div id=tab_GameAnalysis class=\"tab\">\n", build_tab_GameAnalysis(),
               "<div id=GameMessage>\n",
                  "<div id=GameMessageHeader>", T_('Messages#ged'), build_comment_tools(), "</div>\n",
                  "<div id=GameMessageBody>\n", build_move_comments(), "</div>\n",
               "</div>\n",
            "</div>\n";
   if ( $show_notes )
   {
      echo "<div id=tab_GameNotes class=tab>\n";
      draw_notes(null, $notes, 12, 65); // use fixed size
      echo "</div>\n";
   }
   echo  "</div>\n";
}//draw_game_tools

function build_comment_tools()
{
   global $base_path, $my_game, $my_mpgame;
   return " <div id=GameMessageTools>"
      . (( $my_game || $my_mpgame )
         ? image($base_path."images/up.png", T_('Show my previous move#ged'), null, 'id=GameMsgTool_GoToMyPreviousMove')
         : '' )
      . image($base_path."13/wm.gif", T_('Show current move comment#ged'), null, 'id=GameMsgTool_ScrollToCurrMove')
      . image($base_path."images/comment_hide.png", T_('Hide comments#ged'), null, 'id=GameMsgTool_ToggleComment')
      . "</div>";
}

// display all game move-messages properly filtered according to player/observer and move for std- and MP-game
function build_move_comments()
{
   global $base_path, $TheBoard, $game_row, $is_mp_game, $mpg_users, $gc_helper, $view_comment, $my_id;
   $out = array();

   $Handicap = $game_row['Handicap'];
   $GamePlayers = $game_row['GamePlayers'];
   $rat_suffix = ($game_row['Status'] == GAME_STATUS_FINISHED) ? '_End_Rating' : 'rating';
   $cfg_class = array( BLACK => 'Black', WHITE => 'White' );
   $cfg_image = array(
         BLACK => image($base_path."17/b.gif", T_('Black'), null, 'class=InTextImage'),
         WHITE => image($base_path."17/w.gif", T_('White'), null, 'class=InTextImage'),
         MOVE_SETUP => image($base_path."images/shape.gif", T_('Shape Game'), null, 'class=InTextImage'),
      );
   $chk_uid_color = array( BLACK => $game_row['Black_ID'], WHITE => $game_row['White_ID'] );

   $cfg_user = array(); // uid => Handle, Rank  |  (for MPG): "grp_col:grp_order" => [ Handle, Rating2, ... ]
   if ( $is_mp_game )
      $cfg_users = $mpg_users;
   else
   {
      $cfg_user[BLACK] = sprintf( '%s, %s', $game_row['Blackhandle'],
            echo_rating($game_row["Black{$rat_suffix}"], false, 0, false, true) );
      $cfg_user[WHITE] = sprintf( '%s, %s', $game_row['Whitehandle'],
            echo_rating($game_row["White{$rat_suffix}"], false, 0, false, true) );
   }

   $base_move_link = $base_path."game.php?gid={$TheBoard->gid}".URI_AMP."move=";
   if ( is_array(@$TheBoard->moves[MOVE_SETUP]) ) // handle shape-game setup (has no game-comment)
   {
      $move_nr = MOVE_SETUP;
      $move_link = anchor( $base_move_link . $move_nr, T_('Setup: Shape#moves'), '', 'class=MRef' );
      $out[] = "<div id=movetxt0>\n"
         . "<div class=Head>{$cfg_image[$move_nr]} $move_link</div><div class=Tools></div>\n"
         . "</div>";
   }

   //TODO later add option to jump to "my-last-move" (esp. for MPG)
   for ( $move_nr=0; $move_nr <= $TheBoard->max_moves; $move_nr++ )
   {
      list( $Stone, $PosX, $PosY ) = @$TheBoard->moves[$move_nr];
      if ( $Stone != BLACK && $Stone != WHITE )
         continue;

      switch ( (int)$PosX )
      {
         case POSX_PASS: $move_pos = 'PASS'; break;
         case POSX_SCORE: $move_pos = 'SCORE'; break;
         case POSX_RESIGN: $move_pos = 'RESIGN'; break;
         default:
            if ( $PosX < 0)
               continue;
            $move_pos = number2board_coords($PosX, $PosY, $TheBoard->size);
            break;
      }

      $move_msg = trim( @$TheBoard->movemsg[$move_nr] );
      $move_msg = $gc_helper->filter_comment( $move_msg, $move_nr, $Stone, $view_comment, /*html*/true );
      $move_msg = MarkupHandlerGoban::replace_igoban_tags( $move_msg ); // shown with same style-size as main-board

      // build player-info for current move
      $my_move_str = '';
      if ( $is_mp_game )
      {
         $mpg_user = $gc_helper->get_mpg_user();
         if ( is_array($mpg_user) )
         {
            $user_info = sprintf( '%s, %s', $mpg_user['Handle'], echo_rating($mpg_user['Rating2'], false, 0, false, true) );
            if ( $mpg_user['uid'] == $my_id )
               $my_move_str = ' MyMove';
         }
         else
            $user_info = '[?]'; // shouldn't happen
      }
      else
      {
         $user_info = $cfg_user[$Stone];
         if ( $chk_uid_color[$Stone] == $my_id )
            $my_move_str = ' MyMove';
      }

      $handi_str = ( $Handicap > 0 && $move_nr <= $Handicap ? ' ' . T_('(H)#viewmove') : '' );
      $move_link = anchor( $base_move_link.$move_nr, $move_pos, '', 'class=MRef' );
      $out[] = "<div id=movetxt$move_nr class=\"{$cfg_class[$Stone]}$my_move_str\">\n"
         . "<div class=Head>{$cfg_image[$Stone]} "
            . sprintf( T_('Move %s [%s] by %s#ged'), $move_nr . $handi_str, $move_link, $user_info )
         . "</div><div class=Tools></div>\n"
         . "<div class=CBody>$move_msg</div>"
         . "</div>";
   }

   return implode("\n", $out);
}//build_move_comments

function build_tab_GameAnalysis()
{
   global $base_path;

   return "<div class=\"ToolsArea\">\n" .
      sprintf( "%s: %s %s %s %s %s<br>\n",
         span('bold', T_('Prisoners#ged')),
         image($base_path.'17/b.gif', T_('Black'), null, 'class=InTextStone'),
         span('BlackPrisoners bold', 0),
         MED_SPACING,
         image($base_path.'17/w.gif', T_('White'), null, 'class=InTextStone'),
         span('WhitePrisoners bold', 0) ) .
      "<span id=\"GameViewer\">" .
         image($base_path.'images/start.gif', T_('First move'), null,    'id=FirstMove') .
         image($base_path.'images/prev.gif',  T_('Previous move'), null, 'id=PrevMove') .
         image($base_path.'images/next.gif',  T_('Next move'), null,     'id=NextMove') .
         image($base_path.'images/end.gif',   T_('Last move'), null,     'id=LastMove') .
      "</span>\n" .
      "</div>\n";
}


// returns true, if given move is the final score-move (predecessor = POSX_SCORE too)
function get_final_score_move( $move )
{
   if ( $move < 2 )
      return $move;

   global $TheBoard;
   if ( $move > $TheBoard->max_moves ) $move = $TheBoard->max_moves;

   list( $temp, $PosX ) = @$TheBoard->moves[$move];
   if ( $PosX != POSX_SCORE )
      return $move;
   list( $temp, $PosX ) = @$TheBoard->moves[$move-1]; // predecessor-move
   return ( $PosX == POSX_SCORE ) ? $move - 1 : $move;
}

function draw_message_box( $cfg_board, $message )
{
   $tabindex = 1;

   echo name_anchor('msgbox'),
      '<TABLE class=MessageForm>',
      '<TR class=Message>',
         '<TD>', T_('Message'), ':</TD>',
      '</TR>',
      '<TR>',
         '<TD>',
            '<textarea name="message" tabindex="', ($tabindex++), '" cols="80" rows="8">',
               textarea_safe($message), '</textarea>',
         '</TD>',
      '</TR>',
      '<TR class=Submit>',
         '<TD>',
            '<input type="submit" name="nextgame" tabindex="', ($tabindex++),
               '" value="', T_('Submit and go to next game'),
               '" accesskey="', ACCKEY_ACT_EXECUTE, '" title="[&amp;', ACCKEY_ACT_EXECUTE, ']">',
            '<input type="submit" name="nextstatus" tabindex="', ($tabindex++),
               '" value="', T_("Submit and go to status"), '">',

            SMALL_SPACING,
            '<input type="submit" name="preview" tabindex="', ($tabindex++),
               '" value="', T_('Preview'),
               '" accesskey="', ACCKEY_ACT_PREVIEW, '" title="[&amp;', ACCKEY_ACT_PREVIEW, ']">',
            MED_SPACING,
            '<input type="submit" name="cancel" tabindex="', ($tabindex++), '" value="', T_('Cancel move'), '">',
         '</TD>',
      '</TR>';

   if ( $cfg_board->get_board_flags() & BOARDFLAG_SUBMIT_MOVE_STAY_GAME )
   {
      echo
         '<TR class=Submit>', '<TD>',
            '<input type="submit" name="staygame" tabindex="', ($tabindex++), '" value="', T_('Submit and stay'), '">',
         '</TD>', '</TR>';
   }

   echo '</TABLE>', "<br>\n";
} //draw_message_box

// keep in sync with tournaments/game_admin.php#draw_add_time()-func
function draw_add_time( $game_row, $colorToMove )
{
   $info = GameAddTime::make_add_time_info( $game_row, $colorToMove );
   $tabindex=10; // NOTE: fix this start value !?

   echo name_anchor('addtime'),
      '<TABLE class=AddtimeForm>
        <TR>
          <TD>', T_('Choose how much additional time you wish to give your opponent'), ':</TD>
        </TR>
        <TR>
          <TD>
           <SELECT name="add_days" size="1"  tabindex="', ($tabindex++), '">';

   //basic_safe() because inside <option></option>
   foreach ( $info['days'] as $idx => $day_str )
   {
      echo sprintf( "<OPTION value=\"%d\"%s>%s</OPTION>\n",
                    $idx, ( ($idx==1) ? ' selected' : '' ), basic_safe($day_str) );
   }
   echo '  </SELECT>
           &nbsp;', T_('added to maintime of your opponent.'), '
          </TD>
        </TR>';

   // no byoyomi-reset if no byoyomi
   if ( $info['byo_reset'] )
   {
      echo '<TR>
              <TD>
                <input type="checkbox" checked name="reset_byoyomi" tabindex="', ($tabindex++), '" value="1"',
                   '>&nbsp;', T_('Reset byoyomi settings when re-entering'), '
              </TD>
            </TR>
            <TR><TD>',
               T_('Note: Current byoyomi period is resetted regardless of full reset.'), '
            </TD></TR>';
   }

   echo '<TR>
          <TD align=left>
<input type=submit name="nextaddtime" tabindex="', ($tabindex++), '" value="', T_('Add Time'), '"
><input type=submit name="cancel" tabindex="', ($tabindex++), '" value="', T_('Cancel'), '"
></TD>
        </TR>
      </TABLE>';
} //draw_add_time

function draw_game_info( $game_row, $game_setup, $board, $tourney )
{
   global $base_path, $show_game_tools, $gc_helper, $my_id;

   echo '<table class=GameInfos>', "\n";

   $cols = 4; // 4=icon|user+imgs|rank | prisoners
   $cols_r = $cols - 1;
   $cols_r1 = $cols; // remaining cols for one element on single line
   $to_move = get_to_move( $game_row, 'game.bad_ToMove_ID' );
   $img_tomove = SMALL_SPACING . image( $base_path.'images/backward.gif', T_('Player to move'), null, 'class="InTextImage"' );
   $all_moves = ( $board->curr_move >= $board->max_moves );
   $game_is_started = isStartedGame($game_row['Status']);
   $game_is_finished = ($game_row['Status'] === GAME_STATUS_FINISHED);

   $color_class = 'class="InTextStone"';
   if ( $game_row['Status'] == GAME_STATUS_KOMI )
   {
      $komi = NO_VALUE;

      $Handitype = $game_setup->Handicaptype;
      if ( is_htype_divide_choose($Handitype) )
      {
         $fk = new FairKomiNegotiation( $game_setup, $game_row );
         $uhandles = $fk->get_htype_user_handles();
         $color_note = GameTexts::get_fair_komi_types( $Handitype, null, $uhandles[0], $uhandles[1] );
      }
      else
         $color_note = GameTexts::get_fair_komi_types( $Handitype );
      $icon_col_b = $icon_col_w = image( $base_path.'17/y.gif', $color_note, NULL, $color_class );
   }
   else
   {
      $komi = $game_row['Komi'];
      $icon_col_b = image( $base_path.'17/b.gif', T_('Black'), null, $color_class );
      $icon_col_w = image( $base_path.'17/w.gif', T_('White'), null, $color_class );
   }


   foreach ( array( BLACK, WHITE ) as $color )
   {
      $PFX = ($color == BLACK) ? 'Black' : 'White';
      $pfx = ($color == BLACK) ? 'black' : 'white';
      $icon_col = ($color == BLACK) ? $icon_col_b : $icon_col_w;
      $col_uid = $game_row["{$PFX}_ID"];

      $user_ref = user_reference( REF_LINK, 1, '', $col_uid, $game_row["{$PFX}name"], $game_row["{$PFX}handle"] );
      $user_tomove_img = ( $to_move == $color ) ? $img_tomove : '';
      $user_off_time = echo_off_time( ($to_move == $color), $game_row["{$PFX}_OnVacation"],
         $game_row["{$PFX}_ClockUsed"], $game_row['WeekendClock'] );
      if ( !$user_off_time ) $user_off_time = '';
      $user_online = ( $my_id != $col_uid ) ? SMALL_SPACING . echo_user_online_vacation( false, $game_row["{$PFX}_Lastaccess"] ) : '';
      $user_rating = echo_rating(
         ($game_is_finished) ? $game_row["{$PFX}_End_Rating"] : $game_row["{$PFX}rating"],
         true, $col_uid );
      if ( $game_is_started )
      {
         $time_remaining = T_('Time remaining') . ": " .
            TimeFormat::echo_time_remaining( $game_row["{$PFX}_Maintime"], $game_row['Byotype'],
                  $game_row["{$PFX}_Byotime"], $game_row["{$PFX}_Byoperiods"],
                  $game_row['Byotime'], $game_row['Byoperiods'],
                  TIMEFMT_ADDTYPE | TIMEFMT_ZERO );
      }
      else
         $time_remaining = '';

      $prisoners = ($all_moves) ? $game_row["{$PFX}_Prisoners"] : $board->prisoners[$color];
      $td_prisoners = sprintf( "<td class=\"Prisoners %s right\">%s: %s</td>\n",
         $PFX, T_('Prisoners'), span('bold', $prisoners) );

      // name-info + to-move + off-time + rating
      echo "<tr class={$pfx}Info>\n", // blackInfo/whiteInfo
         "<td class=Color>", $icon_col, "</td>\n",
         "<td class=Name>", $user_ref, $user_tomove_img, $user_off_time, $user_online, "</td>\n",
         "<td class=\"Ratings right\">", $user_rating, "</td>\n", $td_prisoners, "</tr>\n";

      if ( $game_is_started )
      {
         echo "<tr class={$pfx}Info>\n",
            "<td colspan=$cols_r1>$time_remaining</td>\n</tr>\n";
      }
   }//black/white-rows

   //tournament rows
   if ( ALLOW_TOURNAMENTS && !is_null($tourney) )
   {
      $tflags_str = ($game_row['Flags'] & GAMEFLAGS_TG_DETACHED)
         ? span('TWarning', sprintf('(%s) ', T_('annulled#tourney')))
         : '';
      echo "<tr>\n",
         '<td class=Color>', echo_image_tournament_info($tourney->ID, $tourney->Title), "</td>\n",
         "<td colspan=\"$cols_r\">", $tourney->build_info(4), "</td>\n",
         "</tr>\n",
         "<tr>\n<td></td>\n",
         "<td colspan=\"$cols_r\">", $tflags_str, T_('Title'), ': ', make_html_safe($tourney->Title, true),
         "</td>\n",
         "</tr>\n";
   }

   //multi-player-game rows
   if ( $game_row['GameType'] != GAMETYPE_GO )
   {
      echo "<tr>\n",
         '<td class=Color>', echo_image_game_players($game_row['ID']), "</td>\n",
         "<td colspan=\"$cols_r\">", T_('Game Type').': ',
            GameTexts::format_game_type($game_row['GameType'], $game_row['GamePlayers']),
         "</td>\n",
         "</tr>\n";

      if ( $game_row['Moves'] > 0 )
      {
         // determine user of current/selected move + move-color for last-move with comment-info
         $mpg_user = $gc_helper->get_mpg_user();
         if ( is_array($mpg_user) )
         {
            echo "<tr>\n<td></td>\n",
               "<td colspan=\"$cols_r\">",
               "<dl class=BoardInfos><dd>", T_('Last move by:'), SMALL_SPACING,
                  ( $gc_helper->get_mpg_move_color() == GPCOL_B ? $icon_col_b : $icon_col_w),
                  MINI_SPACING,
                  user_reference( REF_LINK, 1, '', $mpg_user['uid'], $mpg_user['Name'], $mpg_user['Handle'] ),
                  SMALL_SPACING,
                  echo_rating( @$mpg_user['Rating2'], true, $mpg_user['uid'] ),
               "</dd></dl>\n",
               "</td>\n",
               "</tr>\n";
         }
      }
   }//mpg-info

   //game rows
   $sep = ', ';
   $shape_id = (int)@$game_row['ShapeID'];
   echo "<tr>\n",
      '<td class=Color>', echo_image_gameinfo($game_row['ID']), "</td>\n",
      "<td colspan=\"$cols_r\">",
         echo_image_shapeinfo($shape_id, $game_row['Size'], $game_row['ShapeSnapshot'], false, false, true),
         T_('Ruleset'), ': ', Ruleset::getRulesetText($game_row['Ruleset']),
         $sep, T_('Rated'), ': ', ( ($game_row['Rated'] == 'N') ? T_('No') : T_('Yes') ),
         $sep, T_('Handicap'), ': ', $game_row['Handicap'],
         $sep, T_('Komi'), ': ', $komi,
      "</td>\n</tr>\n";

   echo "<tr>\n",
      "<td colspan=\"$cols_r1\">", T_('Time limit'), ': ',
         TimeFormat::echo_time_limit( $game_row['Maintime'], $game_row['Byotype'],
            $game_row['Byotime'], $game_row['Byoperiods'], TIMEFMT_ADDTYPE|TIMEFMT_SHORT),
      "</td>\n",
      "</tr>\n";

   if ( isset($board) )
   {
      $txt= draw_board_info($board);
      if ( $txt )
         echo "<tr id=\"boardInfos\" class=\"BoardInfos NoPrint\"><td colspan=$cols\n>$txt</td></tr>\n";
   }

   echo "</table>\n";
} //draw_game_info

// NOTE: not drawn if no move stored yet (see Board.load_from_db-func)
function draw_board_info($board)
{
   if ( count($board->infos) <= 0 )
      return '';

   $fmt_role_from = array( WHITE => T_('White'), BLACK => T_('Black'), STONE_TD_ADDTIME => T_('Tournament director') );
   $fmt_role_to   = array( BLACK => T_('White'), WHITE => T_('Black') );

   $board_infos = array();
   foreach ( $board->infos as $row )
   {
      list( $key, $move_nr, $role_from, $role_to, $hours, $byo_reset ) = $row;
      if ( $key == POSX_ADDTIME )
      {
         $arr_time = array();
         if ( $hours > 0 )
            $arr_time[] = '+' . TimeFormat::echo_time($hours);
         if ( $byo_reset )
            $arr_time[] = T_('byoyomi reset#addtime');

         $board_infos[] = sprintf( T_('Time changed at move %s (%s): %s -> %s#addtime'),
            $move_nr, implode(', ', $arr_time), $fmt_role_from[$role_from], $fmt_role_to[$role_to] );
      }
   }

   return ( count($board_infos) > 0 )
      ? "<dl class=\"BoardInfos\">\n<dd>" . implode('</dd><dd>', $board_infos) . "</dd></dl>\n"
      : '';
} //draw_board_info


// $collapsed==null used to skip hide-notes for JS-based game-tools
function draw_notes( $collapsed='N', $notes='', $height=0, $width=0)
{
   if ( $collapsed === 'Y' )
   {
      //echo textarea_safe( $notes) . "\n";
      echo '<INPUT type="HIDDEN" name="hidenotes" value="N">'
         , "  <input name=\"togglenotes\" type=\"submit\" value=\"", T_('Show notes'), "\" class=NoPrint>";
      return;
   }

   echo " <table class=GameNotes>\n"
      , "  <tr><th>", T_('Private game notes'), "</th></tr>\n"
      , "  <tr><td class=Notes>\n"
      , "   <textarea name=\"gamenotes\" id=\"gameNotes\" cols=\"$width\" rows=\"$height\">",
            textarea_safe( $notes), "</textarea>\n"
      , "  </td></tr>\n"
      , "  <tr><td class=NoPrint>"
      , "<input name=\"savenotes\" id=\"saveNotes\" type=\"submit\" value=\"", T_('Save notes'), "\">";

   if ( $collapsed === 'N' )
   {
      echo '<INPUT type="HIDDEN" name="hidenotes" value="Y">'
         , "<input name=\"togglenotes\" type=\"submit\" value=\"", T_('Hide notes'), "\">";
   }

   echo "</td></tr>\n"
      , "</table>\n";
} //draw_notes

function draw_fairkomi_negotiation( $my_id, &$form, $grow, $game_setup )
{
   $fk = new FairKomiNegotiation($game_setup, $grow);

   $black_id = $grow['Black_ID'];
   $white_id = $grow['White_ID'];
   $user[0] = user_reference( 0, 1, '', $black_id, @$grow['Blackname'], @$grow['Blackhandle'] );
   $user[1] = user_reference( 0, 1, '', $white_id, @$grow['Whitename'], @$grow['Whitehandle'] );

   $req_komibid = @$_REQUEST['komibid'];
   $show_bid[0] = $fk->get_view_komibid( $my_id, $black_id, $form, $req_komibid );
   $show_bid[1] = $fk->get_view_komibid( $my_id, $white_id, $form, $req_komibid );
   $errors = ( isset($_REQUEST['komibid']) )
      ? $fk->check_komibid($req_komibid, $my_id)
      : array();

   section('fairkomi', T_('Komi negotiation for Fair Komi') );

   $fairkomi_type = GameTexts::get_fair_komi_types($game_setup->Handicaptype);
   echo_notes( 'fk_notes', sprintf( T_('Negotiation process for "%s":#fairkomi'), $fairkomi_type ),
      $fk->build_notes_fair_komi(), false );

   if ( count($errors) )
   {
      $form->add_row( array(
            'DESCRIPTION', T_('Errors'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $form->add_empty_row();
      echo "<hr>\n", $form->create_form_string();
   }

   $fk->echo_fairkomi_table( $form, $user, $show_bid, $my_id );
}//draw_fairkomi_negotiation


function draw_conditional_moves_links( $gid, $my_id, $move_seq )
{
   global $base_path;
   $link_base = $base_path."game.php?gid=$gid".URI_AMP.'cma=';
   $has_cm = !is_null($move_seq);

   echo T_('Conditional moves'), ': ';
   if ( $has_cm )
      echo anchor( $link_base.'show', T_('Show#condmoves') ), SEP_MEDSPACING;
   if ( $has_cm )
      echo anchor( $link_base.'edit', T_('Edit#condmoves') );
   else
      echo anchor( $link_base.'edit', T_('Add#condmoves') );
   echo "<br><br>\n";
}//draw_conditional_moves_links


function handle_conditional_moves( &$move_seq, $game_row, $cm_action, &$board, $to_move, $my_id, $is_my_turn )
{
   $gid = $game_row['ID'];
   $Size = $game_row['Size'];
   $my_col = ($game_row['Black_ID'] == $my_id) ? BLACK : WHITE;

   $new_action = $new_coord = $cm_start_move = '';

   $var_view = get_request_arg('cm_var_view', '1');
   if ( !$var_view )
      $var_view = '1';

   if ( !is_null($move_seq) )
   {
      $db_cond_moves = $move_seq->Sequence;
      $cnt_replay_moves = $move_seq->StartMoveNr - 1;
      $check_nodes = true;

      if ( $cm_action == 'edit' && $move_seq->Status == MSEQ_STATUS_ACTIVE ) // inactivate on edit
      {
         $move_seq->setStatus( MSEQ_STATUS_INACTIVE );
         $move_seq->update();
      }
   }
   else
   {
      $db_cond_moves = '';
      $cnt_replay_moves = $game_row['Moves'];
      $check_nodes = false;
   }
   $cond_moves = trim( get_request_arg('cond_moves', $db_cond_moves) );

   $cm_src_manual = true;
   if ( @$_REQUEST['cma_upload'] && isset($_FILES['cm_sgf_file']) ) // upload SGF from file
   {
      list( $errors, $sgf_data, $game_sgf_parser ) =
         ConditionalMoves::load_cond_moves_from_sgf( $_FILES['cm_sgf_file'], $game_row, $board );
      if ( count($errors) == 0 )
      {
         // re-parse & re-format conditional-moves part for input-box
         $cond_moves = SgfParser::sgf_builder( array( $game_sgf_parser->sgf_game_tree ), '', '', '',
            'ConditionalMoves::sgf_strip_cond_moves_notes' );
         $cm_src_manual = false;
         $check_nodes = true;
      }
   }
   else
      $errors = array();

   if ( !$cond_moves && ( @$_REQUEST['cm_preview'] || @$_REQUEST['cm_save'] || @$_REQUEST['cm_activate'] ) )
      $errors[] = T_('Missing conditional moves.');


   $var_names = array();
   $sgf_parser = new SgfParser( SGFP_OPT_SKIP_ROOT_NODE );
   if ( $cond_moves && ( $check_nodes || @$_REQUEST['cm_preview'] || @$_REQUEST['cm_save'] || @$_REQUEST['cm_activate'] ) )
   {
      if ( $cm_src_manual )
         $cond_moves = ConditionalMoves::reformat_to_sgf( $cond_moves, $Size, ($to_move == BLACK) );

      if ( $sgf_parser->parse_sgf($cond_moves) )
      {
         if ( count($sgf_parser->games) == 0 )
            $errors[] = T_('Can\'t detect conditional moves.');
         else
         {
            // check syntax of cond-moves
            $game_tree = $sgf_parser->games[0];
            $gchkmove = GameCheckMove::prepare_game_check_move_board_start( $gid, $Size, $game_row['ShapeSnapshot'] );
            $gchkmove->replay_moves( $board->moves, $cnt_replay_moves );
            $preview_gchkmove = clone $gchkmove;
            list( $errors, $var_names, $cond_moves_sgf_coords ) =
               ConditionalMoves::check_nodes_cond_moves( $game_tree, $gchkmove, $Size, $my_col );

            // reformat cond-moves with board-coords for display in edit-box
            if ( count($errors) == 0 )
            {
               $cond_moves = SgfParser::sgf_builder( array( $game_tree ), "\r\n", ' ', '',
                  'SgfParser::sgf_convert_move_to_board_coords', $Size );
            }

            if ( @$_REQUEST['cm_preview'] && $var_view && count($errors) == 0 ) // show selected CM-variation on board
            {
               $result = ConditionalMoves::extract_variation( $game_tree, $var_view, $Size );
               if ( is_array($result) )
               {
                  $board->set_conditional_moves( $result );
                  $err = ConditionalMoves::add_played_conditional_moves_on_board( $board, $preview_gchkmove, $result );
                  if ( $err )
                     $errors[] = $err;
               }
               else
                  $errors[] = $result;
            }

            if ( (@$_REQUEST['cm_save'] || @$_REQUEST['cm_activate']) && count($errors) == 0 ) // save cond-moves
            {
               $cm_id = ( is_null($move_seq) ) ? 0 : $move_seq->ID; // NEW or EDIT
               $cm_flags = 0;
               $cm_private = get_request_arg('cm_private', 0);
               if ( $cm_private )
                  $cm_flags |= MSEQ_FLAG_PRIVATE;
               $cm_status = ( @$_REQUEST['cm_activate'] && !$is_my_turn ) ? MSEQ_STATUS_ACTIVE : MSEQ_STATUS_INACTIVE;

               if ( $cm_id ) // EDIT (reset start-/last-move to start-move)
               {
                  $cm_start_move_nr = $move_seq->StartMoveNr;
                  $cm_start_move = $move_seq->StartMove;
               }
               else // NEW
               {
                  $cm_start_move_nr = $game_row['Moves'] + 1;
                  $cm_start_move = ConditionalMoves::get_nodes_start_move_sgf_coords( $game_tree, $Size );
               }
               $cm_moveseq = new MoveSequence( $cm_id, $gid, $my_id, $cm_status, $cm_flags, 0,
                  $cm_start_move_nr, $cm_start_move, $cm_start_move_nr, 1, $cm_start_move, $cond_moves_sgf_coords );

               ta_begin();
               {//HOT-section to save conditional-moves
                  if ( $cm_moveseq->persist() )
                  {
                     $_REQUEST['sysmsg'] = ( $cm_moveseq->Status == MSEQ_STATUS_ACTIVE )
                         ? T_('Conditional moves saved and activated!')
                         : T_('Conditional moves saved, but not activated!');
                     $move_seq = $cm_moveseq;
                  }
               }
               ta_end();
            }
         }
      }//parse-sgf
   }


   // show first move to submit on CM-activate
   if ( count($errors) == 0 && @$_REQUEST['cm_activate'] && $cm_start_move )
   {
      $new_coord = substr( $cm_start_move, 1 );
      $new_action = ( $new_coord ) ? GAMEACT_DO_MOVE : GAMEACT_PASS;
   }

   return array( $sgf_parser, $errors, $var_names, $cond_moves, $new_action, $new_coord );
}//handle_conditional_moves

function draw_conditional_moves_input( &$gform, $gid, $my_id, $cm_action, $move_seq, $sgf_parser, $errors, $cond_moves, $var_names )
{
   global $base_path, $is_my_turn;

   $is_show = ( $cm_action == 'show' );
   $has_cm = !is_null($move_seq);
   $attbs_disabled = ( $is_show ) ? 'disabled=1' : '';

   if ( is_null($move_seq) )
      $move_seq = new MoveSequence( 0, $gid, $my_id );

   $var_view = get_request_arg('cm_var_view', '1');
   if ( !$var_view )
      $var_view = '1';
   $cm_private = get_request_arg('cm_private', ( ($move_seq->Flags & MSEQ_FLAG_PRIVATE) ? 1 : 0) );

   $var_views_str = implode(' , ', $var_names);

   echo name_anchor('condmoves'),
      "<TABLE id=\"CondMovesTable\" class=MessageForm>\n";

   if ( $sgf_parser->error_msg )
   {
      echo "<TR class=Error>\n",
            '<TD class=Rubric>', T_('Parse errors#condmoves'), ":</TD>\n",
            '<TD colspan="2">', $sgf_parser->error_loc, "<br><br>\n", "</TD>\n",
         '</TR>';
   }
   if ( count($errors) )
   {
      echo "<TR class=Error>\n",
            '<TD class=Rubric>', T_('Errors'), ":</TD>\n",
            '<TD colspan="2">', implode("<br>\n", $errors), "<br><br>\n", "</TD>\n",
         '</TR>';
   }

   $cm_lines = max( 1, min( 4, max( (int)(strlen($cond_moves)/60) + 1, substr_count($cond_moves, "\n") ) ) );

   echo
      "<TR>\n",
         '<TD class=Rubric>', T_('Conditional moves'), ":</TD>\n",
         '<TD colspan="2">',
            anchor( $base_path."sgf.php?gid=$gid".URI_AMP."cm=1".URI_AMP."no_cache=1", T_('Download SGF with conditional moves')),
            ( $has_cm && $is_show
               ? SEP_MEDSPACING . anchor( $base_path."game.php?gid=$gid".URI_AMP.'cma=edit', T_('Edit#condmoves'))
               : '' ),
         "</TD>\n",
      '</TR>';
   if ( !$is_show )
   {
      echo "<TR>\n",
            "<TD></TD>\n",
            '<TD colspan="2">',
               $gform->print_insert_file( 'cm_sgf_file', 12, SGF_MAXSIZE_UPLOAD, 'application/x-go-sgf', true ),
               MED_SPACING,
               $gform->print_insert_submit_button( 'cma_upload', T_('Upload SGF with conditional moves') ),
            "</TD>\n",
         '</TR>';
   }
   echo
      "<TR>\n",
         '<TD class=Rubric>', ( $is_show ? T_('Sequence#condmoves') : T_('Edit sequence') ), ":</TD>\n",
         '<TD colspan="2" ', ($is_show ? 'class=CMSequence>' : '>'),
            ( $is_show
               ? span('CMSequence', wordwrap( str_replace("\n", "<br>\n", $cond_moves), 80, "<br>\n", false ))
               : $gform->print_insert_textarea( 'cond_moves', 80, $cm_lines, $cond_moves, $attbs_disabled ) ),
         "</TD>\n",
      '</TR>',
      "<TR class=Vars>\n",
         '<TD class=Rubric>', T_('Variations#condmoves'), ":</TD>\n",
         '<TD>', $var_views_str, "</TD>\n",
         '<TD class="Preview">',
            $gform->print_insert_text_input( 'cm_var_view', 6, 16, $var_view ),
            $gform->print_insert_submit_buttonx( 'cm_preview', T_('Preview variation#condmoves'),
                  array( 'accesskey' => ACCKEY_ACT_PREVIEW, 'title' => '[&amp;'.ACCKEY_ACT_PREVIEW.']' )),
         "</TD>\n",
      '</TR>',
      "<TR class=Attr>\n",
         '<TD class=Rubric>', T_('Status#condmoves'), ' / ', T_('Attributes#condmoves'), ":</TD>\n",
         '<TD colspan="2">',
            span('EmphasizeWarn', MoveSequence::getStatusText($move_seq->Status)),
            SMALL_SPACING, SMALL_SPACING,
            $gform->print_insert_checkbox( 'cm_private', '1', T_('Private (after game finished)#condmoves'), $cm_private, $attbs_disabled ),
         "</TD>\n",
      '</TR>',
      "<TR class=Submit>\n",
         "<TD></TD>\n",
         '<TD>',
            $gform->print_insert_submit_buttonx( 'cm_activate',
               ( $is_my_turn ? T_('Save to activate#condmoves') : T_('Activate#condmoves') ), $attbs_disabled ),
            SMALL_SPACING,
            $gform->print_insert_submit_buttonx( 'cm_save', T_('Save inactive#condmoves'), $attbs_disabled ),
         "</TD>\n",
         '<TD class="Cancel">',
            $gform->print_insert_submit_button( 'cancel', T_('Cancel') ),
         "</TD>\n",
      '</TR>',
      "</TABLE><br>\n";
}//draw_conditional_moves_input

function build_last_move_title( $gc_helper, $game_row, $curr_move_arr )
{
   static $ARR_STONE_COLOR = array( BLACK => 'Black', WHITE => 'White' );

   $mpg_user = $gc_helper->get_mpg_user();
   if ( is_array($mpg_user) )
      $last_move_user_str = user_reference( 0, 1, '', $mpg_user['uid'], $mpg_user['Name'], $mpg_user['Handle'] );
   else
   {
      $PFX = ( is_array($curr_move_arr) )
         ? @$ARR_STONE_COLOR[$curr_move_arr[0]] // list( $Stone, $PosX, $PosY) = $curr_move_arr
         : 0;
      if ( !$PFX )
         $PFX = ( $game_row['ToMove_ID'] == $game_row['Black_ID'] ) ? 'White' : 'Black';
      $last_move_user_str = user_reference( 0, 1, '',
         $game_row["{$PFX}_ID"], $game_row["{$PFX}name"], $game_row["{$PFX}handle"] );
   }

   return sprintf(T_('Message from %s#gamemsg'), $last_move_user_str) . ':';
}//build_last_move_title

?>

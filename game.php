<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
if ( ENABLE_STDHANDICAP ) {
   require_once 'include/sgf_parser.php';
}
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

     toggleobserve=y|n  : toggle observing game
     movechange=1&gotomove= : view selected move (for view-move selectbox)
     move|m=            : view specific move (alternative for selecting move)
*/
   // NOTE: using page: confirm.php
   // NOTE: allowed for guest-user: toggle-observe

   $gid = (int)get_alt_arg( 'gid', 'g');
   $action = (string)get_alt_arg( 'action', 'a');
   $arg_move = get_alt_arg( 'move', 'm'); //move number, incl. MOVE_SETUP for shape-game
   $coord = (string)get_alt_arg( 'coord', 'c');
   $stonestring = (string)get_alt_arg( 'stonestring', 's');
   $preview = (bool)@$_REQUEST['preview'];

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
      $may_play = ( $logged_in && $my_id == $ToMove_ID ) ;
      if ( !$action )
         $action = 'negotiate_komi';
   }
   elseif ( $Status == GAME_STATUS_FINISHED || $move < $Moves )
      $may_play = false;
   else
   {
      $may_play = ( $logged_in && $my_id == $ToMove_ID ) ;
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
   $may_del_game  = $my_game && $too_few_moves && isStartedGame($Status) && ( $tid == 0 ) && !$is_mp_game;

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

   $extra_infos = array();
   $game_score = null;

   if ( $just_looking || $is_guest ) //no process except 'movechange'
   {
      $validation_step = false;
      $may_play = false;
      if ( $Status == GAME_STATUS_FINISHED )
      {
         if ( abs($Score) <= SCORE_MAX && $move == $Moves ) // don't calc for resign/time-out/forfeit
         {
            $score_board = clone $TheBoard;
            list( $score, $game_score ) =
               GameActionHelper::calculate_game_score( $score_board, $stonestring, $Ruleset, $coord );
         }
         $admResult = ( $GameFlags & GAMEFLAGS_ADMIN_RESULT ) ? sprintf(' (%s)', T_('set by admin#game')) : '';
         $extra_infos[score2text($Score, true) . $admResult] = 'Score';
      }
      elseif ( $TheBoard->is_scoring_step($move, $Status) )
      {
         $score_board = clone $TheBoard;
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
            $gchkmove->check_move( $coord, $to_move, $Last_Move, $GameFlags );
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

            $extra_infos[T_('Preliminary Score') . ": " . score2text($score, true)] = 'Score';

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

            $extra_infos[T_('Preliminary Score') . ": " . score2text($score, true)] = 'Score';
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

   $cnt_attached_sgf = ( $GameFlags & GAMEFLAGS_ATTACHED_SGF ) ? GameSgfControl::count_cache_game_sgfs( $gid ) : 0;


   $title = T_("Game") ." #$gid,$arg_move";
   start_page($title, 0, $logged_in, $player_row, $TheBoard->style_string(), null, $js);



   $jumpanchor = ( $validation_step ) ? '#msgbox' : '';
   echo "\n<FORM name=\"game_form\" action=\"game.php?gid=$gid$jumpanchor\" method=\"POST\">";
   $gform = new Form( 'game_form', "game.php?gid=$gid$jumpanchor", FORM_POST, false );
   $gform->set_config(FEC_BLOCK_FORM, true);
   $page_hiddens = array();
   // [ game_form start

   echo "\n<table id=GamePage align=center>\n<tr><td>"; //board & associates table {--------

   if ( $movenumbers>0 )
   {
      $movemodulo = $cfg_board->get_move_modulo();
      if ( $movemodulo >= 0 )
      {
         $TheBoard->move_marks( $move - $movenumbers, $move, $movemodulo );
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
   if( !is_null($game_score) )
   {
      GameScore::draw_score_box( $game_score, $Ruleset );
      //FIXME for debugging show other ruleset:
      //$other_ruleset = ( $Ruleset == RULESET_JAPANESE ) ? RULESET_CHINESE : RULESET_JAPANESE;
      //GameScore::draw_score_box( $game_score, $other_ruleset );
   }
   echo "</td><td>";

   if ( $is_fairkomi_negotiation )
      draw_fairkomi_negotiation( $my_id, $gform, $game_row, $game_setup );
   else
      $TheBoard->draw_board( $may_play, $action, $stonestring, ( $show_game_tools ? '' : $last_move_msg ) );

   //messages about actions
   if ( $validation_step )
      $extra_infos[T_('Hit "Submit" to confirm')] = 'Guidance';

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
         draw_message_box( $message );

         if ( $preview )
         {
            $preview_msg = make_html_safe( $message, 'gameh' );
            $preview_msg = MarkupHandlerGoban::replace_igoban_tags( $preview_msg );
            $TheBoard->draw_move_message( $preview_msg );
         }
      }
   }
   else if ( $Moves > 1 || $is_shape )
   {
      if ( !$show_game_tools )
         draw_moves( $gid, $arg_move, $game_row['Handicap'] );
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
   if ( $Status == GAME_STATUS_FINISHED && ($GameFlags & GAMEFLAGS_HIDDEN_MSG) )
      echo MED_SPACING . echo_image_gamecomment( $gid );
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

   echo build_hidden( $page_hiddens);
   echo "\n</FORM>";

   echo "\n<HR>";
   if ( !$show_game_tools )
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
         //global $has_sgf_alias;
         $menu_array[T_('Download sgf')] = ( $has_sgf_alias ? "game$gid.sgf" : "sgf.php?gid=$gid" );

         if ( ($my_game || $my_mpgame) && ($Moves>0 || $is_shape) && !$has_sgf_alias )
            $menu_array[T_('Download sgf with all comments')] = "sgf.php?gid=$gid".URI_AMP."owned_comments=1" ;
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
               if ( $PosX < 0) // TIME|FORFEIT, etc. (no user "move")
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

   echo '<span class="SgfMove">', sprintf( T_('(SGF-Move %s)'), $sgf_move ), '</span>&nbsp;';

   // add selectbox to show specific move
   echo "<SELECT name=\"gotomove\" size=\"1\"";
   if ( is_javascript_enabled() )
   {
      echo " onchange=\"javascript:this.form['movechange'].click();\"";
   }
   echo ">\n$str</SELECT>";
   echo '<INPUT type="HIDDEN" name="gid" value="' . $gid . "\" class=NoPrint>";
   echo '<INPUT type="submit" name="movechange" value="' . T_('View move') . "\" class=NoPrint>";
} //draw_moves

function draw_game_tools()
{
   global $base_path, $game_row, $game_setup, $TheBoard, $tourney, $notes;
   $img_chg_note = image($base_path."images/star3.gif", T_('Notes have unsaved changes!#ged'), null, 'id=NotesChanged');


   echo "</td>\n",
      "<td id=ToolsArea class=\"GameTools NoPrint\">",
         "<div id=tabs>\n",
            "<ul>\n",
               "<li>", anchor('#tab_GameInfo', T_('Game Info#ged'), T_('Game Information#ged')), "</li>\n",
               "<li>", anchor('#tab_GameNotes', T_('Notes#ged') . ' ' . $img_chg_note, T_('Private game notes')), "</li>\n",
               "<li>", anchor('#tab_GameAnalysis', T_('Analyse#ged'), T_('Analyse game#ged')), "</li>\n",
            "</ul>\n",
            "<div id=tab_GameInfo class=tab>\n";
   draw_game_info($game_row, $game_setup, $TheBoard, $tourney); // with board-info
   echo     "</div>\n",
            "<div id=tab_GameNotes class=tab>\n";
   draw_notes(null, $notes, 12, 65); // use fixed size
   echo     "</div>\n",
            "<div id=tab_GameAnalysis class=\"tab\">\n", build_tab_GameAnalysis(), "</div>\n",
         "</div>\n",
         "<div id=GameMessage>\n",
            "<div id=GameMessageHeader>", T_('Messages#ged'), build_comment_tools(), "</div>\n",
            "<div id=GameMessageBody>\n", build_move_comments(), "</div>\n",
         "</div>\n";
}//draw_game_tools

function build_comment_tools()
{
   global $base_path;
   return " <div id=GameMessageTools>"
      . image($base_path."images/up.png", T_('Show my previous move#ged'), null, 'id=GameMsgTool_GoToMyPreviousMove')
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
            if ( $PosX < 0) continue; // TIME|FORFEIT, etc. (no user "move")
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

function draw_message_box( $message )
{
   $tabindex = 1;

   echo name_anchor('msgbox'),
      '<TABLE class=MessageForm>',
      '<TR class=Message>',
      '<TD class=Rubric>', T_('Message'), ':</TD>',
      '<TD colspan="2"><textarea name="message" tabindex="', ($tabindex++), '" cols="70" rows="8">',
         textarea_safe($message), '</textarea></TD>',
      '</TR>',
      '<TR class=Submit>',
      '<TD></TD>',
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
      '</TD>',
      '<TD class="Cancel">',
         '<input type=submit name="cancel" tabindex="', ($tabindex++), '" value="', T_('Cancel move'), '">',
      '</TD>',
      '</TR>',
      '</TABLE>',
      "<br>\n";
} //draw_message_box

// keep in sync with tournaments/game_admin.php#draw_add_time()-func
function draw_add_time( $game_row, $colorToMove )
{
   $info = GameAddTime::make_add_time_info( $game_row, $colorToMove );
   $tabindex=10; // NOTE: fix this start value !?

   echo '
    <a name="addtime"></a>
      <TABLE class=AddtimeForm>
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
   global $base_path, $show_game_tools, $gc_helper;

   echo '<table class=GameInfos>', "\n";

   // with JS-game-tools put prisoner-info into 2nd-line with time-remaining & indent lines on starting-icons
   // otherwise: prisoners in 1st-line and not always indent (e.g. time-info)

   $cols = ( $show_game_tools ) ? 3 : 4; // 3=icon|user+imgs|rank, 4=(like 3) | prisoners
   $cols_r = $cols - 1;
   $cols_r1 = ( $cols == 4 ) ? $cols : $cols_r; // remaining cols for one element on single line
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


   foreach( array( BLACK, WHITE ) as $color )
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
         "<td class=Name>", $user_ref, $user_tomove_img, $user_off_time, "</td>\n",
         "<td class=\"Ratings right\">", $user_rating, "</td>\n",
         ( $cols == 4 ? $td_prisoners : '' ),
         "</tr>\n";

      if ( $game_is_started || $cols == 3 )
      {
         echo "<tr class={$pfx}Info>\n",
            "<td colspan=$cols_r1>$time_remaining</td>\n",
            ( $cols == 4 ? '' : $td_prisoners ),
            "</tr>\n";
      }
   }//black/white-rows

   //tournament rows
   if ( ALLOW_TOURNAMENTS && !is_null($tourney) )
   {
      $tflags_str = ($game_row['GameFlags'] & GAMEFLAGS_TG_DETACHED)
         ? span('TWarning', sprintf('(%s) ', T_('detached#tourney')))
         : '';
      echo "<tr>\n",
         '<td class=Color>', echo_image_tournament_info($tourney->ID), "</td>\n",
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
      ( $cols == 3
         ? "</td>\n</tr>\n" .
           "<tr>\n<td></td>\n" .
           "<td colspan=\"$cols_r\">"
         : $sep ),
         T_('Handicap'), ': ', $game_row['Handicap'],
         $sep, T_('Komi'), ': ', $komi,
      "</td>\n</tr>\n";

   echo "<tr>\n",
      ( $cols == 4 ? '' : "<td></td>\n" ),
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

   $fmts= array(
      //array(POSX_ADDTIME, $MoveNr, TimeFrom, TimeTo(=$Stone), $Hours, ByoReset);
      POSX_ADDTIME => array(
         array( T_('%2$s had added %4$s to %3$s %5$s at move %1$d'),
                T_('%2$s had restarted byoyomi for %3$s at move %1$d') ),
         // [ colnum, mapping ]
         array( 0, null), //MoveNr
         array( 1, array( WHITE => T_('White'), BLACK => T_('Black'), //From
                          STONE_TD_ADDTIME => T_('Tournament director') )),
         array( 2, array( BLACK => T_('White'), WHITE => T_('Black'))), //To
         array( 3, 'string(TimeFormat::echo_time)'), //Hours
         array( 4, array( 0 => '', 1 => T_('and restarted byoyomi'))), //Reset
      ),
   );

   $txt= '';
   foreach ( $board->infos as $row )
   {
      $key = array_shift($row);
      $sub = @$fmts[$key];
      if ( $sub )
      {
         //echo var_export($row, true);
         $fmtarr = array_shift($sub);
         $fmt = (is_array($fmtarr)) ? $fmtarr[($row[2] > 0) ? 0 : 1] : $fmtarr;
         $val = array();
         $cnt_sub = count($sub);
         for ( $i=0; $i < $cnt_sub; $i++ )
         {
            list($col, $fct) = $sub[$i];
            //echo "$col=> $tmp<br>";
            if ( is_array($fct) )
               $val[$i] = $fct[$row[$col]];
            else if ( is_string($fct) )
               $val[$i] = TimeFormat::echo_time($row[$col]);
            else
               $val[$i] = $row[$col];
         }
         //echo var_export($val, true);
         $str= vsprintf($fmt, $val);
         if ( $str )
            $txt.= "<dd>$str</dd>\n";
      }
   }
   if ( $txt )
      $txt= "<dl class=\"BoardInfos\">\n$txt</dl>\n";
   return $txt;
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
      $fk->build_notes(), false );

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

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
if( @$_REQUEST['nextgame']
      || @$_REQUEST['nextstatus']
      || @$_REQUEST['cancel']
      || @$_REQUEST['nextskip']
      || @$_REQUEST['nextaddtime']
   )
{
//confirm use $_REQUEST: gid, move, action, coord, stonestring
   include_once( "confirm.php");
   exit; //should not be executed
}

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( 'include/classlib_userconfig.php' );
require_once( "include/game_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
require_once( 'include/classlib_game.php' );
require_once( 'include/time_functions.php' );
require_once( "include/rating.php" );
if( ENA_STDHANDICAP ) {
require_once( "include/sgf_parser.php" );
}

// abbreviations used to reduce file size
function get_alt_arg( $n1, $n2)
{
// $_GET must have priority at least for those used in the board links
// for instance, $_POST['coord'] is the current (last used) coord
// while $_GET['coord'] is the coord selected in the board (next coord)

   if( isset( $_GET[$n1]) )
      return $_GET[$n1];
   if( isset( $_GET[$n2]) )
      return $_GET[$n2];

   if( isset( $_POST[$n1]) )
      return $_POST[$n1];
   if( isset( $_POST[$n2]) )
      return $_POST[$n2];

   return '';
}

{

   $gid = (int)get_alt_arg( 'gid', 'g');
   $action = (string)get_alt_arg( 'action', 'a');
   $move = (int)get_alt_arg( 'move', 'm'); //move number
   $coord = (string)get_alt_arg( 'coord', 'c');
   $stonestring = (string)get_alt_arg( 'stonestring', 's');
   $preview = (bool)@$_REQUEST['preview'];

   $message = get_request_arg( 'message');

   disable_cache();

   connect2mysql();

   if( $gid <= 0 )
      error('unknown_game');

   $logged_in = who_is_logged( $player_row);
   if( $logged_in )
   {
      $my_id = $player_row['ID'];
      $cfg_board = ConfigBoard::load_config_board($my_id);
   }
   else
   {// for quick-suite
      $my_id = 0;
      $cfg_board = new ConfigBoard($my_id); // use defaults
   }


   $query= "SELECT Games.*, " .
           "Games.Flags+0 AS GameFlags, " . //used by check_move
           "black.Name AS Blackname, " .
           "black.Handle AS Blackhandle, " .
           "black.OnVacation AS Black_OnVacation, " .
           "black.ClockUsed AS Black_ClockUsed, " .
           "black.Rank AS Blackrank, " .
           "black.Rating2 AS Blackrating, " .
           "black.RatingStatus AS Blackratingstatus, " .
           "white.Name AS Whitename, " .
           "white.Handle AS Whitehandle, " .
           "white.OnVacation AS White_OnVacation, " .
           "white.ClockUsed AS White_ClockUsed, " .
           "white.Rank AS Whiterank, " .
           "white.Rating2 AS Whiterating, " .
           "white.RatingStatus AS Whiteratingstatus " .
           "FROM Games, Players AS black, Players AS white " .
           "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID";

   if( !($game_row=mysql_single_fetch( 'game.findgame', $query)) )
      error('unknown_game','game.findgame');

   extract($game_row);

   if( $Status == 'INVITED' )
      error('unknown_game','game.invited');


   if( @$_REQUEST['movechange'] )
      $move = (int)@$_REQUEST['gotomove'];
   if( $move<=0 )
      $move = $Moves;

   if( $Status == 'FINISHED' || $move < $Moves )
   {
      $may_play = false;
      $just_looking = true;
   }
   else
   {
      $may_play = ( $logged_in && $my_id == $ToMove_ID ) ;
      $just_looking = !$may_play;
      if( $may_play )
      {
         if( !$action )
         {
            if( $Status == 'PLAY' )
            {
               if( $Handicap>1 && $Moves==0 )
                  $action = 'handicap';
               else
                  $action = 'choose_move';
            }
            else if( $Status == 'PASS' )
            {
               $action = 'choose_move';
            }
            else if( $Status == 'SCORE' || $Status == 'SCORE2' )
               $action = 'remove';
         }
      }
   }

   // ??? no more useful: equ (!$just_looking && !$may_play) which is nearly ($may_play && !$may_play)
   if( !$just_looking && !($logged_in && $my_id == $ToMove_ID) )
      error('not_your_turn');

   // allow validation
   if( $just_looking && ( $action == 'add_time' || $action == 'delete' || $action == 'resign' ) )
      $just_looking = false;

   $my_game = ( $logged_in && ( $my_id == $Black_ID || $my_id == $White_ID ) );

   if( !$logged_in || $my_game || ($Status == 'FINISHED') )
      $my_observe = null;
   else
   {
      $my_observe = is_on_observe_list( $gid, $my_id);
      if( @$_REQUEST['toggleobserve'] == ($my_observe ? 'N' : 'Y') )
      {
         //TODO: weakness: toggle_observe_list() recall is_on_observe_list()!
         toggle_observe_list($gid, $my_id);
         $my_observe = !$my_observe;
      }
   }
   $has_observers = has_observers( $gid);


   $is_running_game = ($Status == 'PLAY' || $Status == 'PASS' || $Status == 'SCORE' || $Status == 'SCORE2' );

   $too_few_moves = ( $Moves < DELETE_LIMIT+$Handicap );
   $may_del_game  = $my_game && $too_few_moves && $is_running_game;

   $may_resign_game = ( $action == 'choose_move') || ( $my_game && $is_running_game && ( $action == '' || $action == 'resign' ) );

   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else if( $ToMove_ID )
      error('database_corrupted', "game.bad_ToMove_ID($gid)");

   if( $Status != 'FINISHED' && ($Maintime > 0 || $Byotime > 0) )
   {
      // LastTicks may handle -(time spend) at the moment of the start of vacations
      $hours = ticks_to_hours(get_clock_ticks($ClockUsed) - $LastTicks);

      if( $to_move == BLACK )
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

   $may_add_time = $my_game && allow_add_time_opponent( $game_row, $my_id);


   $no_marked_dead = ( $Status == 'PLAY' || $Status == 'PASS' ||
                       $action == 'choose_move' || $action == 'domove' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, $move, $no_marked_dead) )
      error('internal_error', "game.load_from_db($gid)");
   $movecol= $TheBoard->movecol;
   $movemsg= $TheBoard->movemsg;

   $extra_infos = array();
   $game_score = null;

   if( $just_looking ) //no process except 'movechange'
   {
      $validation_step = false;
      $may_play = false;
      if( $Status == 'FINISHED' )
      {
         if( abs($Score) <= SCORE_MAX && $move == $Moves ) // don't calc for resign/time-out
         {
            $game_score = check_remove( $TheBoard, GSMODE_TERRITORY_SCORING, $coord); //ajusted globals: $stonestring
            $game_score->calculate_score();
         }
         $extra_infos[score2text($Score, true)] = 'Score';
      }
   }
   else switch( (string)$action )
   {
      case 'choose_move': //single input pass
      {
         if( !$is_running_game ) //after resume
            error('invalid_action',"game.choose_move.$Status");

         $validation_step = false;
      }
      break;

      case 'domove': //for validation after 'choose_move'
      {
         if( !$is_running_game ) //after resume
            error('invalid_action',"game.domove.$Status");

         $validation_step = true;
{//to fix old way Ko detect. Could be removed when no more old way games.
  if( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
}
         check_move( $TheBoard, $coord, $to_move);
//ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)
         $game_row['Black_Prisoners'] = $Black_Prisoners;
         $game_row['White_Prisoners'] = $White_Prisoners;

         $stonestring = '';
         foreach($prisoners as $tmp)
         {
            list($x,$y) = $tmp;
            $stonestring .= number2sgf_coords($x, $y, $Size);
         }

         if( strlen($stonestring) != $nr_prisoners*2 )
            error('move_problem');

         $TheBoard->set_move_mark( $colnr, $rownr);
         //$coord must be kept for validation by confirm.php
      }
      break;

      case 'handicap': //multiple input step + validation
      {
         if( $Status != 'PLAY' || !( $Handicap>1 && $Moves==0 ) )
            error('invalid_action',"game.handicap.$Status");

         $paterr = '';
         $patdone = 0;
         if( ENA_STDHANDICAP && !$stonestring && !$coord
            && ( $StdHandicap=='Y' || @$_REQUEST['stdhandicap'] ) )
         {
            $extra_infos[T_('A standard placement of handicap stones has been requested.')]
               = 'Info';
            $stonestring = get_handicap_pattern( $Size, $Handicap, $paterr);
            if( $paterr )
               $extra_infos[$paterr] = 'Important';
            //$coord = ''; // $coord is incoherent with the following
            $patdone = 1;
         }

         check_handicap( $TheBoard, $coord); //adjust $stonestring
         if( (strlen($stonestring)/2) < $Handicap )
         {
            $validation_step = false;
            $extra_infos[T_('Place your handicap stones, please!')]
               = 'Info';
            if( ENA_STDHANDICAP && !$patdone && strlen($stonestring)<2 )
            {
               $extra_infos[
                     "<a href=\"game.php?gid=$gid".URI_AMP."stdhandicap=t\">"
                     . T_('Standard placement') . "</a>"
                  ] = '';
            }
         }
         else
            $validation_step = true;
         $coord = ''; // already processed/stored in $stonestring
      }
      break;

      case 'resign': //for validation
      {
         if( !$may_resign_game )
            error('invalid_action',"game.resign($Status,$my_id)");

         $validation_step = true;
         $extra_infos[T_('Resigning')] = 'Important';
      }
      break;

      case 'add_time': //add-time for opponent
      {
         if( !$may_add_time )
            error('invalid_action', "game.add_time");

         $validation_step = true;
      }
      break;


      case 'pass': //for validation
      {
         if( $Status != 'PLAY' && $Status != 'PASS' )
            error('invalid_action',"game.pass.$Status");

         $validation_step = true;
         $extra_infos[T_('Passing')] = 'Info';
         $extra_infos[T_('Assure that all boundaries of your territory are closed before ending the game.')] = 'Important';
      }
      break;

      case 'delete': //for validation
      {
         if( !$may_add_time )
            error('invalid_action',"game.delete($Status,$my_id)");

         $validation_step = true;
         $extra_infos[T_('Deleting game')] = 'Important';
      }
      break;

      case 'remove': //multiple input step
      {
         if( $Status != 'SCORE' && $Status != 'SCORE2' )
            error('invalid_action',"game.remove.$Status");

         $validation_step = false;
         $game_score = check_remove( $TheBoard, GSMODE_TERRITORY_SCORING, $coord); //ajusted globals: $stonestring
         $score = $game_score->calculate_score();

         $done_url = "game.php?gid=$gid".URI_AMP."a=done"
            . ( $stonestring ? URI_AMP."stonestring=$stonestring" : '' );

         $extra_infos[T_('Score') . ": " . score2text($score, true)] = 'Score';
         $extra_infos[
               sprintf( T_("Please mark dead stones and click %s'done'%s when finished."),
                        "<a href=\"$done_url\">", '</a>')
            ] = 'Info';
         $coord = ''; // already processed/stored in $stonestring
      }
      break;

      case 'done': //for validation after 'remove'
      {
         if( $Status != 'SCORE' && $Status != 'SCORE2' )
            error('invalid_action',"game.done.$Status");

         $validation_step = true;
         $game_score = check_remove( $TheBoard, GSMODE_TERRITORY_SCORING ); //ajusted globals: $stonestring
         $score = $game_score->calculate_score();

         $extra_infos[T_('Score') . ": " . score2text($score, true)] = 'Score';
      }
      break;

      default:
      {
         error('invalid_action',"game.noaction.$Status");
      }
   }
   if( $validation_step ) $may_play = false;


/*
 : Viewing of game messages while read or downloaded (sgf):
 : Game  : Text ::         Viewed by         :: sgf+comments by : sgf only :
 : Ended : Tag  :: Writer : Oppon. : Others  :: Writer : Oppon. : any ones :
 : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
 : no    : none :: yes    : yes    : no      :: yes    : yes    : no       :
 : no    : <c>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 : no    : <h>  :: yes    : no     : no      :: yes    : no     : no       :
 : yes   : none :: yes    : yes    : no      :: yes    : yes    : no       :
 : yes   : <c>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 : yes   : <h>  :: yes    : yes    : yes     :: yes    : yes    : yes      :
 : ----- : ---- :: ------ : ------ : ------- :: ------ : ------ : -------- :
  corresponding $html_mode (F= a filter only keeping <c> and <h> blocks):
 : no    : -    :: gameh  : game   : F+game  ::   ... see sgf.php ...
 : yes   : -    :: gameh  : gameh  : F+gameh ::
*/

   if( $Status == 'FINISHED' )
     $html_mode= 'gameh';
   else
     $html_mode= 'game';

   if( $my_game )
   {
      if( $my_id == $Black_ID )
      {
         $my_color= 'B';
         $opponent_ID= $White_ID;
         $movemsg = make_html_safe($movemsg, ($movecol==BLACK) ? 'gameh' : $html_mode );
      }
      else //if( $my_id == $White_ID )
      {
         $my_color= 'W';
         $opponent_ID= $Black_ID;
         $movemsg = make_html_safe($movemsg, ($movecol==WHITE) ? 'gameh' : $html_mode );
      }

      $cfgsize_notes = $cfg_board->get_cfgsize_notes( $Size );
      $notesheight = $cfg_board->get_notes_height( $cfgsize_notes );
      $noteswidth = $cfg_board->get_notes_width( $cfgsize_notes );
      $notesmode = $cfg_board->get_notes_mode( $cfgsize_notes );
      if( isset($_REQUEST['notesmode']) )
         $notesmode= strtoupper($_REQUEST['notesmode']);

      $show_notes = true;
      $notes = '';
      $noteshide = (substr( $notesmode, -3) == 'OFF') ? 'Y' : 'N';
      if( $noteshide == 'Y' )
         $notesmode = substr( $notesmode, 0, -3);

      if( $tmp=mysql_single_fetch( 'game.gamenotes',
             "SELECT Hidden,Notes FROM GamesNotes"
             ." WHERE gid=$gid AND uid=$my_id") )
      {
         $notes = $tmp['Notes'];
         $noteshide = $tmp['Hidden'];
      }

      $savenotes = false;
      if( @$_REQUEST['togglenotes'] )
      {
         $tmp = ( (@$_REQUEST['hidenotes'] == 'N') ? 'N' : 'Y' );
         if( $tmp != $noteshide )
         {
            $noteshide = $tmp;
            $savenotes = true;
         }
      }
      if( @$_REQUEST['savenotes'] )
      {
         $tmp = rtrim(get_request_arg('gamenotes'));
         if( $tmp != $notes )
         {
            $notes = $tmp;
            $savenotes = true;
         }
      }
      if( $savenotes )
      {
         // note: GamesNotes needs PRIMARY KEY (gid,player):
         db_query( 'game.replace_gamenote',
                 "REPLACE INTO GamesNotes (gid,uid,Hidden,Notes)"
               . " VALUES ($gid,$my_id,'$noteshide','"
                  . mysql_addslashes($notes) . "')" );
      }
   }
   else // !$my_game
   {
      $opponent_ID= 0;
      $movemsg = game_tag_filter( $movemsg);
      $movemsg = make_html_safe($movemsg, $html_mode );
      $show_notes = false;
      $noteshide = 'Y';
   }
   db_close();

   if( ENA_MOVENUMBERS )
   {
      $movenumbers = $cfg_board->get_move_numbers();
      if( isset($_REQUEST['movenumbers']) )
         $movenumbers= (int)$_REQUEST['movenumbers'];
   }
   else
      $movenumbers= 0;

   if( $logged_in )
      $TheBoard->set_style( $cfg_board );


   $title = T_("Game") ." #$gid,$move";
   start_page($title, 0, $logged_in, $player_row, $TheBoard->style_string());



   $jumpanchor = ( $validation_step ) ? '#msgbox' : '';
   echo "\n<FORM name=\"game_form\" action=\"game.php?gid=$gid$jumpanchor\" method=\"POST\">";
   $page_hiddens = array();
   // [ game_form start

   echo "\n<table align=center>\n<tr><td>"; //board & associates table {--------

   if( $movenumbers>0 )
   {
      $movemodulo = $cfg_board->get_move_modulo();
      if( $movemodulo >= 0 )
      {
         $TheBoard->move_marks( $move - $movenumbers, $move, $movemodulo );
         $TheBoard->draw_captures_box( T_('Captures'));
         echo "<br>\n";
      }
   }
   GameScore::draw_score_box( $game_score, GSMODE_TERRITORY_SCORING );
   {//FIXME: remove after testing
      echo "<br>\n";
      GameScore::draw_score_box( $game_score, GSMODE_AREA_SCORING ); }
   echo "</td><td>";

   $TheBoard->movemsg= $movemsg;
   $TheBoard->draw_board( $may_play, $action, $stonestring);
   //TODO: javascript move buttons && numbers hide

   //messages about actions
   if( $validation_step )
      $extra_infos[T_('Hit "Submit" to confirm')] = 'Guidance';

   if( count($extra_infos) )
   {
      echo "\n<dl class=ExtraInfos>";
      foreach($extra_infos as $txt => $class)
         echo "<dd".($class ? " class=\"$class\"" : '').">$txt</dd>";
      echo "</dl>";
   }
   else
      echo "\n<br>";

   $cols = 2;
   if( $show_notes && $noteshide != 'Y' )
   {
      if( $notesmode == 'BELOW' )
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

   if( $validation_step )
   {
      if( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }

      if( $action == 'add_time' )
         draw_add_time( $game_row );
      else
      {
         $stay_on_board = ($action != 'delete');
         draw_message_box( $message, $stay_on_board );

         if( $preview )
         {
            $prevmsg = make_html_safe( $message, 'gameh' );
            $TheBoard->draw_move_message( $prevmsg );
         }
      }
   }
   else if( $Moves > 1 )
   {
      draw_moves( $gid, $move, $game_row['Handicap'] );
      if( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }
   }

   if( $show_notes )
   {
      draw_notes('Y');
      $show_notes = false;
   }

   // observers may view the comments in the sgf files, so not restricted to own games
   echo SMALL_SPACING
      . anchor( "game_comments.php?gid=$gid"
              , T_('Comments')
              , ''
              , array( 'accesskey' => ACCKEYP_GAME_COMMENT,
                       'target' => FRIENDLY_SHORT_NAME.'_game_comments'
              ));

   echo "\n</td></tr>\n</table>"; //board & associates table }--------


   // ] game_form end
   //$page_hiddens['gid'] = $gid; //set in the URL (allow a cool OK button in the browser)
                     //and more: a hidden gid may already be set by draw_moves()
   $page_hiddens['action'] = $action;
   $page_hiddens['move'] = $move;
   if( @$coord )
      $page_hiddens['coord'] = $coord;
   if( @$stonestring )
      $page_hiddens['stonestring'] = $stonestring;
   $page_hiddens['movenumbers'] = @$_REQUEST['movenumbers'];
   $page_hiddens['notesmode'] = @$_REQUEST['notesmode'];

   echo build_hidden( $page_hiddens);
   echo "\n</FORM>";

   echo "\n<HR>";
   draw_game_info($game_row, $TheBoard);
/*
   $txt= draw_board_info($TheBoard);
   if( $txt )
      echo "<div id=\"boardInfos\">$txt</div>\n";
*/
   echo "<HR>\n";



   if( $may_play || $validation_step ) //should be "from status page" as the nextgame option
   {
      $menu_array[T_('Skip to next game')] = "confirm.php?gid=$gid".URI_AMP."nextskip=t";
   }

   if( !$validation_step )
   {
      if( $action == 'choose_move' )
      {
         if( $Status != 'SCORE' && $Status != 'SCORE2' )
            $menu_array[T_('Pass')] = "game.php?gid=$gid".URI_AMP."a=pass";
      }
      else if( $action == 'remove' )
      {
         if( @$done_url )
            $menu_array[T_('Done')] = $done_url;

         $menu_array[T_('Resume playing')] = "game.php?gid=$gid".URI_AMP."a=choose_move";
      }
      else if( $action == 'handicap' )
      {
         // none (at the moment)
      }
      else if( $Status == 'FINISHED' && $my_game && $opponent_ID > 0) //&& $just_looking
      {
         $menu_array[T_('Send message to user')] = "message.php?mode=NewMessage".URI_AMP."uid=$opponent_ID" ;
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$opponent_ID" ;
      }

      if( $may_resign_game )
         $menu_array[T_('Resign')] = "game.php?gid=$gid".URI_AMP."a=resign";

      if( $may_del_game )
         $menu_array[T_('Delete game')] = "game.php?gid=$gid".URI_AMP."a=delete";

      if( $action != 'add_time' && $may_add_time )
         $menu_array[T_('Add time for opponent')] = "game.php?gid=$gid".URI_AMP."a=add_time#addtime";

      $menu_array[T_('Download sgf')] = ( $has_sgf_alias ? "game$gid.sgf" : "sgf.php?gid=$gid" );

      if( $my_game && $Moves>0 && !$has_sgf_alias )
      {
         $menu_array[T_('Download sgf with all comments')] = "sgf.php/?gid=$gid".URI_AMP."owned_comments=1" ;
      }

      if( isset($my_observe) )
      {
         if( $my_observe )
            $menu_array[T_('Remove from observe list')] = "game.php?gid=$gid".URI_AMP."toggleobserve=N";
         else
            $menu_array[T_('Add to observe list')] = "game.php?gid=$gid".URI_AMP."toggleobserve=Y";
      }

      if( $has_observers )
         $menu_array[T_('Show observers')] = "users.php?observe=$gid";
   }

   $menu_array[T_('Show game info')] = "gameinfo.php?gid=$gid";

   end_page(@$menu_array);
}

function draw_moves( $gid, $move, $handicap )
{
   global $TheBoard, $player_row;

   $Size= $TheBoard->size;
   $Moves= $TheBoard->max_moves;

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

   if( ($mvs_start > 0) || ($move <= $step) )
   {
      $i= 0;
      $firstskip = (($move - 1) % $step) + 2;
   }
   else
      $i= ($move-1) % $step;
   $str= '';

   while( $i++ < $Moves )
   {
      $dlt= abs($move-$i);
      if( ($i <= $mvs_start || $i >= $Moves - $mvs_end) || ($dlt < $step) || ($dlt % $step) == 0 )
      {
         list( $Stone, $PosX, $PosY)= @$TheBoard->moves[$i];
         if( $Stone != BLACK && $Stone != WHITE )
            continue;

         switch( (int)$PosX )
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
               if( $PosX < 0)
                  continue;
               $c = number2board_coords($PosX, $PosY, $Size);
               break;
         }

         $c= str_repeat($ctab, strlen($Moves)-strlen($i)).$c;
         if( $Stone == WHITE ) $c= $wtab.$c;
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
         if( $firstskip > 0 && $i - $firstskip < $step )
         {
            $i -= ($i - $firstskip);
            $firstskip = 0;
         }
         $i += $step - 2;
         if( $i >= $Moves - $mvs_end ) $i = $Moves - $mvs_end - 1;
      }

      //first move at bottom:
      $str= $c.$str;
   }

   // show SGF-move-num
   echo "\n";
   if( $move <= $handicap )
      $sgf_move = 0;
   else {
      $sgf_move = get_final_score_move( $move );
      if( $handicap > 0 )
         $sgf_move -= $handicap;
   }
   echo '<span class="SgfMove">', sprintf( T_('(SGF-Move %s)'), $sgf_move ), '</span>&nbsp;';

   // add selectbox to show specific move
   echo "<SELECT name=\"gotomove\" size=\"1\"";
   if( is_javascript_enabled() )
   {
      echo " onchange=\"javascript:this.form['movechange'].click();\"";
   }
   echo ">\n$str</SELECT>";
   echo '<INPUT type="HIDDEN" name="gid" value="' . $gid . "\">";
   echo '<INPUT type="submit" name="movechange" value="' . T_('View move') . "\">";
} //draw_moves

// returns true, if given move is the final score-move (predecessor = POSX_SCORE too)
function get_final_score_move( $move )
{
   if( $move < 2 )
      return $move;

   global $TheBoard;
   if( $move > $TheBoard->max_moves ) $move = $TheBoard->max_moves;

   list( $temp, $PosX ) = @$TheBoard->moves[$move];
   if( $PosX != POSX_SCORE )
      return $move;
   list( $temp, $PosX ) = @$TheBoard->moves[$move-1]; // predecessor-move
   return ( $PosX == POSX_SCORE ) ? $move - 1 : $move;
}

function draw_message_box( &$message, $stay_on_board )
{
   $tabindex=1;
   $to_status_str = T_('Submit and go to status');
   $stay_checked = (get_request_arg('stay')) ? ' checked' : '';

   echo '<a name="msgbox"></a>'
      . '<TABLE class=MessageForm>'
      . '<TR class=Message>'
      . '<TD class=Rubric>' . T_('Message') . ':</TD>'
      . '<TD colspan="2"><textarea name="message" tabindex="'.($tabindex++)
         . '" cols="60" rows="8">'.textarea_safe( $message).'</textarea></TD>'
      . '</TR>'
      . '<TR class=Submit>'
      . '<TD></TD>'
      . '<TD>'
         .'<input type="submit" name="nextgame" tabindex="'.($tabindex++)
            .'" value="'.T_('Submit move') // Submit and go to next game
            .'" accesskey="'.ACCKEY_ACT_EXECUTE.'" title="[&amp;'.ACCKEY_ACT_EXECUTE.']">'
         //.'<input type="submit" name="nextstatus" tabindex="'.($tabindex++).'" value="'.$to_status_str.'">'
         .( $stay_on_board
            ? '<input type="checkbox" name="stay" tabindex="'.($tabindex++).' value="1"'.$stay_checked.'>' . T_('Stay on board')
            : '' )
         .SMALL_SPACING
         .'<input type="submit" name="preview" tabindex="'.($tabindex++)
            .'" value="'.T_('Preview')
            .'" accesskey="'.ACCKEY_ACT_PREVIEW.'" title="[&amp;'.ACCKEY_ACT_PREVIEW.']">'
      . '</TD>'
      . '<TD class="Cancel">'
         . '<input type=submit name="cancel" tabindex="'.($tabindex++) .'" value="'.T_('Cancel move').'">'
      . '</TD>'
      . '</TR>'
      . '</TABLE>'
      . "<br>\n"
      ;

} //draw_message_box

function draw_add_time( $game_row )
{
   $tabindex=10; //TODO: fix this start value
   echo '
    <a name="addtime"></a>
      <TABLE class=AddtimeForm>
        <TR>
          <TD>' . T_('Choose how much additional time you wish to give your opponent') . ':</TD>
        </TR>
        <TR>
          <TD>
           <SELECT name="add_days" size="1"  tabindex="'.($tabindex++).'">';

   //basic_safe() because inside <option></option>
   $trday = basic_safe(T_('day'));
   $trdays = basic_safe(T_('days'));
   for( $i=0; $i <= MAX_ADD_DAYS; $i++)
   {
      echo
         sprintf( "<OPTION value=\"%d\"%s>%s %s</OPTION>\n",
            $i, ( ($i==1) ? ' selected' : '' ),
            $i, ( ($i>1)  ? $trdays : $trday ) );
   }
   echo '  </SELECT>
           &nbsp;' . T_('added to maintime of your opponent.') . '
          </TD>
        </TR>';

   if( $game_row['Byotype'] != 'FIS'
      && $game_row['Byotime'] > 0 && $game_row['Byoperiods'] > 0 ) // no byoyomi-reset if no byoyomi
   {
      echo '<TR>
              <TD>
                <input type="checkbox" checked name="reset_byoyomi" tabindex="'.($tabindex++).'" value="1"'
                   . '>&nbsp;' . T_('Reset byoyomi settings when re-entering') . '
              </TD>
            </TR>';
   }

   echo '<TR>
          <TD align=left>
<input type=submit name="nextaddtime" tabindex="'.($tabindex++).'" value="' . T_('Add Time') . '"
><input type=submit name="cancel" tabindex="'.($tabindex++).'" value="' . T_('Cancel') . '"
></TD>
        </TR>
      </TABLE>
';
} //draw_add_time

function draw_game_info(&$game_row, &$board)
{
   global $base_path;

   echo '<table class=GameInfos>' . "\n";

   $cols = 4;
   $to_move = get_to_move( $game_row, 'game.bad_ToMove_ID' );

   //black rows
   $blackOffTime = echo_off_time( ($to_move == BLACK), $game_row['Black_OnVacation'], $game_row['Black_ClockUsed'] );
   echo '<tr id="blackInfo">' . "\n";
   echo "<td class=Color>", image( "{$base_path}17/b.gif", T_('Black'), null, 'class="InTextStone"' ), "</td>\n";
   echo '<td class=Name>',
      user_reference( REF_LINK, 1, '', $game_row['Black_ID'], $game_row['Blackname'], $game_row['Blackhandle']),
      ( $blackOffTime ? SMALL_SPACING . $blackOffTime : '' ),
      "</td>\n";

   echo '<td class=Ratings>'
      . echo_game_rating( $game_row['Black_ID']
                     , $game_row['Black_Start_Rating']
                     , ($game_row['Status']==='FINISHED')
                           ? $game_row['Black_End_Rating'] : $game_row['Blackrating'])
      . "</td>\n";
   echo '<td class=Prisoners>' . T_('Prisoners') . ': ' . $game_row['Black_Prisoners'] . "</td>\n";
   echo "</tr>\n";

   if( $game_row['Status'] != 'FINISHED' )
   {
      echo '<tr id="blackTime">' . "\n";
      echo "<td colspan=\"" . $cols . "\">\n" . T_("Time remaining") . ": " .
         echo_time_remaining( $game_row['Black_Maintime'], $game_row['Byotype']
                       ,$game_row['Black_Byotime'], $game_row['Black_Byoperiods']
                       ,$game_row['Byotime']) .
         "</td>\n</tr>\n";
   }


   //white rows
   $whiteOffTime = echo_off_time( ($to_move == WHITE), $game_row['White_OnVacation'], $game_row['White_ClockUsed'] );
   echo '<tr id="whiteInfo">' . "\n";
   echo "<td class=Color>", image( "{$base_path}17/w.gif", T_('White'), null, 'class="InTextStone"' ), "</td>\n";
   echo '<td class=Name>',
      user_reference( REF_LINK, 1, '', $game_row['White_ID'], $game_row['Whitename'], $game_row['Whitehandle']),
      ( $whiteOffTime ? SMALL_SPACING . $whiteOffTime : '' ),
      "</td>\n";

   echo '<td class=Ratings>'
      . echo_game_rating( $game_row['White_ID']
                     , $game_row['White_Start_Rating']
                     , ($game_row['Status']==='FINISHED')
                           ? $game_row['White_End_Rating'] : $game_row['Whiterating'])
      . "</td>\n";
   echo '<td class=Prisoners>' . T_('Prisoners') . ': ' . $game_row['White_Prisoners'] . "</td>\n";
   echo "</tr>\n";


   if( $game_row['Status'] != 'FINISHED' )
   {
      echo '<tr id="whiteTime">' . "\n";
      echo "<td colspan=\"" . $cols . "\">\n" . T_("Time remaining") . ": " .
         echo_time_remaining( $game_row['White_Maintime'], $game_row['Byotype']
                       ,$game_row['White_Byotime'], $game_row['White_Byoperiods']
                       ,$game_row['Byotime']) .
         "</td>\n</tr>\n";
   }


   //game rows
   $sep = ',' . SMALL_SPACING;
   echo '<tr id="gameRules">' . "\n";
   echo '<td class=Color>', echo_image_gameinfo($game_row['ID']), "</td>\n";
   echo "<td colspan=\"" . ($cols-1) . "\">" . T_('Rules') . ': ';
   echo T_('Komi') . ': ' . $game_row['Komi'] ;
   echo $sep . T_('Handicap') . ': ' . $game_row['Handicap'];
   echo $sep . T_('Rated game') . ': ' .
      ( ($game_row['Rated'] == 'N') ? T_('No') : T_('Yes') ) . "</td>\n";

   echo "</tr>\n";

   echo '<tr id="gameTime">' . "\n";
   echo "<td colspan=\"" . $cols . "\">" . T_('Time limit') . ': ' .
      echo_time_limit( $game_row['Maintime'], $game_row['Byotype']
                  ,$game_row['Byotime'], $game_row['Byoperiods']) . "</td>\n";

   echo "</tr>\n";

   if( isset($board) )
   {
      $txt= draw_board_info($board);
      if( $txt )
         echo "<tr id=\"boardInfos\" class=BoardInfos><td colspan=$cols\n>$txt</td></tr>\n";
   }

   echo "</table>\n";
} //draw_game_info

function draw_board_info($board)
{
   if( count($board->infos) <= 0 )
      return '';

   $fmts= array(
      //array(POSX_ADDTIME, $MoveNr, $Stone, $Hours, $PosY);
      POSX_ADDTIME => array(
         T_('%2$s had added %4$s to %3$s %5$s at move %1$d'),
         array( 0, null), //MoveNr
         array( 1, array( WHITE => T_('White'), BLACK => T_('Black'))), //From
         array( 1, array( BLACK => T_('White'), WHITE => T_('Black'))), //To
         array( 2, 'echo_time'), //Hours
         array( 3, array( 0 => '', 1 => T_('and restarted byoyomi'))), //Reset
      ),
   );

   $txt= '';
   foreach( $board->infos as $row )
   {
      $key = array_shift($row);
      $sub = @$fmts[$key];
      if( $sub )
      {
         //echo var_export($row, true);
         $fmt = array_shift($sub);
         $val = array();
         for( $i=0; $i<count($sub); $i++ )
         {
            list($n, $fct) = $sub[$i];
            //echo "$n => $tmp<br>";
            if( is_array($fct) )
               $val[$i] = $fct[$row[$n]];
            else if( is_string($fct) )
               $val[$i] = $fct($row[$n]);
            else
               $val[$i] = $row[$n];
         }
         //echo var_export($val, true);
         $str= vsprintf($fmt, $val);
         if( $str )
            $txt.= "<dd>$str</dd\n>";
      }
   }
   if( $txt )
      $txt= "<dl class=BoardInfos>$txt</dl>\n";
   return $txt;
} //draw_board_info

function echo_game_rating( $uid, $start_rating, $end_rating)
{
   return
        "<span class=StartRating>"
      . echo_rating( $start_rating, true, $uid )
      . "</span>"
      . "<span class=Separator>-</span>"
      . "<span class=EndRating>"
      . echo_rating( $end_rating, true, $uid )
      . "</span>"
      ;
} //echo_game_rating


function draw_notes( $collapsed='N', $notes='', $height=0, $width=0)
{
   if( $collapsed == 'Y' )
   {
      //echo textarea_safe( $notes) . "\n";
      echo '<INPUT type="HIDDEN" name="hidenotes" value="N">';
      echo "  <input name=\"togglenotes\" type=\"submit\" value=\""
               . T_('Show notes') . "\">";
      return;
   }

   if( $height<3 ) $height= 3;
   if( $width<15 ) $width= 15;

   echo " <table class=GameNotes>\n";
   echo "  <tr><th>" . T_('Private game notes') . "</th></tr>\n";
   echo "  <tr><td class=Notes>\n";
   echo "   <textarea name=\"gamenotes\" id=\"gameNotes\" cols=\"$width\" rows=\"$height\">"
            . textarea_safe( $notes) . "</textarea>\n";
   echo "  </td></tr>\n";
   echo "  <tr><td><input name=\"savenotes\" type=\"submit\" value=\""
            . T_('Save notes') . "\">";

   if( $collapsed == 'N' )
   {
   echo '<INPUT type="HIDDEN" name="hidenotes" value="Y">';
   echo "<input name=\"togglenotes\" type=\"submit\" value=\""
            . T_('Hide notes') . "\">";
   }

   echo "</td></tr>\n";
   echo "</table>\n";
} //draw_notes

?>

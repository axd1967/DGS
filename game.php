<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

if( @$_REQUEST['nextgame']
      or @$_REQUEST['nextstatus']
      or @$_REQUEST['nextback']
      or @$_REQUEST['nextskip']
   )
{
//confirm use $_REQUEST: gid, move, action, coord, stonestring
   include_once( "confirm.php");
   exit; //should not be executed
}

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
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

   $message = get_request_arg( 'message');


   connect2mysql();

   if( !$gid )
      error('no_game_nr');

   $logged_in = who_is_logged( $player_row);

//    if( !$logged_in )
//       error('not_logged_in');

   if( $logged_in && @$_REQUEST['toggleobserve'] )
      toggle_observe_list($gid, $player_row["ID"]);


   $query= "SELECT Games.*, " .
           "Games.Flags+0 AS GameFlags, " . //used by check_move
           "black.Name AS Blackname, " .
           "black.Handle AS Blackhandle, " .
           "black.OnVacation AS Blackwarning, " .
           "black.Rank AS Blackrank, " .
           "black.Rating2 AS Blackrating, " .
           "black.RatingStatus AS Blackratingstatus, " .
           "white.Name AS Whitename, " .
           "white.Handle AS Whitehandle, " .
           "white.OnVacation AS Whitewarning, " .
           "white.Rank AS Whiterank, " .
           "white.Rating2 AS Whiterating, " .
           "white.RatingStatus AS Whiteratingstatus " .
           "FROM Games, Players AS black, Players AS white " .
           "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID";

   if( !($game_row=mysql_single_fetch( $query)) )
      error('unknown_game','game1');

   extract($game_row);

   if( $Status == 'INVITED' )
      error('unknown_game','game2');


   if( @$_REQUEST['movechange'] )
      $move = (int)@$_REQUEST['gotomove'];
   if( $move<=0 )
      $move = $Moves;
   if( $Status == 'FINISHED' or $move < $Moves )
   {
      $may_play = false;
      $just_looking = true;
   }
   else
   {
      $may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID ) ;
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
            else if( $Status == 'SCORE' or $Status == 'SCORE2' )
               $action = 'remove';
         }
      }
   }


   // ??? no more useful:
   if( !$just_looking && $logged_in && $player_row["ID"] != $ToMove_ID )
      error('not_your_turn');


   $my_game = ( $logged_in && ( $player_row["ID"] == $Black_ID or $player_row["ID"] == $White_ID ) ) ;

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;

   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else if( $ToMove_ID )
      error("database_corrupted");

   if( $Status != 'FINISHED' and ($Maintime > 0 or $Byotime > 0) )
   {
      // LastTicks may handle -(time spend) at the moment of the start of vacations
      $ticks = get_clock_ticks($ClockUsed) - $LastTicks;
      $hours = ( $ticks > $tick_frequency ? floor(($ticks-1) / $tick_frequency) : 0 );

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



   $no_marked_dead = ( $Status == 'PLAY' or $Status == 'PASS' or
                       $action == 'choose_move' or $action == 'domove' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, $move, $no_marked_dead) )
      error('internal_error', "game load_from_db $gid");
   $movecol= $TheBoard->movecol;
   $movemsg= $TheBoard->movemsg;


   $extra_message = false;

   if( $just_looking ) //no process except 'movechange'
   {
      $validation_step = false;
      $may_play = false;
      if( $Status == 'FINISHED' )
      {
         $extra_message = "<font color=\"blue\">" . score2text($Score, true) . "</font>";
      }
   }
   else switch( $action )
   {
      case 'choose_move': //single input pass
      {
         if( $Status != 'PLAY' && $Status != 'PASS' 
          && $Status != 'SCORE' && $Status != 'SCORE2' //after resume
           )
            error('invalid_action','game.choose_move');

         $validation_step = false;
      }
      break;

      case 'domove': //for validation after 'choose_move'
      {
         if( $Status != 'PLAY' && $Status != 'PASS'
          && $Status != 'SCORE' && $Status != 'SCORE2' //after resume
           )
            error('invalid_action','game.domove');

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
         reset($prisoners);
         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $stonestring .= number2sgf_coords($x, $y, $Size);
         }

         if( strlen($stonestring) != $nr_prisoners*2 )
            error('move_problem');

         $TheBoard->set_move_mark( $colnr, $rownr);
         //$coord must be kept for validation by confirm.php
      }
      break;

      case 'handicap': //multiple input pass + validation
      {
         if( $Status != 'PLAY' or !( $Handicap>1 && $Moves==0 ) )
            error('invalid_action','game.handicap');

         $paterr = '';
         $patdone = 0;
         if( ENA_STDHANDICAP && !$stonestring && !$coord
            && ( $StdHandicap=='Y' or @$_REQUEST['stdhandicap'] ) )
         {
            $stonestring = get_handicap_pattern( $Size, $Handicap, $paterr);
            $extra_message = "<font color=\"green\">"
              . T_('A standard placement of handicap stones has been requested.')
              . "</font>";
            //$coord = ''; // $coord is incoherent with the following
            $patdone = 1;
         }

         check_handicap( $TheBoard, $coord); //adjust $stonestring
         if( (strlen($stonestring)/2) < $Handicap )
         {
            $validation_step = false;
            $extra_message = "<font color=\"green\">" .
              T_('Place your handicap stones, please!') . "</font>";
            if( ENA_STDHANDICAP && !$patdone && strlen($stonestring)<2 )
            {
               $extra_message.= "<br><a href=\"game.php?gid=$gid".URI_AMP."stdhandicap=t\">"
                 . T_('Standard placement') . "</a>";
            }
         }
         else
            $validation_step = true;

         if( $paterr )
            $extra_message = "<font color=\"red\">" . $paterr .
                              "</font><br>" . $extra_message;
         $coord = ''; // already processed/stored in $stonestring
      }
      break;

      case 'resign': //for validation
      {
         $validation_step = true;
         $extra_message = "<font color=\"red\">" . T_('Resigning') . "</font>";
      }
      break;


      case 'pass': //for validation
      {
         if( $Status != 'PLAY' and $Status != 'PASS' )
            error('invalid_action','game.pass');

         $validation_step = true;
         $extra_message = "<font color=\"green\">" . T_('Passing') . "</font>";
      }
      break;

      case 'delete': //for validation
      {
         if( $Status != 'PLAY' or !$too_few_moves )
            error('invalid_action','game.delete');

         $validation_step = true;
         $extra_message = "<font color=\"red\">" . T_('Deleting game') . "</font>";
      }
      break;

      case 'remove': //multiple input pass
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error('invalid_action','game.remove');

         $validation_step = false;
         check_remove( $TheBoard, $coord);
//ajusted globals by check_remove(): $score, $stonestring;

         $done_url = "game.php?gid=$gid".URI_AMP."a=done"
            . ( $stonestring ? URI_AMP."stonestring=$stonestring" : '' );

         $extra_message = "<font color=\"blue\">" . T_('Score') . ": " .
             score2text($score, true) . "</font>";
         $extra_message.= "<P>";
         $extra_message.= "<font color=\"green\">"
            . sprintf( T_("Please mark dead stones and click %s'done'%s when finished."),
                        "<a href=\"$done_url\">", '</a>' )
            . "</font>";
         $coord = ''; // already processed/stored in $stonestring
      }
      break;

      case 'done': //for validation after 'remove'
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error('invalid_action','game.done');

         $validation_step = true;
         check_remove( $TheBoard);
//ajusted globals by check_remove(): $score, $stonestring;

         $extra_message = "<font color=\"blue\">" . T_('Score') . ": " .
             score2text($score, true) . "</font>";
      }
      break;

      default:
      {
         error('invalid_action','game.noaction');
      }
   }
   if( $validation_step ) $may_play = false;


/* Viewing of game messages while readed or downloaded (sgf):
 : Game  : Text ::         Viewed by            :: sgf+comments by : Other  :
 : Ended : Tag  :: Writer : Oppon. : Others     :: Writer : Oppon. : sgf    :
 : ----- : ---- :: ------ : ------ : ---------- :: ------ : ------ : ------ :
 : no    : none :: yes    : yes    : no         :: yes    : yes    : no     :
 : no    : <c>  :: yes    : yes    : yes        :: yes    : yes    : yes    :
 : no    : <h>  :: yes    : no     : no         :: yes    : no     : no     :
 : yes   : none :: yes    : yes    : no         :: yes    : yes    : no     :
 : yes   : <c>  :: yes    : yes    : yes        :: yes    : yes    : yes    :
 : yes   : <h>  :: yes    : yes    : yes        :: yes    : yes    : yes    :
  corresponding $html_mode (fltr=filter only keeping <c> and <h> blocks):
 : no    : -    :: gameh  : game   : fltr+game  ::   ... see sgf.php ...
 : yes   : -    :: gameh  : gameh  : fltr+gameh ::
*/

   if( $Status == 'FINISHED' )
     $html_mode= 'gameh';
   else
     $html_mode= 'game';

   if( $my_game )
   {
      if ($Size >= $player_row["NotesCutoff"])
      {
        $notesheight = $player_row["NotesLargeHeight"];
        $noteswidth = $player_row["NotesLargeWidth"];
        $notesmode = $player_row["NotesLargeMode"];
      }
      else
      {
        $notesheight = $player_row["NotesSmallHeight"];
        $noteswidth = $player_row["NotesSmallWidth"];
        $notesmode = $player_row["NotesSmallMode"];
      }
      if( isset($_REQUEST['notesmode']) )
         $notesmode= (string)$_REQUEST['notesmode'];
      $show_notes = ( $notesmode and $notesmode !== '0' and $notesmode !== 'OFF' );

      if( $player_row["ID"] == $Black_ID )
      {
         $dbplayer= 'B';
         $opponent_ID= $White_ID;
         $movemsg = make_html_safe($movemsg, $movecol==BLACK ? 'gameh' : $html_mode );
      }
      else //if( $player_row["ID"] == $White_ID )
      {
         $dbplayer= 'W';
         $opponent_ID= $Black_ID;
         $movemsg = make_html_safe($movemsg, $movecol==WHITE ? 'gameh' : $html_mode );
      }

      if( $show_notes && $tmp=mysql_single_fetch(
                    "SELECT Hidden,Notes FROM GamesNotes"
                  . " WHERE gid=$gid AND player='$dbplayer'"
                  ) )
      {
         $dbhidden = $tmp['Hidden'];
         $dbnotes = $tmp['Notes'];
         unset( $tmp);
      }
      else
      {
         $dbhidden = 'Y';
         $dbnotes = '';
      }

      if( $show_notes && (@$_REQUEST['savenotes'] or @$_REQUEST['togglenotes']) )
      {
         if( @$_REQUEST['togglenotes'] )
            $collapse_notes = ($dbhidden == 'Y' ? 'N' : 'Y' );
         else
            $collapse_notes = $dbhidden;

         if( @$_REQUEST['savenotes'] )
            $notes = rtrim(get_request_arg('gamenotes'));
         else
            $notes = $dbnotes;

         mysql_query(
                 "REPLACE INTO GamesNotes (gid,player,Hidden,Notes)"
               . " VALUES ($gid,'$dbplayer','$collapse_notes','"
                  . addslashes($notes) . "')"
               ); // or die(mysql_error());
      }
/*
      else if( $show_notes && @$_REQUEST['movechange'] )
      {
         $collapse_notes = $dbhidden;
         $notes = rtrim(get_request_arg('gamenotes'));
      }
*/
      else
      {
         $collapse_notes = $dbhidden;
         $notes = $dbnotes;
      }
      unset( $dbplayer, $dbnotes, $dbhidden);
   }
   else // !$my_game
   {
      $opponent_ID= 0;
      $movemsg = game_tag_filter( $movemsg);
      $movemsg = make_html_safe($movemsg, $html_mode );
      $show_notes = false;
      $collapse_notes = 'Y';
   }
     
   if( ENA_MOVENUMBERS )
   {
      $movenumbers= (int)@$player_row['MoveNumbers'];
      if( isset($_REQUEST['movenumbers']) )
         $movenumbers= (int)$_REQUEST['movenumbers'];
   }
   else
      $movenumbers= 0;

   if( $logged_in )
      $TheBoard->set_style( $player_row);


   start_page(T_("Game"), true, $logged_in, $player_row, $TheBoard->style_string());



   echo "<FORM name=\"game_form\" action=\"game.php?gid=$gid\" method=\"POST\">\n";
   $page_hiddens[] = array();
   // [ game_form start

   echo "<table align=center>\n<tr><td>"; //board & associates table {--------

   if( $movenumbers>0 )
   {
      $tmp= (int)@$player_row['MoveModulo'];
      if( $tmp >= 0 )
      {
         $TheBoard->move_marks( $move-$movenumbers, $move, $tmp);
         $TheBoard->draw_captures_box( T_('Captures'));
      }
   }
   echo "</td><td>\n";

   $TheBoard->movemsg= $movemsg;
   $TheBoard->draw_board( $may_play, $action, $stonestring);

   if( $extra_message ) //score messages
      echo "<P><center>$extra_message</center>\n";
   echo '<br>';

   if( $show_notes && $collapse_notes != 'Y' )
   {
      if( $notesmode == 'BELOW' )
         echo "</td></tr>\n<tr><td colspan=99 align='center'>";
      else //default 'RIGHT'
         echo "</td>\n<td align='left' valign='center'>";
      draw_notes( 'N', $notes, $notesheight, $noteswidth);
      $show_notes = false;
   }

   // colspan = captures+board column
   echo "</td></tr>\n<tr><td colspan=2 align='center'>";

   if( $validation_step )
   {
      if( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }
      draw_message_box( $message);
   }
   else if( $Moves > 1 )
   {
      draw_moves();
      if( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }

      //if( $my_game ) //sgf comments may be viewed by observers
      {
         echo "\n<center><a href=\"game_comments.php?gid=$gid\" target=\"DGS_game_comments\">" . 
               T_('Comments') . "</a></center>\n";
      }
   }

      if( $show_notes )
      {
         draw_notes('Y');
         $show_notes = false;
      }

   echo "</td></tr>\n</table>\n"; //board & associates table }--------


   // ] game_form end
   //$page_hiddens['gid'] = $gid; //set in the URL (allow a cool OK button in the browser)
   $page_hiddens['action'] = $action;
   $page_hiddens['move'] = $move;
   if( @$coord )
      $page_hiddens['coord'] = $coord;
   if( @$stonestring )
      $page_hiddens['stonestring'] = $stonestring;

   if( @$_REQUEST['movenumbers'] )
      $page_hiddens['movenumbers'] = $_REQUEST['movenumbers'];
   if( @$_REQUEST['notesmode'] )
      $page_hiddens['notesmode'] = $_REQUEST['notesmode'];

   foreach( $page_hiddens as $key => $val )
   {
      echo "<input type=\"hidden\" name=\"$key\" value=\"$val\">\n";
   }
   echo "</FORM>\n";


   echo "<HR>\n";
   draw_game_info($game_row);
   echo "<HR>\n";



   if( $may_play or $validation_step ) //should be "from status page" as the nextgame option
   {
      $menu_array[T_('Skip to next game')] = "confirm.php?gid=$gid".URI_AMP."nextskip=t";
   }

   if( !$validation_step )
   {
      if( $action == 'choose_move' )
      {
         if( $Status != 'SCORE' && $Status != 'SCORE2' )
            $menu_array[T_('Pass')] = "game.php?gid=$gid".URI_AMP."a=pass";

         if( $too_few_moves )
            $menu_array[T_('Delete game')] = "game.php?gid=$gid".URI_AMP."a=delete";

         $menu_array[T_('Resign')] = "game.php?gid=$gid".URI_AMP."a=resign";
      }
      else if( $action == 'remove' )
      {
         if( @$done_url )
            $menu_array[T_('Done')] = $done_url;
         $menu_array[T_('Resume playing')] = "game.php?gid=$gid".URI_AMP."a=choose_move";
      }
      else if( $action == 'handicap' )
      {
         $menu_array[T_('Delete game')] = "game.php?gid=$gid".URI_AMP."a=delete";
      }
      else if( $Status == 'FINISHED' && $my_game && $opponent_ID > 0) //&& $just_looking
      {
         $menu_array[T_('Send message to user')] = "message.php?mode=NewMessage".URI_AMP."uid=$opponent_ID" ;
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$opponent_ID" ;
      }

      $menu_array[T_('Download sgf')] = ( $has_sgf_alias ? "game$gid.sgf" : "sgf.php?gid=$gid");

      if( $my_game && $Moves>0 && !$has_sgf_alias )
      {
         $menu_array[T_('Download sgf with all comments')] = "sgf.php/?gid=$gid".URI_AMP."owned_comments=1" ;
      }

      if( ($Status != 'FINISHED') and !$my_game and $logged_in )
      {
         if( is_on_observe_list( $gid, $player_row["ID"] ) )
            $menu_array[T_('Remove from observe list')] = "game.php?gid=$gid".URI_AMP."toggleobserve=t";
         else
            $menu_array[T_('Add to observe list')] = "game.php?gid=$gid".URI_AMP."toggleobserve=t";
      }
   }

   end_page(@$menu_array);
}

function draw_moves()
{
   global $TheBoard, $gid, $move, $Size;

   echo '<INPUT type="HIDDEN" name="gid" value="' . $gid . "\">\n";
   echo '<SELECT name="gotomove" size="1">' . "\n";

   $str = '';
   foreach( $TheBoard->moves as $MoveNr => $sub )
   {
      list( $Stone, $PosX, $PosY) = $sub;
      if( $Stone != BLACK and $Stone != WHITE ) continue;

      switch( $PosX )
      {
         case POSX_PASS :
            $c = T_('Pass');
            break;
         case POSX_SCORE :
            $c = T_('Scoring');
            break;
         case POSX_RESIGN :
            $c = T_('Resign');
            break;
         default :
            $c = number2board_coords($PosX, $PosY, $Size);
            break;
      }

      if( $Stone == WHITE ) $c = str_repeat('&nbsp;', 12) . $c;
      if( $MoveNr < 10 ) $c = '&nbsp;'.$c;
      $str = sprintf('<OPTION value="%d" %s>Move %s:&nbsp;&nbsp;%s</OPTION>' . "\n",
                   $MoveNr, ($MoveNr == $move ? ' selected' : ''), $MoveNr, $c)
          . $str;
   }
   echo "$str</SELECT>\n";
   echo '<INPUT type="submit" name="movechange" value="' . T_('View move') . "\">\n";
}

function draw_message_box(&$message)
{

   $tabindex=1;
   echo '
    <center>
      <TABLE align="center">
        <TR>
          <TD align=right>' . T_('Message') . ':</TD>
          <TD align=left>
            <textarea name="message" tabindex="'.($tabindex++).'" cols="50" rows="8">'
               . textarea_safe( $message) . '</textarea></TD>
        </TR>
      </TABLE>
<TABLE align=center cellpadding=5>
<TR><TD><input type=submit name="nextgame" tabindex="'.($tabindex++).'" value="' .
      T_('Submit and go to next game') . '"></TD>
    <TD><input type=submit name="nextstatus" tabindex="'.($tabindex++).'" value="' .
      T_("Submit and go to status") . '"></TD></TR>
<TR><TD align=right colspan=99><input type=submit name="nextback" tabindex="'.($tabindex++).'" value="' .
      T_("Go back") . '"></TD></TR>
      </TABLE>
    </CENTER>
';

}

function draw_game_info(&$game_row)
{
   global $table_row_color2;

   echo '<table border=0 cellspacing=0 cellpadding=3 align=center>' . "\n";
   echo '<tr bgcolor="#DDDDDD">' . "\n";
   echo "<td align=center><img src=\"17/b.gif\" alt=\"" . T_('Black') ."\"></td>\n";
   echo '<td>' .
      user_reference( REF_LINK, 1, 'black', $game_row['Black_ID'],
                      $game_row['Blackname'], $game_row['Blackhandle']) .
      ( $game_row['Blackwarning'] > 0 ?
        '&nbsp;&nbsp;&nbsp;<font color=red>' . T_('On vacation') . '</font>' : '' ) .
      "</td>\n";

   $rating = ( $game_row['Status']==='FINISHED' ?
               $game_row['Black_Start_Rating'] : $game_row['Blackrating'] );

   echo '<td>' . echo_rating( $rating, true, $game_row['Black_ID'] ) . "</td>\n";
   echo '<td>&nbsp;</td>' . "\n";
   echo '<td>' . T_('Prisoners') . ': ' . $game_row['Black_Prisoners'] . "</td>\n";
   echo "</tr>\n";

   if( $game_row['Status'] != 'FINISHED' )
   {
      echo '<tr bgcolor="#DDDDDD">' . "\n";
      echo "<td colspan=99>\n" . T_("Time remaining") . ": " .
         echo_time_remaining($game_row['Maintime'], $game_row['Byotype'],
                             $game_row['Byotime'], $game_row['Byoperiods'],
                             $game_row['Black_Maintime'], $game_row['Black_Byotime'],
                             $game_row['Black_Byoperiods']) . "</td>\n";
      echo "</tr>\n";
   }


   echo '<tr bgcolor="#FFFFFF">' . "\n";
   echo "<td align=center><img src=\"17/w.gif\" alt=\"" . T_('White') ."\"></td>\n";
   echo '<td>' .
      user_reference( REF_LINK, 1, 'black', $game_row['White_ID'],
                      $game_row['Whitename'], $game_row['Whitehandle']) .
      ( $game_row['Whitewarning'] > 0 ?
        '&nbsp;&nbsp;&nbsp;<font color=red>' . T_('On vacation') . '</font>' : '' ) .
      "</td>\n";

   $rating = ( $game_row['Status']==='FINISHED' ?
               $game_row['White_Start_Rating'] : $game_row['Whiterating'] );

   echo '<td>' . echo_rating( $rating, true, $game_row['White_ID'] ) . "</td>\n";
   echo '<td>&nbsp;</td>' . "\n";
   echo '<td>' . T_('Prisoners') . ': ' . $game_row['White_Prisoners'] . "</td>\n";
   echo "</tr>\n";


   if( $game_row['Status'] != 'FINISHED' )
   {
      echo '<tr bgcolor="#FFFFFF">' . "\n";
      echo "<td colspan=99>\n" . T_("Time remaining") . ": " .
         echo_time_remaining($game_row['Maintime'], $game_row['Byotype'],
                             $game_row['Byotime'], $game_row['Byoperiods'],
                             $game_row['White_Maintime'], $game_row['White_Byotime'],
                             $game_row['White_Byoperiods']) . "</td>\n";
      echo "</tr>\n";
   }

   $sep = ',&nbsp;&nbsp;&nbsp;';
   echo '<tr bgcolor=' . $table_row_color2 . '>' . "\n";
   echo "<td colspan=99>" . T_('Rules') . ': ';
   echo T_('Komi') . ': ' . $game_row['Komi'] ;
   echo $sep . T_('Handicap') . ': ' . $game_row['Handicap'];
   echo $sep . T_('Rated game') . ': ' .
      ( $game_row['Rated'] == 'N' ? T_('No') : T_('Yes') ) . "</td>\n";

   echo "</tr>\n";

   echo '<tr bgcolor=' . $table_row_color2 . '>' . "\n";
   echo "<td colspan=99>" . T_('Time limit') . ': ' .
      echo_time_limit($game_row['Maintime'], $game_row['Byotype'],
                      $game_row['Byotime'], $game_row['Byoperiods']) . "</td>\n";

   echo "</tr>\n";
   echo "</table>\n";

}


function draw_notes( $collapsed='N', $notes='', $height=0, $width=0)
{
   if( $collapsed == 'Y' )
   {
      //echo textarea_safe( $notes) . "\n";
      echo "  <input name=\"togglenotes\" type=\"submit\" value=\""
               . T_('Show notes') . "\">\n";
      return;
   }

   if( $height<3 ) $height= 3;
   if( $width<15 ) $width= 15;

   echo " <table class=gamenotes>\n";
   echo "  <tr><th>" . T_('Private game notes') . "</td></tr>\n";
   echo "  <tr><td class=notes>\n";
   echo "   <textarea name=\"gamenotes\" id=\"gamenotes\" cols=\"$width\" rows=\"$height\">"
            . textarea_safe( $notes) . "</textarea>\n";
   echo "  </td></tr>\n";
   echo "  <tr><td><input name=\"savenotes\" type=\"submit\" value=\""
            . T_('Save notes') . "\">\n";

   if( $collapsed == 'N' )
   {
   echo "  <input name=\"togglenotes\" type=\"submit\" value=\""
            . T_('Hide notes') . "\">\n";
   }

   echo "  </td></tr>\n";
   echo " </table>\n";
}

?>

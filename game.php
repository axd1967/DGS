<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
require_once( "include/rating.php" );
if( ENA_STDHANDICAP ) {
require_once( "include/sgf_parser.php" );
}


{
/*
 Because of get_request_url() from draw_notes(),
 this page must be called with $_GET[] arguments.
*/
   $gid = @$_GET['gid'];
   $action = @$_GET['action'];
   $move = @$_GET['move'];
   $coord = @$_GET['coord'];
   $stonestring = (string)@$_GET['stonestring'];
   $toggleobserve = @$_GET['toggleobserve'];

   // abbreviations used to reduce file size
   if( @$_GET['g'] ) $gid=$_GET['g'];
   if( @$_GET['a'] ) $action=$_GET['a'];
   if( @$_GET['m'] ) $move=$_GET['m'];
   if( @$_GET['c'] ) $coord=$_GET['c'];
   if( @$_GET['s'] ) $stonestring=(string)$_GET['s'];

   connect2mysql();

   if( !$gid )
      error("no_game_nr");

   $logged_in = who_is_logged( $player_row);

   if( $toggleobserve and $logged_in )
      toggle_observe_list($gid, $player_row["ID"]);



//    if( !$logged_in )
//       error("not_logged_in");

   $result = mysql_query( "SELECT Games.*, " .
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
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

   if( @mysql_num_rows($result) != 1 )
      error("unknown_game",'game1');


   $game_row = mysql_fetch_assoc($result);
   extract($game_row);

   if( $Status == 'INVITED' )
      error("unknown_game",'game2');

   if( $action and $logged_in and $player_row["ID"] != $ToMove_ID )
      error("not_your_turn");


   if( $move<=0 ) $move = $Moves;

   $may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID and $move == $Moves ) ;

   $my_game = ( $logged_in and ( $player_row["ID"] == $Black_ID or $player_row["ID"] == $White_ID ) ) ;

   $too_few_moves = ($Moves < DELETE_LIMIT+$Handicap) ;

   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else if( $ToMove_ID )
      error("database_corrupted");

   if( !$action )
   {
      $action = 'just_looking';
      if( $may_play )
      {
         if( $Status == 'PLAY' or $Status == 'PASS' )
         {
            $action = 'choose_move';
            if( $Moves < $Handicap )
               $action = 'handicap';
         }
         else if( $Status == 'SCORE' or $Status == 'SCORE2' )
            $action = 'remove';
      }
   }

   if( $Status != 'FINISHED' and ($Maintime > 0 or $Byotime > 0) )
   {
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
                       $action == 'choose_move' or $action == 'move' );

   $TheBoard = new Board( );
   if( !$TheBoard->load_from_db( $game_row, $move, $no_marked_dead) )
      error('internal_error', "game load_from_db $gid");
   $movecol= $TheBoard->movecol;
   $movemsg= $TheBoard->movemsg;


   $enable_message = true;
   $extra_message = false;

   switch( $action )
   {
      case 'just_looking':
      {
         $enable_message = false;
         if( $Status == 'FINISHED' )
         {
            $extra_message = "<font color=\"blue\">" . score2text($Score, true) . "</font>";
         }
      }
      break;

      case 'choose_move':
      {
         $enable_message = false;
      }
      break;

      case 'move':
      {
{//to fixe old way Ko detect. Could be removed when no more old way games.
  if( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
}
         check_move( $TheBoard, $coord, $to_move);
//ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

         $prisoner_string = '';
         reset($prisoners);
         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $prisoner_string .= number2sgf_coords($x, $y, $Size);
         }

         if( strlen($prisoner_string) != $nr_prisoners*2 )
            error("move_problem");


         //$Moves++;
         $TheBoard->set_move_mark( $colnr, $rownr);
      }
      break;

      case 'handicap':
      {
         if( $Status != 'PLAY' )
            error('invalid_action','game3');

         $paterr = '';
         if( ENA_STDHANDICAP && !$stonestring && !$coord && $Handicap>1 
            && ( $StdHandicap=='Y' or @$_REQUEST['stdhandicap'] ) )
         {
            $stonestring = get_handicap_pattern( $Size, $Handicap, $paterr);
            $extra_message = "<font color=\"green\">"
              . T_('A standard placement of handicap stones has been requested.')
              . "</font>";
         }
         //else //$stonestring and/or $coord from URL
         {
            check_handicap( $TheBoard, $coord); //adjust $stonestring
            if( (strlen($stonestring)/2) < $Handicap )
            {
               $enable_message = false;
               $extra_message = "<font color=\"green\">" .
                 T_('Place your handicap stones, please!') . "</font>";
               if( ENA_STDHANDICAP && strlen($stonestring)<2 )
               {
                  $extra_message.= "<br><a href=\"game.php?gid=$gid".URI_AMP."stdhandicap=t\">"
                    . T_('Standard placement') . "</a>";
               }
            }
         }
         if( $paterr )
            $extra_message = "<font color=\"red\">" . $paterr .
                              "</font><br>" . $extra_message;
      }
      break;

      case 'resign':
      {
         $extra_message = "<font color=\"red\">" . T_('Resigning') . "</font>";
      }
      break;


      case 'pass':
      {
         if( $Status != 'PLAY' and $Status != 'PASS' )
            error("invalid_action",'game4');

         $extra_message = "<font color=\"green\">" . T_('Passing') . "</font>";
      }
      break;

      case 'delete':
      {
         if( $Status != 'PLAY' or !$too_few_moves )
            error("invalid_action",'game5');

         $extra_message = "<font color=\"red\">" . T_('Deleting game') . "</font>";
      }
      break;

      case 'remove':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action",'game6');

         check_remove( $TheBoard, $coord);
//ajusted globals by check_remove(): $score, $stonestring;

         $enable_message = false;

         $done_url = "game.php?gid=$gid".URI_AMP."action=done"
            . ( $stonestring ? URI_AMP."stonestring=$stonestring" : '' );

         $extra_message = "<font color=\"blue\">" . T_('Score') . ": " .
             score2text($score, true) . "</font>";
         $extra_message.= "<P>";
         $extra_message.= "<font color=\"green\">"
            . sprintf( T_("Please mark dead stones and click %s'done'%s when finished."),
                        "<a href=\"$done_url\">", '</a>' )
            . "</font>";
      }
      break;

      case 'done':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action",'game7');

         check_remove( $TheBoard);
//ajusted globals by check_remove(): $score, $stonestring;

         $extra_message = "<font color=\"blue\">" . T_('Score') . ": " .
             score2text($score, true) . "</font>";
      }
      break;

      default:
      {
         error("invalid_action",'game8');
      }
   }

   if( $enable_message ) $may_play = false;

/* Viewing of game messages while readed or downloaded (sgf):
 : Game  : Text ::         Viewed by        :: sgf+comments by : Other  :
 : Ended : Tag  :: Writer : Oppon. : Others :: Writer : Oppon. : sgf    :
 : ----- : ---- :: ------ : ------ : ------ :: ------ : ------ : ------ :
 : no    : none :: yes    : yes    : no     :: yes    : yes    : no     :
 : no    : <c>  :: yes    : yes    : yes    :: yes    : yes    : yes    :
 : no    : <h>  :: yes    : no     : no     :: yes    : no     : no     :
 : yes   : none :: yes    : yes    : no     :: yes    : yes    : no     :
 : yes   : <c>  :: yes    : yes    : yes    :: yes    : yes    : yes    :
 : yes   : <h>  :: yes    : yes    : yes    :: yes    : yes    : yes    :
  corresponding $html_mode (fltr=filter only keeping <c> and <h> blocks):
 : no    : -    :: gameh  : game   : fltr+game  ::
 : yes   : -    :: gameh  : gameh  : fltr+gameh ::
*/

   if( $Status == 'FINISHED' )
     $html_mode= 'gameh';
   else
     $html_mode= 'game';

   if( $my_game && $player_row["ID"] == $Black_ID )
   {
     $show_notes = true;
     $notes = $Black_Notes;
     $opponent_ID= $White_ID;
     $movemsg = make_html_safe($movemsg, $movecol==BLACK ? 'gameh' : $html_mode );
   }
   elseif( $my_game && $player_row["ID"] == $White_ID )
   {
     $show_notes = true;
     $notes = $White_Notes;
     $opponent_ID= $Black_ID;
     $movemsg = make_html_safe($movemsg, $movecol==WHITE ? 'gameh' : $html_mode );
   }
   else
   {
     $show_notes = false;
     $opponent_ID= 0;
     $movemsg = game_tag_filter( $movemsg);
     $movemsg = make_html_safe($movemsg, $html_mode );
   }
     
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


   $TheBoard->set_style( $player_row);


   start_page(T_("Game"), true, $logged_in, $player_row, $TheBoard->style_string());

   echo "<table align=center>\n<tr><td>"; //notes table {--------

   $TheBoard->movemsg= $movemsg;
   $TheBoard->draw_board( $may_play, $action, $stonestring);

   if( $extra_message ) //score messages
      echo "<P><center>$extra_message</center>\n";
   echo '<br>';

   if( $show_notes && $notesmode != 'OFF' )
   {
      if ($notesmode == 'BELOW')
         echo "</td></tr>\n<tr><td align='center'>";
      else //default RIGHT
         echo "</td>\n<td align='left' valign='center'>";
      draw_notes($notes, $gid, $notesheight, $noteswidth);
   }

   if( $enable_message )
   {
      echo "</td></tr>\n<tr><td align='center'>";
      draw_message_box(); //use $stonestring, $prisoner_string, $move
   }
   echo "</td></tr>\n</table>"; //notes table }--------


   echo "<HR>\n";
   draw_game_info($game_row);
   echo "<HR>\n";

// display moves

   if( !$enable_message )
   {
      if( $Moves > 0 )
      {
         draw_moves();
         //if( $my_game ) //sgf comments may be viewed by observers
         {
            echo "\n<center><a href=\"game_comments.php?gid=$gid\" target=\"DGS_game_comments\">" . 
                  T_('Comments') . "</a></center>\n";
         }
      }
      if( $action == 'choose_move' )
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            $menu_array[T_('Pass')] = "game.php?gid=$gid".URI_AMP."action=pass";

         if( $too_few_moves )
            $menu_array[T_('Delete game')] = "game.php?gid=$gid".URI_AMP."action=delete";

         $menu_array[T_('Resign')] = "game.php?gid=$gid".URI_AMP."action=resign";
      }
      else if( $action == 'remove' )
      {
         if( @$done_url )
            $menu_array[T_('Done')] = $done_url;
         $menu_array[T_('Resume playing')] = "game.php?gid=$gid".URI_AMP."action=choose_move";
      }
      else if( $action == 'handicap' )
      {
         $menu_array[T_('Delete game')] = "game.php?gid=$gid".URI_AMP."action=delete";
      }
      else if( $my_game && $Status == 'FINISHED' && $opponent_ID > 0) //&& $action == 'just_looking'
      {
         $menu_array[T_('Send message to user')] = "message.php?mode=NewMessage".URI_AMP."uid=$opponent_ID" ;
         $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$opponent_ID" ;
      }

      $menu_array[T_('Download sgf')] = ( $has_sgf_alias ? "game$gid.sgf" : "sgf.php?gid=$gid");

      if( $my_game && $Moves>0 && !$has_sgf_alias )
      {
         $menu_array[T_('Download sgf with all comments')] = "sgf.php/?gid=$gid".URI_AMP."owned_comments=1" ;
      }

      if( $action == 'choose_move' or $action == 'handicap' or $action == 'remove' )
         $menu_array[T_('Skip to next game')] = "confirm.php?gid=$gid".URI_AMP."skip=t";

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


   echo '<table id="game_moves" border=4 cellspacing=0 cellpadding=1 align=center bgcolor="#66C17B">';
   echo "\n<tr align=center><th>" . T_('Moves') . "</th>\n";

   $moves_per_row = 20;

   for($i=0; $i<$moves_per_row; $i++)
     echo "<td>$i</td>";

   echo "</tr>\n<tr align=center><td>1-". ($moves_per_row - 1) . '</td><td>&nbsp;</td>';

   $i=1;
   foreach( $TheBoard->moves as $MoveNr => $sub )
   {
      list( $Stone, $PosX, $PosY) = $sub;
      if( $Stone != BLACK and $Stone != WHITE ) continue;
      if( $i % $moves_per_row == 0 )
         echo "</tr>\n<tr align=center><td>$i-" . ($i + $moves_per_row - 1) . '</td>';

      switch( $PosX )
      {
         case POSX_PASS :
            $c = 'P';
            break;
         case POSX_SCORE :
            $c = 'S';
            break;
         case POSX_RESIGN :
            $c = 'R';
            break;
         default :
            $c = number2board_coords($PosX, $PosY, $Size);
            break;
      }

      if( $MoveNr == $move ) // bgcolor=F7F5E3
         printf("<td class=r>%s</td>\n", $c );
      else if( $Stone == BLACK )
         printf( '<td><A class=b href="game.php?gid=%d'.URI_AMP."move=%d\">%s</A></td>\n"
               , $gid, $MoveNr, $c );
      else
         printf( '<td><a class=w href="game.php?gid=%d'.URI_AMP."move=%d\">%s</a></td>\n"
               , $gid, $MoveNr, $c );

      $i++;
   }
   echo "</tr></table>\n";
}


function draw_message_box()
{
   global $action, $gid, $stonestring, $coord, $prisoner_string, $move;

   $tabindex=1;
   echo '
  <FORM name="confirmform" action="confirm.php" method="POST">
    <center>
      <TABLE align="center">
        <TR>
          <TD align=right>' . T_('Message') . ':</TD>
          <TD align=left>
            <textarea name="message" tabindex="'.($tabindex++).'" cols="50" rows="8"></textarea></TD>
        </TR>
      </TABLE>
<TABLE align=center cellpadding=5>
<TR><TD><input type=submit name="nextgame" tabindex="'.($tabindex++).'" value="' .
      T_('Submit and go to next game') . '"></TD>
    <TD><input type=submit name="nextstatus" tabindex="'.($tabindex++).'" value="' .
      T_("Submit and go to status") . '"></TD></TR>
<TR><TD align=right colspan=2><input type=submit name="nextback" tabindex="'.($tabindex++).'" value="' .
      T_("Go back") . '"></TD></TR>
      </TABLE>
    </CENTER>
';

    if( $action == 'move' )
    {
       echo "<input type=\"hidden\" name=\"coord\" value=\"$coord\">\n";
       echo "<input type=\"hidden\" name=\"prisoner_string\" value=\"$prisoner_string\">\n";
    }
    else if( $action == 'done' or $action == 'handicap' )
    {
       if( @$stonestring )
         echo "<input type=\"hidden\" name=\"stonestring\" value=\"$stonestring\">\n";
    }

   echo '
  <input type="hidden" name="gid" value="' . $gid . '">
  <input type="hidden" name="move" value="' . $move .'">
  <input type="hidden" name="action" value="' . $action .'">
  </FORM>
';

}


function draw_game_info($game_row)
{
  echo "<table align=center border=2 cellpadding=3 cellspacing=3>\n";
  echo "   <tr>\n";
  echo "     <td></td><td width=" . ($game_row['Size']*9) . ">" .
    T_('White') . "</td><td width=" . ($game_row['Size']*9) . ">" . T_('Black') . "</td>\n";
  echo "   </tr><tr>\n";
  echo "     <td>" . T_('Name') . ":</td>\n";
  echo '     <td' . ( $game_row['Whitewarning'] > 0
             ? blend_warning_cell_attb( T_('On vacation') ) : '' ) . '>'
       . user_reference( REF_LINK, 1, 'black', $game_row['White_ID'], $game_row['Whitename'], $game_row['Whitehandle'])
       . "</td>\n";
  echo '     <td' . ( $game_row['Blackwarning'] > 0
             ? blend_warning_cell_attb( T_('On vacation') ) : '' ) . '>'
       . user_reference( REF_LINK, 1, 'black', $game_row['Black_ID'], $game_row['Blackname'], $game_row['Blackhandle'])
       . "</td>\n";
  echo "   </tr><tr>\n";

  echo '     <td>' . T_('Rating') . ":</td>\n";
  echo '     <td>' . echo_rating( ($game_row['Status']==='FINISHED' ?
                                   $game_row['White_End_Rating'] : $game_row['Whiterating'] ),
                                  true, $game_row['White_ID'] ) . "</td>\n";
  echo '     <td>' . echo_rating( ($game_row['Status']==='FINISHED' ?
                                   $game_row['Black_End_Rating'] : $game_row['Blackrating'] ),
                                  true, $game_row['Black_ID'] ) . "</td>\n";
  echo "   </tr><tr>\n";

  echo '     <td>' . T_('Rank info') . ":</td>\n";
  echo '     <td>' . make_html_safe($game_row['Whiterank'], true) . "</td>\n";
  echo '     <td>' . make_html_safe($game_row['Blackrank'], true) . "</td>\n";
  echo "   </tr><tr>\n";
  echo '     <td>' . T_('Prisoners') . ":</td>\n";
  echo '     <td>' . $game_row['White_Prisoners'] . "</td>\n";
  echo '     <td>' . $game_row['Black_Prisoners'] . "</td>\n";
  echo "   </tr><tr>\n";
  echo '     <td></td><td>' . T_('Komi') . ': ' . $game_row['Komi'] . "</td>\n";
  echo '     <td>' . T_('Handicap') . ': ' . $game_row['Handicap'] . "</td>\n";
  echo "   </tr>\n";

    if( $game_row['Status'] != 'FINISHED' and ($game_row['Maintime'] > 0 or $game_row['Byotime'] > 0))
    {
      echo " <tr>\n";
      echo '     <td>' . T_('Main time') . ":</td>\n";
      echo '     <td>' .  echo_time( $game_row['White_Maintime'] ) . "</td>\n";
      echo '     <td>' .  echo_time( $game_row['Black_Maintime'] ) . "</td>\n";
      echo "   </tr>\n";

      if( $game_row['Black_Byotime'] > 0 or $game_row['White_Byotime'] > 0 )
      {
        echo " <tr>\n";
        echo '     <td>' . T_('Byoyomi') . ":</td>\n";
        echo '     <td>' .  echo_time( $game_row['White_Byotime'] );
        if( $game_row['White_Byotime'] > 0 ) echo ' (' . $game_row['White_Byoperiods'] . ')';

        echo "</td>\n";
        echo '     <td>' . echo_time( $game_row['Black_Byotime'] );

        if( $game_row['Black_Byotime'] > 0 ) echo ' (' . $game_row['Black_Byoperiods'] . ')';
        echo "</td>\n";
        echo "   </tr>\n";
      }

    }

      echo " <tr>\n";
      echo '       <td>' . T_('Time limit') . ":</td>\n";
      echo '       <td colspan=2>';

      echo echo_time_limit($game_row['Maintime'], $game_row['Byotype'], $game_row['Byotime'], $game_row['Byoperiods']);

      echo "</td>\n";
      echo "   </tr>\n";

    echo '   <tr><td>' . T_('Rated game') . ': </td><td colspan=2>' .
      ( $game_row['Rated'] == 'N' ? T_('No') : T_('Yes') ) . "</td></tr>\n";
    echo "    </table>\n";
}


function draw_notes( $notes, $gid, $height=0, $width=0)
{
   $notes = textarea_safe($notes); //always inside an edit box... no HTML effects.
   if( $height<3 ) $height= 3;
   if( $width<15 ) $width= 15;

   echo "<form name=\"savenotes\" action=\"savenotes.php\" method=\"POST\">\n";
   echo " <table>";
   echo "  <tr><td bgcolor='#7aa07a'><font color=white><b><span id=\"game_notes_caption\">" .
      T_('Private game notes') . "</span></b></font></td></tr>\n";
   echo "  <tr><td bgcolor='#ddf0dd'>\n";
   echo "   <textarea name=\"game_notes\" id=\"game_notes\" cols=\"$width\" rows=\"$height\">$notes</textarea>";
   echo "  </td></tr>\n";
   echo "  <tr><td><input type=\"submit\" value=\"" . T_('Save notes') . "\"></td></tr>\n";
   echo " </table>\n";
//   echo " <input type=\"hidden\" name=\"refer_url\" value=\"". urlencode(get_request_url()) . "\">\n";
   echo " <input type=\"hidden\" name=\"refer_url\" value=\"". get_request_url() . "\">\n";
   echo " <input type=\"hidden\" name=\"gid\" value=\"". $gid . "\">\n";
   echo "</form>\n";
}

?>

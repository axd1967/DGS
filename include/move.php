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


//ajusted globals by check_move(): $array, $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//return: $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)
function check_move($print_error=true)
{
   global $coord, $colnr, $rownr, $Size, $array, $to_move, $Black_Prisoners, $White_Prisoners,
      $Last_X, $Last_Y, $Last_Move, $prisoners, $nr_prisoners, $flags;

   list($colnr,$rownr) = sgf2number_coords($coord, $Size);

   if( !isset($rownr) or !isset($colnr) or @$array[$colnr][$rownr] >= 1 )
   {
      if( $print_error )
         error("illegal_position",'move1');
      else
      {
         echo "Illegal_position";
         return false;
      }
   }

   $array[$colnr][$rownr] = $to_move;


   $prisoners = array();
   check_prisoners($colnr,$rownr, WHITE+BLACK-$to_move, $Size, $array, $prisoners);


   $nr_prisoners = count($prisoners);

   if( $to_move == BLACK )
      $Black_Prisoners += $nr_prisoners;
   else
      $White_Prisoners += $nr_prisoners;

   // Check for ko

   if( $nr_prisoners == 1 and $flags & KO )
   {
      list($dummy, list($x,$y)) = each($prisoners);

      if( ($Last_X == $x and $Last_Y == $y )
        or $Last_Move == number2sgf_coords( $x,$y, $Size) )
      {
         if( $print_error )
            error("ko");
         else
         {
            echo "ko";
            return false;
         }
      }
   }

   // Check for suicide

   $suicide_allowed = false;

   if( !has_liberty_check($colnr, $rownr, $Size, $array, $prisoners, $suicide_allowed) )
   {
      if(!$suicide_allowed)
      {
         if( $print_error )
            error("suicide");
         else
         {
            echo "suicide";
            return false;
         }

      }
   }

   // Ok, all tests passed.
   return true;
}

function check_handicap() //adjust $handi, $stonestring, $enable_message and others
{
   global $stonestring, $colnr, $rownr, $Size, $array, $coord, $Handicap,
      $enable_message, $extra_message, $handi;

   if( !$stonestring ) $stonestring = "1";

   // add handicap stones to array

   $l = strlen( $stonestring );

   for( $i=1; $i < $l; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $Size);

      if( !isset($rownr) or !isset($colnr) or $array[$colnr][$rownr] != NONE )
         error("illegal_position",'move2');

      $array[$colnr][$rownr] = BLACK;
   }

   if( $coord )
   {
      list($colnr,$rownr) = sgf2number_coords($coord, $Size);

      if( !isset($rownr) or !isset($colnr) or $array[$colnr][$rownr] != NONE )
         error("illegal_position",'move3');

      $array[$colnr][$rownr] = BLACK;
      $stonestring .= $coord;
   }

   if( (strlen( $stonestring ) / 2) < $Handicap )
   {
      $enable_message = false;
      $extra_message = "<font color=\"green\">" .
        T_('Place your handicap stones, please!') . "</font>";
   }

   $handi = true;

}


//ajusted globals by check_remove(): $array, $score, $stonestring;
function check_remove( $coord=false )
{
   global $stonestring, $Size, $array,
      $Komi, $score, $White_Prisoners, $Black_Prisoners;

   if( !$stonestring ) $stonestring = "1";

   // toggle marked stones and marked dame to array

   $l = strlen( $stonestring );

   // $stonearray is used to cancel out duplicates, in order to make $stonestring shorter.
   $stonearray = array();

   for( $i=1; $i < $l; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $Size);

      if( !isset($rownr) or !isset($colnr) )
         error("illegal_position",'move4');

      $stone = isset($array[$colnr][$rownr]) ? $array[$colnr][$rownr] : NONE ;
      if( $stone == BLACK or $stone == WHITE or $stone == NONE ) //NONE for MARKED_DAME
         $array[$colnr][$rownr] = $stone + OFFSET_MARKED;
      else if( $stone == BLACK_DEAD or $stone == WHITE_DEAD or $stone == MARKED_DAME )
         $array[$colnr][$rownr] = $stone - OFFSET_MARKED;

      if( !isset( $stonearray[$colnr][$rownr] ) )
         $stonearray[$colnr][$rownr] = true;
      else
         unset( $stonearray[$colnr][$rownr] );
   }

   if( $coord )
   {
      list($colnr,$rownr) = sgf2number_coords($coord, $Size);

      if( !isset($rownr) or !isset($colnr) )
         error("illegal_position",'move5');

      $stone = isset($array[$colnr][$rownr]) ? $array[$colnr][$rownr] : NONE ;
      if ( MAX_SEKI_MARK<=0 or ($stone!=NONE and $stone!=MARKED_DAME) )
      {
         if( $stone!=BLACK and $stone!=WHITE and $stone!=BLACK_DEAD and $stone!=WHITE_DEAD )
            error("illegal_position",'move6');
      }

      $marked = array();
      toggle_marked_area( $colnr, $rownr, $Size, $array, $marked );

      while( list($dummy, list($colnr,$rownr)) = each($marked) )
      {
         if( !isset( $stonearray[$colnr][$rownr] ) )
            $stonearray[$colnr][$rownr] = true;
         else
            unset( $stonearray[$colnr][$rownr] );
      }
   }

   $stonestring = '1';
   while( list($colnr, $sub) = each($stonearray) )
   {
      while( list($rownr, $dummy) = each($sub) )
      {
         $stonestring .= number2sgf_coords($colnr, $rownr, $Size);
      }
   }

   $score = create_territories_and_score( $Size, $array );
   $score += $White_Prisoners - $Black_Prisoners + $Komi;

}

function draw_message_box()
{
   global $action, $gid, $stonestring, $coord, $prisoner_string, $move;

   echo '
  <FORM name="confirmform" action="confirm.php" method="POST">
    <center>
      <TABLE align="center">
        <TR>
          <TD align=right>' . T_('Message') . ':</TD>
          <TD align=left>
            <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
        </TR>
        <input type="hidden" name="gid" value="' . $gid . '">
        <input type="hidden" name="move" value="' . $move .'">
        <input type="hidden" name="action" value="' . $action .'">
';

    if( $action == 'move' )
    {
       echo "<input type=\"hidden\" name=\"coord\" value=\"$coord\">\n";
       echo "<input type=\"hidden\" name=\"prisoner_string\" value=\"$prisoner_string\">\n";
    }
    else if( $action == 'done' or $action == 'handicap' )
    {
       echo "<input type=\"hidden\" name=\"stonestring\" value=\"$stonestring\">\n";
    }

   echo '
      </TABLE>
<TABLE align=center cellpadding=5>
<TR><TD><input type=submit name="nextgame" value="' .
      T_('Submit and go to next game') . '"></TD>
    <TD><input type=submit name="nextstatus" value="' .
      T_("Submit and go to status") . '"></TD></TR>
<TR><TD align=right colspan=2><input type=submit name="nextback" value="' .
      T_("Go back") . '"></TD></TR>
      </TABLE>
    </CENTER>
  </FORM>
';

}

function draw_game_info($row)
{
  echo "<table align=center border=2 cellpadding=3 cellspacing=3>\n";
  echo "   <tr>\n";
  echo "     <td></td><td width=" . ($row['Size']*9) . ">" .
    T_('White') . "</td><td width=" . ($row['Size']*9) . ">" . T_('Black') . "</td>\n";
  echo "   </tr><tr>\n";
  echo "     <td>" . T_('Name') . ":</td>\n";
  echo '     <td>'
       . user_reference( 1, 1, '', $row['White_ID'], $row['Whitename'], $row['Whitehandle'])
       . "</td>\n";
  echo '     <td>'
       . user_reference( 1, 1, '', $row['Black_ID'], $row['Blackname'], $row['Blackhandle'])
       . "</td>\n";
  echo "   </tr><tr>\n";

  echo '     <td>' . T_('Rating') . ":</td>\n";
  echo '     <td>' . echo_rating( ($row['Status']==='FINISHED' ?
                                   $row['White_Start_Rating'] : $row['Whiterating'] ),
                                  true, $row['White_ID'] ) . "</td>\n";
  echo '     <td>' . echo_rating( ($row['Status']==='FINISHED' ?
                                   $row['Black_Start_Rating'] : $row['Blackrating'] ),
                                  true, $row['Black_ID'] ) . "</td>\n";
  echo "   </tr><tr>\n";

  echo '     <td>' . T_('Rank info') . ":</td>\n";
  echo '     <td>' . make_html_safe($row['Whiterank'], true) . "</td>\n";
  echo '     <td>' . make_html_safe($row['Blackrank'], true) . "</td>\n";
  echo "   </tr><tr>\n";
  echo '     <td>' . T_('Prisoners') . ":</td>\n";
  echo '     <td>' . $row['White_Prisoners'] . "</td>\n";
  echo '     <td>' . $row['Black_Prisoners'] . "</td>\n";
  echo "   </tr><tr>\n";
  echo '     <td></td><td>' . T_('Komi') . ': ' . $row['Komi'] . "</td>\n";
  echo '     <td>' . T_('Handicap') . ': ' . $row['Handicap'] . "</td>\n";
  echo "   </tr>\n";

  if( $row['Status'] != 'FINISHED' and ($row['Maintime'] > 0 or $row['Byotime'] > 0))
    {
      echo " <tr>\n";
      echo '     <td>' . T_('Main time') . ":</td>\n";
      echo '     <td>' .  echo_time( $row['White_Maintime'] ) . "</td>\n";
      echo '     <td>' .  echo_time( $row['Black_Maintime'] ) . "</td>\n";
      echo "   </tr>\n";

      if( $row['Black_Byotime'] > 0 or $row['White_Byotime'] > 0 )
      {
        echo " <tr>\n";
        echo '     <td>' . T_('Byoyomi') . ":</td>\n";
        echo '     <td>' .  echo_time( $row['White_Byotime'] );
        if( $row['White_Byotime'] > 0 ) echo ' (' . $row['White_Byoperiods'] . ')';

        echo "</td>\n";
        echo '     <td>' . echo_time( $row['Black_Byotime'] );

        if( $row['Black_Byotime'] > 0 ) echo ' (' . $row['Black_Byoperiods'] . ')';
        echo "</td>\n";
        echo "   </tr>\n";
      }

      echo " <tr>\n";
      echo '       <td>' . T_('Time limit') . ":</td>\n";
      echo '       <td colspan=2>';

      echo echo_time_limit($row['Maintime'], $row['Byotype'], $row['Byotime'], $row['Byoperiods']);

      echo "</td>\n";
      echo "   </tr>\n";

    }

    echo '   <tr><td>' . T_('Rated') . ': </td><td colspan=2>' .
      ( $row['Rated'] == 'N' ? T_('No') : T_('Yes') ) . "</td></tr>\n";
    echo "    </table>\n";
}



function draw_moves()
{
   global $moves_result, $gid, $move, $Size;

   mysql_data_seek($moves_result, 0);


   echo '<table border=4 cellspacing=0 cellpadding=1 align=center bgcolor=66C17B><tr align=center><th>' . T_('Moves') . '</th>
';

   $moves_per_row = 20;

   for($i=0; $i<$moves_per_row; $i++)
     echo "<td>$i</td>";

   echo '</tr>
<tr align=center><td>1-'. ($moves_per_row - 1) . '</td><td>&nbsp;</td>';

   $i=1;
   while( $row = mysql_fetch_array($moves_result) )
   {
      $s = $row["Stone"];
      if( $s != BLACK and $s != WHITE ) continue;
      if( $i % $moves_per_row == 0 )
         echo "</tr>\n<tr align=center><td>$i-" . ($i + $moves_per_row - 1) . '</td>';

      if( $row["PosX"] == -1 )
         $c = 'P';
      else if( $row["PosX"] == -2 )
         $c = 'S';
      else if( $row["PosX"] == -3 )
         $c = 'R';
      else
      {
         $c=number2board_coords($row["PosX"], $row["PosY"], $Size);
      }
      if( $i == $move )
         printf('<td class=r bgcolor=F7F5E3><font color=red>%s</font></td>
', $c );
      else if( $s == BLACK )
         printf( '<td><A class=b href="game.php?gid=%d&move=%d">%s</A></td>
', $gid, $i, $c );
      else
         printf( '<td><a class=w href="game.php?gid=%d&move=%d">%s</a></td>
', $gid, $i, $c );

      $i++;
   }
   echo "</tr></table>\n";

}

function draw_notes( $notes, $height, $width, $gid)
{

   echo "<form name=\"savenotes\" action=\"savenotes.php\" method=\"post\">\n";
   echo " <input type=\"hidden\" name=\"refer_url\" value=\"". get_request_url() . "\">\n";
   echo " <input type=\"hidden\" name=\"gid\" value=\"". $gid . "\">\n";
   echo " <table>";
   echo "  <tr><td bgcolor='#7aa07a'><font color=white><b><span id=\"notes_caption\">" .
      T_('Private game notes') . "</span></b></font></td></tr>\n";
   echo "  <tr><td bgcolor='#ddf0dd'>\n";
   $notes = textarea_safe($notes); //always inside an edit box... no HTML effects.
   echo "   <textarea name=\"notes\" id=\"notes\" cols=\"$width\" rows=\"$height\" wrap=\"virtual\">$notes</textarea>";
   echo "  </td></tr>\n";
   echo "  <tr><td><input type=\"submit\" value=\"" . T_('Save notes') . "\"></td></tr>\n";
   echo " </table>\n";
   echo "</form>\n";
}

?>
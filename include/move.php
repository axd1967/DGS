<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

function check_move($print_error=true)
{
   global $coord, $colnr, $rownr, $Size, $array, $to_move, $Black_Prisoners, $White_Prisoners,
      $Last_X, $Last_Y, $prisoners, $nr_prisoners, $flags;

   $colnr = ord($coord)-ord('a');
   $rownr = ord($coord[1])-ord('a');


   if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size 
       or $colnr < 0 or $array[$colnr][$rownr] >= 1 )
   {
      if( $print_error )
         error("illegal_position");
      else 
      {
         echo "Illegal_position";
         return false;
      }
   }

   $array[$colnr][$rownr] = $to_move;


   $prisoners = array();
   check_prisoners($colnr,$rownr, 3-$to_move, $Size, $array, $prisoners);
         
         
   $nr_prisoners = count($prisoners);
         
   if( $to_move == BLACK )
      $Black_Prisoners += $nr_prisoners;
   else
      $White_Prisoners += $nr_prisoners;

   // Check for ko
                  
   if( $nr_prisoners == 1 and $flags & KO )
   {
      list($dummy, list($x,$y)) = each($prisoners);

      if( $Last_X == $x and $Last_Y == $y )
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

function check_handicap()
{
   global $stonestring, $colnr, $rownr, $Size, $array, $coord, $Handicap, 
      $enable_message, $extra_message, $handi;

   if( !$stonestring ) $stonestring = "1";

   // add killed stones to array
         
   $l = strlen( $stonestring );

   for( $i=1; $i < $l; $i += 2 )
   {
      $colnr = ord($stonestring[$i])-ord('a');
      $rownr = ord($stonestring[$i+1])-ord('a');
                 
      if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 
         or $array[$colnr][$rownr] )
      {
         error("illegal_position");
      }

      $array[$colnr][$rownr] = BLACK;
   }

   if( $coord )
   {
      $colnr = ord($coord)-ord('a');
      $rownr = ord($coord[1])-ord('a');

      if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0
          or $array[$colnr][$rownr] )
      {
         error("illegal_position");
      }

      $array[$colnr][$rownr] = BLACK;
      $stonestring .= chr(ord('a') + $colnr) . chr(ord('a') + $rownr);
   }

   if( (strlen( $stonestring ) / 2) < $Handicap )
   {
      $enable_message = false;
      $extra_message = "<font color=\"green\">Place your handicap stones, please!</font>";
   }

   $handi = true;

}

function check_done()
{
   global $stonestring, $Size, $array, $prisoners, $Komi, $score, 
      $White_Prisoners, $Black_Prisoners;

   if( !$stonestring ) $stonestring = "1";

   // add killed stones to array
         
   $l = strlen( $stonestring );
   $index = array();

   for( $i=1; $i < $l; $i += 2 )
   {
      $colnr = ord($stonestring[$i])-ord('a');
      $rownr = ord($stonestring[$i+1])-ord('a');
                 
      if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
      {
         error("illegal_position");
      }

      if( $index[$colnr][$rownr] )
         unset($index[$colnr][$rownr]);
      else
         $index[$colnr][$rownr] = TRUE;

      $stone = $array[$colnr][$rownr];
      if( $stone == BLACK or $stone == WHITE )
         $array[$colnr][$rownr] = $stone + 6;
      else if( $stone == BLACK_DEAD or $stone == WHITE_DEAD )
         $array[$colnr][$rownr] = $stone - 6;
   }
         
   $prisoners = array();
   while( list($x, $sub) = each($index) )
   {
      while( list($y, $val) = each($sub) )
      {
         array_push($prisoners, array($x,$y));
      }
   }

   $score = create_territories_and_score( $Size, $array );
   $score += $White_Prisoners - $Black_Prisoners + $Komi;

}

function check_remove()
{
   global $stonestring, $Size, $array, $prisoners, $coord;
  
   if( !$stonestring ) $stonestring = "1";
  
   // add killed stones to array
  
   $l = strlen( $stonestring );
  
   for( $i=1; $i < $l; $i += 2 )
   {
      $colnr = ord($stonestring[$i])-ord('a');
      $rownr = ord($stonestring[$i+1])-ord('a');
      
      if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
      {
         error("illegal_position");
      }

      $stone = $array[$colnr][$rownr];
      if( $stone == BLACK or $stone == WHITE )
         $array[$colnr][$rownr] = $stone + 6;
      else if( $stone == BLACK_DEAD or $stone == WHITE_DEAD )
         $array[$colnr][$rownr] = $stone - 6;
   }
  
   if( $coord )
   {
      $colnr = ord($coord)-ord('a');
      $rownr = ord($coord[1])-ord('a');

      $stone = $array[$colnr][$rownr];
      if(( $stone != BLACK and $stone != WHITE and 
           $stone != BLACK_DEAD and $stone != WHITE_DEAD ) or
         $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
      {
         error("illegal_position");
      }
                 
      $prisoners = array();
      remove_dead( $colnr, $rownr, $array, $prisoners );

      while( list($dummy, list($x,$y)) = each($prisoners) )
      {
         $stonestring .= chr(ord('a') + $x) . chr(ord('a') + $y);
      }
   }

}

function draw_message_box()
{
   global $action, $gid, $stonestring, $coord;
?>
  <FORM name="confirmform" action="confirm.php" method="POST">
    <center>
      <TABLE align="center">
        <TR>
          <TD align=right>Message:</TD>
          <TD align=left>  
            <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
        </TR>
        <input type="hidden" name="gid" value="<?php echo $gid; ?>">
          <input type="hidden" name="action" value="<?php echo $action; ?>">
<?php 
    if( $action == 'move' ) 
    { 
       echo "       <input type=\"hidden\" name=\"coord\" value=\"$coord\">\n";
    }
    else if( $action == 'done' or $action == 'handicap' )
    {
       echo "<input type=\"hidden\" name=\"stonestring\" value=\"" . $stonestring . "\">\n";
    } 

?>
         <TR><TD></TD>
                <TD><input type=submit name="next" value="Submit and go to next game">
                    <input type=submit name="next" value="Submit and go to status"></TD></TR>

              
              <TR><TD></TD>
                <TD align=right><input type=submit name="next" value="Go back"></TD></TR>
  
      </TABLE>
    </CENTER>
  </FORM>


<?php

}

function draw_game_info()
{
  global $Size, $Whiterating, $Blackrating, $Whiterank, $Blackrank, 
    $Whitename, $Blackname, $Whitehandle, $Blackhandle, $White_Prisoners, $Black_Prisoners,
    $White_ID, $Black_ID, $Komi, $Handicap, $Status, $Maintime, $Byotime, $White_Maintime, 
    $Black_Maintime, $White_Byotime, $Black_Byotime, $White_Byoperiods, $Black_Byoperiods,
     $Byotype, $Rated, $Byoperiods;
?>
    <table align=center border=2 cellpadding=3 cellspacing=3>
        <tr>
<td></td><td width=<?php echo ($Size*9) . ">White</td><td width=" . ($Size*9) . ">Black</td>"; ?>
        </tr><tr>
          <td>Name:</td>
          <td><A href="userinfo.php?uid=<?php echo "$White_ID\">$Whitename ($Whitehandle)"; ?></A></td>
          <td><A href="userinfo.php?uid=<?php echo "$Black_ID\">$Blackname ($Blackhandle)"; ?></A></td>

        </tr><tr>
          <td>Rating:</td>
          <td><?php echo_rating( $Whiterating ); ?></td>
          <td><?php echo_rating( $Blackrating ); ?></td>

        </tr><tr>
          <td>Rank info:</td>
          <td><?php echo( $Whiterank ); ?></td>
          <td><?php echo( $Blackrank ); ?></td>
          
        </tr><tr>
          <td>Prisoners:</td>
          <td><?php echo( $White_Prisoners );?></td>
          <td><?php echo( $Black_Prisoners );?></td>
          
        </tr><tr>
          <td></td><td>Komi: <?php echo( $Komi );?></td>
          <td>Handicap: <?php echo( $Handicap );?></td>
        </tr>

<?php
   if( $Status != 'FINISHED' and ($Maintime > 0 or $Byotime > 0))
   {
?>
      <tr>
          <td>Main Time:</td><td> <?php echo_time( $White_Maintime );?></td>
          <td><?php echo_time( $Black_Maintime );?> </td>
        </tr>
<?php
      if( $Black_Byotime > 0 or $White_Byotime > 0 )
      {
?>

      <tr>
          <td>Byoyomi:</td>
          <td> 
<?php echo_time( $White_Byotime );
      if( $White_Byotime > 0 ) echo '(' . $White_Byoperiods . ')'; 
?></td>
          <td> 
<?php echo_time( $Black_Byotime );
      if( $Black_Byotime > 0 ) echo '(' . $Black_Byoperiods . ')'; 
?></td>
        </tr>


<?php
       }
?>
      <tr>
            <td>Time limit:</td><td colspan=2> 
<?php 
      if ( $Maintime > 0 )
         echo_time( $Maintime );
      if( $Byotime <= 0 )
         echo ' without byoyomi';
      else if( $Byotype == 'FIS' )
      {
         echo ' with ';
         echo_time($Byotime);
         echo ' extra per move';
      }
      else
      {
         if ( $Maintime > 0 )
            echo ' + ';
         echo_time($Byotime); 
         echo '/' . $Byoperiods .  ($Byotype == 'JAP' ? '&nbsp;periods&nbsp;Japanese' : '&nbsp;stones&nbsp;Canadian') . '&nbsp;byoyomi';
      }
?></td>
        </tr>
<?php
    }

    echo '<tr><td>Rated: </td><td colspan=2>' . ( $Rated == 'Y' ? 'Yes' : 'No' ) . '</td></tr>
    </table>
';
}



function draw_moves()
{
   global $moves_result, $gid, $move, $Size;
   
   mysql_data_seek($moves_result, 0);
   
   
   echo '<table border=4 cellspacing=0 cellpadding=1 align=center bgcolor=66C17B><tr align=center><th>Moves</th>
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
         $col = chr($row["PosX"]+ord('a'));
         if( $col >= 'i' ) $col++;
         $c = $col . ($Size - $row["PosY"]);
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
   echo "</tr></table>";

}

?>
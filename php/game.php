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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

require( "include/std_functions.php" );
require( "include/board.php" );
require( "include/move.php" );
require( "include/rating.php" );

if( !$gid )
{
    header("Location: error.php?err=no_game_nr");
    exit;
}

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

$result = mysql_query( "SELECT Games.*, " .
                       "Games.Flags+0 AS flags, " . 
                       "black.Name AS Blackname, " .
                       "black.Handle AS Blackhandle, " .
                       "black.Rank AS Blackrank, " .
                       "black.Rating AS Blackrating, " .
                       "black.RatingStatus AS Blackratingstatus, " .
                       "white.Name AS Whitename, " .
                       "white.Handle AS Whitehandle, " .
                       "white.Rank AS Whiterank, " .
                       "white.Rating AS Whiterating, " .
                       "white.RatingStatus AS Whiteratingstatus " .
                       "FROM Games, Players AS black, Players AS white " .
                       "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

if(  mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=unknown_game");
    exit;
}

extract(mysql_fetch_array($result));

if( $Status == 'INVITED' )
{
    header("Location: error.php?err=unknown_game");
    exit;
}

if( $action and $player_row["ID"] != $ToMove_ID )
{
    header("Location: error.php?err=not_your_turn");
    exit;
}

$may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID and (!$move or $move == $Moves) );

if( $Black_ID == $ToMove_ID )
    $to_move = BLACK;
else if( $White_ID == $ToMove_ID )
    $to_move = WHITE;
else if( isset($to_move) )
{       
  echo $to_move;
    header("Location: error.php?err=database_corrupted");
    exit;
}

if( !$action )
{
    $action = 'just_looking';
    if( $may_play )
        {
            if( $Status == 'PLAY' or $Status == 'PASS' )
                {
                    $action = 'choose_move';
                    if( $Moves == 0 and $Handicap > 0 )
                        $action = 'handicap';
                }
            else if( $Status == 'SCORE' or $Status == 'SCORE2' )
                $action = 'remove';
        }
}

if( $Status != 'FINISHED' and ($Maintime > 0 or $Byotime > 0) )
{
  $ticks = get_clock_ticks($ClockUsed) - $LastTicks;
  $hours = ( $ticks > 0 ? (int)(($ticks-1) / $tick_frequency) : 0 );

  if( $to_move == BLACK )
    {
      time_remaining($hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods, $Maintime,
      $Byotype, $Byotime, $Byoperiods, false);
    }
  else
    {
      time_remaining($hours, $White_Maintime, $White_Byotime, $White_Byoperiods, $Maintime,
      $Byotype, $Byotime, $Byoperiods, false);
    }
}



$no_marked_dead = ( $Status == 'PLAY' or $Status == 'PASS' or 
                    $action == 'choose_move' or $action == 'move' );

list($lastx,$lasty) = 
make_array( $gid, $array, $msg, $Moves, $move, $moves_result, $marked_dead, $no_marked_dead );

$enable_message = true;

switch( $action )
{
 case 'just_looking':
     {
         if( $Status == 'FINISHED' )
             $extra_message = "<font color=\"blue\">" . score2text($Score, true) . "</font>";
         $enable_message = false;
         if( $move )
             {
                 $Last_X = $lastx;
                 $Last_Y = $lasty;
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
       check_move();
       
       $Moves++;
       $Last_X = $colnr;
       $Last_Y = $rownr;
     }
     break;

 case 'handicap':
     {
         if( $Status != 'PLAY' )
             {
                 header("Location: error.php?err=invalid_action");
                 exit;
             }

         check_handicap();

     }
     break;

 case 'resign':
     {
         $extra_message = "<font color=\"red\">Resigning</font>";
     }
     break;


 case 'pass':
     {
         if( $Status != 'PLAY' and $Status != 'PASS' )
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }
         $extra_message = "<font color=\"green\">Passing</font>";
     }
     break;

 case 'delete':
     {
         if( $Status != 'PLAY' or ( $Moves >= 4+$Handicap ) )
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }
         $extra_message = "<font color=\"red\">Deleting game</font>";
     }
     break;

 case 'remove':
     {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }
         
         check_remove();

         $enable_message = false;

         $extra_message = "<font color=\"green\">Please mark dead stones and click 'done' when finished.</font>";
     }
     break;

 case 'done':
     {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }

         check_done();

         $extra_message = "<font color=\"blue\">Score: " . 
              score2text($score, true) . "</font>";
     }
     break;

 default:
     {
         header("Location: error.php?err=illegal_action");
         exit;
     }
}


start_page("Game", true, $logged_in, $player_row );

if( $enable_message ) $may_play = false;

if( !$logged_in or ( $player_row["ID"] != $Black_ID and $player_row["ID"] != $White_ID ) )
     unset( $msg );

draw_board($Size, $array, $may_play, $gid, $Last_X, $Last_Y, 
           $player_row["Stonesize"], $player_row["Boardfontsize"], $msg, $stonestring, $handi );


if( $extra_message )
     echo "<P><center>$extra_message</center>\n";

if( $enable_message )
{
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

?>
<HR>
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
      </tr><tr>
          <td>Main Time:</td><td> <?php echo_time( $White_Maintime );?></td>
          <td><?php echo_time( $Black_Maintime );?> </td>
        </tr>
<?php
if( $Black_Byotime > 0 or $White_Byotime > 0 )
    {
?>

      </tr><tr>
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
      </tr><tr>
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
';

?>
    </table>
<HR>
<?php

// display moves

if( !$enable_message and $Moves > 0 )
{
    mysql_data_seek($moves_result, 0);


echo '<table border=4 cellspacing=0 cellpadding=1 align=center class="moves"><tr align=center><th>Moves</th>
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
        echo "</tr>\n<tr align=center><td>$i-" . ($i + $moves_per_row - 1) . "</td>\n";

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
        printf('<td  class="r">%s</td>
', $c );
    else if( $s == BLACK )        
      printf( '<td><a class="b" href=game.php?gid=%d&move=%d>%s</A></td>
', $gid, $i, $c );
    else
      printf( '<td><a class="w" href=game.php?gid=%d&move=%d>%s</A></td>
', $gid, $i, $c );

    $i++;    
}
echo "</tr></table>";
}

if( $action == 'remove' or $action == 'choose_move' or $action == 'just_looking' or 
    $action == 'handicap' )
{
echo '
    <p>
    <table width="100%" border=0 cellspacing=0 cellpadding=4>
      <tr align="center">
';
if( $action == 'choose_move' )
    {
      $width= ( $Moves < 4+$Handicap ? '25%' : '33%' );

      echo "<td width=$width><B><A href=\"game.php?gid=$gid&action=pass\">Pass</A></B></td>\n";

      if( $Moves < 4+$Handicap )
        echo "<td width=25%><B><A href=\"game.php?gid=$gid&action=delete\">Delete game</A></B></td>\n";
    }
else if( $action == 'remove' )
    {
   echo "<td width=25%><B><A href=\"game.php?gid=$gid&action=done&stonestring=$stonestring\">Done</A></B></td>
<td width=25%><B><A href=\"game.php?gid=$gid&action=choose_move\">Resume playing</A></B></td>\n";
   $width="25%";
    }
else if( $action == 'handicap' )
  {
    echo "<td width=50%><B><A href=\"game.php?gid=$gid&action=delete\">Delete game</A></B></td>\n";
    $width="50%";
  }

echo "<td><B><A href=\"sgf.php?gid=$gid\">Download sgf</A></B></td>\n";

 if( $action != 'just_looking' and $action != 'handicap' )
     echo "<td width=$width><B><A href=\"game.php?gid=$gid&action=resign\">Resign</A></B></td>\n";
echo "

      </tr>
    </table>
";
end_page(false); 
}
else
end_page(); 
?>

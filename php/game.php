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

include( "std_functions.php" );
include( "board.php" );

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
                       "white.Name AS Whitename, " .
                       "white.Handle AS Whitehandle, " .
                       "white.Rank AS Whiterank " .
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

$may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID and !$move );

if( $Black_ID == $ToMove_ID )
    $to_move = BLACK;
else
    $to_move = WHITE;


if( !$action )
{
    $action = 'just_looking';
    if( $may_play )
        {
            if( $Status == 'PLAY' or $Status == 'PASS' )
                $action = 'choose_move';
            else if( $Status == 'SCORE' or $Status == 'SCORE2' )
                $action = 'remove';
        }
}

$no_marked_dead = ( $Status == 'PLAY' or $Status == 'PASS' or 
                    $action == 'choose_move' or $action == 'move' );

make_array( $gid, $array, $msg, $Moves, $move, $marked_dead, $no_marked_dead );

$enable_message = true;

switch( $action )
{
 case 'just_looking':
 case 'choose_move':
     {
         $enable_message = false;
     }
     break;

 case 'move':
     {
         $colnr = ord($coord)-ord('a');
         $rownr = ord($coord[1])-ord('a');

         if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 
         or $array[$colnr][$rownr] >= 1 )
             {
                 header("Location: error.php?err=illegal_position");
                 exit;
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
                         header("Location: error.php?err=ko");
                         exit;
                     }
             }

         // Check for suicide
         
         $suicide_allowed = false;
         
         if( !has_liberty_check($colnr, $rownr, $Size, $array, $prisoners, $suicide_allowed) )
             {
                 if(!$suicide_allowed)
                     {
                         header("Location: error.php?err=suicide");
                         exit;
                     }
             }
         

         // Ok, all tests passed.
         
         $Moves++;
         $Last_X = $colnr;
         $Last_Y = $rownr;
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

 case 'remove':
     {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }

         $enable_message = false;

         if( !$killedstring ) $killedstring = "1";

         // add killed stones to array
         
         $l = strlen( $killedstring );

         for( $i=1; $i < $l; $i += 2 )
             {
                 $colnr = ord($killedstring[$i])-ord('a');
                 $rownr = ord($killedstring[$i+1])-ord('a');
                 
                 if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
                     {
                         header("Location: error.php?err=illegal_position");
                         exit;
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
                         header("Location: error.php?err=illegal_position");
                         exit;
                     }
                 
                 $prisoners = array();
                 remove_dead( $colnr, $rownr, $array, $prisoners );

                 while( list($dummy, list($x,$y)) = each($prisoners) )
                     {
                         $killedstring .= chr(ord('a') + $x) . chr(ord('a') + $y);
                     }
             }


     }
     break;

 case 'done':
     {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }


         if( !$killedstring ) $killedstring = "1";

         // add killed stones to array
         
         $l = strlen( $killedstring );
         $index = array();

         for( $i=1; $i < $l; $i += 2 )
             {
                 $colnr = ord($killedstring[$i])-ord('a');
                 $rownr = ord($killedstring[$i+1])-ord('a');
                 
                 if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
                     {
                         header("Location: error.php?err=illegal_position");
                         exit;
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

         $extra_message = "<font color=\"blue\">Score: $score</font>";
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
           $player_row["Stonesize"], $player_row["Boardfontsize"], $msg, $killedstring );


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
        echo "
      <input type=\"hidden\" name=\"prisoners\" value=\"" . urlencode(serialize($prisoners)) . "\">
      <input type=\"hidden\" name=\"colnr\" value=\"$colnr\">
      <input type=\"hidden\" name=\"rownr\" value=\"$rownr\">\n";
    }
else if( $action == 'done' )
    {
        echo "
      <input type=\"hidden\" name=\"prisoners\" value=\"" . urlencode(serialize($prisoners)) . "\">\n";
        echo "
      <input type=\"hidden\" name=\"score\" value=\"$score\">\n";

    }

?>
      <TR><TD></TD>
        <TD><input type=submit name="next" value="Submit and go to next game"></TD></TR>
      <TR><TD></TD>
        <TD><input type=submit name="next" value="Submit and go to status"></TD></TR>
      </TR>
  
    </TABLE>
  </CENTER>
</FORM>


<?php

}

?>
<HR>
    <table align=center border=2 cellpadding=3 cellspacing=3>
        <tr>
          <td></td><td>White</td><td>Black</td>
        </tr><tr>
          <td>Name:</td>
          <td><A href="userinfo.php?uid=<?php echo "$White_ID\">$Whitename ($Whitehandle)"; ?></A</td>
          <td><A href="userinfo.php?uid=<?php echo "$Black_ID\">$Blackname ($Blackhandle)"; ?></A</td>
        </tr><tr>
          <td>Rank:</td>
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
    </table>
<HR>
<?php

if( ( $action == 'remove' or $action == 'choose_move' ) and $Moves >= $Handicap )
{
echo "
    <p>
    <table width=\"100%\" border=0 cellspacing=0 cellpadding=4>
      <tr align=\"center\">\n";
if( $action == 'choose_move' )
   echo "<td><B><A href=\"game.php?gid=$gid&action=pass\">Pass</A></B></td>\n";
else
   echo "<td><B><A href=\"game.php?gid=$gid&action=done&killedstring=$killedstring\">Done</A></B></td>
<td><B><A href=\"game.php?gid=$gid&action=choose_move\">Resume</A></B></td>\n";
       

echo "
        <td><B><A href=\"game.php?gid=$gid&action=resign\">Resign</A></B></td>
      </tr>
    </table>
";
end_page(false); 
}
else
end_page(); 
?>

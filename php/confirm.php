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

include "connect2mysql.php";
include "std_functions.php";
include "board.php";

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )     
{
    header("Location: error.php?err=not_logged_in");
    exit;
}

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

if( mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=unknown_game");
    exit;
}

extract(mysql_fetch_array($result));

if( $flags & TOBECONFIRMED )
    $move=$Moves-1;
else 
    $move=$Moves;



make_array( $gid, $array, $msg, $Moves, $move );

if( $player_row["ID"] != $ToMove_ID )
{
    header("Location: error.php?err=not_your_turn");
    exit;
}

$colnr = ord($coord)-ord('a');
$rownr = ord($coord[1])-ord('a');

if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 
    or $array[$colnr][$rownr] >= 1 )
{
    header("Location: error.php?err=not_empty");
    exit;
}
  
// Check for captures


if( $Black_ID == $ToMove_ID )
    $next_move = BLACK;
else
    $next_move = WHITE;


$array[$colnr][$rownr] = $next_move;


$prisoners = array();
check_prisoners($colnr,$rownr, 3-$next_move, $Size, $array, $prisoners);


$nr_prisoners = count($prisoners);

// Check for ko

while( list($dummy, $pos) = each($prisoners) )
{
    list($x, $y) = $pos;
    $query .= ",($Moves, \"NONE\", $x, $y)";
}

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

start_page("Confirm move", true, $logged_in, $player_row );

draw_board($Size, $array, false, $gid, $colnr, $rownr, 
           $player_row["Stonesize"], $player_row["Boardfontsize"], $msg );

?>
<FORM name="confirmform" action="do_confirm.php" method="POST">
  <center>
    <TABLE align="center">
     <TR>
        <TD align=right>Message:</TD>
        <TD align=left>  
          <textarea name="message" cols="50" rows="8" wrap="virtual"></textarea></TD>
      </TR>
      <input type="hidden" name="gid" value="<?php echo $gid; ?>">
      <input type="hidden" name="prisoners" value="<?php echo urlencode(serialize($prisoners)); ?>">
      <input type="hidden" name="colnr" value="<?php echo $colnr; ?>">
      <input type="hidden" name="rownr" value="<?php echo $rownr; ?>">
      <TR><TD></TD>
        <TD><input type=submit name="action" value="Submit and go to next game"></TD></TR>
      <TR><TD></TD>
        <TD><input type=submit name="action" value="Submit and go to status"></TD></TR>
      </TR>
  
    </TABLE>
</FORM>

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
<?php
if( $next_move == BLACK )
{    
    echo "<td>". $White_Prisoners . "</td>\n";
    echo "<td>". ( $Black_Prisoners + $nr_prisoners ) . "</td>\n";
}
else      
{    
    echo "<td>". ( $White_Prisoners + $nr_prisoners ) . "</td>\n";
    echo "<td>". $Black_Prisoners . "</td>\n";
}
?>
        </tr><tr>
          <td></td><td>Komi: <?php echo( $Komi );?></td>
          <td>Handicap: <?php echo( $Handicap );?></td>
        </tr>
    </table>
<HR>
    


<?php
    end_page(); 
?>
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
include( "connect2mysql.php" );
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

$may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID and !$move);

if( $flags & TOBECONFIRMED )
{
    $move = $Moves-1;
}

start_page("Game", true, $logged_in, $player_row );

make_array( $gid, $array, $msg, $Moves, $move );

// Check if user may make a move



draw_board($Size, $array, $may_play, $gid, $Last_X, $Last_Y, 
           $player_row["Stonesize"], $player_row["Boardfontsize"], $msg );

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
    end_page(); 
?>

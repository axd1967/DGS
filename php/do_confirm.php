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

if( !$logged_in )
{
    header("Location: error.php?err=not_logged_in");
    exit;
}

$result = mysql_query( "SELECT *,Flags+0 AS flags FROM Games WHERE ID=$gid" );

if(  mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=unknown_game");
    exit;
}

extract(mysql_fetch_array($result));

if( $player_row["ID"] != $ToMove_ID )
{
    header("Location: error.php?err=not_your_turn");
    exit;
}



$prisoners = unserialize(urldecode($prisoners));
reset($prisoners);

$nr_prisoners = count($prisoners);

if( $nr_prisoners == 1 )
    $flags |= KO;
else
    $flags &= ~KO;

$Moves++;

$query = "INSERT INTO Moves$gid ( MoveNr, Stone, PosX, PosY, Text ) VALUES ";


while( list($dummy, $pos) = each($prisoners) )
{
    list($x, $y) = $pos;
    $query .= "($Moves, \"NONE\", $x, $y, \"\"), ";
}


if( $Black_ID == $ToMove_ID )
    $next_move = BLACK;
else
    $next_move = WHITE;

$query .= "($Moves, $next_move, $colnr, $rownr, '$message') ";

$result = mysql_query( $query );

if( mysql_affected_rows() < 1)
{
    header("Location: error.php?err=mysql_insert_move");
    exit;
}


$query = "UPDATE Games SET " .
         "Moves=$Moves, " .
         "Last_X=$colnr, " .
         "Last_Y=$rownr, ";


if( $ToMove_ID == $Black_ID )
{
     if( $nr_prisoners > 0 )
         $query .= "Black_Prisoners=" . ( $Black_Prisoners + $nr_prisoners ) . ", ";
     if( $Moves < $Handicap )
         $query .= "ToMove_ID=$Black_ID, ";
     else
         $query .= "ToMove_ID=$White_ID, ";
}
else
{
     if( $nr_prisoners > 0 )
         $query .= "White_Prisoners=" . ( $White_Prisoners + $nr_prisoners ) . ", ";
     $query .= "ToMove_ID=$Black_ID, ";
}

$query .= "Flags=$flags" .
          " WHERE ID=$gid";

$result = mysql_query( $query );


if( mysql_affected_rows() != 1)
{
    header("Location: error.php?err=mysql_update_game");
    exit;
}

if( $message )
{
    mysql_query( "UPDATE Moves$gid SET Text='$message' " .
                 "WHERE MoveNr=$Moves AND ( Stone=" . BLACK . " OR Stone=" . WHITE . ")" );
}

if( $action == "Submit and go to status" )
{
    header("Location: status.php");
    exit;
}
else if( $action == "Submit and go to next game" )
{
    // Should go to next game
    header("Location: status.php");
    exit;
}

header("Location: game.php?gid=$gid");
exit;
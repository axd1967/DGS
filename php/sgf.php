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

//function make_sgf($gid)
//{

include( "std_functions.php" );

connect2mysql();


$correct_handicap = true;

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
         return false;

    extract(mysql_fetch_array($result));

    $result = mysql_query( "SELECT * FROM Moves$gid" );


    echo "(;GM[1]\n" .
        "PC[Dragon Go Server: http://dragongoserver.sourceforge.net]\n" .
        "DT[" . date( "Y-m-d", time() ) . "]\n" .
        "PW[$Blackname]\n" .
        "PB[$Whitename]\n" .
        "WR[$Blackrank]\n" .
        "BR[$Whiterank]\n";
    if( isset($Score) )
        {
            echo "RE[" . score2text($Score, false) . "]\n";
        }

echo "SZ[$Size]\n";
if( $correct_handicap )
     echo "HA[$Handicap]\n";


if( $Handicap > 0 and $correct_handicap ) 
     echo "AB";

while( $row = mysql_fetch_array($result) )
{
    if( $row["Stone"] != WHITE and $row["Stone"] != BLACK )
        continue;
    
    if( $row["MoveNr"] > $Handicap or !$correct_handicap )
        echo( $row["Stone"] == WHITE ? ";W" : ";B" );
    
    echo "[" . chr($row["PosX"] + ord('a')) .
        chr($row["PosY"] + ord('a')) . "]";
}
echo "\n;)\n";

//}
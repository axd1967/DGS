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


require( "include/std_functions.php" );


{
   disable_cache();
   
   connect2mysql();


   $use_HA = false;
   $use_AB_for_handicap = true;
//$rules = "New Zealand";
   $sgf_version = 3;

   if( !$gid )
   {
      if( eregi("game([0-9]+)", $REQUEST_URI, $result) )
         $gid = $result[1];
   }

   if( !$gid )
      error("unknown_game");

   $result = mysql_query( 'SELECT Games.*, ' .
                          'Games.Flags+0 AS flags, ' . 
                          'UNIX_TIMESTAMP(Games.Starttime) AS startstamp, ' . 
                          'UNIX_TIMESTAMP(Games.Lastchanged) AS timestamp, ' . 
                          'black.Name AS Blackname, ' .
                          'black.Handle AS Blackhandle, ' .
                          'black.Rank AS Blackrank, ' .
                          'white.Name AS Whitename, ' .
                          'white.Handle AS Whitehandle, ' .
                          'white.Rank AS Whiterank ' .
                          'FROM Games, Players AS black, Players AS white ' .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

   if( mysql_num_rows($result) != 1 )
      error("unknown_game");
     
   extract(mysql_fetch_array($result));
     
   $result = mysql_query( "SELECT Moves.*,Text FROM Moves " . 
                          "LEFT JOIN MoveMessages " .
                          "ON Moves.gid=MoveMessages.gid AND Moves.MoveNr=MoveMessages.MoveNr " .
                          "WHERE Moves.gid=$gid order by Moves.ID" );

   header ('Content-Type: application/x-go-sgf');
   header( "Content-Disposition: inline; filename=\"$Whitehandle-$Blackhandle-" . 
           date('Ymd', $timestamp) . '.sgf"' ); 
   header( "Content-Description: PHP Generated Data" );

   echo "(;FF[$sgf_version]GM[1]
PC[Dragon Go Server: $HOSTBASE]
DT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]
PB[$Blackname]
PW[$Whitename]
BR[$Blackrank]
WR[$Whiterank]
";

   if( isset($Score) )
   {
      echo "RE[" . score2text($Score, false) . "]\n";
   }

   echo "SZ[$Size]\n";
   echo "KM[$Komi]\n";

   if( $rules )
      echo "RU[$rules]\n";

   if( $use_HA and $Handicap > 0 )
      echo "HA[$Handicap]\n";

   if( $Handicap > 1 )
      echo "PL[W]\n";

   $regexp = ( $Status == 'FINISHED' ? "c|comment|h|hidden" : "c|comment" );
      

   if( $Handicap > 0 and $use_AB_for_handicap ) 
      echo "AB";

   while( $row = mysql_fetch_array($result) )
   {
      if( $row["PosX"] < 0 or ($row["Stone"] != WHITE and $row["Stone"] != BLACK ) )
         continue;
    
      if( $row["MoveNr"] > $Handicap or !$use_AB_for_handicap )
         echo( $row["Stone"] == WHITE ? ";W" : ";B" );
    
      echo "[" . chr($row["PosX"] + ord('a')) .
         chr($row["PosY"] + ord('a')) . "]";




      if( $nr_matches = preg_match_all("'<($regexp)>(.*?)</($regexp)>'mis", $row["Text"],
                                       $matches, PREG__SET_ORDER) )
      {
         echo "C[";
         for($i=0; $i<$nr_matches; $i++)
         {
            echo ( $row["Stone"] == WHITE ? $Whitename : $Blackname ) . ": ";
            echo  str_replace("]","\]", trim($matches[2][$i])) .
               ( $i == $nr_matches-1 ? "" : "\n" );
         }
         echo "]";
      }


   }
   echo "\n)\n";

}
?>   
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


require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

function sgf_echo_comment( $com ) {
   if ( $com )
      echo "\nC[".str_replace("]","\]", ltrim($com,"\r\n"))."]\n";
}

{
   disable_cache();

   connect2mysql();


   $use_HA = false;
   $use_AB_for_handicap = true;
   $sgf_trim_level = -1; //-1= skip ending pass, -2= keep them
   $sgf_pass_highlight = 1; //0=no highlight, 1=with Name property, 2=in comments

//As board size may be > 'tt' coord, we can't use [tt] for pass moves
// so we use [] and, then, we need at least sgf_version = 4 (FF[4])
   $sgf_version = 4;

   if( !$gid )
   {
      if( eregi("game([0-9]+)", $REQUEST_URI, $result) )
         $gid = $result[1];
   }

   if( !$gid )
      error("unknown_game");

   $result = mysql_query(
      'SELECT Games.*, ' .
      'Games.Flags+0 AS flags, ' .
      'UNIX_TIMESTAMP(Games.Starttime) AS startstamp, ' .
      'UNIX_TIMESTAMP(Games.Lastchanged) AS timestamp, ' .
      'black.Name AS Blackname, ' .
      'black.Handle AS Blackhandle, ' .
      "IF(Games.Status='FINISHED', Games.Black_End_Rating, black.Rating2 ) AS Blackrating, " .
      'white.Name AS Whitename, ' .
      'white.Handle AS Whitehandle, ' .
      "IF(Games.Status='FINISHED',Games.White_End_Rating, white.Rating2 ) AS Whiterating " .
      'FROM Games, Players AS black, Players AS white ' .
      "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" )
      or die(mysql_error());

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


   $node_com = "";

   echo "(;FF[$sgf_version]GM[1]
PC[Dragon Go Server: $HOSTBASE]
DT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]
PB[$Blackname ($Blackhandle)]
PW[$Whitename ($Whitehandle)]\n";

   if( isset($Blackrating) or isset($Whiterating) )
   {
      echo "BR[" . ( isset($Blackrating) ? echo_rating($Blackrating, false) : '?' ) . "]\n" .
         "WR[" . ( isset($Whiterating) ? echo_rating($Whiterating, false) : '?' ) . "]\n";
   }

   if( isset($Score) )
   {
      echo "RE[" . score2text($Score, false, true) . "]\n";
   }

   echo "SZ[$Size]\n";
   echo "KM[$Komi]\n";

   if( $rules )
      echo "RU[$rules]\n";

   if( $Handicap > 0 )
   {
      if( $use_HA )
         echo "HA[$Handicap]\n";
      if( $use_AB_for_handicap )
         echo "PL[W]\nAB";
   }

   $regexp = ( $Status == 'FINISHED' ? "c|comment|h|hidden" : "c|comment" );

   for ($sgf_trim_nr = mysql_num_rows ($result) - 1; $sgf_trim_nr >=0; $sgf_trim_nr--)
   {
      if (!mysql_data_seek ($result, $sgf_trim_nr))
         break;
      if (!$row = mysql_fetch_array($result))
         break;
      if( $row["PosX"] > $sgf_trim_level )
         break;
   }


   mysql_data_seek ($result, 0) ;
   while( $row = mysql_fetch_array($result) )
   {
      if( $sgf_trim_nr >= 0
          && $row["PosX"] >= -1
          && ($row["Stone"] == WHITE or $row["Stone"] == BLACK ) )
      {

         if( $row["MoveNr"] > $Handicap or !$use_AB_for_handicap )
         {
            sgf_echo_comment( $node_com );
            $node_com = "";
            echo( $row["Stone"] == WHITE ? ";W" : ";B" );
         }

         $sgf_trim_nr--;
         if( $row["PosX"] == -1 )  //pass move
         {
            echo "[]"; //do not use [tt]

            if( $sgf_pass_highlight == 1 )
               echo "N[PASS]";

            else if ( $sgf_pass_highlight == 2 )
               $node_com .= "\nPASS";
         }
         else   //if bigger board: + ($row["PosX"]<26)?ord('a'):(ord('A')-26)
            echo "[" . chr($row["PosX"] + ord('a')) .
               chr($row["PosY"] + ord('a')) . "]";

      }

      //keep comments even if in ending pass, SCORE, SCORE2 or resign steps.
      if( $nr_matches = preg_match_all("'<($regexp)>(.*?)</($regexp)>'mis", $row["Text"],
                                       $matches, PREG__SET_ORDER) )
      {
         for($i=0; $i<$nr_matches; $i++)
         {
            $node_com .= "\n" . ( $row["Stone"] == WHITE ? $Whitename : $Blackname ) . ": ";
            $node_com .= trim($matches[2][$i]) ;
         }
      }

   }

/* highlighting result in last comments:
   could show territories, prisonniers, komi ...
   if ( $Status == 'FINISHED') //???
   if( isset($Score) )
   {
      $node_com.= "\nResult: " . score2text($Score, false, true) ;
   }
*/

   sgf_echo_comment( $node_com );

   echo "\n)\n";

}

?>

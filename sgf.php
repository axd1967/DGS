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

function reverse_htmlentities( $str )
{
  $reg= get_html_translation_table(HTML_ENTITIES); //HTML_SPECIALCHARS or HTML_ENTITIES
  $reg= array_flip($reg);
  $reg['&nbsp;'] = ' '; //else maybe '\xa0'
  return strtr($str, $reg);
}

function sgf_simpletext( $str )
{
   return str_replace("]","\]", str_replace("\\","\\\\", 
         ereg_replace("[\x01-\x20]+", " ", reverse_htmlentities( $str )
      ) ) );
}

function sgf_echo_comment( $com )
{
   if ( !$com )
      return false;
   echo "\nC[" . str_replace("]","\]", str_replace("\\","\\\\", 
         reverse_htmlentities( ltrim($com,"\r\n")
      ) ) ) . "]";
   return true;
}

function sgf_echo_point( $marks, $prop="MA" )
{
   if (count($marks) <= 0)
      return false;
   echo $prop;
   foreach($marks as $coord)
   {
      echo "[$coord]";
   }
   return true;
}


{
   disable_cache();

   connect2mysql();


   $use_HA = false;
   $use_AB_for_handicap = true;
   $sgf_trim_level = -1; //-1= skip ending pass, -2= keep them
   $sgf_pass_highlight = 1; //0=no highlight, 1=with Name property, 2=in comments, 3=both

//As board size may be > 'tt' coord, we can't use [tt] for pass moves
// so we use [] and, then, we need at least sgf_version = 4 (FF[4])
   $sgf_version = 4;

/*
   WARNING: Some fields could cause problems because of charset:
   - those coming from user (like $Blackname)
   - those translated (like score2text() or echo_time_limit())
   We could use the CA[] (FF[4]) property if we know what it is.
*/

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

   echo "(\n;FF[$sgf_version]GM[1]"
      . "\nPC[Dragon Go Server: $HOSTBASE]"
      . "\nDT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]"
      . "\nPB[" . sgf_simpletext("$Blackname ($Blackhandle)") . "]"
      . "\nPW[" . sgf_simpletext("$Whitename ($Whitehandle)") . "]";

   if( isset($Blackrating) or isset($Whiterating) )
   {
      echo "\nBR[" . ( isset($Blackrating) ? echo_rating($Blackrating, false) : '?' ) . "]" .
           "\nWR[" . ( isset($Whiterating) ? echo_rating($Whiterating, false) : '?' ) . "]";
   }

   if ($sgf_version >= 4)
   {
      echo "\nOT[" . sgf_simpletext(echo_time_limit($Maintime, $Byotype, $Byotime, $Byoperiods)) . "]";
   }

   if( isset($Score) )
   {
      echo "\nRE[" . sgf_simpletext(score2text($Score, false, true)) . "]";
   }

   echo "\nSZ[$Size]";
   echo "\nKM[$Komi]";

   if( $rules )
      echo "\nRU[$rules]";


   $regexp = ( $Status == 'FINISHED' ? "c|comment|h|hidden" : "c|comment" );

   $sgf_trim_nr = mysql_num_rows ($result) - 1 ;
   if ( $Status == 'FINISHED' )
   {
      while ( $sgf_trim_nr >=0 )
      {
         if (!mysql_data_seek ($result, $sgf_trim_nr))
            break;
         if (!$row = mysql_fetch_array($result))
            break;
         if( $row["PosX"] > $sgf_trim_level 
            && ($row["Stone"] == WHITE or $row["Stone"] == BLACK) )
            break;
         $sgf_trim_nr-- ;
      }
      mysql_data_seek ($result, 0) ;
   }


   if( $Handicap > 0 && $use_HA )
      echo "\nHA[$Handicap]";

   $movenum= 0; $movesync= 0;
   $points=array();
   while( $row = mysql_fetch_array($result) )
   {
      $coord = chr($row["PosX"] + ord('a')) . chr($row["PosY"] + ord('a'));

      if( $row["Stone"] == WHITE or $row["Stone"] == BLACK )
      {

         if( $row["MoveNr"] <= $Handicap && $use_AB_for_handicap )
         {
            $points[$coord]=$coord;
            if( $row["MoveNr"] == $Handicap)
            {
               sgf_echo_point( $points, "\nPL[W]AB");
               unset($points);
            }
         }
         else if ($sgf_trim_nr >= 0)
         {
            sgf_echo_comment( $node_com );
            $node_com = "";

            echo( "\n;" ); //Node start

            if ($row["PosX"] < -1 )
            { //score steps
               sgf_echo_point( $points);
            }
            else
            { //pass, normal move or non AB handicap
               unset($points);

               if( $row["MoveNr"] > $Handicap)
               {
                  $movenum++;
                  if( $row["MoveNr"] != $movenum+$movesync)
                  {
                     //usefull when non AB handicap or resume after SCORE
                     echo "MN[$movenum]";
                     $movesync= $row["MoveNr"]-$movenum;
                  }
               }

               echo( $row["Stone"] == WHITE ? "W" : "B" );
            
               if( $row["PosX"] == -1 )  //pass move
               {
                  echo "[]"; //do not use [tt]

                  if( $sgf_pass_highlight & 1 )
                     echo "N[PASS]";

                  else if ( $sgf_pass_highlight & 2 )
                     $node_com .= "\nPASS";
               }
               else //move or non AB handicap
               {
                  echo "[" . $coord . "]";
               }
            }

         }

         //keep comments even if in ending pass, SCORE, SCORE2 or resign steps.
         if ($sgf_trim_nr == -1)
         {
            sgf_echo_comment( $node_com );
            $node_com = "";
         }
         if( $nr_matches = preg_match_all("'<($regexp)>(.*?)</($regexp)>'mis", $row["Text"],
                                          $matches, PREG__SET_ORDER) )
         {
            for($i=0; $i<$nr_matches; $i++)
            {
               $node_com .= "\n" . ( $row["Stone"] == WHITE ? $Whitename : $Blackname )
                        . ": " . trim($matches[2][$i]) ;
            }
         }

      }
      else if ($row["Stone"] == WHITE_DEAD or $row["Stone"] == BLACK_DEAD)
      { // toggle dead marks
         if (isset($points[$coord]))
            unset($points[$coord]);
         else
            $points[$coord]=$coord;
      }

      $sgf_trim_nr--;
   }

   $i= false;
   if ( $Status == 'FINISHED')
   {
      //from last skipped SCORE/SCORE2 dead stones
      $i= sgf_echo_point( $points, "\n;AE"); //and territories with TB+TW ?

      // highlighting result in last comments:
      // could show counts for territories, prisonniers, komi
      if( isset($Score) )
      {
         $node_com.= "\nResult: " . score2text($Score, false, true) ;
      }
   }
   if ( $node_com )
   {
      if ( !$i )
         echo "\n;" ;
      sgf_echo_comment( $node_com );
   }

   echo "\n)\n";

}

?>

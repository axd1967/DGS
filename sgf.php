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
require_once( "include/board.php" );
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

/* Possible properties are:
 * - AB/AW/AE: add black stone/white stone/empty point
 * - MA/CR/TR/SQ: mark with a cross/circle/triangle/square
 * - TB/TW: mark territory black/white
 */
function sgf_echo_point( $points, $overwrite_prop=false )
{
   if (count($points) <= 0)
      return false;

   if( $overwrite_prop )
   {
      $prop= $overwrite_prop;
      echo "\n" . $prop;
   }
   else
   {
      asort($points);
      $prop= false;
   }

   foreach($points as $coord => $point_prop)
   {
      if( !$overwrite_prop && $prop!=$point_prop )
      {
         $prop= $point_prop;
         echo "\n" . $prop;
      }
      echo "[$coord]";
   }

   return true;
}

//need ./include/board.php
function sgf_create_territories( $size, &$array,
   &$black_territory, &$white_territory,
   &$black_prisoner, &$white_prisoner )
{
   // mark territories

   for( $x=0; $x<$size; $x++)
   {
      for( $y=0; $y<$size; $y++)
      {
         if( !$array[$x][$y] or $array[$x][$y] == NONE )
         {
            mark_territory( $x, $y, $size, $array );
         }
      }
   }

   // split

   for( $x=0; $x<$size; $x++)
   {
      for( $y=0; $y<$size; $y++)
      {
         $coord = chr($x + ord('a')) . chr($y + ord('a'));
         switch( $array[$x][$y] & ~FLAG_NOCLICK)
         {
            case WHITE_DEAD:
               $black_prisoner[$coord]='AE';
            case BLACK_TERRITORY:
               $black_territory[$coord]='TB';
            break;

            case BLACK_DEAD:
               $white_prisoner[$coord]='AE';
            case WHITE_TERRITORY:
               $white_territory[$coord]='TW';
            break;
         }
      }
   }
}


$array=array();

{
   disable_cache();

   connect2mysql();


//As board size may be > 'tt' coord, we can't use [tt] for pass moves
// so we use [] and, then, we need at least sgf_version = 4 (FF[4])
   $sgf_version = 4;

/*
   WARNING: Some fields could cause problems because of charset:
   - those coming from user (like $Blackname)
   - those translated (like score2text() or echo_time_limit())
   We could use the CA[] (FF[4]) property if we know what it is.
*/

   $use_HA = true;
   $use_AB_for_handicap = true;

   //-1= skip ending pass, -2= keep them, -999= keep everything
   $sgf_trim_level = -1;

   //0=no highlight, 1=with Name property, 2=in comments, 3=both
   $sgf_pass_highlight = 1;
   $sgf_score_highlight = 1;


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

   $row = mysql_fetch_array($result);
   extract($row);

   $node_com = "";

   $result = mysql_query( "SELECT Moves.*,Text FROM Moves " .
                          "LEFT JOIN MoveMessages " .
                          "ON Moves.gid=MoveMessages.gid AND Moves.MoveNr=MoveMessages.MoveNr " .
                          "WHERE Moves.gid=$gid order by Moves.ID" );

   header( 'Content-Type: application/x-go-sgf' );
   header( "Content-Disposition: inline; filename=\"$Whitehandle-$Blackhandle-" .
           date('Ymd', $timestamp) . '.sgf"' );
   header( "Content-Description: PHP Generated Data" );


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
   $points= array();
   $next_color= "B";
   while( $row = mysql_fetch_array($result) )
   {
      extract($row);
      $coord = chr($PosX + ord('a')) . chr($PosY + ord('a'));

      switch( $Stone )
      {
         case MARKED_BY_WHITE:
         case MARKED_BY_BLACK:
         { // toggle marks
            //record last skipped SCORE/SCORE2 marked points
            if ($sgf_trim_nr < 0)
               $array[$PosX][$PosY] ^= OFFSET_MARKED;

            if (isset($points[$coord]))
            {
               unset($points[$coord]);
            }
            else if ($sgf_trim_nr < 0)
            {
               if( $array[$PosX][$PosY] == MARKED_DAME )
                  $points[$coord]='CR';
               else
                  $points[$coord]='AE';
            }
            else
            {
               if( $array[$PosX][$PosY] == NONE )
                  $points[$coord]='CR';
               else
                  $points[$coord]='MA';
            }

            break;
         }

         case NONE:
         {

            $array[$PosX][$PosY] = $Stone;
            break;
         }

         case WHITE:
         case BLACK:
         {

            $array[$PosX][$PosY] = $Stone;

            //keep comments even if in ending pass, SCORE, SCORE2 or resign steps.
            if( $nr_matches = preg_match_all("'<($regexp)>(.*?)</($regexp)>'mis", $Text,
                                             $matches, PREG__SET_ORDER) )
            {
               for($i=0; $i<$nr_matches; $i++)
               {
                  $node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname )
                           . ": " . trim($matches[2][$i]) ;
               }
            }

            if( $MoveNr <= $Handicap && $use_AB_for_handicap )
            {
               $points[$coord]='AB';
               if( $MoveNr < $Handicap)
                  break;

               sgf_echo_point( $points);
               unset($points);

               sgf_echo_comment( $node_com );
               $node_com= "";
            }
            else if ($sgf_trim_nr >= 0)
            {
               if ( $Stone == WHITE )
                  $color= "W" ;
               else
                  $color= "B" ;

               if( $next_color != $color )
                  echo "PL[$color]";

               if ( $Stone == WHITE )
                  $next_color= "B" ;
               else
                  $next_color= "W" ;

               echo( "\n;" ); //Node start

               if( $MoveNr > $Handicap && $PosX >= -1 )
               {
                  $movenum++;
                  if( $MoveNr != $movenum+$movesync)
                  {
                     //usefull when "non AB handicap" or "resume after SCORE"
                     echo "MN[$movenum]";
                     $movesync= $MoveNr-$movenum;
                  }
               }

               if ($PosX < -1 )
               { //score steps

                  $next_color= "";

                  if( $sgf_score_highlight & 1 )
                     echo "N[$color SCORE]";

                  if ( $sgf_score_highlight & 2 )
                     $node_com .= "\n$color SCORE";

                  sgf_echo_point( $points);
               }
               else
               { //pass, normal move or non AB handicap

                  if( $PosX == -1 )  //pass move
                  {
                     echo $color."[]"; //do not use [tt]

                     $next_color= "";

                     if( $sgf_pass_highlight & 1 )
                        echo "N[$color PASS]";

                     if ( $sgf_pass_highlight & 2 )
                        $node_com .= "\n$color PASS";
                  }
                  else //move or non AB handicap
                  {
                     echo $color."[$coord]";
                  }

                  unset($points);
               }

               sgf_echo_comment( $node_com );
               $node_com= "";
            }
            break;
         }
      }

      $sgf_trim_nr--;
   }

   if ( $Status == 'FINISHED')
   {
      echo( "\n;" ); //Node start

      //from last skipped SCORE/SCORE2 marked points
      sgf_echo_point( $points);

      $black_territory = array();
      $white_territory = array();
      $black_prisoner = array();
      $white_prisoner = array();
      sgf_create_territories( $Size, $array, 
         $black_territory, $white_territory, 
         $black_prisoner, $white_prisoner);

      sgf_echo_point( $black_territory, 'TB'); //'TB''TR'

      sgf_echo_point( $white_territory, 'TW'); //'TW''SQ'

      // highlighting result in last comments:
      if( isset($Score) )
      {
         if ( abs($Score) < SCORE_RESIGN )
         {
            $node_com.= "\n";
            $scoreW = count($white_territory);
            $node_com.= "\nWhite Territory: $scoreW" ;
            $White_Prisoners+= count($white_prisoner) ;
            $scoreW+= $White_Prisoners;
            $node_com.= "\nWhite Prisoners: $White_Prisoners" ;
            $scoreW+= $Komi;
            $node_com.= "\nKomi: $Komi" ;
            $node_com.= "\n==== $scoreW" ;

            $node_com.= "\n";
            $scoreB = count($black_territory);
            $node_com.= "\nBlack Territory: $scoreB" ;
            $Black_Prisoners+= count($black_prisoner) ;
            $scoreB+= $Black_Prisoners;
            $node_com.= "\nBlack Prisoners: $Black_Prisoners" ;
            $node_com.= "\n==== $scoreB" ;
         }
         $node_com.= "\n";
         $node_com.= "\nResult: " . score2text($Score, false, true) ;

         /*
         $node_com.= "\n";
         $node_com.= "\nWhite_Start_Rating: $White_Start_Rating" ;
         $node_com.= "\nBlack_Start_Rating: $Black_Start_Rating" ;
         $node_com.= "\nWhite_End_Rating: $White_End_Rating" ;
         $node_com.= "\nBlack_End_Rating: $Black_End_Rating" ;
         */
      }
      sgf_echo_comment( $node_com );
      $node_com= "";
   }

   echo "\n)\n";
}

?>

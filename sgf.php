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

$reverse_htmlentities_table= get_html_translation_table(HTML_ENTITIES); //HTML_SPECIALCHARS or HTML_ENTITIES
$reverse_htmlentities_table= array_flip($reverse_htmlentities_table);
$reverse_htmlentities_table['&nbsp;'] = ' '; //else may be '\xa0' as with html_entity_decode()
function reverse_htmlentities( $str )
{
 global $reverse_htmlentities_table;
  return strtr($str, $reverse_htmlentities_table);
}

function sgf_simpletext( $str )
{
   return str_replace("]","\\]", str_replace("\\","\\\\",
         ereg_replace("[\x01-\x20]+", " ", reverse_htmlentities( $str )
      ) ) );
}

function sgf_echo_comment( $com )
{
   if ( !$com )
      return false;
   echo "\nC[" . str_replace("]","\\]", str_replace("\\","\\\\",
         reverse_htmlentities( ltrim($com,"\r\n")
      ) ) ) . "]";
   return true;
}

/* Possible properties are: (uppercase only)
 * - AB/AW/AE: add black stone/white stone/empty point
 * - MA/CR/TR/SQ: mark with a cross/circle/triangle/square (SQ is FF[4])
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
      if( !$overwrite_prop && $prop !== $point_prop )
      {
         $prop= $point_prop;
         if($prop)
            echo "\n" . $prop;
      }
      if($prop)
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


/*
> White: 66 territory + 6 prisoners + 0.5 komi = 72.5
> Black: 64 territory + 1 prisoner = 65
*/
function sgf_count_string( $color, $territory, $prisoner, $komi=false )
{
   $score = 0;
   $str = "$color:";

   $str.= " $territory territor" . ($territory > 1 ? "ies" : "y") ;
   $score+= $territory;

   $str.= " + $prisoner prisoner" . ($prisoner > 1 ? "s" : "") ;
   $score+= $prisoner;

   if( is_numeric($komi) )
   {
      $str.= " + $komi komi" ;
      $score+= $komi;
   }

   $str.= " = $score";
   return $str;
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
   - those translated (like score2text(), echo_rating() or echo_time_limit())
     (but, actually, the translation database is not available here)
   We could use the CA[] (FF[4]) property if we know what it is.
*/

   $rules = "Japanese"; //Mandatory for Go (GM[1])
/*
 "AGA" (rules of the American Go Association)
 "GOE" (the Ing rules of Goe)
 "Japanese" (the Nihon-Kiin rule set)
 "NZ" (New Zealand rules)
*/

   $use_HA = true;
   $use_AB_for_handicap = true;

   //0=no highlight, 1=with Name property, 2=in comments, 3=both
   $sgf_pass_highlight = 1;
   $sgf_score_highlight = 1;

   //Last dead stones property: (uppercase only)
   // ''= keep them, 'AE'= remove, 'MA'/'CR'/'TR'/'SQ'= mark them
   $dead_stone_prop = '';

   //Last marked dame property: (uppercase only)
   // ''= no mark, 'MA'/'CR'/'TR'/'SQ'= mark them
   $marked_dame_prop = '';

   $gid = @$_GET['gid'];
   if( !$gid )
   {
      if( eregi("game([0-9]+)", $REQUEST_URI, $result) )
         $gid = $result[1];
   }
   if( !$gid )
      error("unknown_game");

   $owned_comments = @$_GET['owned_comments'];
   if ( $owned_comments )
      $field_owned = 
         'black.Sessioncode AS Blackscode, ' .
         'white.Sessioncode AS Whitescode, ' .
         'UNIX_TIMESTAMP(black.Sessionexpire) AS Blackexpire, ' .
         'UNIX_TIMESTAMP(white.Sessionexpire) AS Whiteexpire, ' ;
   else
      $field_owned = '';

   $result = mysql_query(
      'SELECT Games.*, ' .
//      'Games.Flags+0 AS flags, ' .
      'UNIX_TIMESTAMP(Games.Starttime) AS startstamp, ' .
      'UNIX_TIMESTAMP(Games.Lastchanged) AS timestamp, ' .
       $field_owned .
      'black.Name AS Blackname, ' .
      'black.Handle AS Blackhandle, ' .
      "IF(Games.Status='FINISHED', Games.Black_End_Rating, black.Rating2 ) AS Blackrating, " .
      'white.Name AS Whitename, ' .
      'white.Handle AS Whitehandle, ' .
      "IF(Games.Status='FINISHED', Games.White_End_Rating, white.Rating2 ) AS Whiterating " .
      'FROM Games, Players AS black, Players AS white ' .
      "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" )
      or die(mysql_error());

   if( mysql_num_rows($result) != 1 )
      error("unknown_game");

   $row = mysql_fetch_array($result);
   extract($row);

   if ( $owned_comments )
   {
      $owned_comments = DAME;
      if ($Blackhandle == @$_COOKIE[COOKIE_PREFIX.'handle'])
      {
         if( $Blackscode == @$_COOKIE[COOKIE_PREFIX.'sessioncode'] && $Blackexpire >= $NOW )
            $owned_comments = BLACK ;
      }
      elseif ($Whitehandle == @$_COOKIE[COOKIE_PREFIX.'handle'])
      {
         if( $Whitescode == @$_COOKIE[COOKIE_PREFIX.'sessioncode'] && $Whiteexpire >= $NOW )
            $owned_comments = WHITE ;
      }
   }
   else
      $owned_comments = DAME;

   $node_com = "";

   $result = mysql_query( "SELECT Moves.*,MoveMessages.Text " .
                          "FROM Moves LEFT JOIN MoveMessages " .
                          "ON MoveMessages.gid=$gid AND MoveMessages.MoveNr=Moves.MoveNr " .
                          "WHERE Moves.gid=$gid ORDER BY Moves.ID" );


   header( 'Content-Type: application/x-go-sgf' );
   $filename= "$Whitehandle-$Blackhandle-$gid-" . date('Ymd', $timestamp) ;
   header( "Content-Disposition: inline; filename=\"$filename.sgf\"" );
   header( "Content-Description: PHP Generated Data" );


   echo "(\n;FF[$sgf_version]GM[1]"
      . "\nPC[Dragon Go Server: $HOSTBASE]"
      . "\nDT[" . date( 'Y-m-d', $startstamp ) . ',' . date( 'Y-m-d', $timestamp ) . "]"
      . "\nGN[" . sgf_simpletext($filename) . "]"
      . "\nGC[Game ID: $gid".($Rated=='N'?'':', rated')."]"   //($HOSTBASE/game.php?gid=$gid)
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
      //may specify CA (charset)
   }

   if( $rules )
      echo "\nRU[$rules]";

   echo "\nSZ[$Size]";
   echo "\nKM[$Komi]";

   if( $Handicap > 0 && $use_HA )
      echo "\nHA[$Handicap]";


   $sgf_trim_nr = mysql_num_rows ($result) - 1 ;
   if ( $Status == 'FINISHED' && isset($Score) )
   {
      echo "\nRE[" . sgf_simpletext(score2text($Score, false, true)) . "]";

      //-1= skip ending pass, -2= keep them, -999= keep everything
      if ( abs($Score) < SCORE_RESIGN )
         $sgf_trim_level = -1;
      else if ( abs($Score) == SCORE_TIME )
         $sgf_trim_level = -3;
      else
         $sgf_trim_level = -2;

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


   $movenum= 0; $movesync= 0;
   $points= array();
   $next_color= "B";
   while( $row = mysql_fetch_assoc($result) )
   {
      $Text="";
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
                  $points[$coord]=$marked_dame_prop;
               else
                  $points[$coord]=$dead_stone_prop;
            }
            else
            {
               if( $array[$PosX][$PosY] == NONE )
                  $points[$coord]='MA';
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
            if( $owned_comments == BLACK or $owned_comments == WHITE )
            {
               if( $Status != 'FINISHED' && $owned_comments != $Stone )
                  $Text = trim(preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $Text));

                  if(  $Text )
                     $node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname )
                           . ": " . $Text ;
            }
            else //SGF query from an observer
            {
               if( $Status != 'FINISHED' )
                  $Text = preg_replace("'<h(idden)? *>(.*?)</h(idden)? *>'is", "", $Text);

               $nr_matches = preg_match_all(
                     "'(<c(omment)? *>(.*?)</c(omment)? *>)".
                     "|(<h(idden)? *>(.*?)</h(idden)? *>)'is"
                     , $Text, $matches );
               for($i=0; $i<$nr_matches; $i++)
               {
                  $Text = trim($matches[3][$i]);
                  if( !$Text )
                     $Text = trim($matches[7][$i]);
                  if(  $Text )
                     $node_com .= "\n" . ( $Stone == WHITE ? $Whitename : $Blackname )
                           . ": " . $Text ;
               }
            }
            $Text="";

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
                     //useful when "non AB handicap" or "resume after SCORE"
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
      echo( "\n;N[RESULT]" ); //Node start

      // highlighting result in last comments:
      if( isset($Score) )
      {
         $node_com.= "\n";

         if ( abs($Score) < SCORE_RESIGN )
         {

            $black_territory = array();
            $white_territory = array();
            $black_prisoner = array();
            $white_prisoner = array();
            sgf_create_territories( $Size, $array,
               $black_territory, $white_territory,
               $black_prisoner, $white_prisoner);

            //Last dead stones mark
            if ($dead_stone_prop)
               sgf_echo_point(
                  array_merge( $black_prisoner, $white_prisoner)
                  , $dead_stone_prop);

            //$points from last skipped SCORE/SCORE2 marked points
            sgf_echo_point(
               array_merge( $points, $black_territory, $white_territory)
               );

            $node_com.= "\n" .
               sgf_count_string( "White"
                  ,count($white_territory)
                  ,$White_Prisoners + count($white_prisoner)
                  ,$Komi
               );

            $node_com.= "\n" .
               sgf_count_string( "Black"
                  ,count($black_territory)
                  ,$Black_Prisoners + count($black_prisoner)
               );

            //$node_com.= "\n";
         }
         $node_com.= "\nResult: " . score2text($Score, false, true) ;

         /*
         $node_com.= "\n";
         $node_com.= "\nWhite_Start_Rating: $White_Start_Rating" ;
         $node_com.= "\nBlack_Start_Rating: $Black_Start_Rating" ;
         $node_com.= "\nWhite_End_Rating: $White_End_Rating" ;
         $node_com.= "\nBlack_End_Rating: $Black_End_Rating" ;
         */
      }
   }

   if( $owned_comments == BLACK )
     $notes = trim($Black_Notes);
   elseif( $owned_comments == WHITE )
     $notes = trim($White_Notes);
   else
     $notes = '';

   if( !empty($notes) )
     $node_com.= "\n\nNotes - " . ( $owned_comments == WHITE ? $Whitename : $Blackname )
                           . ":\n" . $notes ;

   sgf_echo_comment( $node_com );
   $node_com= "";

   echo "\n)\n";
}

?>

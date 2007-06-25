<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

//$TranslateGroups[] = "Game";

function make_invite_game(&$player_row, &$opponent_row, $disputegid)
{
   global $NOW;

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_REQUEST['size']));
   $handicap_type = @$_REQUEST['handicap_type'];
   $color = @$_REQUEST['color'];
   $handicap_m = (int)@$_REQUEST['handicap_m'];
   $handicap_d = (int)@$_REQUEST['handicap_d'];
   $komi_m = (float)@$_REQUEST['komi_m'];
   $komi_n = (float)@$_REQUEST['komi_n'];
   $komi_d = (float)@$_REQUEST['komi_d'];
   $rated = @$_REQUEST['rated'];
   $stdhandicap = @$_REQUEST['stdhandicap'];
   $weekendclock = @$_REQUEST['weekendclock'];

   $byoyomitype = @$_REQUEST['byoyomitype'];
   $timevalue = (int)@$_REQUEST['timevalue'];
   $timeunit = @$_REQUEST['timeunit'];

   $byotimevalue_jap = (int)@$_REQUEST['byotimevalue_jap'];
   $timeunit_jap = @$_REQUEST['timeunit_jap'];
   $byoperiods_jap = (int)@$_REQUEST['byoperiods_jap'];

   $byotimevalue_can = (int)@$_REQUEST['byotimevalue_can'];
   $timeunit_can = @$_REQUEST['timeunit_can'];
   $byoperiods_can = (int)@$_REQUEST['byoperiods_can'];

   $byotimevalue_fis = (int)@$_REQUEST['byotimevalue_fis'];
   $timeunit_fis = @$_REQUEST['timeunit_fis'];

   if( $color == "White" )
   {
      $Black_ID = $opponent_row['ID'];
      $White_ID = $player_row['ID'];
   }
   else
   {
      $White_ID = $opponent_row['ID'];
      $Black_ID = $player_row['ID'];
   }

   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row["Rating2"];
   $opprated = ( $opponent_row['RatingStatus'] && is_numeric($opprating) && $opprating >= MIN_RATING );


   //ToMove_ID=$tomove will hold handitype until ACCEPTED
   switch( $handicap_type )
   {
      case 'conv':
      {
         if( !$iamrated or !$opprated )
            error('no_initial_rating','make_invite_game.conv');
         $tomove = INVITE_HANDI_CONV;
         $handicap = 0; //further computing
         $komi = 0;
      }
      break;

      case 'proper':
      {
         if( !$iamrated or !$opprated )
            error('no_initial_rating','make_invite_game.proper');
         $tomove = INVITE_HANDI_PROPER;
         $handicap = 0; //further computing
         $komi = 0;
      }
      break;

      case 'double':
      {
         $tomove = INVITE_HANDI_DOUBLE;
         $handicap = $handicap_d;
         $komi = $komi_d;
      }
      break;

      case 'manual':
      {
         $tomove = $Black_ID; //no real meaning now, any positive value
         $handicap = $handicap_m;
         $komi = $komi_m;
      }
      break;

      default: //always available even if waiting room or unrated
         $handicap_type = 'nigiri'; 
      case 'nigiri':
      {
         $tomove = INVITE_HANDI_NIGIRI;
         $handicap = 0;
         $komi = $komi_n;
      }
      break;
   }

   if( !($komi <= MAX_KOMI_RANGE and $komi >= -MAX_KOMI_RANGE) )
      error('komi_range','make_invite_game');

   if( !($handicap <= MAX_HANDICAP and $handicap >= 0) )
      error('handicap_range','make_invite_game');

   list($hours, $byohours, $byoperiods) =
      interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                 $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                 $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                 $byotimevalue_fis, $timeunit_fis);

   if( $hours<1 and ($byohours<1 or $byoyomitype == 'FIS') )
      error('time_limit_too_small','make_invite_game');


   if( $rated != 'Y' or $Black_ID == $White_ID )
      $rated = 'N';

   if( $stdhandicap != 'Y' or
       !standard_handicap_is_possible($size, $handicap) )
      $stdhandicap = 'N';

   if( $weekendclock != 'Y' )
      $weekendclock = 'N';

   $query = "Black_ID=$Black_ID, " .
      "White_ID=$White_ID, " .
      "ToMove_ID=$tomove, " .
      "Lastchanged=FROM_UNIXTIME($NOW), " .
      "Size=$size, " .
      "Handicap=$handicap, " .
      "Komi=ROUND(2*($komi))/2, " .
      "Maintime=$hours, " .
      "Byotype='$byoyomitype', " .
      "Byotime=$byohours, " .
      "Byoperiods=$byoperiods, " .
      "Black_Maintime=$hours, " .
      "White_Maintime=$hours, " .
      "WeekendClock='$weekendclock', " .
      "StdHandicap='$stdhandicap', " .
      "Rated='$rated'";

   if( $disputegid > 0 )
   {
      // Check if dispute game exists
      $row= mysql_single_fetch( 'make_game.make_invite_game.dispute',
                     "SELECT ID, Black_ID, White_ID FROM Games"
                    ." WHERE ID=$disputegid AND Status='INVITED'" );
      if( !$row )
         error('unknown_game','make_invite_game.1');
      if( ( $row['Black_ID']!=$player_row['ID'] or $row['White_ID']!=$opponent_row['ID'] )
       && ( $row['White_ID']!=$player_row['ID'] or $row['Black_ID']!=$opponent_row['ID'] )
        )
         error('unknown_game','make_invite_game.2');

      $query = "UPDATE Games SET $query WHERE ID=$disputegid LIMIT 1";
   }
   else
      $query = "INSERT INTO Games SET $query";

   $result = mysql_query( $query )
      or error('mysql_insert_game','make_invite_game.update_game');

   if( mysql_affected_rows() != 1)
      error('mysql_start_game','make_invite_game.update_game');

   if( $disputegid > 0 )
      $gid = $disputegid;
   else
      $gid = mysql_insert_id();

   if( $gid <= 0 )
      error('internal_error','make_invite_game.gameID='.$gid);

   return $gid;
} //make_invite_game



function create_game(&$black_row, &$white_row, &$game_info_row, $gid=null)
{
   global $NOW;

//   var_dump($game_info_row);
//   die();
   if($gid > 0 and !( $game_info_row["Black_ID"] == $black_row['ID'] and
                      $game_info_row["White_ID"] == $white_row['ID'] or
                      $game_info_row["White_ID"] == $black_row['ID'] and
                      $game_info_row["Black_ID"] == $white_row['ID'] ))
      error('mysql_start_game','create_game.not_correct_players');

   $rating_black = $black_row["Rating2"];
   $rating_white = $white_row["Rating2"];
   $black_rated = ( $black_row['RatingStatus'] && is_numeric($rating_black) && $rating_black >= MIN_RATING );
   $white_rated = ( $white_row['RatingStatus'] && is_numeric($rating_white) && $rating_white >= MIN_RATING );

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)$game_info_row["Size"]));

   $clock_used_black = ( $black_row['OnVacation'] > 0 ? VACATION_CLOCK
                         : $black_row["ClockUsed"]);
   $clock_used_white = ( $white_row['OnVacation'] > 0 ? VACATION_CLOCK
                         : $white_row["ClockUsed"]);

   if( $game_info_row['WeekendClock'] != 'Y' )
   {
      $clock_used_black += WEEKEND_CLOCK_OFFSET;
      $clock_used_white += WEEKEND_CLOCK_OFFSET;
   }

   $rated = ( $game_info_row['Rated'] === 'Y' && $black_rated && $white_rated );
   $game_info_row['Rated'] = ( $rated ? 'Y' : 'N' );

   $stdhandicap = $game_info_row['StdHandicap'];
   if( $stdhandicap != 'Y' or
       !standard_handicap_is_possible($size, $game_info_row['Handicap'] ) )
      $stdhandicap = 'N';

   if( ENA_STDHANDICAP&2 && $stdhandicap == 'Y' && $game_info_row['Handicap'] > 0 )
      $skip_handicap_validation = true;
   else 
      $skip_handicap_validation = false; 


   if( $skip_handicap_validation )
   {
      $moves = $game_info_row['Handicap'];
      $tomove = $white_row['ID'];
      $clock_used = $clock_used_white;
   }
   else
   {
      $moves = 0;
      $tomove = $black_row['ID'];
      $clock_used = $clock_used_black;
   }
   $last_ticks = get_clock_ticks($clock_used);

   $set_query =
      "Black_ID=" . $black_row["ID"] . ", " .
      "White_ID=" . $white_row["ID"] . ", " .
      "ToMove_ID=$tomove, " .
      "Status='PLAY', " .
      "Moves=$moves, " .
      "ClockUsed=$clock_used, " .
      "LastTicks=$last_ticks, " .
      "Lastchanged=FROM_UNIXTIME($NOW), " .
      "Starttime=FROM_UNIXTIME($NOW), " .
      "Size=$size, " .
      "Handicap=" . $game_info_row["Handicap"] . ", " .
      "Komi=" . $game_info_row["Komi"] . ", " .
      "Maintime=" . $game_info_row["Maintime"] . ", " .
      "Byotype='" . $game_info_row["Byotype"] . "', " .
      "Byotime=" . $game_info_row["Byotime"] . ", " .
      "Byoperiods=" . $game_info_row["Byoperiods"] . ", " .
      "Black_Maintime=" . $game_info_row["Maintime"] . ", " .
      "White_Maintime=" . $game_info_row["Maintime"] . ", " .
      (is_numeric($rating_black) ? "Black_Start_Rating=$rating_black, " : '' ) .
      (is_numeric($rating_white) ? "White_Start_Rating=$rating_white, " : '' ) .
      "WeekendClock='" . $game_info_row["WeekendClock"] . "', " .
      "StdHandicap='$stdhandicap', " .
      "Rated='" . $game_info_row["Rated"] . "'";

   if( $gid > 0 )
   {
      mysql_query("UPDATE Games SET $set_query WHERE ID=$gid LIMIT 1")
         or error('mysql_query_failed','create_game.update:'.$gid);

      if( mysql_affected_rows() != 1)
         error('mysql_start_game','create_game.update:'.$gid);
   }
   else
   {
      mysql_query("INSERT INTO Games SET $set_query")
         or error('mysql_query_failed','create_game.insert');
      $gid = mysql_insert_id();
   }
   
   if( $gid <= 0 )
      error('internal_error','create_game.gameID='.$gid);

   if( $skip_handicap_validation )
      if( !make_standard_placement_of_handicap_stones($size
                                 , $game_info_row['Handicap'], $gid) )
            error('internal_error','create_game.std_handicap.fail');

   return $gid;
} //create_game

function standard_handicap_is_possible($size, $hcp)
{
   if( ENA_STDHANDICAP&4 ) //allow everything
      return true;
   return( $size == 19 or $hcp <= 4 or ($hcp <= 9 and $size%2 == 1 and $size>=9) );
}

if( ENA_STDHANDICAP&2 ) { //skip black validation
function make_standard_placement_of_handicap_stones($size, $hcp, $gid)
{
   if( $hcp < 2 )
      return false;

   if( !standard_handicap_is_possible($size, $hcp) )
      return false;

   $err = '';

   require_once('include/sgf_parser.php');
   require_once('include/coords.php');
   $stonestring = get_handicap_pattern( $size, $hcp, $err);

   if( $err )
      return false;

   $l = strlen( $stonestring );
   if( $l != 2*$hcp )
      error('internal_error','bad stonestring std_handicap');

   $query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

   for( $i=0; $i < $l; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $size);

      if( !isset($rownr) or !isset($colnr) )
         error("illegal_position",'std_handicap');

      $query .= "($gid, " . ($i/2 + 1) . ", " . BLACK . ", $colnr, $rownr, 0)";
      if( $i+2 < $l ) $query .= ", ";
   }

   mysql_query( $query )
      or error('mysql_query_failed','make_game.make_standard_placement_of_handicap_stones');

   return true;
}
} //ENA_STDHANDICAP&2

?>
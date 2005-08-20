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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/rating.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   //not used: init_standard_folders();

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $handicap_type = @$_POST['handicap_type'];
   if( $handicap_type == 'nigiri' )
      $komi = @$_POST['komi_n'];
   elseif( $handicap_type == 'double' )
      $komi = @$_POST['komi_d'];
   else
      $komi = 0;

   if( ( $handicap_type == 'conv' or $handicap_type == 'proper' ) and
       !$player_row["RatingStatus"] )
   {
      error( "no_initial_rating" );
   }

   if( !($komi <= MAX_KOMI_RANGE and $komi >= -MAX_KOMI_RANGE) )
      error("komi_range");

   $nrGames = max( 1, (int)@$_POST['nrGames']);

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_POST['size']));

      //for interpret_time_limit_forms{
      $byoyomitype = @$_POST['byoyomitype'];
      $timevalue = @$_POST['timevalue'];
      $timeunit = @$_POST['timeunit'];

      $byotimevalue_jap = @$_POST['byotimevalue_jap'];
      $timeunit_jap = @$_POST['timeunit_jap'];
      $byoperiods_jap = @$_POST['byoperiods_jap'];

      $byotimevalue_can = @$_POST['byotimevalue_can'];
      $timeunit_can = @$_POST['timeunit_can'];
      $byoperiods_can = @$_POST['byoperiods_can'];

      $byotimevalue_fis = @$_POST['byotimevalue_fis'];
      $timeunit_fis = @$_POST['timeunit_fis'];
      //for interpret_time_limit_forms}

   interpret_time_limit_forms(); //Set global $hours,$byohours,$byoperiods

   if( ($rated=@$_POST['rated']) != 'Y' or !$player_row["RatingStatus"] )
      $rated = 'N';

   if( ($weekendclock=@$_POST['weekendclock']) != 'Y' )
      $weekendclock = 'N';

   if( ($must_be_rated=@$_POST['must_be_rated']) != 'Y' )
   {
      $must_be_rated = 'N';
      //to keep a good column sorting:
      $rating1 = $rating2 = read_rating('99 kyu', 'dragonrating');
   }
   else
   {
      $rating1 = read_rating(@$_POST['rating1'], 'dragonrating');
      $rating2 = read_rating(@$_POST['rating2'], 'dragonrating');

      if( $rating2 < $rating1 )
      {
         $tmp = $rating1; $rating1 = $rating2; $rating2 = $tmp;
      }

      $rating2 += 50;
      $rating1 -= 50;
   }

   $query = "INSERT INTO Waitingroom SET " .
      "uid=" . $player_row['ID'] . ', ' .
      "nrGames=$nrGames, " .
      "Time=FROM_UNIXTIME($NOW), " .
      "Size=$size, " .
      "Komi=ROUND(2*($komi))/2, " .
      "Maintime=$hours, " .
      "Byotype='$byoyomitype', " .
      "Byotime=$byohours, " .
      "Byoperiods=$byoperiods, " .
      "Handicaptype='$handicap_type', " .
      "WeekendClock='$weekendclock', " .
      "Rated='$rated', " .
      "MustBeRated='$must_be_rated', " .
      "Ratingmin=$rating1, " .
      "Ratingmax=$rating2, " .
      "Comment=\"" . addslashes(trim(get_request_arg('comment'))) . "\"";

   mysql_query( $query )
      or error("mysql_query_failed");

   $msg = urlencode(T_('Game added!'));

   jump_to("waiting_room.php?msg=$msg");
}
?>
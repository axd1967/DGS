<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/rating.php" );
//Useless: $ThePage = new Page('...');

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $handicap_type = @$_POST['handicap_type'];
   switch( (string)$handicap_type )
   {
      case 'conv':
      {
         if( !$iamrated )
            error('no_initial_rating');
         $handicap = 0; //further computing
         $komi = 0.0;
      }
      break;

      case 'proper':
      {
         if( !$iamrated )
            error('no_initial_rating');
         $handicap = 0; //further computing
         $komi = 0.0;
      }
      break;

      case 'double':
      {
         $handicap = (int)@$_POST['handicap_d'];
         $komi = (float)@$_POST['komi_d'];
      }
      break;

      case 'manual': //not allowed in waiting room
      /*
      {
         $handicap = (int)@$_POST['handicap_m'];
         $komi = (float)@$_POST['komi_m'];
      }
      break;
      */
      default: //always available even if waiting room or unrated
         $handicap_type = 'nigiri';
      case 'nigiri':
      {
         $handicap = 0;
         $komi = (float)@$_POST['komi_n'];
      }
      break;
   }

   if( !($komi <= MAX_KOMI_RANGE && $komi >= -MAX_KOMI_RANGE) )
      error('komi_range');

   if( !($handicap <= MAX_HANDICAP && $handicap >= 0) )
      error('handicap_range');

   // handicap adjustment
   $adj_handicap = (int)@$_POST['adj_handicap'];
   if( abs($adj_handicap) > MAX_HANDICAP )
      $adj_handicap = ($adj_handicap<0 ? -1 : 1) * MAX_HANDICAP;

   $min_handicap = min( MAX_HANDICAP, max( 0, (int)@$_POST['min_handicap'] ));

   $max_handicap = (int)@$_POST['max_handicap'];
   if( $max_handicap > MAX_HANDICAP )
      $max_handicap = -1; // don't save potentially changeable "default"

   if( $max_handicap >= 0 && $min_handicap > $max_handicap )
      swap( $min_handicap, $max_handicap );


   $nrGames = max( 1, (int)@$_POST['nrGames']);

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_POST['size']));

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

   list($hours, $byohours, $byoperiods) =
      interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                 $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                 $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                 $byotimevalue_fis, $timeunit_fis);

   if( $hours<1 && ($byohours<1 || $byoyomitype == 'FIS') )
      error('time_limit_too_small');


   if( ($rated=@$_POST['rated']) != 'Y' || !$player_row['RatingStatus'] )
      $rated = 'N';

   if( ENA_STDHANDICAP )
   {
      if( ($stdhandicap=@$_POST['stdhandicap']) != 'Y' )
         $stdhandicap = 'N';
   } else $stdhandicap = 'N';

   if( ($weekendclock=@$_POST['weekendclock']) != 'Y' )
      $weekendclock = 'N';

   if( @$_POST['must_be_rated'] != 'Y' )
   {
      $MustBeRated = 'N';
      //to keep a good column sorting:
      $rating1 = $rating2 = OUT_OF_RATING;
   }
   else
   {
      $MustBeRated = 'Y';
      $rating1 = read_rating(@$_POST['rating1']);
      $rating2 = read_rating(@$_POST['rating2']);

      if( $rating1 == -OUT_OF_RATING || $rating2 == -OUT_OF_RATING )
         error('rank_not_rating');

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
      "Handicap=$handicap, " .
      "Handicaptype='$handicap_type', " .
      "AdjHandicap=$adj_handicap, " .
      "MinHandicap=$min_handicap, " .
      ($max_handicap < 0 ? '' : "MaxHandicap=$max_handicap, " ) .
      "Maintime=$hours, " .
      "Byotype='$byoyomitype', " .
      "Byotime=$byohours, " .
      "Byoperiods=$byoperiods, " .
      "WeekendClock='$weekendclock', " .
      "Rated='$rated', " .
      "StdHandicap='$stdhandicap', " .
      "MustBeRated='$MustBeRated', " .
      "Ratingmin=$rating1, " .
      "Ratingmax=$rating2, " .
      "Comment=\"" . mysql_addslashes(trim(get_request_arg('comment'))) . "\"";

   db_query( 'add_to_waitingroom.insert', $query );
   db_close();

   $msg = urlencode(T_('Game added!'));

   jump_to("waiting_room.php?showall=1".URI_AMP."sysmsg=$msg");
}
?>

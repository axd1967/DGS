<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'include/time_functions.php' );
require_once( "include/message_functions.php" );
require_once( "include/rating.php" );
require_once( 'include/utilities.php' );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
         && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $cat_handicap_type = @$_POST['cat_htype'];
   switch( (string)$cat_handicap_type )
   {
      case CAT_HTYPE_CONV:
         if( !$iamrated )
            error('no_initial_rating');
         $handicap_type = HTYPE_CONV;
         $handicap = 0; //further computing
         $komi = 0.0;
         break;

      case CAT_HTYPE_PROPER:
         if( !$iamrated )
            error('no_initial_rating');
         $handicap_type = HTYPE_PROPER;
         $handicap = 0; //further computing
         $komi = 0.0;
         break;

      case CAT_HTYPE_MANUAL:
         $handicap_type = (string)@$_POST['color_m'];
         if( empty($handicap_type) )
            $handicap_type = HTYPE_NIGIRI;

         $handicap = (int)@$_POST['handicap_m'];
         $komi = (float)@$_POST['komi_m'];
         break;

      default:
         $cat_handicap_type = CAT_HTYPE_MANUAL;
         $handicap_type = HTYPE_NIGIRI;
         $handicap = (int)@$_POST['handicap_m'];
         $komi = (float)@$_POST['komi_m'];
         break;
   }

   if( !($komi <= MAX_KOMI_RANGE && $komi >= -MAX_KOMI_RANGE) )
      error('komi_range', "add_to_waitingroom.check.komi($komi)");

   if( !($handicap <= MAX_HANDICAP && $handicap >= 0) )
      error('handicap_range', "add_to_waitingroom.check.handicap($handicap)");

   // ruleset
   $ruleset = @$_POST['ruleset'];

   // komi adjustment
   $adj_komi = (float)@$_POST['adj_komi'];
   if( abs($adj_komi) > MAX_KOMI_RANGE )
      $adj_komi = ($adj_komi<0 ? -1 : 1) * MAX_KOMI_RANGE;
   if( floor(2 * $adj_komi) != 2 * $adj_komi ) // round to x.0|x.5
      $adj_komi = ($adj_komi<0 ? -1 : 1) * round(2 * abs($adj_komi)) / 2.0;

   $jigo_mode = (string)@$_POST['jigo_mode'];
   if( $jigo_mode != JIGOMODE_KEEP_KOMI && $jigo_mode != JIGOMODE_ALLOW_JIGO
         && $jigo_mode != JIGOMODE_NO_JIGO )
      error('invalid_args', "add_to_waitingroom.check.jigo_mode($jigo_mode)");

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
   if( $nrGames < 1 )
      $nrGames = 1;
   if( $nrGames > NEWGAME_MAX_GAMES )
      error('invalid_args', "add_to_waitingroom.check.nr_games($nrGames)");

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

   if( $hours<1 && ($byohours<1 || $byoyomitype == BYOTYPE_FISCHER) )
      error('time_limit_too_small');


   if( ($rated=@$_POST['rated']) != 'Y' || $player_row['RatingStatus'] == RATING_NONE )
      $rated = 'N';

   if( ENA_STDHANDICAP )
   {
      if( ($stdhandicap=@$_POST['stdhandicap']) != 'Y' )
         $stdhandicap = 'N';
   }
   else
      $stdhandicap = 'N';

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
         swap( $rating1, $rating2 );

      $rating2 += 50;
      $rating1 -= 50;
   }

   $min_rated_games = limit( (int)@$_POST['min_rated_games'], 0, 10000, 0 );

   $same_opponent = (int)@$_POST['same_opp'];


   $query = "INSERT INTO Waitingroom SET " .
      "uid=" . $player_row['ID'] . ', ' .
      "nrGames=$nrGames, " .
      "Time=FROM_UNIXTIME($NOW), " .
      "Ruleset='" . mysql_addslashes($ruleset) . "', " .
      "Size=$size, " .
      "Komi=ROUND(2*($komi))/2, " .
      "Handicap=$handicap, " .
      "Handicaptype='" . mysql_addslashes($handicap_type) . "', " .
      "AdjKomi=$adj_komi, " .
      "JigoMode='" . mysql_addslashes($jigo_mode) . "', " .
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
      "MinRatedGames=$min_rated_games, " .
      "SameOpponent=$same_opponent, " .
      "Comment=\"" . mysql_addslashes(trim(get_request_arg('comment'))) . "\"";

   db_query( 'add_to_waitingroom.insert', $query );
   db_close();

   $msg = urlencode(T_('Game added!'));

   jump_to("waiting_room.php?showall=1".URI_AMP."sysmsg=$msg");
}
?>

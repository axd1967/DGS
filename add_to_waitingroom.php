<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/game_functions.php" );
require_once( "include/rating.php" );
require_once( 'include/utilities.php' );
require_once( 'include/classlib_game.php' );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = (int)$player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
         && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $viewmode = (int) @$_POST['viewmode'];

   $shape_id = (int)@$_REQUEST['shape'];
   $shape_snapshot = @$_REQUEST['snapshot'];

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
   if( $jigo_mode == '' )
      $jigo_mode = JIGOMODE_KEEP_KOMI;
   elseif( !preg_match("/^(".CHECK_JIGOMODE.")$/", $jigo_mode) )
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

   // multi-player
   $game_players = (string)@$_POST['game_players'];
   $game_type = MultiPlayerGame::determine_game_type($game_players);
   if( is_null($game_type) )
      error('invalid_args', "add_to_waitingroom.check.game_players($game_players)");
   $is_std_go = ( $game_type == GAMETYPE_GO );
   if( $is_std_go && $viewmode == GSETVIEW_MPGAME )
      error('invalid_args', "add_to_waitingroom.check.game_players.viewmode($viewmode,$game_players)");


   $maxGamesCheck = new MaxGamesCheck();
   $max_games = $maxGamesCheck->get_allowed_games(NEWGAME_MAX_GAMES);
   $nrGames = max( 1, (int)@$_POST['nrGames']);
   if( $nrGames > NEWGAME_MAX_GAMES )
      error('invalid_args', "add_to_waitingroom.check.nr_games($nrGames)");
   elseif( $nrGames > $max_games )
      error('max_games', "add_to_waitingroom.check.max_games.nr_games($nrGames,$max_games)");

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


   if( ($rated = @$_POST['rated']) != 'Y' || $player_row['RatingStatus'] == RATING_NONE )
      $rated = 'N';

   if( ENABLE_STDHANDICAP )
   {
      if( ($stdhandicap=@$_POST['stdhandicap']) != 'Y' )
         $stdhandicap = 'N';
   }
   else
      $stdhandicap = 'N';

   if( ($weekendclock = @$_POST['weekendclock']) != 'Y' )
      $weekendclock = 'N';

   if( $is_std_go )
      list( $MustBeRated, $rating1, $rating2 ) = parse_waiting_room_rating_range();

   $min_rated_games = limit( (int)@$_POST['min_rated_games'], 0, 10000, 0 );

   $same_opponent = (int)@$_POST['same_opp'];


   // insert game (standard-game or multi-player-game)

   if( !$is_std_go ) // use defaults for MP-game
   {
      //$nrGames = 1;
      $handicap_type = HTYPE_NIGIRI;
      $handicap = 0;
      $komi = 6.5;
      $rated = 'N';
      //$min_rated_games = 0;
      //$same_opponent = -1; // same-opp only ONCE for Team-/Zen-Go
   }

   // handle shape-game implicit settings (error if invalid)
   // NOTE: same handling as for make_invite_game()-func in 'include/make_game.php'
   if( $shape_id > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
      if( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "make_invite_game.check.shape($shape_id,$shape_snapshot)");

      // implicit defaults for shape-game
      $size = (int)$arr_shape['Size'];
      $stdhandicap = 'N';
      $rated = 'N';
   }
   else
   {
      $shape_id = 0;
      $shape_snapshot = '';
   }

   // add waiting-room game
   $query_mpgame = $query_wroom = '';
   if( !$is_std_go ) // mp-game
   {
      $query_mpgame = "INSERT INTO Games SET " .
         "Black_ID=$my_id, " . // game-master
         "White_ID=0, " .
         "ToMove_ID=$my_id, " . // appear as status-game
         "Starttime=FROM_UNIXTIME($NOW), " .
         "Lastchanged=FROM_UNIXTIME($NOW), " .
         "ShapeID=$shape_id, " .
         "ShapeSnapshot='" . mysql_addslashes($shape_snapshot) . "', " .
         "GameType='" . mysql_addslashes($game_type) . "', " .
         "GamePlayers='" . mysql_addslashes($game_players) . "', " .
         "Ruleset='" . mysql_addslashes($ruleset) . "', " .
         "Size=$size, " .
         "Handicap=$handicap, " .
         "Komi=ROUND(2*($komi))/2, " .
         "Status='".GAME_STATUS_SETUP."', " .
         "Maintime=$hours, " .
         "Byotype='$byoyomitype', " .
         "Byotime=$byohours, " .
         "Byoperiods=$byoperiods, " .
         "Black_Maintime=$hours, " .
         "White_Maintime=$hours, " .
         "WeekendClock='$weekendclock', " .
         "StdHandicap='$stdhandicap', " .
         "Rated='$rated'";
   }
   else // std-game
   {
      $query_wroom = "INSERT INTO Waitingroom SET " .
         "uid=$my_id, " .
         "nrGames=$nrGames, " .
         "Time=FROM_UNIXTIME($NOW), " .
         "GameType='" . mysql_addslashes($game_type) . "', " .
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
         "ShapeID=$shape_id, " .
         "ShapeSnapshot='" . mysql_addslashes($shape_snapshot) . "', " .
         "Comment=\"" . mysql_addslashes(trim(get_request_arg('comment'))) . "\"";
   }

   ta_begin();
   {//HOT-section for creating waiting-room game
      $gid = 0;
      if( $query_wroom )
         db_query( 'add_to_waitingroom.insert.waitingroom', $query_wroom );
      else if( $query_mpgame )
      {
         $result = db_query( 'add_to_waitingroom.insert.game', $query_mpgame, 'mysql_insert_game' );
         if( mysql_affected_rows() != 1)
            error('mysql_start_game', 'add_to_waitingroom.insert.game2');
         $gid = mysql_insert_id();
         if( $gid <= 0 )
            error('internal_error', "add_to_waitingroom.insert.game.err($gid)");

         MultiPlayerGame::init_multi_player_game( "add_to_waitingroom",
            $gid, $my_id, MultiPlayerGame::determine_player_count($game_players) );
      }
   }
   ta_end();

   $msg = urlencode(T_('Game added!'));

   if( $gid > 0 )
      jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=$msg");
   else
      jump_to("waiting_room.php?showall=1".URI_AMP."sysmsg=$msg");
}
?>

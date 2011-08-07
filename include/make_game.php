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

//$TranslateGroups[] = "Game";

require_once( 'include/game_functions.php' );
require_once( 'include/time_functions.php' );
require_once( 'include/classlib_game.php' );
require_once( 'include/rating.php' );
require_once( 'include/board.php' );


// Inserts INVITATION-game or updates DISPUTE-game
// always return a valid game ID from the database, else call error()
function make_invite_game(&$player_row, &$opponent_row, $disputegid)
{
   global $NOW;

   $my_id = $player_row['ID'];
   $opp_id = $opponent_row['ID'];

   $shape_id = (int)@$_REQUEST['shape'];
   $shape_snapshot = @$_REQUEST['snapshot'];

   $ruleset = @$_REQUEST['ruleset'];
   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_REQUEST['size']));

   $cat_handicap_type = @$_REQUEST['cat_htype'];
   $color_m = @$_REQUEST['color_m'];
   $handicap_type = ( $cat_handicap_type == CAT_HTYPE_MANUAL ) ? $color_m : $cat_handicap_type;
   if( $handicap_type == HTYPE_AUCTION_KOMI )
      $color_m = HTYPE_NIGIRI;

   $handicap_m = (int)@$_REQUEST['handicap_m'];
   $komi_m = (float)@$_REQUEST['komi_m'];
   $komi_auko = trim(@$_REQUEST['komi_auko']);
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

   $my_rating = $player_row["Rating2"];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row["Rating2"];
   $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );

   // Check if dispute game exists, handle Black/White-ID for auction-komi
   $black_prisoners = $white_prisoners = null; // used to store 2*komi-bid | INIT_KOMI_BID for auction komi
   if( $disputegid > 0 ) // dispute
   {
      $grow = mysql_single_fetch( "make_game.make_invite_game.dispute($disputegid)",
            "SELECT ID, Black_ID, White_ID, Black_Prisoners, White_Prisoners, ShapeID, ShapeSnapshot " .
            "FROM Games WHERE ID=$disputegid AND Status='INVITED'" );
      if( !$grow )
         error('unknown_game', "make_invite_game.dispute1($disputegid)");
      if( ($grow['Black_ID'] != $my_id || $grow['White_ID'] != $opp_id)
            && ($grow['Black_ID'] != $opp_id || $grow['White_ID'] != $my_id) )
         error('unknown_game', "make_invite_game.dispute2($disputegid)");

      $Black_ID = $grow['Black_ID'];
      $White_ID = $grow['White_ID'];
      $black_prisoners = $grow['Black_Prisoners'];
      $white_prisoners = $grow['White_Prisoners'];
   }
   else // new invitation
   {
      $grow = null;
      if( $color_m == HTYPE_WHITE )
      {
         $Black_ID = $opponent_row['ID'];
         $White_ID = $player_row['ID'];
      }
      else // HTYPE_NIGIRI/DOUBLE/BLACK
      {
         $Black_ID = $player_row['ID'];
         $White_ID = $opponent_row['ID'];
      }
      if( $handicap_type == HTYPE_AUCTION_KOMI )
         $black_prisoners = $white_prisoners = INIT_KOMI_BID;
   }


   //ToMove_ID=$tomove will hold handitype until game-setting accepted
   switch( (string)$handicap_type )
   {
      case HTYPE_CONV:
         if( !$iamrated || !$opprated )
            error('no_initial_rating','make_invite_game.conv');
         $tomove = INVITE_HANDI_CONV;
         $handicap = 0; //further computing
         $komi = 0;
         break;

      case HTYPE_PROPER:
         if( !$iamrated || !$opprated )
            error('no_initial_rating','make_invite_game.proper');
         $tomove = INVITE_HANDI_PROPER;
         $handicap = 0; //further computing
         $komi = 0;
         break;

      case HTYPE_DOUBLE:
         $tomove = INVITE_HANDI_DOUBLE;
         $handicap = $handicap_m;
         $komi = $komi_m;
         break;

      case HTYPE_BLACK:
      case HTYPE_WHITE:
         $tomove = 1; // any positive value
         $handicap = $handicap_m;
         $komi = $komi_m;
         break;

      case HTYPE_AUCTION_KOMI:
         if( (string)$komi_auko == '' || !is_numeric($komi_auko) )
            error('invalid_komi_bid', "make_invite_game.check.komi_auko.miss($disputegid,$komi_auko)");
         $komi_auko = (float)$komi_auko;

         $tomove = INVITE_HANDI_AUCTION_KOMI;
         $handicap = 0;
         $komi = INIT_KOMI_BID;
         if( $Black_ID == $my_id )
            $black_prisoners = round(2 * $komi_auko);
         else
            $white_prisoners = round(2 * $komi_auko);
         break;

      default: //always available even if waiting room or unrated
         $cat_handicap_type = CAT_HTYPE_MANUAL;
         $handicap_type = HTYPE_NIGIRI;
      case HTYPE_NIGIRI:
         $tomove = INVITE_HANDI_NIGIRI;
         $handicap = $handicap_m;
         $komi = $komi_m;
         break;
   }

   // handle shape-game implicit settings (error if invalid)
   if( $shape_id > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
      if( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "make_invite_game.check.shape($shape_id,$shape_snapshot)");

      $size = (int)$arr_shape['Size'];
      $stdhandicap = 'N';
      $rated = 'N';
   }
   else
   {
      $shape_id = 0;
      $shape_snapshot = '';
   }


   if( $ruleset != RULESET_JAPANESE && $ruleset != RULESET_CHINESE )
      error('unknown_ruleset', "make_invite_game.check.ruleset($ruleset)");

   if( $handicap_type == HTYPE_AUCTION_KOMI && !($komi_auko <= MAX_KOMI_RANGE && $komi_auko >= -MAX_KOMI_RANGE) )
      error('komi_range', "make_invite_game.check.komi_auko($komi_auko)");
   if( $handicap_type != HTYPE_AUCTION_KOMI && !($komi <= MAX_KOMI_RANGE && $komi >= -MAX_KOMI_RANGE) )
      error('komi_range', "make_invite_game.check.komi($komi)");

   if( !($handicap <= MAX_HANDICAP && $handicap >= 0) )
      error('handicap_range', "make_invite_game.check.handicap($handicap)");

   if( $disputegid > 0 && ($grow['ShapeID'] != $shape_id || $grow['ShapeSnapshot'] != $shape_snapshot) )
      error('mismatch_snapshot', "make_invite_game.dispute3($disputegid,$shape_id,$shape_snapshot)");

   list($hours, $byohours, $byoperiods) =
      interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                 $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                 $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                 $byotimevalue_fis, $timeunit_fis);

   if( $hours<1 && ($byohours<1 || $byoyomitype == BYOTYPE_FISCHER) )
      error('time_limit_too_small','make_invite_game');


   if( $rated != 'Y' || $Black_ID == $White_ID )
      $rated = 'N';

   if( $stdhandicap != 'Y' ||
       !standard_handicap_is_possible($size, $handicap) )
      $stdhandicap = 'N';

   if( $weekendclock != 'Y' )
      $weekendclock = 'N';

   $query = "Black_ID=$Black_ID, " .
      "White_ID=$White_ID, " .
      "ToMove_ID=$tomove, " .
      "Lastchanged=FROM_UNIXTIME($NOW), " .
      "Ruleset='" . mysql_addslashes($ruleset) . "', " .
      "Size=$size, " .
      "Handicap=$handicap, " .
      "Komi=ROUND(2*($komi))/2, " .
      "Black_Prisoners=$black_prisoners, " . // B-komi-bid
      "White_Prisoners=$white_prisoners, " . // W-komi-bid
      "Maintime=$hours, " .
      "Byotype='$byoyomitype', " .
      "Byotime=$byohours, " .
      "Byoperiods=$byoperiods, " .
      "Black_Maintime=$hours, " .
      "White_Maintime=$hours, " .
      "WeekendClock='$weekendclock', " .
      "StdHandicap='$stdhandicap', " .
      "Rated='$rated', " .
      "ShapeID='$shape_id', " .
      "ShapeSnapshot='" . mysql_addslashes($shape_snapshot) . "'";

   if( $disputegid > 0 )
      $query = "UPDATE Games SET $query WHERE ID=$disputegid LIMIT 1";
   else
      $query = "INSERT INTO Games SET $query";

   ta_begin();
   {//HOT-section to make invite-game
      $result = db_query( "make_invite_game.update_game($disputegid)", $query, 'mysql_insert_game' );
      if( mysql_affected_rows() != 1)
         error('mysql_start_game', "make_invite_game.update_game2($disputegid)");
      $gid = ( $disputegid > 0 ) ? $disputegid : mysql_insert_id();
   }
   ta_end();

   if( $gid <= 0 )
      error('internal_error', "make_invite_game.err2($gid)");

   return $gid;
} //make_invite_game

/*!
 * \brief Accepts an invitational game making it a running game.
 * \return 0=success, 'err_komi_bid' = error on komi_bid-input for auction-komi
 */
function accept_invite_game( $gid, $player_row, $opponent_row, $komi_bid )
{
   $my_id = $player_row['ID'];
   $opp_id = $opponent_row['ID'];
   $dbg = "accept_invite_game($gid,$my_id,$opp_id)";

   $game_row = mysql_single_fetch( 'send_message.accept',
      "SELECT Status,Black_ID,White_ID,ToMove_ID,Ruleset,Size,Handicap,Komi,Maintime,Byotype,Byotime,Byoperiods," .
         "Rated,StdHandicap,WeekendClock,Black_Prisoners,White_Prisoners,ShapeID,ShapeSnapshot " .
      "FROM Games WHERE ID=$gid LIMIT 1" );
   if( !$game_row )
      error('invited_to_unknown_game', "$dbg.accept.findgame");
   if( $game_row['Status'] != GAME_STATUS_INVITED )
      error('game_already_accepted', "$dbg.accept.badstat");

   //ToMove_ID hold handitype since INVITATION
   $handitype = $game_row['ToMove_ID'];
   $size = $game_row['Size'];

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row['Rating2'];
   $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );


   $my_col_black = ( $game_row['Black_ID'] == $my_id );
   $double = false;
   switch( (int)$handitype )
   {
      case INVITE_HANDI_CONV:
         if( !$iamrated || !$opprated )
            error('no_initial_rating', "$dbgmsg.check.conv_H.rating");
         list($game_row['Handicap'],$game_row['Komi'],$i_am_black ) =
            suggest_conventional( $my_rating, $opprating, $size);
         break;

      case INVITE_HANDI_PROPER:
         if( !$iamrated || !$opprated )
            error('no_initial_rating', "$dbgmsg.check.prop.rating");
         list($game_row['Handicap'],$game_row['Komi'],$i_am_black ) =
            suggest_proper( $my_rating, $opprating, $size);
         break;

      case INVITE_HANDI_NIGIRI:
         mt_srand ((double) microtime() * 1000000);
         $i_am_black = mt_rand(0,1);
         break;

      case INVITE_HANDI_DOUBLE:
         $double = true;
         $i_am_black = true;
         break;

      case INVITE_HANDI_AUCTION_KOMI:
         // check my komi-bid
         if( (string)$komi_bid == '' || !is_numeric($komi_bid) )
            return 'err_komi_bid';
         elseif( floor(2 * $komi_bid) != 2 * $komi_bid ) // x.0|x.5 ?
            return 'err_komi_bid';
         $komi_my_bid = (float)$komi_bid;

         // check opponent komi-bid
         $komi_opp = ( $my_col_black ) ? $game_row['White_Prisoners'] : $game_row['Black_Prisoners'];
         if( $komi_opp == INIT_KOMI_BID )
            error('internal_error', "$dbgmsg.check.auko.opp_komi($komi_my_bid,$komi_opp)");
         $komi_opp = (float)$komi_opp / 2.0;

         // determine colors
         if( $komi_my_bid > $komi_opp )
         {
            $game_row['Komi'] = $komi_my_bid;
            $i_am_black = true;
         }
         elseif( $komi_my_bid < $komi_opp )
         {
            $game_row['Komi'] = $komi_opp;
            $i_am_black = false;
         }
         else // equal bid -> nigiri
         {
            $game_row['Komi'] = $komi_my_bid;
            mt_srand((double) microtime() * 1000000);
            $i_am_black = mt_rand(0,1);
         }
         break;

      default: // 'manual': any positive value, see make_invite_game()-func HTYPE_BLACK/WHITE
         $i_am_black = $my_col_black;
         break;
   }

   ta_begin();
   {//HOT-SECTION to transform game:
      // create_game() must check the Status='INVITED' state of the game to avoid
      // that multiple clicks lead to a bad Running count increase below.
      $gids = array();
      if( $i_am_black || $double )
         $gids[] = create_game($player_row, $opponent_row, $game_row, $gid);
      else
         $gids[] = create_game($opponent_row, $player_row, $game_row, $gid);
      //always after the "already in database" one (after $gid had been checked)
      if( $double )
      {
         // provide a link between the two paired "double" games
         $game_row['double_gid'] = $gid;
         $gids[] = $double_gid2 = create_game($opponent_row, $player_row, $game_row);

         db_query( "$dbg.update_double2",
            "UPDATE Games SET DoubleGame_ID=$double_gid2 WHERE ID=$gid LIMIT 1" );
      }

      $cnt = count($gids);
      db_query( "$dbg.upd_player",
         "UPDATE Players SET Running=Running+$cnt" .
            ( $game_row['Rated'] == 'Y' ? ", RatingStatus='".RATING_RATED."'" : '' ) .
            " WHERE (ID=$my_id OR ID=$opp_id) LIMIT 2" );
   }
   ta_end();

   return 0; //ok
}//accept_invite_game


/*!
 * \brief Creates a running game, black/white_row are prefilled with chosen players
 *        always return a valid game ID from the database, else call error().
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 *
 * \note game_info_row['double_gid'] can be set to write reference to twin double-game
 * \note game_info_row['tid'] = tournament-id
 */
function create_game(&$black_row, &$white_row, &$game_info_row, $gid=0)
{
   global $NOW;

   $gid = (int)$gid;
   if( $gid > 0 )
   {
      if( !($game_info_row['Black_ID'] == $black_row['ID'] && $game_info_row['White_ID'] == $white_row['ID'] )
       && !($game_info_row['White_ID'] == $black_row['ID'] && $game_info_row['Black_ID'] == $white_row['ID'] ) )
      {
         error('mysql_start_game', "create_game.wrong_players($gid)");
      }
   }

   // handle shape-game
   $shape_id = (int)$game_info_row['ShapeID'];
   if( $shape_id > 0 )
   {
      $shape_snapshot = $game_info_row['ShapeSnapshot'];
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
      if( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "create_game.check.shape($shape_id,$shape_snapshot)");

      $GameSnapshot = $arr_shape['Snapshot'];
      $shape_black_first = (bool)@$arr_shape['PlayColorB'];
   }
   else
   {
      $shape_id = 0;
      $shape_snapshot = '';
      $shape_black_first = true;
      $GameSnapshot = '';
   }

   // multi-player-game
   $game_type = ( isset($game_info_row['GameType']) ) ? $game_info_row['GameType'] : GAMETYPE_GO;
   if( isset($game_info_row['GamePlayers']) )
      $game_players = $game_info_row['GamePlayers'];
   else if( $game_type == GAMETYPE_GO )
      $game_players = '';
   else
      error('invalid_args', "create_game.miss_game_players($gid,$game_type)");

   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.create_game');
   if( isset($game_info_row['tid']) ) // tournament-id
      $tid = (int)$game_info_row['tid'];
   else
      $game_info_row['tid'] = $tid = 0;
   if( $tid < 0 ) $tid = 0;

   $rating_black = $black_row["Rating2"];
   if( !is_numeric($rating_black) )
      $rating_black = NO_RATING;
   $rating_white = $white_row["Rating2"];
   if( !is_numeric($rating_white) )
      $rating_white = NO_RATING;
   $black_rated = ( $black_row['RatingStatus'] != RATING_NONE && $rating_black >= MIN_RATING );
   $white_rated = ( $white_row['RatingStatus'] != RATING_NONE && $rating_white >= MIN_RATING );

   if( !isset($game_info_row['Ruleset']) )
      $game_info_row['Ruleset'] = RULESET_JAPANESE; // default

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)$game_info_row["Size"]));

   $weekend_clock_offset = ( $game_info_row['WeekendClock'] != 'Y' ) ? WEEKEND_CLOCK_OFFSET : 0;
   $clock_used_black = ( $black_row['OnVacation'] > 0 )
      ? VACATION_CLOCK
      : $black_row['ClockUsed'] + $weekend_clock_offset;
   $clock_used_white = ( $white_row['OnVacation'] > 0 )
      ? VACATION_CLOCK
      : $white_row['ClockUsed'] + $weekend_clock_offset;

   $rated = ( $game_info_row['Rated'] === 'Y' && $black_rated && $white_rated );
   $game_info_row['Rated'] = ( $rated ? 'Y' : 'N' );

   // set reference to other double-game
   $double_gid = (int)@$game_info_row['double_gid'];

   // adjust komi (AdjKomi/JigoMode may be unset)
   $komi = adjust_komi( (float)$game_info_row['Komi'],
      (float)@$game_info_row['AdjKomi'],
      (string)@$game_info_row['JigoMode'] );
   $game_info_row['Komi'] = $komi; // write back

   // adjust handicap (Adj/Min/MaxHandicap may be unset)
   $handicap = adjust_handicap( (int)$game_info_row['Handicap'],
      (int)@$game_info_row['AdjHandicap'],
      (int)@$game_info_row['MinHandicap'],
      ( isset($game_info_row['MaxHandicap']) ? (int)$game_info_row['MaxHandicap'] : -1 ));
   $game_info_row['Handicap'] = $handicap; // write back

   $stdhandicap = $game_info_row['StdHandicap'];
   $moves = $handicap;
   if( $stdhandicap != 'Y' || !standard_handicap_is_possible($size, $moves ) )
      $stdhandicap = 'N';

   $skip_handicap_validation = ( (ENABLE_STDHANDICAP & 2) && $stdhandicap == 'Y' && $moves > 1 );

   $shape_need_pass = false;
   if( $shape_id == 0 )
      $skip_handicap_validation = false;

   if( $skip_handicap_validation ) // std-handicap-placement
   {
      //$moves = $moves;
      $tomove = $white_row['ID'];
      $col_to_move = WHITE;
      $clock_used = $clock_used_white;
   }
   else // no-handicap OR free-handicap-placement
   {
      if( $shape_id > 0 && !$shape_black_first && $handicap > 0 )
         $shape_black_first = true; // enforce B-first to set free-handicap

      if( $shape_id > 0 && !$shape_black_first && $handicap == 0 )
      {
         $shape_need_pass = true;
         $moves = 1;
         $tomove = $white_row['ID'];
         $col_to_move = WHITE;
         $clock_used = $clock_used_white;
      }
      else
      {
         $moves = 0;
         $tomove = $black_row['ID'];
         $col_to_move = BLACK;
         $clock_used = $clock_used_black;
      }
   }
   $last_ticks = get_clock_ticks( $clock_used, /*refresh-cache*/false );

   $game_info_row['X_BlackClock'] = $black_row['ClockUsed']; // no weekendclock-offset or vacation-clock
   $game_info_row['X_WhiteClock'] = $white_row['ClockUsed'];
   $timeout_date = NextGameOrder::make_timeout_date(
      $game_info_row, $col_to_move, $last_ticks, true/*new-game*/ );

   $set_query =
      "tid=$tid, " .
      "ShapeID=$shape_id, " .
      "DoubleGame_ID=$double_gid, " .
      "Black_ID=" . $black_row["ID"] . ", " .
      "White_ID=" . $white_row["ID"] . ", " .
      "ToMove_ID=$tomove, " .
      "GameType='" . mysql_addslashes($game_type) . "', " .
      "GamePlayers='" . mysql_addslashes($game_players) . "', " .
      "Ruleset='" . mysql_addslashes($game_info_row['Ruleset']) . "', " .
      "Status='PLAY', " .
      "Moves=$moves, " .
      "Black_Prisoners=0, " .
      "White_Prisoners=0, " .
      "ClockUsed=$clock_used, " .
      "TimeOutDate=$timeout_date, " .
      "LastTicks=$last_ticks, " .
      "Lastchanged=FROM_UNIXTIME($NOW), " .
      "Starttime=FROM_UNIXTIME($NOW), " .
      "Size=$size, " .
      "Handicap=$handicap, " .
      "Komi=" . $game_info_row["Komi"] . ", " .
      "Maintime=" . $game_info_row["Maintime"] . ", " .
      "Byotype='" . $game_info_row["Byotype"] . "', " .
      "Byotime=" . $game_info_row["Byotime"] . ", " .
      "Byoperiods=" . $game_info_row["Byoperiods"] . ", " .
      "Black_Maintime=" . $game_info_row["Maintime"] . ", " .
      "White_Maintime=" . $game_info_row["Maintime"] . ", " .
      ($black_rated ? "Black_Start_Rating=$rating_black, " : '' ) .
      ($white_rated ? "White_Start_Rating=$rating_white, " : '' ) .
      "WeekendClock='" . $game_info_row["WeekendClock"] . "', " .
      "StdHandicap='$stdhandicap', " .
      "Rated='" . $game_info_row["Rated"] . "', " .
      "ShapeSnapshot='" . mysql_addslashes($shape_snapshot) . "'";
   if( $shape_id > 0 )
      $set_query .= ", Snapshot='" . mysql_addslashes($GameSnapshot) . "'";

   if( $gid > 0 ) // game prepared by the invitation process or multi-player-game-setup
   {
      $prev_status = ($game_type == GAMETYPE_GO) ? GAME_STATUS_INVITED : GAME_STATUS_SETUP;
      db_query( "create_game.update($gid,$game_type)",
         "UPDATE Games SET $set_query WHERE ID=$gid AND Status='$prev_status' LIMIT 1" );
      if( mysql_affected_rows() != 1)
         error('mysql_start_game', "create_game.update2($gid,$game_type)");
   }
   else // new game
   {
      db_query( 'create_game.insert',
         "INSERT INTO Games SET $set_query" );
      $gid = mysql_insert_id();
   }

   if( $gid <= 0 )
      error('internal_error', "create_game.err2($gid)");

   //ENABLE_STDHANDICAP:
   // both b1 and b2 set is not fully handled (error if incomplete pattern)
   if( $skip_handicap_validation )
   {
      if( $shape_id == 0 && !make_standard_placement_of_handicap_stones($size, $handicap, $gid) )
      {
         //error because it's too late to have a manual placement
         //as the game is already initialized for the white play
         error('internal_error', "create_game.std_handicap($gid)");
      }

      // Black has set handicap-stones -> setup 2nd-next player in multi-player-game
      if( $game_type != GAMETYPE_GO )
      {
         list( $group_color, $group_order, $gpmove_color )
            = MultiPlayerGame::calc_game_player_for_move( $game_players, $moves, $handicap, 1 );
         $next_black_id = GamePlayer::load_uid_for_move( $gid, $group_color, $group_order );
         db_query( "create_game.update_games.next2_gp($gid,$game_type,$next_black_id)",
            "UPDATE Games SET Black_ID=$next_black_id WHERE ID=$gid LIMIT 1" );
      }

      // create game-snapshot for thumbnail (for handicap-stones)
      $stdhandicap_game_row = array( 'ID' => $gid, 'Size' => $size, 'Moves' => $moves, 'ShapeSnapshot' => '' );
      $TheBoard = new Board();
      if( $TheBoard->load_from_db($stdhandicap_game_row) ) // ignore errors
      {
         $snapshot = GameSnapshot::make_game_snapshot($size, $TheBoard);
         db_query( "create_game.upd_game.stdh_snapshot($gid,$size)",
            "UPDATE Games SET Snapshot='" . mysql_addslashes($snapshot) . "' WHERE ID=$gid LIMIT 1" );
      }
   }//set-std-handicap

   // handle shape-game (W-first) & MPG-game
   if( $shape_id > 0 )
   {
      if( $shape_need_pass ) // insert PASS-move for B if shape requires W-first to move
         db_query( "create_game.shape_w1st.pass($gid)",
            "INSERT INTO Moves SET gid=$gid, MoveNr=1, Stone=".BLACK.", PosX=".POSX_PASS.", PosY=0, Hours=0" );

      // setup 2nd-next player in multi-player-game, if W-first for shape-game
      if( $game_type != GAMETYPE_GO )
      {
         list( $group_color, $group_order, $gpmove_color )
            = MultiPlayerGame::calc_game_player_for_move( $game_players, $moves, $handicap, 1 );
         $next_black_id = GamePlayer::load_uid_for_move( $gid, $group_color, $group_order );
         db_query( "create_game.update_games.shape_w1st.next3_gp($gid,$game_type,$next_black_id)",
            "UPDATE Games SET Black_ID=$next_black_id WHERE ID=$gid LIMIT 1" );
      }
   }

   return $gid;
} //create_game

function standard_handicap_is_possible($size, $hcp)
{
   if( ENABLE_STDHANDICAP & 4 ) //allow everything
      return true;
   return( $size == 19 || $hcp <= 4 || ($hcp <= 9 && $size%2 == 1 && $size>=9) );
}

if( ENABLE_STDHANDICAP & 2 ) { //skip black validation
//for get_handicap_pattern:
require_once('include/sgf_parser.php');
require_once('include/coords.php');

//return false if no placement is done but is still possible
// IMPORTANT NOTE: caller needs to open TA with HOT-section if used with other db-writes!!
function make_standard_placement_of_handicap_stones(
            $size, $hcp, $gid, $allow_incomplete_pattern=false)
{
   if( $gid <= 0 )
      error('unknown_game','make_std_handicap');

   if( $hcp < 2 )
      return false;

   if( !standard_handicap_is_possible($size, $hcp) )
      return false;

   $err = '';
   $stonestring = get_handicap_pattern( $size, $hcp, $err);
   //if( $err ) return false;

   $patlen = strlen( $stonestring );
   if( ( $patlen > 2*$hcp ) || ( $patlen < 2*$hcp && !$allow_incomplete_pattern ) )
      error('internal_error', "make_std_handicap.bad_pattern($gid,$hcp,$patlen)");

   $patlen = min( 2*$hcp, $patlen);

   $query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

   for( $i=0; $i < $patlen; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $size);

      if( !isset($rownr) || !isset($colnr) )
         error('illegal_position','make_std_handicap.err2');

      $query .= "($gid, " . ($i/2 + 1) . ", " . BLACK . ", $colnr, $rownr, 0)";
      if( $i+2 < $patlen ) $query .= ", ";
   }

   db_query( 'make_std_handicap.err3', $query );

   if( $patlen != 2*$hcp )
      return false;

   return true;
}//make_standard_placement_of_handicap_stones

} //ENABLE_STDHANDICAP & 2

?>

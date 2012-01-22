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
function make_invite_game( &$player_row, &$opponent_row, $disputegid )
{
   $my_id = $player_row['ID'];
   $opp_id = $opponent_row['ID'];

   $my_gs = make_invite_game_setup( $player_row, $opponent_row );
   $my_gs_old = $opp_gs = null;

   if( $disputegid > 0 ) // dispute
   {
      // Check if dispute game exists
      $grow = mysql_single_fetch( "make_game.make_invite_game.dispute($disputegid)",
            "SELECT ID, Black_ID, White_ID, ShapeID, ShapeSnapshot, GameSetup " .
            "FROM Games WHERE ID=$disputegid AND Status='".GAME_STATUS_INVITED."' LIMIT 1" );
      if( !$grow )
         error('unknown_game', "make_invite_game.dispute1($disputegid)");

      $correct_game = ( $grow['Black_ID'] == $my_id && $grow['White_ID'] == $opp_id )
                   || ( $grow['Black_ID'] == $opp_id && $grow['White_ID'] == $my_id );
      if( !$correct_game )
         error('wrong_dispute_game', "make_invite_game.dispute2($disputegid,$my_id,$opp_id)");

      if( $grow['ShapeID'] != $my_gs->ShapeID || $grow['ShapeSnapshot'] != $my_gs->ShapeSnapshot )
         error('mismatch_snapshot', "make_invite_game.dispute3($disputegid,{$my_gs->ShapeID},{$my_gs->ShapeSnapshot})");

      $Black_ID = $grow['Black_ID'];
      $White_ID = $grow['White_ID'];

      list( $my_gs_old, $opp_gs ) = GameSetup::parse_invitation_game_setup( $my_id, $grow['GameSetup'], $disputegid );
   }
   else // invitation
   {
      if( $my_gs->Handicaptype == HTYPE_WHITE )
      {
         $Black_ID = $opp_id;
         $White_ID = $my_id;
      }
      else // HTYPE_BLACK & all other handicap-types
      {
         $Black_ID = $my_id;
         $White_ID = $opp_id;
      }
   }

   if( is_null($opp_gs) ) // create opponents GameSetup
   {
      // Notes:
      // 1. properly handle BLACK/WHITE-handicaptype for disputes
      // 2. not-set shouldn't happen for dispute (except for non-migrated games) -> fix setup with opp-default
      $opp_gs = GameSetup::create_opponent_game_setup( $my_gs, $opp_id );
   }
   $game_setup_encoded = GameSetup::build_invitation_game_setup( $my_gs, $opp_gs );

   //ToMove_ID=$tomove will hold handitype until game-setting accepted
   $tomove = get_invite_handicaptype( $my_gs->Handicaptype );
   if( !$tomove )
      $tomove = INVITE_HANDI_NIGIRI; //always available


   $upd_game = new UpdateQuery('Games');
   $upd_game->upd_num('Black_ID', $Black_ID);
   $upd_game->upd_num('White_ID', $White_ID);
   $upd_game->upd_num('ToMove_ID', $tomove); // handicap-type
   $upd_game->upd_time('Lastchanged');
   $upd_game->upd_txt('Ruleset', $my_gs->Ruleset);
   $upd_game->upd_num('Size', $my_gs->Size);
   $upd_game->upd_num('Handicap', $my_gs->Handicap);
   $upd_game->upd_bool('StdHandicap', $my_gs->StdHandicap );
   $upd_game->upd_num('Komi', $my_gs->Komi );
   $upd_game->upd_num('Maintime', $my_gs->Maintime );
   $upd_game->upd_txt('Byotype', $my_gs->Byotype );
   $upd_game->upd_num('Byotime', $my_gs->Byotime );
   $upd_game->upd_num('Byoperiods', $my_gs->Byoperiods );
   $upd_game->upd_num('Black_Maintime', $my_gs->Maintime );
   $upd_game->upd_num('White_Maintime', $my_gs->Maintime );
   $upd_game->upd_bool('WeekendClock', $my_gs->WeekendClock );
   $upd_game->upd_bool('Rated', $my_gs->Rated );
   $upd_game->upd_num('ShapeID', $my_gs->ShapeID );
   $upd_game->upd_txt('ShapeSnapshot', $my_gs->ShapeSnapshot );
   $upd_game->upd_txt('GameSetup', $game_setup_encoded );

   $upd_game_query = $upd_game->get_query();
   if( $disputegid > 0 )
      $query = "UPDATE Games SET $upd_game_query WHERE ID=$disputegid LIMIT 1";
   else
      $query = "INSERT INTO Games SET $upd_game_query";

   // make invite-game (or save dispute)
   $result = db_query( "make_invite_game.update_game($disputegid)", $query, 'mysql_insert_game' );
   if( mysql_affected_rows() != 1)
      error('mysql_start_game', "make_invite_game.update_game2($disputegid)");
   $gid = ( $disputegid > 0 ) ? $disputegid : mysql_insert_id();

   if( $gid <= 0 )
      error('internal_error', "make_invite_game.err2($gid)");

   return $gid;
}//make_invite_game

/*!
 * \brief Creates GameSetup-obj parsed from URL-args, used as container for game-settings.
 * \param $my_urow pivot-user, required fields in row: ID, RatingStatus, Rating2
 */
function make_invite_game_setup( $my_urow, $opp_urow )
{
   $my_id = $my_urow['ID'];
   $opp_id = $opp_urow['ID'];
   if( $my_id == $opp_id )
      error('invalid_args', "make_invite_game_setup.check.same_players($my_id,$opp_id)");

   $gs = new GameSetup( $my_id );

   $cat_handicap_type = @$_REQUEST['cat_htype'];
   if( $cat_handicap_type == CAT_HTYPE_MANUAL )
      $gs->Handicaptype = @$_REQUEST['color_m']; // handitype
   elseif( $cat_handicap_type == CAT_HTYPE_FAIR_KOMI )
   {
      $gs->Handicaptype = @$_REQUEST['fk_htype'];
      $gs->JigoMode = @$_REQUEST['jigo_mode'];
      if( !preg_match("/^(".CHECK_JIGOMODE.")$/", $gs->JigoMode) )
         error('invalid_args', "make_invite_game_setup.check.fk.jigomode({$gs->JigoMode})");
   }
   else
      $gs->Handicaptype = $cat_handicap_type; // conv/proper

   // check handi-type & cat-handi-type
   $check_cat_htype = get_category_handicaptype($gs->Handicaptype);
   if( !$check_cat_htype || $check_cat_htype !== $cat_handicap_type )
      error('invalid_args', "make_invite_game_setup.check.cat_htype({$gs->Handicaptype},$check_cat_htype,$cat_handicap_type)");

   $my_rating = $my_urow['Rating2'];
   $iamrated = ( $my_urow['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opp_urow['Rating2'];
   $opprated = ( $opp_urow['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );

   $gs->Rated = ( @$_REQUEST['rated'] == 'Y' );
   $gs->StdHandicap = ( @$_REQUEST['stdhandicap'] == 'Y' );
   $gs->WeekendClock = ( @$_REQUEST['weekendclock'] == 'Y' );

   $gs->Handicap = (int)@$_REQUEST['handicap_m'];
   $gs->Komi = (float)@$_REQUEST['komi_m'];
   switch( (string)$gs->Handicaptype )
   {
      case HTYPE_CONV:
      case HTYPE_PROPER:
         if( !$iamrated || !$opprated )
            error('no_initial_rating', "make_invite_game_setup.check.urat({$gs->Handicaptype},$iamrated,$opprated)");
         //break; running through
      case HTYPE_AUCTION_SECRET: //further computing for conv/proper, or negotiating on fair-komi
         $gs->Handicap = $gs->Komi = 0;
         break;

      case HTYPE_NIGIRI:
      case HTYPE_DOUBLE:
      case HTYPE_BLACK:
      case HTYPE_WHITE:
         break;

      default: // always available, even if waiting room or unrated
         $cat_handicap_type = CAT_HTYPE_MANUAL;
         $gs->Handicaptype = HTYPE_NIGIRI;
         break;
   }//handicap_type

   // handle shape-game implicit settings (error if invalid)
   $gs->ShapeID = (int)@$_REQUEST['shape'];
   $gs->ShapeSnapshot = @$_REQUEST['snapshot'];
   if( $gs->ShapeID > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($gs->ShapeSnapshot);
      if( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "make_invite_game_setup.check.shape({$gs->ShapeID},{$gs->ShapeSnapshot})");

      $gs->Size = (int)$arr_shape['Size'];
      $gs->StdHandicap = false;
      $gs->Rated = false;
   }
   else
   {
      $gs->Size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_REQUEST['size']));
      $gs->ShapeID = 0;
      $gs->ShapeSnapshot = '';
   }


   $gs->Ruleset = @$_REQUEST['ruleset'];
   if( $gs->Ruleset != RULESET_JAPANESE && $gs->Ruleset != RULESET_CHINESE )
      error('unknown_ruleset', "make_invite_game_setup.check.ruleset({$gs->Ruleset})");

   if( !($gs->Komi <= MAX_KOMI_RANGE && $gs->Komi >= -MAX_KOMI_RANGE) )
      error('komi_range', "make_invite_game_setup.check.komi({$gs->Komi})");
   $gs->Komi = (float)round( 2 * $gs->Komi ) / 2.0;

   if( !($gs->Handicap <= MAX_HANDICAP && $gs->Handicap >= 0) )
      error('handicap_range', "make_invite_game_setup.check.handicap({$gs->Handicap})");

   if( !standard_handicap_is_possible($gs->Size, $gs->Handicap) )
      $gs->StdHandicap = false;

   // time

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

   list($hours, $byohours, $byoperiods) =
      interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                 $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                 $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                 $byotimevalue_fis, $timeunit_fis);
   if( $hours < 1 && ($byohours < 1 || $byoyomitype == BYOTYPE_FISCHER) )
      error('time_limit_too_small', "make_invite_game_setup.check.time($byoyomitype,$hours,$byohours,$byoperiods)");
   $gs->Maintime = $hours;
   $gs->Byotype = $byoyomitype;
   $gs->Byotime = $byohours;
   $gs->Byoperiods = $byoperiods;

   return $gs;
}//make_invite_game_setup


/*! \brief Accepts an invitational game making it a running game; returning array of created game-IDs. */
function accept_invite_game( $gid, $player_row, $opponent_row )
{
   $my_id = $player_row['ID'];
   $opp_id = $opponent_row['ID'];
   $dbg = "accept_invite_game($gid,$my_id,$opp_id)";

   $game_row = mysql_single_fetch( "$dbg.findgame",
         "SELECT Status,Black_ID,White_ID,ToMove_ID,Ruleset,Size,Handicap,Komi,Maintime,Byotype,Byotime,Byoperiods," .
            "Rated,StdHandicap,WeekendClock,ShapeID,ShapeSnapshot,GameSetup " .
         "FROM Games WHERE ID=$gid LIMIT 1" );
   if( !$game_row )
      error('invited_to_unknown_game', "$dbg.findgame2");
   $correct_game = ( $game_row['Black_ID'] == $my_id && $game_row['White_ID'] == $opp_id )
                || ( $game_row['Black_ID'] == $opp_id && $game_row['White_ID'] == $my_id );
   if( !$correct_game )
      error('wrong_dispute_game', "$dbg.check_players");
   if( $game_row['Status'] != GAME_STATUS_INVITED )
      error('game_already_accepted', "$dbg.badstat");


   //ToMove_ID holds handitype since INVITATION
   $invite_handitype = (int)$game_row['ToMove_ID'];
   $my_col_black = ( $my_id == $game_row['Black_ID'] );
   $handicaptype = get_handicaptype_for_invite( $invite_handitype, $my_col_black );
   $cat_htype = get_category_handicaptype($handicaptype);

   // handle game-setup
   list( $my_gs, $opp_gs ) = GameSetup::parse_invitation_game_setup( $my_id, $game_row['GameSetup'], $gid );
   if( is_null($my_gs) ) // shouldn't happen -> use defaults
   {
      $my_gs = new GameSetup( $my_id );
      $my_gs->Handicaptype = $handicaptype;
      if( $cat_htype == CAT_HTYPE_MANUAL )
      {
         $my_gs->Handicap = (int)$game_row['Handicap'];
         $my_gs->Komi = (float)$game_row['Komi'];
      }
   }

   // prepare final game-settings

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row['Rating2'];
   $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );

   $size = $game_row['Size'];
   $double = false;

   $handicap = $game_row['Handicap'];
   $komi = $game_row['Komi'];
   switch( (string)$handicaptype )
   {
      case HTYPE_CONV:
         if( !$iamrated || !$opprated )
            error('no_initial_rating', "$dbgmsg.check.conv_H.rating");
         list( $handicap, $komi, $i_am_black ) = suggest_conventional( $my_rating, $opprating, $size);
         break;

      case HTYPE_PROPER:
         if( !$iamrated || !$opprated )
            error('no_initial_rating', "$dbgmsg.check.prop.rating");
         list( $handicap, $komi, $i_am_black ) = suggest_proper( $my_rating, $opprating, $size);
         break;

      // manual handicap-types
      case HTYPE_DOUBLE:
         $double = true;
         $i_am_black = true;
         break;
      case HTYPE_BLACK:
         $i_am_black = $my_col_black;
         break;
      case HTYPE_WHITE:
         $i_am_black = !$my_col_black;
         break;

      // fair-komi handicap-types, start fair-komi-negotiation
      case HTYPE_AUCTION_SECRET:
         if( is_null($my_gs) )
            error('internal_error', "$dbgmsg.check.game_setup");
         $my_gs->Komi = $my_gs->OppKomi = NULL;
         $handicap = $komi = 0;
         $i_am_black = $my_col_black;
         break;

      //case HTYPE_NIGIRI: // default
      default:
         mt_srand((double) microtime() * 1000000);
         $i_am_black = mt_rand(0,1);
         break;
   }
   $game_row['Handicap'] = $handicap;
   $game_row['Komi'] = $komi;


   ta_begin();
   {//HOT-SECTION to transform game:
      // create_game() must check the Status='INVITED' state of the game to avoid
      // that multiple clicks lead to a bad Running count increase below.
      // NOTE: for non-game-invitations, create_game()-func must avoid that multiple-clicks lead to race-conditions
      $gids = array();
      if( $i_am_black || $double )
         $gids[] = create_game($player_row, $opponent_row, $game_row, $my_gs, $gid);
      else
         $gids[] = create_game($opponent_row, $player_row, $game_row, $my_gs, $gid);
      $gid = $gids[0];

      // always after the "already in database" one
      if( $double )
      {
         // provide a link between the two paired "double" games
         $game_row['double_gid'] = $gid;
         $gids[] = $double_gid2 = create_game($opponent_row, $player_row, $game_row, $game_setup);

         db_query( "$dbg.update_double2",
            "UPDATE Games SET DoubleGame_ID=$double_gid2 WHERE ID=$gid LIMIT 1" );
      }

      GameHelper::update_players_start_game( 'accept_invite_game',
         $my_id, $opp_id, count($gids), ( $game_row['Rated'] == 'Y' ) );
   }
   ta_end();

   return $gids;
}//accept_invite_game


/*!
 * \brief Creates a running game, black/white_row are prefilled with chosen players
 *        always return a valid game ID from the database, else call error().
 * \param $black_row / $white_row required fields: ID, Rating2, RatingStatus, OnVacation, ClockUsed
 * \param $game_info_row required fields & [optional fields with defaults]:
 *        Handicaptype, Black_ID, White_ID, ShapeID, ShapeSnapshot, GameType, [GamePlayers], [tid], [Ruleset], Size,
 *        Rated, double_gid, Komi, [AdjKomi], [JigoMode], Handicap, [AdjHandicap], [MinHandicap], [MaxHandicap],
 *        StdHandicap, Maintime, Byotype, Byotime, Byoperiods, WeekendClock;
 *        field 'double_gid' can be set to write reference to twin double-game,
 *        field 'tid' = tournament-id
 * \param $game_setup GameSetup-object
 *
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 */
function create_game(&$black_row, &$white_row, &$game_info_row, $game_setup=null, $gid=0)
{
   $black_id = (int)$black_row['ID'];
   $white_id = (int)$white_row['ID'];

   $gid = (int)$gid;
   if( $gid > 0 )
   {
      if( !($game_info_row['Black_ID'] == $black_id && $game_info_row['White_ID'] == $white_id )
       && !($game_info_row['White_ID'] == $black_id && $game_info_row['Black_ID'] == $white_id ) )
      {
         error('wrong_players', "create_game.wrong_players($gid)");
      }
   }

   // check handicap-type (only needed for fair-komi checks)
   if( is_null($game_setup) ) // e.g. for MPG
   {
      $htype = @$game_info_row['Handicaptype'];
      $game_setup_encoded = '';
   }
   else
   {
      $htype = $game_setup->Handicaptype;
      $game_setup_encoded = $game_setup->encode();
   }
   if( !$htype )
      error('invalid_args', "create_game.check.miss_htype($gid,$black_id,$white_id)");

   // check fair-komi
   $is_fairkomi = ( get_category_handicaptype($htype) == CAT_HTYPE_FAIR_KOMI );
   if( $is_fairkomi )
   {
      if( !$game_setup_encoded )
         error('invalid_args', "create_game.check.fairkomi.miss_gs($htype,$gid,$black_id,$white_id)");
      if( $game_setup->uid != $black_id && $game_setup->uid != $white_id )
         error('wrong_players', "create_game.check.fairkomi.players($htype,$gid,{$game_setup->uid},$black_id,$white_id)");
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
   if( $is_fairkomi && $game_type != GAMETYPE_GO )
      error('invalid_args', "create_game.fairkomi.no_mpg($gid,$game_type,$htype)");

   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.create_game');
   if( isset($game_info_row['tid']) ) // tournament-id
      $tid = (int)$game_info_row['tid'];
   else
      $game_info_row['tid'] = $tid = 0;
   if( $tid < 0 ) $tid = 0;

   $rating_black = $black_row['Rating2'];
   if( !is_numeric($rating_black) )
      $rating_black = NO_RATING;
   $rating_white = $white_row['Rating2'];
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
   $game_info_row['Rated'] = ( $rated ) ? 'Y' : 'N';

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
   if( $shape_id > 0 )
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
         $tomove = $black_id;
         $col_to_move = BLACK;
         $clock_used = $clock_used_black;
      }
   }
   $last_ticks = get_clock_ticks( $clock_used, /*refresh-cache*/false );

   $game_info_row['X_BlackClock'] = $black_row['ClockUsed']; // no weekendclock-offset or vacation-clock
   $game_info_row['X_WhiteClock'] = $white_row['ClockUsed'];
   $timeout_date = NextGameOrder::make_timeout_date( $game_info_row, $col_to_move, $last_ticks, true/*new-game*/ );

   // setup fair-komi-game, determine game-status
   if( $is_fairkomi )
   {
      $game_status = GAME_STATUS_KOMI;
      $tomove = $game_setup->uid;
   }
   else
      $game_status = GAME_STATUS_PLAY;


   // insert new game

   $upd_game = new UpdateQuery('Games');
   $upd_game->upd_num('tid', $tid);
   $upd_game->upd_num('ShapeID', $shape_id);
   $upd_game->upd_num('DoubleGame_ID', $double_gid);
   $upd_game->upd_num('Black_ID', $black_id);
   $upd_game->upd_num('White_ID', $white_id);
   $upd_game->upd_num('ToMove_ID', $tomove);
   $upd_game->upd_txt('GameType', $game_type);
   $upd_game->upd_txt('GamePlayers', $game_players);
   $upd_game->upd_txt('Ruleset', $game_info_row['Ruleset']);
   $upd_game->upd_txt('Status', $game_status);
   $upd_game->upd_num('Moves', $moves);
   $upd_game->upd_num('ClockUsed', $clock_used);
   $upd_game->upd_num('TimeOutDate', $timeout_date);
   $upd_game->upd_num('LastTicks', $last_ticks);
   $upd_game->upd_time('Lastchanged');
   $upd_game->upd_time('Starttime');
   $upd_game->upd_num('Size', $size);
   $upd_game->upd_num('Handicap', $handicap);
   $upd_game->upd_num('Komi', $komi);
   $upd_game->upd_num('Maintime', $game_info_row['Maintime']);
   $upd_game->upd_txt('Byotype', $game_info_row['Byotype']);
   $upd_game->upd_num('Byotime', $game_info_row['Byotime']);
   $upd_game->upd_num('Byoperiods', $game_info_row['Byoperiods']);
   $upd_game->upd_num('Black_Maintime', $game_info_row['Maintime']);
   $upd_game->upd_num('White_Maintime', $game_info_row['Maintime']);
   if( $black_rated )
      $upd_game->upd_num('Black_Start_Rating', $rating_black);
   if( $white_rated )
      $upd_game->upd_num('White_Start_Rating', $rating_white);
   $upd_game->upd_txt('WeekendClock',  $game_info_row['WeekendClock']);
   $upd_game->upd_txt('StdHandicap', $stdhandicap);
   $upd_game->upd_txt('Rated', $game_info_row['Rated']);
   $upd_game->upd_txt('ShapeSnapshot', $shape_snapshot);
   $upd_game->upd_txt('GameSetup', $game_setup_encoded);
   if( $shape_id > 0 )
      $upd_game->upd_txt('Snapshot', $GameSnapshot);

   $set_game_query = $upd_game->get_query();
   if( $gid > 0 ) // game prepared by the invitation process or multi-player-game-setup
   {
      $prev_status = ($game_type == GAMETYPE_GO) ? GAME_STATUS_INVITED : GAME_STATUS_SETUP;
      db_query( "create_game.update($gid,$game_type)",
         "UPDATE Games SET $set_game_query WHERE ID=$gid AND Status='$prev_status' LIMIT 1" );
      if( mysql_affected_rows() != 1)
         error('mysql_start_game', "create_game.update2($gid,$game_type)");
   }
   else // new game (waiting-room)
   {
      db_query( 'create_game.insert',
         "INSERT INTO Games SET $set_game_query" );
      $gid = mysql_insert_id();
   }

   if( $gid <= 0 )
      error('internal_error', "create_game.err2($gid)");

   //ENABLE_STDHANDICAP:
   // both b1 and b2 set is not fully handled (error if incomplete pattern)
   if( $skip_handicap_validation && $shape_id == 0 )
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
      $stdh_game_row = array( 'ID' => $gid, 'Size' => $size, 'Moves' => $moves, 'ShapeSnapshot' => '' );
      $TheBoard = new Board();
      if( $TheBoard->load_from_db($stdh_game_row, 0, /*dead*/true, /*lastmsg*/false, /*fix*/false) ) // ignore errors
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
      {
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
function make_standard_placement_of_handicap_stones( $size, $hcp, $gid, $allow_incomplete_pattern=false )
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

   $moves_query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

   for( $i=0; $i < $patlen; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $size);

      if( !isset($rownr) || !isset($colnr) )
         error('illegal_position','make_std_handicap.err2');

      $moves_query .= "($gid, " . ($i/2 + 1) . ", " . BLACK . ", $colnr, $rownr, 0)";
      if( $i+2 < $patlen ) $moves_query .= ", ";
   }

   db_query( 'make_std_handicap.insert_moves.err3', $moves_query );

   if( $patlen != 2*$hcp )
      return false;

   return true;
}//make_standard_placement_of_handicap_stones

} //ENABLE_STDHANDICAP & 2

?>

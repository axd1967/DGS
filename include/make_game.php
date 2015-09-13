<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/board.php';

// for get_handicap_pattern:
require_once 'include/sgf_parser.php';
require_once 'include/coords.php';


// Inserts INVITATION-game or updates DISPUTE-game
// always return a valid game ID from the database, else call error()
// IMPORTANT NOTE: caller needs to open TA with HOT-section!!
function make_invite_game( &$player_row, &$opponent_row, $disputegid )
{
   $my_id = $player_row['ID'];
   $opp_id = $opponent_row['ID'];

   $my_gs = make_invite_game_setup_from_url( $player_row, $opponent_row );
   $is_dispute = ( $disputegid > 0 );

   if ( $is_dispute )
   {
      // Check if dispute game exists
      $grow = mysql_single_fetch( "make_game.make_invite_game.dispute($disputegid)",
            "SELECT ID, Black_ID, White_ID, ShapeID, ShapeSnapshot " .
            "FROM Games WHERE ID=$disputegid AND Status='".GAME_STATUS_INVITED."' LIMIT 1" );
      if ( !$grow )
         error('unknown_game', "make_invite_game.dispute1($disputegid)");

      $correct_game = ( $grow['Black_ID'] == $my_id && $grow['White_ID'] == $opp_id )
                   || ( $grow['Black_ID'] == $opp_id && $grow['White_ID'] == $my_id );
      if ( !$correct_game )
         error('wrong_dispute_game', "make_invite_game.dispute2($disputegid,$my_id,$opp_id)");

      // shape is not allowed to change
      if ( $grow['ShapeID'] != $my_gs->ShapeID || $grow['ShapeSnapshot'] != $my_gs->ShapeSnapshot )
         error('mismatch_snapshot', "make_invite_game.dispute3($disputegid,{$my_gs->ShapeID},{$my_gs->ShapeSnapshot})");

      if ( is_null(GameInvitation::load_game_invitation( $disputegid, $my_id )) )
         error('invite_bad_gamesetup', "make_invite_game.dispute4.miss_game_inv($disputegid,$my_id)");
   }


   // insert new Games-entry, or for dispute update Games-entry with game-setup of current dispute-sender
   $upd_game = build_invitation_game_update( $my_id, $opp_id, $my_id, $my_gs,
      /*upd-shape*/true, $is_dispute, /*upd-time*/true );
   $upd_game_query = $upd_game->get_query();
   if ( $is_dispute )
      $query = "UPDATE Games SET $upd_game_query WHERE ID=$disputegid LIMIT 1";
   else
      $query = "INSERT INTO Games SET $upd_game_query";

   // make invite-game (or save dispute)
   $result = db_query( "make_invite_game.update_game($disputegid)", $query, 'mysql_insert_game' );
   if ( mysql_affected_rows() != 1)
      error('mysql_start_game', "make_invite_game.update_game2($disputegid)");
   $gid = ( $disputegid > 0 ) ? $disputegid : mysql_insert_id();

   if ( $gid <= 0 )
      error('internal_error', "make_invite_game.err2($gid)");

   // insert both GameInvitation-entries for 1st invite-msg, or update my GameInvitation-entry on dispute
   $my_ginv = $my_gs->build_game_invitation( $gid );
   if ( $is_dispute )
      $my_ginv->update();
   else // first invite-msg
   {
      // create opponents GameSetup, handling color-switching handicap-types
      $opp_gs = GameSetup::create_opponent_game_setup( $my_gs, $opp_id );

      $opp_ginv = $opp_gs->build_game_invitation( $gid );
      GameInvitation::insert_game_invitations( array( $my_ginv, $opp_ginv ) );
   }

   return $gid;
}//make_invite_game

function build_invitation_game_update( $black_id, $white_id, $tomove_id, $game_setup, $upd_shape, $is_dispute, $upd_time )
{
   $upd_game = new UpdateQuery('Games');
   if ( !$is_dispute )
   {
      $upd_game->upd_txt('Status', GAME_STATUS_INVITED);
      if ( $upd_time )
         $upd_game->upd_time('Starttime');
      $upd_game->upd_num('Black_ID', $black_id); // initial inviting user
      $upd_game->upd_num('White_ID', $white_id); // initial invited user
   }
   $upd_game->upd_num('ToMove_ID', $tomove_id); // current sender of invitation/dispute-message
   if ( $upd_time )
      $upd_game->upd_time('Lastchanged');
   $upd_game->upd_txt('Ruleset', $game_setup->Ruleset);
   $upd_game->upd_num('Size', $game_setup->Size);
   $upd_game->upd_num('Handicap', $game_setup->Handicap);
   $upd_game->upd_bool('StdHandicap', $game_setup->StdHandicap );
   $upd_game->upd_num('Komi', $game_setup->Komi );
   $upd_game->upd_num('Maintime', $game_setup->Maintime );
   $upd_game->upd_txt('Byotype', $game_setup->Byotype );
   $upd_game->upd_num('Byotime', $game_setup->Byotime );
   $upd_game->upd_num('Byoperiods', $game_setup->Byoperiods );
   $upd_game->upd_num('Black_Maintime', $game_setup->Maintime );
   $upd_game->upd_num('White_Maintime', $game_setup->Maintime );
   $upd_game->upd_bool('WeekendClock', $game_setup->WeekendClock );
   $upd_game->upd_bool('Rated', $game_setup->Rated );
   if ( $upd_shape )
   {
      $upd_game->upd_num('ShapeID', $game_setup->ShapeID );
      $upd_game->upd_txt('ShapeSnapshot', $game_setup->ShapeSnapshot );
   }
   $upd_game->upd_txt('GameSetup', $game_setup->encode_game_setup() );
   return $upd_game;
}//build_invitation_game_update

/*!
 * \brief Creates GameSetup-obj parsed from URL-args, used as container for game-settings.
 * \param $my_urow pivot-user, required fields in row: ID, RatingStatus, Rating2
 */
function make_invite_game_setup_from_url( $my_urow, $opp_urow )
{
   $my_id = $my_urow['ID'];
   $opp_id = $opp_urow['ID'];
   if ( $my_id == $opp_id )
      error('invalid_args', "make_invite_game_setup_from_url.check.same_players($my_id,$opp_id)");

   $gs = new GameSetup( $my_id );

   $cat_handicap_type = @$_REQUEST['cat_htype'];
   if ( $cat_handicap_type == CAT_HTYPE_MANUAL )
      $gs->Handicaptype = @$_REQUEST['color_m']; // handitype
   elseif ( $cat_handicap_type == CAT_HTYPE_FAIR_KOMI )
      $gs->Handicaptype = @$_REQUEST['fk_htype'];
   else
      $gs->Handicaptype = $cat_handicap_type; // conv/proper

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_REQUEST['size']));

   // check handi-type & cat-handi-type
   $check_cat_htype = get_category_handicaptype($gs->Handicaptype);
   if ( !$check_cat_htype || $check_cat_htype !== $cat_handicap_type )
      error('invalid_args', "make_invite_game_setup_from_url.check.cat_htype({$gs->Handicaptype},$check_cat_htype,$cat_handicap_type)");
   $is_fairkomi = ( $check_cat_htype == CAT_HTYPE_FAIR_KOMI );


   $my_rating = $my_urow['Rating2'];
   $iamrated = ( $my_urow['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opp_urow['Rating2'];
   $opprated = ( $opp_urow['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );

   $gs->Rated = ( @$_REQUEST['rated'] == 'Y' );
   $gs->StdHandicap = ( @$_REQUEST['stdhandicap'] == 'Y' );
   $gs->WeekendClock = ( @$_REQUEST['weekendclock'] == 'Y' );

   $gs->Handicap = (int)@$_REQUEST['handicap_m'];
   $gs->Komi = (float)@$_REQUEST['komi_m'];
   switch ( (string)$gs->Handicaptype )
   {
      case HTYPE_CONV:
      case HTYPE_PROPER:
         if ( !$iamrated || !$opprated )
            error('no_initial_rating', "make_invite_game_setup_from_url.check.urat({$gs->Handicaptype},$iamrated,$opprated)");
         //break; running through
      case HTYPE_AUCTION_SECRET: //further computing for conv/proper, or negotiating on fair-komi
      case HTYPE_AUCTION_OPEN:
      case HTYPE_YOU_KOMI_I_COLOR:
      case HTYPE_I_KOMI_YOU_COLOR:
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
   if ( $gs->ShapeID > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($gs->ShapeSnapshot);
      if ( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "make_invite_game_setup_from_url.check.shape({$gs->ShapeID},{$gs->ShapeSnapshot})");
      if ( $gs->Handicaptype == HTYPE_CONV || $gs->Handicaptype == HTYPE_PROPER )
         error('invalid_args', "make_invite_game_setup_from_url.check.shape.htype({$gs->ShapeID},{$gs->ShapeSnapshot},{$gs->Handicaptype})");

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

   // komi adjustment
   $adj_komi = (float)@$_REQUEST['adj_komi'];
   if ( $is_fairkomi )
      $adj_komi = 0;
   if ( abs($adj_komi) > MAX_KOMI_RANGE )
      $adj_komi = ($adj_komi<0 ? -1 : 1) * MAX_KOMI_RANGE;
   if ( floor(2 * $adj_komi) != 2 * $adj_komi ) // round to x.0|x.5
      $adj_komi = ($adj_komi<0 ? -1 : 1) * round(2 * abs($adj_komi)) / 2.0;
   $gs->AdjKomi = $adj_komi;

   $jigo_mode = (string)@$_REQUEST['jigo_mode'];
   if ( $jigo_mode == '' )
      $jigo_mode = JIGOMODE_KEEP_KOMI;
   elseif ( !preg_match("/^(".CHECK_JIGOMODE.")$/", $jigo_mode) )
      error('invalid_args', "make_invite_game_setup_from_url.check.jigo_mode($jigo_mode)");
   $gs->JigoMode = $jigo_mode;

   // handicap adjustment
   $adj_handicap = (int)@$_REQUEST['adj_handicap'];
   if ( $is_fairkomi )
      $adj_handicap = 0;
   if ( abs($adj_handicap) > MAX_HANDICAP )
      $adj_handicap = ($adj_handicap<0 ? -1 : 1) * MAX_HANDICAP;
   $gs->AdjHandicap = $adj_handicap;

   $min_handicap = min( MAX_HANDICAP, max( 0, (int)@$_REQUEST['min_handicap'] ));
   if ( $is_fairkomi )
      $min_handicap = 0;

   list( $gs->MinHandicap, $gs->MaxHandicap ) =
      DefaultMaxHandicap::limit_min_max_with_def_handicap( $gs->Size, $min_handicap, (int)@$_REQUEST['max_handicap'] );


   $gs->Ruleset = @$_REQUEST['ruleset'];
   if ( !preg_match( "/^(".CHECK_RULESETS.")$/", $gs->Ruleset ) )
      error('unknown_ruleset', "make_invite_game_setup_from_url.check.ruleset({$gs->Ruleset})");
   elseif ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $gs->Ruleset ) )
      error('feature_disabled', "make_invite_game_setup_from_url.disabled.ruleset({$gs->Ruleset})");

   if ( !($gs->Komi <= MAX_KOMI_RANGE && $gs->Komi >= -MAX_KOMI_RANGE) )
      error('komi_range', "make_invite_game_setup_from_url.check.komi({$gs->Komi})");
   $gs->Komi = (float)round( 2 * $gs->Komi ) / 2.0;

   if ( !($gs->Handicap <= MAX_HANDICAP && $gs->Handicap >= 0) )
      error('handicap_range', "make_invite_game_setup_from_url.check.handicap({$gs->Handicap})");

   if ( !standard_handicap_is_possible($gs->Size, $gs->Handicap) )
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

   list( $hours, $byohours, $byoperiods, $time_errors, $time_errfields ) =
      interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                 $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                 $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                 $byotimevalue_fis, $timeunit_fis, /*limit*/true );
   if ( $hours < 1 && ($byohours < 1 || $byoyomitype == BYOTYPE_FISCHER) )
      error('time_limit_too_small', "make_invite_game_setup_from_url.check.time.min($byoyomitype,$hours,$byohours,$byoperiods)");
   if ( count($time_errors) ) // should not happen with "limited" time-settings
      error('time_limits_exceeded', "make_invite_game_setup_from_url.check.time.max($byoyomitype,$hours,$byohours,$byoperiods)");

   $gs->Maintime = $hours;
   $gs->Byotype = $byoyomitype;
   $gs->Byotime = $byohours;
   $gs->Byoperiods = $byoperiods;

   return $gs;
}//make_invite_game_setup_from_url

function make_invite_template_game_setup( $my_urow )
{
   // assume rated players
   if ( $my_urow['RatingStatus'] == RATING_NONE )
   {
      $my_urow['RatingStatus'] = RATING_INIT;
      $my_urow['Rating2'] = MIN_RATING;
   }
   $fake_urow = array( 'ID' => $my_urow['ID'] + 1, 'RatingStatus' => RATING_RATED, 'Rating2' => MIN_RATING );

   $gs = make_invite_game_setup_from_url( $my_urow, $fake_urow );
   return $gs;
}//make_invite_template_game_setup


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
   if ( !$game_row )
      error('invited_to_unknown_game', "$dbg.findgame2");
   $correct_game = ( $game_row['Black_ID'] == $my_id && $game_row['White_ID'] == $opp_id )
                || ( $game_row['Black_ID'] == $opp_id && $game_row['White_ID'] == $my_id );
   if ( !$correct_game )
      error('wrong_dispute_game', "$dbg.check_players");
   if ( $game_row['Status'] != GAME_STATUS_INVITED )
      error('game_already_accepted', "$dbg.badstat");

   // accepting invite-game from opponents invitation- or dispute-game-setup
   // NOTE: game-invitation-setup of user in 'ToMove_ID' is already copied by make_invite_game() into Games-fields
   //       ready to be used for create_game()!
   if ( $game_row['ToMove_ID'] != $opp_id )
      error('internal_error', "$dbg.bad_invitation_tomove");

   $opp_game_inv = GameInvitation::load_game_invitation( $gid, $opp_id );
   if ( is_null($opp_game_inv) )
      error('invite_bad_gamesetup', "$dbg.miss_game_invitation($opp_id)");
   $opp_gs = GameSetup::new_from_game_invitation( $game_row, $opp_game_inv );


   // prepare final game-settings

   $game_settings = GameSettings::get_game_settings_from_gamesetup( $opp_gs );

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $opprating = $opponent_row['Rating2'];
   $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );

   $handicaptype = $opp_gs->Handicaptype;
   $double = false;
   $handicap = $game_row['Handicap'];
   $komi = $game_row['Komi'];
   switch ( (string)$handicaptype ) // handicap-type seen from opponent
   {
      case HTYPE_CONV:
         if ( !$iamrated || !$opprated )
            error('no_initial_rating', "$dbg.check.conv_H.rating");
         list( $handicap, $komi, $i_am_black, $is_nigiri ) = $game_settings->suggest_conventional( $my_rating, $opprating );
         break;

      case HTYPE_PROPER:
         if ( !$iamrated || !$opprated )
            error('no_initial_rating', "$dbg.check.prop.rating");
         list( $handicap, $komi, $i_am_black, $is_nigiri ) = $game_settings->suggest_proper( $my_rating, $opprating );
         break;

      // manual handicap-types
      case HTYPE_DOUBLE:
         $double = true;
         $i_am_black = false;
         break;
      case HTYPE_BLACK:
         $i_am_black = false;
         break;
      case HTYPE_WHITE:
         $i_am_black = true;
         break;

      // fair-komi handicap-types, start fair-komi-negotiation
      case HTYPE_AUCTION_SECRET:
      case HTYPE_AUCTION_OPEN:
      case HTYPE_YOU_KOMI_I_COLOR:
      case HTYPE_I_KOMI_YOU_COLOR:
         $opp_gs->Komi = $opp_gs->OppKomi = NULL;
         $handicap = $komi = 0;

         // for div&choose-FK: komi-giver comes first, so that player must take BLACK
         if ( $handicaptype == HTYPE_YOU_KOMI_I_COLOR )
            $i_am_black = true;
         elseif ( $handicaptype == HTYPE_I_KOMI_YOU_COLOR )
            $i_am_black = false;
         else
            $i_am_black = false;
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
      if ( $i_am_black || $double )
         $gids[] = create_game($player_row, $opponent_row, $game_row, $opp_gs, $gid);
      else
         $gids[] = create_game($opponent_row, $player_row, $game_row, $opp_gs, $gid);
      $gid = $gids[0];

      // always after the "already in database" one
      if ( $double )
      {
         // provide a link between the two paired "double" games
         $game_row['double_gid'] = $gid;
         $gids[] = $double_gid2 = create_game($opponent_row, $player_row, $game_row, $opp_gs);

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
 *        Rated, double_gid, Komi, Handicap, StdHandicap, Maintime, Byotype, Byotime, Byoperiods, WeekendClock;
 *        field 'double_gid' can be set to write reference to twin double-game,
 *        field 'tid' = tournament-id
 * \param $game_setup GameSetup-object; can be null for MP-games
 *
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 */
function create_game(&$black_row, &$white_row, &$game_info_row, $game_setup=null, $gid=0)
{
   $black_id = (int)$black_row['ID'];
   $white_id = (int)$white_row['ID'];

   $gid = (int)$gid;
   if ( $gid > 0 )
   {
      if ( !($game_info_row['Black_ID'] == $black_id && $game_info_row['White_ID'] == $white_id )
        && !($game_info_row['White_ID'] == $black_id && $game_info_row['Black_ID'] == $white_id ) )
      {
         error('wrong_players', "create_game.wrong_players($gid)");
      }
   }

   // check handicap-type (only needed for fair-komi checks)
   if ( is_null($game_setup) ) // e.g. for MPG
   {
      $htype = @$game_info_row['Handicaptype'];
      $game_setup_encoded = '';
   }
   else
   {
      $htype = $game_setup->Handicaptype;
      $game_setup_encoded = $game_setup->encode_game_setup();
   }
   if ( !$htype )
      error('invalid_args', "create_game.check.miss_htype($gid,$black_id,$white_id)");

   // check fair-komi
   $is_fairkomi = ( get_category_handicaptype($htype) == CAT_HTYPE_FAIR_KOMI );
   if ( $is_fairkomi )
   {
      if ( !$game_setup_encoded )
         error('invalid_args', "create_game.check.fairkomi.miss_gs($htype,$gid,$black_id,$white_id)");
      if ( $game_setup->uid != $black_id && $game_setup->uid != $white_id )
         error('wrong_players', "create_game.check.fairkomi.players($htype,$gid,{$game_setup->uid},$black_id,$white_id)");
   }

   // handle shape-game
   $shape_id = (int)$game_info_row['ShapeID'];
   if ( $shape_id > 0 )
   {
      $shape_snapshot = $game_info_row['ShapeSnapshot'];
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
      if ( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "create_game.check.shape($shape_id,$shape_snapshot)");

      $shape_black_first = (bool)@$arr_shape['PlayColorB'];
      $GameSnapshot = $arr_shape['Snapshot'];
   }
   else
   {
      $shape_id = 0;
      $shape_snapshot = '';
      $shape_black_first = true;
      $GameSnapshot = GameSnapshot::init_game_snapshot();
   }

   // multi-player-game
   $game_type = ( isset($game_info_row['GameType']) ) ? $game_info_row['GameType'] : GAMETYPE_GO;
   if ( isset($game_info_row['GamePlayers']) )
      $game_players = $game_info_row['GamePlayers'];
   else if ( $game_type == GAMETYPE_GO )
      $game_players = '';
   else
      error('invalid_args', "create_game.miss_game_players($gid,$game_type)");
   if ( $is_fairkomi && $game_type != GAMETYPE_GO )
      error('invalid_args', "create_game.fairkomi.no_mpg($gid,$game_type,$htype)");

   if ( isset($game_info_row['tid']) ) // tournament-id
      $tid = (int)$game_info_row['tid'];
   else
      $game_info_row['tid'] = $tid = 0;
   if ( $tid < 0 ) $tid = 0;
   if ( $tid && !ALLOW_TOURNAMENTS )
      error('feature_disabled', "Tournament.create_game($tid)");

   $rating_black = $black_row['Rating2'];
   if ( !is_numeric($rating_black) )
      $rating_black = NO_RATING;
   $rating_white = $white_row['Rating2'];
   if ( !is_numeric($rating_white) )
      $rating_white = NO_RATING;
   $black_rated = ( $black_row['RatingStatus'] != RATING_NONE && $rating_black >= MIN_RATING );
   $white_rated = ( $white_row['RatingStatus'] != RATING_NONE && $rating_white >= MIN_RATING );

   if ( !isset($game_info_row['Ruleset']) )
      $game_info_row['Ruleset'] = Ruleset::get_default_ruleset();
   if ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $game_info_row['Ruleset']) )
      error('feature_disabled', "create_game.disabled.ruleset({$game_info_row['Ruleset']})");

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)$game_info_row['Size']));

   $rated = ( $game_info_row['Rated'] === 'Y' && $black_rated && $white_rated );
   $game_info_row['Rated'] = ( $rated ) ? 'Y' : 'N';

   // set reference to other double-game
   $double_gid = (int)@$game_info_row['double_gid'];

   $handicap = (int)$game_info_row['Handicap'];
   $stdhandicap = $game_info_row['StdHandicap'];
   $moves = $handicap;
   if ( $stdhandicap != 'Y' || !standard_handicap_is_possible($size, $moves ) )
      $stdhandicap = 'N';

   $skip_handicap_validation = ( (ENABLE_STDHANDICAP & 2) && $stdhandicap == 'Y' && $moves > 1 );

   $shape_need_pass = false;
   if ( $shape_id > 0 )
      $skip_handicap_validation = false;

   if ( $skip_handicap_validation ) // std-handicap-placement
   {
      $col_to_move = WHITE;
      //$moves = $moves;
   }
   else // no-handicap OR free-handicap-placement
   {
      if ( $shape_id > 0 && !$shape_black_first && $handicap > 0 )
         $shape_black_first = true; // enforce B-first to set free-handicap

      if ( $shape_id > 0 && !$shape_black_first && $handicap == 0 )
      {
         $col_to_move = WHITE;
         $shape_need_pass = true;
         $moves = 1;
      }
      else
      {
         $col_to_move = BLACK;
         $moves = 0;
      }
   }
   $next_urow = ( $col_to_move == BLACK ) ? $black_row : $white_row;
   $tomove = $next_urow['ID'];

   // determine clock-used, last-ticks, timeout-date
   if ( $next_urow['OnVacation'] > 0 ) // next-player on vacation
      $clock_used = VACATION_CLOCK; //and LastTicks=0
   else
      $clock_used = $next_urow['ClockUsed'] + ( $game_info_row['WeekendClock'] != 'Y' ? WEEKEND_CLOCK_OFFSET : 0 );
   $last_ticks = get_clock_ticks( 'create_game', $clock_used );

   $game_info_row['Black_OnVacation'] = $black_row['OnVacation'];
   $game_info_row['White_OnVacation'] = $white_row['OnVacation'];
   $timeout_date = NextGameOrder::make_timeout_date( $game_info_row, $col_to_move, $clock_used, 0, true/*new-game*/ );

   // setup fair-komi-game, determine game-status
   if ( $is_fairkomi )
   {
      $game_status = GAME_STATUS_KOMI;
      $tomove = ( is_htype_divide_choose($htype) ) ? $black_id : $game_setup->uid;
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
   $upd_game->upd_num('Komi', (float)$game_info_row['Komi']);
   $upd_game->upd_num('Maintime', $game_info_row['Maintime']);
   $upd_game->upd_txt('Byotype', $game_info_row['Byotype']);
   $upd_game->upd_num('Byotime', $game_info_row['Byotime']);
   $upd_game->upd_num('Byoperiods', $game_info_row['Byoperiods']);
   $upd_game->upd_num('Black_Maintime', $game_info_row['Maintime']);
   $upd_game->upd_num('White_Maintime', $game_info_row['Maintime']);
   if ( $black_rated )
      $upd_game->upd_num('Black_Start_Rating', $rating_black);
   if ( $white_rated )
      $upd_game->upd_num('White_Start_Rating', $rating_white);
   $upd_game->upd_txt('WeekendClock',  $game_info_row['WeekendClock']);
   $upd_game->upd_txt('StdHandicap', $stdhandicap);
   $upd_game->upd_txt('Rated', $game_info_row['Rated']);
   $upd_game->upd_txt('Snapshot', $GameSnapshot);
   $upd_game->upd_txt('ShapeSnapshot', $shape_snapshot);
   $upd_game->upd_txt('GameSetup', $game_setup_encoded);

   $set_game_query = $upd_game->get_query();
   if ( $gid > 0 ) // game prepared by the invitation process or multi-player-game-setup
   {
      $prev_status = ($game_type == GAMETYPE_GO) ? GAME_STATUS_INVITED : GAME_STATUS_SETUP;
      db_query( "create_game.update($gid,$game_type)",
         "UPDATE Games SET $set_game_query WHERE ID=$gid AND Status='$prev_status' LIMIT 1" );
      if ( mysql_affected_rows() != 1)
         error('mysql_start_game', "create_game.update2($gid,$game_type)");
   }
   else // new game (waiting-room)
   {
      db_query( 'create_game.insert',
         "INSERT INTO Games SET $set_game_query" );
      $gid = mysql_insert_id();
   }

   if ( $gid <= 0 )
      error('internal_error', "create_game.err2($gid)");

   //ENABLE_STDHANDICAP:
   // both b1 and b2 set is not fully handled (error if incomplete pattern)
   if ( $skip_handicap_validation && $shape_id == 0 )
   {
      if ( $shape_id == 0 && !make_standard_placement_of_handicap_stones($size, $handicap, $gid) )
      {
         //error because it's too late to have a manual placement
         //as the game is already initialized for the white play
         error('internal_error', "create_game.std_handicap($gid)");
      }

      // Black has set handicap-stones -> setup 2nd-next player in multi-player-game
      if ( $tid == 0 && $game_type != GAMETYPE_GO )
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
      if ( $TheBoard->load_from_db($stdh_game_row) ) // ignore errors
      {
         $snapshot = GameSnapshot::make_game_snapshot($size, $TheBoard);
         db_query( "create_game.upd_game.stdh_snapshot($gid,$size)",
            "UPDATE Games SET Snapshot='" . mysql_addslashes($snapshot) . "' WHERE ID=$gid LIMIT 1" );
      }
   }//set-std-handicap

   // handle shape-game (W-first) & MPG-game
   if ( $shape_id > 0 )
   {
      if ( $shape_need_pass ) // insert PASS-move for B if shape requires W-first to move
      {
         db_query( "create_game.shape_w1st.pass($gid)",
            "INSERT INTO Moves SET gid=$gid, MoveNr=1, Stone=".BLACK.", PosX=".POSX_PASS.", PosY=0, Hours=0" );

         // setup 2nd-next player in multi-player-game, if W-first for shape-game
         if ( $tid == 0 && $game_type != GAMETYPE_GO )
         {
            list( $group_color, $group_order, $gpmove_color )
               = MultiPlayerGame::calc_game_player_for_move( $game_players, $moves, $handicap, 1 );
            $next_black_id = GamePlayer::load_uid_for_move( $gid, $group_color, $group_order );
            db_query( "create_game.update_games.shape_w1st.next3_gp($gid,$game_type,$next_black_id)",
               "UPDATE Games SET Black_ID=$next_black_id WHERE ID=$gid LIMIT 1" );
         }
      }
   }

   // clear caches
   if ( $tomove > GUESTS_ID_MAX ) // safety-check
      clear_cache_quick_status( $tomove, QST_CACHE_GAMES );
   GameHelper::delete_cache_status_games( "create_game($gid)", $tomove );
   GameHelper::delete_cache_game_row( "create_game($gid)", $gid );
   Board::delete_cache_game_moves( "create_game($gid)", $gid ); // not needed for NEW game, but not hurting either

   return $gid;
}//create_game

function standard_handicap_is_possible($size, $hcp)
{
   if ( ENABLE_STDHANDICAP & 4 ) //allow everything
      return true;
   return ( $size == 19 || $hcp <= 4 || ($hcp <= 9 && $size%2 == 1 && $size>=9) );
}

//return false if no placement is done but is still possible
// IMPORTANT NOTE: caller needs to open TA with HOT-section if used with other db-writes!!
function make_standard_placement_of_handicap_stones( $size, $hcp, $gid, $allow_incomplete_pattern=false )
{
   if ( $gid <= 0 )
      error('unknown_game','make_std_handicap');

   if ( $hcp < 2 )
      return false;

   if ( !standard_handicap_is_possible($size, $hcp) )
      return false;

   $err = '';
   $stonestring = get_handicap_pattern( $size, $hcp, $err);
   //if ( $err ) return false;

   $patlen = strlen( $stonestring );
   if ( ( $patlen > 2*$hcp ) || ( $patlen < 2*$hcp && !$allow_incomplete_pattern ) )
      error('internal_error', "make_std_handicap.bad_pattern($gid,$hcp,$patlen)");

   $patlen = min( 2*$hcp, $patlen);

   $moves_query = "INSERT INTO Moves ( gid, MoveNr, Stone, PosX, PosY, Hours ) VALUES ";

   for ( $i=0; $i < $patlen; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $size);

      if ( !isset($rownr) || !isset($colnr) )
         error('illegal_position','make_std_handicap.err2');

      $moves_query .= "($gid, " . ($i/2 + 1) . ", " . BLACK . ", $colnr, $rownr, 0)";
      if ( $i+2 < $patlen ) $moves_query .= ", ";
   }

   db_query( 'make_std_handicap.insert_moves.err3', $moves_query );

   if ( $patlen != 2*$hcp )
      return false;

   return true;
}//make_standard_placement_of_handicap_stones

?>

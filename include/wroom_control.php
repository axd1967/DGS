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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/message_functions.php';
require_once 'include/game_functions.php';
require_once 'include/rating.php';
require_once 'include/make_game.php';
require_once 'include/contacts.php';
require_once 'include/db/waitingroom.php';
require_once 'include/classlib_user.php';


 /*!
  * \class WaitingroomControl
  *
  * \brief Controller-Class to handle waitingroom-stuff.
  */
class WaitingroomControl
{

   // ------------ static functions ----------------------------

   /*! \brief Returns QuerySQL for waiting-room (used for base-query for all/suitable/my waiting-room-games). */
   public static function build_waiting_room_query( $wroom_id=0, $suitable=false )
   {
      global $player_row, $NOW;

      $my_id = $player_row['ID'];
      $my_rating = $player_row['Rating2'];
      $my_rated_games = (int)$player_row['RatedGames'];
      $iamrated = user_has_rating();
      $my_hero_ratio_perc = 100 * User::calculate_hero_ratio( $player_row['GamesWeaker'], $player_row['Finished'],
         $player_row['Rating2'], $player_row['RatingStatus'] );

      $qsql = Waitingroom::build_query_sql( $wroom_id, /*with_player*/true );

      $qsql->add_part( SQLP_FIELDS,
         'WRJ.JoinedCount',
         'UNIX_TIMESTAMP(WRJ.ExpireDate) AS X_ExpireDate' );

      // $calculated = ( $Handicaptype == 'conv' || $Handicaptype == 'proper' );
      // $goodrated  = ( user-has-rating || Rated == 'N' );
      // $haverating = ( user-has-rating || !$calculated );
      // if ( $MustBeRated != 'Y' )  $goodrating = true;
      // else if ( user-has-rating ) $goodrating = ( $my_rating >= $RatingMin && $my_rating < $RatingMax );
      // else                        $goodrating = false;
      // $goodmingames = ( $MinRatedGames > 0 ? ($my_rated_games >= $MinRatedGames) : true );
      // $goodhero = ( $my_hero_ratio_perc >= $MinHeroRatio )

      $calculated = "WR.Handicaptype IN ('".HTYPE_CONV."','".HTYPE_PROPER."')";
      if ( $iamrated )
      {
         $goodrated = "1";
         $haverating = "1";
         $goodrating = "IF(WR.MustBeRated='Y' AND ($my_rating < WR.RatingMin OR $my_rating >= WR.RatingMax),0,1)";
      }
      else // user unrated
      {
         $goodrated = "IF(WR.Rated='N',1,0)";
         $haverating = "NOT $calculated";
         $goodrating = "IF(WR.MustBeRated='Y',0,1)";
      }
      $sql_goodmingames = "IF(WR.MinRatedGames>0,($my_rated_games >= WR.MinRatedGames),1)";

      $sql_goodmaxgames = ( MaxGamesCheck::is_limited() ) // Opponent max-games
         ? "IF(WR.uid=$my_id OR (WRP.Running + WRP.GamesMPG < ".MAX_GAMESRUN."),1,0)" : 1;

      $sql_goodsameopp =
         "CASE WHEN (WR.uid=$my_id OR WR.SameOpponent=0 OR (WR.SameOpponent > ".SAMEOPP_TOTAL." AND ISNULL(WRJ.wroom_id))) THEN 1 " .
              "WHEN (WR.SameOpponent < ".SAMEOPP_TOTAL.") THEN (COALESCE(GS.Running,0) < -WR.SameOpponent + ".SAMEOPP_TOTAL.") " . // total-times-check
              "WHEN (WR.SameOpponent<0) THEN (WRJ.JoinedCount < -WR.SameOpponent) " . // same-offer-times-check
              "ELSE (WRJ.ExpireDate <= FROM_UNIXTIME($NOW)) " . // same-offer-date-check
              "END";

      $sql_goodhero = "($my_hero_ratio_perc >= WR.MinHeroRatio)";

      $qsql->add_part( SQLP_FIELDS,
         "$calculated AS calculated",
         "$goodrated AS goodrated",
         "$haverating AS haverating",
         "$goodrating AS goodrating",
         "$sql_goodmingames AS goodmingames",
         "$sql_goodmaxgames AS goodmaxgames",
         "$sql_goodsameopp AS goodsameopp",
         "$sql_goodhero AS goodhero",
         "IF(WR.uid=$my_id OR WR.SameOpponent > ".SAMEOPP_TOTAL.",0, COALESCE(GS.Running,0)) AS X_TotalCount"
         );
      $qsql->add_part( SQLP_FROM,
         "LEFT JOIN WaitingroomJoined AS WRJ ON WRJ.opp_id=$my_id AND WRJ.wroom_id=WR.ID" );
      $qsql->add_part( SQLP_FROM,
         "LEFT JOIN GameStats AS GS ON GS.uid=IF($my_id<WR.uid,$my_id,WR.uid) AND GS.oid=IF($my_id<WR.uid,WR.uid,$my_id)" );
      if ( $suitable && MaxGamesCheck::is_limited() )
         $qsql->add_part( SQLP_HAVING, 'goodmaxgames' );

      // Contacts: make the protected waitingroom games invisible
      $qsql->add_part( SQLP_FIELDS,
         "IF(ISNULL(C.uid),0,C.SystemFlags & ".CSYSFLAG_WAITINGROOM.") AS C_denied" );
      $qsql->add_part( SQLP_FROM,
         "LEFT JOIN Contacts AS C ON C.uid=WR.uid AND C.cid=$my_id" );
      $qsql->add_part( SQLP_WHERE,
         'WR.nrGames>0' );
      $qsql->add_part( SQLP_HAVING,
         'C_denied=0' );

      // Contacts: hide unwanted user-offers (though still joinable)
      $qsql->add_part( SQLP_FIELDS,
         "IF(ISNULL(CH.uid),0,CH.SystemFlags & ".CSYSFLAG_WR_HIDE_GAMES.") AS CH_hidden" );
      $qsql->add_part( SQLP_FROM,
         "LEFT JOIN Contacts AS CH ON CH.uid=$my_id AND CH.cid=WR.uid" );
      if ( $suitable )
         $qsql->add_part( SQLP_HAVING, 'CH_hidden=0' );

      return $qsql;
   }//build_waiting_room_query

   /*! \brief Extend and return passed QuerySQL $qsql with HAVING-clause for suitable-filter. */
   public static function extend_query_waitingroom_suitable( $qsql )
   {
      $qsql->add_part( SQLP_HAVING, 'goodrating', 'goodmingames', 'goodrated', 'haverating', 'goodhero', 'goodsameopp' );
      return $qsql;
   }

   /*!
    * \brief Returns restrictions for joining waiting-room entry and if offer is joinable or not.
    * \param $row waiting-room row loaded by query built from build_waiting_room_query().
    * \param $html false = no HTML-entities in restrictions
    * \return array( restrictions|NO_VALUE, joinable=true|false ); NO_VALUE = no restrictions found
    */
   public static function get_waitingroom_restrictions( $row, $suitable, $html=true )
   {
      $restrictions = echo_game_restrictions( $row['MustBeRated'], $row['RatingMin'], $row['RatingMax'],
            $row['MinRatedGames'], $row['MinHeroRatio'], $row['goodmaxgames'], $row['SameOpponent'],
            ( !$suitable && $row['CH_hidden'] ), $row['goodrated'], $row['haverating'],
            /*short*/true, $html );
      $joinable = ( $row['goodrated'] && $row['haverating'] && $row['goodrating'] && $row['goodmingames']
         && $row['goodhero'] && $row['goodmaxgames'] && $row['goodsameopp'] && !$row['C_denied'] );
      return array( $restrictions, $joinable );
   }//get_waitingroom_restrictions

   /*! \brief Joins waiting-room game. */
   public static function join_waitingroom_game( $wr_id )
   {
      global $player_row, $NOW;

      $my_id = (int)@$player_row['ID'];
      if ( $my_id <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', "WC:join_waitingroom_game($wr_id)");

      if ( !is_numeric($wr_id) || $wr_id <= 0 )
         error('waitingroom_game_not_found', "WC:join_waitingroom_game.bad_id($wr_id)");

      $maxGamesCheck = new MaxGamesCheck();
      if ( !$maxGamesCheck->allow_game_start() )
         error('max_games', "WC:join_waitingroom_game.max_games($wr_id,{$maxGamesCheck->count_games})");

      $my_rated_games = (int)$player_row['RatedGames'];
      $my_hero_ratio_perc = 100 * User::calculate_hero_ratio( $player_row['GamesWeaker'], $player_row['Finished'],
         $player_row['Rating2'], $player_row['RatingStatus'] );

      $sql_goodmingames = "IF(W.MinRatedGames>0,($my_rated_games >= W.MinRatedGames),1)";
      $sql_goodmaxgames = ( MaxGamesCheck::is_limited() ) // Opponent max-games
         ? "IF(P.Running + P.GamesMPG < ".MAX_GAMESRUN.",1,0)" : 1;

      $sql_goodsameopp =
         "CASE WHEN (W.uid=$my_id OR W.SameOpponent=0 OR (W.SameOpponent > ".SAMEOPP_TOTAL." AND ISNULL(WRJ.wroom_id))) THEN 1 " .
              "WHEN (W.SameOpponent < ".SAMEOPP_TOTAL.") THEN (COALESCE(GS.Running,0) < -W.SameOpponent + ".SAMEOPP_TOTAL." ) " . // total-times-check
              "WHEN (W.SameOpponent<0) THEN (WRJ.JoinedCount < -W.SameOpponent) " . // same-offer-times-check
              "ELSE (WRJ.ExpireDate <= FROM_UNIXTIME($NOW)) " . // same-offer-date-check
              "END";

      $sql_goodhero = "($my_hero_ratio_perc >= W.MinHeroRatio)";

      $query= "SELECT W.*"
            . ',IF(ISNULL(C.uid),0,C.SystemFlags & '.CSYSFLAG_WAITINGROOM.') AS C_denied'
            . ',IF(ISNULL(WRJ.opp_id),0,1) AS X_wrj_exists'
            . ',WRJ.JoinedCount'
            . ",$sql_goodmingames AS goodmingames"
            . ",$sql_goodmaxgames AS goodmaxgames"
            . ",$sql_goodsameopp AS goodsameopp"
            . ",$sql_goodhero AS goodhero"
            . ",(P.Running + P.GamesMPG) AS X_OppGamesCount"
            . " FROM Waitingroom AS W"
               . " LEFT JOIN Players AS P ON P.ID=W.uid"
               . " LEFT JOIN Contacts AS C ON C.uid=W.uid AND C.cid=$my_id"
               . " LEFT JOIN WaitingroomJoined AS WRJ ON WRJ.opp_id=$my_id AND WRJ.wroom_id=W.ID"
               . " LEFT JOIN GameStats AS GS ON GS.uid=IF($my_id<W.uid,$my_id,W.uid) AND GS.oid=IF($my_id<W.uid,W.uid,$my_id)"
            . " WHERE W.ID=$wr_id AND W.nrGames>0"
            . " HAVING C_denied=0";
      $game_row = mysql_single_fetch( "WC:join_waitingroom_game.find_game($wr_id,$my_id)", $query);
      if ( !$game_row )
         error('waitingroom_game_not_found', "WC:join_waitingroom_game.find_game2($wr_id,$my_id)");
      $game_settings = GameSettings::get_game_settings_from_gamerow( $game_row, /*def*/false );

      $opponent_ID = $game_row['uid'];
      $gid = (int)@$game_row['gid'];
      $handicaptype = $game_row['Handicaptype'];
      $category_handicaptype = get_category_handicaptype($handicaptype);


      //else... joining game

      $opponent_row = mysql_single_fetch('WC:join_waitingroom_game.find_players',
            "SELECT ID, Name, Handle, Rating2, RatingStatus, ClockUsed, OnVacation " .
            "FROM Players WHERE ID=$opponent_ID LIMIT 1" );
      if ( !$opponent_row )
         error('waitingroom_game_not_found', "WC:join_waitingroom_game.find_players.opp($wr_id,$opponent_ID)");
      if ( $my_id == $opponent_ID )
         error('waitingroom_own_game', "WC:join_waitingroom_game.check.opp($wr_id)");

      if ( $game_row['MustBeRated'] == 'Y' &&
          !($player_row['Rating2'] >= $game_row['RatingMin'] && $player_row['Rating2'] < $game_row['RatingMax']) )
         error('waitingroom_not_in_rating_range', "WC:join_waitingroom_game.check.rating($wr_id)");

      if ( !$game_row['goodmingames'] )
         error('waitingroom_not_enough_rated_fin_games',
            "WC:join_waitingroom_game.min_rated_fin_games($gid,$my_id,{$game_row['MinRatedGames']})");

      if ( !$game_row['goodhero'] )
         error('waitingroom_not_in_hero_range',
            "WC:join_waitingroom_game.check.hero_ratio($wr_id,$my_hero_ratio_perc%,{$game_row['MinHeroRatio']}%)");

      if ( !$game_row['goodmaxgames'] )
         error('max_games_opp', "WC:join_waitingroom_game.opp_max_games($gid,$my_id,{$game_row['X_OppGamesCount']})");

      if ( !$game_row['goodsameopp'] )
         error('waitingroom_not_same_opponent',
            "WC:join_waitingroom_game.same_opponent($gid,$my_id,{$game_row['SameOpponent']})");

      if ( $game_row['GameType'] != GAMETYPE_GO ) // user can join mp-game only once
      {
         if ( GamePlayer::exists_game_player($gid, $my_id) )
            error('waitingroom_not_same_opponent', "WC:join_waitingroom_game.mpg_same_opponent($gid,$my_id)");
      }

      $size = limit( $game_row['Size'], MIN_BOARD_SIZE, MAX_BOARD_SIZE, 19 );

      $my_rating = $player_row['Rating2'];
      $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
      $opprating = $opponent_row['Rating2'];
      $opprated = ( $opponent_row['RatingStatus'] != RATING_NONE && is_numeric($opprating) && $opprating >= MIN_RATING );

      $double = false;
      switch ( (string)$handicaptype )
      {
         case HTYPE_CONV:
            if ( !$iamrated || !$opprated )
               error('no_initial_rating', "WC:join_waitingroom_game.conv($wr_id)");
            list( $game_row['Handicap'], $game_row['Komi'], $i_am_black, $is_nigiri ) =
               $game_settings->suggest_conventional( $my_rating, $opprating );
            break;

         case HTYPE_PROPER:
            if ( !$iamrated || !$opprated )
               error('no_initial_rating', "WC:join_waitingroom_game.proper($wr_id)");
            list( $game_row['Handicap'], $game_row['Komi'], $i_am_black, $is_nigiri ) =
               $game_settings->suggest_proper( $my_rating, $opprating );
            break;

         case HTYPE_DOUBLE:
            $double = true;
            $i_am_black = true;
            break;

         case HTYPE_BLACK:
            $i_am_black = false; // game-offerer wants BLACK, so challenger gets WHITE
            break;

         case HTYPE_WHITE:
            $i_am_black = true; // game-offerer wants WHITE, so challenger gets BLACK
            break;

         case HTYPE_ALTERNATE: // only for tournaments
            error('internal_error', "WC:join_waitingroom_game.bad.htype($handicaptype)");
            break;

         case HTYPE_AUCTION_SECRET:
         case HTYPE_AUCTION_OPEN:
         case HTYPE_I_KOMI_YOU_COLOR:
            $i_am_black = false; // waiting-room-OFFERER is black for fair-komi (giving komi first)
            break;
         case HTYPE_YOU_KOMI_I_COLOR:
            $i_am_black = true; // waiting-room-JOINER is black for fair-komi (give komi first)
            break;

         default: //always available even if waiting room or unrated
            $game_row['Handicaptype'] = $handicaptype = HTYPE_NIGIRI;
            $game_row['Handicap'] = 0;
         case HTYPE_NIGIRI:
            mt_srand((double) microtime() * 1000000);
            $i_am_black = mt_rand(0,1);
            break;
      }//handicaptype

      $game_setup = GameSetup::new_from_waitingroom_game_row( $game_row );
      $game_setup->read_waitingroom_fields( $game_row );
      if ( $category_handicaptype == CAT_HTYPE_FAIR_KOMI )
         $game_setup->Komi = $game_setup->OppKomi = null; // start with empty komi-bids

      ta_begin();
      {//HOT-section to join waiting-room game-offer
         $gids = array();
         $is_std_go = ( $game_row['GameType'] == GAMETYPE_GO );
         if ( $is_std_go )
         {
            if ( $i_am_black || $double )
               $gids[] = create_game($player_row, $opponent_row, $game_row, $game_setup);
            else
               $gids[] = create_game($opponent_row, $player_row, $game_row, $game_setup);
            $gid = $gids[0];

            //keep this after the regular one ($gid => consistency with send_message)
            if ( $double )
            {
               // provide a link between the two paired "double" games
               $game_row['double_gid'] = $gid;
               $double_gid2 = create_game($opponent_row, $player_row, $game_row, $game_setup);
               $gids[] = $double_gid2;

               db_query( "WC:join_waitingroom_game.update_double2($gid)",
                  "UPDATE Games SET DoubleGame_ID=$double_gid2 WHERE ID=$gid LIMIT 1" );
            }
         }
         else // join multi-player-game
         {
            $gid = $game_row['gid']; // use existing game for Team-/Zen-Go
            if ( $gid <= 0 )
               error('internal_error', "WC:join_waitingroom_game.join_game.check.gid($wr_id,$gid,$my_id)");

            MultiPlayerGame::join_waitingroom_mp_game( "WC:join_waitingroom_game.join_game($wr_id)", $gid, $my_id );
            $gids[] = $gid;
         }

         if ( $is_std_go )
         {
            GameHelper::update_players_start_game( 'WC:join_waitingroom_game',
               $my_id, $opponent_ID, count($gids), ($game_row['Rated'] == 'Y') );
         }


         // Reduce number of games left in the waiting room

         if ( $game_row['nrGames'] > 1 )
         {
            db_query( 'WC:join_waitingroom_game.reduce',
               "UPDATE Waitingroom SET nrGames=nrGames-1 WHERE ID=$wr_id AND nrGames>0 LIMIT 1" );
         }
         else
         {
            db_query( 'WC:join_waitingroom_game.reduce_delete',
               "DELETE FROM Waitingroom WHERE ID=$wr_id LIMIT 1" );
         }

         // Update WaitingroomJoined
         // NOTE: restriction on count and time are mutual exclusive

         $same_opp = $game_row['SameOpponent'];
         $query_so = '';
         if ( $same_opp < 0 ) // restriction on count
         {
            if ( $game_row['X_wrj_exists'] )
               $query_so = 'UPDATE WaitingroomJoined SET JoinedCount=JoinedCount+1 '
                  . "WHERE opp_id=$my_id AND wroom_id=$wr_id LIMIT 1";
            else
               $query_so = 'INSERT INTO WaitingroomJoined '
                  . "SET opp_id=$my_id, wroom_id=$wr_id, JoinedCount=1";
         }
         elseif ( $same_opp > 0 ) // restriction on time
         {
            $expire_date = $NOW + $same_opp * SECS_PER_DAY;
            $query_wrjexp = "WaitingroomJoined SET ExpireDate=FROM_UNIXTIME($expire_date)";
            if ( $game_row['X_wrj_exists'] ) // faster than REPLACE-INTO
               $query_so = 'UPDATE ' . $query_wrjexp . "WHERE opp_id=$my_id AND wroom_id=$wr_id LIMIT 1";
            else
               $query_so = 'INSERT INTO ' . $query_wrjexp . ", opp_id=$my_id, wroom_id=$wr_id";
         }
         if ( $query_so )
            db_query( "WC:join_waitingroom_game.wroom_joined.save(u$my_id,wr$wr_id)", $query_so );


         // Send message to notify opponent

         $subject = 'Your waiting room game has been joined.'; // maxlen=80
         $message = ( empty($game_row['Comment']) ) ? '' : "Comment: {$game_row['Comment']}\n\n";
         $message .= sprintf( "%s has joined your waiting room game.\n",
            user_reference( REF_LINK, 1, '', $player_row) );
         $message .= sprintf( "\nGames of type [%s]:\n",
            GameTexts::get_game_type($game_row['GameType']) );
         foreach ( $gids as $gid )
            $message .= "* <game $gid>\n";

         send_message( 'WC:join_waitingroom_game', $message, $subject
            , $opponent_ID, '', /*notify*/true
            , 0, MSGTYPE_NORMAL );
      }
      ta_end();
   }//join_waitingroom_game

   /*!
    * \brief Deletes waiting-room game.
    * \return $gid if it was multi-player-game; 0 otherwise
    */
   public static function delete_waitingroom_game( $wr_id )
   {
      global $player_row;
      $my_id = (int)@$player_row['ID'];

      $game_row = mysql_single_fetch( "WC:delete_waitingroom_game.find_game($wr_id,$my_id)",
            "SELECT uid, gid, nrGames FROM Waitingroom WHERE ID=$wr_id LIMIT 1" );
      if ( !$game_row )
         error('waitingroom_game_not_found', "WC:delete_waitingroom_game.find_game2($wr_id,$my_id)");

      $uid = $game_row['uid'];
      if ( $my_id != $uid )
         error('waitingroom_delete_not_own', "WC:delete_waitingroom_game.check.user($wr_id,$uid)");
      $gid = $game_row['gid'];

      ta_begin();
      {//HOT-section to delete waiting-room offer
         db_query( "WC:delete_waitingroom_game.delete($wr_id,$gid)",
            "DELETE FROM Waitingroom WHERE ID=$wr_id LIMIT 1" );

         if ( $gid )
            MultiPlayerGame::revoke_offer_game_players( $gid, $game_row['nrGames'], GPFLAG_WAITINGROOM );
      }
      ta_end();

      return $gid;
   }//delete_waitingroom_game

} // end of 'WaitingroomControl'



 /*!
  * \class WaitingroomOffer
  *
  * \brief Container-Class to handle single waitingroom-offer.
  */
class WaitingroomOffer
{
   private $row;
   private $CategoryHanditype;
   public $mp_player_count;
   private $iamrated;

   public $resultType; // 1=calculated, 2=fix, 3=mpg (see (3f) in quick-specs)
   public $resultColor; // '' | double | mpg | fairkomi | nigiri | black | white (see quick-specs (3f))used for quick-suite)
   public $resultHandicap;
   public $resultKomi;

   public function __construct( $row )
   {
      $this->row = $row;
      $this->CategoryHanditype = get_category_handicaptype($row['Handicaptype']);
      $this->mp_player_count = ($this->row['GameType'] == GAMETYPE_GO)
         ? 0
         : MultiPlayerGame::determine_player_count($this->row['GamePlayers']);
      $this->iamrated = user_has_rating();
   }

   public function is_fairkomi()
   {
      return ( $this->CategoryHanditype == CAT_HTYPE_FAIR_KOMI );
   }

   public function is_my_game()
   {
      global $player_row;
      return ( $this->row['uid'] == $player_row['ID'] );
   }

   // calculate game-settings for waiting-room or quick-suite
   public function calculate_offer_settings()
   {
      global $player_row;

      $is_my_game = $this->is_my_game();
      $my_rating = $player_row['Rating2'];

      $handitype = $this->row['Handicaptype'];
      $game_type = $this->row['GameType'];
      $is_fairkomi = $this->is_fairkomi();

      // probable game-settings without adjustment
      $infoHandi = $this->row['Handicap'];
      $infoKomi = $this->resultKomi = $this->row['Komi'];
      $iamblack = '';
      $info_nigiri = false;
      if ( $this->iamrated && !$is_my_game && !$is_fairkomi ) // conv/proper/manual
      {
         if ( user_has_rating($this->row, 'WRP_') ) // other has rating
         {
            $game_settings = GameSettings::get_game_settings_from_gamerow( $this->row, /*def*/false );
            if ( $handitype == HTYPE_CONV )
            {
               list( $infoHandi, $infoKomi, $iamblack, $info_nigiri ) =
                  $game_settings->suggest_conventional( $my_rating, $this->row['WRP_Rating2'] );
            }
            elseif ( $handitype == HTYPE_PROPER )
            {
               list( $infoHandi, $infoKomi, $iamblack, $info_nigiri ) =
                  $game_settings->suggest_proper( $my_rating, $this->row['WRP_Rating2'] );
            }
         }
      }

      if ( $is_my_game )
         $this->resultType = 0;
      elseif ( $game_type != GAMETYPE_GO ) // MPG
         $this->resultType = 3;
      elseif ( (string)$iamblack != '' ) // probable setting
         $this->resultType = 1;
      else // fix-calculated
         $this->resultType = 2;

      $colstr = $this->determine_color(
         $game_type, $handitype, $this->CategoryHanditype, $is_my_game, $iamblack,
         $info_nigiri, $player_row['Handle'], $this->row['WRP_Handle'] );

      $settings_str = '';
      if ( !$is_my_game && $game_type == GAMETYPE_GO && !$is_fairkomi )
      {
         $this->resultHandicap = $infoHandi;
         $this->resultKomi = $infoKomi;
         $settings_str = ($this->resultHandicap > 0)
            ? sprintf( T_('%s H%s K%s#wrsettings'), $colstr, (int)$this->resultHandicap, $this->resultKomi )
            : sprintf( T_('%s Even K%s#wrsettings'), $colstr, $this->resultKomi );
      }
      elseif ( $is_fairkomi )
      {
         $this->resultHandicap = 0;
         $settings_str = $colstr . ' ' . T_('Negotiate#fairkomi_wrsettings');
      }
      elseif ( $game_type != GAMETYPE_GO ) // MPG
      {
         $this->resultHandicap = $this->resultKomi = 0;
         $icon_text = T_('Multi-Player-Game') . ': '
            . sprintf( T_('%s free slot(s) of %s players#mpg'), $this->row['nrGames'], $this->mp_player_count )
            . '; ' . T_('Show game-players');
         $settings_str = $colstr . MINI_SPACING . echo_image_game_players( $this->row['gid'], $icon_text )
            . MINI_SPACING . sprintf( '(%s/%s)', $this->row['nrGames'], $this->mp_player_count);
      }
      elseif ( $is_my_game && $colstr )
      {
         $settings_str = ($infoHandi > 0)
            ? sprintf( T_('%s H%s K%s#wrsettings'), $colstr, (int)$infoHandi, $infoKomi )
            : sprintf( T_('%s Even K%s#wrsettings'), $colstr, $infoKomi );
      }

      if ( ENABLE_STDHANDICAP && ($this->row['StdHandicap'] !== 'Y') && !$is_fairkomi )
         $settings_str .= ($settings_str ? ' ' : '') . T_('(Free Handicap)#handicap_tablewr');

      return $settings_str;
   }//calculate_offer_settings

   // \internal
   private function determine_color( $game_type, $Handicaptype, $CategoryHanditype, $is_my_game, $iamblack,
         $is_nigiri, $my_handle, $opp_handle )
   {
      global $base_path;

      if ( $game_type != GAMETYPE_GO ) //MPG
      {
         $this->resultColor = 'mpg';
         $colstr = image( $base_path.'17/y.gif', T_('Manual#color'), T_('Color set by game-master for multi-player-game#color') );
      }
      elseif ( $Handicaptype == HTYPE_NIGIRI || $is_nigiri )
      {
         $this->resultColor = 'nigiri';
         $colstr = image( $base_path.'17/y.gif', T_('Nigiri#color'), T_('Nigiri (You randomly play Black or White)#color') );
      }
      elseif ( $Handicaptype == HTYPE_DOUBLE )
      {
         $this->resultColor = 'double';
         $colstr = image( $base_path.'17/w_b.gif', T_('B+W#color'), T_('You play Black and White#color') );
      }
      elseif ( $Handicaptype == HTYPE_BLACK )
      {
         $this->resultColor = ( $is_my_game ) ? 'black' : 'white';
         if ( $is_my_game )
            $colstr = image( $base_path.'17/b.gif', T_('B#color'), T_('I play Black#color') );
         else
            $colstr = image( $base_path.'17/w.gif', T_('W#color'), T_('You play White#color') );
      }
      elseif ( $Handicaptype == HTYPE_WHITE )
      {
         $this->resultColor = ( $is_my_game ) ? 'white' : 'black';
         if ( $is_my_game )
            $colstr = image( $base_path.'17/w.gif', T_('W#color'), T_('I play White#color') );
         else
            $colstr = image( $base_path.'17/b.gif', T_('B#color'), T_('You play Black#color') );
      }
      elseif ( $CategoryHanditype == CAT_HTYPE_FAIR_KOMI )
      {
         $this->resultColor = 'fairkomi';
         $col_note = ( $is_my_game )
            ? GameTexts::get_fair_komi_types( $Handicaptype, NULL, $my_handle, /*opp*/NULL )
            : GameTexts::get_fair_komi_types( $Handicaptype, NULL, $opp_handle, $my_handle );
         $colstr = image( $base_path.'17/y.gif', $col_note, NULL );
      }
      elseif ( (string)$iamblack != '' ) // $iamrated && !$is_my_game && HTYPE_CONV/PROPER
      {
         $this->resultColor = ($iamblack) ? 'black' : 'white';
         if ( $iamblack )
            $colstr = image( $base_path.'17/b.gif', T_('B#color'), T_('You probably play Black#color') );
         else
            $colstr = image( $base_path.'17/w.gif', T_('W#color'), T_('You probably play White#color') );
      }
      else // HTYPE_CONV/PROPER (unrated|my-game-offer) or otherwise calculated
      {
         $this->resultColor = '';
         $colstr = '';
      }

      if ( $colstr && $Handicaptype != HTYPE_DOUBLE )
         $colstr = insert_width(5) . $colstr;

      return $colstr;
   }//determine_color

   public function check_joining_waitingroom( $html )
   {
      global $player_row;
      $my_id = $player_row['ID'];

      $html_out = $join_warning = '';
      $join_errors = array();

      if ( $this->row['GameType'] != GAMETYPE_GO ) // user can join mp-game only once
         $can_join_mpg = !GamePlayer::exists_game_player( $this->row['gid'], $my_id );
      else
         $can_join_mpg = true;

      $maxGamesCheck = new MaxGamesCheck();
      $can_join_maxg = $maxGamesCheck->allow_game_start(); //own MAX-games

      $can_join = $can_join_mpg && $can_join_maxg;
      if ( !$this->is_my_game() )
      {
         $html_out .= "<br>\n";

         if ( $can_join )
         {
            if ( $html )
               $html_out .= $maxGamesCheck->get_warn_text();
            else
               $join_warning = $maxGamesCheck->get_warn_text(/*html*/false);
         }
         elseif ( !$can_join_maxg )
         {
            if ( $html )
               $html_out .= $maxGamesCheck->get_error_text() . "<br>\n" . $maxGamesCheck->get_warn_text();
            else
            {
               $join_errors[] = $maxGamesCheck->get_error_text(/*html*/false);
               $join_errors[] = $maxGamesCheck->get_warn_text(/*html*/false);
            }
         }
         elseif ( !$can_join_mpg )
         {
            $err_text = T_('Already invited to or joined this multi-player-game!');
            if ( $html )
               $html_out .= span('MPGWarning', $err_text );
            else
               $join_errors[] = $err_text;
         }

         if ( $can_join_mpg && !$this->row['goodmaxgames'] )
         {
            $err_text = ErrorCode::get_error_text('max_games_opp');
            if ( $html )
               $html_out .= "<br>\n" . span('ErrMsgMaxGames', $err_text);
            else
               $join_errors[] = $err_text;
         }
      }

      return array( $can_join, $html_out, $join_warning, implode(' / ', $join_errors) );
   }//check_joining_waitingroom


   // ------------ static functions ----------------------------

} // end of 'WaitingroomOffer'

?>

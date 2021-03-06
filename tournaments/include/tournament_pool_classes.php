<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Tournament";

require_once 'include/classlib_userconfig.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/std_classes.php';
require_once 'include/form_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_utils.php';


 /*!
  * \file tournament_pool_classes.php
  *
  * \brief helper-classes for viewing round-robin tournament-pools with results
  */


 /*!
  * \class PoolGame
  *
  * \brief Container for game within pool between two players
  */
class PoolGame
{
   public $GameNo; // 1..<games-per-challenge>
   public $Challenger_uid; // smaller than Defender_uid
   public $Defender_uid;
   public $gid;
   public $Score; // score for challenger
   public $Flags; // TournamentGame.Flags

   public function __construct( $game_no, $ch_uid=0, $df_uid=0, $gid=0, $score=null, $flags=0 )
   {
      $this->GameNo = ($game_no) ? (int)$game_no : 1;

      $new_score = (is_null($score)) ? null : (float)$score;
      if ( $ch_uid < $df_uid )
      {
         $this->Challenger_uid = (int)$ch_uid;
         $this->Defender_uid = (int)$df_uid;
         $this->Score = $new_score;
      }
      else
      {
         $this->Challenger_uid = (int)$df_uid;
         $this->Defender_uid = (int)$ch_uid;
         $this->Score = (is_null($new_score)) ? null : -$new_score;
      }
      $this->gid = (int)$gid;
      $this->Flags = (int)$flags;
   }//__construct

   public function get_opponent( $uid )
   {
      return ($this->Challenger_uid == $uid) ? $this->Defender_uid : $this->Challenger_uid;
   }

   public function get_score( $uid )
   {
      if ( is_null($this->Score) )
         return null;
      else
         return ($this->Challenger_uid == $uid) ? $this->Score : -$this->Score;
   }

   /*! \brief Returns arr( user-score, points, cell-marker, title, style ) for given user-id. */
   public function calc_result( $uid, $tpoints )
   {
      if ( is_null($this->Score) )
      {
         $chk_score = null;
         $points = 0;
         $mark = '#'; // running game
         $title = T_('Game running');
         $style = null;
      }
      else
      {
         $chk_score = $this->get_score($uid);
         $points = $tpoints->calculate_points( $chk_score, $this->Flags );
         $mark = $points;

         if ( $this->Flags & TG_FLAG_GAME_DETACHED ) // annulled (=detached) game has no effect on tournament
         {
            $title = T_('Game annulled#tourney');
            $style = 'MatrixAnnulled';
         }
         elseif ( $chk_score < 0 ) // won
         {
            $title = sprintf( T_('Game won by [%s]'), self::get_score_text($chk_score) );
            $style = 'MatrixWon';
         }
         elseif ( $chk_score > 0 ) // lost
         {
            $title = sprintf( T_('Game lost by [%s]'), self::get_score_text($chk_score) );
            $style = 'MatrixLost';
         }
         else //=0 draw
         {
            if ( $this->Flags & TG_FLAG_GAME_NO_RESULT ) // game-end NO-RESULT
            {
               $title = T_('Game No-Result (Void)');
               $style = 'MatrixNoResult';
            }
            else
            {
               $title = T_('Game draw (Jigo)');
               $style = 'MatrixDraw';
            }
         }
         if ( abs($chk_score) == SCORE_FORFEIT )
            $style .= ' MatrixForfeit';
      }

      return array( $chk_score, $points, $mark, $title, $style );
   }//calc_result


   // ------------ static functions ----------------------------

   private static function get_score_text( $score )
   {
      $score = abs($score);
      if ( $score == SCORE_RESIGN )
         return T_('Resignation');
      elseif ( $score == SCORE_TIME )
         return T_('Timeout');
      elseif ( $score == SCORE_FORFEIT )
         return T_('Forfeit');
      else
         return abs($score);
   }

} // end of 'PoolGame'




 /*!
  * \class PoolRankCalculator
  *
  * \brief Static helper-class to calculate rank of pool-users applying tie-breakers if needed.
  */
class PoolRankCalculator
{
   // ------------ static functions ----------------------------

   /*!
    * \brief Returns (static) Tie-Breaker for given level (starting with 0).
    * \param $level null= return count of tie-breakers, otherwise TIEBREAKER_... according to level
    *
    * \internal
    */
   private static function get_tiebreaker( $level )
   {
      // index = order level: => tie-breaker
      static $map = array( TIEBREAKER_POINTS, TIEBREAKER_SODOS );
      if ( is_null($level) )
         return count($map);
      else
         return (isset($map[$level])) ? $map[$level] : /*unknown*/0;
   }

   /*!
    * \brief Calculates rank of given users from stored data and apply tie-breakers if needed.
    * \param $arr_tpools [ uid => TPool, ... ]: array-reference with TournamentPool-data needed
    *        to calculate rank for each users; TPool->CalcRank will be filled with rank (starting at 1);
    *        requires properly initialized TPool-fields: Points, Wins, SODOS, poolGames
    * \param $users users to calculate ranks applying tie-breakers given by order of
    *        get_tiebreaker(level)-func
    *
    * \note Just sorting by Points/Wins/SODOS could also be done with a simple sort-function,
    *       but (later) tie-breakers like iterative Direct-Comparison are more complex.
    *       Also a sort-function only sorts, but don't SET ranks (the tie-breaking would only
    *       happen between two elements being compared, which is insufficient for some tie-breakers).
    */
   public static function calc_ranks( &$arr_tpools, $users )
   {
      $tie_level = 0;
      $user_ranks = self::build_user_ranks( $arr_tpools, $users, self::get_tiebreaker($tie_level) );

      // Note on algorithm:
      // - if more tie-breakers must be applied, identify sub-groups with same-rank
      //   and apply tie-breaking on those recursively (without recursion)
      $arr_stop_tiebreak = array(); // [ uid => 1, ...]: no tie-breaking needed if set for uid
      $max_tiebreaker = self::get_tiebreaker(null) - 1;
      for ( $tie_level++; $tie_level <= $max_tiebreaker; $tie_level++ )
      {
         if ( count($arr_stop_tiebreak) == count($users) ) // no more subgroups without tie-break-stop
            break;
         $same_ranks = self::build_same_ranks_groups( $user_ranks );
         if ( is_null($same_ranks) ) // completely ordered = all ranks appear only once
            break;

         $next_rank = 0; // to "shift" lower ranks down after determining in-between ranks with tie-breakers
         // NOTE: to make the "shifting" work, $same_ranks must be ordered(!) with highest rank (0) as 1st item
         foreach ( $same_ranks as $arr_users ) // tie-break subgroups with same rank
         {
            if ( count($arr_users) <= 1 ) // subgroup with only 1 member
            {
               foreach ( $arr_users as $uid => $rank )
               {
                  $user_ranks[$uid] = $next_rank++;
                  $arr_stop_tiebreak[$uid] = 1;
               }
            }
            else // subgroup with >1 members
            {
               // set ranks (starting with 0) for subgroup of users applying tie-breaker
               $groupuser_ranks = self::build_user_ranks( $arr_tpools,
                  array_keys($arr_users), self::get_tiebreaker($tie_level) );
               $group_rank_counts = array_count_values($groupuser_ranks);
               if ( count($group_rank_counts) > 1 ) // tie-breaking successful (complete or partly)
               {
                  // determine "skipped" ranks for (tied) sub-groups, e.g. (1,1,3) to avoid (1,1,2)
                  $assign_rank = array();
                  foreach ( $group_rank_counts as $sub_rank => $sub_rank_count )
                  {
                     $assign_rank[$sub_rank] = $next_rank;
                     $next_rank += $sub_rank_count;
                  }

                  // set new ranks in main-result
                  foreach ( $groupuser_ranks as $uid => $rank_new )
                  {
                     $user_ranks[$uid] = $assign_rank[$rank_new];
                     if ( $group_rank_counts[$rank_new] <= 1 ) // if >1 tie-breaking was only partly successful
                        $arr_stop_tiebreak[$uid] = 1;
                  }
               }
               else // tie-breaking failed with current tie-breaker (still a draw with same value for all sub-group-users)
               {
                  foreach ( $groupuser_ranks as $uid => $rank_new )
                     $user_ranks[$uid] = $next_rank;
                  $next_rank += count($groupuser_ranks);
               }
            }
         }//loop same-rank-user-subgroups
      }//loop tie-breakers

      // copy final ranks into TPool->CalcRank for given users
      foreach ( $user_ranks as $uid => $rank )
         $arr_tpools[$uid]->CalcRank = $rank + 1;
   }//calc_ranks

   /*!
    * \brief Returns all user-subgroups with same-rank value;
    *        used to identify groups of users that need a tie-break.
    * \param $users expect users-array to be sorted by value: [ uid => rank, ... ]
    * \return null if no ties (=same rank) found;
    *         otherwise return array of sub-array( uid => rank, ... ) all with same rank
    *
    * \note Example: [ u1=>1, u2=>2, u3=>2, u4=>3, u5=>3 ]
    *             -> [ [u1=>1], [u2=>2, u3=>2], [u4=>3, u5=>3] ]
    *
    * \internal
    */
   private static function build_same_ranks_groups( $users )
   {
      // find ties = rank-values appearing several times
      $arr_valcount = array_count_values( $users ); // [ rank => count ], e.g. 1 => #1, 2 => #2, 3 => #2
      if ( count($arr_valcount) == count($users) )
         return null;

      $result = array();
      foreach ( $arr_valcount as $pivot_rank => $cnt )
         $result[] = array_intersect( $users, array($pivot_rank) );
      return $result;
   }//build_same_ranks_groups

   /*!
    * \brief Assigns rank to given $users according to specified "tie-breaker value-base".
    * \param $arr_tpools [ uid => TPool, ... ]: array mapping uid in $users to TournamentPool-object
    *        containing the required data to determine rank for users
    * \param $users [ uid, ... ]: list of users expected to have the same rank
    *        (or initially having no rank, which is also "having the same rank")
    * \param $tie_breaker determine ranks by using this given tie-breaker (TIEBREAKER_...)
    * \return [ uid => rank, ... ]: array mapping user ($uid) to rank (starting at 0)
    *         ordered by ascending rank
    *
    * \internal
    */
   private static function build_user_ranks( $arr_tpools, $users, $tie_breaker )
   {
      $arr = array();
      $result = array();

      switch ( (int)$tie_breaker )
      {
         case TIEBREAKER_POINTS:
         {
            // build mapping (unique value -> to rank)
            foreach ( $users as $uid )
               $arr[] = (int) ( 2 * $arr_tpools[$uid]->Points ); // avoid float-keys
            $arr = self::build_value_rank_map( $arr, true );

            // assign rank to users according to mapped-values
            foreach ( $users as $uid )
               $result[$uid] = $arr[(int)( 2 * $arr_tpools[$uid]->Points )];
            break;
         }
         case TIEBREAKER_SODOS:
         {
            foreach ( $users as $uid )
               $arr[] = (int) ( 2 * $arr_tpools[$uid]->SODOS ); // avoid float-keys
            $arr = self::build_value_rank_map( $arr, true );

            foreach ( $users as $uid )
               $result[$uid] = $arr[(int)( 2 * $arr_tpools[$uid]->SODOS )];
            break;
         }
         case TIEBREAKER_WINS:
         {
            foreach ( $users as $uid )
               $arr[] = $arr_tpools[$uid]->Wins;
            $arr = self::build_value_rank_map( $arr, true );

            foreach ( $users as $uid )
               $result[$uid] = $arr[$arr_tpools[$uid]->Wins];
            break;
         }
         default: // no tie-breaking
         {
            foreach ( $users as $uid )
               $result[$uid] = 0;
            break;
         }
      }

      asort( $result, SORT_NUMERIC ); // sort by rank
      return $result;
   }//build_user_ranks

   /*!
    * \brief Returns array with mapping of (unique) value to according rank.
    * \param $reverse if true, highest-value (instead of lowest value) starts with rank ascending from 0
    * \return [ value => rank, ... ]: rank starting at 0
    *
    * \internal
    */
   private static function build_value_rank_map( $arr, $reverse )
   {
      // uniq before sort, because default sort-flag SORT_STRING for PHP >=5.2.10
      // (but even if applied on numbers, arr is unique afterwards)
      $arr = array_unique($arr);

      if ( $reverse )
         rsort( $arr, SORT_NUMERIC );
      else
         sort( $arr, SORT_NUMERIC );

      $rank = 0;
      $rank_map = array();
      foreach ( $arr as $val )
         $rank_map[$val] = $rank++;
      return $rank_map;
   }

} // end of 'PoolRankCalculator'




 /*!
  * \class PoolTables
  *
  * \brief Container with all the pool users, games and results for one tournament-round
  */
class PoolTables
{
   /*! \brief list of pool-users: map( uid => TournamentPool with ( User, PoolGame-list, Rank(later) ), ... ) */
   public $users = array();
   /*!
    * \brief list of pool-numbers with list of result-ordered(!) pool-users: map( tierPoolKey => [ uid, ... ] )
    * \note IMPORTANT: must be kept sorted by Tier, Pool
    */
   private $pools = array();
   /*! \brief TournamentPoints-object; mandatory, if fill_games() is used to calculate points/rank of pool. */
   private $tpoints = null;

   public function __construct( $tpool_iterator )
   {
      $this->fill_pools( $tpool_iterator );
   }

   public function get_user_tournament_pool( $uid )
   {
      return @$this->users[$uid];
   }

   /*! \brief returns list of tier-pool-keys. */
   public function get_tier_pool_keys()
   {
      return array_keys($this->pools);
   }

   /*! \brief Returns array( tier-pool-key => arr[ uid's, ... ] ); not-filled pools have empty user-array (if present). */
   public function get_pools()
   {
      return $this->pools;
   }

   /*! \brief Returns non-null array with pool-users. */
   public function get_pool_users( $tier, $pool )
   {
      $tier_pool_key = TournamentUtils::encode_tier_pool_key( $tier, $pool );
      return (isset($this->pools[$tier_pool_key])) ? $this->pools[$tier_pool_key] : array();
   }

   public function count_unassigned_pool_users()
   {
      return count( $this->get_pool_users(1,0) );
   }

   public function get_tournament_points()
   {
      return $this->tpoints;
   }

   /*!
    * \brief Returns array( uid => col-idx, ... ) for given tier & pool.
    * \return col-idx in resulting array starting with 0(!) and not taking $games_factor (games-per-challenge) into account.
    * \note array-key is defined by PoolGame.get_opponent()
    */
   public function get_user_col_map( $tier, $pool, $games_factor )
   {
      $map = array();
      $idx = -1;
      $arr_users = $this->get_pool_users( $tier, $pool );
      foreach ( $arr_users as $uid )
         $map[$uid] = ++$idx;
      return $map;
   }

   private function fill_pools( $tpool_iterator )
   {
      while ( list(,$arr_item) = $tpool_iterator->getListIterator() )
      {
         list( $tpool, $orow ) = $arr_item;
         if ( is_null($tpool) )
            continue;

         $uid = $tpool->uid;
         $tier_pool_key = TournamentUtils::encode_tier_pool_key( $tpool->Tier, $tpool->Pool );

         $this->users[$uid] = $tpool;
         $this->pools[$tier_pool_key][] = $uid;
      }

      ksort($this->pools); // MUST be kept sorted by Tier,Pool
   }//fill_pools

   /*!
    * \brief Reorders users in all pools by TP.ID (needed for TRULE_HANDITYPE_ALTERNATE).
    * IMPORTANT NOTE: only works if TP_ID has been loaded into TournamentPool for users!
    */
   public function reorder_pool_users_by_tp_id()
   {
      foreach ( $this->pools as $tier_pool_key => $arr_users )
         usort( $this->pools[$tier_pool_key], array( $this, '_compare_user_tp_id' ) );
   }//reorder_pool_users

   /*! \internal Comparator-function to sort users of pool by TP.ID. */
   private function _compare_user_tp_id( $a_uid, $b_uid )
   {
      $a_tpool = $this->users[$a_uid];
      $b_tpool = $this->users[$b_uid];

      return cmp_int( $a_tpool->User->urow['TP_ID'], $b_tpool->User->urow['TP_ID'] );
   }//_compare_user_tp_id

   /*!
    * \brief Sets TournamentGames for pool-tables and count games.
    * \note also sets this->tpoints for consecutive pool-viewing & other actions calculating points/rank of pool.
    */
   public function fill_games( $tgames_iterator, $tpoints )
   {
      if ( is_null($tpoints) )
         error('invalid_args', "PoolTables.fill_games.miss_tpoints");
      $this->tpoints = $tpoints;

      $defeated_opps = array(); // uid => [ opp-uid, ... ];  won(opp 2x) or jigo(opp 1x)

      // fix stored PoolGame-list to correct PoolGame.GameNo in correct order
      // NOTE: tgames_iterator could be ordered by TG.ID instead in, but that would do a slow filesort
      //       => so we sort the T-games manually
      $fix_game_no = array(); // ch_uid.df_uid => game-no (= 1..<games-per-challenge>)
      $tgames_iterator->sortListIterator('ID', SORT_NUMERIC); // sort by TG.ID

      while ( list(,$arr_item) = $tgames_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;
         $ch_uid = $tgame->Challenger_uid;
         $df_uid = $tgame->Defender_uid;
         $tpool_ch = $this->users[$ch_uid];
         $tpool_df = $this->users[$df_uid];

         $game_score = ($tgame->isScoreStatus( /*chk-detach*/false )) ? $tgame->Score : null;
         $poolGame = new PoolGame( 1, $ch_uid, $df_uid, $tgame->gid, $game_score, $tgame->Flags );

         // fix PoolGame.GameNo in the same order as TG.ID to keep same position in pools-view
         $fkey = $poolGame->Challenger_uid . '.' . $poolGame->Defender_uid; // smaller uid 1st
         if ( !isset($fix_game_no[$fkey]) )
            $fix_game_no[$fkey] = 1;
         else
            $poolGame->GameNo = ++$fix_game_no[$fkey];

         $tpool_ch->PoolGames[] = $poolGame;
         $tpool_df->PoolGames[] = $poolGame;

         $ch_arr = $poolGame->calc_result( $ch_uid, $tpoints ); // score,points,mark,title,style
         $tpool_ch->Points += $ch_arr[1];
         $ch_score = $ch_arr[0];
         if ( !is_null($ch_score) )
         {
            if ( $ch_score > 0 ) $tpool_ch->Losses++;
            if ( $ch_score < 0 ) $tpool_ch->Wins++;
            if ( $ch_score < 0 ) $defeated_opps[$ch_uid][] = $df_uid;
            if ( $ch_score <= 0 ) $defeated_opps[$ch_uid][] = $df_uid; // win counts double, jigo/void simple
         }

         $df_arr = $poolGame->calc_result( $df_uid, $tpoints );
         $tpool_df->Points += $df_arr[1];
         $df_score = $df_arr[0];
         if ( !is_null($df_score) )
         {
            if ( $df_score > 0 ) $tpool_df->Losses++;
            if ( $df_score < 0 ) $tpool_df->Wins++;
            if ( $df_score < 0 ) $defeated_opps[$df_uid][] = $ch_uid;
            if ( $df_score <= 0 ) $defeated_opps[$df_uid][] = $ch_uid; // win counts double, jigo/void simple
         }
      }//process TGs

      // calc SODOS
      foreach ( $defeated_opps as $uid => $arr_opps )
      {
         $tpool_ch = $this->users[$uid];
         foreach ( $arr_opps as $opp )
         {
            $tpool_df = $this->users[$opp];
            $tpool_ch->SODOS += $tpool_df->Points;
         }
         $tpool_ch->SODOS /= 2;
      }

      // re-order all pool-users per pool (unplayed pools by user-rating)
      foreach ( $this->pools as $tier_pool_key => $tmp )
      {
         // calculate rank for users determined by tie-breakers
         PoolRankCalculator::calc_ranks( $this->users, $this->pools[$tier_pool_key] );

         usort( $this->pools[$tier_pool_key], array( $this, '_compare_user_ranks' ) ); //by TPool-fields.Rank/CalcRank
      }
   }//fill_games

   /*! \internal Comparator-function to sort users of pool by pool-rank. */
   private function _compare_user_ranks( $a_uid, $b_uid )
   {
      $a_tpool = $this->users[$a_uid];
      $b_tpool = $this->users[$b_uid];

      $cmp_rank = cmp_int( $a_tpool->get_cmp_rank(), $b_tpool->get_cmp_rank() );
      if ( $cmp_rank != 0 )
         return $cmp_rank;

      // Tie-Breaker: CalcRank (even if eventually used in cmp_rank implicitly)
      $cmp_calcrank = cmp_int( $a_tpool->CalcRank, $b_tpool->CalcRank );
      if ( $cmp_calcrank != 0 )
         return $cmp_calcrank;

      // NOT a tie-breaker: order on user-rating for meaningful order for same ranked-users in pool-matrix
      $cmp_rating = -cmp_int( round(10*$a_tpool->User->Rating), round(10*$b_tpool->User->Rating) );
      if ( $cmp_rating != 0 )
         return $cmp_rating;
      return cmp_int( $a_uid, $b_uid ); // static ordering of user-uid to assure same order
   }//compare_user_ranks

   /*!
    * Returns arr(
    *    all => count-games,
    *    run => running-games,
    *    finished => finished-games,
    *    jigo => games won by jigo,
    *    void => games ended with no-result (=void),
    *    resign => games won by resignation,
    *    time => games won by timeout,
    *    forfeit => games won by forfeit ).
    */
   public function count_games()
   {
      $count_games = 0;
      $count_run = 0;
      $count_jigo = 0;
      $count_void = 0;
      $count_resign = 0;
      $count_time = 0;
      $count_forfeit = 0;

      $visited = array(); // game-id, because each PoolGame appears twice for challenger+defender
      foreach ( $this->users as $uid => $tpool )
      {
         foreach ( $tpool->PoolGames as $poolGame )
         {
            if ( @$visited[$poolGame->gid] )
               continue;
            $visited[$poolGame->gid] = 1;

            $count_games++;
            if ( is_null($poolGame->Score) )
               $count_run++;
            elseif ( $poolGame->Score == 0 )
            {
               if ( $poolGame->Flags & TG_FLAG_GAME_NO_RESULT )
                  $count_void++;
               else
                  $count_jigo++;
            }
            elseif ( abs($poolGame->Score) == SCORE_RESIGN )
               $count_resign++;
            elseif ( abs($poolGame->Score) == SCORE_TIME )
               $count_time++;
            elseif ( abs($poolGame->Score) == SCORE_FORFEIT )
               $count_forfeit++;
         }
      }

      return array(
         'all'  => $count_games,
         'run'  => $count_run,
         'finished' => $count_games - $count_run,
         'jigo' => $count_jigo,
         'void' => $count_void,
         'resign' => $count_resign,
         'time' => $count_time,
         'forfeit' => $count_forfeit,
      );
   }//count_games

   /*! \brief Counts max. of users pro pool for all pools. */
   public function count_pools_max_user()
   {
      $max_users = 0;
      foreach ( $this->pools as $tier_pool_key => $arr_users )
      {
         list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
         if ( $pool > 0 )
            $max_users = max( $max_users, count($arr_users) );
      }
      return $max_users;
   }

   /*! \brief Returns arr( tier-pool-key => PoolSummaryEntry, ... ) used for input to PoolSummary-class. */
   public function calc_pool_summary( $games_factor )
   {
      $arr = array();
      foreach ( $this->pools as $tier_pool_key => $arr_users )
      {
         $usercount = count($arr_users);
         $arr[$tier_pool_key] = new PoolSummaryEntry(
            $usercount, TournamentUtils::calc_pool_games($usercount, $games_factor) );
      }
      return $arr;
   }

   /*! \brief Returns expected sum of games for all pools. */
   public function calc_pool_games_count( $games_factor )
   {
      $count = 0;
      foreach ( $this->pools as $tier_pool_key => $arr_users )
         $count += TournamentUtils::calc_pool_games( count($arr_users), $games_factor );
      return $count;
   }

} // end of 'PoolTables





 /*!
  * \class PoolSummary
  *
  * \brief Utility methods for pool-summary for one tournament-round.
  */
class PoolSummary
{
   private $pool_summary; // [ tier-pool-key => PoolSummaryEntry, ... ]
   private $table; // Table-object
   private $tourney_type;
   private $choice_form = null; // Form-object

   public function __construct( $page, $arr_pool_sum, $tourney_type, $choice_form=null )
   {
      $this->pool_summary = $arr_pool_sum;
      $this->tourney_type = $tourney_type;
      $this->choice_form = $choice_form;

      $pstable = new Table( 'TPoolSummary', $page, null, 'ps',
         TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      if ( $this->choice_form )
         $pstable->add_tablehead( 5, T_('Choice#header'), 'Mark' );
      $pstable->add_tablehead( 1, T_('Pool'), 'Number' );
      $pstable->add_tablehead( 2, T_('Size#header'), 'Number' );
      $pstable->add_tablehead( 3, new TableHead( T_('#Games#header'), T_('#Games')), 'Number' );
      if ( $this->choice_form )
         $pstable->add_tablehead( 6, new TableHead( T_('#Started Games#header'), T_('#Started Games#tourney')), 'Number' );
      $pstable->add_tablehead( 4, T_('Pool Errors#header'), 'Note' );
      $this->table = $pstable;
   }

   public function make_table_pool_summary()
   {
      ksort($this->pool_summary);
      $cnt_pools = count($this->pool_summary);
      $cnt_users = $cnt_games = $cnt_started_games = 0;
      foreach ( $this->pool_summary as $tier_pool_key => $pse )
      {
         list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );

         $cnt_errors = count($pse->errors);
         $cnt_users += $pse->count_users;
         $cnt_games += $pse->count_games;
         $cnt_started_games += $pse->count_started_games;

         $row_arr = array(
            1 => PoolViewer::format_tier_pool( $this->tourney_type, $tier, $pool, true ),
            2 => $pse->count_users,
            3 => $pse->count_games,
            4 => ( $cnt_errors ? implode(', ', $pse->errors ) : T_('OK') ),
         );
         if ( $this->choice_form )
         {
            $key = 'tpk_' . $tier_pool_key;
            if ( $cnt_pools > 1 ) // use 'ALL' instead if only 1 pool in total
               $row_arr[5] = $this->choice_form->print_insert_checkbox( $key, '1', '', @$_REQUEST[$key],
                  array( 'title' => T_('Select pool for starting tournament games')) );
            $row_arr[6] = ( $pse->count_games != $pse->count_started_games )
               ? span('EmphasizeWarn', $pse->count_started_games)
               : $pse->count_started_games;
         }
         if ( $cnt_errors )
            $row_arr['extra_class'] = 'Violation';
         $this->table->add_row( $row_arr );
      }//pools-loop

      // summary row
      $row_arr = array(
            1 => ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN ) ? '' : $cnt_pools,
            2 => $cnt_users,
            3 => $cnt_games,
            4 => T_('Sum'),
            'extra_class' => 'Sum',
         );
      if ( $this->choice_form )
      {
         $row_arr[1] = T_('All');
         $row_arr[5] = $this->choice_form->print_insert_checkbox( 'tpk_all', '1', '', @$_REQUEST['tpk_all'],
               array( 'title' => T_('Starting tournament games for ALL pools')) );
         $row_arr[6] = ( $cnt_games != $cnt_started_games )
            ? span('EmphasizeWarn', $cnt_started_games)
            : $cnt_started_games;
      }
      $this->table->add_row( $row_arr );

      return $this->table;
   }//make_table_pool_summary

   /*! \brief returns total counts of all pools: arr( pools-count, user-count, games-count, started-games-count ). */
   public function get_counts()
   {
      $cnt_users = $cnt_games = $cnt_started_games = 0;
      foreach ( $this->pool_summary as $tier_pool_key => $pse )
      {
         $cnt_users += $pse->count_users;
         $cnt_games += $pse->count_games;
         $cnt_started_games += $pse->count_started_games;
      }

      return array( count($this->pool_summary), $cnt_users, $cnt_games, $cnt_started_games );
   }//get_counts

} // end of 'PoolSummary'


 /*!
  * \class PoolSummaryEntry
  *
  * \brief Class for entries of PoolSummary-class containing specific data about single pool.
  */
class PoolSummaryEntry
{
   public $count_users;
   public $count_games;
   public $count_started_games;
   public $errors;

   public function __construct( $count_users, $count_games, $count_started_games=0 )
   {
      $this->count_users = (int)$count_users;
      $this->count_games = (int)$count_games;
      $this->count_started_games = (int)$count_started_games;
      $this->errors = array();
   }

} // end of 'PoolSummaryEntry'




 /*!
  * \class PoolViewer
  *
  * \brief Class to help in building GUI-representation of pools within tournament-round
  */

define('PVOPT_NO_RESULT',  0x01); // don't show cross-table with results
define('PVOPT_NO_COLCFG',  0x02); // don't load table-column-config
define('PVOPT_NO_EMPTY',   0x04); // don't show empty pools
define('PVOPT_EMPTY_SEL',  0x08); // show "selected" empty-pools (directly called with make_single_pool_table-func)
define('PVOPT_NO_TRATING', 0x10); // don't show T-rating (is same as user-rating)
define('PVOPT_EDIT_COL',   0x20); // add edit-actions table-column for pool-edit
define('PVOPT_EDIT_RANK',  0x40); // add edit-actions table-column for rank-edit
define('PVOPT_NO_ONLINE',  0x80); // don't show user-online-icon (may be disabled if pools loaded from cache)

class PoolViewer
{
   private $tid;
   private $tourney_type;
   private $ptabs; // PoolTables-object
   private $table; // Table-object

   private $my_id; // player_row['ID']
   private $pool_name_formatter;
   private $games_factor;
   private $options;
   private $edit_callback = null;

   private $pools_max_users;
   private $first_pool = true;
   private $poolidx; // start-index of result-matrix starting with 1

   /*!
    * \brief Construct PoolViewer setting up Table-structure.
    * \param $pool_tables PoolTables object
    */
   public function __construct( $tid, $page, $tourney_type, $pool_tables, $pool_names_format, $games_factor=1, $pv_opts=0 )
   {
      global $player_row;

      $this->tid = $tid;
      $this->tourney_type = $tourney_type;
      $this->ptabs = $pool_tables;
      $this->my_id = $player_row['ID'];
      $this->pool_name_formatter = new PoolNameFormatter($pool_names_format);
      $this->games_factor = (int)$games_factor;
      $this->options = (int)$pv_opts;
      $this->pools_max_users = $this->ptabs->count_pools_max_user();

      if ( $this->options & PVOPT_NO_COLCFG )
         $cfg_tblcols = null;
      else
      {
         $cfg_tblcols = ConfigTableColumns::load_config( $this->my_id, CFGCOLS_TOURNAMENT_POOL_VIEW );
         if ( !$cfg_tblcols )
            error('user_init_error', 'PoolViewer.constructor.init.config_table_cols');
      }
      $hide_results = ( $this->options & PVOPT_NO_RESULT );

      $table = new Table( 'PoolViewer', $page, $cfg_tblcols, '', TABLE_NO_SORT|TABLE_NO_SIZE|TABLE_NO_THEAD );
      $table->use_show_rows(false);
      $table->add_external_parameters( new RequestParameters( array( 'tid' => $this->tid )), true );
      if ( !$hide_results )
         $table->add_or_del_column();
      $this->table = $table;
   }//__construct

   public function setEditCallback( $edit_callback )
   {
      $this->edit_callback = $edit_callback;
   }

   public function init_pool_table()
   {
      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      if ( $this->options & PVOPT_EDIT_COL )
         $this->table->add_tablehead( 8, T_('Actions#header'), 'Image', TABLE_NO_HIDE );
      $this->table->add_tablehead( 1, T_('Name#header'), 'User', 0 );
      $this->table->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE );
      $this->table->add_tablehead( 3, new TableHead( T_('User Rating#header'), T_('User Rating')), 'Rating', 0 );
      if ( !($this->options & PVOPT_NO_TRATING) )
         $this->table->add_tablehead( 4, new TableHead( T_('Tournament Rating#header'), T_('Tournament Rating')), 'Rating', 0 );
      $this->table->add_tablehead( 5, T_('Country#header'), 'Image', 0 );
      $this->table->add_tablehead(15, new TableHeadImage( T_('Running and finished tournament games'), 'images/table.gif'), 'Image', 0 );
      $this->table->add_tablehead(13, new TableHeadImage( T_('User online#header'), 'images/online.gif',
            ( $this->options & PVOPT_NO_ONLINE
                  ? T_('Indicator for being on vacation#header')
                  : sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS) . ', ' . T_('or on vacation#header')
            )
         ), 'Image', 0 );
      $this->table->add_tablehead(14, new TableHead( T_('Last access#T_header'), T_('Last access')), 'Right', 0 );
      $this->table->add_tablehead(16, new TableHead( T_('Tournament last move#T_header'), T_('Tournament last move')), 'Right', 0 );

      // IMPORTANT NOTE: don't use higher tablehead-nr after this!!
      //    or else config-table-cols can't be correctly saved if future table-heads are added.
      $idx = $this->table->get_max_tablehead_nr(); // last used-static-col

      $this->poolidx = $idx;
      if ( !($this->options & PVOPT_NO_RESULT) )
      {
         $this->table->add_tablehead( 6, new TableHead( T_('Position#tourneyheader'), T_('Position#TRR_tourney')), 'NumberC', TABLE_NO_HIDE );

         $wider_class = ($this->pools_max_users >= 10) ? ' wider' : '';
         foreach ( range(1, $this->pools_max_users) as $user_idx )
         {
            $ext_class = ( $user_idx < 10 ) ? $wider_class : '';
            if ( $this->games_factor > 1 )
            {
               for ( $g=0; $g < $this->games_factor; ++$g )
                  $this->table->add_tablehead( ++$idx, $user_idx, 'Matrix'.$ext_class, TABLE_NO_HIDE );
            }
            else
               $this->table->add_tablehead( ++$idx, $user_idx, 'Matrix'.$ext_class, TABLE_NO_HIDE );
         }

         $this->table->add_tablehead( 9, T_('#Wins#tourney'), 'NumberC', 0 );
         $this->table->add_tablehead( 7, T_('Points#header'), 'NumberC', TABLE_NO_HIDE );
         $this->table->add_tablehead(10, new TableHead( T_('SODOS#tourney'), T_('Sum Of Defeated Opponents\' Scores')), 'NumberC', 0 );
         $this->table->add_tablehead(11, T_('Rank#tpool'), 'TRank', TABLE_NO_HIDE );
         $this->table->add_tablehead(12, '', '', TABLE_NO_HIDE ); // rank-image
      }
      if ( ($this->options & PVOPT_EDIT_RANK) && !($this->options & PVOPT_EDIT_COL) )
         $this->table->add_tablehead( 8, T_('Edit#header'), 'Image', TABLE_NO_HIDE );
   }//init_pool_table

   /*! \brief Makes table for all pools. */
   public function make_pool_table()
   {
      $this->first_pool = true;

      // unassigned users first
      if ( $this->ptabs->count_unassigned_pool_users() )
         $this->make_single_pool_table( 1, 0 );

      $arr_pool_keys = $this->ptabs->get_tier_pool_keys();
      foreach ( $arr_pool_keys as $tier_pool_key )
      {
         list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
         if ( $pool > 0 )
            $this->make_single_pool_table( $tier, $pool );
      }
   }//make_pool_table

   /*!
    * \brief Makes table for single pool.
    * \param $opts optional: PVOPT_...
    */
   public function make_single_pool_table( $tier, $pool, $opts=0 )
   {
      global $base_path, $NOW;

      $show_results = ( $pool > 0 ) && !( $this->options & PVOPT_NO_RESULT );
      $show_trating = !( $this->options & PVOPT_NO_TRATING );
      $show_edit_col = ( $this->options & (PVOPT_EDIT_COL|PVOPT_EDIT_RANK) ) && !is_null($this->edit_callback);

      $tpoints = $this->ptabs->get_tournament_points();
      if ( $show_results && is_null($tpoints) )
         error('miss_args', "PoolViewer.make_single_pool_table.miss_tpoints({$this->tid},$tier,$pool)");

      $arr_users = $this->ptabs->get_pool_users( $tier, $pool );
      $map_usercols = $this->ptabs->get_user_col_map( $tier, $pool, $this->games_factor );
      $cnt_users = count($arr_users);

      // header
      if ( !$this->first_pool )
         $this->table->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
      if ( $cnt_users )
      {
         $pool_title = ( $pool == 0 )
            ? T_('Users without pool assignment') :
            $this->pool_name_formatter->format($tier, $pool);
         $pool_label = self::format_pool_label( $this->tourney_type, $tier, $pool );
         $this->table->add_row_title( name_anchor($pool_label, '', $pool_title) );
         if ( $this->first_pool )
            $this->table->add_row_thead();
         else
            $this->table->add_row_thead( array( 'mode1' => TABLE_NO_HIDE ));
      }
      $this->first_pool = false;
      if ( $cnt_users == 0 ) // empty-pool
      {
         if ( !($this->options & PVOPT_NO_EMPTY) || ($opts & PVOPT_EMPTY_SEL) )
            $this->table->add_row_title(
               sprintf( T_('%s (empty)#poolname'), $this->pool_name_formatter->format($tier, $pool) ) );
         return;
      }


      // init crosstable
      $cell_matrix_self = Table::build_row_cell( 'X', 'MatrixSelf' );
      if ( $cnt_users < $this->pools_max_users ) // too few users in pool
         $arr_miss_users = array_fill( $this->poolidx + 1 + $cnt_users * $this->games_factor,
            $this->pools_max_users - $cnt_users + $this->games_factor - 1, '-' );
      else
         $arr_miss_users = null;

      // build crosstable
      $timediff_fmt = TIMEFMT_SHORT|TIMEFMT_ZERO|TIMEFMT_ADD_HOUR;
      $show_online_icon = !( $this->options & PVOPT_NO_ONLINE );
      $idx = -1;
      foreach ( $arr_users as $uid )
      {
         $tpool = $this->ptabs->users[$uid];
         $user = $tpool->User;
         ++$idx;

         $row_arr = array(
            2 => user_reference( REF_LINK, 1, '', $uid, $user->Handle, ''),
            6 => $idx + 1,
            7 => $tpool->Points,
            9 => $tpool->Wins . ' : ' . $tpool->Losses,
            10 => $tpool->SODOS,
            11 => $tpool->formatRank( /*incl-CalcRank*/true ),
            12 => $tpool->echoRankImage( $this->tourney_type ),
            13 => echo_user_online_vacation( @$user->urow['OnVacation'], ($show_online_icon ? $user->Lastaccess : 0) ),
            14 => TimeFormat::echo_time_diff( $NOW, $user->Lastaccess, 24, $timediff_fmt ),
            16 => TimeFormat::echo_time_diff( $NOW, (int)@$user->urow['TP_X_Lastmoved'], 24, $timediff_fmt ),
         );
         if ( $this->table->Is_Column_Displayed[1] )
            $row_arr[1] = $user->Name;
         if ( $this->table->Is_Column_Displayed[3] )
            $row_arr[3] = echo_rating( $user->Rating, true, $uid );
         if ( $show_trating && $this->table->Is_Column_Displayed[4] )
            $row_arr[4] = echo_rating( @$user->urow['TP_Rating'], true, $uid );
         if ( $this->table->Is_Column_Displayed[5] )
            $row_arr[5] = getCountryFlagImage( $user->Country );
         if ( $this->table->Is_Column_Displayed[15] )
         {
            $row_arr[15] = echo_image_table( IMG_GAMETABLE_RUN,
                  $base_path."show_games.php?tid={$this->tid}".URI_AMP."uid=$uid",
                  sprintf( T_('Running tournament games of user [%s]'), $user->Handle ),
                  false )
               . echo_image_table( IMG_GAMETABLE_FIN,
                  $base_path."show_games.php?tid={$this->tid}".URI_AMP."uid=$uid".URI_AMP."finished=1",
                  sprintf( T_('Finished tournament games of user [%s]'), $user->Handle ),
                  false );
         }

         if ( $show_results ) // build game-result-matrix (one user-row)
         {
            for ( $g=0; $g < $this->games_factor; ++$g ) // blacken self-pairings
               $row_arr[$this->poolidx + 1 + $idx * $this->games_factor + $g] = $cell_matrix_self;
            if ( $arr_miss_users ) // add '-' if too few users in pool
               $row_arr += $arr_miss_users;

            // add game-results
            // - X=self, #=running-game;; points=won/lost/jigo/no-result (see TournamentPoints-config)
            // - linked to running/finished game
            foreach ( $tpool->PoolGames as $poolGame )
            {
               $game_url = $base_path."game.php?gid=".$poolGame->gid;
               $col = $map_usercols[ $poolGame->get_opponent($uid) ] * $this->games_factor + $poolGame->GameNo;

               list( $score, $points, $mark, $title, $style ) = $poolGame->calc_result( $uid, $tpoints );
               $cell = anchor( $game_url, $mark, $title );
               if ( !is_null($style) )
                  $cell = Table::build_row_cell( $cell, $style );

               $row_arr[$this->poolidx + $col] = $cell;
            }

            // mark line of current-user
            if ( $uid == $this->my_id )
            {
               foreach ( range(1, $this->poolidx) as $cell_idx )
               {
                  if ( isset($row_arr[$cell_idx]) )
                     $row_arr[$cell_idx] = Table::build_row_cell($row_arr[$cell_idx], 'TourneyUser');
               }
            }
         }//result-matrix

         if ( $show_edit_col && !is_null($this->edit_callback) )
            $row_arr[8] = call_user_func_array( $this->edit_callback, array( &$this, &$uid ) ); // call vars by ref

         $this->table->add_row( $row_arr );
      }
   }//make_single_pool_table

   /*! \brief Prints built table, need make_pool_table() first. */
   public function echo_pool_table()
   {
      echo $this->table->echo_table();
   }

   /*! \brief Returns tourney-type-specific label for tier/pool (for view_pools-page): pool_C3 (league), pool3 (round-robin) */
   public static function format_pool_label( $tourney_type, $tier, $pool )
   {
      if ( $tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         $pn_league = new PoolNameFormatter('pool_%t(uc)%p(num)');
         $pool_label = $pn_league->format( $tier, $pool );
      }
      else //if ( $tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
         $pool_label = "pool$pool";

      return $pool_label;
   }

   /*! \brief Returns tourney-type-specific format for tier/pool: Pool C3 (league), Pool 3 (round-robin). */
   public static function format_tier_pool( $tourney_type, $tier, $pool, $short=false )
   {
      $pool_prefix = ($short) ? '' : '%P ';
      if ( $tourney_type == TOURNEY_TYPE_LEAGUE )
         $pn = new PoolNameFormatter($pool_prefix.'%t(uc)%p(num)');
      else //if ( $tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
         $pn = new PoolNameFormatter($pool_prefix.'%p(num)');

      return $pn->format( $tier, $pool );
   }

} // end of 'PoolViewer'




 /*!
  * \class PoolNameFormatter
  *
  * \brief Class to format pool-name
  */
class PoolNameFormatter
{
   private $format;

   public function __construct( $format=null )
   {
      $this->format = ( $format ) ? $format : '%P %t(uc)%p(num)';
   }

   public function format( $tier, $pool )
   {
      static $ARR_FMT_NEEDLES = array( '%P', '%p(num)', '%p(uc)', '%L', '%t(num)', '%t(uc)', '%%' );

      if ( $pool == 0 ) // unassigned-pool => always '0' (without tier)
         $tier = -1;

      return str_replace( $ARR_FMT_NEEDLES,
            array(
               /* %P      */  T_('Pool#poolname'),
               /* %p(num) */  $pool,
               /* %p(uc)  */  ($pool >= 1 && $pool <= 26 ? chr(ord('A') + $pool - 1) : $pool ),
               /* %L      */  T_('League#poolname'),
               /* %t(num) */  ($tier < 0 ) ? '' : $tier,
               /* %t(uc)  */  ($tier >= 1 && $tier <= 26 ? chr(ord('A') + $tier - 1) : ($tier < 0 ? '' : $tier) ),
               /* %%      */  '%',
            ), $this->format );
   }//format

   public function is_valid_format()
   {
      if ( !preg_match("/%p\(num|uc\)/", $this->format) ) // must have '%p(..)'
         return false;

      // must have some other text
      $chk_fmt = preg_replace( "/(%[pt]\((num|uc)\)|%%|\\s+)/", '', $this->format );
      if ( (string)$chk_fmt == '' )
         return false;

      // no other than allowed '%'-macros
      $chk_fmt = str_replace( array('%P', '%L'), '', $chk_fmt );
      if ( strpos($chk_fmt, '%') !== false )
         return false;

      if ( preg_match("/[<>\r\n]/", $this->format) ) // no HTML <|>
         return false;

      return true;
   }//is_valid_format

   public static function format_with_default( $tier, $pool )
   {
      $pn_formatter = new PoolNameFormatter();
      return $pn_formatter->format($tier, $pool);
   }

} // end of 'PoolNameFormatter'



 /*!
  * \class PoolParser
  *
  * \brief Class to parse different pool-formats & check for valid tier/pool-combination.
  */
class PoolParser
{
   private $tourney_type;
   private $tround;
   public $errors = array();

   private $valid_tier_pools; // [ tier-pool-key => 1, .. ]

   public function __construct( $tourney_type, $tround )
   {
      $this->tourney_type = $tourney_type;
      $this->tround = $tround;

      $this->build_valid_tier_pools();
   }

   private function build_valid_tier_pools()
   {
      $arr = array();
      $arr[TournamentUtils::encode_tier_pool_key($tier=1, 1)] = 1;

      $cnt_pools = 1;
      $tier_inc_factor = 1;
      while ( ++$tier <= TROUND_MAX_TIERCOUNT && $cnt_pools < $this->tround->Pools )
      {
         $max_tier_pools = $this->tround->TierFactor * $tier_inc_factor;
         $tier_inc_factor *= 2;
         for ( $pool=1; $pool <= $max_tier_pools && $cnt_pools < $this->tround->Pools; $pool++, $cnt_pools++)
            $arr[TournamentUtils::encode_tier_pool_key($tier, $pool)] = 1;
      }

      $this->valid_tier_pools = $arr;
   }//build_valid_tier_pools

   public function get_valid_tier_pools()
   {
      return $this->valid_tier_pools;
   }

   /*!
    * \brief Returns tier-pool-key of parsed $value; 0 on error.
    * \param $value accepted formats:
    *       league-tournament: "C3", "c3";  "3[non-digits]1", "3 1", "3.1", "3:1", etc.
    *       round-robin-tournament: "3"
    */
   public function parse_tier_pool( $error_context, $value )
   {
      $this->errors = array();

      if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         if ( preg_match("/^(\d+)\D+(\d+)$/", $value, $matches) ) // "3[non-digit]1" -> tier=3, pool=1
            $result = TournamentUtils::encode_tier_pool_key( $matches[1], $matches[2] );
         elseif ( preg_match("/^([A-Z])(\d+)$/i", $value, $matches) ) // "C3|c3" -> tier=3, pool=1
         {
            $tier = ord( strtoupper($matches[1]) ) - ord('A') + 1;
            $result = TournamentUtils::encode_tier_pool_key( $tier, $matches[2] );
         }
         else
            return $this->add_error( $error_context, sprintf( T_('Pool [%s] is invalid'), $value ) );

         if ( !isset($this->valid_tier_pools[$result]) )
            return $this->add_error( $error_context, sprintf( T_('Pool [%s] is invalid'), $value ));
      }
      else //if ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
      {
         if ( is_numeric($value) && $value >= 1 && $value <= $this->tround->Pools )
            $result = TournamentUtils::encode_tier_pool_key( 1, $value );
         else
            return $this->add_error( $error_context, sprintf( T_('Pool [%s] is invalid, must be in range %s'),
                  $value, build_range_text(1, $this->tround->Pools) ));
      }

      if ( $result == 0 || $result == TournamentUtils::encode_tier_pool_key(1, 0) ) // unassigned-pool not allowed
         return $this->add_error( $error_context, sprintf( T_('Pool [%s] is invalid'), $value ));

      return $result;
   }//parse_tier_pool

   /*! \brief Returns true, if given $tier/$pool are a valid tier/pool-combination. */
   public function is_valid_tier_pool( $tier, $pool )
   {
      if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         $tier_pool_key = TournamentUtils::encode_tier_pool_key( $tier, $pool );
         if ( isset($this->valid_tier_pools[$tier_pool_key]) )
            return true;
      }
      else //if ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
      {
         if ( !is_numeric($tier) || $tier != 1 )
            return false;
         if ( is_numeric($tier) && $pool >= 1 && $pool <= $this->tround->Pools )
            return true;
      }

      return false;
   }//is_valid_tier_pool

   /*! \brief Returns true, if given $tier_pool_key is a valid tier/pool-combintation. */
   public function is_valid_tier_pool_key( $tier_pool_key )
   {
      list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
      return $this->is_valid_tier_pool( $tier, $pool );
   }

   /*! \brief Builds arr( tier-pool-key => pool-name, ... ) to be used for selection-box with all valid pools. */
   public function build_valid_tier_pools_selection()
   {
      $arr = array();

      if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         foreach ( $this->get_valid_tier_pools() as $tier_pool_key => $tmp )
         {
            list( $tier, $pool ) = TournamentUtils::decode_tier_pool_key( $tier_pool_key );
            $arr[$tier_pool_key] = PoolViewer::format_tier_pool( $this->tourney_type, $tier, $pool, true );
         }
      }
      else //if ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
      {
         for ( $pool=1; $pool <= $this->tround->Pools; $pool++ )
         {
            $tier_pool_key = TournamentUtils::encode_tier_pool_key( 1, $pool );
            $arr[$tier_pool_key] = $pool;
         }
      }

      return $arr;
   }//build_valid_tier_pools_selection

   private function add_error( $error_context, $error_msg )
   {
      $this->errors[] = "[$error_context]: $error_msg";
      return 0;
   }

} // end of 'PoolParser'




 /*!
  * \class TierSlicer
  *
  * \brief Helper-class to create pools with tiered leagues.
  */
class TierSlicer
{
   private $tourney_type;
   private $tround;
   private $slice_mode;
   private $tp_count;

   private $tier_pool_count; // pool-count for current tier
   private $tier_max_tp; // max-TP for current tier

   private $curr_tier;
   private $tier_idx_tp;
   private $tier_inc_factor;
   private $remaining_tp; // remaining TPs
   private $pool_slicer = null;
   private $arr_tier_pools; // { tier:pool => 1 }
   private $max_tier;

   public function __construct( $tourney_type, $tround, $slice_mode, $tp_count )
   {
      if ( !($tround instanceof TournamentRound) )
         error('invalid_args', "TierSlicer:__construct.check_tround($tourney_type,$slice_mode,$tp_count)");

      $this->tourney_type = $tourney_type;
      $this->tround = $tround;
      $this->slice_mode = $slice_mode;
      $this->tp_count = (int)$tp_count;

      $this->init();
   }

   private function init()
   {
      $this->curr_tier = 1;
      $this->tier_idx_tp = 0;
      $this->remaining_tp = $this->tp_count;

      $this->arr_tier_pools = array(); // [ tier:pool => 1 ], also for pool=0
      $this->tier_inc_factor = 1;
      $this->max_tier = 1;

      if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         $this->tier_pool_count = 1;
         $this->tier_max_tp = $this->tround->PoolSize;
      }
      else //if ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
      {
         $this->tier_pool_count = $this->tround->Pools;
         $this->tier_max_tp = $this->tp_count;
      }

      $this->reset_pool_slicer();
   }

   private function reset_pool_slicer( $slice_mode=null )
   {
      if ( is_null($slice_mode) )
         $slice_mode = $this->slice_mode;
      $this->pool_slicer = new PoolSlicer( $slice_mode, $this->tier_pool_count, $this->tround->PoolSize );
   }

   public function next_tier_pool()
   {
      if ( $this->tier_idx_tp >= $this->tier_max_tp )
      {
         $this->tier_idx_tp = 0;
         $this->curr_tier++;

         if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
         {
            $this->tier_pool_count = $this->tround->TierFactor * $this->tier_inc_factor;
            $this->tier_max_tp = $this->tier_pool_count * $this->tround->PoolSize;
            $this->tier_inc_factor *= 2;

            // handle bottom-tier
            if ( $this->remaining_tp >= $this->tround->MinPoolSize * $this->tier_pool_count )
               $this->reset_pool_slicer();
            elseif ( $this->remaining_tp < $this->tround->MinPoolSize )
            {
               // fill remaining TPs into unassigned-pool
               $this->curr_tier = 1;
               $this->reset_pool_slicer( TROUND_SLICE_MANUAL );
            }
            else // min-pool-size <= rem_tp < min-pool-size * tier-pool-count
            {
               $this->tier_pool_count = floor( $this->remaining_tp / $this->tround->MinPoolSize );
               $this->reset_pool_slicer();
            }
         }
      }
      $this->tier_idx_tp++;
      $this->remaining_tp--;

      $curr_pool = $this->pool_slicer->next_pool();
      $this->visit_tier_pool( $this->curr_tier, $curr_pool );
      return array( $this->curr_tier, $curr_pool );
   }//next_tier_pool

   private function visit_tier_pool( $tier, $pool )
   {
      if ( $tier > $this->max_tier )
         $this->max_tier = $tier;
      $this->arr_tier_pools["$tier:$pool"] = 1;
   }

   /*! \brief Returns creation stats: arr( max-tier, #pools (without unassigned), has-unassigned-pool=0|1 ). */
   public function get_slicer_counts()
   {
      return array( $this->max_tier, count($this->arr_tier_pools), (int)@$this->arr_tier_pools['1:0'] );
   }

} // end of 'TierSlicer'



 /*!
  * \class PoolSlicer
  *
  * \brief Helper-class with different slice-modes for seeding pools.
  */
class PoolSlicer
{
   private $slice_mode;
   private $pool_count;
   private $pool_size;

   private $idx_slicing;
   private $curr_pool;
   private $arr_pools;

   private $arr_snaking = null;
   private $mod_snaking = 0;

   public function __construct( $slice_mode, $pool_count, $pool_size )
   {
      if ( $pool_count <= 0 )
         error('invalid_args', "PoolSlicer:__construct.check_pool_count($slice_mode,$pool_count,$pool_size)");
      if ( $pool_count < 1 )
         error('invalid_args', "PoolSlicer:__construct.check_pool_size($slice_mode,$pool_count,$pool_size)");

      $this->slice_mode = $slice_mode;
      $this->pool_count = (int)$pool_count;
      $this->pool_size = (int)$pool_size;

      $this->init();
   }

   private function init()
   {
      static $ARR_START_POOL = array(
            TROUND_SLICE_SNAKE         => 1,
            TROUND_SLICE_ROUND_ROBIN   => 0,
            TROUND_SLICE_FILLUP_POOLS  => 1,
            TROUND_SLICE_MANUAL        => 0,
         );

      $this->idx_slicing = 0;
      $this->curr_pool = $ARR_START_POOL[$this->slice_mode];

      $this->arr_pools = array(); // [ pool => entries ], also for pool=0
      foreach ( range(0, $this->pool_count) as $pool )
         $this->arr_pools[$pool] = 0;

      if ( $this->slice_mode == TROUND_SLICE_SNAKE )
      {
         if ( $this->pool_count == 1 )
            $this->arr_snaking = array( 0, 0 );
         else
         {
            $this->arr_snaking = array_merge(
               array(0),
               array_fill( 1, $this->pool_count - 1, 1 ),
               array(0),
               array_fill( $this->pool_count + 1, $this->pool_count - 1, -1) );
         }
         $this->mod_snaking = 2 * $this->pool_count; // should be == count($arr_snaking)
      }
   }//init

   /*! \brief Returns next pool-index for chosen slice-mode. */
   public function next_pool()
   {
      switch ( $this->slice_mode )
      {
         case TROUND_SLICE_SNAKE:
            $this->curr_pool += $this->arr_snaking[$this->idx_slicing % $this->mod_snaking];
            break;
         case TROUND_SLICE_ROUND_ROBIN:
            if ( ++$this->curr_pool > $this->pool_count )
               $this->curr_pool = 1;
            break;
         case TROUND_SLICE_FILLUP_POOLS:
            if ( $this->curr_pool < $this->pool_count && $this->arr_pools[$this->curr_pool] >= $this->pool_size )
               ++$this->curr_pool;
            break;
         case TROUND_SLICE_MANUAL:
         default:
            // else: pool always 0
            break;
      }

      $this->arr_pools[$this->curr_pool]++;
      $this->idx_slicing++;
      return $this->curr_pool;
   }//next_pool

   public function count_visited_pools()
   {
      $cnt_pools = 0;
      foreach ( $this->arr_pools as $pool => $cnt )
      {
         if ( $cnt > 0 )
            $cnt_pools++;
      }
      return $cnt_pools;
   }

} // end of 'PoolSlicer'





 /*!
  * \class RankSummary
  *
  * \brief Utility methods for rank-count-summary for one tournament-round
  */
class RankSummary
{
   private $tourney_type;
   private $rank_summary; // rank|TPOOLRK_NO_RANK/WITHDRAW => [ count, NextRound-count ]
   private $relegation_summary; // Rank => [ relegation(=TPOOL_FLAG_PROMOTE|DEMOTE|0(=same-tier)) => count, ... ]
   private $tp_count;
   private $table; // Table-object

   /*!
    * \brief Constructs RankSummary.
    * \param $rank_counts [ TournamentPool.Rank => count, ... ]
    * \param $relegation_counts [ TournamentPool.Rank => [ flag => count, ... ]]
    */
   public function __construct( $page, $tourney_type, $rank_counts, $relegation_counts, $tp_count=0 )
   {
      $this->tourney_type = $tourney_type;
      $this->tp_count = (int)$tp_count;
      $this->relegation_summary = $relegation_counts;

      $rstable = new Table( 'TPoolSummary', $page, null, 'rs',
         TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $rstable->add_tablehead( 1, T_('Rank#tpool'), 'TRank' );
      $rstable->add_tablehead( 2, T_('Count#ranksum_header'), 'NumberC' );
      $rstable->add_tablehead( 3, T_('NR-Count#ranksum_header'), 'NumberC' );
      if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         $rstable->add_tablehead( 4, new TableHeadImage(echo_image_tourney_relegation(TPOOL_FLAG_PROMOTE, true),
            'images/promote.gif'), 'wider NumberC' );
         $rstable->add_tablehead( 5, new TableHeadImage(echo_image_tourney_relegation(TPOOL_FLAG_DEMOTE, true),
            'images/demote.gif'), 'wider NumberC' );
      }
      $this->table = $rstable;

      // build rank-summary
      $arr = array();
      foreach ( $rank_counts as $rank => $count )
      {
         $key = ( $rank < TPOOLRK_RANK_ZONE ) ? TPOOLRK_NO_RANK : abs($rank);
         if ( !isset($arr[$key]) )
            $arr[$key] = array( 0, 0 );
         $arr[$key][0] += $count;
         if ( $rank > 0 )
            $arr[$key][1] += $count;
      }
      $this->rank_summary = $arr;
   }//__construct

   public function get_ranks()
   {
      $arr = array();
      foreach ( $this->rank_summary as $rank => $tmp )
      {
         if ( $rank >= 0 )
            $arr[] = $rank;
      }
      sort( $arr, SORT_NUMERIC );
      return $arr;
   }//get_ranks

   public function build_notes_rank_summary()
   {
      $is_trr = ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN );
      $notes = array();

      if ( $is_trr )
      {
         $notes[] = T_('Rank \'TP\' = tournament-participants registered to start in next round');
         $notes[] = sprintf( T_('Rank \'%s\' = pool-user has been withdrawn from next round (no pool-winner)'), TPOOLRK_WITHDRAW );
      }
      else
         $notes[] = sprintf( T_('Rank \'%s\' = pool-user has been withdrawn from next cycle'), TPOOLRK_WITHDRAW );
      $notes[] = sprintf( T_('Rank \'%s\' = rank not set yet for pool-users'), T_('unset#tpool') );
      $notes[] = sprintf( T_('%s = count of users with a set rank#tpool'), T_('Count#ranksum_header') );

      $txt_next_round = ( $is_trr )
         ? T_('%s = count of pool-winners (playing in next round, or marked for final result)#tourney')
         : T_('%s = count of pool-users to play in next cycle#tourney');
      $notes[] = sprintf( $txt_next_round, T_('NR-Count#ranksum_header') );

      if ( $this->tourney_type == TOURNEY_TYPE_LEAGUE )
      {
         $notes[] = array( 'text' => sprintf( '%s / %s = ',
               echo_image_tourney_relegation(TPOOL_FLAG_PROMOTE), echo_image_tourney_relegation(TPOOL_FLAG_DEMOTE) )
            . T_('count of pool-users promoted / demoted in the next cycle#tourney') );
      }

      return $notes;
   }//build_notes_rank_summary

   public function make_table_rank_summary()
   {
      uksort( $this->rank_summary, array($this, '_compare_ranks') );

      if ( $this->tourney_type == TOURNEY_TYPE_ROUND_ROBIN )
      {
         // TP-count
         $this->table->add_row( array(
               1 => 'TP',
               2 => NO_VALUE,
               3 => empty_if0($this->tp_count),
               //4;5 only used for league-tournaments
            ));
      }

      $count_nextround = $this->tp_count;
      $count_users = $count_promotes = $count_demotes = 0;
      foreach ( $this->rank_summary as $rank => $arr ) // arr = count_rank, count_nextround
      {
         $cnt_promote = (int)@$this->relegation_summary[$rank][TPOOL_FLAG_PROMOTE];
         $cnt_demote  = (int)@$this->relegation_summary[$rank][TPOOL_FLAG_DEMOTE];

         $count_nextround += $arr[1];
         $count_users += $arr[0];
         $count_promotes += $cnt_promote;
         $count_demotes += $cnt_demote;

         $this->table->add_row( array(
               1 => ($rank < TPOOLRK_RANK_ZONE) ? T_('unset#tpool') : $rank,
               2 => $arr[0],
               3 => empty_if0($arr[1]),
               4 => empty_if0($cnt_promote),
               5 => empty_if0($cnt_demote),
            ));
      }

      // summary row
      $this->table->add_row( array(
            1 => T_('Sum'),
            2 => $count_users,
            3 => $count_nextround,
            4 => $count_promotes,
            5 => $count_demotes,
            'extra_class' => 'Sum', ));

      return $this->table;
   }//make_table_rank_summary

   // \internal
   private function _compare_ranks( $a, $b )
   {
      // rank order: 1, 2, 3, ..., 0, -100
      $a2 = ($a != 0) ? abs($a) : -TPOOLRK_RANK_ZONE;
      $b2 = ($b != 0) ? abs($b) : -TPOOLRK_RANK_ZONE;
      return cmp_int( $a2, $b2 );
   }

} // end of 'RankSummary'

?>

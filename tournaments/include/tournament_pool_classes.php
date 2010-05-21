<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_pool.php';


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
   var $Challenger_uid; // smaller than Defender_uid
   var $Defender_uid;
   var $gid;
   var $Score; // score for challenger

   function PoolGame( $ch_uid=0, $df_uid=0, $gid=0, $score=null )
   {
      $new_score = (is_null($score)) ? null : (float)$score;
      if( $ch_uid < $df_uid )
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
   }

   function get_opponent( $uid )
   {
      return ($this->Challenger_uid == $uid) ? $this->Defender_uid : $this->Challenger_uid;
   }

   function get_score( $uid )
   {
      if( is_null($this->Score) )
         return null;
      else
         return ($this->Challenger_uid == $uid) ? $this->Score : -$this->Score;
   }

   /*! \brief Returns arr( user-score, points, cell-marker, title, style ) for given user-id. */
   function calc_result( $uid )
   {
      if( is_null($this->Score) )
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

         //TODO for Mego/Hahn use score instead +-score
         if( $chk_score < 0 )
         {
            $points = 2; // won
            $title = T_('Game won');
            $style = 'MatrixWon';
         }
         elseif( $chk_score > 0 )
         {
            $points = 0; // lost
            $title = T_('Game lost');
            $style = 'MatrixLost';
         }
         else
         {
            $points = 1; // jigo
            $title = T_('Jigo');
            $style = 'MatrixJigo';
         }
         $mark = $points;
      }

      return array( $chk_score, $points, $mark, $title, $style );
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
    */
   function get_tiebreaker( $level )
   {
      // index = order level: => tie-breaker
      static $map = array( TIEBREAKER_POINTS, TIEBREAKER_SODOS );
      if( is_null($level) )
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
   function calc_ranks( &$arr_tpools, $users )
   {
      $tie_level = 0;
      $user_ranks = PoolRankCalculator::build_user_ranks( $arr_tpools, $users,
         PoolRankCalculator::get_tiebreaker($tie_level) );

      // Note on algorithm:
      // - if more tie-breakers must be applied, identify sub-groups with same-rank
      //   and apply tie-breaking on those recursively (without recursion)
      $arr_stop_tiebreak = array(); // [ uid => 1, ...]: no tie-breaking if set for uid
      $max_tiebreaker = PoolRankCalculator::get_tiebreaker(null) - 1;
      for( $tie_level++; $tie_level <= $max_tiebreaker; $tie_level++ )
      {
         if( count($arr_stop_tiebreak) == count($users) ) // no more subgroups without tie-break-stop
            break;
         $same_ranks = PoolRankCalculator::build_same_ranks_groups( $user_ranks );
         if( is_null($same_ranks) ) // completely ordered = all ranks appear only once
            break;

         foreach( $same_ranks as $arr_users ) // tie-break subgroups with same rank
         {
            $stop_tiebreaking = true;
            if( count($arr_users) > 1 ) // subgroup only if >1 members
            {
               // set ranks (starting with 0) for subgroup of users
               $groupuser_ranks = PoolRankCalculator::build_user_ranks( $arr_tpools,
                  array_keys($arr_users), PoolRankCalculator::get_tiebreaker($tie_level) );
               if( count( array_count_values($groupuser_ranks) ) > 1 ) // tie-breaking successful
               {
                  // adjust ranks in main-result for new-ranks (make "hole" by increasing remaining ranks)
                  $same_rank_val = current( $arr_users );
                  $max_rank_new = max( $groupuser_ranks );
                  foreach( $user_ranks as $uid => $rank )
                  {
                     if( $rank > $same_rank_val )
                        $user_ranks[$uid] += $max_rank_new;
                  }

                  // copy new ranks to main-result $user_ranks
                  foreach( $groupuser_ranks as $uid => $rank_new )
                     $user_ranks[$uid] = $same_rank_val + $rank_new;
               }
               else // tie-breaking failed for current tie-breaker (still a draw)
               {
                  if( $tie_level >= $max_tiebreaker )
                     $stop_tiebreaking = false; // continue tie-breaking if there are more tie-breakers
               }
            }

            if( $stop_tiebreaking )
            {
               foreach( $arr_users as $uid => $rank )
                  $arr_stop_tiebreak[$uid] = 1;
            }
         }
      }

      // copy final ranks into TPool->CalcRank for given users
      foreach( $user_ranks as $uid => $rank )
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
    */
   function build_same_ranks_groups( $users )
   {
      // find ties = rank-values appearing several times
      $arr_valcount = array_count_values( $users ); // [ rank => count ], e.g. 1 => #1, 2 => #2, 3 => #2
      if( count($arr_valcount) == count($users) )
         return null;

      $result = array();
      foreach( $arr_valcount as $pivot_rank => $cnt )
         $result[] = array_intersect( $users, array($pivot_rank) );
      return $result;
   }

   /*!
    * \brief Assigns rank to given $users according to specified "tie-breaker value-base".
    * \param $arr_tpools [ uid => TPool, ... ]: array mapping uid in $users to TournamentPool-object
    *        containing the required data to determine rank for users
    * \param $users [ uid, ... ]: list of users expected to have the same rank
    *        (or initially having no rank, which is also "having the same rank")
    * \param $tie_breaker Determine ranks determined by using tie-breaker (TIEBREAKER_...)
    * \return [ uid => rank, ... ]: array mapping user ($uid) to rank (starting at 0)
    *         ordered by ascending rank
    */
   function build_user_ranks( $arr_tpools, $users, $tie_breaker )
   {
      $arr = array();
      $result = array();

      switch( (int)$tie_breaker )
      {
         case TIEBREAKER_POINTS:
         {
            // build mapping (unique value -> to rank)
            foreach( $users as $uid )
               $arr[] = (int) ( 2 * $arr_tpools[$uid]->Points ); // avoid float-keys
            $arr = PoolRankCalculator::build_value_rank_map( $arr, true );

            // assign rank to users according to mapped-values
            foreach( $users as $uid )
               $result[$uid] = $arr[(int)( 2 * $arr_tpools[$uid]->Points )];
            break;
         }
         case TIEBREAKER_SODOS:
         {
            foreach( $users as $uid )
               $arr[] = (int) ( 2 * $arr_tpools[$uid]->SODOS ); // avoid float-keys
            $arr = PoolRankCalculator::build_value_rank_map( $arr, true );

            foreach( $users as $uid )
               $result[$uid] = $arr[(int)( 2 * $arr_tpools[$uid]->SODOS )];
            break;
         }
         case TIEBREAKER_WINS:
         {
            foreach( $users as $uid )
               $arr[] = $arr_tpools[$uid]->Wins;
            $arr = PoolRankCalculator::build_value_rank_map( $arr, true );

            foreach( $users as $uid )
               $result[$uid] = $arr[$arr_tpools[$uid]->Wins];
            break;
         }
         default: // no tie-breaking
         {
            foreach( $users as $uid )
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
    */
   function build_value_rank_map( $arr, $reverse )
   {
      // uniq before sort, because default sort-flag SORT_STRING for PHP >=5.2.10
      // (but even if applied on numbers, arr is unique afterwards)
      $arr = array_unique($arr);

      if( $reverse )
         rsort( $arr, SORT_NUMERIC );
      else
         sort( $arr, SORT_NUMERIC );

      $rank = 0;
      $rank_map = array();
      foreach( $arr as $val )
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
   var $users;
   /*! \brief list of pool-numbers with list of result-ordered(!) pool-users: map( poolNo => [ uid, ... ] ) */
   var $pools;

   function PoolTables( $count_pools )
   {
      $this->users = array();
      $this->pools = array();
      for( $pool=1; $pool <= $count_pools; $pool++ )
         $this->pools[$pool] = array();
   }

   function get_user_tournament_pool( $uid )
   {
      return @$this->users[$uid];
   }

   /*! \brief Returns array( pool => arr[ uid's, ... ] ). */
   function get_pool_users()
   {
      return $this->pools;
   }

   /*! \brief Returns array( uid => col-idx, ... ) for given pool-no (col-idx starting with 1(!)). */
   function get_user_col_map( $pool )
   {
      $map = array();
      $idx = 1;
      foreach( $this->pools[$pool] as $uid )
         $map[$uid] = $idx++;
      return $map;
   }

   function fill_pools( $tpool_iterator )
   {
      while( list(,$arr_item) = $tpool_iterator->getListIterator() )
      {
         list( $tpool, $orow ) = $arr_item;
         if( is_null($tpool) ) continue;
         $uid = $tpool->uid;
         $pool = $tpool->Pool;

         $this->users[$uid] = $tpool;
         $this->pools[$pool][] = $uid;
      }
   }

   /*! \brief Sets TournamentGames for pool-tables and count games. */
   function fill_games( $tgames_iterator )
   {
      $reorder_pools = array(); // pool-no => 1 (=needs re-order of users)
      $defeated_opps = array(); // uid => [ opp-uid, ... ];  won(opp 2x) or jigo(opp 1x)
      while( list(,$arr_item) = $tgames_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;
         $ch_uid = $tgame->Challenger_uid;
         $df_uid = $tgame->Defender_uid;
         $tpool_ch = $this->users[$ch_uid];
         $tpool_df = $this->users[$df_uid];

         $game_score = ($tgame->isScoreStatus()) ? $tgame->Score : null;
         $poolGame = new PoolGame( $ch_uid, $df_uid, $tgame->gid, $game_score );

         $tpool_ch->PoolGames[] = $poolGame;
         $tpool_df->PoolGames[] = $poolGame;

         $arr = $poolGame->calc_result( $ch_uid ); // score,points,mark,title,style
         $tpool_ch->Points += $arr[1];
         if( !is_null($arr[0]) )
         {
            if( $arr[0] > 0 ) $tpool_ch->Losses++;
            if( $arr[0] < 0 ) $tpool_ch->Wins++;
            if( $arr[0] < 0 ) $defeated_opps[$ch_uid][] = $df_uid;
            if( $arr[0] <= 0 ) $defeated_opps[$ch_uid][] = $df_uid; // win counts double
         }

         $arr = $poolGame->calc_result( $df_uid );
         $tpool_df->Points += $arr[1];
         if( !is_null($arr[0]) )
         {
            if( $arr[0] > 0 ) $tpool_ch->Losses++;
            if( $arr[0] < 0 ) $tpool_df->Wins++;
            if( $arr[0] < 0 ) $defeated_opps[$df_uid][] = $ch_uid;
            if( $arr[0] <= 0 ) $defeated_opps[$df_uid][] = $ch_uid; // win counts double
         }

         if( !is_null($game_score) )
            $reorder_pools[$tpool_ch->Pool] = 1;
      }

      // calc SODOS
      foreach( $defeated_opps as $uid => $arr_opps )
      {
         $tpool_ch = $this->users[$uid];
         foreach( $arr_opps as $opp )
         {
            $tpool_df = $this->users[$opp];
            $tpool_ch->SODOS += $tpool_df->Points;
         }
         $tpool_ch->SODOS /= 2;
      }

      // re-order pool-users
      foreach( $reorder_pools as $pool => $tmp )
      {
         // calculate rank for users determined by tie-breakers
         PoolRankCalculator::calc_ranks( $this->users, $this->pools[$pool] );

         usort( $this->pools[$pool], array( $this, '_compare_user_ranks' ) ); //by TPool-fields.Rank/CalcRank
      }
   }//fill_games

   /*! \internal Comparator-function to sort users of pool. */
   function _compare_user_ranks( $a_uid, $b_uid )
   {
      $a_tpool = $this->users[$a_uid];
      $b_tpool = $this->users[$b_uid];

      $cmp_rank = cmp_int( $a_tpool->get_cmp_rank(), $b_tpool->get_cmp_rank() );
      if( $cmp_rank != 0 )
         return $cmp_rank;

      // Tie-Breaker: CalcRank (even if eventually used in cmp_rank implicitly)
      $cmp_calcrank = cmp_int( $a_tpool->CalcRank, $b_tpool->CalcRank );
      if( $cmp_calcrank != 0 )
         return $cmp_calcrank;

      return cmp_int( $a_uid, $b_uid ); // static ordering of users to assure same order
   }//compare_user_ranks

   /*!
    * Returns arr( all => count-games, run => running-games, finished => finished-games,
    *         jigo => games won by jigo, time => games won by timeout, resign => games won by resignation ).
    */
   function count_games()
   {
      $count_games = 0;
      $count_run = 0;
      $count_jigo = 0;
      $count_time = 0;
      $count_resign = 0;

      $visited = array(); // game-id, because each PoolGame appears twice for challenger+defender
      foreach( $this->users as $uid => $tpool )
      {
         foreach( $tpool->PoolGames as $poolGame )
         {
            if( @$visited[$poolGame->gid] ) continue;
            $visited[$poolGame->gid] = 1;

            $count_games++;
            if( is_null($poolGame->Score) )
               $count_run++;
            elseif( $poolGame->Score == 0 )
               $count_jigo++;
            elseif( abs($poolGame->Score) == SCORE_TIME )
               $count_time++;
            elseif( abs($poolGame->Score) == SCORE_RESIGN )
               $count_resign++;
         }
      }

      return array(
         'all'  => $count_games,
         'run'  => $count_run,
         'finished' => $count_games - $count_run,
         'jigo' => $count_jigo,
         'time' => $count_time,
         'resign' => $count_resign,
      );
   }

   /*! \brief Counts max. of users pro pool for all pools. */
   function count_pools_max_user()
   {
      $max_users = 0;
      foreach( $this->pools as $pool => $arr_users )
      {
         if( $pool > 0 )
            $max_users = max( $max_users, count($arr_users) );
      }
      return $max_users;
   }

   /*!
    * \brief Returns #users for each pool with some extra.
    * \return arr( pool => arr( user-count, errors-arr(empty), pool-games-count ), ... ).
    */
   function calc_pool_summary()
   {
      $arr = array(); // [ pool => [ #users, [], #games-per-pool ], ... ]
      foreach( $this->pools as $pool => $arr_users )
      {
         $usercount = count($arr_users);
         $arr[$pool] = array( $usercount, array(), TournamentUtils::calc_pool_games($usercount) );
      }
      return $arr;
   }

   /*! \brief Returns expected sum of games for all pools. */
   function calc_pool_games_count()
   {
      $count = 0;
      foreach( $this->pools as $pool => $arr_users )
         $count += TournamentUtils::calc_pool_games( count($arr_users) );
      return $count;
   }

} // end of 'PoolTables





 /*!
  * \class PoolSummary
  *
  * \brief Utility methods for pool-summary for one tournament-round
  */
class PoolSummary
{
   var $pool_summary; // pool => [ pool-user-count, errors, pool-games ]
   var $table; // Table-object

   function PoolSummary( $page, $arr_pool_sum )
   {
      $this->pool_summary = $arr_pool_sum;

      $pstable = new Table( 'TPoolSummary', $page, null, 'ps',
         TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $pstable->add_tablehead( 1, T_('Pool#poolsum_header'), 'Number' );
      $pstable->add_tablehead( 2, T_('Size#poolsum_header'), 'Number' );
      $pstable->add_tablehead( 3, T_('Games#poolsum_header'), 'Number' );
      $pstable->add_tablehead( 4, T_('Pool Errors#poolsum_header'), 'Note' );
      $this->table = $pstable;
   }

   function make_table_pool_summary()
   {
      ksort($this->pool_summary);
      $cnt_users = 0;
      $cnt_games = 0;
      foreach( $this->pool_summary as $pool => $arr )
      {
         list( $pool_usercount, $errors, $pool_games ) = $arr;
         $cnt_errors = count($errors);
         $cnt_users += $pool_usercount;
         $cnt_games += $pool_games;

         $row_arr = array(
            1 => $pool,
            2 => $pool_usercount,
            3 => $pool_games,
            4 => ( $cnt_errors ? implode(', ', $errors ) : T_('OK#poolsum') ),
         );
         if( $cnt_errors )
            $row_arr['extra_class'] = 'Violation';
         $this->table->add_row( $row_arr );
      }

      // summary row
      $this->table->add_row( array(
            2 => $cnt_users,
            3 => $cnt_games,
            4 => T_('Sum#poolsum'),
            'extra_class' => 'Sum', ));

      return $this->table;
   }

   /*! \brief returns arr( pools-count, user-count, games-count ). */
   function get_counts()
   {
      $cnt_users = 0;
      $cnt_games = 0;
      foreach( $this->pool_summary as $pool => $arr )
      {
         list( $pool_usercount, $errors, $pool_games ) = $arr;
         $cnt_users += $pool_usercount;
         $cnt_games += $pool_games;
      }

      return array( count($this->pool_summary), $cnt_users, $cnt_games );
   }

} // end of 'PoolSummary'




 /*!
  * \class PoolViewer
  *
  * \brief Class to help in building GUI-representation of pools within tournament-round
  */

define('PVOPT_NO_RESULT',  0x01); // don't show cross-table with results
define('PVOPT_NO_COLCFG',  0x02); // don't load table-column-config
define('PVOPT_NO_EMPTY',   0x04); // don't show empty pools
define('PVOPT_EMPTY_SEL',  0x08); // show "selected" empty-pools (directly called with make_pool_table-func)
define('PVOPT_NO_TRATING', 0x10); // don't show T-rating (is same as user-rating)
define('PVOPT_EDIT_COL',   0x20); // add edit-actions table-column for pool-edit
define('PVOPT_EDIT_RANK',  0x40); // add edit-actions table-column for rank-edit

class PoolViewer
{
   var $ptabs; // PoolTables-object
   var $table; // Table-object

   var $my_id; // player_row['ID']
   var $options;
   var $edit_callback;

   var $pools_max_users;
   var $first_pool;
   var $poolidx; // start-index of result-matrix starting with 1

   /*! \brief Construct PoolViewer setting up Table-structure. */
   function PoolViewer( $tid, $page, $pool_tables, $pv_opts=0 )
   {
      global $player_row;

      $this->ptabs = $pool_tables;
      $this->my_id = $player_row['ID'];
      $this->options = (int)$pv_opts;
      $this->pools_max_users = $this->ptabs->count_pools_max_user();
      $this->first_pool = true;
      $this->edit_callback = null;

      if( $this->options & PVOPT_NO_COLCFG )
         $cfg_tblcols = null;
      else
      {
         $cfg_pages = ConfigPages::load_config_pages( $this->my_id, CFGCOLS_TOURNAMENT_POOL_VIEW );
         $cfg_tblcols = $cfg_pages->get_table_columns();
      }
      $hide_results = ( $this->options & PVOPT_NO_RESULT );

      $table = new Table( 'PoolViewer', $page, $cfg_tblcols, '', TABLE_NO_SORT|TABLE_NO_SIZE|TABLE_NO_THEAD );
      $table->use_show_rows(false);
      $table->add_external_parameters( new RequestParameters( array( 'tid' => $tid )), true );
      if( !$hide_results )
         $table->add_or_del_column();
      $this->table = $table;
   }

   function setEditCallback( $edit_callback )
   {
      $this->edit_callback = $edit_callback;
   }

   function init_table()
   {
      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      if( $this->options & PVOPT_EDIT_COL )
         $this->table->add_tablehead( 8, T_('Actions#pool_header'), 'Image', TABLE_NO_HIDE );
      $this->table->add_tablehead( 1, T_('Name#header'), 'User', 0 );
      $this->table->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE );
      $this->table->add_tablehead( 3, T_('User Rating#pool_header'), 'Rating', 0 );
      if( !($this->options & PVOPT_NO_TRATING) )
         $this->table->add_tablehead( 4, T_('Tournament Rating#pool_header'), 'Rating', 0 );
      $this->table->add_tablehead( 5, T_('Country#pool_header'), 'Image', 0 );

      $idx = 13;
      $this->poolidx = $idx - 1;
      if( !($this->options & PVOPT_NO_RESULT) )
      {
         $this->table->add_tablehead( 6, T_('Place#pool_header'), 'NumberC', TABLE_NO_HIDE );

         foreach( range(1, $this->pools_max_users) as $pool )
            $this->table->add_tablehead( $idx++, $pool, 'Matrix', TABLE_NO_HIDE );

         $this->table->add_tablehead( 9, T_('#Wins#pool_header'), 'NumberC', 0 );
         $this->table->add_tablehead( 7, T_('Points#pool_header'), 'NumberC', TABLE_NO_HIDE );
         $this->table->add_tablehead(10, T_('SODOS#pool_header'), 'NumberC', 0 );
         $this->table->add_tablehead(11, T_('Rank#pool_header'), 'TRank', TABLE_NO_HIDE );
         $this->table->add_tablehead(12, '', '', TABLE_NO_HIDE );
      }
      if( ($this->options & PVOPT_EDIT_RANK) && !($this->options & PVOPT_EDIT_COL) )
         $this->table->add_tablehead( 8, T_('Edit#pool_header'), 'Image', TABLE_NO_HIDE );
   }

   /*! \brief Makes table for all pools. */
   function make_table()
   {
      $this->first_pool = true;

      // unassigned users first
      if( isset($this->ptabs->pools[0]) )
         $this->make_pool_table( 0 );

      foreach( $this->ptabs->pools as $pool => $arr_users )
      {
         if( $pool > 0 )
            $this->make_pool_table( $pool, 0 );
      }
   }

   /*!
    * \brief Makes table for single pool.
    * \param $opts optional: PVOPT_EMPTY_SEL
    */
   function make_pool_table( $pool, $opts=0 )
   {
      global $base_path;

      $arr_users = $this->ptabs->pools[$pool];
      $map_usercols = $this->ptabs->get_user_col_map($pool);
      $cnt_users = count($arr_users);
      $show_results = ( $pool > 0 ) && !( $this->options & PVOPT_NO_RESULT );
      $show_trating = !( $this->options & PVOPT_NO_TRATING );
      $show_edit_col = ( $this->options & (PVOPT_EDIT_COL|PVOPT_EDIT_RANK) ) && !is_null($this->edit_callback);

      // header
      if( !$this->first_pool )
         $this->table->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
      if( $cnt_users )
      {
         $this->table->add_row_title(
            ($pool == 0) ? T_('Users without pool assignment') : sprintf( T_('Pool %s'), $pool) );
         if( $this->first_pool )
            $this->table->add_row_thead();
         else
            $this->table->add_row_thead( array( 'mode1' => TABLE_NO_HIDE ));
      }
      $this->first_pool = false;
      if( $cnt_users == 0 ) // empty-pool
      {
         if( !($this->options & PVOPT_NO_EMPTY) || ($opts & PVOPT_EMPTY_SEL) )
            $this->table->add_row_title( sprintf( T_('Pool %s (empty)'), $pool ) );
         return;
      }

      // crosstable
      $idx = 0;
      foreach( $arr_users as $uid )
      {
         $tpool = $this->ptabs->users[$uid];
         $user = $tpool->User;
         $idx++;

         $row_arr = array(
            2 => user_reference( REF_LINK, 1, '', $uid, $user->Handle, ''),
            6 => $idx,
            7 => $tpool->Points,
            9 => $tpool->Wins . ' : ' . $tpool->Losses,
            10 => $tpool->SODOS,
            11 => $tpool->formatRank( /*incl-CalcRank*/true ),
            12 => $tpool->echoRankImage(),
         );
         if( $this->table->Is_Column_Displayed[1] )
            $row_arr[1] = $user->Name;
         if( $this->table->Is_Column_Displayed[3] )
            $row_arr[3] = echo_rating( $user->Rating, true, $uid );
         if( $show_trating && $this->table->Is_Column_Displayed[4] )
            $row_arr[4] = echo_rating( @$user->urow['TP_Rating'], true, $uid );
         if( $this->table->Is_Column_Displayed[5] )
            $row_arr[5] = getCountryFlagImage( $user->Country );

         if( $show_results )
         {
            $row_arr[$this->poolidx + $idx] = Table::build_row_cell( 'X', 'MatrixSelf' );
            if( $cnt_users < $this->pools_max_users ) // too few users in pool
               $row_arr += array_fill( $this->poolidx + $cnt_users + 1, $this->pools_max_users - $cnt_users, '-' );

            // add game-results
            // - X=self, 0=lost, 1=jigo, 2=won, #=running-game;; for Hahn use score instead +-score
            // - link to running/finished game
            foreach( $tpool->PoolGames as $poolGame )
            {
               $game_url = $base_path."game.php?gid=".$poolGame->gid;
               $col = $map_usercols[$poolGame->get_opponent($uid)];

               list( $score, $points, $mark, $title, $style ) = $poolGame->calc_result( $uid );
               $cell = anchor( $game_url, $mark, $title );
               if( !is_null($style) )
                  $cell = Table::build_row_cell( $cell, $style );

               $row_arr[$this->poolidx + $col] = $cell;
            }

            // mark line of current-user
            if( $uid == $this->my_id )
            {
               foreach( range(1,$this->poolidx) as $cell )
               {
                  if( isset($row_arr[$cell]) )
                     $row_arr[$cell] = Table::build_row_cell($row_arr[$cell], 'TourneyUser');
               }
            }
         }

         if( $show_edit_col && !is_null($this->edit_callback) )
            $row_arr[8] = call_user_func_array( $this->edit_callback,
               array( $this, $uid ) ); // call vars by ref

         $this->table->add_row( $row_arr );
      }
   }//make_pool_table

   /*! \brief Prints built table, need make_table() first. */
   function echo_table()
   {
      echo $this->table->echo_table();
   }

} // end of 'PoolViewer'




 /*!
  * \class RankSummary
  *
  * \brief Utility methods for rank-count-summary for one tournament-round
  */
class RankSummary
{
   var $rank_summary; // rank|TPOOLRK_NO_RANK/RETREAT => [ count, NextRound-count ]
   var $tp_count;
   var $table; // Table-object

   /*!
    * \brief Constructs RankSummary.
    * \param $rank_counts [ TournamentPool.Rank => count, ... ]
    */
   function RankSummary( $page, $rank_counts, $tp_count=0 )
   {
      $rstable = new Table( 'TPoolSummary', $page, null, 'rs',
         TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $rstable->add_tablehead( 1, T_('Rank#ranksum_header'), 'TRank' );
      $rstable->add_tablehead( 2, T_('Count#ranksum_header'), 'NumberC' );
      $rstable->add_tablehead( 3, T_('NR-Count#ranksum_header'), 'NumberC' );
      $this->table = $rstable;

      $arr = array();
      foreach( $rank_counts as $rank => $count )
      {
         $key = ( $rank < TPOOLRK_RANK_ZONE ) ? TPOOLRK_NO_RANK : abs($rank);
         if( !isset($arr[$key]) )
            $arr[$key] = array( 0, 0 );
         $arr[$key][0] += $count;
         if( $rank > 0 )
            $arr[$key][1] += $count;
      }
      $this->rank_summary = $arr;
      $this->tp_count = (int)$tp_count;
   }

   function get_ranks()
   {
      $arr = array();
      foreach( $this->rank_summary as $rank => $tmp )
      {
         if( $rank >= 0 )
            $arr[] = $rank;
      }
      sort( $arr, SORT_NUMERIC );
      return $arr;
   }

   function build_notes()
   {
      $notes = array();
      $notes[] = T_('Rank \'TP\' = tournament-participants registered for next-round');
      $notes[] = sprintf( T_('Rank \'%s\' = rank not set yet for pool-users'), T_('unset#ranksum') );
      $notes[] = sprintf( T_('%s = count of users with given rank set'), T_('Count#ranksum_header') );
      $notes[] = sprintf( T_('%s = count of users advancing to Next-Round, or marked for final result'),
         T_('NR-Count#ranksum_header') );
      return $notes;
   }

   function make_table_rank_summary()
   {
      uksort( $this->rank_summary, array($this, '_compare_ranks') );

      // TP-count
      $this->table->add_row( array(
            1 => 'TP',
            2 => NO_VALUE,
            3 => ($this->tp_count ? $this->tp_count : ''),
         ));
      $count_nextround = $this->tp_count;
      $count_users = 0;

      foreach( $this->rank_summary as $rank => $arr ) // arr = count_rank, count_nextround
      {
         $count_nextround += $arr[1];
         $count_users += $arr[0];
         $this->table->add_row( array(
               1 => ($rank < TPOOLRK_RANK_ZONE) ? T_('unset#ranksum') : $rank,
               2 => $arr[0],
               3 => ($arr[1] ? $arr[1] : ''),
            ));
      }

      // summary row
      $this->table->add_row( array(
            1 => T_('Sum#ranksum'),
            2 => $count_users,
            3 => $count_nextround,
            'extra_class' => 'Sum', ));

      return $this->table;
   }//make_table_rank_summary

   function _compare_ranks( $a, $b )
   {
      // rank order: 1, 2, 3, ..., 0, -100
      $a2 = ($a != 0) ? abs($a) : -TPOOLRK_RANK_ZONE;
      $b2 = ($b != 0) ? abs($b) : -TPOOLRK_RANK_ZONE;
      return cmp_int( $a2, $b2 );
   }

} // end of 'RankSummary'

?>

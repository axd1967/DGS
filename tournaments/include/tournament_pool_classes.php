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

} // end of 'PoolGame'




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
      while( list(,$arr_item) = $tgames_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;

         $game_score = ($tgame->isScoreStatus()) ? $tgame->Score : null;
         $poolGame = new PoolGame( $tgame->Challenger_uid, $tgame->Defender_uid, $tgame->gid, $game_score );
         $this->users[$tgame->Challenger_uid]->PoolGames[] = $poolGame;
         $this->users[$tgame->Defender_uid]->PoolGames[] = $poolGame;
      }

      //TODO how to calc Rank=Place efficiently? 1. order uid-list by TPool.Points+TieBreakers, 2. (later) if no Points(<0) get order of uid or TPool-ID or Rating instead
      //TODO -> re-order this->pools
   }

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
define('PVOPT_EDIT_COL',   0x20); // add edit-actions table-column

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

      $idx = 11;
      $this->poolidx = $idx - 1;
      if( !($this->options & PVOPT_NO_RESULT) )
      {
         $this->table->add_tablehead( 6, T_('Place#pool_header'), 'NumberC', TABLE_NO_HIDE );

         foreach( range(1, $this->pools_max_users) as $pool )
            $this->table->add_tablehead( $idx++, $pool, 'Matrix', TABLE_NO_HIDE );

         $this->table->add_tablehead( 7, T_('Points#header'), 'Number', TABLE_NO_HIDE );
      }

   }

   /*! \brief Prints build table, need make_table() first. */
   function echo_table()
   {
      echo $this->table->echo_table();
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
      $show_edit_col = ( $this->options & PVOPT_EDIT_COL ) && !is_null($this->edit_callback);

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
            7 => 0, //TODO $tpool->Points,
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

               //TODO mark color + X=self, 0=lost, 1=jigo, 2=won;; for Hahn use score instead +-score
               //TODO $cell = Table::build_row_cell( anchor( $game_url, '#', T_('Game running') ), 'MatrixWon/Lost/Jigo' );
               $cell = anchor( $game_url, '#', T_('Game running') );

               $row_arr[$this->poolidx + $col] = $cell;
            }

            // mark line of current-user
            if( $uid == $this->my_id )
            {
               foreach( range(1,7) as $cell )
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

} // end of 'PoolViewer'

?>

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
         $this->Score = -$new_score;
      }
      $this->gid = (int)$gid;
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
   /*! \brief list of pool-numbers with list of pool-users: map( poolNo => [ uid, ... ] ) */
   var $pools;

   function PoolTables( $count_pools )
   {
      $this->users = array();
      $this->pools = array();
      foreach( range(1, $count_pools) as $pool )
         $this->pools[$pool] = array();
   }

   function fill_pools( $tpool_iterator )
   {
      while( list(,$arr_item) = $tpool_iterator->getListIterator() )
      {
         list( $tpool, $orow ) = $arr_item;
         $uid = $tpool->uid;
         $pool = $tpool->Pool;

         $this->users[$uid] = $tpool;
         $this->pools[$pool][] = $uid;
      }
   }

   function fill_games( $tgames_iterator )
   {
      while( list(,$arr_item) = $tgames_iterator->getListIterator() )
      {
         list( $tgame, $orow ) = $arr_item;

         $game_score = ($tgame->isScoreStatus()) ? $tgame->Score : null;
         $poolGame = new PoolGame( $tgame->Challenger_uid, $tgame->Defender_uid, $tgame->gid, $game_score );
         //TODO fill-games
      }

      //TODO how to calc Rank=Place efficiently? 1. order uid-list by TPool.Points+TieBreakers, 2. (later) if no Points(<0) get order of uid or TPool-ID or Rating instead
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

} // end of 'PoolTables




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

class PoolViewer
{
   var $ptabs; // PoolTables-object
   var $table; // Table-object

   var $my_id; // player_row['ID']
   var $options;

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

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $table->add_tablehead( 1, T_('Name#header'), 'User', 0 );
      $table->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE );
      $table->add_tablehead( 3, T_('User Rating#pool_header'), 'Rating', 0 );
      if( !($this->options & PVOPT_NO_TRATING) )
         $table->add_tablehead( 4, T_('Tournament Rating#pool_header'), 'Rating', 0 );
      $table->add_tablehead( 5, T_('Country#pool_header'), 'Image', 0 );

      $idx = 11;
      $this->poolidx = $idx - 1;
      if( !$hide_results )
      {
         $table->add_tablehead( 6, T_('Place#pool_header'), 'NumberC', TABLE_NO_HIDE );

         foreach( range(1, $this->pools_max_users) as $pool )
            $table->add_tablehead( $idx++, $pool, 'Matrix', TABLE_NO_HIDE );

         $table->add_tablehead( 7, T_('Points#header'), 'Number', TABLE_NO_HIDE );
         //$table->add_tablehead( 8, T_//('TieBreaker1#header'), 'Number' );
      }

      $this->table = $table;
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
      $arr_users = $this->ptabs->pools[$pool];
      $cnt_users = count($arr_users);
      $show_results = ( $pool > 0 ) && !( $this->options & PVOPT_NO_RESULT );
      $show_trating = !( $this->options & PVOPT_NO_TRATING );

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

            //TODO add game-results
            // X=self, 0=lost, 1=jigo, 2=won, #=running-game;; for Hahn use score instead +-score
            // link to running/finished game

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

         $this->table->add_row( $row_arr );
      }
   }//make_pool_table

} // end of 'PoolViewer'

?>

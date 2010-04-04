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

require_once 'include/table_columns.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/std_classes.php';
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
   }

   /*! \brief Counts max. of users pro pool for all pools. */
   function count_pools_max_user()
   {
      $max_users = 0;
      foreach( $this->pools as $pool => $arr_users )
         $max_users = max( $max_users, count($arr_users) );
      return $max_users;
   }

} // end of 'PoolTables




 /*!
  * \class PoolViewer
  *
  * \brief Class to help in building GUI-representation of pools within tournament-round
  */
class PoolViewer
{
   var $ptabs; // PoolTables-object
   var $table; // Table-object

   var $my_id; // player_row['ID']
   var $pools_max_users;
   var $first_pool;
   var $poolidx; // start-index of result-matrix starting with 1

   /*! \brief Construct PoolViewer setting up Table-structure. */
   function PoolViewer( $tid, $page, $pool_tables )
   {
      global $player_row;

      $this->ptabs = $pool_tables;
      $this->pools_max_users = $this->ptabs->count_pools_max_user();
      $this->my_id = $player_row['ID'];

      $cfg_pages = ConfigPages::load_config_pages( $this->my_id, CFGCOLS_TOURNAMENT_POOL_VIEW );
      $cfg_tblcols = $cfg_pages->get_table_columns();

      $table = new Table( 'PoolViewer', $page, $cfg_tblcols, '', TABLE_NO_SORT|TABLE_NO_SIZE|TABLE_NO_THEAD );
      $table->use_show_rows(false);
      $table->add_external_parameters( new RequestParameters( array( 'tid' => $tid )), true );
      $table->add_or_del_column();

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $table->add_tablehead( 1, T_('Name#header'), 'User', 0 );
      $table->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE );
      $table->add_tablehead( 3, T_('User Rating#pool_header'), 'Rating', 0 );
      $table->add_tablehead( 4, T_('Tournament Rating#pool_header'), 'Rating', 0 );
      $table->add_tablehead( 5, T_('Country#pool_header'), 'Image', 0 );
      $table->add_tablehead( 6, T_('Place#pool_header'), 'NumberC', TABLE_NO_HIDE );

      $idx = 11;
      $this->poolidx = $idx - 1;
      foreach( range(1, $this->pools_max_users) as $pool )
         $table->add_tablehead( $idx++, $pool, 'Matrix', TABLE_NO_HIDE );

      $table->add_tablehead( 7, T_('Points#header'), 'Number', TABLE_NO_HIDE );
      //$table->add_tablehead( 8, T_//('TieBreaker1#header'), 'Number' );

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
      foreach( $this->ptabs->pools as $pool => $arr_users )
      {
         $this->make_pool_table( $pool );
      }
   }

   /*! \brief Makes table for single pool. */
   function make_pool_table( $pool )
   {
      // header
      if( !$this->first_pool )
         $this->table->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
      $this->table->add_row_title( sprintf( T_('Pool %s'), $pool ) );
      if( $this->first_pool )
         $this->table->add_row_thead();
      else
         $this->table->add_row_thead( array( 'mode1' => TABLE_NO_HIDE ));

      // crosstable
      $idx = 0;
      $arr_users = $this->ptabs->pools[$pool];
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
         if( $this->table->Is_Column_Displayed[4] )
            $row_arr[4] = echo_rating( $user->Rating, true, $uid ); //TODO use T-rating (load only if needed)
         if( $this->table->Is_Column_Displayed[5] )
            $row_arr[5] = getCountryFlagImage( $user->Country );

         $row_arr[$this->poolidx + $idx] = Table::build_row_cell( 'X', 'MatrixSelf' );

         //TODO add game-results

         // mark line of current-user
         if( $uid == $this->my_id )
         {
            foreach( range(1,7) as $cell )
            {
               if( isset($row_arr[$cell]) )
                  $row_arr[$cell] = Table::build_row_cell($row_arr[$cell], 'TourneyUser');
            }
         }

         $this->table->add_row( $row_arr );
      }

      $this->first_pool = false;
   }

} // end of 'PoolViewer'

?>

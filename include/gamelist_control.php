<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/db/games.php';

define('GAMEVIEW_RUNNING', 0);
define('GAMEVIEW_FINISHED', 1);
define('GAMEVIEW_OBSERVE_MINE', 2);
define('GAMEVIEW_OBSERVE_ALL', 3);
define('GAMEVIEW_STATUS', 4);
define('GAMEVIEW_INFO', 5);


 /*!
  * \class GameListControl
  *
  * \brief Controller-Class to handle game-list.
  */
class GameListControl
{
   var $view; // GAMEVIEW_...
   var $view_all; // bool (has precedence over view_uid), only for finished/running-games
   var $view_uid; // $uid

   var $my_id;
   var $is_quick; // quick-suite?

   var $load_notes;
   var $mp_game;
   var $ext_tid;

   function GameListControl( $quick_suite=false )
   {
      global $player_row;
      $this->my_id = (int)$player_row['ID'];
      $this->is_quick = $quick_suite;

      $this->view = GAMEVIEW_RUNNING; // default: my running games
      $this->view_all = false;
      $this->view_uid = $this->my_id;

      $this->load_notes = false;
      $this->mp_game = 0;
      $this->ext_tid = 0;
   }

   /*!
    * \brief Sets view and uid for GameListControl.
    * \param $view one of GAMEVIEW_...
    * \param $view_uid 'all' or numeric uid
    */
   function setView( $view, $view_uid )
   {
      $this->view = $view;
      $this->view_uid = $this->my_id; // default

      if( $this->is_observe() )
         $this->view_all = false; // only for finished/running
      elseif( $this->is_status() || $this->is_info() )
         $this->view_all = false;
      else
      {
         if( $view_uid === 'all' )
            $this->view_all = true;
         else
         {
            $this->view_all = false;
            $this->view_uid = (is_numeric($view_uid) && $view_uid > 0) ? $view_uid : $this->my_id;
         }
      }
   }//setView

   function is_observe() // OA+OU
   {
      return ($this->view == GAMEVIEW_OBSERVE_MINE || $this->view == GAMEVIEW_OBSERVE_ALL);
   }

   function is_running() // RA+RU
   {
      return ($this->view == GAMEVIEW_RUNNING);
   }

   function is_finished() // FA+FU
   {
      return ($this->view == GAMEVIEW_FINISHED);
   }

   function is_all() // FA+RA
   {
      return $this->view_all;
   }

   function is_observe_all() // OA
   {
      return ($this->view == GAMEVIEW_OBSERVE_ALL);
   }

   function is_status() // ST
   {
      return ($this->view == GAMEVIEW_STATUS);
   }

   function is_info() // INFO
   {
      return ($this->view == GAMEVIEW_INFO);
   }


   /*! \brief Returns QuerySQL for games-list. */
   function build_games_query( $load_user_ratingdiff, $load_remaining_time )
   {
      if( $this->is_status() )
         error('invalid_action', "GameListControl.build_games_query({$this->view},{$this->view_all},{$this->view_uid})");

      $my_id = $this->my_id;
      $uid = $this->view_uid;
      $is_finished = $this->is_finished(); // FU+FA
      $is_running = $this->is_running();  // RU+RA

      $need_ticks = $need_ratingdiff = $need_bw_user_info = false;

      $qsql = new QuerySQL( SQLP_FIELDS, // std-fields
         'G.*',
         'G.Flags+0 AS X_GameFlags',
         'UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged',
         'UNIX_TIMESTAMP(G.Starttime) AS X_Starttime',
         "IF(G.Rated='N','N','Y') AS X_Rated" );

      if( $this->is_observe() ) //OB
      {
         $need_bw_user_info = $need_ticks = true;
         $qsql->add_part( SQLP_FROM,
            'Observers AS Obs',
            'INNER JOIN Games AS G ON G.ID=Obs.gid' );

         if( $this->is_observe_all() ) //OA
         {
            $qsql->add_part( SQLP_FIELDS,
               'COUNT(Obs.uid) AS X_ObsCount',
               "IF(G.Black_ID=$my_id OR G.White_ID=$my_id,'Y','N') AS X_MeObserved" );
            $qsql->add_part( SQLP_GROUP, 'Obs.gid' );
         }
         else //OU
         {
            $qsql->add_part( SQLP_WHERE, 'Obs.uid=' . $my_id );
         }
      }
      elseif( $this->is_all() ) //FA+RA
      {
         $need_bw_user_info = true;
         $qsql->add_part( SQLP_FROM, 'Games AS G' );

         if( $is_finished ) //FA
         {
            $need_ratingdiff = true;
            $qsql->add_part( SQLP_FIELDS,
               'Black_End_Rating AS blackEndRating',
               'White_End_Rating AS whiteEndRating' );
            $qsql->add_part( SQLP_WHERE, "G.Status='".GAME_STATUS_FINISHED."'" );
         }
         elseif( $is_running ) //RA
            $qsql->add_part( SQLP_WHERE, 'G.Status' . IS_STARTED_GAME );
      }
      elseif( $this->is_info() ) // INFO
      {
         $need_bw_user_info = $need_ticks = $need_ratingdiff = true;
         $qsql->add_part( SQLP_FROM, 'Games AS G' );
         GameHelper::extend_query_with_game_prio( $qsql, $uid, true, 'G' );
      }
      else //FU+RU ?UNION
      {
         $qsql->add_part( SQLP_FROM, 'Games AS G' );

         if( $this->is_quick )
            $need_bw_user_info = true;
         else
         {
            $qsql->add_part( SQLP_FIELDS,
               'Opp.Name AS oppName',
               'Opp.Handle AS oppHandle',
               'Opp.ID AS oppID',
               'Opp.Rating2 AS oppRating',
               "IF(G.Black_ID=$uid, G.White_Start_Rating, G.Black_Start_Rating) AS oppStartRating",
               "IF(G.Black_ID=$uid, G.Black_Start_Rating, G.White_Start_Rating) AS userStartRating",
               "IF(G.Black_ID=$uid, $uid, G.White_ID) AS userID",
               'UNIX_TIMESTAMP(Opp.Lastaccess) AS oppLastaccess' );
            $qsql->add_part( SQLP_FROM, 'Players AS Opp' );
         }

         $qsql->add_part( SQLP_FIELDS,
            //extra bits of Color are for sorting purposes
            //b0= White to play, b1= I am White, b4= not my turn, b5= bad or no ToMove info
            "IF(G.ToMove_ID=$uid,0,0x10)+IF(G.White_ID=$uid,2,0)+"
               . "IF(G.White_ID=G.ToMove_ID,1,IF(G.Black_ID=G.ToMove_ID,0,0x20)) AS X_Color" );

         if( $this->mp_game )
         {
            $qsql->add_part( SQLP_FROM, 'INNER JOIN GamePlayers AS GP ON GP.gid=G.ID' );
            $qsql->add_part( SQLP_WHERE, "GP.uid=$uid" );
         }

         if( $this->load_notes && $uid == $my_id ) //FU+RU ?UNION
            GameHelper::extend_query_with_game_notes( $qsql, $my_id, 'G' );

         if( $is_finished ) //FU ?UNION
         {
            $qsql->add_part( SQLP_WHERE, "G.Status='".GAME_STATUS_FINISHED."'" );

            if( $this->is_quick )
               $need_ratingdiff = $load_user_ratingdiff;
            else
            {
               if( $this->mp_game )
               {
                  $qsql->add_part( SQLP_FIELDS,
                     "G.Score AS X_Score", // seen as Black
                     -OUT_OF_RATING." AS oppEndRating", // opp is always user
                     -OUT_OF_RATING." AS userEndRating" );
               }
               else
               {
                  $qsql->add_part( SQLP_FIELDS,
                     "IF(G.Black_ID=$uid, -G.Score, G.Score) AS X_Score",
                     "IF(G.Black_ID=$uid, G.White_End_Rating, G.Black_End_Rating) AS oppEndRating",
                     "IF(G.White_ID=$uid, G.White_End_Rating, G.Black_End_Rating) AS userEndRating" );
               }
               $qsql->add_part( SQLP_FIELDS, 'oppRlog.RatingDiff AS oppRatingDiff' );
               $qsql->add_part( SQLP_FROM,
                  "LEFT JOIN Ratinglog AS oppRlog ON oppRlog.gid=G.ID AND oppRlog.uid=G.White_ID+G.Black_ID-$uid" );

               if( $load_user_ratingdiff && !$this->mp_game ) // opp is always user for MP-game
               {
                  $qsql->add_part( SQLP_FIELDS, 'userRlog.RatingDiff AS userRatingDiff' );
                  $qsql->add_part( SQLP_FROM, "LEFT JOIN Ratinglog AS userRlog ON userRlog.gid=G.ID AND userRlog.uid=$uid" );
               }
            }
         }
         elseif( $is_running ) //RU ?UNION
         {
            $qsql->add_part( SQLP_WHERE, 'G.Status' . IS_STARTED_GAME );

            if( $load_remaining_time ) //RU
               $need_ticks = true;
         }

         if( $this->mp_game )
         {
            if( !$this->is_quick )
               $qsql->add_part( SQLP_WHERE, "Opp.ID=$uid" );
         }
         else
         {
            if( $this->is_quick )
               $qsql->add_part( SQLP_UNION_WHERE, "G.White_ID=$uid", "G.Black_ID=$uid" );
            else
            {
               $qsql->add_part( SQLP_UNION_WHERE,
                  "G.White_ID=$uid AND Opp.ID=G.Black_ID",
                  "G.Black_ID=$uid AND Opp.ID=G.White_ID" );
            }
            $qsql->useUnionAll();
         }
      }//FU+RU

      if( $need_bw_user_info )
         GameListControl::extend_game_list_query_with_user_info( $qsql, 'G' );
      if( $need_ticks )
      {
         $qsql->add_part( SQLP_FIELDS, "COALESCE(Clock.Ticks,0) AS X_Ticks" );
         $qsql->add_part( SQLP_FROM, "LEFT JOIN Clock ON Clock.ID=G.ClockUsed" );
      }
      if( $need_ratingdiff )
      {
         $qsql->add_part( SQLP_FIELDS,
            'blog.RatingDiff AS blackDiff',
            'wlog.RatingDiff AS whiteDiff' );
         $qsql->add_part( SQLP_FROM,
            'LEFT JOIN Ratinglog AS blog ON blog.gid=G.ID AND blog.uid=G.Black_ID',
            'LEFT JOIN Ratinglog AS wlog ON wlog.gid=G.ID AND wlog.uid=G.White_ID' );
      }

      if( $this->ext_tid )
      {
         $qsql->add_part( SQLP_FIELDS,
            'TG.Status AS TG_Status',
            'TG.Flags AS TG_Flags',
            "IF(T.Type='".TOURNEY_TYPE_LADDER."',IF(TG.Challenger_uid=$uid,1,0),-1) AS TG_Challenge" );
         $qsql->add_part( SQLP_FROM,
            'LEFT JOIN TournamentGames AS TG ON TG.gid=G.ID',
            'INNER JOIN Tournament AS T ON T.ID=G.tid' );
         $qsql->add_part( SQLP_WHERE,
            "G.tid={$this->ext_tid}" );
      }

      return $qsql;
   }//build_games_query


   // ------------ static functions ----------------------------

   function build_game_list_query_status_view( $uid, $load_notes, $load_prio, $is_mpg, $ext_tid )
   {
      global $player_row;
      $my_id = $player_row['ID'];
      $next_game_order = $player_row['NextGameOrder'];

      $qsql = NextGameOrder::build_status_games_query(
         $uid, IS_STARTED_GAME, $next_game_order, /*ticks*/true, $load_prio, $load_notes );

      if( $is_mpg )
         $qsql->add_part( SQLP_WHERE, "Games.GamePlayers>''" ); // '' = std-go

      if( ALLOW_TOURNAMENTS && is_numeric($ext_tid) && $ext_tid > 0 )
         $qsql->add_part( SQLP_WHERE, "Games.tid=$ext_tid" );

      return $qsql;
   }//build_game_list_query_status_view

   function extend_game_list_query_with_user_info( &$qsql, $tablename='Games' )
   {
      $qsql->add_part( SQLP_FIELDS,
         'black.ID AS blackID',
         'black.Handle AS blackHandle',
         'black.Name AS blackName',
         'black.Rating2 AS blackRating2',
         'black.Country AS blackCountry',
         'UNIX_TIMESTAMP(black.Lastaccess) AS blackX_Lastaccess',
         'white.ID AS whiteID',
         'white.Handle AS whiteHandle',
         'white.Name AS whiteName',
         'white.Rating2 AS whiteRating2',
         'white.Country AS whiteCountry',
         'UNIX_TIMESTAMP(white.Lastaccess) AS whiteX_Lastaccess' );
      $qsql->add_part( SQLP_FROM,
         "INNER JOIN Players AS black ON black.ID=$tablename.Black_ID",
         "INNER JOIN Players AS white ON white.ID=$tablename.White_ID" );
   }//extend_game_list_query_with_user_info

} // end of 'GameListControl'

?>

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

require_once 'include/std_classes.php';
require_once 'include/db/bulletin.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_round_helper.php';

 /*!
  * \file tournament_league_helper.php
  *
  * \brief General functions to support tournament management of league tournaments with db-access.
  */


 /*!
  * \class TournamentLeagueHelper
  *
  * \brief Helper-class for league-like tournaments with mostly static functions
  *        to support Tournament management with db-access combining forces of different tournament-classes.
  */
class TournamentLeagueHelper
{

   /*!
    * \brief Sets relegations for finished pools updating TournamentPool.Rank+Flags for given tourney-round.
    * \note also executes TournamentRoundHelper::fill_ranks_tournament_pool() to finish pools.
    * \param $tround TournamentRound-object
    * \return array of actions taken
    */
   public static function fill_relegations_tournament_pool( $tlog_type, $tround, $tourney_type )
   {
      $tid = $tround->tid;
      $result = TournamentRoundHelper::fill_ranks_tournament_pool( $tlog_type, $tround, $tourney_type );

      $cnt_upd = TournamentPool::update_tournament_pool_set_relegations( $tround );
      $result[] = sprintf( T_('Relegations of %s players have been set for finished pools.'), $cnt_upd );

      if ( $cnt_upd > 0 )
         TournamentLogHelper::log_fill_tournament_pool_relegations( $tid, $tlog_type, $tround, $cnt_upd );

      return $result;
   }//fill_relegations_tournament_pool

} // end of 'TournamentLeagueHelper'

?>

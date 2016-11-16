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
require_once 'tournaments/include/tournament_factory.php';
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


   /*!
    * \brief Spawns tournament for next cycle of league-tournament.
    * \return new Tournament.ID on success; 0 on failure
    */
   public static function spawn_next_cycle( $tlog_type, $tourney, &$errors, $check_only )
   {
      static $ARR_TSTATUS = array( TOURNEY_STATUS_PLAY );
      global $base_path;

      $src_tid = (int)$tourney->ID;
      if ( $tourney->Type != TOURNEY_TYPE_LEAGUE )
         error('invalid_args', "TLH:spawn_next_cycle.check.ttype_only_league($src_tid)");
      $ttype = TournamentFactory::getTournament($tourney->WizardType);

      $errors = array();
      if ( !in_array($tourney->Status, $ARR_TSTATUS) )
         $errors[] = sprintf( T_('Spawning tournament for next cycle of league-tournament is only allowed on tournament status [%s].'),
            build_text_list('Tournament::getStatusText', $ARR_TSTATUS) );
      if ( $tourney->Next_tid > 0 )
         $errors[] = sprintf( T_('Spawning tournament for next cycle is not allowed. It already has a next cycle (%s).'),
            anchor( $base_path."tournaments/manage_tournament.php?tid={$tourney->Next_tid}", "#{$tourney->Next_tid}" ) );

      if ( count($errors) || $check_only )
         return 0;

      ta_begin();
      {//HOT-section to spawn next cycle (tournament)
         $new_tid = $ttype->copyTournament( $tlog_type, $src_tid );
         if ( $new_tid > 0 ) // success
         {
            $link_success = self::link_tournaments( $tlog_type, $src_tid, $new_tid );

            TournamentLogHelper::log_spawn_next_cycle( $src_tid, $tlog_type, $new_tid, $link_success );

            if ( $link_success )
            {
               $log_msg = "Link tournaments T#$src_tid -> T#$new_tid";
               TournamentLogHelper::log_link_tournament( $tlog_type, $src_tid, $log_msg );
               TournamentLogHelper::log_link_tournament( $tlog_type, $new_tid, $log_msg );
            }
            else
               $errors[] = sprintf( T_('Linking with successfully spawned tournament (#%s -> #%s) failed. Please contact a tournament-admin.'),
                  $src_tid, $new_tid );
         }
         else
            $errors[] = T_('Spawning tournament for next cycle of league-tournament failed.');
      }
      ta_end();

      return $new_tid;
   }//spawn_next_cycle

   /*!
    * \brief Links tournaments (on T-source set T.Next_tid, on T-target set T.Prev_tid).
    * \return true = success; false otherwise
    */
   private static function link_tournaments( $tlog_type, $src_tid, $trg_tid )
   {
      $src_tid = (int)$src_tid;
      $trg_tid = (int)$trg_tid;

      ta_begin();
      {//HOT-section to link tournaments
         $success = Tournament::update_tournament_links( $src_tid, -1, $trg_tid );
         if ( $success )
            $success = Tournament::update_tournament_links( $trg_tid, $src_tid, -1 );
      }
      ta_end();

      return $success;
   }//link_tournaments


} // end of 'TournamentLeagueHelper'

?>

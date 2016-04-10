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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';
require_once 'tournaments/include/tournament_limits.php';
require_once 'tournaments/include/tournament_template_round_robin.php';

require_once 'tournaments/include/tournament_properties.php';
require_once 'tournaments/include/tournament_rules.php';
require_once 'tournaments/include/tournament_round.php';

 /*!
  * \file public_round_robin.php
  *
  * \brief Classes and functions to handle (pooled) Round-Robin-typed tournament.
  */


 /*!
  * \class PublicRoundRobinTournament
  *
  * \brief Template-pattern for official "DGS Round-Robin"-tournament
  */
class PublicRoundRobinTournament extends TournamentTemplateRoundRobin
{
   public function __construct()
   {
      parent::__construct( TOURNEY_WIZTYPE_PUBLIC_ROUNDROBIN,
         T_('Public Round-Robin with single round and one pool#tourney'),
         TOURNEY_TITLE_GAME_RESTRICTION );

      // overwrite tournament-type-specific properties
      $this->need_admin_create_tourney = false;
      $this->limits->setLimits( TLIMITS_MAX_TP, true, 3, 16 );
      $this->limits->setLimits( TLIMITS_TRD_MAX_ROUNDS, false, 1 );
      $this->limits->setLimits( TLIMITS_TRD_MIN_POOLSIZE, false, 3, 16 );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLSIZE, false, 3, 16 );
      $this->limits->setLimits( TLIMITS_TRD_MAX_POOLCOUNT, true, 1 );
      $this->limits->setLimits( TLIMITS_TRD_TP_MAX_GAMES, false, 0, 18 ); // 10 TP max for DOUBLE-htype
      $this->limits->setLimits( TLIMITS_TPR_RATING_USE_MODE, false, TLIM_TPR_RUM_NO_COPY_CUSTOM );
   }

   public function createTournament()
   {
      global $player_row;
      $title = sprintf("%s's mini-tournament (19x19)", $player_row['Handle']);
      $tourney = $this->make_tournament( TOURNEY_SCOPE_PUBLIC, $title );

      $tprops = new TournamentProperties();
      $tprops->MinParticipants = 3;
      $tprops->MaxParticipants = 8;
      $tprops->MaxStartRound = 1;
      $tprops->setMinRatingStartRound( NO_RATING );

      $trules = new TournamentRules();
      $trules->Size = 19;
      $trules->Handicaptype = TRULE_HANDITYPE_NIGIRI;

      $tpoints = new TournamentPoints();
      $tpoints->setDefaults( TPOINTSTYPE_SIMPLE );

      $tround = new TournamentRound();
      $tround->MinPoolSize = 3;
      $tround->MaxPoolSize = 8;
      $tround->MaxPoolCount = 1;
      $tround->PoolWinnerRanks = 3;

      return $this->_createTournament( $tourney, $tprops, $trules, $tpoints, $tround );
   }//createTournament

} // end of 'PublicRoundRobinTournament'

?>

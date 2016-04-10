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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/types/dgs_ladder.php';
require_once 'tournaments/include/types/public_ladder.php';
require_once 'tournaments/include/types/private_ladder.php';
require_once 'tournaments/include/types/dgs_round_robin.php';
require_once 'tournaments/include/types/public_round_robin.php';
require_once 'tournaments/include/types/private_round_robin.php';


 /*!
  * \file tournament_factory.php
  *
  * \brief class with different types of tournaments to support wizard
  */



 /*!
  * \class TournamentTypes
  *
  * \brief Factory to create certain tournament-type
  */
class TournamentFactory
{
   // ------------ static functions ----------------------------

   /*! \brief Constructs ConfigBoard-object with specified arguments. */
   public static function getTournament( $wizard_type )
   {
      if ( $wizard_type == TOURNEY_WIZTYPE_DGS_LADDER )
         return new DgsLadderTournament();
      elseif ( $wizard_type == TOURNEY_WIZTYPE_PUBLIC_LADDER )
         return new PublicLadderTournament();
      elseif ( $wizard_type == TOURNEY_WIZTYPE_PRIVATE_LADDER )
         return new PrivateLadderTournament();
      elseif ( $wizard_type == TOURNEY_WIZTYPE_DGS_ROUNDROBIN )
         return new DgsRoundRobinTournament();
      elseif ( $wizard_type == TOURNEY_WIZTYPE_PUBLIC_ROUNDROBIN )
         return new PublicRoundRobinTournament();
      elseif ( $wizard_type == TOURNEY_WIZTYPE_PRIVATE_ROUNDROBIN )
         return new PrivateRoundRobinTournament();
      else
         error('invalid_args', "TournamentFactory:getTournament($wizard_type)");
   }

   /*! \brief Returns list with all defined wizard-types in order to be showed for tourney-wizard. */
   public static function getTournamentTypes()
   {
      $ttypes = array();
      if ( preg_match("/^(".ALLOW_TOURNAMENTS.")$/", TOURNEY_TYPE_LADDER) )
      {
         $ttypes[TOURNEY_WIZTYPE_DGS_LADDER]     = TWIZT_LADDER | TWIZ_DGS;
         $ttypes[TOURNEY_WIZTYPE_PUBLIC_LADDER]  = TWIZT_LADDER | TWIZ_PUBLIC;
         $ttypes[TOURNEY_WIZTYPE_PRIVATE_LADDER] = TWIZT_LADDER | TWIZ_PRIVATE;
      }
      if ( preg_match("/^(".ALLOW_TOURNAMENTS.")$/", TOURNEY_TYPE_ROUND_ROBIN) )
      {
         $ttypes[TOURNEY_WIZTYPE_DGS_ROUNDROBIN] = TWIZT_ROUND_ROBIN | TWIZ_DGS;
         $ttypes[TOURNEY_WIZTYPE_PUBLIC_ROUNDROBIN] = TWIZT_ROUND_ROBIN | TWIZ_PUBLIC;
         $ttypes[TOURNEY_WIZTYPE_PRIVATE_ROUNDROBIN] = TWIZT_ROUND_ROBIN | TWIZ_PRIVATE;
      }
      return $ttypes;
   }

} // end of 'TournamentFactory'

?>

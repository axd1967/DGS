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

require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_ladder_props.php';

 /*!
  * \file tournament_cache.php
  *
  * \brief Container and function to cache tournament-objects.
  */


 /*!
  * \class TournamentCache
  *
  * \brief Helper-class to store different tournament-objects in local cache.
  */

class TournamentCache
{
   /*! \brief array( tid => Tournament-object ) */
   var $cache_tournament;

   /*! \brief array( tid => TournamentLadderProps-object ) */
   var $cache_tl_props;


   function TournamentCache()
   {
      $this->cache_tournament = array();
      $this->cache_tl_props = array();
   }

   function load_tournament( $dbgmsg, $tid )
   {
      if( isset($this->cache_tournament[$tid]) )
         $tourney = $this->cache_tournament[$tid];
      else
      {
         $tourney = Tournament::load_tournament($tid);
         if( is_null($tourney) )
            error('unknown_tournament', "$dbgmsg.find_tournament($tid)");
         else
            $this->cache_tournament[$tid] = $tourney;
      }
      return $tourney;
   }

   function load_tournament_ladder_props( $dbgmsg, $tid )
   {
      if( isset($this->cache_tl_props[$tid]) )
         $tl_props = $this->cache_tl_props[$tid];
      else
      {
         $tl_props = TournamentLadderProps::load_tournament_ladder_props( $tid );
         if( is_null($tl_props) )
            error('unknown_tournament', "$dbgmsg.find_tladder_props($tid)");
         else
            $this->cache_tl_props[$tid] = $tl_props;
      }
      return $tl_props;
   }

} // end of 'TournamentCache'
?>

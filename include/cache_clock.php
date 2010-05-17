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

require_once 'include/connect2mysql.php';

 /*!
  * \file cache_clock.php
  *
  * \brief Container and function to cache Clock-db-data.
  */

global $CACHE_CLOCK;
$CACHE_CLOCK = new ClockCache();


 /*!
  * \class CacheClock
  *
  * \brief Helper-class to store Clock-ticks in local cache.
  */
class ClockCache
{
   /*! \brief array( clock_id => ticks ) */
   var $cache_clock_ticks;

   function ClockCache()
   {
      $this->cache_clock_ticks = array();
   }

   function load_clock_ticks( $dbgmsg, $clock_id, $refresh_cache=false )
   {
      if( !is_numeric($clock_id) || $clock_id > MAX_CLOCK )
         error('invalid_args', "$dbgmsg.load_clock_ticks.check($clock_id)");
      if( $clock_id < 0 ) // VACATION_CLOCK
         return 0; // On vacation

      if( $refresh_cache || !isset($this->cache_clock_ticks[$clock_id]) )
      {
         $row = mysql_single_fetch( "$dbgmsg.load_clock_ticks.find($clock_id)",
            "SELECT Ticks FROM Clock WHERE ID=$clock_id LIMIT 1" );
         if( !$row )
            error('mysql_clock_ticks', "$dbgmsg.load_clock_ticks.bad_clock($clock_id)");
         else
            $this->cache_clock_ticks[$clock_id] = (int)@$row['Ticks'];
      }
      $ticks = (int)@$this->cache_clock_ticks[$clock_id];
      return $ticks;
   }

} // end of 'ClockCache'
?>

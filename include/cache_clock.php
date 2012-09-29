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

require_once 'include/connect2mysql.php';
require_once 'include/dgs_cache.php';

 /*!
  * \file cache_clock.php
  *
  * \brief Container and function to cache Clock-db-data.
  */

global $CACHE_CLOCK;
$CACHE_CLOCK = new ClockCache();


 /*!
  * \class ClockCache
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

   function load_clock_ticks( $dbgmsg, $clock_id, $use_cache=true )
   {
      if( !is_numeric($clock_id) || $clock_id > MAX_CLOCK )
         error('invalid_args', "$dbgmsg.load_clock_ticks.check($clock_id)");
      if( $clock_id < 0 ) // VACATION_CLOCK
         return 0; // On vacation

      if( !$use_cache || !isset($this->cache_clock_ticks[$clock_id]) )
      {
         // need special handling to load all or only one clock-entry (if cache disabled)
         if( DgsCache::is_persistent() )
         {
            $arr_clocks = ClockCache::load_cache_clocks( !$use_cache );
            if( is_null($arr_clocks) || !isset($arr_clocks[$clock_id]) )
               error('invalid_args', "$dbgmsg.load_clock_ticks.cache.bad_clock($clock_id)");
            $this->cache_clock_ticks = $arr_clocks;
         }
         else
         {
            $row = mysql_single_fetch( "$dbgmsg.load_clock_ticks.find($clock_id)",
               "SELECT Ticks FROM Clock WHERE ID=$clock_id LIMIT 1" );
            if( !$row )
               error('invalid_args', "$dbgmsg.load_clock_ticks.no_cache.bad_clock($clock_id)");
            else
               $this->cache_clock_ticks[$clock_id] = (int)@$row['Ticks'];
         }
      }

      $ticks = (int)@$this->cache_clock_ticks[$clock_id];
      return $ticks;
   }


   // ------------ static functions ----------------------------

   /*! \brief Returns singleton of clock-cache. */
   function get_clock_cache()
   {
      global $CACHE_CLOCK;
      return $CACHE_CLOCK;
   }

   function load_cache_clocks( $reload=false )
   {
      $dbgmsg = "ClockCache::load_cache_clocks()";
      $key = "Clocks";
      $result = DgsCache::fetch($dbgmsg, $key);
      if( $reload || is_null($result) )
      {
         $result = array();
         $db_result = db_query( $dbgmsg.'.find_all', "SELECT ID, Ticks FROM Clock" ); // load all clocks
         while( ($row = mysql_fetch_assoc($db_result)) )
            $result[$row['ID']] = (int)$row['Ticks'];
         mysql_free_result($db_result);

         DgsCache::store( $dbgmsg, $key, $result, 10*SECS_PER_MIN );
      }
      return $result;
   }

   function delete_cache_clocks()
   {
      DgsCache::delete("ClockCache::delete_cache_clocks()", "Clocks");
   }

} // end of 'ClockCache'
?>

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

/*!
 * \file cache_globals.php
 *
 * \brief Definitions and declarations for DGS caching.
 */

define('SECS_PER_MIN',  60);
define('SECS_PER_HOUR', 3600);
define('SECS_PER_DAY',  86400);

// cache-implementations
define('CACHE_TYPE_NONE', false); // no caching
define('CACHE_TYPE_APC', 'ApcCache');
define('CACHE_TYPE_FILE', 'FileCache');

// Cache Content:
//
// NOTE: noted are average size-requirements for APC-cache -> NEED X (estimated size X for live-server)
//       file-caches requires only 20% (or less) for one entry as given for APC-cache.
//
// NOTE: live-server stats used for total size-estimations:
//    #users/time: 50/min, 150/5min, 200/10min, 500/30min, 700/h, 900/2h, 1300/4h, 1500/6h, 2000/12h, 2500/1d, 3500/7d, 4400/30d
//    #game-views/time: 100/min, 350/10min, 1200/h, 4500/8h
//    #tournaments unknown as feature is not live (therefore the '?')
//
define('CACHE_GRP_DEFAULT', 0);
define('CACHE_GRP_CFG_PAGES', 1);   // 7KB/user * 1h -> 5 MB
define('CACHE_GRP_CFG_BOARD', 2);   // 2KB/user * 1d -> 5 MB
define('CACHE_GRP_CLOCKS', 3);      // 1KB total * 10min -> 1 KB
define('CACHE_GRP_GAME_OBSERVERS', 4); // 0.5KB/user/game-id * 30min -> 750 KB
define('CACHE_GRP_GAME_NOTES', 5);  // 1KB/user/game-id * 30min -> 3 MB + cache-REFRESH
define('CACHE_GRP_USER_REF', 6);    // 0.5KB/id,handle * 1d -> 1 MB
define('CACHE_GRP_STATS_GAMES', 7); // 8KB total * 1d -> 8 KB
define('CACHE_GRP_FOLDERS', 8);     // 7KB/user * 30min -> 6 MB
define('CACHE_GRP_PROFILE', 9);     // 0.5K/user/type * 1d -> 2 MB
define('CACHE_GRP_GAME_MOVES', 10); // 100-200KB/game * 10min -> 30-100 MB(!)
define('CACHE_GRP_GAME_MOVEMSG', 11); // 40-250KB/game * 10min -> 20-120 MB(!)
define('CACHE_GRP_TOURNAMENT', 12); // 2KB/tourney * 1h -> 100 KB ?
define('CACHE_GRP_TDIRECTOR', 13);  // 0.5KB/tourney * 1h -> 50 KB ?
define('CACHE_GRP_TLPROPS', 14);    // 2KB/tourney * 1h -> 200 KB ?    // TournamentLadderProps
define('CACHE_GRP_TPROPS', 15);     // 1KB/tourney * 1h -> 50 KB ?
define('CACHE_GRP_TRULES', 16);     // 2KB/tourney * 1h -> 100 KB ?
define('CACHE_GRP_TROUND', 17);     // 0.5KB/tourney/round * 1h -> 50 KB ?
define('CACHE_GRP_TNEWS', 18);      // ~50KB/tourney * 1d -> 2 MB ?
define('CACHE_GRP_TP_COUNT', 19);   // 0.5KB/tourney * 1h -> 25 KB ?   // TournamentParticipant-count
define('CACHE_GRP_TPARTICIPANT', 20); // 2KB/tourney/TP * 1h -> 20 MB(!) ?
define('CACHE_GRP_TRESULT', 21);    // 20KB/tourney * 1d -> 1 MB ?
// NOTE: keep as last def and adjust to MAX when adding a new cache-group
define('MAX_CACHE_GRP', 21);

// configure cleanup for expired cache-entries (cache-groups not listed uses expire-time of CACHE_GRP_DEFAULT)
global $ARR_CACHE_GROUP_CLEANUP;
$ARR_CACHE_GROUP_CLEANUP = array(
      // CACHE_GRP_.. => expire-time [secs]
      CACHE_GRP_DEFAULT       => 2*SECS_PER_DAY,
      #CACHE_GRP_CFG_PAGES     => SECS_PER_DAY,
      #CACHE_GRP_CFG_BOARD     => SECS_PER_DAY,
      #CACHE_GRP_CLOCKS        => SECS_PER_DAY,
      CACHE_GRP_GAME_OBSERVERS => SECS_PER_HOUR,
      CACHE_GRP_GAME_NOTES    => SECS_PER_HOUR,
      CACHE_GRP_USER_REF      => SECS_PER_DAY,
      #CACHE_GRP_STATS_GAMES   => SECS_PER_DAY,
      #CACHE_GRP_FOLDERS       => SECS_PER_DAY,
      #CACHE_GRP_PROFILE       => SECS_PER_DAY,
      CACHE_GRP_GAME_MOVES    => SECS_PER_HOUR,
      CACHE_GRP_GAME_MOVEMSG  => SECS_PER_HOUR,
      CACHE_GRP_TOURNAMENT    => SECS_PER_DAY,
      CACHE_GRP_TDIRECTOR     => SECS_PER_DAY,
      CACHE_GRP_TLPROPS       => SECS_PER_DAY,
      CACHE_GRP_TPROPS        => SECS_PER_DAY,
      CACHE_GRP_TRULES        => SECS_PER_DAY,
      CACHE_GRP_TROUND        => SECS_PER_DAY,
      CACHE_GRP_TNEWS         => SECS_PER_DAY,
      CACHE_GRP_TP_COUNT      => SECS_PER_DAY,
      CACHE_GRP_TPARTICIPANT  => SECS_PER_DAY,
      CACHE_GRP_TRESULT       => SECS_PER_DAY,
   );

?>

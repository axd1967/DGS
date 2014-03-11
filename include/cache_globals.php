<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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
define('CACHE_GRP_CFG_PAGES', 1);   // 7KB/user * 1d -> 17 MB
define('CACHE_GRP_CFG_BOARD', 2);   // 2KB/user * 1d -> 5 MB
define('CACHE_GRP_CLOCKS', 3);      // 1KB total * 10min -> 4 KB
define('CACHE_GRP_GAME_OBSERVERS', 4); // 0.5KB/user/game-id * 30min -> 750 KB
define('CACHE_GRP_GAME_NOTES', 5);  // 1KB/user/game-id * 30min -> 3 MB
define('CACHE_GRP_USER_REF', 6);    // 0.5KB/id,handle * 1d -> 1 MB
define('CACHE_GRP_STATS_GAMES', 7); // 8KB total * 1d -> 8 KB
define('CACHE_GRP_FOLDERS', 8);     // 7KB/user * 1d -> 17 MB
define('CACHE_GRP_PROFILE', 9);     // 0.5K/user/type * 1d -> 2 MB
define('CACHE_GRP_GAME_MOVES', 10); // 100-200KB/game * 10min -> 30-100 MB(!)
define('CACHE_GRP_GAME_MOVEMSG', 11); // 40-250KB/game * 10min -> 20-120 MB(!)
define('CACHE_GRP_TOURNAMENT', 12); // 2KB/tourney * 1h -> 100 KB ?
define('CACHE_GRP_TDIRECTOR', 13);  // 0.5KB/tourney * 1d -> 50 KB ?
define('CACHE_GRP_TLPROPS', 14);    // 2KB/tourney * 1d -> 200 KB ?    // TournamentLadderProps
define('CACHE_GRP_TPROPS', 15);     // 1KB/tourney * 1d -> 50 KB ?
define('CACHE_GRP_TRULES', 16);     // 2KB/tourney * 1d -> 100 KB ?
define('CACHE_GRP_TROUND', 17);     // 0.5KB/tourney/round * 1d -> 50 KB ?
define('CACHE_GRP_TNEWS', 18);      // ~50KB/tourney * 1d -> 2 MB ?
define('CACHE_GRP_TP_COUNT', 19);   // 0.5KB/tourney * 1h -> 25 KB ?   // TournamentParticipant-count
define('CACHE_GRP_TPARTICIPANT', 20); // 2KB/tourney/TP * 1h -> 20 MB(!) ?
define('CACHE_GRP_TRESULT', 21);    // 20KB/tourney * 1d -> 1 MB ?
define('CACHE_GRP_GAMES', 22);      // 7KB/game * 10min -> 4 MB   // no long-caching accepting delayed data-display: vacation-start/end, user-updates (rating, clockused, name)
define('CACHE_GRP_FORUM', 23);      // 1KB/forum * 1d -> 12 KB
define('CACHE_GRP_FORUM_NAMES', 24); // 7KB total * 1d -> 7 KB
define('CACHE_GRP_GAMELIST_STATUS', 25); // 5.5KB/user/game (1.9KB FILE) * 1min -> 7 MB
define('CACHE_GRP_BULLETINS', 26);  // 8KB KB/user/bulletin * 1d -> 10 MB
define('CACHE_GRP_MSGLIST', 27);    // 2KB/user/msg * 4h -> 5 MB
define('CACHE_GRP_TGAMES', 28);     // 3KB/tourney/T-game * 1d -> 60 MB ?
define('CACHE_GRP_TLADDER', 29);    // 4.5KB/tourney/T-ladder * 1h -> 23 MB ?
define('CACHE_GRP_GAMESGF_COUNT', 30); // 0.5KB/game-id * 30min -> 500 KB
define('CACHE_GRP_TP_COUNT_ALL', 31); // 0.5KB/tourney * 1h -> 25 KB ?   // TournamentParticipant-all-count
define('CACHE_GRP_USER_HANDLE', 32); // 2KB/handle * 1h -> 1 MB
define('CACHE_GRP_TPOINTS', 33);    // 0.5KB/tourney * 1d -> 25 KB ?
// NOTE: keep as last def and adjust to MAX when adding a new cache-group
define('MAX_CACHE_GRP', 33);

// names for DGS-cache manager
global $ARR_CACHE_GROUP_NAMES;
$ARR_CACHE_GROUP_NAMES = array(
      // CACHE_GRP_.. => keys/names (to be able to search for in cache)
      CACHE_GRP_DEFAULT        => 'DEFAULT',
      CACHE_GRP_CFG_PAGES      => 'ConfigPages',
      CACHE_GRP_CFG_BOARD      => 'ConfigBoard',
      CACHE_GRP_CLOCKS         => 'Clocks',
      CACHE_GRP_GAME_OBSERVERS => 'Observers',
      CACHE_GRP_GAME_NOTES     => 'GameNotes',
      CACHE_GRP_USER_REF       => 'user_ref',
      CACHE_GRP_STATS_GAMES    => 'Statistics.games',
      CACHE_GRP_FOLDERS        => 'Folders',
      CACHE_GRP_PROFILE        => 'Profile',
      CACHE_GRP_GAME_MOVES     => 'Game.moves',
      CACHE_GRP_GAME_MOVEMSG   => 'Game.movemsg',
      CACHE_GRP_TOURNAMENT     => 'Tournament',
      CACHE_GRP_TDIRECTOR      => 'TDirector',
      CACHE_GRP_TLPROPS        => 'TLadderProps',
      CACHE_GRP_TPROPS         => 'TProps',
      CACHE_GRP_TRULES         => 'TRules',
      CACHE_GRP_TROUND         => 'TRound',
      CACHE_GRP_TNEWS          => 'TNews',
      CACHE_GRP_TP_COUNT       => 'TPCount',
      CACHE_GRP_TPARTICIPANT   => 'TParticipant',
      CACHE_GRP_TRESULT        => 'TResult',
      CACHE_GRP_GAMES          => 'Games',
      CACHE_GRP_FORUM          => 'Forum',
      CACHE_GRP_FORUM_NAMES    => 'ForumNames',
      CACHE_GRP_GAMELIST_STATUS => 'StatusGames',
      CACHE_GRP_BULLETINS      => 'Bulletins',
      CACHE_GRP_MSGLIST        => 'Messages',
      CACHE_GRP_TGAMES         => 'TGames',
      CACHE_GRP_TLADDER        => 'TLadder',
      CACHE_GRP_GAMESGF_COUNT  => 'GameSgfCount',
      CACHE_GRP_TP_COUNT_ALL   => 'TPCountAll',
      CACHE_GRP_USER_HANDLE    => 'user_hdl',
      CACHE_GRP_TPOINTS        => 'TPoints',
   );

// configure cleanup for expired cache-entries (cache-groups not listed uses expire-time of CACHE_GRP_DEFAULT)
global $ARR_CACHE_GROUP_CLEANUP;
$ARR_CACHE_GROUP_CLEANUP = array(
      // CACHE_GRP_.. => expire-time for cleanup [secs]    // real expire-time from cache
      CACHE_GRP_DEFAULT       => 2*SECS_PER_DAY,
      CACHE_GRP_CFG_PAGES     => SECS_PER_DAY,
      CACHE_GRP_CFG_BOARD     => SECS_PER_DAY,
      #CACHE_GRP_CLOCKS        => SECS_PER_DAY,
      CACHE_GRP_GAME_OBSERVERS => SECS_PER_HOUR, // 30min
      CACHE_GRP_GAME_NOTES    => SECS_PER_HOUR, // 30min
      CACHE_GRP_USER_REF      => 7*SECS_PER_DAY, // 1d
      #CACHE_GRP_STATS_GAMES   => SECS_PER_DAY,
      CACHE_GRP_FOLDERS       => SECS_PER_DAY,
      CACHE_GRP_PROFILE       => SECS_PER_DAY,
      CACHE_GRP_GAME_MOVES    => 15*SECS_PER_MIN, // 10min
      CACHE_GRP_GAME_MOVEMSG  => 15*SECS_PER_MIN, // 10min
      CACHE_GRP_TOURNAMENT    => SECS_PER_HOUR,
      CACHE_GRP_TDIRECTOR     => SECS_PER_DAY,
      CACHE_GRP_TLPROPS       => SECS_PER_DAY,
      CACHE_GRP_TPROPS        => SECS_PER_DAY,
      CACHE_GRP_TRULES        => SECS_PER_DAY,
      CACHE_GRP_TROUND        => SECS_PER_DAY,
      CACHE_GRP_TNEWS         => SECS_PER_DAY,
      CACHE_GRP_TP_COUNT      => SECS_PER_DAY,
      CACHE_GRP_TPARTICIPANT  => SECS_PER_DAY, // 1h
      CACHE_GRP_TRESULT       => SECS_PER_DAY,
      CACHE_GRP_GAMES         => 15*SECS_PER_MIN, // 10min
      CACHE_GRP_FORUM         => SECS_PER_DAY,
      CACHE_GRP_FORUM_NAMES   => 7*SECS_PER_DAY, // 1d
      CACHE_GRP_GAMELIST_STATUS => 10*SECS_PER_MIN, // 1min
      CACHE_GRP_BULLETINS     => SECS_PER_DAY,
      CACHE_GRP_MSGLIST       => 4*SECS_PER_HOUR,
      CACHE_GRP_TGAMES        => SECS_PER_DAY,
      CACHE_GRP_TLADDER       => SECS_PER_DAY, // 1h
      CACHE_GRP_GAMESGF_COUNT => SECS_PER_HOUR, // 30min
      CACHE_GRP_TP_COUNT_ALL  => SECS_PER_DAY,
      CACHE_GRP_USER_HANDLE   => SECS_PER_HOUR,
      CACHE_GRP_TPOINTS       => SECS_PER_DAY,
   );

?>

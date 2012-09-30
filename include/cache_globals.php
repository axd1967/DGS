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

// cache-implementations
define('CACHE_TYPE_APC', 'ApcCache');
define('CACHE_TYPE_FILE', 'FileCache');

// cache content
define('CACHE_GRP_CFG_PAGES', 1);
define('CACHE_GRP_CFG_BOARD', 2);
define('CACHE_GRP_CLOCKS', 3);
define('CACHE_GRP_GAME_OBSERVERS', 4);
define('CACHE_GRP_GAME_NOTES', 5);
define('CACHE_GRP_USER_REF', 6);
define('CACHE_GRP_STATS_GAMES', 7);
define('CACHE_GRP_FOLDERS', 8);
define('CACHE_GRP_PROFILE', 9);
define('CACHE_GRP_GAME_MOVES', 10);
define('CACHE_GRP_GAME_MOVEMSG', 11);
define('CACHE_GRP_TOURNAMENT', 12);
define('CACHE_GRP_TDIRECTOR', 13);
define('CACHE_GRP_TLPROPS', 14); // TournamentLadderProps
define('CACHE_GRP_TPROPS', 15);
define('CACHE_GRP_TRULES', 16);
define('CACHE_GRP_TROUND', 17);
define('CACHE_GRP_TNEWS', 18);
define('CACHE_GRP_TP_COUNT', 19); // TournamentParticipant-count
define('CACHE_GRP_TPARTICIPANT', 20);
// NOTE: keep as last def and adjust to MAX when adding a new cache-group
define('MAX_CACHE_GRP', 20);

?>

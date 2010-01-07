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

/*!
 * \file tournament_globals.php
 *
 * \brief Definitions and declarations of DGS globally used vars and constants for tournaments.
 */


// ---------- Tournament Stuff ------------------------------------

define('TOURNEY_SCOPE_DRAGON',  'DRAGON');
define('TOURNEY_SCOPE_PUBLIC',  'PUBLIC');
define('TOURNEY_SCOPE_PRIVATE', 'PRIVATE');
define('CHECK_TOURNEY_SCOPE', 'DRAGON|PUBLIC|PRIVATE');

// tournament-types
define('TOURNEY_TYPE_LADDER',      'LADDER');
define('TOURNEY_TYPE_ROUND_ROBIN', 'ROUNDROBIN');
define('CHECK_TOURNEY_TYPE', 'LADDER|ROUNDROBIN');

// wizard tournament-types with different profiles for tournament-types
// also adjust TournamentUtils::getWizardTournamentType()
define('TOURNEY_WIZTYPE_DGS_LADDER', 1);
define('MAX_TOURNEY_WIZARD_TYPE', 1);

define('TOURNEY_STATUS_ADMIN',    'ADM');
define('TOURNEY_STATUS_NEW',      'NEW');
define('TOURNEY_STATUS_REGISTER', 'REG');
define('TOURNEY_STATUS_PAIR',     'PAIR');
define('TOURNEY_STATUS_PLAY',     'PLAY');
define('TOURNEY_STATUS_CLOSED',   'CLOSED');
define('CHECK_TOURNEY_STATUS', 'ADM|NEW|REG|PAIR|PLAY|CLOSED');

?>

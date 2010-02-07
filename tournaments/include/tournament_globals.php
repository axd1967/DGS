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
//TODO define('TOURNEY_SCOPE_PRIVATE', 'PRIVATE');
define('CHECK_TOURNEY_SCOPE', 'DRAGON|PUBLIC');

// tournament-types
define('TOURNEY_TYPE_LADDER',      'LADDER');
define('TOURNEY_TYPE_ROUND_ROBIN', 'ROUNDROBIN');
define('CHECK_TOURNEY_TYPE', 'LADDER|ROUNDROBIN');

// wizard tournament-types with different profiles for tournament-types
// also adjust TournamentUtils::getWizardTournamentType()
define('TOURNEY_WIZTYPE_DGS_LADDER', 1);
define('MAX_TOURNEY_WIZARD_TYPE', 1);

// also adjust TournamentStatus::check_status_change()
define('TOURNEY_STATUS_ADMIN',    'ADM');
define('TOURNEY_STATUS_NEW',      'NEW');
define('TOURNEY_STATUS_REGISTER', 'REG');
define('TOURNEY_STATUS_PAIR',     'PAIR');
define('TOURNEY_STATUS_PLAY',     'PLAY');
define('TOURNEY_STATUS_CLOSED',   'CLOSED');
define('TOURNEY_STATUS_DELETE',   'DEL');
define('CHECK_TOURNEY_STATUS', 'ADM|NEW|REG|PAIR|PLAY|CLOSED|DEL');

define('LADDER_SEEDORDER_CURRENT_RATING', 1);
define('LADDER_SEEDORDER_REGISTER_TIME',  2);
define('LADDER_SEEDORDER_TOURNEY_RATING', 3);
define('LADDER_SEEDORDER_RANDOM',         4);

// ---------- Tournament Participant Stuff ------------------------

define('TP_STATUS_APPLY',     'APPLY');
define('TP_STATUS_REGISTER',  'REGISTER');
define('TP_STATUS_INVITE',    'INVITE');
define('CHECK_TP_STATUS', 'APPLY|REGISTER|INVITE');
define('TPCOUNT_STATUS_ALL',  '*');

define('TP_FLAGS_INVITED',       0x0001); // invited by TD
define('TP_FLAGS_ACK_INVITE',    0x0002); // invite by TD approved by user
define('TP_FLAGS_ACK_APPLY',     0x0004); // user-application approved by TD
define('TP_FLAGS_VIOLATE',       0x0008); // user-registration violates T-restrictions

// ---------- Tournament Games Stuff ------------------------------

define('TG_STATUS_INIT',   'INIT');
define('TG_STATUS_PLAY',   'PLAY');
define('TG_STATUS_SCORE',  'SCORE');
define('TG_STATUS_DONE',   'DONE');
define('CHECK_TG_STATUS', 'INIT|PLAY|SCORE|DONE');

// ---------- Tournament Ladder Props -----------------------------

// action to do for handling game-end in tournament for case
define('TGEND_NO_CHANGE',        'NO_CHANGE');  // no change for challenger and defender
define('TGEND_CHALLENGER_ABOVE', 'CH_ABOVE');   // challenger moves 1 rank above defender
define('TGEND_CHALLENGER_BELOW', 'CH_BELOW');   // challenger moves 1 rank below defender
define('TGEND_SWITCH',           'SWITCH');     // challenger and defender switch places
define('TGEND_DEFENDER_BELOW',   'DF_BELOW');   // defender moves 1 rank below challenger
define('TGEND_DEFENDER_LAST',    'DF_LAST');    // defender moves at ladder-bottom
define('TGEND_DEFENDER_DELETE',  'DF_DEL');     // defender is removed from ladder

// see also TournamentLadderProps::getGameEndText()
define('CHECK_TGEND_NORMAL',  'CH_ABOVE|CH_BELOW|SWITCH|DF_BELOW|DF_LAST');
define('CHECK_TGEND_JIGO',    'NO_CHANGE|CH_ABOVE|CH_BELOW');
define('CHECK_TGEND_TIMEOUT', 'NO_CHANGE|CH_ABOVE|CH_BELOW|SWITCH|DF_BELOW|DF_LAST|DF_DEL');

?>

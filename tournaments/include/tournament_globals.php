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
define('TOURNEY_WIZTYPE_PUBLIC_LADDER', 2);
define('TOURNEY_WIZTYPE_PRIVATE_LADDER', 3);
define('TOURNEY_WIZTYPE_DGS_ROUNDROBIN', 4);
define('MAX_TOURNEY_WIZARD_TYPE', 4);

// also adjust TournamentStatus::check_status_change()
define('TOURNEY_STATUS_ADMIN',    'ADM');
define('TOURNEY_STATUS_NEW',      'NEW');
define('TOURNEY_STATUS_REGISTER', 'REG');
define('TOURNEY_STATUS_PAIR',     'PAIR');
define('TOURNEY_STATUS_PLAY',     'PLAY');
define('TOURNEY_STATUS_CLOSED',   'CLOSED');
define('TOURNEY_STATUS_DELETE',   'DEL');
define('CHECK_TOURNEY_STATUS', 'ADM|NEW|REG|PAIR|PLAY|CLOSED|DEL');

// also adjust Tournament::getFlagsText(), see edit_lock.php
define('TOURNEY_FLAG_LOCK_ADMIN',      0x0001); // lock all ops on tourney for T-admin-work
define('TOURNEY_FLAG_LOCK_REGISTER',   0x0002); // lock user-registration for tourney
define('TOURNEY_FLAG_LOCK_TDWORK',     0x0004); // lock tourney for TD-work, prohibits some T-game-stuff/cron
define('TOURNEY_FLAG_LOCK_CRON',       0x0008); // lock by cron, prohibits certain tourney-specific actions
define('TOURNEY_FLAG_LOCK_CLOSE',      0x0010); // lock preparing for transition to CLOSED-status

define('TOURNEY_SEEDORDER_NONE',           0); // no choice in GUI
define('TOURNEY_SEEDORDER_CURRENT_RATING', 1);
define('TOURNEY_SEEDORDER_REGISTER_TIME',  2);
define('TOURNEY_SEEDORDER_TOURNEY_RATING', 3);
define('TOURNEY_SEEDORDER_RANDOM',         4);

define('TCHKTYPE_TD', 1);
define('TCHKTYPE_USER_NEW', 2);
define('TCHKTYPE_USER_EDIT', 3);

// ---------- Tournament Properties Stuff -------------------------

define('TPROP_RUMODE_COPY_CUSTOM',  'COPY_CUSTOM');
define('TPROP_RUMODE_CURR_FIX',     'CURR_FIX');
define('TPROP_RUMODE_COPY_FIX',     'COPY_FIX');
define('CHECK_TPROP_RUMODE', 'COPY_CUSTOM|CURR_FIX|COPY_FIX');

// ---------- Tournament Extension Stuff --------------------------

define('TE_PROP_TLADDER_RANK_PERIOD_UPDATE', 1);
define('TE_PROP_TROUND_START_TGAMES', 2);
define('TE_MAX_PROP', 2); // max. tournament-extension-property

// ---------- Tournament Director Stuff ---------------------------

// see also TournamentDirector::getFlagsText()
define('TD_FLAG_GAME_END', 0x0001);
define('TD_FLAG_GAME_ADD_TIME', 0x0002);

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

define('TP_MAX_COUNT', 32767);

// ---------- Tournament Games Stuff ------------------------------

define('TG_STATUS_INIT',   'INIT');
define('TG_STATUS_PLAY',   'PLAY');
define('TG_STATUS_SCORE',  'SCORE');
define('TG_STATUS_WAIT',   'WAIT');
define('TG_STATUS_DONE',   'DONE');
define('CHECK_TG_STATUS', 'INIT|PLAY|SCORE|WAIT|DONE');

define('TG_FLAG_GAME_END_TD', 0x0001); // T-game ended by TD

// ---------- Tournament Ladder Props -----------------------------

// action to do for handling game-end in tournament for case
define('TGEND_NO_CHANGE',           'NO_CHANGE');  // no change for challenger and defender
define('TGEND_CHALLENGER_ABOVE',    'CH_ABOVE');   // challenger moves 1 rank above defender
define('TGEND_CHALLENGER_BELOW',    'CH_BELOW');   // challenger moves 1 rank below defender
define('TGEND_CHALLENGER_LAST',     'CH_LAST');    // challenger moves to ladder-bottom
define('TGEND_CHALLENGER_DELETE',   'CH_DEL');     // challenger is removed from ladder
define('TGEND_SWITCH',              'SWITCH');     // challenger and defender switch places
define('TGEND_DEFENDER_BELOW',      'DF_BELOW');   // defender moves 1 rank below challenger
define('TGEND_DEFENDER_LAST',       'DF_LAST');    // defender moves to ladder-bottom
define('TGEND_DEFENDER_DELETE',     'DF_DEL');     // defender is removed from ladder

// see also TournamentLadderProps::getGameEndText()
define('CHECK_TGEND_NORMAL',        'CH_ABOVE|CH_BELOW|SWITCH|DF_BELOW|DF_LAST');
define('CHECK_TGEND_JIGO',          'NO_CHANGE|CH_ABOVE|CH_BELOW');
define('CHECK_TGEND_TIMEOUT_WIN',   'NO_CHANGE|CH_ABOVE|CH_BELOW|SWITCH|DF_BELOW|DF_LAST|DF_DEL');
define('CHECK_TGEND_TIMEOUT_LOSS',  'NO_CHANGE|CH_LAST|CH_DEL');

// ladder-limits
define('TLADDER_MAX_DEFENSES', 20);
define('TLADDER_MAX_CHALLENGES', 200);
define('TLADDER_MAX_WAIT_REMATCH', 3*30*24); // 3 months
define('TLADDER_MAX_CHRNG_RATING', 32767);
define('TLADDER_MAX_CHRNG_ABS', 2000);
define('TLADDER_CHRNG_RATING_UNUSED', -TLADDER_MAX_CHRNG_RATING-1);

// ---------- Tournament Round Stuff ------------------------------

define('TROUND_STATUS_INIT', 'INIT');
define('TROUND_STATUS_POOL', 'POOL');
define('TROUND_STATUS_PAIR', 'PAIR');
define('TROUND_STATUS_PLAY', 'PLAY');
define('TROUND_STATUS_DONE', 'DONE');
define('CHECK_TROUND_STATUS', 'INIT|POOL|PAIR|PLAY|DONE');

// tournament-round limits
define('TROUND_MAX_COUNT', 255);
define('TROUND_MAX_POOLSIZE', 25);
define('TROUND_MAX_POOLCOUNT', 1000);

define('TROUND_SLICE_ROUND_ROBIN', 1);
define('TROUND_SLICE_FILLUP_POOLS', 2);
define('TROUND_SLICE_MANUAL', 3);

// ---------- Tournament Pool Stuff -------------------------------

// TournamentPool.Rank-values
define('TPOOLRK_NO_RANK', -100); // unset pool-rank
define('TPOOLRK_RANK_ZONE', -90); // reserved rank-zone -100..-90, >-90 zone with ranks
define('TPOOLRK_RETREAT', 0); // temp-marked as retreat from next round

define('TIEBREAKER_POINTS', 1);
define('TIEBREAKER_SODOS',  2);
define('TIEBREAKER_WINS',   3);

define('RKACT_SET_NEXT_RND',   1); // mark user to advance to next-round
define('RKACT_CLEAR_NEXT_RND', 2); // user will NOT advance to next-round
define('RKACT_CLEAR_RANKS',    3); // set Rank=0 for user
define('RKACT_REMOVE_RANKS',   4); // unset Rank for user

?>

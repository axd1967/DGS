<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/config-local.php';

/*!
 * \file globals.php
 *
 * \brief Definitions and declarations of DGS globally used vars and constants.
 */

define('DGS_VERSION', '1.0.15');

// global version of quick-suite: increased with each release
define('QUICK_VERSION', 1);
define('QUICK_SUITE_VERSION', DGS_VERSION.':'.QUICK_VERSION);

// ---------- General stuff----------------------------------------

define('NO_VALUE', '---');
define('UNKNOWN_VALUE', '???');

define('MINI_SPACING',  '&nbsp;');
define('MED_SPACING',   '&nbsp;&nbsp;');
define('SMALL_SPACING', '&nbsp;&nbsp;&nbsp;');
define('SEP_SPACING',   SMALL_SPACING.'|'.SMALL_SPACING);
define('SEP_MEDSPACING',   MED_SPACING.'|'.MED_SPACING);


// ---------- Clock stuff -----------------------------------------

define('CLOCK_CRON_TICK', 201);
define('CLOCK_CRON_HALFHOUR', 202);
define('CLOCK_CRON_DAY', 203);
define('CLOCK_TIMELEFT', 204);
define('CLOCK_CRON_TOURNEY', 205);
define('CLOCK_CRON_TOURNEY_DAILY', 206);
define('MAX_CLOCK', 206);

define('CLOCK_TOURNEY_GAME_WAIT', CLOCK_CRON_HALFHOUR);

define('WEEKEND_CLOCK_OFFSET', 100);
define('VACATION_CLOCK', -1); // keep it < 0


// ---------- Games stuff -----------------------------------------

define('GAME_STATUS_SETUP', 'SETUP');
define('GAME_STATUS_INVITED', 'INVITED');
define('GAME_STATUS_PLAY', 'PLAY');
define('GAME_STATUS_PASS', 'PASS');
define('GAME_STATUS_SCORE', 'SCORE');
define('GAME_STATUS_SCORE2', 'SCORE2');
define('GAME_STATUS_FINISHED', 'FINISHED');
define('CHECK_GAME_STATUS', 'SETUP|INVITED|PLAY|PASS|SCORE|SCORE2|FINISHED');
define('CHECK_GAME_STATUS_RUNNING', 'PLAY|PASS|SCORE|SCORE2');

//keep next constants powers of 2
define('GAMEFLAGS_KO', 0x01);
define('GAMEFLAGS_HIDDEN_MSG', 0x02);
define('GAMEFLAGS_ADMIN_RESULT', 0x04);

// enum Games.GameType
define('GAMETYPE_GO',      'GO');
define('GAMETYPE_TEAM_GO', 'TEAM_GO');
define('GAMETYPE_ZEN_GO',  'ZEN_GO');
define('CHECK_GAMETYPE',  'GO|TEAM_GO|ZEN_GO');

//Games table: particular Score values
define('SCORE_RESIGN', 1000);
define('SCORE_TIME', 2000);
define('SCORE_MAX', min(SCORE_RESIGN,SCORE_TIME) - 1); // =min(SCORE_...) - 1

// see Games/Waitingroom/TournamentRules.Ruleset, see also specs/db/table-Waitingroom.txt
define('RULESET_JAPANESE', 'JAPANESE'); // using area-scoring
define('RULESET_CHINESE',  'CHINESE'); // using territory-scoring
define('CHECK_RULESETS', 'JAPANESE|CHINESE');

// game-settings view-mode
define('GSETVIEW_SIMPLE', 0);
define('GSETVIEW_EXPERT', 1);
define('GSETVIEW_MPGAME', 2); // multi-player
define('MAX_GSETVIEW', 2);


define('MAX_SEKI_MARK', 2);

//----- Board array
define("NONE", 0); //i.e. DAME, Moves(Stone=NONE,PosX/PosY=coord,Hours=0)
define("BLACK", 1);
define("WHITE", 2);
define('STONE_TD_ADDTIME', -1); //dummy-val (not stored in DB)

define("OFFSET_TERRITORY", 0x04); //keep it a power of 2
define("DAME", OFFSET_TERRITORY+NONE);
define("BLACK_TERRITORY", OFFSET_TERRITORY+BLACK);
define("WHITE_TERRITORY", OFFSET_TERRITORY+WHITE);

define("OFFSET_MARKED", 0x08); //keep it a power of 2
define("MARKED_DAME", OFFSET_MARKED+NONE);
define("BLACK_DEAD", OFFSET_MARKED+BLACK);
define("WHITE_DEAD", OFFSET_MARKED+WHITE);

define("FLAG_NOCLICK", 0x10); //keep it a power of 2
//----- Board(end)

// ---------- Folder & Message stuff ------------------------------

// folder for "destroyed" messages (former was: Folder_nr=NULL)
// '-4'-value in regard to FOLDER_DELETED and to keep < FOLDER_NONE
define('FOLDER_DESTROYED', -4);

define('FOLDER_NONE', -1); // pseudo-folder used for "selecting no folder"
define('FOLDER_ALL_RECEIVED', 0); // pseudo-folder-nr to check for valid-folders
//Valid folders must be > FOLDER_ALL_RECEIVED
define('FOLDER_MAIN', 1);
define('FOLDER_NEW', 2);
define('FOLDER_REPLY', 3);
define('FOLDER_DELETED', 4); // Trashcan-folder
define('FOLDER_SENT', 5);
//User folders must be >= USER_FOLDERS
define('USER_FOLDERS', 6);

define('MSGFLAG_BULK', 0x01); // multi-receiver (bulk) message


// ---------- Filter stuff ----------------------------------------

define('FNAME_REQUIRED', 'sf_req');  // comma-separated list of required filters (id or name); not included in SearchProfile!!
define('REQF_URL', URI_AMP.FNAME_REQUIRED.'=');  // prepared URL-part for appending to require certain filters


// ---------- Rating stuff ----------------------------------------

define('MAX_START_RATING', 2600); //6 dan
define('MIN_RATING', -900); //30 kyu
define('OUT_OF_RATING', 9999); //ominous rating bounds: [-OUT_OF_RATING,OUT_OF_RATING]
define('NO_RATING', -OUT_OF_RATING);
define('RATING_9DAN', 2900); //9 dan (selectable max-rating)

// Players.RatingStatus
define('RATING_NONE',  'NONE'); // no rating set
define('RATING_INIT',  'INIT'); // rating set, but can be changed (no rated games yet)
define('RATING_RATED', 'RATED'); // rating established (rated game exists)

?>

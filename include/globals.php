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

require_once( "include/config-local.php" );

/*!
 * \file globals.php
 *
 * \brief Definitions and declarations of DGS globally used vars and constants.
 */


// ---------- General stuff----------------------------------------

define('NO_VALUE', '---');
define('UNKNOWN_VALUE', '???');


// ---------- Folder stuff ----------------------------------------

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


// ---------- Filter stuff ----------------------------------------

define('FNAME_REQUIRED', 'sf_req');  // comma-separated list of required filters (id or name); not included in SearchProfile!!
define('REQF_URL', URI_AMP.FNAME_REQUIRED.'=');  // prepared URL-part for appending to require certain filters

?>

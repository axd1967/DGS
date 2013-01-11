<?php

require_once 'include/cache_globals.php'; // needed for cache-consts

// set debug-level for writing db-queries into error-log: 0 = disabled, 3 = db-query with user+time-needed
define('DBG_QUERY', 0);
//error_log('##########[DGS-LOC]' . str_repeat('##########',10)); // comment out for separation of page-requests


// Friendly names.

//Long name: Keep it AscII
define('FRIENDLY_LONG_NAME', 'Dragon Go Server');
//Short name: Begining with a letter then only letters or numbers
define('FRIENDLY_SHORT_NAME', 'DGS');


// This is the main url. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// set HOSTBASE = "http://www.some_domain.com/~my_dir/";

define('HOSTBASE', 'http://localhost/');

// This is the server name. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// http://www.some_domain.com/index.php
// set $HOSTNAME = "www.some_domain.com";

define('HOSTNAME', 'localhost');

// This is used for cookies. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// http://localhost/~my_dir/index.php
// http://127.0.0.1/~my_dir/index.php
// set $SUB_PATH = "/~my_dir/";

define('SUB_PATH', '/');

// Then you should have: HOSTBASE = "http://" . $HOSTNAME . $SUB_PATH;


// From address for notification emails
define('EMAIL_FROM', 'noreply@localhost');

// additional sendmail parameters for local MTA used for sending mail in send_email()-function
define('SENDMAIL_PARAMETERS', '');

// comma-separated list with email-addresses of admins to inform about internal server-problems
define('EMAIL_ADMINS', 'root@localhost');


// These four are used to connect to mysql, with a command corresponding to
// mysql -u$MYSQLUSER -p$MYSQLPASSWORD -h$MYSQLHOST -D$DB_NAME

define('MYSQLHOST', 'mysql');
define('MYSQLUSER', 'dragongoserver');
define('MYSQLPASSWORD', '');
define('DB_NAME', 'dragongoserver');


// Dependent of your mysql version:

define('MYSQL_VERSION', '5.0.45');
// may be found with SELECT VERSION(),PASSWORD('foo'),OLD_PASSWORD('foo');
// devel-server is '5.0.51a-log' (client '???') old_exist but new_==old_
// live-server is '5.0.45-log' (client '???') and new_<>old_
// Note: use version-number, that is supported by PHPs version_compare()-function
//       see http://de2.php.net/manual/en/function.version-compare.php

/**
 * MySQL encryption function used for passwords
 * May be 'PASSWORD', 'OLD_PASSWORD', 'MD5', 'SHA1' ...
 *   (adjust check_password() in connect2mysql.php for other methods)
 * Also adjust the default "guest" password into init.mysql
 *  and the type or size of the Password and Newpassword fields.
 * See also: http://dev.mysql.com/doc/refman/5.0/en/old-client.html
 *  and: http://dev.mysql.com/doc/refman/5.0/en/encryption-functions.html
 **/
define('PASSWORD_ENCRYPT', 'SHA1');

// 1 = allow full table-navigation using SQL_CALC_FOUND_ROWS
// IMPORTANT NOTE:
//   Assure, that you've set "mysql.trace_mode = Off" in php.ini for webserver,
//   otherwise FOUND_ROWS() may fail!!
define('ALLOW_SQL_CALC_ROWS', 1);


// Dependent of the configuration of your server:

// OPTIMIZATION:
// restrict initial view in show_games.php when showing ALL games:
// NOTE: used to avoid slow-queries on live-server
//   value is number of relative days for LastMove
//     0 = deactivate restriction
//     e.g. 1 = only select games younger than one day
define('RESTRICT_SHOW_GAMES_ALL', 1);

// Number of attempts to retry connecting to database (see connect2mysql())
// WARNING: needs PHP5 for Windows-Server because of usleep()
// - 0 = no retries (only one connect-try)
define('DB_CONNECT_RETRY_COUNT', 2);
define('DB_CONNECT_RETRY_SLEEP_MS', 1000); // [ms]

define('URI_AMP_IN','&'); //see ini_get('arg_separator.input')
//URI_AMP at '&amp;' work even if arg_separator.output is set to '&'
define('URI_AMP','&amp;'); //see ini_get('arg_separator.output')

// A folder where some elements can be saved which don't need a complete
// rebuilt each time a page is called (for example, the statistics graphs)
// Must be relative to the root folder and ended by a '/'
// Set it to '' (empty string) to disable the cache features.
define('CACHE_FOLDER', 'temp/');

// A folder to store non-public/sensitive data, that should be stored
// outside of the webservers DOCUMENT_ROOT. Must end with '/'.
// The path is relative to the document-root or an absolute path if starting with '/'.
// IMPORTANT: also check notes in INSTALL-file!
define('DATASTORE_FOLDER', '../data-store/');

// Relative path to root-folder ended with a '/'.
// Set it to '' (empty string) to disable user-pictures.
// NOTE: not to be mistaken for an URL, which may look the same
define('USERPIC_FOLDER', 'userpic/');


// Global parameters to control caching

// Default caching-type to use.
// If enabled, this default cache-type is used for cache-groups not listed in $DGS_CACHE_GROUPS below
define('DGS_CACHE', CACHE_TYPE_NONE); // caching disabled
//define('DGS_CACHE', CACHE_TYPE_APC);  // APC-cache needs APC to be installed, see INSTALL-doc
//define('DGS_CACHE', CACHE_TYPE_FILE); // file-cache needs working DATASTORE_FOLDER

// cache debugging: 0 = debug disabled, 1 = write debug-info into error-log
define('DBG_CACHE', 0);

// Defines which cache-type to use for each cache-group:
// - CACHE_TYPE_NONE = disable caching for cache-group
// - CACHE_TYPE_APC  = use APC shared-memory cache
// - CACHE_TYPE_FILE = use file-based caching (requires DATASTORE_FOLDER)
//
// NOTE: Commented out or omitted cache-groups are using the default cache-type defined by DGS_CACHE above!
//
// NOTE: Some cache-groups may require a considerable amount of free space (memory or disc-space dependent on cache).
//       Size estimations are given with comments for defines of CACHE_GRP_...
global $DGS_CACHE_GROUPS;
$DGS_CACHE_GROUPS = array(
      // CACHE_GRP_..         => CACHE_TYPE_NONE|APC|FILE
      #CACHE_GRP_CFG_PAGES     => CACHE_TYPE_APC,
      #CACHE_GRP_CFG_BOARD     => CACHE_TYPE_APC,
      #CACHE_GRP_CLOCKS        => CACHE_TYPE_APC,
      #CACHE_GRP_GAME_OBSERVERS => CACHE_TYPE_APC,
      #CACHE_GRP_GAME_NOTES    => CACHE_TYPE_APC,
      #CACHE_GRP_USER_REF      => CACHE_TYPE_APC,
      #CACHE_GRP_STATS_GAMES   => CACHE_TYPE_APC,
      #CACHE_GRP_FOLDERS       => CACHE_TYPE_APC,
      #CACHE_GRP_PROFILE       => CACHE_TYPE_APC,
      #CACHE_GRP_GAME_MOVES    => CACHE_TYPE_FILE,
      #CACHE_GRP_GAME_MOVEMSG  => CACHE_TYPE_FILE,
      #CACHE_GRP_TOURNAMENT    => CACHE_TYPE_APC,
      #CACHE_GRP_TDIRECTOR     => CACHE_TYPE_APC,
      #CACHE_GRP_TLPROPS       => CACHE_TYPE_APC,
      #CACHE_GRP_TPROPS        => CACHE_TYPE_APC,
      #CACHE_GRP_TRULES        => CACHE_TYPE_APC,
      #CACHE_GRP_TROUND        => CACHE_TYPE_APC,
      #CACHE_GRP_TNEWS         => CACHE_TYPE_APC,
      #CACHE_GRP_TP_COUNT      => CACHE_TYPE_APC,
      #CACHE_GRP_TPARTICIPANT  => CACHE_TYPE_FILE,
      #CACHE_GRP_TRESULT       => CACHE_TYPE_FILE,
      #CACHE_GRP_GAMES         => CACHE_TYPE_FILE,
      #CACHE_GRP_FORUM         => CACHE_TYPE_APC,
      #CACHE_GRP_FORUM_NAMES   => CACHE_TYPE_APC,
      #CACHE_GRP_GAMELIST_STATUS => CACHE_TYPE_FILE,
      #CACHE_GRP_BULLETINS     => CACHE_TYPE_FILE,
      #CACHE_GRP_MSGLIST       => CACHE_TYPE_FILE,
      #CACHE_GRP_TGAMES        => CACHE_TYPE_FILE,
      #CACHE_GRP_TLADDER       => CACHE_TYPE_FILE,
      #CACHE_GRP_GAMESGF_COUNT => CACHE_TYPE_APC,
   );


// Global parameters to configure behaviour for features of your DGS-server:

// Define access keys
// - keep empty with value '' to define no access key
// - keys 0..9 are reserved for bottom page-links
// - unused keys: ajkqy

// access keys always visible in menus
define('ACCKEY_MENU_STATUS',     's');
define('ACCKEY_MENU_WAITROOM',   'r');
define('ACCKEY_MENU_TOURNAMENT', 't');
define('ACCKEY_MENU_USERINFO',   'p');
define('ACCKEY_MENU_MESSAGES',   'b');
define('ACCKEY_MENU_SENDMSG',    'm');
define('ACCKEY_MENU_INVITE',     'i');
define('ACCKEY_MENU_NEWGAME',    'n');
define('ACCKEY_MENU_USERS',      'u');
define('ACCKEY_MENU_CONTACTS',   'c');
define('ACCKEY_MENU_GAMES',      'g');
define('ACCKEY_MENU_FORUMS',     'f');
define('ACCKEY_MENU_FAQ',        'h'); // main-menu: Help
define('ACCKEY_MENU_DOCS',       'd');
define('ACCKEY_MENU_VOTE',       'v');
define('ACCKEY_MENU_TRANSLATE',  'l');
define('ACCKEY_MENU_LOGOUT',     'o');

// access keys for general actions
define('ACCKEY_ACT_EXECUTE',     'x');
define('ACCKEY_ACT_PREVIEW',     'w');
define('ACCKEY_ACT_PREV',        '<');
define('ACCKEY_ACT_NEXT',        '>');
define('ACCKEY_ACT_FILT_SEARCH', 'e');
define('ACCKEY_ACT_FILT_RESET',  'z');

// access keys for specific pages
define('ACCKEYP_GAME_COMMENT',   '');

// Allow JavaScript for some convenience-functionality
define('ALLOW_JAVASCRIPT', true);

// Max. number of running games with 75%-threshold for warning
// - starting games in joined tourneys can cross this limit for player
// - set 0 for unlimited
define('MAX_GAMESRUN', 200);

// Number of running games to accept tourney-registration
// - must be <= MAX_GAMESRUN, no effect if MAX_GAMESRUN=0 or ALLOW_TOURNAMENTS=false
define('MAX_GAMESRUN_TREG', 150);

// TODO-area: forbid chinese-ruleset as long as implementation not fully evolved yet
define('ALLOW_RULESET_CHINESE', false);

// Allow usage of tournaments
define('ALLOW_TOURNAMENTS', false);
define('ALLOW_TOURNAMENTS_ROUND_ROBIN', false); // disable in prod, unfinished impl.

// Allow creation of tournaments only to Tournament-Admin (false) or every user (true)
define('ALLOW_TOURNAMENTS_CREATE_BY_USER', false);

// Allow usage of GoDiagrams (only working with JavaScript)
define('ALLOW_GO_DIAGRAMS', false);

// Allow usage of Game-Editor / old-goban-editor / Game-Viewer
define('ALLOW_GAME_EDITOR', false);
define('ALLOW_OLD_GOBAN_EDITOR', false);
define('ENABLE_GAME_VIEWER', false);

// Forum: 'Quote' works as 'Reply', but inits textbox with previous post in <quote>-tags
define('ALLOW_QUOTING', false);

// Feature-voting: no voting-stuff shown/accessible if disabled
define('ALLOW_FEATURE_VOTE', true);

// Survey-voting: no voting-stuff shown/accessible if disabled
define('ALLOW_SURVEY_VOTE', true);

// Allow usage of new quick-do-suite
define('ALLOW_QUICK_DO', true);

// Forum: number of weeks ending NEW-scope (older entries are considered READ)
define('FORUM_WEEKS_NEW_END', 12);

// Forums.ID of Support-forum (required for some error-conditions)
define('FORUM_ID_SUPPORT', 2);

// Games-list: Configuring number of (starting) chars from private game-notes
// as Notes-column in status-game-list and my-games-list
// - set to 0 to disable
define('LIST_GAMENOTE_LEN', 20);

// enable donation-links
define('ENABLE_DONATIONS', false);

// Players.ID adding <c>-help for game-comments (used for DGS-sensei-account)
// set to 0 to disable
define('AUTO_COMMENT_UID', 9284);

// Months after which game-invitations are deleted (0=keep forever)
define('GAME_INVITATIONS_EXPIRE_MONTHS', 0);

// list with user-handles allowed to login during maintenance-mode
// activate with $is_down var in 'include/quick_common.php'
$ARR_USERS_MAINTENANCE = array();

// IP-blocklist: user with these IPs are blocked
// Syntax: '127.0.0.1' (=ip), '127.0.0.1/32' (=subnet), '/^127\.0\.0\.1$/' (=regex)
// Check Config with: scripts/check_block_ip.php
$ARR_BLOCK_IPLIST = array(
);

?>

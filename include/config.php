<?php

// Friendly names.

//Long name: Keep it AscII
define('FRIENDLY_LONG_NAME', 'Dragon Go Server');
//Short name: Begining with a letter then only letters or numbers
define('FRIENDLY_SHORT_NAME', 'DGS');


// This is the main url. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// set $HOSTBASE = "http://www.some_domain.com/~my_dir/";

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

// Then you should have: $HOSTBASE = "http://" . $HOSTNAME . $SUB_PATH;


// From address for notification emails
define('EMAIL_FROM', 'noreply@localhost');


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

define('ALLOW_SQL_UNION', 1); // 1 = UNION supported (needs min. mysql 4.0.X)

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
// Set it to '' (empty string) to disable the cache features
define('CACHE_FOLDER', 'temp/');

// Relative path to root-folder ended with a '/'.
// Set it to '' (empty string) to disable user-pictures.
// NOTE: not to be mistaken for an URL, which may look the same
define('USERPIC_FOLDER', 'userpic/');


// Global parameters to configure behaviour for features of your DGS-server:

// Define access keys
// - keep empty with value '' to define no access key
// - keys 0..9 are reserved for bottom page-links
// - unused keys: ahjky

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
define('ACCKEY_MENU_FAQ',        'q');
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

// Allow usage of tournaments
define('ALLOW_TOURNAMENTS', false);

// Allow usage of GoDiagrams (only working with JavaScript)
define('ALLOW_GO_DIAGRAMS', false);

// Allow usage of Goban-Editor
define('ALLOW_GOBAN_EDITOR', false);

// Forum: 'Quote' works as 'Reply', but inits textbox with previous post in <quote>-tags
define('ALLOW_QUOTING', false);

// Feature-voting: no voting-stuff shown/accessible if disabled
define('ALLOW_FEATURE_VOTE', true);

// Forum: number of weeks ending NEW-scope (older entries are considered READ)
define('FORUM_WEEKS_NEW_END', 12);

// Games-list: Configuring number of (starting) chars from private game-notes
// as Notes-column in status-game-list and my-games-list
// - set to 0 to disable
define('LIST_GAMENOTE_LEN', 20);

// enable donation-links
define('ENABLE_DONATIONS', false);

// list with user-handles allowed to login during maintenance-mode
// activate with $is_down var in 'include/quick_common.php'
$ARR_USERS_MAINTENANCE = array();

// IP-blocklist: user with these IPs are blocked
// Syntax: '127.0.0.1' (=ip), '127.0.0.1/32' (=subnet), '/^127\.0\.0\.1$/' (=regex)
// Check Config with: scripts/check_block_ip.php
$ARR_BLOCK_IPLIST = array(
);

?>

<?php

// Friendly names.

//Long name: Keep it AscII
$FRIENDLY_LONG_NAME = "Dragon Go Server";
//Short name: Begining with a letter then only letters or numbers
$FRIENDLY_SHORT_NAME = "DGS";


// This is the main url. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// set $HOSTBASE = "http://www.some_domain.com/~my_dir/";

$HOSTBASE = "http://localhost/";

// This is the server name. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// http://www.some_domain.com/index.php
// set $HOSTNAME = "www.some_domain.com";

$HOSTNAME = "localhost";

// This is used for cookies. If the main page is, e.g.,
// http://www.some_domain.com/~my_dir/index.php
// http://localhost/~my_dir/index.php
// http://127.0.0.1/~my_dir/index.php
// set $SUB_PATH = "/~my_dir/";

$SUB_PATH = "/";

// Then you should have: $HOSTBASE = "http://" . $HOSTNAME . $SUB_PATH;


// From address for notification emails
$EMAIL_FROM = "noreply@localhost";


// These four are used to connect to mysql, with a command corresponding to
// mysql -u$MYSQLUSER -p$MYSQLPASSWORD -h$MYSQLHOST -D$DB_NAME

$MYSQLHOST = "mysql";
$MYSQLUSER = "dragongoserver";
$MYSQLPASSWORD = "";
$DB_NAME = "dragongoserver";


// Dependent of your mysql version:

define('MYSQL_VERSION', '3.23.49');
// may be found with SELECT VERSION(),PASSWORD('foo'),OLD_PASSWORD('foo');
// devel-server is '4.1.20-log' (client '3.23.49') old_exist but new_==old_
// live-server is '5.0.22-log' (client '5.0.22') and new_<>old_
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


// Dependent of the configuration of your server:

define('URI_AMP_IN','&'); //see ini_get('arg_separator.input')
//URI_AMP at '&amp;' work even if arg_separator.output is set to '&'
define('URI_AMP','&amp;'); //see ini_get('arg_separator.output')

// A folder where some elements can be saved which don't need a complete
// rebuilt each time a page is called (for example, the statistics graphs)
// Must be relative to the root folder and ended by a '/'
// Set it to '' (empty string) to disable the cache features
define('CACHE_FOLDER', 'temp/');


// Define access keys
// - keep empty with value '' to define no access key
// - keys 0..9 are reserved for bottom page-links

// access keys always visible in menus
define('ACCKEY_MENU_STATUS',     's');
define('ACCKEY_MENU_WAITROOM',   'r');
define('ACCKEY_MENU_USERINFO',   'p');
define('ACCKEY_MENU_MESSAGES',   'b');
define('ACCKEY_MENU_SENDMSG',    'm');
define('ACCKEY_MENU_INVITE',     'i');
define('ACCKEY_MENU_USERS',      'u');
define('ACCKEY_MENU_CONTACTS',   'c');
define('ACCKEY_MENU_GAMES',      'g');
define('ACCKEY_MENU_FORUMS',     'f');
define('ACCKEY_MENU_FAQ',        'q');
define('ACCKEY_MENU_DOCS',       'd');
define('ACCKEY_MENU_VOTE',       'v');
define('ACCKEY_MENU_TRANSLATE',  't');
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

?>

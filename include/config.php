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
//found with SELECT VERSION(),PASSWORD('foo'),OLD_PASSWORD('foo');
//devel-server is '4.1.20-log' (client '3.23.49') old_exist but new_=old_
//live-server is '5.0.22-log' (client '5.0.22') new_<>old_

define('ALLOW_SQL_UNION', 1); // 1 = UNION supported (needs min. mysql 4.0.X)

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


// Dependent of the configuration of your server:

define('URI_AMP_IN','&'); //ini_get('arg_separator.input')
//URI_AMP at '&amp;' work even if arg_separator.output is set to '&'
define('URI_AMP','&amp;'); //ini_get('arg_separator.output')
//define('URI_AMP','&'); //ini_get('arg_separator.output')
?>

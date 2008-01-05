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

define('ALLOW_SQL_UNION', 1); // 1 = UNION supported (needs min. mysql 4.0.X)

//MySQL encryption function used for passwords
//could be 'PASSWORD', 'OLD_PASSWORD', 'MD5'...
//see also http://dev.mysql.com/doc/refman/5.0/en/old-client.html
//also change the default "guest" password into init.mysql
define('PASSWORD_ENCRYPT', 'PASSWORD');


// Dependent of the configuration of your server:

define('URI_AMP_IN','&'); //ini_get('arg_separator.input')
//URI_AMP at '&amp;' work even if arg_separator.output is set to '&'
define('URI_AMP','&amp;'); //ini_get('arg_separator.output')
//define('URI_AMP','&'); //ini_get('arg_separator.output')
?>

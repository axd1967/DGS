<?php

// Friendly names. Keep them AscII!

$FRIENDLY_LONG_NAME = "Dragon Go Server";
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


// Dependent  of the configuration of your server:

define('URI_AMP_IN','&'); //ini_get('arg_separator.input')
//URI_AMP at '&amp;' work even if arg_separator.output is set to '&'
define('URI_AMP','&amp;'); //ini_get('arg_separator.output')
//define('URI_AMP','&'); //ini_get('arg_separator.output')
?>
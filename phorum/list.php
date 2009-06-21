<?php

chdir("../");
require_once( "include/quick_common.php" );
chdir("phorum");

$forum_id = (int)@$_REQUEST['f'];

if( $forum_id > 0 )
   header("Location: ../forum/list.php?forum=$forum_id");
else
   header("Location: ../forum/index.php");

?>
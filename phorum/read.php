<?php

chdir("../");
require_once( "include/quick_common.php" );
chdir("forum/");

connect2mysql();

$old_forum = @$_REQUEST['f']+0;

$old_thread = @$_REQUEST['t']+0;

$old_id = @$_REQUEST['i']+0;

$row = mysql_single_fetch('forum_old_links_redirect',
                          "SELECT ID, Thread_ID, Forum_ID FROM Posts " .
                          "WHERE Forum_ID='$old_forum' AND old_ID='$old_id'")
   or error('unknown_post');

header("Location: ../forum/read.php?forum=" . $row['Forum_ID'] .
       "&thread=" . $row['Thread_ID'] . "#" . $row['ID']);

?>
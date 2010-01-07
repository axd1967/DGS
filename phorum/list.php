<?php

chdir("../");
require_once( "include/quick_common.php" );

{
   $forum_id = (int)@$_REQUEST['f'];

   if( $forum_id > 0 )
      header("Location: ../forum/list.php?forum=$forum_id");
   else
      header("Location: ../forum/index.php");
}
?>

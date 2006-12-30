<?php
do {
  if(!chdir('../')) exit;
} while(!file_exists('include/connect2mysql.php'));
require_once( 'include/connect2mysql.php' );
  jump_to('index.php');
?>

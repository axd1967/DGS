<?php
   $arg = INFO_ALL & ~(INFO_VARIABLES | INFO_ENVIRONMENT);

   if( @$_REQUEST['module'] )
      $arg^= INFO_MODULES;
   if( @$_REQUEST['config'] )
      $arg^= INFO_CONFIGURATION;
   if( @$_REQUEST['env'] )
      $arg^= INFO_ENVIRONMENT;
   if( @$_REQUEST['var'] )
      $arg^= INFO_VARIABLES; //caution: contains PHP_AUTH_USER and PHP_AUTH_PW

   phpinfo($arg);
?>

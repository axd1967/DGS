<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/




define('ERROR_MODE_JUMP', 1);
define('ERROR_MODE_PRINT', 2);
define('ERROR_MODE_COLLECT', 3);

class Errors
{
      var $mode;
      var $errors_are_fatal;
      var $log_errors;

      var $error_list;

      function Errors()
      {
         $this->error_list = array();
         $this->set_mode( ERROR_MODE_JUMP);
      }

      function set_mode($m)
      {
         switch( $m )
         {
         case ERROR_MODE_PRINT:
            $this->mode = $m;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            break;
         case ERROR_MODE_COLLECT:
            $this->mode = $m;
            $this->errors_are_fatal = false;
            $this->log_errors = true;
            break;
         default:
         case ERROR_MODE_JUMP:
            $this->mode = ERROR_MODE_JUMP;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            break;
         }
      }


      function echo_error_list($html_mode=false)
      {
         $str = '';

         reset($this->error_list);
         while( list($key, $val) = each($this->error_list) )
         {
            list($err, $debugmsg) = $val;
            $str .= "#Error: " . trim(ereg_replace( "[\x01-\x20]+", " ", $err)) ."\n";
            if( $html_mode ) $str .= "<br>\n";
            $str .= "debugmsg: " . $debugmsg . "\n";
            $str .= ( $html_mode ? "<hr>\n" :"\n");
         }

         echo $str;
      }


      function add_error($err, $debugmsg=NULL, $warn=false)
      {
         global $player_row;
         if( isset($player_row) and isset($player_row['Handle']))
            $handle = $player_row['Handle'];
         else
            $handle = safe_getcookie('handle');

         if( $this->log_errors and !$warn )
            list( $err, $uri)= err_log( $handle, $err, $debugmsg);
         else
            $uri = "error.php?err=" . urlencode($err);


         if( $this->mode == ERROR_MODE_COLLECT )
         {
            $this->error_list[] = array($err, $debugmsg);
         }
         else if( $this->mode == ERROR_MODE_PRINT or $warn )
         {
            echo ( $warn ? "#Warning: " : "#Error: " ) .
               trim(ereg_replace( "[\x01-\x20]+", " ", $err));
         }
         else // case ERROR_MODE_JUMP:
         {
            disable_cache();
            jump_to( $uri );
         }

         if( $this->errors_are_fatal and !$warn )
            exit;
      }


}

$TheErrors = new Errors();



if( !function_exists('error') )
{
function error($err, $debugmsg=NULL)
{
   global $TheErrors;

   $TheErrors->add_error($err, $debugmsg);
}
}

if( !function_exists('warning') )
{
function warning($err)
{
   global $TheErrors;

   $TheErrors->add_error($err, NULL, true);
}
}



function err_log( $handle, $err, $debugmsg=NULL)
{

   $uri = "error.php?err=" . urlencode($err);
   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $errorlog_query = "INSERT INTO Errorlog SET Handle='".mysql_escape_string($handle)."'"
      .", Message='".mysql_escape_string($err)."', IP='".mysql_escape_string($ip)."'" ;

   $mysqlerror = @mysql_error();

   if( !empty($mysqlerror) )
   {
      $uri .= URI_AMP."mysqlerror=" . urlencode($mysqlerror);
      $errorlog_query .= ", MysqlError='".mysql_escape_string( $mysqlerror)."'";
      $err.= ' / '. $mysqlerror;
   }


   if( empty($debugmsg) )
   {
    global $SUB_PATH;
//CAUTION: sometime, REQUEST_URI != PHP_SELF+args
//if there is a redirection, _URI==requested, while _SELF==reached (running one)
      $debugmsg = @$_SERVER['REQUEST_URI']; //@$_SERVER['PHP_SELF'];
      //$debugmsg = str_replace( $SUB_PATH, '', $debugmsg);
      $debugmsg = substr( $debugmsg, strlen($SUB_PATH));
   }
   if( !empty($debugmsg) )
   {
      $errorlog_query .= ", Debug='" . mysql_escape_string( $debugmsg) . "'";
      //$err.= ' / '. $debugmsg; //Do not display this info!
   }

   global $dbcnx;
   if( !@$dbcnx )
      connect2mysql( true);

   @mysql_query( $errorlog_query );

   return array( $err, $uri);
}

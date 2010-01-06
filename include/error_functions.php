<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once( 'include/quick_common.php' );
require_once( 'include/connect2mysql.php' );


define('ERROR_MODE_JUMP', 1);
define('ERROR_MODE_PRINT', 2);
define('ERROR_MODE_COLLECT', 3);
define('ERROR_MODE_TEST', 4);

class DgsErrors
{
   var $mode;
   var $errors_are_fatal;
   var $log_errors;
   var $collect_errors;
   var $print_errors;

   var $error_list;

   function DgsErrors()
   {
      $this->error_list = array();
      $this->set_mode( ERROR_MODE_JUMP);
   }

   function set_mode($m)
   {
      $p= $this->mode;
      $m= (int)$m;
      switch( (int)$m )
      {
         case ERROR_MODE_PRINT:
            $this->mode = $m;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            $this->collect_errors = false;
            $this->print_errors = true;
            break;
         case ERROR_MODE_COLLECT:
            $this->mode = $m;
            $this->errors_are_fatal = false;
            $this->log_errors = true;
            $this->collect_errors = true;
            $this->print_errors = false;
            break;
         case ERROR_MODE_TEST:
            $this->mode = $m;
            $this->errors_are_fatal = false;
            $this->log_errors = false;
            $this->collect_errors = true;
            $this->print_errors = false;
            break;

         default:
         case ERROR_MODE_JUMP:
            $this->mode = ERROR_MODE_JUMP;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            $this->collect_errors = false;
            $this->print_errors = false;
            break;
      }
      return $p;
   }


   function list_string($prefix='', $html_mode=false)
   {
      $str = '';
      foreach( $this->error_list as $ary )
      {
         list($err, $debugmsg, $warn) = $ary;
         $warn= ($warn ?'Warning' :'Error' );
         if( $html_mode )
         {
            $tmp = @htmlspecialchars($debugmsg, ENT_QUOTES);
            if( $tmp ) $debugmsg = $tmp;
            $str.= "\n<dt class=$warn>#$warn: $err</dt>"
               . "\n<dd>debugmsg: $prefix-$debugmsg</dd>";
         }
         else
            $str.= "#$warn: $err\ndebugmsg: $prefix-$debugmsg\n\n";
      }
      if( $str && $html_mode )
         $str= "\n<dl class=ErrorList>$str\n</dl>";
      return $str;
   } //list_string

   function error_count()
   {
      return count($this->error_list);
   }

   function error_clear()
   {
      $this->error_list = array();
   }

   //FIXME ??? NOTE on $html_mode: remove arg $html_mode (the client should close the HTML-page not the error-func)
   function dump_exit($prefix='', $html_mode=false)
   {
      echo $this->list_string($prefix, $html_mode);
      $this->error_clear();
      if( $html_mode )
         echo "\n</BODY></HTML>\n"; // at least
      //FIXME ??? need the following ???:   ob_end_flush();
      exit;
   } //dump_exit


   function add_error($err, $debugmsg=NULL, $warn=false)
   {
      global $player_row;
      if( isset($player_row) && isset($player_row['Handle']) )
         $handle = $player_row['Handle'];
      else
         $handle = safe_getcookie('handle');

      $err= trim(preg_replace( "%[\\x1-\\x20\\x80-\\xff<&>_]+%", "_", $err));
      if( $this->log_errors && !$warn )
         list( $err, $uri)= err_log( $handle, $err, $debugmsg);
      else
      {
         $uri = "error.php?err=" . urlencode($err);
         if( !is_null($debugmsg) ) $uri .= URI_AMP . 'debugmsg=' . urlencode($debugmsg);
      }

      if( $this->collect_errors )
         $this->error_list[] = array($err, $debugmsg, $warn);

      if( $this->print_errors || $warn  )
         echo '[', ( $warn ? "#Warning" : "#Error" ), ": $err; $debugmsg]\n";

      if( !$warn && $this->mode == ERROR_MODE_JUMP )
      {
         disable_cache();
         jump_to( $uri );
      }

      if( $this->errors_are_fatal && !$warn )
         exit;
      return false;
   } //add_error

} //end of class 'DgsErrors'




global $TheErrors; //PHP5
$TheErrors = new DgsErrors();

if( !function_exists('error') )
{
   function error($err, $debugmsg=NULL)
   {
      global $TheErrors;
      return $TheErrors->add_error($err, $debugmsg);
   }
}

if( !function_exists('warning') )
{
   function warning($err)
   {
      global $TheErrors;
      return $TheErrors->add_error($err, NULL, true);
   }
}



function err_log( $handle, $err, $debugmsg=NULL)
{
   $mysqlerror = @mysql_error();

   global $dbcnx, $player_row;
   if( !@$dbcnx )
      connect2mysql(true);

   $uri = "error.php?err=" . urlencode($err);
   if( !is_null($debugmsg) ) $uri .= URI_AMP . 'debugmsg=' . urlencode($debugmsg);

   $uid = (int)@$player_row['ID'];
   $ip = (string)@$_SERVER['REMOTE_ADDR'];

   //CAUTION: sometime, REQUEST_URI != PHP_SELF+args
   //if there is a redirection, _URI==requested, while _SELF==reached (running one)
   $request = @$_SERVER['REQUEST_URI']; //@$_SERVER['PHP_SELF'];
   $request = substr( $request, strlen(SUB_PATH));

   $errorlog_query = "INSERT INTO Errorlog SET"
                     . " uid='$uid'"
                     . ", Handle='".mysql_addslashes($handle)."'"
                     . ", Message='".mysql_addslashes($err)."'"
                     . ", Request='".mysql_addslashes($request)."'"
                     . ", IP='".mysql_addslashes($ip)."'" ; //+ Date= timestamp

   if( !empty($mysqlerror) )
   {
      $uri .= URI_AMP."mysqlerror=" . urlencode($mysqlerror);
      $errorlog_query .= ", MysqlError='".mysql_addslashes($mysqlerror)."'";
      $err.= ' / '. $mysqlerror;
   }
   if( is_string($debugmsg) )
      $errorlog_query .= ", Debug='".mysql_addslashes($debugmsg)."'";

   if( $dbcnx )
   {
      if( @mysql_query( $errorlog_query ) !== false )
         $uri .= URI_AMP."eid=" . mysql_insert_id();
   }

   return array( $err, $uri);
} //err_log

?>

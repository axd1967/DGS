<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival

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

require_once 'include/quick_common.php';
require_once 'include/connect2mysql.php';


define('ERROR_MODE_JUMP', 1);
define('ERROR_MODE_PRINT', 2);
define('ERROR_MODE_COLLECT', 3);
define('ERROR_MODE_TEST', 4);
define('ERROR_MODE_QUICK_SUITE', 5);

define('ERROR_FORMAT_TEXT', 1);
define('ERROR_FORMAT_JSON', 2);

define('ERROR_HEADER_DEFAULT', 0);
define('ERROR_HEADER_TEXT', 1);
define('ERROR_HEADER_JSON', 2);

class DgsErrors
{
   private $mode;
   private $errors_are_fatal;
   private $log_errors;
   private $collect_errors;
   private $print_errors;
   private $error_format; // ERROR_FORMAT_...
   private $error_header; // ERROR_HEADER_...

   private $error_list = array();

   public function __construct()
   {
      $this->set_mode(ERROR_MODE_JUMP);
   }

   public function set_mode( $m )
   {
      $p= $this->mode;
      $m= (int)$m;
      switch ( (int)$m )
      {
         case ERROR_MODE_PRINT:
            $this->mode = $m;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            $this->collect_errors = false;
            $this->print_errors = true;
            $this->error_format = ERROR_FORMAT_TEXT;
            $this->error_header = ERROR_HEADER_DEFAULT;
            break;
         case ERROR_MODE_COLLECT:
            $this->mode = $m;
            $this->errors_are_fatal = false;
            $this->log_errors = true;
            $this->collect_errors = true;
            $this->print_errors = false;
            $this->error_format = ERROR_FORMAT_TEXT;
            $this->error_header = ERROR_HEADER_DEFAULT;
            break;
         case ERROR_MODE_TEST:
            $this->mode = $m;
            $this->errors_are_fatal = false;
            $this->log_errors = false;
            $this->collect_errors = true;
            $this->print_errors = false;
            $this->error_format = ERROR_FORMAT_TEXT;
            $this->error_header = ERROR_HEADER_DEFAULT;
            break;
         case ERROR_MODE_QUICK_SUITE:
            $this->mode = $m;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            $this->collect_errors = false;
            $this->print_errors = true;
            $this->error_format = ERROR_FORMAT_JSON;
            $this->error_header = ERROR_HEADER_JSON;
            break;

         default:
         case ERROR_MODE_JUMP:
            $this->mode = ERROR_MODE_JUMP;
            $this->errors_are_fatal = true;
            $this->log_errors = true;
            $this->collect_errors = false;
            $this->print_errors = false;
            $this->error_format = ERROR_FORMAT_TEXT;
            $this->error_header = ERROR_HEADER_DEFAULT;
            break;
      }
      return $p;
   }//set_mode


   public function list_string($prefix='', $html_mode=false)
   {
      $str = '';
      foreach ( $this->error_list as $ary )
      {
         list($err, $debugmsg, $warn) = $ary;
         $warn= ($warn ?'Warning' :'Error' );
         if ( $html_mode )
         {
            $tmp = @htmlspecialchars($debugmsg, ENT_QUOTES);
            if ( $tmp ) $debugmsg = $tmp;
            $str.= "\n<dt class=$warn>#$warn: $err</dt>"
               . "\n<dd>debugmsg: $prefix-$debugmsg</dd>";
         }
         else
            $str.= "#$warn: $err\ndebugmsg: $prefix-$debugmsg\n\n";
      }
      if ( $str && $html_mode )
         $str= "\n<dl class=ErrorList>$str\n</dl>";
      return $str;
   }//list_string

   public function error_count()
   {
      return count($this->error_list);
   }

   public function error_clear()
   {
      $this->error_list = array();
   }

   public function dump_exit($prefix='', $html_mode=false)
   {
      echo $this->list_string($prefix, $html_mode);
      $this->error_clear();
      if ( $html_mode )
         echo "\n</BODY></HTML>\n"; // at least

      global $ThePage;
      if ( !($ThePage instanceof HTMLPage) && ob_get_level() > 0 )
         ob_end_flush();

      exit;
   }//dump_exit


   public function add_error($err, $debugmsg=NULL, $warn=false, $allow_log_error=true )
   {
      global $player_row;
      if ( isset($player_row) && isset($player_row['Handle']) )
         $handle = $player_row['Handle'];
      else
         $handle = safe_getcookie('handle');

      $err= trim(preg_replace( "%[\\x1-\\x20\\x80-\\xff<&>_]+%", "_", $err));
      if ( $allow_log_error && $this->log_errors && !$warn )
         list( $err, $uri ) = self::err_log( $handle, $err, $debugmsg);
      else
      {
         $uri = "error.php?err=" . urlencode($err);
         if ( !is_null($debugmsg) ) $uri .= URI_AMP . 'debugmsg=' . urlencode($debugmsg);
      }

      if ( $this->collect_errors )
         $this->error_list[] = array($err, $debugmsg, $warn);

      if ( $this->print_errors || $warn )
      {
         if ( $this->error_header == ERROR_HEADER_JSON )
            header('Content-Type: application/json');
         elseif ( $this->error_header == ERROR_HEADER_TEXT )
            header('Content-Type: text/plain;charset=utf-8');
         //else //if ( $this->error_header == ERROR_HEADER_DEFAULT )

         if ( $this->error_format == ERROR_FORMAT_TEXT )
            echo '[', ( $warn ? "#Warning" : "#Error" ), ": $err; $debugmsg]\n";
         else //if ( $this->error_format == ERROR_FORMAT_JSON )
         {
            if ( !$warn )
            {
               $err_arr = array(
                     'version' => QUICK_SUITE_VERSION,
                     'error' => $err,
                     'error_msg' => $debugmsg,
                  );
               quick_suite_add_quota( $err_arr );

               echo dgs_json_encode( $err_arr );
            }
         }
      }

      if ( !$warn && $this->mode == ERROR_MODE_JUMP )
      {
         $page_url = ( $err == 'login_if_not_logged_in' ) ? URI_AMP.'page='.urlencode(get_request_url()) : '';
         disable_cache();
         jump_to( $uri . $page_url );
      }

      if ( $this->errors_are_fatal && !$warn )
         exit;
      return false;
   }//add_error


   // ------------ static functions ----------------------------

   public static function err_log( $handle, $err_code, $debugmsg=NULL)
   {
      global $dbcnx, $player_row, $is_down;

      $mysqlerror = @mysql_error();

      $uri = "error.php?err=" . urlencode($err_code);
      if ( !is_null($debugmsg) ) $uri .= URI_AMP . 'debugmsg=' . urlencode($debugmsg);

      $uid = (int)@$player_row['ID'];
      $ip = (string)@$_SERVER['REMOTE_ADDR'];

      //CAUTION: sometime, REQUEST_URI != PHP_SELF+args
      //if there is a redirection, _URI==requested, while _SELF==reached (running one)
      $request = @$_SERVER['REQUEST_URI']; //@$_SERVER['PHP_SELF'];
      $request = substr( $request, strlen(SUB_PATH));

      $err_msg = $err_code;
      if ( !empty($mysqlerror) )
      {
         $uri .= URI_AMP."mysqlerror=" . urlencode($mysqlerror);
         $err_msg .= ' / '. $mysqlerror;
      }

      if ( self::skip_db_connect($err_code) )
         $uri .= URI_AMP."req_uri=" . urlencode(substr($request,0,255)); // trim a bit

      if ( !$is_down && self::need_db_errorlog($err_code) )
      {
         if ( !@$dbcnx )
            connect2mysql(true);

         $errorlog_query = "INSERT INTO Errorlog SET"
                        . " uid='$uid'"
                        . ", Handle='".mysql_addslashes($handle)."'"
                        . ", Message='".mysql_addslashes($err_msg)."'"
                        . ", Request='".mysql_addslashes($request)."'"
                        . ", IP='".mysql_addslashes($ip)."'" ; //+ Date= timestamp
         if ( !empty($mysqlerror) )
            $errorlog_query .= ", MysqlError='".mysql_addslashes($mysqlerror)."'";
         if ( is_string($debugmsg) )
            $errorlog_query .= ", Debug='".mysql_addslashes($debugmsg)."'";

         if ( $dbcnx )
         {
            if ( @mysql_query( $errorlog_query ) !== false )
               $uri .= URI_AMP."eid=" . mysql_insert_id();
         }
      }

      return array( $err_msg, $uri);
   }//err_log


   /*!
    * \brief Returns true if errorlog should be written in Errorlog-table; false otherwise.
    * \see ErrorCode::init() for list of error-codes
    */
   public static function need_db_errorlog( $errcode )
   {
      // keep in alphabetic-order
      static $skip_dblog = array(
            'bulkmessage_self' => 1,
            'cookies_disabled' => 1,
            'early_pass' => 1,
            'entity_init_error' => 1,
            'guest_may_not_receive_messages' => 1,
            'guest_no_invite' => 1,
            'handicap_range' => 1,
            'invalid_args:nolog' => 1,
            'invite_self' => 1,
            'ko' => 1,
            'komi_bad_fraction' => 1,
            'komi_range' => 1,
            'login_if_not_logged_in' => 1,
            'multi_player_master_mismatch' => 1,
            'multi_player_need_initial_rating' => 1,
            'multi_player_no_users' => 1,
            'mysql_connect_failed' => 1,
            'mysql_select_db_failed' => 1,
            'name_not_given' => 1,
            'need_activation' => 1,
            'not_allowed_for_guest' => 1,
            'not_logged_in' => 1,
            'optlock_clash' => 1,
            'password_illegal_chars' => 1,
            'password_mismatch' => 1,
            'password_too_short' => 1,
            'rank_not_rating' => 1,
            'rating_not_rank' => 1,
            'rating_out_of_range' => 1,
            'receiver_not_found' => 1,
            'register_miss_email' => 1,
            'registration_policy_not_checked' => 1,
            'server_down' => 1,
            'suicide' => 1,
            'time_limit_too_small' => 1,
            'tournament_director_min1' => 1,
            'unknown_user' => 1,
            'userid_illegal_chars' => 1,
            'userid_in_use' => 1,
            'userid_spam_account' => 1,
            'userid_too_long' => 1,
            'userid_too_short' => 1,
            'value_not_numeric' => 1,
            'value_out_of_range' => 1,
            'waitingroom_game_not_found' => 1,
            'verification_invalidated' => 1,
            'waitingroom_not_enough_rated_fin_games' => 1,
            'waitingroom_not_in_rating_range' => 1,
            'waitingroom_not_same_opponent' => 1,
            'waitingroom_own_game' => 1,
         );
      return !isset($skip_dblog[$errcode]);
   }//need_db_errorlog

   /*! \brief Returns true for errors, that need special error-handling as db-connect failed. */
   public static function skip_db_connect( $errcode )
   {
      static $no_db_connect = array(
            'mysql_connect_failed' => 1,
            'mysql_select_db_failed' => 1,
         );
      return isset($no_db_connect[$errcode]);
   }

} //end of class 'DgsErrors'




global $TheErrors; //PHP5
$TheErrors = new DgsErrors();

if ( !function_exists('error') )
{
   function error($err, $debugmsg=NULL, $log_error=true )
   {
      global $TheErrors;
      return $TheErrors->add_error($err, $debugmsg, /*warn*/false, $log_error );
   }
}

if ( !function_exists('warning') )
{
   function warning($err, $debugmsg=NULL )
   {
      global $TheErrors;
      return $TheErrors->add_error($err, $debugmsg, /*warn*/true );
   }
}

?>

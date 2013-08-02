<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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


$TranslateGroups[] = "Error";

require_once 'include/std_functions.php';
require_once 'include/error_codes.php';


{
   if ( !$is_down )
      connect2mysql(true);

   $BlockReason = '';
   $userid = '???';
   $is_admin = false;
   if ( !$is_down && $dbcnx )
   {
      $tmp= $TheErrors->set_mode(ERROR_MODE_COLLECT);
      //may call error() again:
      $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_UPDATE|LOGIN_SKIP_VFY_CHK );
      $TheErrors->set_mode($tmp);
      if ( !is_null($player_row) )
      {
         $userid = @$player_row['Handle'];
         $BlockReason = @$player_row['BlockReason'];
         $is_admin = ( @$player_row['admin_level'] & (ADMIN_DATABASE|ADMIN_DEVELOPER) );
      }
   }
   else
   {
      $logged_in = false;
      $player_row = NULL;
   }

   $err = get_request_arg('err');
   $errorlog_id = get_request_arg('eid');

   // written if set, can be resetted to NULL on certain errors to avoid publishing sensitive data
   $debugmsg = get_request_arg('debugmsg');

/*
Within the .htaccess file of the server root:
(where /DragonGoServer/ is SUB_PATH)
# 401: Authorization required (must be local URL)
ErrorDocument 401 /DragonGoServer/error.php?err=page_not_found&redir=htaccess
# 403: Access denied
ErrorDocument 403 /DragonGoServer/error.php?err=page_not_found&redir=htaccess
# 404: Not Found
ErrorDocument 404 /DragonGoServer/error.php?err=page_not_found&redir=htaccess
# 500: Server Error.
*/

   $redir = get_request_arg('redir');
   if ( $dbcnx && $redir /* && @$_SERVER['REDIRECT_STATUS'] != 401 */ )
   { //need to be recorded
      //temporary hide an unsolved DGS problem, waiting better:
      if (!( @$_SERVER['REDIRECT_STATUS'] == 404
            && in_array(
               substr( @$_SERVER['REDIRECT_URL'], strlen(SUB_PATH)),
               array('phorum/post.php','favicon.ico'))
         ))
      {
         if ( isset($player_row) && isset($player_row['Handle']) )
            $handle = $player_row['Handle'];
         else
            $handle = safe_getcookie('handle');
         $debugmsg= $redir  # htaccess
            .' 1='.@$_SERVER['REDIRECT_STATUS']  # 404
            .' 2='.@$_SERVER['REDIRECT_URL']  # /phorum/post.php (from domain root)
            .' 3='.@$_SERVER['REDIRECT_ERROR_NOTES']  # /
            .' 4='.@$_SERVER['HTTP_REFERER']
            //.' 5='.getcwd()  # the root folder
            //.' 6='.@$_SERVER['REQUEST_URI']  #
            ;
         list( $err, $uri ) = DgsErrors::err_log( $handle, $err, $debugmsg);
         db_close();
         //must restart from the root else $base_path is not properly set
         jump_to($uri,0);
      }
   }

   start_page("Error", true, $logged_in, $player_row );
   echo "<br>\n";


   // NOTE:
   // - When adding a new error-code, add output of errorlog_id if it is helpful
   //   to know the Errorlog.ID to identify a problem.
   //   Having an ID relieves support of searching in big Errorlog-table.
   // - It's not always useful to log errorlog_id,
   //   e.g. not for errors indicating a false behaviour of a user

   $hide_dbgmsg = handle_error( $err, $errorlog_id, $userid, $is_admin, $BlockReason );
   if ( $hide_dbgmsg )
      $debugmsg = NULL;

   db_close();

   // also output detailed debug-msg
   if ( !is_null($debugmsg) && !$hide_dbgmsg && (string)$debugmsg != '' )
      echo '<p>', span('ErrorMsg', 'Error details: '), basic_safe($debugmsg), '</p>';

   $mysqlerror = get_request_arg('mysqlerror');
   if ( $mysqlerror )
   {
      $mysqlerror = str_replace(
         array( MYSQLHOST, DB_NAME, MYSQLUSER, MYSQLPASSWORD),
         array( '[*host*]', '[*db*]', '[*user*]', '[*pwd*]'),
         $mysqlerror);
      echo '<p>', span('ErrorMsg', 'MySQL error: '), basic_safe($mysqlerror), '</p>';
   }

   end_page();
}//main


// special handling for error-codes
// returns true, if debug-message should be hidden (because containing sensitive data)
function handle_error( $error_code, $errorlog_id, $userid, $is_admin, $block_reason )
{
   $jump_to_login = ( isset($_REQUEST['page']) && $error_code == 'login_if_not_logged_in' );

   if ( !$jump_to_login )
      ErrorCode::echo_error_text($error_code, $errorlog_id);

   $hide_debugmsg = false;
   if ( ErrorCode::is_sensitive($error_code) )
      $hide_debugmsg = true;

   switch ( (string)$error_code )
   {
      case('login_if_not_logged_in'):
         // show login-page passing on error-code-info and "original" page for later redirect after login
         if ( $jump_to_login )
            jump_to('index.php?err=not_logged_in'.URI_AMP.'eid='.urlencode($errorlog_id).URI_AMP.'page='.urlencode($_REQUEST['page']));
         break;

      case('fever_vault'):
         jump_to('index.php?logout=1'.URI_AMP.'err=fever_vault'.URI_AMP.'eid='.urlencode($errorlog_id));
         break;

      case('login_denied'):
      {
         if ( (string)$userid != '' )
            admin_log( 0, $userid, 'login_denied');

         if ( (string)$block_reason != '' )
         {
            ErrorCode::echo_error_text( 'login_denied:blocked_with_reason', $errorlog_id );
            echo "<br><br>\n",
               '<table><tr><td>',
                  make_html_safe($block_reason, 'msg'),
               "</td></tr></table><br>\n";
         }
         else
            ErrorCode::echo_error_text( 'login_denied:blocked', $errorlog_id );
         break;
      }//login_denied

      case('mysql_query_failed'):
         if ( !$is_admin ) $hide_debugmsg = true; // contains DB-query
         break;

      case('edit_bio_denied'):
      case('adminlevel_too_low'):
         if ( (string)$userid != '' )
            admin_log( 0, $userid, $error_code );
         break;

      case('ip_blocked_register'):
         jump_to("do_registration_blocked.php?errlog_id=$errorlog_id");
         break;
   } //end-switch

   return $hide_debugmsg;
} //handle_error

?>

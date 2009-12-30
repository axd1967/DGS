<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );
require_once( "include/error_codes.php" );


{
   connect2mysql(true);

   $BlockReason = '';
   $userid = '???';
   $is_admin = false;
   if( $dbcnx )
   {
      $tmp= $TheErrors->set_mode(ERROR_MODE_COLLECT);
      //may call error() again:
      $logged_in = who_is_logged( $player_row);
      $TheErrors->set_mode($tmp);
      if( !is_null($player_row) )
      {
         $userid = @$player_row['Handle'];
         $BlockReason = @$player_row['BlockReason'];
         $is_admin = ( @$player_row['admin_level'] & ADMINGROUP_EXECUTIVE );
      }
   }
   else
   {
      $logged_in = false;
      $player_row = NULL;
   }

   $err = get_request_arg('err');

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
   if( $dbcnx && $redir /* && @$_SERVER['REDIRECT_STATUS'] != 401 */ )
   { //need to be recorded
      //temporary hide an unsolved DGS problem, waiting better:
      if(!( @$_SERVER['REDIRECT_STATUS'] == 404
            && in_array(
               substr( @$_SERVER['REDIRECT_URL'], strlen(SUB_PATH)),
               array('phorum/post.php','favicon.ico'))
         ))
      {
         if( isset($player_row) && isset($player_row['Handle']) )
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
         list( $err, $uri)= err_log( $handle, $err, $debugmsg);
         db_close();
         //must restart from the root else $base_path is not properly set
         jump_to($uri,0);
      }
   }

   start_page("Error", true, $logged_in, $player_row );
   echo '&nbsp;<br>';

   // prep output of error-log ID
   $errorlog_id = get_request_arg('eid');
   if( $errorlog_id )
      $errorlog_id = " [ERRLOG{$errorlog_id}]: ";

   // written if set, can be resetted to NULL on certain errors to avoid publishing sensitive data
   $debugmsg = get_request_arg('debugmsg');


   // NOTE:
   // - When adding a new error-code, add output of errolog_id if it is helpful
   //   to know the Errorlog.ID to identify a problem.
   //   Having a ID releaves support of searching in big Errorlog-table.
   // - It's not always useful to log errorlog_id,
   //   e.g. not for errors indicating a false behaviour of a user

   //TODO handle syntax-checks defined in here on edit-pages without redirect to error-page

   $hide_dbgmsg = handle_error( $err, $errorlog_id, $userid, $is_admin, $BlockReason );
   if( $hide_dbgmsg )
      $debugmsg = NULL;

   db_close();

   $mysqlerror = get_request_arg('mysqlerror');
   if( $mysqlerror )
   {
      $mysqlerror = str_replace(
         array( MYSQLHOST, DB_NAME, MYSQLUSER, MYSQLPASSWORD),
         array( '[*h*]', '[*d*]', '[*u*]', '[*p*]'),
         $mysqlerror);
      echo "<p>MySQL error: ".basic_safe($mysqlerror)."</p>";
   }

   // also output detailed debug-msg
   if( !is_null($debugmsg) && !$hide_dbgmsg && (string)$debugmsg != '' )
      echo "<p>Error details: [$debugmsg]</p>";

   echo '<p></p>';
   end_page();
}


// special handling for error-codes
// returns true, if debug-message should be hidden (because containing sensitive data)
function handle_error( $error_code, $errorlog_id, $userid, $is_admin, $block_reason )
{
   $hide_debugmsg = false;

   Errorcode::echo_error_text($error_code, $errorlog_id);

   if( Errorcode::is_sensitive($error_code) )
      $hide_debugmsg = true;

   switch( (string)$error_code )
   {
      case('mysql_query_failed'):
         if( !$is_admin ) $hide_debugmsg = true; // contains DB-query
         break;

      case('ip_blocked_register'):
      {
         echo "<br><br>\n",
            '<form action="do_registration_blocked.php" method="post">',
            T_('To register a new account despite the IP-block, a user account can be created by an admin.'),
            "<br>\n",
            T_('Please enter a user-id for the DragonGoServer and your email to send you the login details.'),
            "<br><br>\n",
            T_('An IP-block is only used to keep misbehaving users away.'),
            "<br>\n",
            T_('So please add why you want to bypass the IP-block in the Comments-field.'),
            "<br>\n",
            T_('There you can also add questions you might have.'),
            "<br>\n",
            T_('All fields must be provided in order to fulfill the request.'),
            "<br><br>\n",
            '<table>',
              '<tr>',
                '<td>', T_('Userid'), ':</td>',
                '<td><input type="text" name="userid" value="" size="16"></td>',
              '</tr>', "\n",
              '<tr>',
                '<td>', T_('Email'), ':</td>',
                '<td><input type="text" name="email" value="" size="50"></td>',
              '</tr>', "\n",
              '<tr>',
                '<td>&nbsp;</td>',
                '<td><input type="checkbox" name="policy" value="1">&nbsp;'
                     . sprintf( T_('I have read and accepted the DGS <a href="%s" target="dgsTOS">Rules of Conduct</a>.'), HOSTBASE."policy.php" ) . '</td>',
              '</tr>', "\n",
              '<tr><td colspan="2">&nbsp;</td></tr>', "\n",
              '<tr>',
                '<td>', T_('Comments'), ':</td>',
                '<td><textarea name="comment" cols="60" rows="10"></textarea></td>',
              '</tr>', "\n",
            '</table>', "\n",
            sprintf( '<input type="submit" name="send_request" value="%s">', T_('Send registration request') ),
            '</form>',
            "\n";
         break;
      }//ip_blocked_register

      case('login_denied'):
      {
         if( (string)$userid != '' )
            admin_log( 0, $userid, 'login_denied');

         if( (string)$block_reason != '' )
         {
            Errorcode::echo_error_text( 'login_denied:blocked_with_reason', $errorlog_id );
            echo "<br><br>\n",
               '<table><tr><td>',
                  make_html_safe($block_reason, 'msg'),
               "</td></tr></table><br>\n";
         }
         else
            Errorcode::echo_error_text( 'login_denied:blocked', $errorlog_id );
         break;
      }//login_denied

      case('edit_bio_denied'):
      case('adminlevel_too_low'):
         if( (string)$userid != '' )
            admin_log( 0, $userid, $error_code );
         break;

   } //end-switch

   return $hide_debugmsg;
} //handle_error

?>

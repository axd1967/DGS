<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/std_functions.php" );
chdir('forum');
require_once( 'forum_functions.php' );
chdir('..');

define('USE_REGEXP_REGISTRATION',1); //loose account name reject


{
   connect2mysql();

   if( !is_blocked_ip() )
      error( 'not_logged_in' ); // block spammer, call only if IP-blocked

   $uhandle = get_request_arg('userid');
   if( strlen( $uhandle ) < 3 )
      error("userid_too_short");
   if( illegal_chars( $uhandle ) )
      error("userid_illegal_chars");

   $email = get_request_arg('email');
   verify_email( 'do_registration_blocked', $email);

   $policy = get_request_arg('policy');
   if( !$policy )
     error("registration_policy_not_checked");

   $comment = get_request_arg('comment');

   if( !USE_REGEXP_REGISTRATION )
   {
   //if foO exist, reject foo but accept fo0 (with a zero instead of uppercase o)
      $result = db_query( 'do_registration.find_player',
            "SELECT Handle FROM Players WHERE Handle='".mysql_addslashes($uhandle)."'" );
   }
   else
   {
   //reject the O0, l1I and S5 confusing matchings (used by account usurpers)
   //for instance, with the Arial font, a I and a l can't be distinguished
      $regx = preg_quote($uhandle); //quotemeta()
      $regx = eregi_replace( '[0o]', '[0o]', $regx);
      $regx = eregi_replace( '[1li]', '[1li]', $regx);
      $regx = eregi_replace( '[5s]', '[5s]', $regx);
      $regx = mysql_addslashes($regx);
      $regx = '^'.$regx.'$';

      $result = db_query( 'do_registration.find_player_regexp',
            "SELECT Handle FROM Players WHERE Handle REGEXP '$regx'" );
   }

   if( @mysql_num_rows($result) > 0 )
      error('userid_in_use');

// Userid and email is fine, now send request to Support-forum (moderated)

   $ip = (string)@$_SERVER['REMOTE_ADDR'];
   $Subject = "Admin-Request to register account to bypass IP-block";
   $Text = "This is an automated support-request to register a new account on behalf of an IP-blocked user with the following credentials:\n"
      . "Userid: $uhandle\n"
      . "Email: $email\n"
      . "Blocked-IP: $ip\n\n"
      . "User-Comment:\n"
      . $comment;

   $query = "INSERT INTO Posts SET " .
            "Forum_ID=2, " . // hard-wired: Support forum
            "Thread_ID=0, " .
            "Time=FROM_UNIXTIME($NOW), " .
            "LastChanged=FROM_UNIXTIME($NOW), " .
            "Subject=\"$Subject\", " .
            "Text=\"$Text\", " .
            "User_ID=1, " . // user moderated guest-user
            "Parent_ID=0, " .
            "AnswerNr=1, " .
            "Depth=1, " .
            "Approved='P', " .
            "crc32=" . crc32($Text) . ", " .
            "PosIndex='**'";

   $result = db_query( 'do_registration_blocked.insert_new_post', $query );

   if( mysql_affected_rows() != 1)
      error('mysql_insert_post', 'do_registration_blocked.insert_new_post');

   $new_id = mysql_insert_id();
   db_query( 'do_registration_blocked.new_thread',
      "UPDATE Posts SET Thread_ID=ID WHERE ID=$new_id LIMIT 1" );

   if( mysql_affected_rows() != 1)
      error('mysql_insert_post', 'do_registration_blocked.new_thread');

   add_forum_log( $new_id, $new_id, FORUMLOGACT_NEW_PEND_POST . ':new_thread' );

   jump_to('index.php?sysmsg=' .
      T_('Request to register new account has been sent to an admin. '
         . 'This can take some time. If everything is OK, '
         . 'the account-details with password to login are sent to your email.') );
}
?>

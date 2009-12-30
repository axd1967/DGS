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

$TranslateGroups[] = "Start";

require_once( "include/register_functions.php" );


{
   connect2mysql();

   if( !is_blocked_ip() )
      error( 'not_logged_in' ); // block spammer, call only if IP-blocked

   $reg = new UserRegistration();
   $reg->check_registration_blocked();

   // Userid and email is fine, now send request to Support-forum (moderated)
   $reg->register_blocked_user(
      /*FIXME forum_id support-forum: need adjustment for DGS-clone */ 2 );

   jump_to('index.php?sysmsg=' .
      T_('Request to register new account has been sent to an admin. '
         . 'This can take some time. If everything is OK, '
         . 'the account-details with password to login are sent to your email.') );
}

?>

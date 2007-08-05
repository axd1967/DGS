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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $admin_level = (int)@$player_row['admin_level']; //local modifications
   if( !$admin_level )
      error('adminlevel_too_low');

   start_page(T_('Admin'), true, $logged_in, $player_row);

   section( 'Admin', T_('Administration'));

   add_link_page_link('admin_password.php', T_('New password'),
                     '', $admin_level & ADMIN_PASSWORD);
   add_link_page_link('admin_translators.php', T_('Manage translators'),
                     '', $admin_level & ADMIN_TRANSLATORS);
   add_link_page_link('admin_faq.php', T_('Edit FAQ'),
                     '', $admin_level & ADMIN_FAQ);
   add_link_page_link('forum/admin.php', T_('Admin forums'),
                     '', $admin_level & ADMIN_FORUM);
   add_link_page_link('admin_admins.php', T_('Edit admin staff'),
                     '', $admin_level & ADMIN_ADMINS);
//   add_link_page_link('admin_requests.php', T_('Handle user requests'), '', false);

   add_link_page_link();

   end_page();
}
?>

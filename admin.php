<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/form_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $adm = $player_row['admin_level'];

  if( $adm < 1 )
    error("adminlevel_too_low");

  start_page(T_('Admin'), true, $logged_in, $player_row);

  echo "<table align=center><tr><td>\n";
  echo "<center><h3><font color=$h3_color>" .
    T_('Administration') . "</font></h3></center><p>\n";

  add_link_page_link('admin_translators.php', T_('Manage translators'),
                     '', $adm & ADMIN_TRANSLATORS);
  add_link_page_link('admin_faq.php', T_('Edit FAQ'), '', $adm & ADMIN_FAQ);
  add_link_page_link('forum/admin.php', T_('Admin forums'), '', $adm & ADMIN_FORUM);
//  add_link_page_link('admin_requests.php', T_('Handle user requests'), '', false);
  add_link_page_link('admin_admins.php', T_('Edit admin staff'), '', $adm & ADMIN_ADMINS);

  echo "<br>&nbsp;\n</td></tr></table>\n";

  end_page();
}
<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival

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

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
$GLOBALS['ThePage'] = new Page('Admin');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_ADM_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin');

   $admin_level = (int)@$player_row['admin_level']; //local modifications
   if ( !$admin_level )
      error('adminlevel_too_low', 'admin');

   start_page(T_('Admin'), true, $logged_in, $player_row);

   section( 'Admin', T_('Administration'));
   centered_container();

   add_link_page_link('admin_password.php', T_('New Password'),
                     '', $admin_level & ADMIN_PASSWORD);
   add_link_page_link('admin_translators.php', T_('Manage Translators'),
                     '', $admin_level & ADMIN_TRANSLATORS);
   add_link_page_link('admin_faq.php', T_('Edit FAQ'),
                     '', $admin_level & ADMIN_FAQ);
   add_link_page_link('admin_faq.php?ot='.TXTOBJTYPE_INTRO, T_('Edit Introduction'),
                     '', $admin_level & ADMIN_FAQ);
   add_link_page_link('admin_faq.php?ot='.TXTOBJTYPE_LINKS, T_('Edit Links'),
                     '', $admin_level & ADMIN_FAQ);
   add_link_page_link('admin_bulletin.php', T_('Admin Bulletin'),
                     '', $admin_level & ADMIN_DEVELOPER);
   if ( ALLOW_FEATURE_VOTE )
      add_link_page_link('features/edit_feature.php', T_('Admin Feature'),
                        '', $admin_level & (ADMIN_FEATURE|ADMIN_DEVELOPER) );
   if ( ALLOW_SURVEY_VOTE )
      add_link_page_link('admin_survey.php', T_('Admin Survey'),
                        '', $admin_level & (ADMIN_SURVEY|ADMIN_DEVELOPER));
   add_link_page_link('forum/admin.php', T_('Admin Forums'),
                     '', $admin_level & ADMIN_DEVELOPER);
   add_link_page_link('admin_contrib.php', T_('Admin contributions'),
                     '', $admin_level & ADMIN_DEVELOPER);
   add_link_page_link('admin_users.php', T_('Edit User Attributes'),
                     '', $admin_level & ADMIN_DEVELOPER);
   add_link_page_link('admin_admins.php', T_('Edit Admin Staff'),
                     '', $admin_level & ADMIN_SUPERADMIN);
   //add_link_page_link('admin_requests.php', T_//('Handle User Requests'), '', false);

   echo "<br><br>\n";

   add_link_page_link('admin_show_faqlog.php', T_('Show FAQ Log'),
                     '', $admin_level & (ADMIN_FAQ|ADMIN_DEVELOPER));
   add_link_page_link('forum/admin_show_forumlog.php', T_('Show Forum Log'),
                     '', $admin_level & (ADMIN_FORUM|ADMIN_DEVELOPER));
   add_link_page_link('admin_show_users.php', T_('Show Administrated Users'),
                     '', $admin_level & ADMINGROUP_EXECUTIVE);
   add_link_page_link('tournaments/show_tournament_log.php', T_('Show Tournament Log'),
                     '', $admin_level & (ADMIN_DEVELOPER|ADMIN_TOURNAMENT));
   add_link_page_link('admin_show_errorlog.php', T_('Show Error Log'),
                     '', $admin_level & ADMIN_DEVELOPER);
   add_link_page_link('admin_show_adminlog.php', T_('Show Admin Log'),
                     '', $admin_level & ADMIN_DEVELOPER);
   add_link_page_link('scripts/index.php', T_('Show Admin Scripts'),
                     '', $admin_level & (ADMIN_SUPERADMIN|ADMIN_DATABASE|ADMIN_DEVELOPER));

   add_link_page_link();

   end_page();
}
?>

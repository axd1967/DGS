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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );
$GLOBALS['ThePage'] = new Page('Docs');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Docs'), true, $logged_in, $player_row );

   section( 'Docs', T_('Documentation'));
   centered_container();

   add_link_page_link('introduction.php', T_('Introduction to Dragon'), T_('Getting started'));
   add_link_page_link('policy.php', T_('Terms of Service'), T_('Rules of Conduct and Privacy Policy'));
   if( ENABLE_DONATIONS )
      add_link_page_link('donation.php', T_('Donation'), T_('Support DGS with a donation'));
   add_link_page_link("news.php", T_('News'), T_('Release notes'));
   add_link_page_link('site_map.php', T_('Site map'), T_('Page structure of site'));
   add_link_page_link("faq.php", T_('Frequently Asked Questions'), T_('with answers'));
   add_link_page_link("links.php", T_('Links'), T_('Link collection'));
   add_link_page_link("people.php", T_('People'), T_("who contributes to Dragon"));

   $arr_stats = array( "statistics.php" => T_('Statistics') );
   if( strpos(HOSTBASE,'dragongoserver.net') !== false )
      $arr_stats[HOSTBASE.'stat/'] = T_('Web-Statistics');
   add_link_page_link( $arr_stats, ', ', T_("Statistics about Dragon"));

   add_link_page_link("http://senseis.xmp.net/?DGSWishlist", T_('DGS Wish list'),
                     T_('Features and requests DGS users dream of'));
/* Note: Goodies are not part of DGS, but a user-to-user feature.
   add_link_page_link("goodies/index.php", T_//('Goodies'),
                     T_//('Some useful GreaseMonkey scripts for DGS'));
*/
   add_link_page_link("install.php", T_('Installation instructions'),
                     T_('if you want your own dragon'));
   add_link_page_link("snapshot.php", T_('Download dragon sources'),
                     T_('daily snapshot of the cvs'));
   add_link_page_link("http://dragongoserver.cvs.sourceforge.net/dragongoserver/DragonGoServer/",
                     T_('Browse Dragon source code'));
   add_link_page_link("http://sourceforge.net/projects/dragongoserver/",
                     T_('Dragon project page at sourceforge'));
   add_link_page_link("licence.php", T_('License'), 'AGPL');

   add_link_page_link();

   end_page();
}

?>

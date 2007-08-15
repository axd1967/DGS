<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'Docs'; //temporary solution to CSS problem
   start_page(T_('Docs'), true, $logged_in, $player_row );

   section( 'Docs', T_('Documentation'));
   centered_container();

   add_link_page_link('introduction.php', T_('Introduction to Dragon'), T_('Getting started'));
   add_link_page_link("news.php", T_('News'), T_('Release notes'));
   add_link_page_link('site_map.php', T_('Site map'), T_('Page structure of site'));
   add_link_page_link("faq.php", T_('Frequently Asked Questions'), T_('with answers'));
   add_link_page_link("links.php", T_('Links'), T_('Link collection'));
   add_link_page_link("people.php", T_('People'), T_("who contributes to Dragon"));
   add_link_page_link("todo.php", T_('To do list'), T_('plans for future improvements'));
   add_link_page_link("http://senseis.xmp.net/?DGSWishlist", T_('DGS Wish list'),
                     T_('Features and requests DGS users dream of'));
/* Note: Goodies are not part of DGS, but a user-to-user feature.
   add_link_page_link("goodies/index.php", T_('Goodies'),
                     T_('Some useful GreaseMonkey scripts for DGS'));
*/
   add_link_page_link("install.php", T_('Installation instructions'),
                     T_('if you want your own dragon'));
   add_link_page_link("snapshot", T_('Download dragon sources'),
                     T_('daily snapshot of the cvs'));
   add_link_page_link("http://dragongoserver.cvs.sourceforge.net/dragongoserver/DragonGoServer/",
                     T_('Browse Dragon source code'));
   add_link_page_link("http://sourceforge.net/projects/dragongoserver/",
                     T_('Dragon project page at sourceforge'));
   add_link_page_link("licence.php", T_('Licence'), 'GPL');

   add_link_page_link();

   end_page();
}

?>

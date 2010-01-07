<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );

{
   $GLOBALS['ThePage'] = new Page('Donation');

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Donation'), true, $logged_in, $player_row );

   section( 'Donation', T_('Donations'));

   echo
      image( "{$base_path}images/dragon_logo.jpg", FRIENDLY_LONG_NAME ),
      "<br><br>\n",
      '<h2><i><font color="darkred">',
         T_('Thank you for your generous donation to support DGS !!'),
      '</font></i></h2>',
      "<br>\n"
      ;

   end_page();
}
?>

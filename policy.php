<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );

{
   $ThePage = new Page('Policy');

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Policy'), true, $logged_in, $player_row );

   section( 'Policy', T_('DragonGoServer Policy'));

   echo
      '<h3>', T_('Rules of Conduct'), '</h3>',
      sprintf(
      T_('DragonGoServer (DGS) is a place to meet Go friends from all over the world ' .
         'to play and enjoy some good games of Go. We have defined some basic ' .
         'rules to make DGS a friendly and thriving place. A more %s informal dragon ' .
            'etiquette %s can be found in the FAQ.'),
         '<a href="faq.php?read=t&cat=15#Entry36">', '</a>'),

      T_('<p>We do not accept: ' .
         '<ul> ' .
         '  <li>Discriminatory, offensive or abusive speech. ' .
         '  <li>Threats or insults directed at other members or admins. ' .
         '  <li>Pornographic texts or links to pornographic web sites. ' .
         '  <li>Any advertisement which have not been explicitly permitted. ' .
         '  <li>Any behavior which is illegal in either Sweden or in your country of residence. ' .
         '</ul>'),

      T_('<p>If you do not comply to any of our rules of conduct we reserve the right to take ' .
         'any action we see fit in the particular situation. This can include removing written texts, ' .
         'restricting the usage of features, blocking your access to this site or termination of your account.'),

      '<h3>', T_('Privacy Policy'), '</h3>',
      T_('<p>We will not give away personal information about you (email address, IP address and messages), ' .
         'which resides on DragonGoServer without explicit permission from you unless it is requested by police force. '),
      T_('<p>You are responsible for your actions and information that you put on the site in your bio, ' .
         'forums or in messages to other members.'),

      '<h3>', T_('Effective Date#policy'), '</h3>',
      make_html_safe(
         T_('The DragonGoServer policy takes effect at 14-Dec-2008. We reserve the right to change this policy ' .
            'from time to time. Any changes will be announced in the <home forum/list.php?forum=1>News forum</home>.'), 'msg'),

      "<p></p>\n";

   end_page();
}
?>

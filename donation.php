<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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
   $ThePage = new Page('Donation');

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_('Donation'), true, $logged_in, $player_row );

   section( 'Donation', T_('Donations'));

   $donate_url = ( ENABLE_DONATIONS )
       ? 'https://www.paypal.com/cgi-bin/webscr'
       : 'http://www.dragongoserver.net/forum/read.php?forum=1&thread=24242'; // thx, goal-reached

   echo
      "<center>\n",
      "<table><tr><td>\n",
      T_("Dear Go-lovers,

         <p>
         for almost seven years now (2009), the Dragon Go Server has been kindly
         hosted by SamurajData completely free of charge. We are extremely
         grateful for their support, the service provided by them was really
         essential for the success of DGS. Since the start, DGS has seen
         a steady increase in activity and since a few years back SamurajData
         has had a little trouble motivating the cost of hosting us for free.
         Due to the high activity on DGS, we used up resourses for the paying
         customers, which made it necessary to move us to a separated machine.
         On christmas 2007, they arranged an older server for us
         to use by our own."),

      "<p>",
      T_("Unfortunately on February 5th (2009), this machine was singing
         on it's last verse, so we needed a new solution. In order to let us
         use their newer hardware, we agreed on paying a small fee
         (200 SEK/month, i.e. around 20 EUR/month).
         We think this still is a very generous offer by them."),

      "<p>",
      T_("Therefore I am asking you to donate a small sum, e.g. 10 or 20 Euro,
         so that we can continue running the server without personal costs.
         Smaller amounts are equally appreciated.
         Our goal is to collect enough money to pay for the service for
         at least three years.

         <p>
         Best regards,

         <p>
         The admins of DGS"),

      "<br>\n",
      "<center>\n",
      // NOTE: form-elements MUST NOT be changed!
      //       They has been created by PayPal-integration-service
     '<form action="'.$donate_url.'" method="post">',
        '<input type="hidden" name="cmd" value="_s-xclick">',
        '<input type="hidden" name="hosted_button_id" value="3262396">',
        T_('The following link will take you to PayPal#donate'), "<br><br>\n",
        '<input type="image" src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="">',
        '<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">',
      '</form>',
      "</center>\n",

      "</td></tr></table>\n",
      "</center>\n",
      "<br>\n"
      ;

   end_page();
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

 /* The code in this file is written by Ragnar Ouchterlony */

require_once( "include/std_functions.php" );
require_once( "include/tournament.php" );

function display_row_of_information( $description, $information )
{
  echo "  <tr><td align=\"left\"><b>&nbsp;$description&nbsp;</b></td>" .
    "<td align=\"left\">&nbsp;$information&nbsp;</td></tr>\n";
}

function make_max_min_participants_string( $min, $max )
{
   if( is_null( $max ) )
   {
      return sprintf( T_("At least %s participants"), $min );
   }

   return sprintf( T_("Between %1\$s and %2\$s participants"), $min, $max );
}

function make_applicationperiod_string( $app_p, $soap )
{
   global $NOW, $date_fmt2;

   if( is_null( $app_p ) or is_null( $soap ) )
   {
      return T_("Applicationperiod has not yet been decided");
   }

   $eoap = strtotime( "+$app_p days", $soap );
   $start_string = date($date_fmt2, $soap);
   $end_string = date($date_fmt2, $eoap);

   $resultstring = "";
   if( $NOW < $soap )
   {
      $resultstring =
         sprintf( T_("The application period will start %1\$s, and end %2\$s"),
                  $start_string, $end_string );
   }
   elseif( $NOW >= $soap and $NOW < $eoap )
   {
      $resultstring =
         sprintf( T_("The application period started %1\$s, and will end %2\$s"),
                  $start_string, $end_string );
   }
   else
   {
      $resultstring =
         sprintf( T_("The application period was started %1\$s, and ended %2\$s"),
                  $start_string, $end_string );
   }

   return $resultstring;
}

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   start_page(T_("Show tournament"), true, $logged_in, $player_row );

   $t = new Tournament( $_GET['tid'] );

   echo "<table align=\"center\" border=\"3\" cellspacing=\"0\" cellpadding=\"3\">\n";
   display_row_of_information( T_('ID'), $t->ID );
   display_row_of_information( T_('Name'), $t->Name );
   display_row_of_information( T_('Description'), $t->Description );
   display_row_of_information( T_('Organizers'), $t->get_organizers_html() );
   display_row_of_information( T_('Max and min participants'),
                               make_max_min_participants_string( $t->MinParticipants,
                                                                 $t->MaxParticipants ) );
   display_row_of_information( T_('Tournament state'), $TourState_Strings[ $t->State ] );
   display_row_of_information( T_('Applicationperiod'),
                               make_applicationperiod_string( $t->Applicationperiod,
                                                              $t->StartOfApplicationPeriod ) );

   echo "</table>\n";

   print_r( $t );

   end_page(false);
}
?>

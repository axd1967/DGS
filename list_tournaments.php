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

 /* The code in this file is written by Ragnar Ouchterlony */

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/tournament.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   $button_nr = $player_row["Button"];

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   $style = 'a.button { color : ' . $buttoncolors[$button_nr] .
      ';  font : bold 100% sans-serif;  text-decoration : none;  width : 90px; }
td.button { background-image : url(images/' . $buttonfiles[$button_nr] . ');' .
      'background-repeat : no-repeat;  background-position : center; }';

   start_page(T_("Tournaments"), true, $logged_in, $player_row, $style );

   $result = mysql_query( "SELECT ID, State, Name FROM Tournament" );

   $table = new Table( 'list_tournaments.php', '', 't_', true );

   $table->add_tablehead( 1, T_('ID'), 'ID', false, true );
   $table->add_tablehead( 2, T_('State'), 'State', false, true );
   $table->add_tablehead( 3, T_('Name'), 'Name', false, true );
   $table->add_tablehead( 4, T_('Organizer'), null, false, true );
   $table->add_tablehead( 5, T_('Participant'), null, false, true );

   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);

      $organizers = array();
      $orgresult = mysql_query( "SELECT tid, pid FROM TournamentOrganizers " .
                                "WHERE tid='$ID'" );
      while( $orgrow = mysql_fetch_array( $orgresult ) )
         array_push( $organizers, $orgrow['pid'] );

      if( in_array( $player_row['ID'], $organizers ) or $row['State'] > 0 )
      {
         $participants = array();
         $partresult = mysql_query( "SELECT tid, pid FROM TournamentParticipants " .
                                    "WHERE tid='$ID'" );
         while( $partrow = mysql_fetch_array( $partresult ) )
            array_push( $participants, $partrow['pid'] );

         $state_colors = array(
            TOUR_STATE_INACTIVE          => "",
            TOUR_STATE_APPLICATIONPERIOD => " bgcolor=\"FFEE00\"",
            TOUR_STATE_RUNNING           => " bgcolor=\"00F464\"",
            TOUR_STATE_FINISHED          => " bgcolor=\"FFA27A\"" );

         $row_strings[1] = "<td class=button width=92 align=center>" .
            "<a class=button href=\"show_tournament.php?tid=$ID\">" .
            "&nbsp;&nbsp;&nbsp;$ID&nbsp;&nbsp;&nbsp;</a></td>";
         $row_strings[2] = "<td" . $state_colors[ $State ] . ">" .
            $TourState_Strings[ $State ] . "</td>";
         $row_strings[3] = "<td nowrap>$Name</td>";
         $orgsrc = '"images/' .
            (in_array( $player_row['ID'], $organizers ) ?
             'yes.gif" alt="' . T_('Yes') . '"' :
             'no.gif" alt="' . T_('NO') . '"');
         $partsrc = '"images/' .
            (in_array( $player_row['ID'], $participants ) ?
             'yes.gif" alt="' . T_('Yes') . '"' :
             'no.gif" alt="' . T_('NO') . '"');
         $row_strings[4] = "<td align=\"center\"><img src=$orgsrc></td>";
         $row_strings[5] = "<td align=\"center\"><img src=$partsrc></td>";

         $table->add_row($row_strings);
      }
   }

   if( empty( $table->Tablerows ) )
   {
      echo T_("There are currently no visible tournaments.");
   }
   else
   {
      $table->echo_table();
   }

   end_page(false);
}

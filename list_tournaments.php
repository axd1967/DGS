<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

 /* The code in this file is written by Ragnar Ouchterlony */

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/tournament.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   $table = new Table( 'tournament', 'list_tournaments.php'); //, '', 'TRN' );
   $table->set_default_sort( 'Name', 0, 'ID', 1);
   //$table->add_or_del_column();

   $result = mysql_query( "SELECT ID, State, Name FROM Tournament" );

   start_page(T_("Tournaments"), true, $logged_in, $player_row,
               $table->button_style($player_row['Button']) );

      $table->add_tablehead( 0,
         T_('ID'), 'ID', false, true, array( 'class' => 'Button') );
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

         $row_strings[0] = $table->button_TD_anchor( "show_tournament.php?tid=$ID", $ID);
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

   end_page();
}
?>

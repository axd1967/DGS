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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");



   $days_left = floor($player_row['VacationDays']);
   $floor_onvacation = floor($player_row['OnVacation']);
   $minimum_days = $vacation_min_days - $floor_onvacation;
   $vacationdiff = round(@$_POST['vacationdiff']);
   $vacationlength = round(@$_POST['vacationlength']);

   $str = '';

   if( $player_row['OnVacation'] > 0 )
   {
      if( $minimum_days > $days_left or
          ( $minimum_days == $days_left and $minimum_days == 0 ) )
      {
         $str .= T_("Sorry, you can't change the vacation length at the moment.");
      }
      else if( isset($_POST['change_vacation']) and
            $vacationdiff >= $minimum_days and $vacationdiff <= $days_left )
      {
         if( $vacationdiff == 0 )
            jump_to("status.php");

         mysql_query("UPDATE Players SET VacationDays=VacationDays-($vacationdiff), " .
                     "OnVacation=OnVacation+($vacationdiff) " .
                     "WHERE ID=" . $player_row['ID'] . " " .
                     "AND VacationDays >= ($vacationdiff) LIMIT 1" )
            or error('mysql_query_failed', 'edit_vacation.change_vacation');

         $msg = urlencode(T_('Vacation length changed!'));

         jump_to("status.php?sysmsg=$msg");
      }
      else
      {
         $vacation_form = new Form( 'vacationform', 'edit_vacation.php', FORM_POST );

         $days = array();
         for( $i=$minimum_days; $i<=$days_left; $i++ )
            if( $i==0 ) $days[$i] = ''; else
            $days[$i] = ( $i >= 0 ? T_('Add') : T_('Remove') ) . ' ' . echo_day(abs($i));

         $vacation_form->add_row( array( 'HEADER', T_('Change vacation length') ) );

         $vacation_form->add_row( array( 'SPACE' ) );
         $vacation_form->add_row( array(
                  'DESCRIPTION', echo_day($floor_onvacation),
                  'SELECTBOX', 'vacationdiff', 1, $days, 0, false,
                  'SUBMITBUTTON', 'change_vacation', T_('Change vacation length') ) );

         $str .= $vacation_form->get_form_string(1);
      }
   }
   else
   {
      if( $days_left < $vacation_min_days )
      {
         $str .= sprintf(T_("Sorry, you need at least %d vacation days to be able to start a vacation period."), $vacation_min_days);
      }
      else if( isset($_POST['start_vacation']) and
         $vacationlength >= $vacation_min_days and $vacationlength <= $days_left )
      {
         // LastTicks will handle -(time spend) at the moment of the start of vacations
         $result = mysql_query("SELECT Games.ID as gid, LastTicks-Clock.Ticks AS ticks " .
                         "FROM Games, Clock " .
                         "WHERE Status" . IS_RUNNING_GAME .
                         'AND Games.ClockUsed >= 0 ' . // not VACATION_CLOCK
                         'AND Clock.ID=Games.ClockUsed ' .
                         "AND ToMove_ID='" . $player_row['ID'] . "'" )
            or error('mysql_query_failed', 'edit_vacation.find_games');

         while( $game_row = mysql_fetch_array( $result ) )
         {
            mysql_query("UPDATE Games SET ClockUsed=" .VACATION_CLOCK
                      . ", LastTicks='" . $game_row['ticks'] . "'" 
                      . " WHERE ID=" . $game_row['gid'] . " LIMIT 1" )
            or error('mysql_query_failed', 'edit_vacation.update_games');
         }

         mysql_query("UPDATE Players SET VacationDays=VacationDays-$vacationlength, " .
                     "OnVacation=$vacationlength " .
                     "WHERE ID=" . $player_row['ID'] . " " .
                     "AND VacationDays >= ($vacationlength) LIMIT 1" )
            or error('mysql_query_failed', 'edit_vacation.update_player');

         $msg = urlencode(T_('Have a nice vacation!'));

         jump_to("status.php?sysmsg=$msg");
      }
      else
      {
         $vacation_form = new Form( 'vacationform', 'edit_vacation.php', FORM_POST );

         $days = array();
         for($i=$vacation_min_days; $i<=$days_left; $i++ )
            $days[$i] = "$i " . T_('days');

         $vacation_form->add_row( array( 'HEADER', T_('Start vacation') ) );

         $vacation_form->add_row( array( 'SPACE' ) );
         $vacation_form->add_row( array(
                  'DESCRIPTION', T_('Choose vacation length'),
                  'SELECTBOX', 'vacationlength', 1, $days, $vacation_min_days, false,
                  'SUBMITBUTTON', 'start_vacation', T_('Start vacation') ) );

         $str .= $vacation_form->get_form_string(1);
      }

   }

   start_page(T_("Vacation"), true, $logged_in, $player_row );

   echo "<CENTER>\n";
   echo $str;
   echo "</CENTER>\n";

   end_page();

}

?>

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id= $player_row['ID'];
   $days_left = floor($player_row['VacationDays']);
   $floor_onvacation = floor($player_row['OnVacation']);
   $minimum_days = $vacation_min_days - $floor_onvacation;

   $str = '';

   if( $player_row['OnVacation'] > 0 ) //already on vacation
   {
      $vacationdiff = round(@$_POST['vacationdiff']);
      if( $minimum_days > $days_left || ( $minimum_days == $days_left && $minimum_days == 0 ) )
      {
         $str .= T_("Sorry, you can't change the vacation length at the moment.");
      }
      else if( isset($_POST['change_vacation']) &&
            $vacationdiff >= $minimum_days && $vacationdiff <= $days_left )
      {
         if( $vacationdiff == 0 )
            jump_to("status.php");

         db_query( 'edit_vacation.change_vacation',
            "UPDATE Players SET VacationDays=VacationDays-($vacationdiff)"
                  . ",OnVacation=OnVacation+($vacationdiff)"
                  . " WHERE ID=$my_id"
                     . " AND VacationDays >= ($vacationdiff) LIMIT 1" );

         $msg = urlencode(T_('Vacation length changed!'));
         jump_to("status.php?sysmsg=$msg");
      }
      else
      {
         $vacation_form = new Form( 'vacationform', 'edit_vacation.php', FORM_POST );

         $days = array();
         for( $i=$minimum_days; $i<=$days_left; $i++ )
         {
            if( $i==0 )
               $days[$i] = '';
            else
               $days[$i] = ( $i >= 0 ? T_('Add') : T_('Remove') ) . ' ' . TimeFormat::echo_day(abs($i));
         }

         $vacation_form->add_row( array( 'HEADER', T_('Change vacation length') ) );

         $vacation_form->add_row( array( 'SPACE' ) );
         $vacation_form->add_row( array(
                  'DESCRIPTION', TimeFormat::echo_day($floor_onvacation),
                  'SELECTBOX', 'vacationdiff', 1, $days, 0, false,
                  'SUBMITBUTTON', 'change_vacation', T_('Change vacation length') ) );
      }
   }
   else //not yet on vacation
   {
      $vacationlength = round(@$_POST['vacationlength']);
      if( $days_left < $vacation_min_days )
      {
         $str .= sprintf(T_("Sorry, you need at least %d vacation days to be able to start a vacation period."), $vacation_min_days);
      }
      elseif( isset($_POST['start_vacation'])
            && $vacationlength >= $vacation_min_days && $vacationlength <= $days_left )
      {
         // LastTicks will handle -(time spent) at the moment of the start of vacations
         // in the reference of the ClockUsed by the game
         db_query( 'edit_vacation.update_games',
            "UPDATE Games INNER JOIN Clock ON Clock.ID=Games.ClockUsed"
               . " SET Games.ClockUsed=" .VACATION_CLOCK
                  . ", Games.LastTicks=Games.LastTicks-Clock.Ticks"
               . " WHERE Games.Status" . IS_RUNNING_GAME
               . " AND Games.ToMove_ID=$my_id"
               . ' AND Games.ClockUsed>=0' // not VACATION_CLOCK
            );

         db_query( 'edit_vacation.update_player',
            "UPDATE Players SET VacationDays=VacationDays-($vacationlength)"
               . ", OnVacation=$vacationlength"
            . " WHERE ID=$my_id AND VacationDays >= ($vacationlength) LIMIT 1" );

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

      }
   }

   start_page(T_('Vacation'), true, $logged_in, $player_row );

   echo $str;
   if( isset($vacation_form) )
      echo $vacation_form->get_form_string(1);

   end_page();
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2003-2003  Erik Ouchterlony

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

$TranslateGroups[] = "Users";

require( "include/std_functions.php" );
require( "include/form_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
   error("not_logged_in");


start_page(T_("Vacation"), true, $logged_in, $player_row );


echo "<CENTER>\n";

$days_left = floor($player_row['VacationDays']);

if( $days_left < 7 )
{
   echo T_("Sorry, you need at least 7 vacation days to be able to start a vacation period.");
}
else if( isset($_POST['start_vacation']) )
{
   $vacationlength = $_POST['vacationlength'];
   if( $vacationlength < 7 or $vacationlength > $days_left )
      error('vacation_bad_length', $vacationlength);

   $result = mysql_query("SELECT Games.ID as gid, LastTicks-Clock.Ticks AS ticks " .
                         "FROM Games, Clock " .
                         "WHERE Clock.ID=ClockUsed " .
                         "AND ToMove_ID='" . $player_row['ID'] . "' " .
                         "AND Status!='INVITED' AND Status!='FINISHED'")
      or die(mysql_error());

   while( $row = mysql_fetch_array( $result ) );
   {
      mysql_query("UPDATE Games SET LastTicks='" . $row['ticks'] . "', ClockUsed=-1 " .
                  "WHERE ID='" . $row['gid'] . "' LIMIT 1");
   }

   mysql_query("UPDATE Players SET VacationDays=VacationDays-$vacationlength, " .
               "OnVacation=$vacationlength") or die(mysql_error());
}
else
{
   $vacation_form = new Form( 'vacationform', 'change_password.php', FORM_POST );

   $days = array();
   for($i=7; $i<=$days_left; $i++ )
      $days[$i] = "$i " . T_('days');

   $vacation_form->add_row( array( 'HEADER', T_('Start a Vacation') ) );

//    $vacation_form->add_row( array( 'DESCRIPTION', '<font color=green>' .
//                                    T_('Choose vacation length') . '</font>' ) );
   $vacation_form->add_row( array( 'SPACE' ) );
   $vacation_form->add_row( array( 'DESCRIPTION', T_('Choose vacation length'),
                                   'SELECTBOX', 'vacationlength', 1, $days, 7, false,
                                   'SUBMITBUTTON', 'start_vacation', T_('Start vacation') ) );

   $vacation_form->echo_string();
}


echo "</CENTER>\n";

end_page();

?>


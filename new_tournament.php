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

{

  require( "include/std_functions.php" );
  require( "include/timezones.php" );
  require( "include/rating.php" );
  require( "include/form_functions.php" );

  connect2mysql(); 

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  start_page("Create tournament", true, $logged_in, $player_row );

  $type_array=array('KNOCKOUT'   => 'Knockout',
                    'LEAGUE'     => 'League',
                    'MACMAHON'   => 'MacMahon',
                    'ROUNDROBIN' => 'RoundRobin',
                    'SWISS'      => 'Swiss');

  echo "<CENTER>\n";

  echo form_start( 'tournamentform', 'new_tournament_phase2.php', 'POST');

  echo form_insert_row( 'HEADER', 'Tournament information' );
  echo form_insert_row( 'DESCRIPTION', 'Tournament name',
                        'TEXTINPUT', 'name', 50, 80, "" );

  echo form_insert_row( 'DESCRIPTION', 'Tournament description',
                        'TEXTAREA', 'description', 50, 8, "" );

  echo form_insert_row( 'SPACE' );
  echo form_insert_row( 'DESCRIPTION', 'Creating organizer',
                        'TEXT', $player_row['Handle'] );
  echo form_insert_row( 'DESCRIPTION', 'Comma separated list of other organizers',
                        'TEXTINPUT', 'organizers', 50, 150, '' );

  echo form_insert_row( 'SPACE' );
  echo form_insert_row( 'DESCRIPTION', 'Number of participants allowed',
                        'TEXT', 'Minimum:',
                        'TEXTINPUT', 'min', 10, 10, "",
                        'TEXT', 'Maximum:',
                        'TEXTINPUT', 'max', 10, 10, "" );

  echo form_insert_row( 'DESCRIPTION', 'Type of tournament',
                        'RADIOBUTTONS', 'type', $type_array, 'MACMAHON' );

  $current_month = getdate( $NOW );
  $next_month = getdate( strtotime( "+1 month", $NOW ) );

  $hour_array = array();
  for( $bs = 0; $bs <= 23; $bs++ )
    $hour_array[$bs]="$bs:00";

  $day_array = array();
  for( $bs = 1; $bs <= 31; $bs++ )
    $day_array[$bs]=$bs;

  $month_array = array( 1 => 'January',  'February', 'March',    'April',
                        'May',           'June',     'July',     'August',
                        'September',     'October',  'November', 'December' );
  $year_array = array();
  for( $bs = $next_month['year']; $bs <= $next_month['year']+10; $bs++ )
    $year_array[$bs]=$bs;

  echo form_insert_row( 'DESCRIPTION', 'Starttime of tournament',
                        'SELECTBOX', 'start_day', 1,
                        $day_array, $next_month['mday'], false,
                        'SELECTBOX', 'start_month', 1,
                        $month_array, $next_month['mon'], false,
                        'SELECTBOX', 'start_year', 1,
                        $year_array, $next_month['year'], false,
                        'TEXT', 'at',
                        'SELECTBOX', 'start_hour', 1,
                        $hour_array, $next_month['hours'], false,
                        'TEXT', $player_row['Timezone']);

  echo form_insert_row( 'DESCRIPTION', 'First allowed starttime',
                        'SELECTBOX', 'firststart_day', 1,
                        $day_array, $current_month['mday'], false,
                        'SELECTBOX', 'firststart_month', 1,
                        $month_array, $current_month['mon'], false,
                        'SELECTBOX', 'firststart_year', 1,
                        $year_array, $current_month['year'], false,
                        'TEXT', 'at',
                        'SELECTBOX', 'firststart_hour', 1,
                        $hour_array, $current_month['hours'], false,
                        'TEXT', $player_row['Timezone']);

  echo form_insert_row( 'DESCRIPTION', 'Deadline for tournament entry',
                        'CHECKBOX', 'use_deadline', 1, '', false,
                        'SELECTBOX', 'deadline_day', 1,
                        $day_array, $next_month['mday'], false,
                        'SELECTBOX', 'deadline_month', 1,
                        $month_array, $next_month['mon'], false,
                        'SELECTBOX', 'deadline_year', 1,
                        $year_array, $next_month['year'], false,
                        'TEXT', 'at',
                        'SELECTBOX', 'deadline_hour', 1,
                        $hour_array, $next_month['hours'], false,
                        'TEXT', $player_row['Timezone']);

  echo form_insert_row( 'SUBMITBUTTON', 'action', 'Next >' );

  echo form_end();

  echo "</CENTER>\n";

  end_page(false);

}

?>

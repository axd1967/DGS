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
  require( "include/tournament_functions.php" );
  require( "include/form_functions.php" );

  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( empty($name) )
    error("tournament_no_name_given");
  if( empty($description) )
    error("tournament_no_description_given");

  if( empty($min) )
    $min = 2;
  elseif( !is_numeric($min) )
    error("value_not_numeric");

  if( !empty($max) && !is_numeric($max) )
    error("value_not_numeric");
  elseif( !empty($max) && is_numeric($max) && ($min > $max) )
    error("min_larger_than_max");

  if( empty($type) )
    $type = 'MACMAHON';

  parse_organizer_list( $organizers );

  $starttime=mktime($start_hour,0,0,$start_month,$start_day,$start_year);
  $firtsstarttime=mktime($firtsstart_hour,0,0,$firtsstart_month,
                         $firtsstart_day,$firtsstart_year);
  if( $firtsstarttime < $NOW )
    $firtsstarttime = NULL;

  if( $use_deadline == 1 )
    $deadlinetime=mktime($deadline_hour,0,0,$deadline_month,$deadline_day,$deadline_year);
  else
    $deadlinetime = NULL;

  $type_array=array('KNOCKOUT'   => 'Knockout',
                    'LEAGUE'     => 'League',
                    'MACMAHON'   => 'MacMahon',
                    'ROUNDROBIN' => 'RoundRobin',
                    'SWISS'      => 'Swiss');

  start_page("Creating a $type_array[$type] tournament",
             true, $logged_in, $player_row );

  echo "<CENTER>\n";

  echo form_start( 'tournament_p2_form', 'create_tournament.php', 'POST' );

  echo form_insert_row( 'HIDDEN', 'name', $name );
  echo form_insert_row( 'HIDDEN', 'description', $description );
  echo form_insert_row( 'HIDDEN', 'min', $min );
  echo form_insert_row( 'HIDDEN', 'max', $max );
  echo form_insert_row( 'HIDDEN', 'type', $type );
  echo form_insert_row( 'HIDDEN', 'organizers', $player_row['Handle'].",".$organizers );
  echo form_insert_row( 'HIDDEN', 'starttime', $starttime );
  echo form_insert_row( 'HIDDEN', 'firststarttime', $firststarttime );
  echo form_insert_row( 'HIDDEN', 'deadlihetime', $deadlinetime );

  echo form_insert_row( 'HEADER', 'General options' );

  switch( $type )
    {
    case( "KNOCKOUT" ):
      {
        echo form_insert_row( 'DESCRIPTION', 'How many seeded participants',
                              'TEXTINPUT', 'seeded', 10, 10, 0 );
        echo form_insert_row( 'DESCRIPTION', 'Use handicap',
                              'CHECKBOX', 'handicap', 1, '', true );
      }
      break;

      /*     case( "LEAGUE" ): */
      /*       { */
      /*         echo "League not done yet!"; */
      /*       } */
      /*       break; */

      /*     case( "MACMAHON" ): */
      /*       { */
      /*         echo "MacMahon not done yet!"; */
      /*       } */
      /*       break; */

      /*     case( "ROUNDROBIN" ): */
      /*       { */
      /*         echo "RoundRobin not done yet!"; */
      /*       } */
      /*       break; */

      /*     case( "SWISS" ): */
      /*       { */
      /*         echo "Swiss not done yet!"; */
      /*       } */
      /*       break; */

    default:
      {
        error( "unknown_tournament_type" );
      }
      break;
    }

  /* General game information to be used
   * Needed by all types:
   *
   * Size
   * Komi
   * Maintime
   * Byotype
   * Byotime
   * Byoperiods
   * Rated
   * WeekendClock
   */

  echo form_insert_row( 'SPACE' );
  echo form_insert_row( 'HEADER', 'Game options' );

  $value_array=array();
  for( $bs = 5; $bs <= 25; $bs++ )
    $value_array[$bs]=$bs;

  echo form_insert_row( 'DESCRIPTION', 'Board size',
                        'SELECTBOX', 'size', 1, $value_array, 19, false );

  echo form_insert_row( 'DESCRIPTION', 'Komi',
                        'TEXTINPUT', 'komi', 5, 5, '6.5' );

  $value_array=array( 'hours' => 'hours', 'days' => 'days', 'months' => 'months' );
  echo form_insert_row( 'DESCRIPTION', 'Main time',
                        'TEXTINPUT', 'timevalue', 5, 5, 3,
                        'SELECTBOX', 'timeunit', 1, $value_array, 'months', false );

  echo form_insert_row( 'DESCRIPTION', 'Japanese byo-yomi',
                        'RADIOBUTTONS', 'byoyomitype', array( 'JAP' => '' ), 'JAP',
                        'TEXTINPUT', 'byotimevalue_jap', 5, 5, 1,
                        'SELECTBOX', 'timeunit_jap', 1, $value_array, 'days', false,
                        'TEXT', 'with&nbsp;',
                        'TEXTINPUT', 'byoperiods_jap', 5, 5, 10,
                        'TEXT', 'extra periods.' );

  echo form_insert_row( 'DESCRIPTION', 'Canadian byo-yomi',
                        'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), 'CAN',
                        'TEXTINPUT', 'byotimevalue_can', 5, 5, 15,
                        'SELECTBOX', 'timeunit_can', 1, $value_array, 'days', false,
                        'TEXT', 'for&nbsp;',
                        'TEXTINPUT', 'byoperiods_can', 5, 5, 15,
                        'TEXT', 'stones.' );

  echo form_insert_row( 'DESCRIPTION', 'Fischer time',
                        'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), 'FIS',
                        'TEXTINPUT', 'byotimevalue_fis', 5, 5, 1,
                        'SELECTBOX', 'timeunit_fis', 1, $value_array, 'days', false,
                        'TEXT', 'extra&nbsp;per move.' );

  echo form_insert_row( 'DESCRIPTION', 'Clock runs on weekends',
                        'CHECKBOX', 'weekendclock', 'Y', "", true );
  echo form_insert_row( 'DESCRIPTION', 'Rated',
                        'CHECKBOX', 'rated', 'Y', "", true );

  echo form_insert_row( 'SUBMITBUTTON', 'action', 'Create' );

  echo form_end();

  echo "</CENTER>\n";

  end_page(false);
}
?>

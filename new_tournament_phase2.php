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

  $tour_form = new Form( 'tournament_p2_form', 'create_tournament.php', FORM_POST );

  $tour_form->add_row( array( 'HIDDEN', 'name', $name ) );
  $tour_form->add_row( array( 'HIDDEN', 'description', $description ) );
  $tour_form->add_row( array( 'HIDDEN', 'min', $min ) );
  $tour_form->add_row( array( 'HIDDEN', 'max', $max ) );
  $tour_form->add_row( array( 'HIDDEN', 'type', $type ) );
  $tour_form->add_row( array( 'HIDDEN', 'organizers', $player_row['Handle'].",".$organizers ) );
  $tour_form->add_row( array( 'HIDDEN', 'starttime', $starttime ) );
  $tour_form->add_row( array( 'HIDDEN', 'firststarttime', $firststarttime ) );
  $tour_form->add_row( array( 'HIDDEN', 'deadlihetime', $deadlinetime ) );

  $tour_form->add_row( array( 'HEADER', 'General options' ) );

  switch( $type )
    {
    case( "KNOCKOUT" ):
      {
        $tour_form->add_row( array( 'DESCRIPTION', 'How many seeded participants',
                                    'TEXTINPUT', 'seeded', 10, 10, 0 ) );
        $tour_form->add_row( array( 'DESCRIPTION', 'Use handicap',
                                    'CHECKBOX', 'handicap', 1, '', true ) );
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

  $tour_form->add_row( array( 'SPACE' ) );
  $tour_form->add_row( array( 'HEADER', 'Game options' ) );

  $value_array=array();
  for( $bs = 5; $bs <= 25; $bs++ )
    $value_array[$bs]=$bs;

  $tour_form->add_row( array( 'DESCRIPTION', 'Board size',
                              'SELECTBOX', 'size', 1, $value_array, 19, false ) );

  $tour_form->add_row( array( 'DESCRIPTION', 'Komi',
                              'TEXTINPUT', 'komi', 5, 5, '6.5' ) );

  $value_array=array( 'hours' => 'hours', 'days' => 'days', 'months' => 'months' );
  $tour_form->add_row( array( 'DESCRIPTION', 'Main time',
                              'TEXTINPUT', 'timevalue', 5, 5, 3,
                              'SELECTBOX', 'timeunit', 1, $value_array, 'months', false ) );

  $tour_form->add_row( array( 'DESCRIPTION', 'Japanese byo-yomi',
                              'RADIOBUTTONS', 'byoyomitype', array( 'JAP' => '' ), 'JAP',
                              'TEXTINPUT', 'byotimevalue_jap', 5, 5, 1,
                              'SELECTBOX', 'timeunit_jap', 1, $value_array, 'days', false,
                              'TEXT', 'with&nbsp;',
                              'TEXTINPUT', 'byoperiods_jap', 5, 5, 10,
                              'TEXT', 'extra periods.' ) );

  $tour_form->add_row( array( 'DESCRIPTION', 'Canadian byo-yomi',
                              'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), 'CAN',
                              'TEXTINPUT', 'byotimevalue_can', 5, 5, 15,
                              'SELECTBOX', 'timeunit_can', 1, $value_array, 'days', false,
                              'TEXT', 'for&nbsp;',
                              'TEXTINPUT', 'byoperiods_can', 5, 5, 15,
                              'TEXT', 'stones.' ) );

  $tour_form->add_row( array( 'DESCRIPTION', 'Fischer time',
                              'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), 'FIS',
                              'TEXTINPUT', 'byotimevalue_fis', 5, 5, 1,
                              'SELECTBOX', 'timeunit_fis', 1, $value_array, 'days', false,
                              'TEXT', 'extra&nbsp;per move.' ) );

  $tour_form->add_row( array( 'DESCRIPTION', 'Clock runs on weekends',
                              'CHECKBOX', 'weekendclock', 'Y', "", true ) );
  $tour_form->add_row( array( 'DESCRIPTION', 'Rated',
                              'CHECKBOX', 'rated', 'Y', "", true ) );

  $tour_form->add_row( array( 'SUBMITBUTTON', 'action', 'Create' ) );

  $tour_form->echo_string();

  echo "</CENTER>\n";

  end_page(false);
}
?>

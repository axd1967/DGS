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
  require( "include/tournament_functions.php" );

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

  if( empty($max) )
    $max = 'NULL';

  if( empty($type) )
    $type = 'MACMAHON';

  if( empty($starttime) )
    $starttime = strtotime( "+1 month", $NOW );

  $starttimestr = "FROM_UNIXTIME($starttime)";

  if( empty($firststarttime) )
    $firststarttimestr = 'NULL';
  else
    $firststarttimestr = "FROM_UNIXTIME($firststarttime)";

  if( empty($deadlinetime) )
    $deadlinetimestr = 'NULL';
  else
    $deadlinetimestr = "FROM_UNIXTIME($deadlinetime)";


  $organizer_array = parse_organizer_list( $organizers );

  switch( $type )
    {
    case( 'KNOCKOUT' ):
      {
        if( empty($seeded) )
          $seeded=0;

        if( $handicap == 1 )
          $handicap='Y';
        else
          $handicap='N';
      }
      break;

    default:
      {
        error( "unknown_tournament_type" );
      }
      break;
    }

  if( $komi > 200 or $komi < -200 )
    error("komi_range");

  $hours = $timevalue;
  if( $timeunit != 'hours' )
    $hours *= 15;
  if( $timeunit == 'months' )
    $hours *= 30;

  if( $byoyomitype == 'JAP' )
    {
      $byohours = $byotimevalue_jap;
      if( $timeunit_jap != 'hours' )
        $byohours *= 15;
      if( $timeunit_jap == 'months' )
        $byohours *= 30;

      $byoperiods = $byoperiods_jap;
    }
  else if( $byoyomitype == 'CAN' )
    {
      $byohours = $byotimevalue_can;
      if( $timeunit_can != 'hours' )
        $byohours *= 15;
      if( $timeunit_can == 'months' )
        $byohours *= 30;

      $byoperiods = $byostones_can;
    }
  else if( $byoyomitype == 'FIS' )
    {
      $byohours = $byotimevalue_fis;
      if( $timeunit_fis != 'hours' )
        $byohours *= 15;
      if( $timeunit_fis == 'months' )
        $byohours *= 30;

      $byoperiods = 0;
    }

  if( $rated != 'Y' )
    $rated = 'N';

  if( $weekendclock != 'Y' )
    $weekendclock = 'N';

  $type_array=array('KNOCKOUT'   => 1,
                    'LEAGUE'     => 2,
                    'ROUNDROBIN' => 3,
                    'SWISS'      => 4,
                    'MACMAHON'   => 5 );

  $result = mysql_query( "INSERT INTO Tournament SET " .
                         "Name='$name', " .
                         "Description='$description', " .
                         "Type='$type_array[$type]', " .
                         "State='WAITING', " .
                         "CreationTime=FROM_UNIXTIME($NOW), " .
                         "StartTime=$starttime_str, " .
                         "FirstStartTime=$firststarttimestr, " .
                         "Deadline=$deadlinetimestr, " .
                         "MinParticipants='$min', " .
                         "MaxParticipants='$max', " .
                         "Size='$size', " .
                         "Komi=ROUND(2*$komi)/2, " .
                         "Maintime=$hours, " .
                         "Byotype='$byoyomitype', " .
                         "Byotime=$byohours, " .
                         "Byoperiods=$byoperiods, " .
                         "Rated='$rated', " .
                         "WeekendClock='$weekendclock' " );

  if( mysql_affected_rows() != 1)
    error("mysql_insert_tournament");

  $tid = mysql_insert_id();

  foreach( $organizer_array as $pid )
    {
      $result = mysql_query( "INSERT INTO TournamentOrganizers SET " .
                             "tid=$tid, " .
                             "pid=$pid " );

      if( mysql_affected_rows() != 1)
        {
          mysql_query( "DELETE FROM Tournament WHERE ID=$tid" );
          error("mysql_insert_tournament");
        }
    }

  switch( $type )
    {
    case( 'KNOCKOUT' ):
      {
        $result = mysql_query( "INSERT INTO Knockout SET " .
                               "Tournament_ID=$tid, " .
                               "Seedings=$seeded, " .
                               "UseHandicap='$handicap'" );

        if( mysql_affected_rows() != 1)
          {
            mysql_query( "DELETE FROM Tournament WHERE ID=$tid" );
            mysql_query( "DELETE FROM TournamentOrganizers WHERE tid=$tid" ); 
            error("mysql_insert_tournament");
          }
      }
      break;

    default:
      {
        mysql_query( "DELETE FROM Tournament WHERE ID=$tid" );
        mysql_query( "DELETE FROM TournamentOrganizers WHERE tid=$tid" ); 
        error( "unknown_tournament_type" );
      }
      break;
   }

  jump_to('status.php');
}

?>

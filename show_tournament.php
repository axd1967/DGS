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

function display_row_of_information( $description, $information )
{
  echo "  <tr><td align=\"left\"><b>&nbsp;$description&nbsp;</b></td>" .
    "<td align=\"left\">&nbsp;$information&nbsp;</td></tr>\n";
}

{
  require( "include/std_functions.php" );
  require( "include/timezones.php" );
  require( "include/tournament_functions.php" );
  require( "include/form_functions.php" );

  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !is_numeric($tid) )
    error("strange_tournament_id");

  start_page("Show Tournament", true, $logged_in, $player_row );

  $query =
    "SELECT " .
    "Tournament.*, " .
    "IFNULL(UNIX_TIMESTAMP(CreationTime),0) AS creationtime, " .
    "IFNULL(UNIX_TIMESTAMP(EndTime),0) AS endtime, " .
    "IFNULL(UNIX_TIMESTAMP(StartTime),0) AS starttime, " .
    "IFNULL(UNIX_TIMESTAMP(FirstStartTime),0) AS firststarttime, " .
    "IFNULL(UNIX_TIMESTAMP(Deadline),0) AS deadline, " .
    "COUNT(pid) AS NrParticipants " .
    "FROM Tournament " .
    "LEFT JOIN TournamentParticipants ON TournamentParticipants.tid=Tournament.ID " .
    "WHERE Tournament.ID=$tid " .
    "GROUP BY ID";

  $result = mysql_query( $query );

  if( mysql_num_rows( $result ) != 1 )
    error( "no_such_tournament" );

  $tour_row = mysql_fetch_array( $result );
  $tournament_organizers = create_organizer_list( $tid );
  $is_participating = is_player_participating_in_tournament( $tid, $player_row['ID'] );

  if( $tour_row['State'] == 'WAITING' )
    {
      if( $remove == 1 && $is_participating )
        {
          $tmp_result = mysql_query( "DELETE FROM TournamentParticipants " .
                                     "WHERE tid=$tid AND pid=" . $player_row['ID'] );

          if( mysql_affected_rows() != 1)
            error( "remove_form_tournament_failed" );

          $tour_row['NrParticipants']--;
          $is_participating = false;
        }
      elseif( $add == 1 && !$is_participating )
        {
          $tmp_result = mysql_query( "INSERT INTO TournamentParticipants " .
                                     "SET tid=$tid, pid=" . $player_row['ID'] );

          if( mysql_affected_rows() != 1)
            error( "add_form_tournament_failed" );

          $tour_row['NrParticipants']++;
          $is_participating = true;
        }
    }

  $type_array = array( 1 => 'Knockout', 'League', 'RoundRobin', 'Swiss', 'MacMahon' );
  $statearray = array( 'INACTIVE' => _("Currently inactive"),
                       'WAITING' => _("Waiting for participants"),
                       'RUNNING' => _("Running"),
                       'FINISHED' => _("Finished") );

  $creationtime = ($tour_row['creationtime'] > 0 ? date($date_fmt2,
                                                        $tour_row['creationtime']) : NULL );
  $endtime = ($tour_row['endtime'] > 0 ? date($date_fmt2,
                                              $tour_row['endtime']) : "Not ended yet!" );
  $starttime = ($tour_row['starttime'] > 0 ? date($date_fmt2,
                                                  $tour_row['starttime']) : NULL );
  $firststarttime = ($tour_row['firststarttime'] > 0 ? date($date_fmt2,
                                                            $tour_row['firststarttime']) : NULL );
  $deadline = ($tour_row['deadline'] > 0 ? date($date_fmt2,
                                                $tour_row['deadline']) : 'No deadline' );

  echo "<table align=\"center\" border=\"3\" cellspacing=\"0\" cellpadding=\"3\">\n";
  display_row_of_information( 'ID', $tour_row['ID'] );
  display_row_of_information( 'Name', $tour_row['Name'] );
  display_row_of_information( 'Type', $type_array[ $tour_row['Type'] ] );
  display_row_of_information( 'State', $statearray[ $tour_row['State'] ] );
  display_row_of_information( 'Organizers', $tournament_organizers );
  display_row_of_information( 'Creationtime', $creationtime );
  display_row_of_information( 'Endtime', $endtime );
  display_row_of_information( 'Starttime', $starttime );
  display_row_of_information( 'First allowed starttime', $firststarttime );
  display_row_of_information( 'Deadline for tournament entry', $deadline );
  display_row_of_information( 'Minimum participants', $tour_row['MinParticipants'] );
  display_row_of_information( 'Maximum participants', $tour_row['MaxParticipants'] );
  display_row_of_information( 'Number of participants', $tour_row['NrParticipants'] );
  display_row_of_information( 'Participating', ($is_participating ? 'Yes' : 'No') );

  switch( $type_array[ $tour_row['Type'] ] )
  {
  case 'Knockout':
    {
      $result = mysql_query( "SELECT * FROM Knockout WHERE Knockout.Tournament_ID=$tid" );
      if( mysql_num_rows( $result ) != 1 )
        error( "no_such_tournament" );
      $knockout_row = mysql_fetch_array( $result );

      display_row_of_information( 'Seedings', $knockout_row['Seedings'] );
      display_row_of_information( 'Handicap', (($knockout_row['Handicap'] == 'Y') ? 'Yes' : 'No' ) );
    }
    break;

  default:
    {
      error( "unknown_tournament_type" );
    }
    break;
  }

  echo "</table>\n";
  echo "<p>\n";

  if( $tour_row['State'] == 'WAITING' )
    {
      $result = mysql_query( "SELECT Players.ID, Players.Name, Players.Handle " .
                             "FROM Players, TournamentParticipants " .
                             "WHERE TournamentParticipants.tid=$tid " .
                             "AND TournamentParticipants.pid=Players.ID " .
                             "ORDER BY Players.Name" );

      $nr_columns = 6;

      if( mysql_num_rows( $result ) == 0 )
        {
          echo "<center><b><font size=+1>" .
            "No players is participating in this tournament yet.</font></b></center>";
        }
      else
        {
          echo "<center><b><font size=+1>Participants:</font></b></center>";
          echo "<table align=\"center\" border=\"3\" cellspacing=\"0\" cellpadding=\"3\">\n";
          $placement = 0;
          while( $prow = mysql_fetch_array( $result ) )
            {
              if( $placement == 0 )
                echo "  <tr>\n";
              echo "    <td><a href=\"userinfo.php?uid=" . $prow['ID'] .
                "\">" . $prow['Name'] . "</a></td>";
              if( $placement == $nr_columns-1 )
                echo "  </tr>\n";
              $placement = ($placement + 1) % $nr_columns;
            }
          if( $placement != $nr_columns-1 )
            echo "  </tr>\n";
          echo "</table>\n<p>\n";
        }

      echo "<center>\n";
      echo form_start( 'participate_add_del_form', "show_tournament.php?tid=$tid", 'POST' );
      echo form_insert_row( 'HIDDEN', ($is_participating ? 'remove' : 'add'), 1,
                            'SUBMITBUTTON', 'adddel',
                            ($is_participating ?
                             _("Remove yourself from tournament") :
                             _("Add youself to tournament")) );
      echo form_end();
      echo "</center>\n";
      echo "<p>\n";
    }

  end_page(false);
}

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
  $table_columns = array( 'ID', 'Name', 'State', 'Type', 'Organizers',
                          'CreationTime', 'EndTime',
                          'StartTime', 'FirstStartTime', 'Deadline',
                          'Min', 'Max', 'Current', 'In' );

  $type_array=array( 1 => 'Knockout', 'League', 'RoundRobin', 'Swiss', 'MacMahon' );

  require( "include/std_functions.php" );
  require( "include/tournament_functions.php" );
  require( "include/table_columns.php" );
  require( "include/timezones.php" );

  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !is_numeric($state) || $state < 1 || $state > 7 )
    $state = 7;

  $column_set = $player_row["TournamentsColumns"];
  $page = "tournaments.php?state=$state&";

  add_or_del($add,$del, "TournamentsColumns");

  if(!$sort1 and !$sort2)
    {
      $sort1='State';
      $sort2='creationtime';
    }

  $order = $sort1 . ( $desc1 ? ' DESC' : '' );
  if( $sort2 )
    $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

  start_page("Tournaments", true, $logged_in, $player_row );

  $state_array = array( 1 => 'WAITING', 'RUNNING', 'FINISHED' );

  if( $state == 1 )
    echo "<center><b><font size=+1>" .
      _("Tournaments waiting for participants") . ":</font></b></center>";
  elseif( $state == 2 )
    echo "<center><b><font size=+1>" . _("Running tournaments") . ":</font></b></center>";
  elseif( $state == 3 )
    echo "<center><b><font size=+1>" . _("Finished tournaments") . ":</font></b></center>";

  $counter = 0;
  $where_clause_strings = array( 1 => 'WHERE',
                                 2 => 'OR',
                                 3 => 'OR' );
  $where_clause = '';
  if( (1 << 0) & $state )
    {
      $counter++;
      $where_clause .= $where_clause_strings[$counter] . " State='WAITING' ";
    }
  if( (1 << 1) & $state )
    {
      $counter++;
      $where_clause .= $where_clause_strings[$counter] . " State='RUNNING' ";
    }
  if( (1 << 2) & $state )
    {
      $counter++;
      $where_clause .= $where_clause_strings[$counter] . " State='FINISHED' ";
    }

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
    $where_clause .
    "GROUP BY ID " .
    "ORDER BY $order";

  $result = mysql_query( $query );

  echo start_end_column_table(true) .
    tablehead(1, 'ID', 'ID', false, true) .
    tablehead(2, 'Name', 'Name', false, true) .
    tablehead(3, 'State', 'State', true, true) .
    tablehead(4, 'Type', 'Type') .
    tablehead(5, 'Organizers') .
    tablehead(6, 'CreationTime', 'creationtime', true) .
    tablehead(7, 'EndTime', 'endtime', true) .
    tablehead(8, 'StartTime', 'starttime', true) .
    tablehead(9, 'FirstStartTime', 'firststarttime', true) .
    tablehead(10, 'Deadline', 'deadline', true) .
    tablehead(11, 'Min', 'MinParticipants', true) .
    tablehead(12, 'Max', 'MaxParticipants', true) .
    tablehead(13, 'Current', 'NrParticipants', true) .
    tablehead(14, 'In') .
    "</tr>\n";

  $row_color=2;
  while( $row = mysql_fetch_array( $result ) )
    {
      $ID = $row['ID'];
      $organizers = create_organizer_list( $ID, true );

      $is_participating = is_player_participating_in_tournament( $ID, $player_row['ID'] );

      $creationtime = ($row['creationtime'] > 0 ? date($date_fmt2,
                                                       $row['creationtime']) : NULL );
      $endtime = ($row['endtime'] > 0 ? date($date_fmt2,
                                             $row['endtime']) : "Not ended yet!" );
      $starttime = ($row['starttime'] > 0 ? date($date_fmt2, $row['starttime']) : NULL );
      $firststarttime = ($row['firststarttime'] > 0 ? date($date_fmt2,
                                                           $row['firststarttime']) : NULL );
      $deadline = ($row['deadline'] > 0 ? date($date_fmt2,
                                               $row['deadline']) : 'No deadline' );

      $row_color=3-$row_color;
      echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n";

      if( (1 << 0) & $column_set )
        echo "<td><a href=\"show_tournament.php?tid=$ID\">$ID</a></td>\n";
      if( (1 << 1) & $column_set )
        echo "<td><a href=\"show_tournament.php?tid=$ID\">" . $row['Name'] . "</a></td>\n";
      if( (1 << 2) & $column_set )
        {
          $bgcolor = 'FFFFFF';
          switch( $row['State'] )
            {
            case 'WAITING':
              {
                echo "<td bgcolor=\"FFA27A\">Waiting</td>\n";
              }
              break;
            case 'RUNNING':
              {
                echo "<td bgcolor=\"00F464\">Running</td>\n";
              }
              break;
            case 'FINISHED':
              {
                echo "<td bgcolor=\"64A2FF\">Finished</td>\n";
              }
              break;
            }
        }
      if( (1 << 3) & $column_set )
        echo '<td>' . $type_array[$row['Type']] . "</td>\n";
      if( (1 << 4) & $column_set )
        echo '<td>' . $organizers . "</td>\n";
      if( (1 << 5) & $column_set )
        echo '<td>' . $creationtime . "</td>\n";
      if( (1 << 6) & $column_set )
        echo '<td>' . $endtime . "</td>\n";
      if( (1 << 7) & $column_set )
        echo '<td>' . $starttime . "</td>\n";
      if( (1 << 8) & $column_set )
        echo '<td>' . $firststarttime . "</td>\n";
      if( (1 << 9) & $column_set )
        echo '<td>' . $deadline . "</td>\n";
      if( (1 << 10) & $column_set )
        echo '<td>' . $row['MinParticipants'] . "</td>\n";
      if( (1 << 11) & $column_set )
        echo '<td>' . $row['MaxParticipants'] . "</td>\n";
      if( (1 << 12) & $column_set )
        echo '<td>' . $row['NrParticipants'] . "</td>\n";
      if( (1 << 13) & $column_set )
        {
          echo "<td>";
          if( $is_participating == 1 )
            echo "<img src=\"images/yes.gif\"";
          echo "</td>\n";
        }
    }

  echo start_end_column_table( false );

  echo "<p>\n" .
    "<table width=\"100%\" border=0 cellspacing=0 cellpadding=4>\n" .
    "  <tr align=\"center\">\n";


  $show_wait = ((1 << 0) & $state ? 1 : 0);
  $show_run  = ((1 << 1) & $state ? 1 : 0);
  $show_fin  = ((1 << 2) & $state ? 1 : 0);
  $show_hide = array( 0 => 'Show', 1 => 'Hide' );

  $new_wait_state = (1 << 0) ^ $state;
  $new_run_state  = (1 << 1) ^ $state;
  $new_fin_state  = (1 << 2) ^ $state;

  echo "    <td><b><a href=\"new_tournament.php\">" .
    "Create a new tournament" . "</a></b></td>\n";
  if( $new_wait_state != 0 )
    echo "    <td><b><a href=\"tournaments.php?state=$new_wait_state\">" .
      $show_hide[$show_wait] . " tournaments waiting for participants" . "</a></b></td>\n";
  if( $new_run_state != 0 )
    echo "    <td><b><a href=\"tournaments.php?state=$new_run_state\">" .
      $show_hide[$show_run] . " running tournaments" . "</a></b></td>\n";
  if( $new_fin_state != 0 )
    echo "    <td><b><a href=\"tournaments.php?state=$new_fin_state\">" .
      $show_hide[$show_fin] . " finished tournaments" . "</a></b></td>\n";

  echo "  </tr>\n";
  echo "</table>\n";

  end_page(false);
}

?>

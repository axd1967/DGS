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

function parse_organizer_list( $organizers )
{
  $orig_organizer_array = explode( ",", $organizers );
  $organizer_array = array();
  foreach( $orig_organizer_array as $organizer )
    {
      if( !empty($organizer) )
        {
          $result = mysql_query( "SELECT ID from Players WHERE Handle='$organizer'" );
          if( mysql_num_rows( $result ) != 1 )
            error("unknown_organizer");

          $organizer_row = mysql_fetch_array( $result );
          $organizer_array[$organizer] = $organizer_row['ID'];
        }
    }
  return $organizer_array;
}

function create_organizer_list( $tid, $use_handle = false )
{
  $handle_name_string = ($use_handle ? "Players.Handle AS PlayerHandle " :
                                       "Players.Name AS PlayerName ");
  $order_string = ($use_handle ? "Players.Handle" : "Players.Name" );
  $organizer_result = mysql_query( "SELECT Players.ID AS PlayerID, " .
                                   $handle_name_string .
                                   "FROM Players, TournamentOrganizers " .
                                   "WHERE TournamentOrganizers.tid=$tid " .
                                   "AND Players.ID=TournamentOrganizers.pid " .
                                   "ORDER BY $order_string" );
  $organizers = '';
  while( $organizer_row = mysql_fetch_array($organizer_result) )
    {
      $organizer_string = ($use_handle ? $organizer_row['PlayerHandle'] :
                                         $organizer_row['PlayerName'] );
      if( !empty($organizers) )
        $organizers .= ', ';
      $organizers .=
        "<a href=\"userinfo.php?uid=" . $organizer_row['PlayerID'] .
        "\">" . $organizer_string . "</a>";
    }

  return $organizers;
}

function is_player_participating_in_tournament( $tid, $pid )
{
  $is_participating_result =
    mysql_query( "SELECT COUNT(*) AS IsParticipating " .
                 "FROM Tournament, TournamentParticipants " .
                 "WHERE TournamentParticipants.tid=$tid " .
                 "AND TournamentParticipants.pid=" . $pid .
                 " GROUP BY Tournament.ID" );

  $is_participating = true;
  if( mysql_num_rows($is_participating_result) != 1 )
    $is_participating = false;

  return $is_participating;
}

function draw_knockout( &$participants )
{
}

function start_tournament( $tid, $tour_row )
{
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

  $result = mysql_query( "SELECT Players.ID, Players.Name, Players.Handle, " .
                         "TournamentParticipants.Seeding, TournamentParticipants.PlayerNumber " .
                         "FROM Players, TournamentParticipants " .
                         "WHERE TournamentParticipants.tid=$tid " .
                         "AND TournamentParticipants.pid=Players.ID" );

  $participants = array();
  while( $prow = mysql_fetch_array( $result ) )
    array_push( $participants, $prow );

  /* Have checks to determine if we really are allowed to start the tournament */

  switch( $type_array[ $tour_row['Type'] ] )
  {
  case 'Knockout':
    {
      /* Probably should make seedings here! */
      draw_knockout( $participants );
    }
    break;

  default:
    {
      error( "unknown_tournament_type" );
    }
    break;
  }

  $result = mysql_query( "UPDATE Tournament SET State='RUNNING' WHERE Tournament=$tid" );
  if( mysql_affected_rows() != 1)
    error("could_not_start_tournament");
  
}

?>

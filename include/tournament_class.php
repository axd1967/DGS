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


/* Tournament states */
define( "TOUR_STATE_INACTIVE", 0 );
define( "TOUR_STATE_APPLICATIONPERIOD", 1 );
define( "TOUR_STATE_RUNNING", 2 )
define( "TOUR_STATE_FINISHED", 3 );


/*
 * Class for a tournament.
 * Each round in a tournament is held in the variable $rounds.
 */
class Tournament
{
  /* The identification of this tournament */
  var $ID;
  /* The name of this tournament. */
  var $name;
  /* A longer description. */
  var $description;

  /* The state of the tournament. */
  var $state;

  /* An array of all the rounds in this tournament.
   * Each element in the array should be either an ID together with a type
   * or a TournamentRound object.
   * The ID variant should look like this: array( 'ID' => 23, 'Type' => 'MacMahon' ).
   * If it is an ID it means that the object hasn't been loaded into
   * memory yet.
   * See also the function get_round().
   */
  var $rounds

  /* The application period of this tournamnet.
   * Should be an integer that says the number of days the application period should last.
   */
  var $applicationperiod;
  /* A timestamp that tells when the applicationperiod should start. */
  var $start_of_applicationperiod;
  /* A boolean that tells whether the tournamnet should be cancelled if too
   * few participants or if the tournament should start when the minimum
   * number of participants has been reached.
   * It also tells if the applicationperiod should end when the maximum number
   * of participants has been reached or if it should alwas start at the end of
   * the given applicationperiod.
   */
  var $strict_end_of_applicationperiod;
  /* This variable is a boolean that tells whether the tournament might
   * accept new participants after the tournament started.
   * The normal behaviour is that if this is set new participants will be accepted
   * between rounds. Other behaviour should be possible to define though.
   */
  var $recieve_applications_after_start;
  /* The maximum number of participants.
   * If this is null or negative, there will be no limit.
   */
  var $max_participants;
  /* The minumum number of participants.
   * Should be two or more, since you can't have tournaments with just one player.
   */
  var $min_participants;

}

/*
 * Base class for a tournament round.
 * This class should define the behaviour of a tournament round.
 */
class TournamentRound
{
  /* The identification of this round. */
  var $ID;
  /* The name of the type of this round.
   * Should be defined by each derived class.
   */
  var $type;

  /* The size of the board for this round. */
  var $board_size;

  /* TODO: More board- and time-variables. */
}

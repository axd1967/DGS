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

 /*!
  * \file tournament.php
  * \brief For class Tournament.
  */

 require( "include/tournament_round.php" );

/* Tournament states */
define( "TOUR_STATE_INACTIVE", 0 );
define( "TOUR_STATE_APPLICATIONPERIOD", 1 );
define( "TOUR_STATE_RUNNING", 2 );
define( "TOUR_STATE_FINISHED", 3 );


/*!
 * \brief Class for a tournament.
 *
 * Each round in a tournament is held in the variable $rounds.
 */
class Tournament
{
   /*! \privatesection */

   /*! \brief The identification of this tournament */
   var $ID;
   /*! \brief The name of this tournament. */
   var $name;
   /*! \brief A longer description. */
   var $description;

   /*! \brief The state of the tournament. */
   var $state;

   /*!
    * \brief An array of all the rounds in this tournament.
    *
    * Each element in the
    * array should be either an ID or a TournamentRound object.
    *
    * If it is an ID it means that the object hasn't been loaded into memory
    * yet.
    * \sa get_round.
    */
   var $rounds;

   /*!
    * \brief The application period of this tournamnet.
    *
    * Should be an integer that says the number of days the application
    * period should last.
    */
   var $applicationperiod;
   /*! A timestamp that tells when the applicationperiod should start. */
   var $start_of_applicationperiod;
   /*!
    * \brief A boolean that tells whether the tournamnet should be cancelled
    * if too few participants or if the tournament should start when the
    * minimum number of participants has been reached.
    *
    * It also tells if the applicationperiod should end when the maximum
    * number of participants has been reached or if it should alwas start at
    * the end of the given applicationperiod.
    */
   var $strict_end_of_applicationperiod;
   /*!
    * \brief This variable is a boolean that tells whether the tournament might
    * accept new participants after the tournament started.
    *
    * The normal behaviour is that if this is set new participants will be
    * accepted between rounds. Other behaviour should be possible to define
    * though.
    */
   var $recieve_applications_after_start;
   /*!
    * \brief The maximum number of participants.
    *
    * If this is null or negative, there will be no limit.
    */
   var $max_participants;
   /*!
    * \brief The minumum number of participants.
    *
    * Should be two or more, since you can't have tournaments with just one
    * player.
    */
   var $min_participants;

   /***
    * User functions.
    ***/

   /*! \publicsection */

   /*!
    * Constructor.
    *
    * \brief If called without $ID it is an illegible tournament.
    */
   function Tournament( $ID = -1 )
      {
         $this->ID = $ID;
      }

   /*!
    * Returns a round from the round-list.
    *
    * Loads the round into memory if it is not in memory already.
    *
    * \param $round Not decided yet what this should be.
    * \todo Decide how to reference a round.
    */
   function get_round( $round )
      {
      }

   /*! \privatesection */

   /***
    * Internal functions.
    ***/

   /*!
    * \brief Function that takes care of determining the type of the
    * tournament round and loads that tournament round into memory from the
    * database.
    */
   function load_tournamentround( $ID )
      {
      }
}

?>

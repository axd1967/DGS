<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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

require_once( "include/tournament_round.php" );

/* Tournament states */
define( "TOUR_STATE_INACTIVE", 0 );
define( "TOUR_STATE_APPLICATIONPERIOD", 1 );
define( "TOUR_STATE_RUNNING", 2 );
define( "TOUR_STATE_FINISHED", 3 );

$TourState_Strings = array( TOUR_STATE_INACTIVE          => T_("Inactive tournament"),
                            TOUR_STATE_APPLICATIONPERIOD => T_("Waiting for participation applications"),
                            TOUR_STATE_RUNNING           => T_("Tournament is running"),
                            TOUR_STATE_FINISHED          => T_("Tournament is finished") );

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
   var $Name;
   /*! \brief A longer description. */
   var $Description;

   /*! \brief The state of the tournament. */
   var $State;

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
   var $Rounds;

   /*!
    * \brief The application period of this tournamnet.
    *
    * Should be an integer that says the number of days the application
    * period should last.
    */
   var $ApplicationPeriod;
   /*! A timestamp that tells when the applicationperiod should start. */
   var $StartOfApplicationPeriod;
   /*!
    * \brief A boolean that tells whether the tournamnet should be cancelled
    * if too few participants or if the tournament should start when the
    * minimum number of participants has been reached.
    *
    * It also tells if the applicationperiod should end when the maximum
    * number of participants has been reached or if it should alwas start at
    * the end of the given applicationperiod.
    */
   var $StrictEndOfApplicationPeriod;
   /*!
    * \brief This variable is a boolean that tells whether the tournament might
    * accept new participants after the tournament started.
    *
    * The normal behaviour is that if this is set new participants will be
    * accepted between rounds. Other behaviour should be possible to define
    * though.
    */
   var $ReceiveApplicationsAfterStart;
   /*!
    * \brief The maximum number of participants.
    *
    * If this is null or negative, there will be no limit.
    */
   var $MaxParticipants;
   /*!
    * \brief The minumum number of participants.
    *
    * Should be two or more, since you can't have tournaments with just one
    * player.
    */
   var $MinParticipants;
   /*! \brief Whether the games in the tournament should change the general dragon rating. */ 
   var $Rated;
   /*! \brief Should the clock run on weekends? */
   var $WeekendClock;

   /*! \brief An array consisting of player ids of users organizing this tournament. */
   var $ListOfOrganizers;
   /*! \brief Cached value of organizers in html. */
   var $ListOfOrganizersInHTML;

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
         $this->ListOfOrganizersInHTML = null;

         if( $this->ID > 0 )
            $this->get_from_database();
      }

   /*!
    * \static
    * \brief Create new tournament from user supplied data.
    * \return The object of the new tournament;
    */
   function Create( $name,
                    $description,
                    $orig_organizer_id )
      {
         $tour = new Tournament();

         $tour->Name = $name;
         $tour->Description = $description;
         $tour->State = TOUR_STATE_INACTIVE;
         $tour->ListOfOrganizers = array( 0 => $orig_organizer_id );

         $result = mysql_query( "INSERT INTO Tournament SET " .
                                "Name='$tour->Name', " .
                                "Description='$tour->Description', " .
                                "State=$tour->State" )
            or error("tournament_error_message_to_be_decided_later");

         if( mysql_affected_rows() != 1)
            error("tournament_error_message_to_be_decided_later");

         $tour->ID = mysql_insert_id();

         $result = mysql_query( "INSERT INTO TournamentOrganizers SET " .
                                "tid=$tour->ID, " .
                                "pid=" . $tour->ListOfOrganizers[0] );

         if( mysql_affected_rows() != 1)
         {
            mysql_query( "DELETE FROM Tournament WHERE ID=$tid" );
            error("mysql_insert_tournament");
         }

         return $tour;
      }

   /*!
    * \brief Fill tournament values from database.
    */
   function get_from_database()
      {
         $result = mysql_query(
            "SELECT Tournament.*, " .
            "IFNULL(UNIX_TIMESTAMP(StartOfApplicationPeriod),NULL) AS SOAP_Unix " .
            "FROM Tournament WHERE ID='$this->ID'"
            )
           or error("tournament_error_message_to_be_decided_later");

         if( mysql_num_rows($result) != 1 )
           error("tournament_error_message_to_be_decided_later");

         $row = mysql_fetch_array( $result );
         foreach( $row as $key => $value )
            {
               if( is_string( $key ) and
                   $key != "FirstRound" and
                   $key != "StartOfApplicationPeriod" and
                   $key != "SOAP_Unix" )
               {
                  $this->$key = $value;
               }
            }

         $this->Rounds = array();
         if( isset($row['FirstRound']) )
         {
            array_push($this->Rounds, $row['FirstRound']);
         }

         $this->StartOfApplicationPeriod = $row['SOAP_Unix'];
         $this->StrictEndOfApplicationPeriod =
            ($this->StrictEndOfApplicationPeriod == 'Y' ? true : false);
         $this->ReceiveApplicationsAfterStart =
            ($this->ReceiveApplicationsAfterStart == 'Y' ? true : false);
         $this->Rated = ($this->Rated == 'Y' ? true : false);
         $this->WeekendClock = ($this->WeekendClock == 'Y' ? true : false);

         $this->ListOfOrganizers = array();
         $orgresult = mysql_query( "SELECT * FROM TournamentOrganizers " .
                                   "WHERE tid='$this->ID'" );
         while( $row = mysql_fetch_array( $orgresult ) )
           array_push( $this->ListOfOrganizers, $row['pid'] );
      }
   /*!
    * Returns a round from the round-list.
    *
    * Loads the round into memory if it is not in memory already.
    *
    * \param $round Not decided yet what this should be.
    * \todo Work out how to get rounds in a nice manner.
    */
   function get_round( $round )
      {
      }

   /*!
    * \brief Returns a string of organizers in html with links to the users.
    *
    * Caches the information in the $ListOfOrganizersInHTML variable.
    * \return A string in html of organizers.
    */
   function get_organizers_html()
      {
         if( is_null( $this->ListOfOrganizersInHTML ) )
         {
            if( empty( $this->ListOfOrganizers ) )
            {
               $this->ListOfOrganizersInHTML = "";
            }
            else
            {
               $result = mysql_query( "SELECT ID, Name FROM Players " .
                                      "WHERE ID IN (" .
                                      implode( ",", $this->ListOfOrganizers ) .
                                      ") ORDER BY Name" );
               $res_html_array = array();
               while( $row = mysql_fetch_array($result) )
               {
                  array_push($res_html_array,
                             "<a href=\"userinfo.php?uid=" . $row['ID'] .
                             "\">" . $row['Name'] . "</a>");
               }
               $this->ListOfOrganizersInHTML = implode(",", $res_html_array);
            }
         }
         return $this->ListOfOrganizersInHTML;
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

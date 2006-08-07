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
 * \file tournament_round.php
 * \brief For class TournamentRound.
 */

require_once( "include/form_functions.php" );

/*!
 * \brief Base class for a tournament round.
 *
 * This class should define the behaviour of a tournament round.
 *
 * \todo The board specification could perhaps be a class of its own that
 *       could be used by many classes in dragon.
 */
class TournamentRound
{
   /*! \privatesection */

   /*! \brief The identification of this round. */
   var $ID;
   /*!
    * \brief The name of the type of this round.
    *
    * Should be defined by each derived class.
    */
   var $type;
   /*! \brief The tournament that this round belongs to. */
   var $tournament_id;
   /*!
    * \brief The next round in the chain of rounds.
    *
    * Should be null if no next round (i.e. it is the last round).
    */
   var $next_round;
   /*!
    * \brief The previous round in the chain of rounds.
    *
    * Should be null if no previous round (i.e. it is the first round).
    */
   var $previous_round;

   /*! \brief The size of the board for this round. */
   var $board_size;
   /*! \brief The komi to use in all games in this round. */
   var $komi;
   /*! \brief Type of handicap to use in the games. */
   var $handicap_type;
   /*! \brief Maintime in all games for this round. */
   var $maintime;
   /*! \brief The byoyomi type for the games. */
   var $byoyomi_type;
   /*! \brief The byoyomi time for all games. */
   var $byoyomi_time;
   /*! \brief The number of byoyomi periods. */
   var $byoperiods;
   /*! \brief The number of times each pair of players should play against each other. */
   var $games_per_pair;

   /*! \brief An array consisting of player ids of all participants in this particular round. */
   var $list_of_participants;

   /***
    * Internal variables.
    ***/



   /***
    * User functions.
    ***/

   /*! \publicsection */

   /*! \brief Constructor without real initialization.  */
   function TournamentRound()
      {
         $ID = -1;
         $type = '';
      }

   /*!
    * \brief Construct a new Tournament round.
    * 
    * \static
    *
    * Is a static function, to make sure to use the correct derived function.
    */
   function construct_new_round_from_database( $round_id )
     {
     }

   /*!
    * \brief Creates a form containing all options for the round.
    *
    * This only creates the form for the options common to all types of
    * tournaments.
    *
    * It is meant that this form should be integrated in the final form
    * displayed using Form::add_form().
    *
    * \param form_name The name of the form. Not necessary if you plan to use
    * \param action    The actionpage.
    * \param method    The type of method, GET or POST.
    *
    * \note that the base function uses line 1000-1100 to allow the derived
    *       classes to add things before and after.
    *
    * \todo Add more common options.
    * \sa Form::Form, Form:add_form
    */
   function create_options_form( $form_name = 'option_form',
                                 $action = 'nopage.php',
                                 $method = FORM_POST )
      {
         $options_form = new Form( $form_name, $action, $method );

         $this->add_type_specific_options_to_form( $options_form );

         return $options_form;
      }

   /*!
    * \brief Generate a list of games for this round.
    *
    * \param what_games What type of games should be included.
    *                   Should be a string with one of the following values:
    *                   'Running', 'Finished', 'All'.
    */
   function get_game_list( $what_games )
      {
      }

   /*!
    * \brief Generate a list of games and print it.
    *
    * \param what_games What type of games should be included.
    *                   Should be a string with one of the following values:
    *                   'Running', 'Finished', 'All'.
    * \sa get_game_list
    */
   function print_game_list( $what_games )
      {
      }

   /***
    * Functions that should be implemented by all derived classes.
    ***/

   /*! \brief Should be used to add specific options for each tournamenttype. */
   function add_type_specific_options_to_form( &$options_form )
      {
      }

   /*!
    * \brief Generate new games for this round.
    *
    * Checks if there are active games in this round and that it should not
    * be ended. If these criterias are fulfilled, generates a new sets of
    * games. If the game should be ended, call end_of_round.
    *
    * \note Only applicable when the round is active.
    *
    * \sa end_of_round
    */
   function generate_games()
      {
      }

   /*!
    * \brief Ends the rounds if it should be ended.
    *
    * Should take care of generating a new round or announce a
    * winner.
    *
    * \note Only applicable when the round is active.
    */
   function end_of_round()
      {
      }

   /*!
    * \brief Prints a view of the results of this round.
    */
   function print_result_view()
      {
      }
}

?>

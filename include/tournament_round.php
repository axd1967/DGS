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
 * \file tournament_round.php
 * \brief For class TournamentRound.
 */

require( "include/form_functions.php" );

/*!
 * \brief Base class for a tournament round.
 *
 * This class should define the behaviour of a tournament round.
 */
class TournamentRound
{
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

  /*! \todo More board- and time-variables. */

  /***
   * Local (private) variables.
   ***/



  /***
   * User functions.
   ***/

  /*! \brief Constructor without real initialization.  */
  function TournamentRound()
    {
      $ID = -1;
      $type = '';
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
   * \see Form::Form, Form:add_form()
   */
  function create_options_form( $form_name = 'option_form',
                                $action = 'nopage.php',
                                $method = FORM_POST )
    {
      $options_form = new Form( $form_name, $action, $method );

      $this->add_type_specific_options_to_form( $options_form );

      return $options_form;
    }

  /***
   * Functions that should be implemented by all derived classes.
   ***/

  /*! \brief Should be used to add specific options for each tournamenttype. */
  function add_type_specific_options_to_form( &$options_form )
    {
    }
}

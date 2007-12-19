<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/* The code in this file is written by Ragnar Ouchterlony */

/*!
 * \file macmahon.php
 * \brief For class MacMahon.
 */

require_once( "include/tournament_round.php" );

/*!
 * \brief Implements the tournament type MacMahon.
 */
class MacMahon extends TournamentRound
{
  /*! \brief Constructor without real initialization. */
  function MacMahon()
    {
      parent::TournamentRound();
    }

  /*! \brief Add things special to the MacMahon round type to the options form. */
  function add_type_specific_options_to_form( &$options_form )
    {
    }

  /*! \brief Generate games in a MacMahon round. */
  function generate_games()
    {
    }

  /*! \brief Checks and ends the round if applicable. */
  function end_of_round()
    {
    }

  /*! \brief Prints a view of the results of this round. */
  function print_result_view()
    {
    }
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

require_once 'tournaments/include/tournament_globals.php';

 /*!
  * \file tournament_template.php
  *
  * \brief "Interface" / template-pattern for different Tournament-type-classes
  */


 /*!
  * \class TournamentTemplate
  *
  * \brief Template for different tournament-types
  */
class TournamentTemplate
{
   var $wizard_type;
   var $title;
   var $uid;

   var $need_rounds;
   var $allow_register_tourney_status;
   var $need_admin_create_tourney;

   /*! \brief Constructs template for different tournament-types. */
   function TournamentTemplate( $wizard_type, $title )
   {
      global $player_row;
      $this->wizard_type = $wizard_type;
      $this->title = $title;
      $this->uid = (int)@$player_row['ID'];

      // tournament-type-specific properties
      $this->need_rounds = false;
      $this->allow_register_tourney_status = array( TOURNEY_STATUS_REGISTER );
      $this->need_admin_create_tourney = true;
   }

   function to_string()
   {
      return print_r( $this, true );
   }

   function create_error( $msgfmt )
   {
      error('tournament_create_error', sprintf( $msgfmt, $this->uid ) );
   }

   // ---------- Interface ----------------------------------------

   /*! \brief Returns inserted Tournament.ID if successful; 0 otherwise. */
   function createTournament()
   {
      error('invalid_method', "TournamentTemplate.createTournament({$this->wizard_type})");
      return 0;
   }

} // end of 'TournamentTemplate'

?>

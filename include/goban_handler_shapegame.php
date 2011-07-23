<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Goban";

require_once( 'include/classlib_goban.php' );

 /* Author: Jens-Uwe Gaspar */


 /*!
  * \file goban_handler_shapegame.php
  *
  * \brief Class implementing GobanHandler to read and write Goban from game-snapshot.
  */


 /*!
  * \class GobanHandlerShapeGame
  * \brief Goban-reader and Goban-writer for Games.Snapshot format.
  */
class GobanHandlerShapeGame
{
   var $args;

   /*! \brief Constructs GobanHandler for shape-game-snapshot. */
   function GobanHandlerShapeGame( $arr_args=null )
   {
      $this->args = (is_array($arr_args)) ? array() : $arr_args;
   }


   /*! \brief (interface) Parses given snapshot and returns Goban-object. */
   function read_goban( $text )
   {
      // init
      $goban = new Goban();

      return $goban;
   }//read_goban

   /*! \brief (interface) Transforms given Goban-object into snapshot-format. */
   function write_goban( $goban )
   {
      return '';
   }

} //end 'GobanHandlerShapeGame'

?>

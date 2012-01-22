<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/classlib_goban.php';
require_once 'include/board.php';

 /* Author: Jens-Uwe Gaspar */


 /*!
  * \file goban_handler_dgsgame.php
  *
  * \brief Class implementing GobanHandler to read Goban from DGS-game, no write supported.
  */


 /*!
  * \class GobanHandlerDgsGame
  * \brief Goban-reader for DGS-game (game-id + optional move)
  */
class GobanHandlerDgsGame
{
   /*! \brief Constructs GobanHandler for DGS-game-reader. */
   function GobanHandlerDgsGame() { }

   /*!
    * \brief (interface) Parses given text or board returning Goban-object.
    * \param $text syntax "<game gid[,move]>" or a Board-object
    */
   function read_goban( $board_text )
   {
      static $VAL_MAP = array(
            BLACK => GOBS_BLACK,
            WHITE => GOBS_WHITE,
         );

      // init
      $goban = new Goban();
      $goban->setOptionsCoords( GOBB_MID, true );

      // parse board-text
      if( !is_a($board_text, 'Board') )
      {
         if( !preg_match("/^<game\s+(\d+)(,(\d+))?>$/", $board_text, $matches) )
         {
            $goban->setSize( 9, 9 );
            $goban->makeBoard( 9, 9, /*withHoshi*/true );
            return $goban;
         }

         // load board
         $gid = (int)@$matches[1];
         $movenum = (int)@$matches[3];
         $board = $this->load_board( $gid, $movenum );
      }

      // setup Goban
      $size = $board->size;
      $goban->setSize( $size, $size );
      $goban->makeBoard( $size, $size, /*withHoshi*/true );

      for( $y = 0; $y < $size; $y++ )
      {
         for( $x = 0; $x < $size; $x++ )
         {
            $stone = (int)@$board->array[$x][$y];
            $goban_stone = (int)@$VAL_MAP[$stone]; // undef = 00 (empty-field)
            if( $goban_stone )
               $goban->setStone( $x + 1, $y + 1, $goban_stone );
         }
      }

      return $goban;
   }//read_goban

   function load_board( $gid, $movenum )
   {
      $game_row = mysql_single_fetch( "GobanHandlerDgsGame.load_board($gid,$movenum)",
         "SELECT ID, Size, Moves, ShapeSnapshot FROM Games WHERE ID=$gid LIMIT 1" );
      if( !$game_row )
         error('unknown_game', "GobanHandlerDgsGame.load_board($gid,$movenum)");

      $board = new Board();
      if( !$board->load_from_db($game_row, $movenum, /*no-dead-marks*/true, /*load-last-msg*/false, /*fix*/false ) )
         error('internal_error', "GobanHandlerDgsGame.load_board2($gid,$movenum)");
      return $board;
   }//load_board

   /*! \brief (interface) Transforms given Goban-object into DGS-game. */
   function write_goban( $goban )
   {
      error('invalid_method', "GobanHandlerDgsGame.write_goban()");
   }

} //end 'GobanHandlerDgsGame'

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

//$TranslateGroups[] = "Game";

require_once 'include/classlib_game.php';



/*!
 * \class GameCheckMove
 *
 * \brief helper-class to handle moving of game.
 * \note main-purpose was to avoid globals.
 */
class GameCheckMove
{
   var $board;

   var $black_prisoners;
   var $white_prisoners;
   var $prisoners;
   var $nr_prisoners;
   var $colnr;
   var $rownr;

   function GameCheckMove( &$board )
   {
      $this->board = $board;
   }

   /*!
    * \brief Checks move on given board adjusting black/white-prisoners.
    * \note adjusted globals: $Black_Prisoners, $White_Prisoners
    * \note sets $this->prisoners with array of the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)
    * \note sets $this->nr_prisoners, colnr/rownr (of given $coord)
    */
   function check_move( $coord, $to_move, $last_move, $game_flags, $error_exit=true )
   {
      $this->black_prisoners = $this->white_prisoners = $this->nr_prisoners = 0;
      $this->prisoners = array();

      $Size = $this->board->size;
      $array = &$this->board->array;

      list($colnr,$rownr) = (is_array($coord)) ? $coord : sgf2number_coords($coord, $Size);
      $this->colnr = $colnr;
      $this->rownr = $rownr;

      if( !isset($rownr) || !isset($colnr) || @$array[$colnr][$rownr] != NONE )
      {
         if( $error_exit )
            error('illegal_position','move1');
         else
            return 'illegal_position';
      }
      $array[$colnr][$rownr] = $to_move;

      $this->board->check_prisoners( $colnr, $rownr, WHITE+BLACK-$to_move, $this->prisoners );

      $this->nr_prisoners = count($this->prisoners);
      if( $this->nr_prisoners == 0 )
      {
         // Check for suicide
         $suicide_allowed = false;
         if( !$this->board->has_liberty_check( $colnr, $rownr, $this->prisoners, $suicide_allowed) )
         {
            if( !$suicide_allowed )
            {
               if( $error_exit )
                  error('suicide');
               else
                  return 'suicide';
            }
         }
         return ''; // Ok, all tests passed.
      }

      // note: $GameFlags has set Ko-flag if last move has taken a single stone
      if( $this->nr_prisoners == 1 && ($game_flags & GAMEFLAGS_KO) )
      {
         // Check for ko
         list($x,$y) = $this->prisoners[0];
         if( $last_move == number2sgf_coords( $x, $y, $Size) )
         {
            if( $error_exit )
               error('ko');
            else
               return 'ko';
         }
      }

      if( $to_move == BLACK )
         $this->black_prisoners = $this->nr_prisoners;
      else
         $this->white_prisoners = $this->nr_prisoners;

      return ''; // Ok, all tests passed.
   }//check_move

   /*! \brief Updates black/white prisoners with diff generated by check_move()-func. */
   function update_prisoners( &$black_pr, &$white_pr )
   {
      $black_pr += $this->black_prisoners;
      $white_pr += $this->white_prisoners;
   }//update_prisoners

} // end 'GameCheckMove'



/*!
 * \brief Places handicap stones adjusted board-array.
 * \param $board Board-object, is modified with setting black handicap stones
 * \param $stonestring coordinate-list of handicap-stones to place
 * \param $coord if != false contains SGF-coord to add black-stone to stone-string
 * \return new list of strone-string with coords of stones
 */
function check_handicap( &$board, $stonestring, $coord=false )
{
   $Size= $board->size;
   $array= &$board->array;

   // add handicap stones to array
   $l = strlen( $stonestring );
   for( $i=0; $i < $l; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $Size);
      if( !isset($rownr) || !isset($colnr) || @$array[$colnr][$rownr] != NONE )
         error('illegal_position','move2');

      $array[$colnr][$rownr] = BLACK;
   }

   if( $coord )
   {
      list($colnr,$rownr) = sgf2number_coords($coord, $Size);
      if( !isset($rownr) || !isset($colnr) || @$array[$colnr][$rownr] != NONE )
         error('illegal_position','move3');

      $array[$colnr][$rownr] = BLACK;
      $stonestring .= $coord;
   }

   return $stonestring;
}//check_handicap




/*!
 * \class GameCheckScore
 *
 * \brief helper-class to handle scoring of game.
 * \note main-purpose was to avoid globals.
 */
class GameCheckScore
{
   var $board;
   var $stonestring;
   var $handicap;
   var $komi;
   var $black_prisoners;
   var $white_prisoners;
   var $toggle_unique;

   function GameCheckScore( &$board, $stonestring, $handicap, $komi, $black_prisoners, $white_prisoners )
   {
      $this->board = $board;
      $this->stonestring = (@$stonestring) ? $stonestring : '';
      $this->handicap = $handicap;
      $this->komi = $komi;
      $this->black_prisoners = $black_prisoners;
      $this->white_prisoners = $white_prisoners;
      $this->toggle_unique = false;
   }

   function set_toggle_unique()
   {
      $this->toggle_unique = true;
   }

   /*!
    * \brief Checks removal of stones by toggling stones.
    * \param $scoring_mode GSMODE_TERRITORY_SCORING | GSMODE_AREA_SCORING
    * \param $coord false to just treat stonestring; single sgf-coord or array of sgf-coords to toggle
    * \return GameScore-object
    */
   function check_remove( $scoring_mode, $coords=false, $with_board_status=false )
   {
      $Size = $this->board->size;
      $array = &$this->board->array;

      // toggle marked stones and marked dame to array

      // $stonearray is used to cancel out duplicates, in order to make $stonestring_loc shorter.
      $stonearray = array();

      $l = strlen($this->stonestring);
      for( $i=0; $i < $l; $i += 2 )
      {
         list($colnr,$rownr) = sgf2number_coords(substr($this->stonestring, $i, 2), $Size);
         if( !isset($rownr) || !isset($colnr) )
            error('illegal_position', "GCS.check_remove.move4($colnr,$rownr)");

         $stone = isset($array[$colnr][$rownr]) ? $array[$colnr][$rownr] : NONE ;
         if( $stone == BLACK || $stone == WHITE || $stone == NONE ) //NONE for MARKED_DAME
            $array[$colnr][$rownr] = $stone + OFFSET_MARKED;
         else if( $stone == BLACK_DEAD || $stone == WHITE_DEAD || $stone == MARKED_DAME )
            $array[$colnr][$rownr] = $stone - OFFSET_MARKED;

         if( !isset($stonearray[$colnr][$rownr]) )
            $stonearray[$colnr][$rownr] = true;
         else
            unset( $stonearray[$colnr][$rownr] );
      }

      if( $coords )
      {
         if( !is_array($coords) )
            $coords = array( $coords );

         $toggled = ( $this->toggle_unique ) ? array() : NULL;
         foreach( $coords as $coord )
         {
            list($colnr,$rownr) = sgf2number_coords($coord, $Size);
            if( !isset($rownr) || !isset($colnr) )
               error('illegal_position', "GCS.check_remove.move5($colnr,$rownr)");

            $stone = isset($array[$colnr][$rownr]) ? $array[$colnr][$rownr] : NONE ;
            if( MAX_SEKI_MARK<=0 || ($stone!=NONE && $stone!=MARKED_DAME) )
            {
               if( $stone!=BLACK && $stone!=WHITE && $stone!=BLACK_DEAD && $stone!=WHITE_DEAD )
                  error('illegal_position', "GCS.check_remove.move6($colnr,$rownr,$stone)");
            }

            $marked = array();
            $this->board->toggle_marked_area( $colnr, $rownr, $marked, $toggled );

            foreach( $marked as $sub )
            {
               list($colnr,$rownr) = $sub;
               if( !isset( $stonearray[$colnr][$rownr] ) )
                  $stonearray[$colnr][$rownr] = true;
               else
                  unset( $stonearray[$colnr][$rownr] );
            }
         }
      }

      $this->stonestring = '';
      foreach( $stonearray as $colnr => $sub )
      {
         foreach( $sub as $rownr => $dummy )
            $this->stonestring .= number2sgf_coords($colnr, $rownr, $Size);
      }

      $game_score = new GameScore( $scoring_mode, $this->handicap, $this->komi );
      $game_score->set_prisoners_all( $this->black_prisoners, $this->white_prisoners );
      $this->board->fill_game_score( $game_score, /*coords*/false, $with_board_status );

      return $game_score;
   }//check_remove

   /*! \brief Update stonestring after check_remove()-call. */
   function update_stonestring( &$stonestr )
   {
      $stonestr = $this->stonestring;
   }

} // end 'GameCheckScore'

?>

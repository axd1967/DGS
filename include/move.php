<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Game";


//ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//$prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)
function check_move( &$board, $coord, $to_move, $print_error=true)
{
   $Size= $board->size;
   $array= &$board->array;

   global $prisoners, $nr_prisoners, $colnr, $rownr;

   list($colnr,$rownr) = sgf2number_coords($coord, $Size);

   if( !isset($rownr) or !isset($colnr) or @$array[$colnr][$rownr] != NONE )
   {
      if( $print_error )
         error("illegal_position",'move1');
      else
      {
         echo "Illegal_position";
         return false;
      }
   }

   $array[$colnr][$rownr] = $to_move;


   $prisoners = array();
   $board->check_prisoners( $colnr, $rownr, WHITE+BLACK-$to_move, $prisoners);

   $nr_prisoners = count($prisoners);


   if( $nr_prisoners == 0 )
   {

      // Check for suicide

      $suicide_allowed = false;

      if( !$board->has_liberty_check( $colnr, $rownr, $prisoners, $suicide_allowed) )
      {
         if(!$suicide_allowed)
         {
            if( $print_error )
               error("suicide");
            else
            {
               echo "suicide";
               return false;
            }

         }
      }

      // Ok, all tests passed.
      return true;
   }


   global $Last_Move, $GameFlags; //input only

   if( $nr_prisoners == 1 and $GameFlags & KO )
   {

      // Check for ko

      list($dummy, list($x,$y)) = each($prisoners);

      if( $Last_Move == number2sgf_coords( $x, $y, $Size) )
      {
         if( $print_error )
            error("ko");
         else
         {
            echo "ko";
            return false;
         }
      }
   }


   global $Black_Prisoners, $White_Prisoners;

   if( $to_move == BLACK )
      $Black_Prisoners += $nr_prisoners;
   else
      $White_Prisoners += $nr_prisoners;


   // Ok, all tests passed.
   return true;
}


//place handicap stones from $stonestring
//if $coord then add it to $stonestring
function check_handicap( &$board, $coord=false)
{
   $Size= $board->size;
   $array= &$board->array;

   global $stonestring;
   if( !@$stonestring ) $stonestring = '';

   // add handicap stones to array

   $l = strlen( $stonestring );

   for( $i=0; $i < $l; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $Size);

      if( !isset($rownr) or !isset($colnr) or @$array[$colnr][$rownr] != NONE )
         error("illegal_position",'move2');

      $array[$colnr][$rownr] = BLACK;
   }

   if( $coord )
   {
      list($colnr,$rownr) = sgf2number_coords($coord, $Size);

      if( !isset($rownr) or !isset($colnr) or @$array[$colnr][$rownr] != NONE )
         error("illegal_position",'move3');

      $array[$colnr][$rownr] = BLACK;
      $stonestring .= $coord;
   }

}


//ajusted globals by check_remove(): $score, $stonestring;
function check_remove( &$board, $coord=false )
{
   $Size= $board->size;
   $array= &$board->array;

   global $stonestring;
   if( !@$stonestring ) $stonestring = '';

   // toggle marked stones and marked dame to array

   $l = strlen( $stonestring );

   // $stonearray is used to cancel out duplicates, in order to make $stonestring shorter.
   $stonearray = array();

   for( $i=0; $i < $l; $i += 2 )
   {
      list($colnr,$rownr) = sgf2number_coords(substr($stonestring, $i, 2), $Size);

      if( !isset($rownr) or !isset($colnr) )
         error("illegal_position",'move4');

      $stone = isset($array[$colnr][$rownr]) ? $array[$colnr][$rownr] : NONE ;
      if( $stone == BLACK or $stone == WHITE or $stone == NONE ) //NONE for MARKED_DAME
         $array[$colnr][$rownr] = $stone + OFFSET_MARKED;
      else if( $stone == BLACK_DEAD or $stone == WHITE_DEAD or $stone == MARKED_DAME )
         $array[$colnr][$rownr] = $stone - OFFSET_MARKED;

      if( !isset( $stonearray[$colnr][$rownr] ) )
         $stonearray[$colnr][$rownr] = true;
      else
         unset( $stonearray[$colnr][$rownr] );
   }

   if( $coord )
   {
      list($colnr,$rownr) = sgf2number_coords($coord, $Size);

      if( !isset($rownr) or !isset($colnr) )
         error("illegal_position",'move5');

      $stone = isset($array[$colnr][$rownr]) ? $array[$colnr][$rownr] : NONE ;
      if ( MAX_SEKI_MARK<=0 or ($stone!=NONE and $stone!=MARKED_DAME) )
      {
         if( $stone!=BLACK and $stone!=WHITE and $stone!=BLACK_DEAD and $stone!=WHITE_DEAD )
            error("illegal_position",'move6');
      }

      $marked = array();
      $board->toggle_marked_area( $colnr, $rownr, $marked );

      foreach( $marked as $sub )
      {
         list($colnr,$rownr) = $sub; 
         if( !isset( $stonearray[$colnr][$rownr] ) )
            $stonearray[$colnr][$rownr] = true;
         else
            unset( $stonearray[$colnr][$rownr] );
      }
   }

   $stonestring = '';
   foreach( $stonearray as $colnr => $sub )
   {
      foreach( $sub as $rownr => $dummy )
      {
         $stonestring .= number2sgf_coords($colnr, $rownr, $Size);
      }
   }

   global $score, $Komi, $White_Prisoners, $Black_Prisoners;
   $score = $board->create_territories_and_score();
   $score += $White_Prisoners - $Black_Prisoners + $Komi;
}
?>
<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

function check_move()
{
  global $coord, $colnr, $rownr, $Size, $array, $to_move, $Black_Prisoners, $White_Prisoners,
    $Last_X, $Last_Y, $prisoners, $nr_prisoners, $flags;

  $colnr = ord($coord)-ord('a');
  $rownr = ord($coord[1])-ord('a');


  if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 
      or $array[$colnr][$rownr] >= 1 )
    {
      header("Location: error.php?err=illegal_position");
      exit;
    }

  $array[$colnr][$rownr] = $to_move;


  $prisoners = array();
  check_prisoners($colnr,$rownr, 3-$to_move, $Size, $array, $prisoners);
         
         
  $nr_prisoners = count($prisoners);
         
  if( $to_move == BLACK )
    $Black_Prisoners += $nr_prisoners;
  else
    $White_Prisoners += $nr_prisoners;

  // Check for ko
                  
  if( $nr_prisoners == 1 and $flags & KO )
    {
      list($dummy, list($x,$y)) = each($prisoners);

      if( $Last_X == $x and $Last_Y == $y )
        {
          header("Location: error.php?err=ko");
          exit;
        }
    }

  // Check for suicide
         
  $suicide_allowed = false;
         
  if( !has_liberty_check($colnr, $rownr, $Size, $array, $prisoners, $suicide_allowed) )
    {
      if(!$suicide_allowed)
        {
          header("Location: error.php?err=suicide");
          exit;
        }
    }
         

  // Ok, all tests passed.
         
}

function check_handicap()
{
  global $stonestring, $colnr, $rownr, $Size, $array, $coord, $Handicap, 
    $enable_message, $extra_message, $handi;

  if( !$stonestring ) $stonestring = "1";

         // add killed stones to array
         
         $l = strlen( $stonestring );

         for( $i=1; $i < $l; $i += 2 )
             {
                 $colnr = ord($stonestring[$i])-ord('a');
                 $rownr = ord($stonestring[$i+1])-ord('a');
                 
                 if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 or 
                     $array[$colnr][$rownr] )
                     {
                         header("Location: error.php?err=illegal_position");
                         exit;
                     }

                 $array[$colnr][$rownr] = BLACK;
             }

         if( $coord )
             {
                 $colnr = ord($coord)-ord('a');
                 $rownr = ord($coord[1])-ord('a');

                 if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 or 
                     $array[$colnr][$rownr] )
                     {
                         header("Location: error.php?err=illegal_position");
                         exit;
                     }

                 $array[$colnr][$rownr] = BLACK;
                 $stonestring .= chr(ord('a') + $colnr) . chr(ord('a') + $rownr);
             }

         if( (strlen( $stonestring ) / 2) < $Handicap )
             {
                 $enable_message = false;
                 $extra_message = "<font color=\"green\">Place your handicap stones, please!</font>";
             }

         $handi = true;

}

function check_done()
{
  global $stonestring, $Size, $array, $prisoners, $Komi, $score, 
    $White_Prisoners, $Black_Prisoners;

  if( !$stonestring ) $stonestring = "1";

         // add killed stones to array
         
  $l = strlen( $stonestring );
  $index = array();

  for( $i=1; $i < $l; $i += 2 )
    {
      $colnr = ord($stonestring[$i])-ord('a');
      $rownr = ord($stonestring[$i+1])-ord('a');
                 
      if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
        {
          header("Location: error.php?err=illegal_position");
          exit;
        }
      if( $index[$colnr][$rownr] )
        unset($index[$colnr][$rownr]);
      else
        $index[$colnr][$rownr] = TRUE;

      $stone = $array[$colnr][$rownr];
      if( $stone == BLACK or $stone == WHITE )
        $array[$colnr][$rownr] = $stone + 6;
      else if( $stone == BLACK_DEAD or $stone == WHITE_DEAD )
        $array[$colnr][$rownr] = $stone - 6;
    }
         
  $prisoners = array();
  while( list($x, $sub) = each($index) )
    {
      while( list($y, $val) = each($sub) )
        {
          array_push($prisoners, array($x,$y));
        }
    }

  $score = create_territories_and_score( $Size, $array );
  $score += $White_Prisoners - $Black_Prisoners + $Komi;

}

function check_remove()
{
  global $stonestring, $Size, $array, $prisoners, $coord;
  
  if( !$stonestring ) $stonestring = "1";
  
  // add killed stones to array
  
  $l = strlen( $stonestring );
  
  for( $i=1; $i < $l; $i += 2 )
    {
      $colnr = ord($stonestring[$i])-ord('a');
      $rownr = ord($stonestring[$i+1])-ord('a');
      
      if( $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
        {
          header("Location: error.php?err=illegal_position");
          exit;
        }

      $stone = $array[$colnr][$rownr];
      if( $stone == BLACK or $stone == WHITE )
        $array[$colnr][$rownr] = $stone + 6;
      else if( $stone == BLACK_DEAD or $stone == WHITE_DEAD )
        $array[$colnr][$rownr] = $stone - 6;
    }
  
  if( $coord )
    {
      $colnr = ord($coord)-ord('a');
      $rownr = ord($coord[1])-ord('a');

      $stone = $array[$colnr][$rownr];
      if(( $stone != BLACK and $stone != WHITE and 
      $stone != BLACK_DEAD and $stone != WHITE_DEAD ) or
         $rownr >= $Size or $rownr < 0 or $colnr >= $Size or $colnr < 0 )
        {
          header("Location: error.php?err=illegal_position");
          exit;
        }
                 
      $prisoners = array();
      remove_dead( $colnr, $rownr, $array, $prisoners );

      while( list($dummy, list($x,$y)) = each($prisoners) )
        {
          $stonestring .= chr(ord('a') + $x) . chr(ord('a') + $y);
        }
    }

}
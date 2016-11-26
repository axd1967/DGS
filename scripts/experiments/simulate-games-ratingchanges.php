<?php
/*
Dragon Go Server
Copyright (C) 2001-  Jens-Uwe Gaspar

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

chdir("../../");
require_once 'include/rating.php';

{
   // script to run simulations of rating-changes between 2 players, Black always winning in even-games

   if ( $argc != 5 )
   {
      echo "Usage: php $argv[0] Size Black_Rank White_Rank IterationCount\n\n";
      exit(1);
   }

   $arg = 1;
   $size = (int)$argv[$arg++];
   $b_rating = read_rating($argv[$arg++]);
   $w_rating = read_rating($argv[$arg++]);
   $cnt = (int)$argv[$arg++];

   $g = array(
         'Handicap' => 0,
         'Komi' => 6.5,
         'Rated' => 'Y',
         'tid' => 0,
         'GameType' => 'GO',
         'Score' => -1, // Black wins
         'Size' => $size,
         'bRatingStatus' => RATEDSTATUS_RATED,
         'bRating' => $b_rating,
         'bRatingMin' => $b_rating - 200 - max(1600 - $b_rating,0)*2/15,
         'bRatingMax' => $b_rating + 200 + max(1600 - $b_rating,0)*2/15,
         'Black_ID' => 123,
         'Black_Start_Rating' => $b_rating,
         'wRatingStatus' => RATEDSTATUS_RATED,
         'wRating' => $w_rating,
         'wRatingMin' => $w_rating - 200 - max(1600 - $w_rating,0)*2/15,
         'wRatingMax' => $w_rating + 200 + max(1600 - $w_rating,0)*2/15,
         'White_ID' => 456,
         'White_Start_Rating' => $w_rating,
      );
   echo "Game-Row:\n", print_r($g, true), "\n\n";

   echo sprintf("New Rating #%5s:  Black %7.2f  |  White %7.2f   -   Black %10s  |  White %10s\n", 0,
      $g['bRating'], $g['wRating'],
      echo_rating( $g['bRating'], 1, 0, true, 1 ),
      echo_rating( $g['wRating'], 1, 0, true, 1 ) );

   for ($i=1; $i <= $cnt; $i++)
   {
      list( $result, $new_rating ) = update_rating2( 0, false, true, $g );

      echo sprintf("New Rating #%5s:  Black %7.2f  |  White %7.2f   -   Black %10s  |  White %10s\n", $i,
         $new_rating['bRating'], $new_rating['wRating'],
         echo_rating( $new_rating['bRating'], 1, 0, true, 1 ),
         echo_rating( $new_rating['wRating'], 1, 0, true, 1 ) );

      $g = array_merge( $g, $new_rating );
   }
}
?>


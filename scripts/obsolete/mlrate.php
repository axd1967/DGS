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

exit; ## for safety, as it's not clear if this (old) scripts still works

require( "include/std_functions.php" );

function echo_rating($rating)
{
   if( !isset($rating) ) return '-';

   $rank_val = round($rating/100);
   $string = '';

   if( $rank_val > 20.5 )
   {
      $string .= ( $rank_val - 20 ) . 'd';
   }
   else
   {
      $string .= ( 21 - $rank_val ) . 'k';
   }

   return $string;
}

connect2mysql();
{
   $result = mysql_query("SELECT Score, Handicap, Komi, Size, " .
                         "UNIX_TIMESTAMP(Lastchanged) AS Date, " .
                         "white.Handle AS whitehandle, white.Rating AS whiterating, " .
                         "black.Handle AS blackhandle, black.Rating AS blackrating " .
                         "FROM Games, Players AS white, Players AS black " .
                         "WHERE white.ID=White_ID AND black.ID=Black_ID " .
                         "AND Status='FINISHED' AND Rated='Done' " .
                         "ORDER BY Lastchanged")
      or die( mysql_error());
   while( $row = mysql_fetch_array( $result ) )
   {
      extract($row);
      echo "\"$whitehandle\" " . echo_rating($whiterating) .
         " \"$blackhandle\" " . echo_rating($blackrating) . " $Handicap $Komi $Size " .
         ($Score > 0 ? 'W' : 'B') . " " . date('Y-m-d', $Date) . "\n";
   }



}
?>

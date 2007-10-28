<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/*
 * Code example to test randomizing for nigiri-colors
 *
 * Usage: open its URL in browser
 *        l=number of series, b=number of boards
 */

chdir("../");
require_once("include/std_functions.php");
chdir("code_examples/");

{
   echo "<html><body>\n";

   echo "<pre>URL-args:<br>\n" .
        "  l - number of series (default 10)\n" .
        "  b - number of boards (default 20)\n" .
        "\n\n\n";

   $loop = get_request_arg('l');
   if ( empty($loop) || $loop <= 0 )
      $loop = 10;

   $bcnt = get_request_arg('b');
   if ( empty($bcnt) || $bcnt <= 0 )
      $bcnt = 20;

   for( $j=1; $j <= $loop; $j++)
   {
      $r = array( 0 => 0, 1 => 0 );
      $s = '';
      for( $i=0; $i < $bcnt; $i++)
      {
         mt_srand ((double) microtime() * 1000000);
         $c = mt_rand(0,1);
         $s .= ($c) ? 'B' : 'W';
         $r[$c]++;
      }

      echo sprintf("Nigiri #%02d: %s : W %2d, B %2d\n", $j, $s, $r[0], $r[1]);
   }

   echo "</pre>\n";
   echo "</body></html>\n";
}
?>


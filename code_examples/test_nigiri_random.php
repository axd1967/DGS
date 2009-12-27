<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
        "  l - number of series (default 10, max. 100)\n" .
        "  b - number of boards (default 20, max. 100)\n" .
        "\n\n\n";

   $loop = get_request_arg('l');
   if( empty($loop) || $loop <= 0 )
      $loop = 10;
   if( $loop > 100 )
      $loop = 100;

   $bcnt = get_request_arg('b');
   if( empty($bcnt) || $bcnt <= 0 )
      $bcnt = 20;
   if( $bcnt > 100 )
      $bcnt = 100;

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


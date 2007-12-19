<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'News'; //temporary solution to CSS problem
   start_page('DragonGoServer NEWS', true, $logged_in, $player_row );

   section( 'News', T_('DragonGoServer NEWS'));

   echo "<pre>\n"; //caution: no <div> allowed inside

   $contents = join('', file ('NEWS'));

   // format NEWS-page:
   $contents = make_html_safe( $contents, true );
   // format: "#release anchor-name [release-date] - DGS-version"
   $contents = preg_replace("/#release\\s+(\w+?)\\s+(.*?)<br>/is",
      "\n<a name=\"\\1\"></a><span class=\"ReleaseTitle\">\\2</span>\n",
      $contents);

   echo $contents;

   echo "</pre>\n";

   end_page();
?>

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'News'; //temporary solution to CSS problem
   start_page('DragonGoServer NEWS', true, $logged_in, $player_row );

   section( 'News', T_('DragonGoServer NEWS'));

   echo "<pre>\n";

   $contents = join('', file ('NEWS'));

   // format NEWS-page:
   $contents = make_html_safe( $contents, true );
   // format: "#release anchor-name [release-date] - DGS-version"
   $contents = preg_replace("/#release\\s+(\w+?)\\s+(.*?)<br>/is", "<a name=\"\\1\">\n<div class=\"ReleaseTitle\">\\2</div>", $contents);

   echo $contents;

   echo "</pre>\n";

   end_page();
?>

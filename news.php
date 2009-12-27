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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

function build_TOC( $text )
{
   $lines = explode("\n", $text);
   $headers = preg_grep("/^#release/i", $lines);
   foreach( $headers as $header )
   {
      $toc_entries[] = preg_replace( "/^#release\\s+(\w+?)\\s+(.*?)\\s*$/i",
         "<li><a href=\"#\\1\">\\2</a>",
         $header);
   }
   if( count($toc_entries) > 0 )
      return '<div class="ReleaseTOC"><h2>' . T_('Table of contents') . '</h2>'
         . "<ul>\n" . implode("\n", $toc_entries) . "</ul></div>\n"
         . '<hr noshade="1" size="1">';
   else
      return '';
} //build_TOC

{
   $ThePage = new Page('News');

   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   // read & format NEWS-page
   $contents = @read_from_file('NEWS');
   $toc = build_TOC( $contents );

   $contents = make_html_safe( $contents, true );

   // format: "#release anchor-name [release-date] - DGS-version"
   $contents = preg_replace("/#release\\s+(\w+?)\\s+(.*?)<br>/is",
      "\n<a name=\"\\1\"></a><span class=\"ReleaseTitle\">\\2</span>\n",
      $contents);

   // add TOC
   $contents = preg_replace("/%TOC%(<br>)?/is", $toc, $contents);
   $contents = preg_replace("/<br>/is", "\n", $contents);

   start_page('DragonGoServer NEWS', true, $logged_in, $player_row );

   section( 'News', T_('DragonGoServer NEWS'));

   echo "<pre>\n"; //caution: no <div> allowed inside

   echo $contents;

   echo "</pre>\n";

   end_page();
}
?>

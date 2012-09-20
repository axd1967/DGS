<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir('..');
require_once 'include/std_functions.php';

$GLOBALS['ThePage'] = new Page('Goodies');


{
   connect2mysql();
   $logged_in = who_is_logged($player_row);

   $page = "goodies/index.php";

   $files = array();
   $path_goodies = 'goodies';
   if( $fh = opendir($path_goodies) )
   {
      while( false !== ($file = readdir($fh)) )
      {
         if( substr($file,-8) == '.user.js' )
            $files[] = "$path_goodies/$file";
      }
      closedir($fh);
   }
   else
      echo "Error: open dir fails<br />";
   asort( $files);


   start_page( T_('Goodies'), true, $logged_in, $player_row );

   echo '<h3>', T_('Dragon Go Goodies'), "</h3>\n",
      "<div>\n",
      "<h4>", T_('GreaseMonkey Scripts'), "</h4>\n",
      "<em>", T_('Depending of your GreaseMonkey version, either Click or Right-Click on a desired name-link to install it:'), "</em>\n",
      "<ul>\n";

   foreach( $files as $file )
   {
      $fh = fopen($file, 'r');
      $txt = fread($fh, filesize($file));
      fclose($fh);

      // @name        DGS Section Hide
      foreach( array('name','description') as $field )
      {
         $r = '%@'.$field.'\\s+(.*)%i';
         preg_match($r, $txt, $m);
         $$field = @$m[1];
      }
      $r = '%<scriptinfos>(.*?)</scriptinfos>%ism';
      preg_match($r, $txt, $m);
      $infos = @$m[1];
      $infos = trim($infos, "\n\r");
      $infos = preg_replace(
         '%(http://[^\\s]+)%is',
         "<a href='\\1'>\\1</a>",
         $infos);

      $txt = '';
      $str = "- $description\n";
      if( $infos )
         $str.= "<br />- Script infos:\n";
      $txt.= "<dt>\n$str</dt>\n";

      $str = ( $infos ) ? "<pre>$infos</pre>\n" : '';
      $txt.= "<dd>\n$str</dd>\n";
      $txt = "<dl>\n$txt</dl>\n";

      //disabling the caches while installing the script cause me some problems.
      //the ?date=$NOW ensure to reload the file (fake no-cache)
      //the $NOW.user.js give a fake extension to feed the GreaseMonkey install
      $str = "<p><strong>Name: <a href='./$file?date=$NOW.user.js'>$name</a></strong></p>\n";

      echo "<li>$str$txt</li>\n";
   } //$files

   echo "</ul>\n",
      "</div>\n";

   end_page();
}

?>

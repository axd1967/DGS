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

// Search the php files to see which are to be included in TranslationPages




chdir( '../' );
require_once( "include/std_functions.php" );

function find_php_files( )
{
   $directories = array( '', 'include/', 'forum/' );

   $array = array();

   foreach( $directories as $dir )
   {
      foreach (glob("$dir*.php") as $filename)
      {
         $array[] = $filename;
      }
   }
   return $array;
}

function grep_file($regexp, $file, &$matches)
{
   $fd = fopen($file, 'r');

   $contents = fread($fd, filesize($file));

   return preg_match($regexp.'m', $contents, $matches);
}



{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");

   start_html('update_translation_pages', 0);

   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors:<br>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p>(just show queries needed):<br>";
   }



   $query = "SELECT * FROM TranslationGroups";
   $result = mysql_query($query) or die(mysql_error());

   $translationgroups = array();
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $translationgroups[$row['Groupname']] = $row['ID'];
   }


// first search the php pages to find which should be translated.

   $files = find_php_files();
   $translationpages_found = array();
   foreach( $files as $file )
   {
      if( grep_file('/^\$TranslateGroups\[\] = \"(\w+)\";$/', $file, $matches) )
      {
//         echo $file . " " . $matches[1] . ", " . $translationgroups[$matches[1]] . "<br>\n";
         $translationpages_found[$file] = array($translationgroups[$matches[1]], false);
      }
   }


// now, compare with the database to see which entries should be updated.

   $query = "SELECT * FROM TranslationPages";

   $result = mysql_query($query) or die(mysql_error());

   $n= (int)mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      if( !isset($translationpages_found[$row['Page']]) )
      {
         echo "<hr>Should be deleted: " . $row['Page'] . "<br>\n";
         dbg_query("DELETE FROM TranslationPages WHERE ID=" . $row['ID'] . " LIMIT 1");
      }
      else
      {
         $p = $translationpages_found[$row['Page']];
         if( $p[1] === true )
            echo "<hr><font color=red>Error: Duplicate entry: " . $row['Page'] .
               "</font><br>\n";
         else
         {
            $translationpages_found[$row['Page']][1] = true;

            if( $p[0] !== $row['Group_ID'] )
            {
               echo "<hr>Group changed: " . $row['Page'] .
                  ": " . $row['Group_ID'] . " --> " . $p[0] . "<br>\n";
               dbg_query("UPDATE TranslationPages SET Group_ID=" . $p[0] .
                         " WHERE ID=" . $row['ID'] . " LIMIT 1");
            }
         }
      }
   }


   foreach( $translationpages_found as $page => $val )
   {
      if( $val[1] === false )
      {
         echo "<hr>To be added: " . $page . "<br>\n";
         dbg_query("INSERT INTO TranslationPages " .
                   "SET Page='" . $page . "', Group_ID=" . $val[0]);
      }
   }

   echo "<hr>Done!!!\n";

   end_html();
}

?>
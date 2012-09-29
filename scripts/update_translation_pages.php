<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Search the php files to see which are to be included in TranslationPages

chdir( '../' );
require_once( "include/std_functions.php" );


function find_php_files( )
{
   $directories = array(
           ''
         , 'goodies/'
         , 'include/'
         , 'include/db/'
         , 'include/quick/'
         , 'features/'
         , 'forum/'
         , 'translations/'
         , 'rss/'
         , 'tournaments/'
         , 'tournaments/ladder/'
         , 'tournaments/roundrobin/'
         , 'tournaments/include/'
         , 'tournaments/include/types/'
         , 'wap/'
         );

   $array = array();

   foreach( $directories as $dir )
   {
      foreach( glob("$dir*.php") as $filename )
      {
         //echo "filename=$filename<br>";
         $array[] = $filename;
      }
   }
   return $array;
}

function grep_file($regexp, $file, &$matches)
{
   $contents = read_from_file($file, 0);
   //$contents = php_strip_whitespace($file); //FIXME (since PHP5) strip PHP-comments

   return preg_match($regexp.'m', $contents, $matches);
}

function group_string( $id)
{
   global $translationgroups;
   return var_export( array_search($id, $translationgroups), true)."($id)";
}



{//main
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'scripts.update_translation_pages');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.update_translation_pages');
   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.update_translation_pages');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   start_html('update_translation_pages', 0);

   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
         if( !mysql_query( $s) )
            die("<BR>$s;<BR>" . mysql_error() );
         echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   $query = "SELECT * FROM TranslationGroups";
   $result = mysql_query($query) or die(mysql_error());

   $translationgroups = array();
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $translationgroups[$row['Groupname']] = $row['ID'];
   }
   mysql_free_result($result);


// first search the php pages to find which should be translated.

   $files = find_php_files();
   $translationpages_found = array();
   foreach( $files as $file )
   {
      //note: only keep the first match of the file (starting at column 1)
      if( grep_file('/^\$TranslateGroups\[\]\s*=\s*([\"\'])(\w+)\1\s*;/', $file, $matches) )
      {
         $group_found = $matches[2];
         //echo $file . " " . $group_found . ", " . $translationgroups[$group_found] . "<br>\n";
         if( !isset($translationgroups[$group_found]) )
         {
            echo "<hr>Should be adjusted NOW: '$group_found' from $file ... OR be added:<br>\n";
            dbg_query("INSERT INTO TranslationGroups SET Groupname='" . mysql_addslashes($group_found) . "'");
            echo "<hr>Fatal error: re-run the script!!!\n";
            end_html();
            exit;
         }
         $translationpages_found[$file] = array($translationgroups[$group_found], false);
      }
   }


// now, compare with the database to see which entries should be updated.

   $query = "SELECT * FROM TranslationPages";
   $result = mysql_query($query) or die(mysql_error());

   $n= (int)mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p></p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      if( !isset($translationpages_found[$row['Page']]) )
      {
         echo "<hr>Should be deleted: " . $row['Page'] . "<br>\n";
         dbg_query("DELETE FROM TranslationPages WHERE Page='" . mysql_addslashes($row['Page']) . "' LIMIT 1");
      }
      else
      {
         $ref = &$translationpages_found[$row['Page']];
         if( $ref[1] === true )
         {
            echo "<hr><font color=red>Error: Duplicate entry: " . $row['Page'] .
               "</font><br>\n";
            continue;
         }
         $ref[1] = true;
         if( $ref[0] !== $row['Group_ID'] )
         {
            echo sprintf( "<hr>Group changed: %s: %s --> %s<br>\n",
                          $row['Page'], group_string($row['Group_ID']), group_string($ref[0]) );
            dbg_query("UPDATE TranslationPages SET Group_ID=" . $ref[0] .
                      " WHERE Page='" . mysql_addslashes($row['Page']) . "' LIMIT 1");
         }
      }
   }
   mysql_free_result($result);


   foreach( $translationpages_found as $page => $val )
   {
      if( $val[1] === false )
      {
         echo "<hr>To be added: $page --> ", group_string($val[0]), "<br>\n";
         dbg_query("INSERT INTO TranslationPages " .
            "SET Page='" . mysql_addslashes($page) . "', Group_ID=" . $val[0]);
      }
   }

   echo "<hr>Done!!!\n";
   end_html();
}//main

?>

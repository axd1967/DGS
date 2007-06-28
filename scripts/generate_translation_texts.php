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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/make_translationfiles.php" );
chdir( 'scripts' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !($player_row['admin_level'] & ADMIN_TRANSLATORS) )
      error('adminlevel_too_low');

   $TheErrors->set_mode(ERROR_MODE_PRINT);

   start_html('update_translation_pages', 0);

   if( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors:</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      echo "<p>(just show queries needed)"
         ."<br>".anchor($_SERVER['PHP_SELF']           , 'Show it again')
         ."<br>".anchor($_SERVER['PHP_SELF'].'?do_it=1', '[Validate it]')
         ."</p>";
   }


   $result = mysql_query("SELECT * FROM TranslationPages");

   while( $row = mysql_fetch_array($result) )
   {
      $Filename = $row['Page'];
      $Group_ID = $row['Group_ID'];

      echo "<hr><p>$Filename $Group_ID</p><hr>\n";

      $fd = fopen( $main_path . $Filename, 'r' )
         or error( 'couldnt_open_file' );

      $contents = fread($fd, filesize ($main_path . $Filename));

      $pattern = "%T_\((['\"].*?['\"])\)[^'\"]%s";
      preg_match_all( $pattern, $contents, $matches );

      foreach( $matches[1] as $str )
      {
         unset($string);
         eval( "\$string = $str;" );
         if( !isset($string) || $string == '' )
         {
            echo "*** Error: something went wrong with [$str]<br>";
            continue;
         }
         $tmp= add_text_to_translate('generate_translation_texts'
               , $string, $Group_ID, $do_it);
         if( $do_it && $tmp )
            $tmp= '++ '.$string;
         if( $tmp )
            echo textarea_safe($tmp)."<br>";
      }
   }
   mysql_free_result($result);

   echo "<hr>Done!!!\n";

   end_html();
}

?>
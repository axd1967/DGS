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

chdir( '../' );
require_once( "include/std_functions.php" );
chdir( 'scripts' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_TRANSLATORS) )
     error("adminlevel_too_low");


  $result = mysql_query("SELECT * FROM TranslationPages");

  while( $row = mysql_fetch_array($result) )
  {
     $Filename = $row['Page'];

     echo "<hr><p>$Filename<hr><p>\n";

     $fd = fopen( $base_path . $Filename, 'r' )
        or error( 'couldnt_open_file' );

     $contents = fread($fd, filesize ($base_path . $Filename));

     $pattern = "/T_\((['\"].*?['\"])\)[^'\"]/s";
     preg_match_all( $pattern, $contents, $matches );

     foreach( $matches[1] as $string )
        {
           $string = preg_replace( '/[\'"]\s+\.\s+[\'"]/s', "", $string );
           $string = preg_replace( '/\\n/', '\n', $string );

           $res = mysql_query("SELECT ID FROM TranslationTexts WHERE Text=$string");
           if( mysql_num_rows( $res ) == 0 )
           {
              mysql_query("INSERT INTO TranslationTexts SET Text=$string")
                 or die(mysql_error());

              mysql_query("INSERT INTO TranslationFoundInGroup " .
                          "SET Text_ID=" . mysql_insert_id() . ", " .
                          "Group_ID=" . $row['Group_ID'] );

              echo "<br>$string";

           }
           else
           {
              $text_row = mysql_fetch_array($res);
              mysql_query("INSERT INTO TranslationFoundInGroup " .
                          "SET Text_ID=" . $text_row['ID'] . ", " .
                          "Group_ID=" . $row['Group_ID'] );
           }
        }
  }
}

?>
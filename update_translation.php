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

/* The code in this file is written by Ragnar Ouchterlony */

require( "include/std_functions.php" );
require( "include/translation_info.php" );

function replace_unnecessary_chars( $string )
{
  return str_replace( "'", "\\'", $string );
}

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( $change || $apply_changes )
    {
      $translator_array = explode(',', $player_row['Translator']);

      if( !in_array( $translate_lang, get_known_languages() ) )
        error('no_such_translation_language');

      if( !in_array( $translate_lang, $translator_array ) )
        error('not_correct_transl_lang');

      $k_langs = get_known_languages_with_full_names();

      $the_translator->change_language($translate_lang);
      $the_translator->set_return_empty();

      $last_updated = $LANG_UPDATE_TIMESTAMPS[ $translate_lang ];
      $query = "SELECT * FROM Translationlog WHERE Language='$translate_lang' ";
      if( $last_updated > 0 )
        $query .= "AND timestamp > FROM_UNIXTIME($last_updated)";
      $query .= "ORDER BY ID";

      $result = mysql_query($query);
      $translation_changes = array();
      while( $row = mysql_fetch_array( $result ) )
        $translation_changes[ $row['CString'] ] = $row['Translation'];

      $new_translations = array();
      $query = "INSERT INTO Translationlog (Handle,Language,CString,Translation) VALUES ";
      $counter = 0;
      $found_anything = false;
      foreach( $translation_info as $string => $info )
        {
          $counter++;

          if( ${"transl$counter"} )
            {
              $current_translation = '';
              if( array_key_exists( $string, $translation_changes ) )
                $current_translation = $translation_changes[$string];
              else
                $current_translation = T_($string);

              $translation = ${"transl$counter"};
              if( strcmp($translation, $current_translation) != 0 )
                {
                  $found_anything = true;
                  $translation_changes[$string] = $translation;
                  $query .= "( '" . $player_row['Handle'] .
                    "', '$translate_lang', '$string', '$translation' ),";
                }
            }
        }

      if( $found_anything )
        mysql_query(substr($query,0,-1)) or error('couldnt_update_translation');

      if( $apply_changes )
        {
          $lang_php_code = sprintf( $translation_template_top,
                                    $translate_lang,
                                    $k_langs[$translate_lang],
                                    $NOW );

          foreach( $translation_info as $string => $info )
            {
              $translation = '';
              if( array_key_exists( $string, $translation_changes ) )
                $translation = $translation_changes[$string];
              else
                $translation = T_($string);

              $r_string = replace_unnecessary_chars( $string );
              $r_translation = replace_unnecessary_chars( $translation );

              $lang_php_code .= "'$r_string' =>\n'$r_translation',\n\n";
            }

          $lang_php_code = substr( $lang_php_code, 0, -3 );
          $lang_php_code .= $translation_template_bottom;

          $filename = "translations/" . $translate_lang . ".php";

          if( !copy( $filename, $filename . ".bak" ) )
            error( "couldnt_make_backup" );

          @chmod( $filename . ".bak", 0666 );

          write_to_file( $filename, $lang_php_code );
        }
    }

  jump_to("translate.php?translate_lang=$translate_lang&group=$group");
}
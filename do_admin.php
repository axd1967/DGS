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
require( "include/form_functions.php" );
require( "include/translation_info.php" );

{

  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( $player_row['Adminlevel'] < 2 )
    error("adminlevel_too_low");


  if( $addlanguage )
    {
      if( strlen( $twoletter ) < 2 || empty( $langname ) )
        error("admin_add_lang_missing_field");

      $k_langs = get_known_languages_with_full_names();
      if( array_key_exists( $twoletter, $k_langs ) || in_array( $langname, $k_langs ) )
        error("admin_add_lang_exists");

      $new_lang_php_code = sprintf( $translation_template_top,
                                    $twoletter, $langname,
                                    $NOW, gmdate( 'Y-m-d H:i:s T', $NOW ) );
      $new_lang_php_code =
        substr( $new_lang_php_code, 0, -1 ) .
        $translation_template_bottom;

      write_to_file( "translations/$twoletter.php", $new_lang_php_code );

      $new_all_languages_php_code = $translation_template_copyright . "\n";
      foreach( $k_langs as $lang => $desc )
        $new_all_languages_php_code .=
        "add_to_known_languages( \"$lang\", \"$desc\" );\n";

      $new_all_languages_php_code .=
        "add_to_known_languages( \"$twoletter\", \"$langname\" );\n";
      $new_all_languages_php_code .= "\n?>\n";

      write_to_file( "translations/all_languages.php", $new_all_languages_php_code );
    }

  if( $transladd )
    {
      if( empty($transluser) )
        error("no_specified_user");

      if( !isset($transladdlang) or empty($transladdlang) )
        error("no_lang_selected");

      $result = mysql_query( "SELECT Translator FROM Players WHERE Handle='$transluser'" );

      if( mysql_affected_rows() != 1 )
        error("unknown_user");

      $row = mysql_fetch_array( $result );
      if( empty($row['Translator']) )
        $translator = array();
      else
        $translator = explode( ',', $row['Translator'] );

      if( !in_array( $transladdlang, $translator ) )
        {
          array_push( $translator, $transladdlang );
          $new_langs = implode(',', $translator);
          $result = mysql_query( "UPDATE Players SET Translator='$new_langs' WHERE Handle='$transluser'" );
          if( mysql_affected_rows() != 1 )
            error("unknown_user");
        }
    }

  if( $translpriv )
    {
      if( empty($transluser) )
        error("no_specified_user");

      if( !isset( $transllang ) )
        $transllang = array();

      $new_langs = implode(',', $transllang);

      $result = mysql_query( "UPDATE Players SET Translator='$new_langs' WHERE Handle='$transluser'" );
      if( mysql_affected_rows() != 1 )
        error("unknown_user");
    }

  jump_to("admin.php");
}
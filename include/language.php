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

$KNOWN_LANGUAGES = array();
$CHARACTER_ENCODINGS = array();

class Language
{
  var $full_name;
  var $last_updated;

  var $translated_strings;

  function find_translation( $string, $return_empty = false )
    {
      $result = $string;
      if( array_key_exists( $string, $this->translated_strings ) )
        {
          $tmp_result = $this->translated_strings[ $string ];
          if( !empty( $tmp_result ) || $return_empty )
            $result = $tmp_result;

          return $result;
        }

      if( $return_empty )
        return '';
      return $result;
    }
}

function add_to_known_languages( $lang, $lang_full_name, $charenc )
{
  global $KNOWN_LANGUAGES, $CHARACTER_ENCODINGS;

  $KNOWN_LANGUAGES[ $lang ] = $lang_full_name;
  $CHARACTER_ENCODINGS[ $lang ] = $charenc;
}

function get_known_languages()
{
  global $KNOWN_LANGUAGES;

  return array_keys($KNOWN_LANGUAGES);
}

function get_known_languages_with_full_names()
{
  global $KNOWN_LANGUAGES;

  return $KNOWN_LANGUAGES;
}

function get_known_translated_languages()
{
  global $KNOWN_LANGUAGES;
  $t_langs = array();
  foreach( $KNOWN_LANGUAGES as $lang => $fullname )
    $t_langs[$lang] = T_($fullname);

  return $t_langs;
}

require( "translations/all_languages.php" );
?>

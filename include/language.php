<?php

$KNOWN_LANGUAGES = array();

/*
 * TODO: Add possibility to use %s, %d and other 'printf'-stuff in translations.
 */
class Language
{
  var $translated_strings;

  function find_translation( $string )
    {
      $result = $string;
      if( array_key_exists( $string, $this->translated_strings ) )
        {
          $tmp_result = $this->translated_strings[ $string ];
          if( !empty( $tmp_result ) )
            $result = $tmp_result;

          return $result;
        }

      return $result;
    }
}

function add_to_known_languages( $lang, $lang_full_name )
{
  global $KNOWN_LANGUAGES;

  $KNOWN_LANGUAGES[ $lang ] = $lang_full_name;
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

include( "translations/en.php" );
include( "translations/sv.php" );
?>

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

function add_to_known_languages( $lang )
{
  global $KNOWN_LANGUAGES;

  array_push( $KNOWN_LANGUAGES, $lang );
}

function get_known_languages()
{
  global $KNOWN_LANGUAGES;
  $result = array( "en", "sv" );
  return $result;
}

include( "translations/en.php" );
include( "translations/sv.php" );
?>

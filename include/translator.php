<?php

require( "include/language.php" );

class Translator
{
  var $current_language;
  var $loaded_languages;
  var $collect_translateable;
  var $translated_messages;

  function Translator( $lang = '' )
    {
      $this->loaded_languages = array();
      $this->collect_translateable = false;
      $this->translated_messages = array();
      $this->change_language( $lang );
    }

  function change_language( $lang = '' )
    {
      if( !empty( $lang ) )
        {
          $this->current_language = $lang;
        }
      else
        {
          $this->current_language = $this->get_preferred_browser_language();
        }
    }

  function set_collect_translateable_mode()
    {
      $this->collect_translateable = true;
    }

  function translate( $string )
    {
      $language = $this->current_language;

      if( $this->collect_translateable )
        array_push( $this->translated_messages, $string );

      if( !array_key_exists( $language, $this->loaded_languages  ) )
        {
          $lang_class_name = $language . "_Language";
          $this->loaded_languages[ $language ] = new $lang_class_name;
        }

      return $this->loaded_languages[ $language ]->find_translation( $string );
    }

  function get_preferred_browser_language()
    {
      global $HTTP_ACCEPT_LANGUAGE, $KNOWN_LANGUAGES;

      $regexp_languages = "";
      foreach( $KNOWN_LANGUAGES as $lang )
        {
          if( empty( $regexp_languages ) )
            $regexp_languages = "($lang";
          else
            $regexp_languages .= "|$lang";
        }
      $regexp_languages .= ")";

      if( isset( $HTTP_ACCEPT_LANGUAGE ) )
        {
          $languages = explode( ", ", $HTTP_ACCEPT_LANGUAGE );

          foreach( $languages as $http_language )
            if( ereg( $regexp_languages, $http_language, $found))
              return $found[1];
        }
    }

}

$the_translator = new Translator;

/* Alias for $the_translator->translate($string) */
function T_($string)
{
  global $the_translator;
  return $the_translator->translate($string);
}

?>
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

require( "include/language.php" );

class Translator
{
  var $current_language;
  var $loaded_languages;
  var $return_empty;

  function Translator( $lang = '' )
    {
      $this->loaded_languages = array();
      $this->return_empty = false;
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

  function set_return_empty( $val = true )
    {
      $this->return_empty = $val;
    }

  function translate( $string )
    {
      $language = $this->current_language;

      if( strcmp( $this->current_language, 'C' ) == 0 )
        {
          return $string;
        }

      if( !array_key_exists( $language, $this->loaded_languages  ) )
        {
          $lang_class_name = $language . "_Language";
          $this->loaded_languages[ $language ] = new $lang_class_name;
        }

      return $this->loaded_languages[ $language ]->find_translation( $string, $this->return_empty );
    }

  function get_preferred_browser_language()
    {
      global $HTTP_ACCEPT_LANGUAGE;

      $known_languages = get_known_languages();

      $regexp_languages = "";
      foreach( $known_languages as $lang )
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

      return 'C';
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
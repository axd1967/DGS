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

  function Translator( $lang = null )
    {
      $this->loaded_languages = array();
      $this->return_empty = false;
      $this->change_language( $lang );
    }

  function change_language( $lang = null )
    {
      global $known_languages;

      if( !is_null( $lang ) and $lang != 'C' )
        {
          if( is_object( $lang ) )
            $this->current_language = $lang;
          else if( is_string( $lang ) )
            {
              list($lang_c,$charset) = explode( '.', $lang, 2 );
              if( is_null( $charset ) )
                {
                  $langs = $known_languages->get_lang( $lang_c );
                  $this->current_language = $langs[0];
                }
              else
                {
                  $this->current_language = $known_languages->get_lang( $lang_c, $charset );
                }
            }
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

  function create_lang_name( $language )
    {
      return $language->lang_code . "_" . str_replace('-','_',$language->charset);
    }

  function create_class_name( $language )
    {
      return Translator::create_lang_name( $language ) . "_Language";
    }

  function get_lang_name()
    {
      return $this->create_lang_name( $this->current_language );
    }

  function get_class_name()
    {
      return $this->create_class_name( $this->current_language );
    }

  function &get_language()
    {
      global $HOSTBASE;

      if( strcmp( $this->current_language->lang_code, 'C' ) == 0 )
        {
          return false;
        }

      $lang_name = $this->create_lang_name($this->current_language);
      if( !array_key_exists( $lang_name, $this->loaded_languages  ) )
        {
          $lang_class_name = $this->create_class_name($this->current_language);

          $file = "translations/" . $lang_name . ".php";

          if( @is_readable( $file ) )
             include_once $file;
          else if( @is_readable( "../$file" ) )
             include_once "../$file";

          $this->loaded_languages[ $lang_name ] = new $lang_class_name;
        }

      return $this->loaded_languages[ $lang_name ];
    }

  function translate( $string )
    {
      $language =& $this->get_language();

      if( $language === false )
        return $string;

      return $language->find_translation( $string, $this->return_empty );
    }

  function get_lang_full_name()
    {
      $language =& $this->get_language();

      if( $language === false )
        return '';

      return $language->full_name;
    }

  function get_last_updated()
    {
      $language =& $this->get_language();

      if( $language === false )
        return -1;

      return $language->last_updated;
    }

  function get_preferred_browser_language()
    {
      global $known_languages, $HTTP_ACCEPT_LANGUAGE, $HTTP_ACCEPT_CHARSET;

      $known_list = $known_languages->get_lang_codes();

      $regexp_languages = "";
      foreach( $known_list as $lang )
        {
          if( empty( $regexp_languages ) )
            $regexp_languages = "($lang";
          else
            $regexp_languages .= "|$lang";
        }
      $regexp_languages .= ")";

      $found_lang = 'C';
      if( isset( $HTTP_ACCEPT_LANGUAGE ) )
        {
          $languages = explode( ", ", $HTTP_ACCEPT_LANGUAGE );

          foreach( $languages as $http_language )
            {
              if( ereg( $regexp_languages, $http_language, $found) )
                {
                  $found_lang = $found[1];
                  break;
                }
            }
        }

      if( strcmp( $found_lang, 'C' ) != 0 and isset( $HTTP_ACCEPT_CHARSET ) )
        {
          $cs_list = $known_languages->get_lang( $found_lang );

          if( empty( $cs_list ) )
            {
              $found_lang = 'C';
            }

          $regexp_charsets = "";
          foreach( $cs_list as $entry )
            {
              if( empty( $regexp_charsets ) )
                $regexp_charsets = "(" . $entry->charset;
              else
                $regexp_charsets .= "|" . $entry->$charset;
            }
          $regexp_charsets .= ")";

          $found_cs = $cs_list[0]->charset;
          $charsets = explode( ", ", $HTTP_ACCEPT_CHARSET );
          foreach( $charsets as $http_charset )
            if( ereg( $regexp_charsets, $http_charset, $found ) )
              {
                $found_cs = $found[1];
                break;
              }
        }

      if( strcmp( $found_lang, 'C' ) == 0 )
        return new LangEntry( 'C', 'No translation', 'iso-8859-1' );
      return $known_languages->get_lang($found_lang, $found_cs);
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

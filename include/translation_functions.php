<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

/*
    translation_functions.php is only included from std_functions.php
>>Info:
    If known_languages.php is missing, it will be automatically
    built in make_known_languages() ...
    called by include_all_translate_groups() ...
    only called by is_logged_in() ...
    so very soon.
*/
if( file_exists( "translations/known_languages.php") )
   include_once( "translations/known_languages.php" );


function T_($string)
{
   global $Tr;

   $s = @$Tr[$string];
   if( empty($s) )
      return $string;
   else
      return $s;
}

function include_all_translate_groups($player_row=null) //must be called from main dir
{
   global $TranslateGroups, $known_languages;

   if( !file_exists( "translations/known_languages.php") )
   {
      require_once( "include/make_translationfiles.php" );
      make_known_languages(); //must be called from main dir
      make_include_files(); //must be called from main dir
   }
   include_once( "translations/known_languages.php" );

   $TranslateGroups = array_unique($TranslateGroups);

   foreach( $TranslateGroups as $i => $group )
      include_translate_group($group, $player_row); //must be called from main dir
}

function include_translate_group($group, $player_row) //must be called from main dir
{
   global $language_used, $encoding_used, $Tr;

   if( isset( $language_used ) ) //from a previous call
      $language = $language_used;
   else
   {
      if( isset($player_row['Lang']) and $player_row['Lang'] !== 'C' )
         $language = $player_row['Lang'];
      else
         $language = get_preferred_browser_language();

      if( empty($language) )
         $language = 'en.iso-8859-1';
      //else call to get_preferred_browser_language() for each $group
   }


   if( empty($language) || strtolower($language)!='en.iso-8859-1' )
   { //replace 'To#2' and 'From#2'
   $filename = "translations/en.iso-8859-1" . '_' . $group . '.php';

   if( file_exists( $filename ) )
   {
      include_once( $filename );
   }
   }


   if( !empty($language) )
   {
      $filename = "translations/$language" . '_' . $group . '.php';

      if( file_exists( $filename ) )
      {
         include_once( $filename );
      }
      $language_used = $language;
      list(,$encoding_used) = explode('.', $language);
   }

}


function get_preferred_browser_language()
{
   global $known_languages;

   $accept_langcodes = explode( ',', @$_SERVER['HTTP_ACCEPT_LANGUAGE'] );
   $accept_charset = strtolower(trim(@$_SERVER['HTTP_ACCEPT_CHARSET']));

   $current_q_val = -1;
   $return_val = NULL;

   foreach( $accept_langcodes as $lang )
      {
         @list($lang, $q_val) = explode(';', trim($lang));
         $lang = substr(trim($lang), 0, 2);
         $q_val = preg_replace('/q=/i','', trim($q_val));
         if( empty($q_val) or !is_numeric($q_val) )
            $q_val = 1.0;

         if( $current_q_val >= $q_val )
            continue;

         if( !$lang or !array_key_exists($lang, $known_languages))
            continue;

         $current_q_val = $q_val;

         if( $accept_charset )
            foreach( $known_languages[$lang] as $charenc => $name )
            {
               if( strpos( $accept_charset, strtolower($charenc) ) !== false )
               {
                  $return_val = $lang . '.' . $charenc;
                  break;
               }
            }

         // No supporting encoding found. Take the first one anyway.
         reset($known_languages[$lang]);
         $return_val = $lang . '.' . key($known_languages[$lang]);
      }

   return $return_val;
}

function get_language_descriptions_translated()
{
   global $known_languages;

   $result = array();
   foreach( $known_languages as $twoletter => $array )
      {
         foreach( $array as $charenc => $lang_name )
            {
               $result[ $twoletter . "." . $charenc ] = T_($lang_name);
            }
      }
   return $result;
}

?>

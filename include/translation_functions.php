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

if( file_exists($base_path . "translations/known_languages.php") )
   include( $base_path . "translations/known_languages.php" );

function T_($string)
{
   global $Tr;

   $s = $Tr[$string];
   if( empty($s) )
      return $string;
   else
      return $s;
}

function include_all_translate_groups($player_row=null)
{
   global $TranslateGroups, $known_languages, $base_path, $time;

   if( !file_exists($base_path . "translations/known_languages.php") )
   {
      require_once( $base_path . "include/make_translationfiles.php" );
      make_known_languages();
      make_include_files();
      include_once( $base_path . "translations/known_languages.php" );
   }

   $TranslateGroups = array_unique($TranslateGroups);

   foreach( $TranslateGroups as $i => $group )
      include_translate_group($group, $player_row);
}

function include_translate_group($group, $player_row)
{
   global $HTTP_ACCEPT_LANGUAGE, $HTTP_ACCEPT_CHARSET,
      $language_used, $encoding_used, $base_path, $Tr;

   if( !isset( $language_used ) )
   {
      if( isset($player_row['Lang']) and $player_row['Lang'] !== 'C' )
         $language = $player_row['Lang'];
      else
         $language = get_preferred_browser_language();
   }
   else
      $language = $language_used;


   $filename = $base_path . "translations/en.iso-8859-1" . '_' . $group . '.php';

   if( file_exists( $filename ) )
   {
      include_once( $filename );
   }


   if( !empty($language) )
   {
      $filename = $base_path . "translations/$language" . '_' . $group . '.php';

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
   global $known_languages, $HTTP_ACCEPT_LANGUAGE, $HTTP_ACCEPT_CHARSET;

   $accept_langcodes = explode( ',', $HTTP_ACCEPT_LANGUAGE );

   $current_q_val = -1;
   $return_val = NULL;

   foreach( $accept_langcodes as $lang )
      {
         list($lang, $q_val) = explode(';', trim($lang));
         $lang = substr(trim($lang), 0, 2);
         $q_val = preg_replace('/q=/i','', trim($q_val));
         if( empty($q_val) or !is_numeric($q_val) )
            $q_val = 1.0;

         if( $current_q_val >= $q_val )
            continue;

         if( !$lang or !array_key_exists($lang, $known_languages))
            continue;

         $current_q_val = $q_val;

         foreach( $known_languages[$lang] as $enc => $name )
            {
               if( strpos(strtolower($HTTP_ACCEPT_CHARSET), $enc) !== false )
               {
                  $return_val = $lang . '.' . $enc;
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
   foreach( $known_languages as $entry => $array )
      {
         foreach( $array as $enc => $lang_name )
            {
               $result[ $entry . "." . $enc ] = T_($lang_name);
            }
      }
   return $result;
}

?>
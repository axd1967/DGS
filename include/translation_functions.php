<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
if ( !isset($known_languages) )
{
   if ( file_exists( "translations/known_languages.php") )
      include_once( "translations/known_languages.php" );
}


/* examples:
 * $known_languages = array(
 *    "en" => array( "iso-8859-1" => "English" ),
 *    "zh" => array( "utf-8" => "Chinese (Traditional)" ),
 *    "zh-cn" => array( "utf-8" => "Chinese (Simplified)" ),
 *    "jp" => array( "utf-8" => "Japanese" ) );
 * $row['Translator'] = 'en.iso-8859-1,zh-cn.utf-8,jp.utf-8';
 * get_language_descriptions_translated() : array(
 *    "en.iso-8859-1" => "English",
 *    "zh-cn.utf-8" => "Chinese (Simplified)",
 *    "jp.utf-8" => "Japanese" );
 */


function T_($string)
{
   global $Tr;
   $s = @$Tr[$string];
   if ( (string)$s != '' )
      return $s;

   global $language_used;
   if ( $language_used == 'N' )
      return '<span class=NativeText>'.$string.'</span>';

   //if you need a '#' which is removed, end the string with a '#'
   return strip_translation_label($string);
}

function strip_translation_label( $string )
{
   return preg_replace('%(.)#[_0-9A-Za-z]*$%', '\\1', $string); // \w (=_0-9a-z) is locale-dependent
}

//if $player_row is absent, use the browser default settings
//called by is_logged_in()
function include_all_translate_groups($player_row=null) //must be called from main dir
{
   global $TranslateGroups, $known_languages;

   if ( !isset($known_languages) )
   {
      if ( !file_exists( "translations/known_languages.php") )
      {
         require_once 'include/make_translationfiles.php';
         make_known_languages(); //must be called from main dir
         //reload the globals, but already done by make_known_languages()
         //include( "translations/known_languages.php");
         make_include_files(); //must be called from main dir
      }
   }

   $TranslateGroups = array_unique($TranslateGroups);

   $language = recover_language( $player_row); //must be called from main dir
   if ( $language == 'N' )
      return;

   foreach ( $TranslateGroups as $group )
   {
      include_translate_group($group, $language); //must be called from main dir
   }
}

//called by include_all_translate_groups()
function include_translate_group($group, $language='') //must be called from main dir
{
   global $Tr; //$Tr is modified by the include_once( $filename );

   //preload 'To#2' and 'From#2' if missing in the language
   if ( strtolower($language) != LANG_DEF_LOAD )
   {
      $filename = "translations/".LANG_DEF_LOAD."_${group}.php";

      if ( file_exists( $filename ) )
      {
         include_once( $filename );
      }
   }

   if ( !empty($language) )
   {
      $filename = "translations/${language}_${group}.php";

      if ( file_exists( $filename ) )
      {
         include_once( $filename );
      }
   }
}


function get_language_descriptions_translated( $keep_english=false)
{
   global $known_languages;

   $result = array();
   foreach ( $known_languages as $browsercode => $array )
   {
      foreach ( $array as $charenc => $langname )
      {
         $result[ $browsercode . LANG_CHARSET_CHAR . $charenc ] =
                     ( $keep_english ?$langname :T_($langname) );
      }
   }
   return $result;
}


function language_exists( $browsercode, $charenc='', $langname='' )
{
   global $known_languages;

   if ( empty($browsercode) )
      return false;
   if ( empty($charenc) )
      @list($browsercode,$charenc) = explode( LANG_CHARSET_CHAR, $browsercode, 2);

   if ( !array_key_exists( $browsercode , $known_languages ) )
      return false;

   $langs = $known_languages[$browsercode];
   if ( !empty($charenc) && array_key_exists( $charenc, $langs ) )
      return $langs[$charenc] . ' (' . $browsercode . LANG_CHARSET_CHAR . $charenc . ')';;

   $langname= strtolower( $langname);
   $revlangs= array_change_key_case( array_flip($langs), CASE_LOWER);
   if ( !empty($langname) && array_key_exists( $langname, $revlangs) )
   {
      $charenc= $revlangs[$langname];
      return $langs[$charenc] . ' (' . $browsercode . LANG_CHARSET_CHAR . $charenc . ')';
   }

   return false;
}//language_exists

function get_translation_group( $group )
{
   $row = mysql_single_fetch( "get_translation_group($group)",
            "SELECT ID FROM TranslationGroups WHERE Groupname='" . mysql_addslashes($group) . "' LIMIT 1" )
      or error('internal_error', "get_translation_group.2($group)");
   return $row['ID'];
}

?>

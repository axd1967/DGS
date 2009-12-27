<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Languages";

/*!
 * \file languages.php
 *
 * \brief list of languages
 *
 * Spoken languages:
 * http://en.wikipedia.org/wiki/List_of_official_languages
 */

// use lazy-init to assure, that translation-language has been initialized !!
$ARR_GLOBALS_LANGUAGES = array();

/*! \brief Returns language-text or all languages (if code=null). */
function getLanguageText( $code=null )
{
   // lazy-init of texts
   $key = 'LANGUAGES';
   if( !isset($ARR_GLOBALS_LANGUAGES[$key]) )
   {
      // index => language-text
      // NOTE: index must be unique and in range (1..62), index added in array for clarity
      // NOTE: stored in two signed ints as bitmask (Players.KnownLanguages1/2)
      $arr = array(
          1 => T_('English#lang'),
          2 => T_('French#lang'),
          3 => T_('Arabic#lang'),
          4 => T_('Spanish (Catalan)#lang'),
          5 => T_('Spanish (Basque)#lang'),
          6 => T_('Russian#lang'),
          7 => T_('Portuguese#lang'),
          8 => T_('German#lang'),
          9 => T_('Dutch#lang'),
         10 => T_('Italian#lang'),
         11 => T_('Chinese (Cantonese)#lang'),
         12 => T_('Chinese (Mandarin)#lang'),
         13 => T_('Czech#lang'),
         14 => T_('Danish#lang'),
         15 => T_('Finnish#lang'),
         16 => T_('Greek#lang'),
         17 => T_('Hebrew#lang'),
         18 => T_('Hungarian#lang'),
         19 => T_('Japanese#lang'),
         20 => T_('Korean#lang'),
         21 => T_('Norwegian#lang'),
         22 => T_('Polish#lang'),
         23 => T_('Romanian#lang'),
         24 => T_('Serbian#lang'),
         25 => T_('Slovak#lang'),
         26 => T_('Swedish#lang'),
         27 => T_('Thai#lang'),
         28 => T_('Turkish#lang'),
         29 => T_('Ukrainan#lang'),
         30 => T_('Vietnamese#lang'),
         31 => T_('Esperanto#lang'),
         32 => T_('Malay#lang'),
         33 => T_('Georgian#lang'),
         34 => T_('Irish Gaelic#lang'),
         35 => T_('Indonesian#lang'),
         36 => T_('Hindi#lang'),
         37 => T_('Tamil#lang'),
         38 => T_('Urdu#lang'),
         39 => T_('Bengali#lang'),
         40 => T_('Bosnian#lang'),
         41 => T_('Croatian#lang'),
         42 => T_('Slovenian#lang'),
         43 => T_('Afrikaans#lang'),
         44 => T_('Swahili#lang'),
         45 => T_('Uzbek#lang'),
         46 => T_('Mongolian#lang'),
         47 => T_('Albanian#lang'),
         48 => T_('Armenian#lang'),
         49 => T_('Bulgarian#lang'),
         50 => T_('Persian#lang'),
         51 => T_('Pashto#lang'),
      );
      $ARR_GLOBALS_LANGUAGES[$key] = $arr;
   }

   if( is_null($code) )
      return $ARR_GLOBALS_LANGUAGES[$key];

   if( !isset($ARR_GLOBALS_LANGUAGES[$key][$code]) )
      error('invalid_args', "Countries.getLanguageText($code)");
   return $ARR_GLOBALS_LANGUAGES[$key][$code];
}

?>

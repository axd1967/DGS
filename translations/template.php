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

/*
 * This file is just a template for the translations and shouldn't be
 * included.
 *
 * How to add a new language:
 *
 * 1) Copy this file to a file with the name 'lang.php' where
 *    'lang' is the two-letter language code of the new language.
 *
 * 2) In the new file, remove this comment, change all occurances
 *    of 'tmpl' to the two-letter language code of the new language
 *    and change 'Template' to the description of your language
 *    (e.g. 'English')
 *
 * 3) Update the database so that all who will be translating the language
 *    has the two-letter code in their 'Translator'-column in the format
 *    'lang1,lang2,lang3'. You can naturally hav as many languages as you
 *    want in that string.
 */

add_to_known_languages( "tmpl", "Template" );

class tmpl_Language extends Language
{
  function tmpl_Language()
    {
      $this->translated_strings =
        array(
             );
    }
};

?>

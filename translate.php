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

require( "include/std_functions.php" );
require( "include/form_functions.php" );
require( "include/translation_info.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $translator_array = explode(',', $player_row['Translator']);

  if( !$translate_lang )
    {
      if( empty( $player_row['Translator'] ) )
        {
          error("not_translator");
        }
      elseif( count( $translator_array ) == 1 )
        {
          $translate_lang = $translator_array[0];
          $lang_choice = false;
        }
      elseif( count( $translator_array ) > 1 )
        {
          $lang_choice = true;
        }
    }

  start_page(T_("Translate"), true, $logged_in, $player_row);

   echo '<CENTER>
<table border="2">
<tr><td>
<CENTER>
<B><h3><font color=' . $h3_color . '>Read this before translating:</font></h3></B><p>
</CENTER>
When translating you should keep in mind the following things:
<ul>
  <li> Since the actual translation is actually changed when you press on
       \'Update translation\' it is good that you check that the new translations
       look good before you go on to translating the next group.
  <li> If you for some reason want to have a phrase untranslated, just
       leave that translation blank and the English phrase will be used.
  <li> In some places there is a percent-character followed by some characters. 
       This is a special place where the program might put some data in.
       <br>
       Example: \'I am %s\' might be displayed as \'I am Erik\' or whatever the name is.
       <br>
       If you want to change order of these you can use \'%1$s\' to place to make
       sure that you get the first argument and \'%2$s\' for the second etc.
       <br>
       <a href="http://www.php.net/manual/en/function.sprintf.php">You can read more here</a>
  <li> In some strings there are html code. If you don\'t know how to use html code,
       just copy the original code and change the real language. If you are unsure
       contact the support.
  <li> If you want to change the html code in some way in the translation, keep in mind
       that the code shall conform to the standard layout of Dragon.
</ul>
</td></tr>
</table>
</CENTER>
<p>
';

  if( $lang_choice )
    {
      echo "<CENTER>\n";
      echo "<B><h3><font color=$h3_color>Select language to translate to:</font></h3></B><p>\n";
      echo form_start( 'selectlangform', 'translate.php', 'GET' );
      $languages = get_known_languages_with_full_names();
      $vals = array();
      foreach( $translator_array as $lang )
        {
          if( array_key_exists( $lang, $languages ) )
            $vals[$lang] = $languages[$lang];
          else
            $vals[$lang] = $lang;
        }

      echo form_insert_row( 'SELECTBOX', 'translate_lang', 1, $vals, '', false,
                            'HIDDEN', 'group', 'Common',
                            'SUBMITBUTTON', 'cl', 'Select' );
      echo form_end();
      echo "</CENTER>\n";
    }
  else
    {
      if( !in_array( $translate_lang, get_known_languages() ) )
        error('no_such_translation_language');

      if( !in_array( $translate_lang, $translator_array ) )
        error('not_correct_transl_lang');

      if( !$group )
        $group = 'Common';

      $old_lang = $the_translator->current_language;
      $the_translator->change_language( $translate_lang );
      $the_translator->set_return_empty();

      echo "<CENTER>\n";
      echo "<B><h3><font color=$h3_color>Translate the following strings:</font></B></h3><p>\n";
      echo form_start( 'translateform', 'update_translation.php', 'POST' );

      $counter = 0;
      foreach( $translation_info as $string => $info )
        {
          $counter++;
          if( in_array( $group, $info['Groups'] ) )
            {
              /* TODO: change the character encoding of htmlentities() when necessary*/
              echo form_insert_row( 'TEXT', nl2br( htmlentities($string,ENT_COMPAT) ),
                                    'TD',
                                    'TEXTAREA', "transl$counter", 50, 5, T_($string) );
            }
        }

      $the_translator->change_language( $old_lang );
      $the_translator->set_return_empty( false );

      echo form_insert_row( 'SPACE' );
      echo form_insert_row( 'HEADER', 'Groups' );
      echo form_insert_row( 'HIDDEN', 'translate_lang', $translate_lang );
      echo form_insert_row( 'DESCRIPTION', 'Change to group',
                            'SELECTBOX', 'group', 1,
                            array_value_to_key_and_value( $translation_groups ),
                            $group, false );

      echo form_insert_row( 'SPACE' );

      echo "</table>\n";
      echo "<table width=\"50%\">\n";
      echo "  <tr>\n" .
        "    <td align=\"center\">" .
        form_insert_submit_button( 'update', 'Update translation' ) . "</td>\n" .
        "    <td align=\"center\">" .
        form_insert_submit_button( 'just_change', 'Just change group' ) . "</td>\n" .
        "  </tr>\n";
      echo form_end();

    }

  end_page(false);
}

?>

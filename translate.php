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
  <li> You can make a lot of changes without actually submitting them to be used
       on the website. Just click on \'Change translation\' to do this. When
       you want the translation to be used on Dragon, click on
       \Apply translation changes to Dragon\'.
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
      $langchoice_form = new Form( 'selectlangform', 'translate.php', FORM_GET );
      $langchoice_form->add_row( array( 'HEADER', 'Select language to translate to' ) );
      $languages = $known_languages->get_descriptions_translated();
      $vals = array();
      foreach( $languages as $lang => $description )
        {
          list( $lc, $cs ) = explode( '.', $lang, 2 );
          if( in_array( $lang, $translator_array ) or
              in_array( $lc, $translator_array ) )
            $vals[$lang] = $description;
        }

      $langchoice_form->add_row( array( 'SELECTBOX', 'translate_lang', 1, $vals, '', false,
                                        'HIDDEN', 'group', 'Common',
                                        'SUBMITBUTTON', 'cl', 'Select' ) );
      $langchoice_form->echo_string();
      echo "</CENTER>\n";
    }
  else
    {
      if( !in_array( $translate_lang, $known_languages->get_lang_codes_with_charsets() ) )
        error('no_such_translation_language');

      list( $tlc, $tcs ) = explode( '.', $translate_lang, 2 );
      if( !in_array( $translate_lang, $translator_array ) and
          !in_array( $tlc, $translator_array ) )
        error('not_correct_transl_language');

      if( !$group or !in_array( $group, $translation_groups ) )
        $group = 'Common';

      $untranslated = false;
      if( strcmp( $group, 'Untranslated phrases' ) == 0 )
        $untranslated = true;

      $old_lang = $the_translator->current_language;
      $the_translator->change_language( $translate_lang );
      $the_translator->set_return_empty();

      $last_updated = $the_translator->get_last_updated();
      $query = "SELECT * FROM Translationlog WHERE Language='$translate_lang' ";
      if( $last_updated > 0 )
        $query .= "AND Date > FROM_UNIXTIME($last_updated) ";
      $query .= "ORDER BY Date,ID";

      $result = mysql_query($query);
      $new_translations = array();
      while( $row = mysql_fetch_array( $result ) )
        $new_translations[ $row['CString'] ] = $row['Translation'];

      echo "<CENTER>\n";

      $translate_form = new Form( 'translateform', 'update_translation.php', FORM_POST );
      $translate_form->add_row( array( 'HEADER', 'Translate the following strings' ) );

      $counter = 0;
      $nr_messages = 0;
      foreach( $translation_info as $string => $info )
        {
          $counter++;
          if( in_array( $group, $info['Groups'] ) || $untranslated )
            {
              $translation = '';
              if( array_key_exists( $string, $new_translations ) )
                $translation = $new_translations[$string];
              else
                $translation = T_($string);

              if( $untranslated &&
                  ($nr_messages > 50 || !empty($translation)) )
                {
                  continue;
                }
              else
                {
                  $nr_messages++;
                }

              $hsize = 60;
              $vsize = intval(floor(min( max( 2,
                                              strlen( $string ) / $hsize + 2,
                                              substr_count( $string, "\n" ) + 2 ),
                                         12 )));
              $translate_form->add_row( array( 'TEXT', nl2br( htmlspecialchars($string,
                                                                    ENT_QUOTES,
                                                                    'iso-8859-1' ) ),
                                               'TD',
                                               'TEXTAREA', "transl$counter",
                                               $hsize, $vsize,
                                               htmlspecialchars($translation,
                                                                ENT_QUOTES,
                                                                $CHARACTER_ENCODINGS[$translate_lang]) ) );
            }
        }

      $the_translator->change_language( $old_lang );
      $the_translator->set_return_empty( false );

      if( $untranslated && $nr_messages >= 50 )
        {
          $translate_form->add_row( array( 'SPACE' ) );
          $translate_form->add_row( array( 'OWNHTML',
                                           "<tr>\n" .
                                           "  <td align=\"center\" colspan=\"2\" " .
                                           "style=\"  border: solid; border-color: " .
                                           "#ff6666; border-width: 2pt;\">\n" .
                                           "    Note that only the first fifty untranslated " .
                                           "messages are displayed, so that there won't be " .
                                           "too many messages at the same time.\n" .
                                           "  </td>\n" .
                                           "</tr>\n" ) );

        }

      $translate_form->add_row( array( 'SPACE' ) );
      $translate_form->add_row( array( 'HEADER', 'Groups' ) );
      $translate_form->add_row( array( 'HIDDEN', 'translate_lang', $translate_lang ) );
      $translate_form->add_row( array( 'DESCRIPTION', 'Change to group',
                                       'SELECTBOX', 'group', 1,
                                       array_value_to_key_and_value( $translation_groups ),
                                       $group, false ) );
      $translate_form->add_row( array( 'SPACE' ) );

      $translate_form->add_row( array( 'OWNHTML',
                                       "</table>\n" .
                                       "<table width=\"100%\">\n" .
                                       "  <tr>\n" .
                                       "    <td align=\"center\">" .
                                       $translate_form->print_insert_submit_button( 'just_group', 'Just change group' ) . "</td>\n" .
                                       "    <td align=\"center\">" .
                                       $translate_form->print_insert_submit_button( 'change', 'Change translation' ) . "</td>\n" .
                                       "    <td align=\"center\">" .
                                       $translate_form->print_insert_submit_button( 'apply_changes', 'Apply translation changes to Dragon' ) . "</td>\n" .
                                       "  </tr>\n" ) );
      $translate_form->echo_string();

    }

  end_page();
}

?>

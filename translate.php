<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony, Ragnar Ouchterlony

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

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/make_translationfiles.php" );

{
   $translation_groups =
      array( 'Common', 'Start', 'Game', 'Messages', 'Users',
             'Docs', 'FAQ', 'Admin', 'Error', 'Countries', 'Untranslated phrases' );


  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");


  $translator_set = @$player_row['Translator'];
  if( !$translator_set )
    error("not_translator");

  $translator_array = explode(',', $translator_set);

  $group = @$_REQUEST['group'];

  if( !$group or !in_array( $group, $translation_groups ) )
     $group = 'Untranslated phrases';

  $untranslated = ($group === 'Untranslated phrases');

  $translate_lang = @$_REQUEST['translate_lang'];

  $lang_choice = false;
  if( !$translate_lang )
    {
      if( count( $translator_array ) == 1 )
        {
          $translate_lang = $translator_array[0];
        }
      elseif( count( $translator_array ) > 1 )
        {
          $lang_choice = true;
        }
    }

  $info_box = '<CENTER>
<table border="2">
<tr><td>
<CENTER>
<B><h3><font color=' . $h3_color . '>Read this before translating:</font></h3></B><p>
</CENTER>
When translating you should keep in mind the following things:
<ul>
  <li> If a translated word is the same as in english, leave it blank and click
       the \'same\' box to the right.
  <li> In some places there is a percent-character followed by some characters.
       This is a special place where the program might put some data in.
       <br>
       Example: \'with %s extra per move\' might be displayed as \'with 2 hours extra per move\'.
       <br>
       If you want to change order of these you can use \'%1$s\' to place to make
       sure that you get the first argument and \'%2$s\' for the second etc.
       <br>
       <a href="http://www.php.net/manual/en/function.sprintf.php">You can read more here</a>
  <li> In some strings there are html code. If you don\'t know how to use html code,
       just copy the original code and change the real language. If you are unsure
       you can use the translator forum to get help.
  <li> If you want to change the html code in some way in the translation, keep in mind
       that the code shall conform to the standard layout of Dragon.
  <li> If a word ends with #2, for example \'To#2\', this means a second word with the same
       spelling, so just ignore the #2 part when translating. This is necessary since in some
       languages \'to\' is translated differently depending on the context (e.g., \'bis\' or
       \'an\' in german).
</ul>
</td></tr>
</table>
</CENTER>
<p>
';

  if( $lang_choice )
  {
     start_page(T_("Translate"), true, $logged_in, $player_row);
     echo $info_box;

     echo "<CENTER>\n";
     $langchoice_form = new Form( 'selectlangform', 'translate.php', FORM_GET );
     $langchoice_form->add_row( array( 'HEADER', 'Select language to translate to' ) );
     $languages = get_language_descriptions_translated();
     $vals = array();
     foreach( $languages as $lang => $description )
        {
           list( $lc, $cs ) = explode( '.', $lang, 2 );
           if( in_array( $lang, $translator_array ) or
               in_array( $lc, $translator_array ) )
              $vals[$lang] = $description;
        }

     $langchoice_form->add_row( array( 'SELECTBOX', 'translate_lang', 1, $vals, '', false,
                                       'HIDDEN', 'group', $group,
                                       'SUBMITBUTTON', 'cl', 'Select' ) );
     $langchoice_form->echo_string();
     echo "</CENTER>\n";
  }
  else
  {
     if( !in_array( $translate_lang, $translator_array ) )
        error('not_correct_transl_language');

      $result = translations_query( $translate_lang, $untranslated, $group )
                or die(mysql_error());
      $numrows = mysql_num_rows($result);
      if( $numrows == 0 and !$untranslated )
         error('translation_bad_language_or_group');

      start_page(T_("Translate"), true, $logged_in, $player_row);
      echo $info_box;

      echo "<CENTER>\n";

      $translate_form = new Form( 'translateform', 'update_translation.php', FORM_POST );
      $translate_form->add_row( array('HEADER', 'Translate the following strings' ) );

      $string = '';
      foreach( $known_languages as $entry => $array )
      {
         foreach( $array as $charenc => $lang_name )
         {
            if( $entry . "." . $charenc == $translate_lang)
               $string .= ",$lang_name";
         }
      }
      if( $string )
         $string = substr( $string, 1);
      else
         $string = $translate_lang;

      $translate_form->add_row( array( 'CELL', 99, 'align="center"', 'TEXT', "- $string -" ) );

      list(,$translate_encoding) = explode('.', $translate_lang);

      while( $row = mysql_fetch_array($result) )
      {
         $string = $row['Original'];
         $hsize = 60;
         $vsize = intval(floor(min( max( 2,
                                         strlen( $string ) / $hsize + 2,
                                         substr_count( $string, "\n" ) + 2 ),
                                    12 )));
         $form_row = array( 'TEXT', nl2br( textarea_safe($string, 'iso-8859-1' ) ),
                            'TD',
                            'TEXTAREA', "transl" . $row['Original_ID'],
                              $hsize, $vsize,
                              textarea_safe( $row['Text'], $translate_encoding),
                            'CELL', 1, 'nowrap',
                            'CHECKBOX', 'same' . $row['Original_ID'], 'Y',
                              'untranslated', $row['Text'] === '',
                           ) ;
         if( $untranslated )
            array_push( $form_row,
                            'BR',
                            'CHECKBOX', 'unch' . $row['Original_ID'], 'Y',
                              'unchanged', false ) ;

         $translate_form->add_row( $form_row, -1, false ) ;

         $translate_form->add_row( array( 'HR' ) );
      }


      if( $untranslated and $numrows == 50 )
      {
         $translate_form->add_row( array( 'SPACE' ) );
         $translate_form->add_row( array( 'OWNHTML',
                                          "  <td align=\"center\" colspan=\"3\" " .
                                          "style=\"  border: solid; border-color: " .
                                          "#ff6666; border-width: 2pt;\">\n" .
                                          "    Note that only the first fifty untranslated " .
                                          "messages are displayed, so that there won't be " .
                                          "too many messages at the same time.\n" .
                                          "  </td>\n" ) );
      }

      $translate_form->add_row( array( 'OWNHTML',
                                       "  <table width=\"100%\"><tr>\n"));
      $translate_form->add_row( array( 'HEADER', 'Groups') );
      $translate_form->add_row( array( 'DESCRIPTION', 'Change to group',
                                       'HIDDEN', 'translate_lang', $translate_lang,
                                       'HIDDEN', 'group', $group,
                                       'SELECTBOX', 'newgroup', 1,
                                       array_value_to_key_and_value( $translation_groups ),
                                       $group, false ) );
      $translate_form->add_row( array( 'SPACE' ) );

      $translate_form->add_row( array( 'OWNHTML',
                                       "    <td align=\"right\" width=\"50%\">" .
                                       $translate_form->print_insert_submit_button( 'just_group', 'Just change group' ) . "</td>\n" .
                                       "    <td align=\"left\">" .
                                       $translate_form->print_insert_submit_button( 'apply_changes', 'Apply translation changes to Dragon' ) . "</td>\n" .
                                       "  </tr></table>\n" ) );

      $translate_form->echo_string();
  }

  end_page();
}

?>

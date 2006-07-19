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

/* The code in this file is written by Ragnar Ouchterlony */

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );


{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_TRANSLATORS) )
    error("adminlevel_too_low");

  start_page(T_('Translator admin'), true, $logged_in, $player_row);

  echo "<center>";

  $translator_form = new Form( 'translatorform', 'admin_do_translators.php', FORM_POST );

  /* Add language for translation */
// International languages code (ISO 639):
// http://www.loc.gov/standards/iso639-2/englangn.html

      $twoletter = get_request_arg('twoletter');
      $charenc = get_request_arg('charenc');
      $langname = get_request_arg('langname');

  $translator_form->add_row( array( 'HEADER', T_('Add language for translation') ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_('Two-letter language code'),
                                    'TEXTINPUT', 'twoletter', 30, 10, $twoletter ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_('Language name (i.e. English)'),
                                    'TEXTINPUT', 'langname', 30, 50, $langname ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_("Character encoding (i.e. 'iso-8859-1')"),
                                    'TEXTINPUT', 'charenc', 30, 50, $charenc ) );
  $translator_form->add_row( array(
      'SUBMITBUTTON', 'addlanguage', T_('Add language'),
      ) );


  /* Set translator privileges for user */
  $translator_form->add_row( array( 'HEADER', T_('Set translator privileges for user') ) );

  $langs = get_language_descriptions_translated();
  asort($langs);

   $transluser = get_request_arg('transluser');

   $translator_form->add_row( array(
      'DESCRIPTION', T_('User to set privileges for (use the userid)'),
      'TEXTINPUT', 'transluser', 30, 80, $transluser,
      ) );

   $translator_array = array();
   $transluser_langs= array();
   if( !empty($transluser) )
   {
      $userrow = mysql_single_fetch(
             "SELECT Translator FROM Players"
            ." WHERE Handle='".addslashes($transluser)."'" );

      if( $userrow )
      {
         if( !empty($userrow['Translator']) )
           $translator_array = explode( LANG_TRANSL_CHAR, $userrow['Translator'] );

         foreach( $translator_array as $value )
         {
            if( isset($langs[$value]) )
               $transluser_langs[$value] = $langs[$value];
         }
         asort($transluser_langs);
         $str= '';
         foreach( $transluser_langs as $value => $lang_name )
         {
            $str.= $lang_name . ' (' . $value . ')<BR>';
         }
         $translator_form->add_row( array(
            'DESCRIPTION', T_('Allowed to translate'),
            'TEXT', $str,
            ) );
      }
   }
   //else
   {
      $translator_form->add_row( array(
         'SUBMITBUTTON', 'showpriv', T_('Show actual user privileges'),
         ) );
   }


  $translator_form->add_row( array(
      'DESCRIPTION', T_('Select language to make user translator for that language.'),
      'SELECTBOX', 'transladdlang', 1, $langs, array(), false,
      ) );
  $translator_form->add_row( array(
      'SUBMITBUTTON', 'transladd', T_('Add language for translator'),
      ) );


  $translator_form->add_row( array(
      'DESCRIPTION', T_('Select the languages the user should be allowed to translate'),
      'SELECTBOX', 'transllang[]', 7, //transllang[] is a MULTIPLE select box
                  $langs, $transluser_langs, true,
      ) );
  $translator_form->add_row( array(
      'SUBMITBUTTON', 'translpriv', T_('Set user privileges'),
      ) );

  $translator_form->echo_string();

  echo "</center>";
  end_page();
}
?>

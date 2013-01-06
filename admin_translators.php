<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival

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

/* The code in this file is written by Ragnar Ouchterlony */

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );


{
   // NOTE: using page: admin_do_translators.php

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'admin_translators');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_translators');
   if( !(@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
      error('adminlevel_too_low', 'admin_translators');


/*
>>> compatibility with get_preferred_browser_language() needed
     http://www.w3.org/International/questions/qa-lang-priorities

>>> Obsolete (old 2 letters language code restriction -> $twoletter):
    International languages code (ISO 639-1):
     http://www.loc.gov/standards/iso639-2/englangn.html
>>> Replaced by (at least 2 letters code -> $browsercode):
    IANA Language Subtag Registry
     http://www.iana.org/assignments/language-subtag-registry
     http://www.iana.org/assignments/language-tags
  but this seems to be just a recommendation not already followed by browsers
  or servers. The main problem is with get_preferred_browser_language().

 More readings:
    Available language codes:
     http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
    W3C languages, charsets and encodings index:
     http://www.w3.org/International/resource-index
    Language tags in XTML (XML meta-tags code)
     http://www.w3.org/International/tutorials/tutorial-lang/
     http://www.w3.org/International/articles/language-tags/Overview.en.php
    Tags for the Identification of Languages (RFC 3066, RFC 4646)
     http://www.apps.ietf.org/rfc/rfc3066.html
     http://www.rfc-editor.org/rfc/rfc4646.txt

 If, a day, the whole site is UTF-8, re-check this site:
     http://www.w3.org/International/tutorials/tutorial-char-enc/en/all.html#Slide0240
*/

   $browsercode = trim(get_request_arg('browsercode'));
   if( !$browsercode ) //twoletter kept for old URL compatibility
      $browsercode = trim(get_request_arg('twoletter'));
   $charenc = trim(get_request_arg('charenc'));
   $langname = trim(get_request_arg('langname'));
   $showlanguages = @$_REQUEST['showlanguages'] ?'1' :'';
   $transluser = trim(get_request_arg('transluser'));
   $transladdlang = get_request_arg('transladdlang');

   if( !$charenc )
      $charenc = 'UTF-8';

   start_page(T_('Translator admin'), true, $logged_in, $player_row);

   echo "<center>";

   $translator_form = new Form( 'translatorform', 'admin_do_translators.php', FORM_POST );

   /* Add language for translation */
   $translator_form->add_row( array( 'HEADER', T_('Add language for translation') ) );
   //$translator_form->add_row( array( 'DESCRIPTION', T_//('Two-letter language code (see ISO 639-1)'),
   $translator_form->add_row( array( 'DESCRIPTION', T_('Language code (i.e. XML meta-tags code, e.g. zh-cn)'),
                                     'TEXTINPUT', 'browsercode', 30, 10, $browsercode ) );
   $translator_form->add_row( array( 'DESCRIPTION', T_('English language name (e.g. French)'),
                                     'TEXTINPUT', 'langname', 30, 50, $langname ) );
   $translator_form->add_row( array( 'DESCRIPTION', T_('Character encoding (e.g. utf-8)'),
                                     'TEXTINPUT', 'charenc', 30, 50, $charenc ) );
   $translator_form->add_row( array(
         'SUBMITBUTTON', 'showlanguages', T_('Known languages'),
         'SUBMITBUTTON', 'addlanguage', T_('Add language'),
      ));
   if( $showlanguages )
   {
      $str= '';
      foreach( $known_languages as $bcod => $value )
      {
         foreach( $value as $cset => $lnam )
            $str.= $lnam . ' (' . $bcod .'.'. $cset . ')<BR>';
      }
      $translator_form->add_row( array(
            'DESCRIPTION', T_('Known languages'),
            'TEXT', $str,
         ));
   }


  /* Set translator privileges for user */
   $translator_form->add_row( array( 'HEADER', T_('Set translator privileges for user') ) );

   $langs = get_language_descriptions_translated(true);
   //it's not obvious that this sort on /*T_*/"translated" strings will always give a good result:
   asort($langs);

  /* Show the privileges of the user */
   $translator_form->add_row( array(
         'DESCRIPTION', T_('User to set privileges for (use the userid)'),
         'TEXTINPUT', 'transluser', 30, 80, $transluser,
      ));

   $translator_array = array();
   $transluser_langs = array();
   if( !empty($transluser) )
   {
      $userrow = mysql_single_fetch( 'admin_translators.transluser',
             "SELECT Translator FROM Players"
            ." WHERE Handle='".mysql_addslashes($transluser)."'" );

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
         foreach( $transluser_langs as $value => $langname )
         {
            $str.= $langname . ' (' . $value . ')<BR>';
         }
         $translator_form->add_row( array(
               'DESCRIPTION', T_('Allowed to translate'),
               'TEXT', $str,
            ));
      }
   }
   //else
   {
      $translator_form->add_row( array(
            'SUBMITBUTTON', 'showpriv', T_('Show actual user privileges'),
         ));
   }


  /* Add a single language to the user */
   $translator_form->add_row( array(
         'DESCRIPTION', T_('Select language to make user translator for that language.'),
         'SELECTBOX', 'transladdlang', 1, $langs, array( $transladdlang), false,
      ));
   $translator_form->add_row( array(
         'SUBMITBUTTON', 'transladd', T_('Add language for translator'),
      ));


  /* Define the full set of languages of the user */
   $translator_form->add_row( array(
         'DESCRIPTION', T_('Select the languages the user should be allowed to translate'),
         //transllang[] is a MULTIPLE select box
         'SELECTBOX', 'transllang', 7, $langs, $transluser_langs, true,
      ));
   $translator_form->add_row( array(
         'SUBMITBUTTON', 'translpriv', T_('Set user privileges'),
      ));


  $translator_form->echo_string();

  echo "</center>";
  end_page();
}
?>

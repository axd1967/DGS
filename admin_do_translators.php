<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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


function lang_illegal( $str)
{
   return substr( $str
                , strcspn( $str, '*?\\/"\':;[]{}<>' //special filename chars
                        . LANG_TRANSL_CHAR.LANG_CHARSET_CHAR)
                , 1);
}

function retry_admin( $msg)
{
   if( $tmp = trim( $msg) )
   {
      $tmp = '?sysmsg='.urlencode($tmp);
      $sep = URI_AMP;
   }
   else
   {
      $tmp = '';
      $sep = '?';
   }

   foreach( array(
         'langname',
         'browsercode',
         'charenc',
         'showlanguages',
         'transluser',
         'transladdlang',
         ) as $arg )
   {
      global $$arg;
      if( isset($$arg) && (!is_string($$arg) or $$arg>'') )
      {
         $tmp.= $sep.$arg."=".urlencode($$arg);
         $sep = URI_AMP;
      }
   }

   jump_to("admin_translators.php" . $tmp);
}


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
      error('adminlevel_too_low');

/* Originally, the language code was a 2 letters code (like ISO 639-1).
   Because of language particularities within the same charset (like
   en-gb and en-us), the language code is now a "at least a 2 letters" code.
   Because of get_preferred_browser_language(), it must follow the code
   used by browsers, i.e. IANA Language Subtag Registry.
*/
   $browsercode = trim(get_request_arg('browsercode'));
   $charenc = trim(get_request_arg('charenc'));
   $langname = trim(get_request_arg('langname'));

   $showlanguages = @$_REQUEST['showlanguages'] ?'1' :'';
   $addlanguage = @$_REQUEST['addlanguage'] ?'1' :'';

   $transluser = get_request_arg('transluser');
   $showpriv = @$_REQUEST['showpriv'] ?'1' :'';

   $transladd = @$_REQUEST['transladd'] ?'1' :'';
   $transladdlang = trim(get_request_arg('transladdlang'));

   $translpriv = @$_REQUEST['translpriv'] ?'1' :'';
   //transllang[] is a MULTIPLE select box
   $transllang = get_request_arg('transllang');

   // Normalization for the array_key_exists() matchings
   $browsercode = strtolower($browsercode);
   $charenc = strtolower($charenc);
   $langname = ucfirst($langname); //ucfirst(strtolower($langname)); //ucwords()


   $msg = '';

   if( $showlanguages )
      retry_admin('');

   if( $addlanguage )
   {
      $tmp = lang_illegal( $browsercode.$langname.$charenc);
      if( $tmp )
         retry_admin( T_("Sorry, there was an illegal character in a language field.") . " ($tmp)");

      if( strlen( $browsercode ) < 2 || empty( $langname ) || empty( $charenc ) )
        retry_admin( T_("Sorry, there was a missing or incorrect field when adding a language."));

      $tmp= language_exists( $browsercode, $charenc, $langname );
      if( $tmp )
        retry_admin( T_("Sorry, the language you tried to add already exists.")
                     ."\n" . $tmp);


      $tmp = mysql_addslashes( $browsercode . LANG_CHARSET_CHAR . $charenc );
      mysql_query("INSERT INTO TranslationLanguages SET " .
                  "Name='" . mysql_addslashes($langname) . "', " .
                  "Language='$tmp'" )
         or error('mysql_query_failed','admin_do_translators.add.insert');

      make_known_languages(); //must be called from main dir

      //insert the name of language to be translated
      $row = mysql_single_fetch( 'admin_do_translators.add.find_group',
            "SELECT ID FROM TranslationGroups WHERE Groupname='Users'")
         or error('internal_error','admin_do_translators.add.find_group');

      $Group_ID = $row['ID'];

      $tmp = add_text_to_translate('admin_do_translators.add', $langname, $Group_ID);

      retry_admin( sprintf( T_("Added language %s with code %s and characterencoding %s.")
                                 , $langname, $browsercode, $charenc ));
   }

//-------------------
// Queries with a user:

   $old_langs = '';
   if( $showpriv or $transladd or $translpriv )
   {
      if( empty($transluser) )
         retry_admin( T_("Sorry, you must specify a user."));
      $row = mysql_single_fetch( 'admin_do_translators.user.find',
                    "SELECT Translator FROM Players"
                    ." WHERE Handle='".mysql_addslashes($transluser)."'" );
      if( !$row )
         retry_admin( T_("Sorry, I couldn't find this user."));
      if( !empty($row['Translator']) )
         $old_langs = $row['Translator'];
   }

   if( $showpriv )
      retry_admin('');

   $translator_array = array();
   $update_it = false;
   if( $transladd )
   {
      if( !empty($transladdlang) )
         if( !language_exists( $transladdlang ) )
            $transladdlang = '';
      if( empty($transladdlang) )
         retry_admin( T_("Sorry, you must specify existing languages."));

      if( $old_langs )
         $translator_array = explode( LANG_TRANSL_CHAR, $old_langs );

      if( in_array( $transladdlang, $translator_array) )
         retry_admin( sprintf( T_("User %s is already translator for language %s."),
                           $transluser, $transladdlang) );
      array_push( $translator_array, $transladdlang );

      $update_it = 'admin_t4';
      $msg = sprintf( T_("Added user %s as translator for language %s.")
                           , $transluser, $transladdlang );
   }
   else if( $translpriv )
   {
      if( is_array( $transllang ) )
         $translator_array = $transllang;

      $update_it = 'admin_t5';
      $msg = sprintf( T_("Changed translator privileges info for user %s.")
                           , $transluser );
   }

   if( $update_it )
   {
      $new_langs = implode( LANG_TRANSL_CHAR, array_unique($translator_array));

      if( $new_langs == $old_langs )
         retry_admin( $msg);

      mysql_query( "UPDATE Players SET Translator='$new_langs'"
                  ." WHERE Handle='".mysql_addslashes($transluser)."'" )
         or error('mysql_query_failed','admin_do_translators.user.update');

      if( mysql_affected_rows() != 1 )
         error('internal_error', $update_it);

      // Check result
      $tmp = mysql_single_fetch( 'admin_do_translators.user.translator',
                   "SELECT Translator FROM Players"
                   ." WHERE Handle='".mysql_addslashes($transluser)."'" );
      if( !$tmp )
         $update_it.= '.1';
      else if( !isset($tmp['Translator']) )
         $update_it.= '.2';
      else if( $tmp['Translator'] != $new_langs )
         $update_it.= '.3'; //surely, the field truncats the string
      else
         retry_admin( $msg);

      // Something went wrong. Restore to old set then error
      mysql_query( "UPDATE Players SET Translator='$old_langs'"
                  ." WHERE Handle='".mysql_addslashes($transluser)."'" )
         or error('mysql_query_failed','admin_do_translators.user.revert');

      error('couldnt_update_translation', $update_it);
   }
   retry_admin('');
}
?>

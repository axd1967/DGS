<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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
   return substr( $str, strcspn( $str, LANG_TRANSL_CHAR.LANG_CHARSET_CHAR), 1);
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
         'charenc',
         'twoletter',
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
      error("not_logged_in");

   if( !($player_row['admin_level'] & ADMIN_TRANSLATORS) )
      error("adminlevel_too_low");

   $addlanguage = @$_REQUEST['addlanguage'];
   $twoletter = trim(get_request_arg('twoletter'));
   $charenc = trim(get_request_arg('charenc'));
   $langname = trim(get_request_arg('langname'));

   $showpriv = @$_REQUEST['showpriv'];

   $transladd = @$_REQUEST['transladd'];
   $transladdlang = trim(get_request_arg('transladdlang'));

   $translpriv = @$_REQUEST['translpriv'];
   //transllang[] is a MULTIPLE select box => no get_request_arg()

   $transllang = get_request_arg('transllang');

   $twoletter = strtolower($twoletter);
   $charenc = strtolower($charenc);
   $langname = ucfirst($langname); //ucword()


   $msg = '';

   if( $addlanguage )
   {
      $tmp = lang_illegal($twoletter.$langname.$charenc);
      if( $tmp )
         retry_admin( T_("Sorry, there was an illegal character in a language field.") . " ($tmp)");

      if( strlen( $twoletter ) < 2 || empty( $langname ) || empty( $charenc ) )
        retry_admin( T_("Sorry, there was a missing or incorrect field when adding a language."));

      if( language_exists( $twoletter, $charenc, $langname ) )
        retry_admin( T_("Sorry, the language you tried to add already exists."));


      mysql_query("INSERT INTO TranslationLanguages SET " .
                  "Language='" . $twoletter . LANG_CHARSET_CHAR . $charenc . "', " .
                  "Name='$langname'");

      make_known_languages(); //must be called from main dir

      $row = mysql_single_fetch(
               "SELECT ID FROM TranslationGroups WHERE Groupname='Users'");
      if( !$row )
         error('internal_error','admin_t1');

      $Group_ID = $row['ID'];

      $tmp = mysql_query(
               "SELECT ID FROM TranslationTexts WHERE Text=\"$langname\"");
      if( @mysql_num_rows( $tmp ) === 0 )
      {
         mysql_query("INSERT INTO TranslationTexts SET Text=\"$langname\"")
            or error('internal_error','admin_t2');

         mysql_query("REPLACE INTO TranslationFoundInGroup " .
                     "SET Text_ID=" . mysql_insert_id() . ", " .
                     "Group_ID=" . $Group_ID );
      }

      retry_admin( sprintf( T_("Added language %s with code %s and characterencoding %s.")
                                 , $langname, $twoletter, $charenc ));
   }

//-------------------
// Queries with a user:

   $old_langs = '';
   if( $showpriv or $transladd or $translpriv )
   {
      $transluser = get_request_arg('transluser');
      if( empty($transluser) )
         retry_admin( T_("Sorry, you must specify a user."));
      $row = mysql_single_fetch(
                    "SELECT Translator FROM Players"
                   ." WHERE Handle='".addslashes($transluser)."'" );
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
                  ." WHERE Handle='".addslashes($transluser)."'" );
      if( mysql_affected_rows() != 1 )
         error('internal_error', $update_it);

      // Check result (
      $tmp = mysql_single_fetch(
                   "SELECT Translator FROM Players"
                  ." WHERE Handle='".addslashes($transluser)."'" );
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
                  ." WHERE Handle='".addslashes($transluser)."'" );
      error('internal_error', $update_it);
   }
   retry_admin('');
}
?>

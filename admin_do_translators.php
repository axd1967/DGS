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

  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_TRANSLATORS) )
     error("adminlevel_too_low");

  $charenc = trim($_REQUEST['charenc']);
  $langname = trim($_REQUEST['langname']);
  $twoletter = trim($_REQUEST['twoletter']);

  $extra_url_parts = '';
  if( $addlanguage )
    {
      if( strlen( $twoletter ) < 2 || empty( $langname ) || empty( $charenc ) )
        error("translator_admin_add_lang_missing_field");

      if( array_key_exists( $twoletter , $known_languages ) and
          array_key_exists( $charenc, $known_languages[$twoletter] ) )
        error("translator_admin_add_lang_exists");


      mysql_query("INSERT INTO TranslationLanguages SET " .
                  "Language='" . $twoletter . '.' . $charenc . "', " .
                  "Name='$langname'");

      make_known_languages();

      $res = mysql_query("SELECT ID FROM TranslationGroups WHERE Groupname='Users'");
      if( mysql_num_rows($res) != 1 )
         error("internal_error");

      $row = mysql_fetch_array($res);
      $Group_ID = $row['ID'];

      $res = mysql_query("SELECT ID FROM TranslationTexts WHERE Text=\"$langname\"");
      if( mysql_num_rows( $res ) === 0 )
      {
         mysql_query("INSERT INTO TranslationTexts SET Text=\"$langname\"")
            or die(mysql_error());

         mysql_query("REPLACE INTO TranslationFoundInGroup " .
                     "SET Text_ID=" . mysql_insert_id() . ", " .
                     "Group_ID=" . $Group_ID );
      }

      $msg = sprintf( T_("Added language %s with code %s and characterencoding %s."),
                      $langname, $twoletter, $charenc );
    }

  if( $transladd )
    {
      if( empty($transluser) )
        error("no_specified_user");

      if( !isset($transladdlang) or empty($transladdlang) )
        error("no_lang_selected");

      $result = mysql_query( "SELECT Translator FROM Players WHERE Handle='$transluser'" );

      if( mysql_affected_rows() != 1 )
        error("unknown_user");

      $row = mysql_fetch_array( $result );
      if( empty($row['Translator']) )
        $translator = array();
      else
        $translator = explode( ',', $row['Translator'] );

      if( !in_array( $transladdlang, $translator ) )
        {
          array_push( $translator, $transladdlang );
          $new_langs = implode(',', $translator);
          $result = mysql_query( "UPDATE Players SET Translator='$new_langs' WHERE Handle='$transluser'" );

          if( mysql_affected_rows() != 1 )
            error("unknown_user");

          $msg = sprintf( T_("Added user %s as translator for language %s."),
                          $transluser, $transladdlang );
        }
      else
        {
           $msg = sprintf( T_("User %s is already translator for language %s."),
                           $transluser, $transladdlang );
        }
    }

  if( $translpriv )
    {
      if( empty($transluser) )
        error("no_specified_user");

      if( !isset( $transllang ) )
        $transllang = array();

      $new_langs = implode(',', $transllang);

      $result = mysql_query( "UPDATE Players SET Translator='$new_langs' WHERE Handle='$transluser'" );
      if( mysql_affected_rows() != 1 )
        error("unknown_user");

      $msg = sprintf( T_("Changed translator privileges info for user %s."), $transluser );

    }

  jump_to("admin_translators.php?msg=" . urlencode($msg));
}
?>

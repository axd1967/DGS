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

require_once( "include/std_functions.php" );
require_once( "include/make_translationfiles.php" );

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $translate_lang = @$_POST['translate_lang'];
  $group = @$_POST['group'];
  $newgroup = @$_POST['newgroup'];

  if( isset($_POST['just_group'] ) )
      jump_to("translate.php?translate_lang=$translate_lang&group=" . urlencode($newgroup));

  $translator_array = explode(',', $player_row['Translator']);

  if( !in_array( $translate_lang, $translator_array ) )
     error('translation_not_correct_language');

  $untranslated = ($group === 'Untranslated phrases');

      $result = translations_query( $translate_lang, $untranslated, $group )
                or die(mysql_error());
      $numrows = mysql_num_rows($result);
      if( $numrows == 0 and !$untranslated )
         error('translation_bad_language_or_group');


  $replace_set = '';
  $log_set = '';
  $done_set = '';
  while( $row = mysql_fetch_array($result) )
  {

     $translation = trim(get_request_arg("transl" . $row['Original_ID']));
     $same = ( @$_POST["same" . $row['Original_ID']] === 'Y' );
     $unchanged = $untranslated && ( @$_POST["unch" . $row['Original_ID']] === 'Y' );

     if( $unchanged )
     {

        if( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
           $done_set .= ',' . $row['Original_ID'];

     }
     else if( (  $same && $row['Text'] !== '' )
           or ( !empty($translation) && $row['Text'] !== stripslashes($translation) ) )
     {
//        echo '<p>' . $row['Original_ID'] . ': ' . @$_POST["transl" . $row['Original_ID']];

        if( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
           $done_set .= ',' . $row['Original_ID'];

        if( $same ) $translation = '';

        $replace_set .= ',(' . $row['Original_ID'] . ',' .
           $row['Language_ID'] . ',"' . $translation . '")';
        $log_set .= ',(' . $player_row['ID'] . ',' .
           $row['Language_ID'] . ',' . $row['Original_ID'] . ',"' . $translation . '")';

     }
  }

  if( $replace_set )
     mysql_query( "REPLACE INTO Translations (Original_ID,Language_ID,Text) VALUES " .
                  substr($replace_set,1) ) or die(mysql_error());

  if( $log_set )
     mysql_query( "INSERT INTO Translationlog " .
                  "(Player_ID,Language_ID,Original_ID,Translation) VALUES " .
                  substr($log_set,1) ) or die(mysql_error());

  if( $done_set )
     mysql_query( "UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN (" .
                  substr($done_set,1) . ')' ) or die(mysql_error());

  make_include_files($translate_lang);

  jump_to("translate.php?translate_lang=$translate_lang&group=" . urlencode($newgroup));

}
?>

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

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $translate_lang = $_POST['translate_lang'];
  $group = $_POST['group'];
  $newgroup = $_POST['newgroup'];

  if( isset($_POST['just_group'] ) )
      jump_to("translate.php?translate_lang=$translate_lang&group=" . urlencode($newgroup));

  $translator_array = explode(',', $player_row['Translator']);

  if( !in_array( $translate_lang, $translator_array ) )
     error('translation_not_correct_language');

  $untranslated = ($group === 'Untranslated phrases');

  $query = "SELECT Translations.Text,TranslationTexts.ID AS Original_ID," .
     "TranslationTexts.Text AS Original, TranslationLanguages.ID AS Language_ID, " .
     "TranslationTexts.Translatable " .
     "FROM TranslationTexts, TranslationGroups, " .
     "TranslationFoundInGroup, TranslationLanguages " .
     "LEFT JOIN Translations ON Translations.Original_ID=TranslationTexts.ID " .
     "AND Translations.Language_ID=TranslationLanguages.ID ";

  if( $untranslated )
     $query .= "WHERE TranslationFoundInGroup.Group_ID=TranslationGroups.ID " .
        "AND TranslationFoundInGroup.Text_ID=TranslationTexts.ID " .
        "AND TranslationLanguages.Language='$translate_lang' " .
        "AND Translatable!='N' " .
        "AND Translations.Text IS NULL ORDER BY Group_ID LIMIT 50";
  else
     $query .= "WHERE TranslationGroups.Groupname='$group' " .
        "AND TranslationFoundInGroup.Group_ID=TranslationGroups.ID " .
        "AND TranslationFoundInGroup.Text_ID=TranslationTexts.ID " .
        "AND TranslationLanguages.Language='$translate_lang' " .
        "AND Translatable!='N' ";

  $result = mysql_query($query) or die(mysql_error());

  if( mysql_num_rows($result) == 0 and !$untranslated )
     error('translation_bad_language_or_group');

  $replace_query = "REPLACE INTO Translations (Original_ID,Language_ID,Text) VALUES ";
  $log_query = "INSERT INTO Translationlog " .
     "(Player_ID,Language_ID,Original_ID,Translation) VALUES ";
  $translatable_query = "UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN (0";
  $has_changed = false;
  while( $row = mysql_fetch_array($result) )
  {
     $transl = trim('' . $_POST["transl" . $row['Original_ID']]);
     $same = ( $_POST["same" . $row['Original_ID']] === 'Y' );
     if( (empty($transl) and !$same) or $transl === $row['Text'] )
        continue;

//     echo '<p>' . $row['Original_ID'] . ': ' . $_POST["transl" . $row['Original_ID']];

     $replace_query .= ($has_changed ? ',(' : '(') . $row['Original_ID'] . ',' .
        $row['Language_ID'] . ',"' . $transl . '")';
     $log_query .= ($has_changed ? ',(' : '(') . $player_row['ID'] . ',' .
        $row['Language_ID'] . ',' . $row['Original_ID'] . ',"' . $transl . '")';

     if( $row['Translatable'] === 'Y' )
        $translatable_query .= ',' . $row['Original_ID'];
     $has_changed = true;
  }

  $translatable_query .= ')';

  if( $has_changed )
  {
     mysql_query( $replace_query ) or die(mysql_error());
     mysql_query( $translatable_query ) or die(mysql_error());
     mysql_query( $log_query ) or die(mysql_error());
  }

  make_include_files($translate_lang);

  jump_to("translate.php?translate_lang=$translate_lang&group=" . urlencode($newgroup));

}
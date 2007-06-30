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

require_once( "include/std_functions.php" );
require_once( "include/make_translationfiles.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $group = get_request_arg('group');
   $translate_lang = get_request_arg('translate_lang');
   $profil_charset = @$_REQUEST['profil_charset'] ? 'Y' : '';

   $alpha_order = (int)(bool)@$_REQUEST['alpha_order'];
   $from_row = max(0,(int)@$_REQUEST['from_row']);

   {
      $translator_set = @$player_row['Translator'];
      if( !$translator_set )
        error("not_translator");
      $translator_array = explode( LANG_TRANSL_CHAR, $translator_set);
   }

   if( !in_array( $translate_lang, $translator_array ) )
      error('not_correct_transl_language', $translate_lang.':'.$translator_set.':'.implode("*", $translator_array));

   $untranslated = ($group === 'Untranslated phrases');

      $result = translations_query( $translate_lang, $untranslated, $group
               , $alpha_order, $from_row)
         or error('mysql_query_failed','update_translation.translations_query');

      $show_rows = (int)@mysql_num_rows($result);
      if( $show_rows <= 0 and !$untranslated )
         error('translation_bad_language_or_group','uptranslat1');
      if( $show_rows > TRANS_ROW_PER_PAGE )
         $show_rows = TRANS_ROW_PER_PAGE;


   $replace_set = '';
   $log_set = '';
   $done_set = '';
   $oid= -1;
   while( ($row = mysql_fetch_assoc($result)) && $show_rows-- > 0 )
   {
      /* see the translations_query() function for the constraints
       * on the "ORDER BY" clause associated with this "$oid" filter:
       */
      if( $oid == $row['Original_ID'] ) continue;
      $oid = $row['Original_ID'];

      $translation = trim(get_request_arg("transl" . $row['Original_ID']));
      $same = ( @$_POST["same" . $row['Original_ID']] === 'Y' );
      $unchanged = ( @$_POST["unch" . $row['Original_ID']] === 'Y' );

      if( $unchanged && $untranslated && $row['Text'] !== '' )
      { //unchanged item
         if( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
            $done_set .= ',' . $row['Original_ID'];

      }
      else if( (  $same && $row['Text'] !== '' )
            or ( !empty($translation) && $row['Text'] !== $translation ) )
      { //same or modified item
         if( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
            $done_set .= ',' . $row['Original_ID'];

         if( $same ) $translation = '';
         else $translation = mysql_addslashes($translation);

         $replace_set .= ',(' . $row['Original_ID'] . ',' .
            $row['Language_ID'] . ',"' . $translation . '")';

         $log_set .= ',(' . $player_row['ID'] . ',' .
            $row['Language_ID'] . ',' . $row['Original_ID'] . ',"' . $translation . '")';

      }
   }

   if( $replace_set )
      mysql_query( "REPLACE INTO Translations (Original_ID,Language_ID,Text) VALUES " .
                   substr($replace_set,1) )
         or error('mysql_query_failed','update_translation.replace');

   if( $log_set )
      mysql_query( "INSERT INTO Translationlog " .
                   "(Player_ID,Language_ID,Original_ID,Translation) VALUES " .
                   substr($log_set,1) ) //+ Date= timestamp
         or error('mysql_query_failed','update_translation.log');

   if( $done_set )
      mysql_query( "UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN (" .
                   substr($done_set,1) . ')' )
         or error('mysql_query_failed','update_translation.done');

   make_include_files($translate_lang); //must be called from main dir

   jump_to("translate.php?translate_lang=".urlencode($translate_lang)
         .URI_AMP."group=".urlencode($group)
         .($profil_charset ? URI_AMP."profil_charset=".$profil_charset : '')
         .($alpha_order ? URI_AMP."alpha_order=$alpha_order" : '')
         .($from_row > 0 ? URI_AMP."from_row=$from_row" : '')
         );

}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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

require_once( "include/std_functions.php" );
require_once( "include/make_translationfiles.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( TRANS_FULL_ADMIN && (@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
   {
      $lang_desc = get_language_descriptions_translated();
      $translator_array = array_keys( $lang_desc);
      unset($lang_desc);
   }
   else
   {
      $translator_set = @$player_row['Translator'];
      if( !$translator_set )
        error('not_translator');
      $translator_array = explode( LANG_TRANSL_CHAR, $translator_set);
   }

   $translate_lang = get_request_arg('translate_lang');
   $profil_charset = (int)(bool)@$_REQUEST['profil_charset'];

   $group = get_request_arg('group');
   $untranslated = (int)(bool)@$_REQUEST['untranslated'];
   $alpha_order = (int)(bool)@$_REQUEST['alpha_order'];
   $filter_en = get_request_arg('filter_en');
   $no_pages = (int)(bool)@$_REQUEST['no_pages'];
   if( $no_pages )
      $from_row = -1;
   else
      $from_row = max(0,(int)@$_REQUEST['from_row']);

   if( !in_array( $translate_lang, $translator_array ) )
      error('not_correct_transl_language', $translate_lang.':'.$translator_set.':'.implode("*", $translator_array));

   $result = translations_query( $translate_lang, $untranslated, $group
            , $from_row, $alpha_order, $filter_en)
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

      $translation = trim(get_request_arg("transl$oid"));
      $same = ( @$_POST["same$oid"] === 'Y' );
      $unchanged = ( @$_POST["unch$oid"] === 'Y' );


      if( $unchanged && $row['Translated'] === 'N' ) //exclude not yet translated items
      { //unchanged item
         //UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN
         if( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
            $done_set .= ",$oid";

         if( $same ) $translation = '';
         else $translation = mysql_addslashes($translation);

         //REPLACE INTO Translations (Original_ID,Language_ID,Text,Translated)
         $replace_set .= ",($oid," . $row['Language_ID'] . ",'$translation','Y')";

         //no $log_set
      }
      else if( ( $same && $row['Text'] !== '' )
            or ( !empty($translation) && $row['Text'] !== $translation ) )
      { //same or modified item
         //UPDATE TranslationTexts SET Translatable='Done' WHERE ID IN
         if( @$row['Translatable'] !== 'N' && @$row['Translatable'] !== 'Done' )
            $done_set .= ",$oid";

         if( $same ) $translation = '';
         else $translation = mysql_addslashes($translation);

         //REPLACE INTO Translations (Original_ID,Language_ID,Text,Translated)
         $replace_set .= ",($oid," . $row['Language_ID'] . ",'$translation','Y')";

         //INSERT INTO Translationlog (Player_ID,Original_ID,Language_ID,Translation)
         $log_set .= ',(' . $player_row['ID'] . ",$oid,"
                  . $row['Language_ID'] . ",'$translation')";
      }
   } //foreach translation phrases

   if( $replace_set )
      mysql_query( "REPLACE INTO Translations " .
                   "(Original_ID,Language_ID,Text,Translated) VALUES " .
                   substr($replace_set,1) )
         or error('mysql_query_failed','update_translation.replace');

   if( $log_set )
      mysql_query( "INSERT INTO Translationlog " .
                   "(Player_ID,Original_ID,Language_ID,Translation) VALUES " .
                   substr($log_set,1) ) //+ Date= timestamp
         or error('mysql_query_failed','update_translation.log');

   if( $done_set )
      mysql_query( "UPDATE TranslationTexts SET Translatable='Done' " .
                   "WHERE ID IN (" . substr($done_set,1) . ')' )
         or error('mysql_query_failed','update_translation.done');

   make_include_files($translate_lang); //must be called from main dir

   jump_to("translate.php?translate_lang=".urlencode($translate_lang)
         .($profil_charset ? URI_AMP."profil_charset=".$profil_charset : '')
         .URI_AMP."group=".urlencode($group)
         .($untranslated ? URI_AMP."untranslated=$untranslated" : '')
         .($alpha_order ? URI_AMP."alpha_order=$alpha_order" : '')
         .($filter_en ? URI_AMP."filter_en=".urlencode($filter_en) : '')
         .($from_row > 0 ? URI_AMP."from_row=$from_row" : '')
         );
}
?>

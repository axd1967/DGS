<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// The Sourceforge devel server need a soft link:
// ln -s -d /tmp/persistent/dragongoserver/translations /home/groups/d/dr/dragongoserver/htdocs/translations

define('TRANS_ROW_PER_PAGE', 30);
define('TRANS_FULL_ADMIN', 1); //allow all languages access to ADMIN_TRANSLATORS


function make_known_languages() //must be called from main dir
{
   $result = db_query( 'make_translationfiles.make_knownlanguages',
      "SELECT * FROM TranslationLanguages ORDER BY Language" );

   $Filename = 'translations/known_languages.php'; //must be called from main dir

   //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
   $fd = @fopen( $Filename, 'w');
   //error_reporting($e);
   if( !$fd )
   {
      //echo "couldnt_open_file ". $Filename; exit;
      global $TheErrors;
      $TheErrors->set_mode(ERROR_MODE_PRINT);
      error('couldnt_open_file', "make_known_languages.err1($Filename)");
   }

   $group= 'Common';
   global $TranslateGroups, $known_languages;
   $TranslateGroups[]= $group;
   $known_languages = array();

   fwrite( $fd, "<?php\n\n"
."\$TranslateGroups[] = \"$group\"; //local use\n"
."// The \$T_ are required for 'scripts/generate_translation_texts.php'.\n"
."\$T_ = 'fnop';\n" //or 'trim'... never translated in known_languages.php
."\n\$known_languages = array(\n" );

   $prev_lang = '';
   $first = true;
   while( $row = mysql_fetch_array($result) )
   {
      @list($browsercode,$charenc) = explode( LANG_CHARSET_CHAR, $row['Language'], 2);
      $browsercode = strtolower(trim($browsercode));
      $charenc = strtolower(trim($charenc));

      $tmp = slashed($row['Name']);
      if( $browsercode === $prev_lang )
         fwrite( $fd, ",\n                 '$charenc' => \$T_('$tmp')");
      else
         fwrite( $fd, ( $first ? '' : " ),\n" ) .
                      "  '$browsercode' => array( '$charenc' => \$T_('$tmp')");

      $known_languages[$browsercode][$charenc] = $row['Name']; //not translated here
      $prev_lang = $browsercode;
      $first = false;
   }
   mysql_free_result($result);
   fwrite( $fd, ( $first ? '' : ' )' ) . "\n);\n\n?>" );
   fclose($fd);
   unset($fd);
} //make_known_languages

function slashed($string)
{
   //all that can disturb a PHP string quoted with ''
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
   /*
   //all that can disturb a PHP string quoted with ""
   return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $string );
   */
}

function make_include_files($language=null, $group=null) //must be called from main dir
{
   global $NOW;

   chdir( 'translations'); //must be called from main dir

   $query = "SELECT Translations.Text, TranslationGroups.Groupname, " .
      "TranslationLanguages.Language, TranslationTexts.Text AS Original " .
      "FROM (Translations, TranslationTexts, TranslationLanguages, " .
         "TranslationFoundInGroup, TranslationGroups) " .
      "WHERE Translations.Language_ID = TranslationLanguages.ID ".
      //"AND Translations.Text!='' " . //else a file containing only '' will not be reseted
      "AND TranslationTexts.ID=Translations.Original_ID " .
      "AND TranslationFoundInGroup.Text_ID=Translations.Original_ID " .
      "AND TranslationGroups.ID=TranslationFoundInGroup.Group_ID ";

   if( !empty($group) )
      $query .= "AND TranslationGroups.Groupname='$group' ";
   if( !empty($language) )
      $query .= "AND TranslationLanguages.Language='$language' ";

   $query .= "ORDER BY Language,Groupname";

   $result = db_query( 'make_translationfiles.make_include_files', $query );

   $grp = '';
   $lang = '';
   while( $row = mysql_fetch_array($result) )
   {
      if( $row['Groupname'] !== $grp || $row['Language'] !== $lang )
      {
         $grp = $row['Groupname'];
         $lang = $row['Language'];

//         echo "<p></p>$lang -- $grp\n";

         if( isset( $fd ) )
         {
            fwrite( $fd , "\n?>");
            fclose( $fd );
         }

         $Filename = $lang . '_' . $grp . '.php';

         //$e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
         $fd = @fopen( $Filename, 'w');
         //error_reporting($e);
         if( !$fd )
            error('couldnt_open_file', "make_include_files.err2($Filename)");

         fwrite( $fd, "<?php\n\n/* Automatically generated at " .
                 gmdate('Y-m-d H:i:s T', $NOW) . " */\n\n");
      }

      if( !empty($row['Text']) )
         fwrite( $fd, "\$Tr['" . slashed($row['Original']) . "'] = '" .
              slashed($row['Text']) . "';\n" );
   }


   if( isset($fd) )
   {
      fwrite( $fd , "\n?>");
      fclose( $fd );
   }

   chdir('../');
} //make_include_files


function translations_query( $translate_lang, $untranslated, $group
               , $from_row=-1, $alpha_order=false, $filter_en='')
{
   /* Note: Some items appear two or more times within the untranslated set
      when from different groups. But we can't use:
          ( $untranslated ? "DISTINCT " : "")
      because the Group_ID column makes the rows distinct.
      Workaround: using "ORDER BY Original_ID LIMIT 50";
       and filter the rows on Original_ID while computing.
      The previous sort was:
          "ORDER BY TranslationFoundInGroup.Group_ID LIMIT 50";
      As the Original are identical when the Original_ID are identical,
      an other possible sort is "ORDER BY Original,Original_ID";
   */
   if( $alpha_order )
      $order = ' ORDER BY Original,Original_ID';
   else
      $order = ' ORDER BY Original_ID';
   if( $from_row >= 0 )
      $limit = " LIMIT $from_row,".(TRANS_ROW_PER_PAGE+1);
   else
      $limit = '';

   $query = "SELECT Translations.Text"
          . ",TranslationTexts.ID AS Original_ID"
          . ",TranslationLanguages.ID AS Language_ID"
          . ",TranslationFoundInGroup.Group_ID"
          . ",TranslationTexts.Text AS Original"
          . ",TranslationTexts.Translatable"
          . ",Translations.Translated"
   . " FROM (TranslationTexts"
        . ",TranslationLanguages"
        . ",TranslationFoundInGroup"
        . ",TranslationGroups)"
   . " LEFT JOIN Translations"
       . " ON Translations.Original_ID=TranslationTexts.ID"
      . " AND Translations.Language_ID=TranslationLanguages.ID"
   . " WHERE TranslationLanguages.Language='".mysql_addslashes($translate_lang)."'"
      . " AND TranslationFoundInGroup.Group_ID=TranslationGroups.ID"
      . " AND TranslationFoundInGroup.Text_ID=TranslationTexts.ID"
      . " AND TranslationTexts.Text>''"
      . " AND TranslationTexts.Translatable!='N'" ;

   if( $filter_en )
      $query .= " AND TranslationTexts.Text LIKE '%".mysql_addslashes($filter_en)."%'";

   if( $untranslated )
   {
      $query .= " AND (Translations.Translated IS NULL OR Translations.Translated='N')";
      // Translations.Translated IS NULL means "never translated" (LEFT JOIN fails).
      // TODO: enhance the query around this 'OR' clause
   }

   if( $group != 'allgroups' )
      $query .= " AND TranslationGroups.Groupname='".mysql_addslashes($group)."'";
   $query .= $order.$limit;

   $result = db_query( 'translations_query', $query );

   return $result;
} //translations_query


function add_text_to_translate( $debugmsg, $string, $Group_ID, $do_it=true)
{
   $string= trim($string);
   if( !$string || $Group_ID <= 0 )
      return false;

   // see the explanations for the latin1_safe() function in admin_faq.php
   $string= preg_replace(
         "%([\\x80-\\xff])%ise",
         "'&#'.ord('\\1').';'",
         $string);

   $string= mysql_addslashes($string);

   $res = db_query( $debugmsg.'.find_transltext',
      "SELECT ID FROM TranslationTexts WHERE Text='$string'" );

   $Text_ID = 0;
   if( @mysql_num_rows( $res ) == 0 )
   {
      $insert= "INSERT INTO TranslationTexts SET Text='$string'";
      if( $do_it )
      {
         db_query( $debugmsg.'.insert_transltext', $insert );
         $Text_ID = mysql_insert_id();
      }
   }
   else
   {
      $insert= '' ;
      if( $do_it )
      {
         $row = mysql_fetch_assoc($res);
         $Text_ID = $row['ID'];
      }
   }
   mysql_free_result($res);

   if( $do_it && $Text_ID > 0 )
   {
      db_query( $debugmsg.'.update_translfig',
         "REPLACE INTO TranslationFoundInGroup " .
                  "SET Text_ID=$Text_ID, Group_ID=$Group_ID" );
   }

   return $insert;
} //add_text_to_translate

?>

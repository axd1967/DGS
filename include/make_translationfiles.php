<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

// The Sourceforge devel server need a soft link:
// ln -s -d /tmp/persistent/dragongoserver/translations /home/groups/d/dr/dragongoserver/htdocs/translations

define('TRANS_ROW_PER_PAGE', 30);
define('TRANS_FULL_ADMIN', 1); //allow all languages access to ADMIN_TRANSLATORS


function make_known_languages() //must be called from main dir
{
   $result = mysql_query("SELECT * FROM TranslationLanguages ORDER BY Language")
      or error('mysql_query_failed', 'make_translationfiles.make_knownlanguages');

   $Filename = 'translations/known_languages.php'; //must be called from main dir

   $e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
   $fd = fopen( $Filename, 'w');
   error_reporting($e);
   if( !$fd )
   {
      //echo "couldnt_open_transl_file ". $Filename; exit;
      global $TheErrors;
      $TheErrors->set_mode(ERROR_MODE_PRINT);
      error("couldnt_open_transl_file", $Filename);
   }

   fwrite( $fd, "<?php\n\n"
."\$TranslateGroups[] = \"Users\"; //local use\n"
."// The \$T_ are required for 'scripts/generate_translation_texts.php'.\n"
."\$T_ = 'fnop';\n" //or 'trim'
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

      $prev_lang = $browsercode;
      $first = false;
   }
   mysql_free_result($result);
   fwrite( $fd, ( $first ? '' : ' )' ) . "\n);\n\n?>" );
   fclose($fd);
   unset($fd);
}

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
      "FROM Translations, TranslationTexts, TranslationLanguages, " .
      "TranslationFoundInGroup, TranslationGroups " .
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

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'make_translationfiles.make_include_files');

   $grp = '';
   $lang = '';
   while( $row = mysql_fetch_array($result) )
   {
      if( $row['Groupname'] !== $grp or $row['Language'] !== $lang )
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

         $e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
         $fd = fopen( $Filename, 'w');
         error_reporting($e);
         if( !$fd )
         {
            error("couldnt_open_transl_file", $Filename);
         }

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
}


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
      $query .=
/*
         " AND LOWER(TranslationTexts.Text) LIKE '%"
               .mysql_addslashes(strtolower($filter_en))."%'";
/*/
         " AND TranslationTexts.Text LIKE '%".mysql_addslashes($filter_en)."%'";
#*/

   if( $untranslated )
      $query .=
         " AND (Translations.Translated IS NULL OR Translations.Translated='N')";
      // Translations.Translated IS NULL means "never translated" (LEFT JOIN fails).
      // TODO: enhance the query around this 'OR' clause

   switch( $group )
   {
   default:
      $query .=
         " AND TranslationGroups.Groupname='".mysql_addslashes($group)."'";
   case 'allgroups':
      break;
   }
   $query .= $order.$limit;

   $result = mysql_query( $query)
      or error('mysql_query_failed','translations_query');

   return $result;
}


function add_text_to_translate( $debugmsg, $string, $Group_ID, $do_it=true)
{
   $string= trim($string);
   if( !$string || $Group_ID <= 0 )
      return false;

   // see the explanations for the latin1_safe() function in admin_faq.php
   {
      $string= preg_replace(
         "%([\\x80-\\xff])%ise",
         "'&#'.ord('\\1').';'",
         $string);
   }

   $string= mysql_addslashes($string);

   $res = mysql_query("SELECT ID FROM TranslationTexts WHERE Text='$string'")
      or error('mysql_query_failed', $debugmsg.'.find_transltext');

   $Text_ID = 0;
   if( @mysql_num_rows( $res ) == 0 )
   {
      $insert= "INSERT INTO TranslationTexts SET Text='$string'";
      if( $do_it )
      {
         mysql_query($insert)
            or error('mysql_query_failed', $debugmsg.'.insert_transltext');
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
      mysql_query("REPLACE INTO TranslationFoundInGroup " .
                  "SET Text_ID=$Text_ID, Group_ID=$Group_ID" )
            or error('mysql_query_failed', $debugmsg.'.update_translfig');
   }

   return $insert;
}


// function delete_translationtext($Text_ID)
// {
//    mysql_query("DELETE FROM TranslationTexts WHERE ID='$Text_ID'");
//    mysql_query("DELETE FROM TranslationFoundInGroup WHERE Text_ID='$Text_ID'");
//    mysql_query("DELETE FROM Translations WHERE Original_ID='$Text_ID'");
// }

?>

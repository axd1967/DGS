<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

// The sourceforge devel server need a soft link:
// ln -s -d /tmp/persistent/dragongoserver/translations /home/groups/d/dr/dragongoserver/htdocs/translations

function make_known_languages() //must be called from main dir
{
   $result = mysql_query("SELECT * FROM TranslationLanguages ORDER BY Language");

   $Filename = 'translations/known_languages.php'; //must be called from main dir

   $e= error_reporting(E_ALL & ~(E_WARNING | E_NOTICE));
   $fd = fopen( $Filename, 'w');
   error_reporting($e);
   if( !$fd )
   {
      //echo "couldnt_open_transl_file ". $Filename; exit;
      global $quick_errors;
      $quick_errors= 1; // nothing more can be done with an error now.
      error("couldnt_open_transl_file", $Filename);
   }

   fwrite( $fd, "<?php\n\n\$known_languages = array(\n" );

   $prev_lang = '';
   $first = true;
   while( $row = mysql_fetch_array($result) )
   {
      @list($lang,$charenc) = explode( LANG_CHARSET_CHAR, $row['Language'], 2);

      if( $lang === $prev_lang )
         fwrite( $fd, ",\n                 \"$charenc\" => \"" . $row["Name"] . '"');
      else
         fwrite( $fd, ( $first ? '' : " ),\n" ) .
                 "  \"$lang\" => array( \"$charenc\" => \"" . $row["Name"] . '"');

      $prev_lang = $lang;
      $first = false;
   }
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


   // Now make all translation include-files

function make_include_files($language=null, $group=null) //must be called from main dir
{
   global $NOW;

   chdir( 'translations'); //must be called from main dir

   $query = "SELECT Translations.Text, TranslationGroups.Groupname, " .
      "TranslationLanguages.Language, TranslationTexts.Text AS Original " .
      "FROM Translations, TranslationTexts, TranslationLanguages, " .
      "TranslationFoundInGroup, TranslationGroups " .
      "WHERE Translations.Language_ID = TranslationLanguages.ID ".
      "AND Translations.Text!='' " .
      "AND TranslationTexts.ID=Translations.Original_ID " .
      "AND TranslationFoundInGroup.Text_ID=Translations.Original_ID " .
      "AND TranslationGroups.ID=TranslationFoundInGroup.Group_ID ";

   if( !empty($group) )
      $query .= "AND TranslationGroups.Groupname='$group' ";
   if( !empty($language) )
      $query .= "AND TranslationLanguages.Language='$language' ";

   $query .= "ORDER BY Language,Groupname";

   $result = mysql_query( $query ) or die(mysql_error());

   $grp = '';
   $lang = '';
   while( $row = mysql_fetch_array($result) )
   {
      if( $row['Groupname'] !== $grp or $row['Language'] !== $lang )
      {
         $grp = $row['Groupname'];
         $lang = $row['Language'];

//         echo "<p>$lang -- $grp\n";

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


// function delete_translationtext($Text_ID)
// {
//    mysql_query("DELETE FROM TranslationTexts WHERE ID='$Text_ID'");
//    mysql_query("DELETE FROM TranslationFoundInGroup WHERE Text_ID='$Text_ID'");
//    mysql_query("DELETE FROM Translations WHERE Original_ID='$Text_ID'");
// }


function translations_query( $translate_lang, $untranslated, $group )
{
// See admin_faq.php to know the Translatable flag meaning.
   $query = 
     "SELECT Translations.Text," .
            "TranslationTexts.ID AS Original_ID," .
            "TranslationLanguages.ID AS Language_ID," .
            "TranslationFoundInGroup.Group_ID," .
            "TranslationTexts.Text AS Original," .
            "TranslationTexts.Translatable " .
     "FROM TranslationTexts," .
          "TranslationLanguages," .
          "TranslationFoundInGroup," .
          "TranslationGroups " .
     "LEFT JOIN Translations " .
        "ON Translations.Original_ID=TranslationTexts.ID " .
       "AND Translations.Language_ID=TranslationLanguages.ID " .
     "WHERE TranslationLanguages.Language='$translate_lang' " .
       "AND TranslationFoundInGroup.Group_ID=TranslationGroups.ID " .
       "AND TranslationFoundInGroup.Text_ID=TranslationTexts.ID " .
       "AND TranslationTexts.Translatable!='N' " ;

   if( !$untranslated )
     $query .= 
       "AND TranslationGroups.Groupname='$group' " .
       "ORDER BY Original_ID"; // LIMIT 50";
   else
     $query .= 
       "AND (Translations.Text IS NULL OR TranslationTexts.Translatable='Changed') " .
       "ORDER BY Original_ID LIMIT 50";
/* Translations.Text IS NOT NULL (but maybe empty if the 'same' box is checked)
    and Translatable='Y' (instead of Done as for a FAQ message)
    is the default status for a translated system message.
   So, Translations.Text IS NULL mean "never translated".
*/
/* Note: Some items appear two or more times within the untranslated set
    when from different groups. But we can't use:
       ( $untranslated ? "DISTINCT " : "")
    because the Group_ID column makes the rows distinct.
   Workaround: using "ORDER BY Original_ID LIMIT 50";
    and filter the rows on Original_ID while computing.
   The previous sort was:
       "ORDER BY TranslationFoundInGroup.Group_ID LIMIT 50";
*/

   return mysql_query($query);
}
?>
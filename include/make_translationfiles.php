<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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


function make_known_languages()
{
   chdir( 'translations' );

   $result = mysql_query("SELECT * FROM TranslationLanguages ORDER BY Language");

   $fd = fopen('known_languages.php', 'w')
      or error("couldnt_open_transl_file", 'known_languages');

   fwrite( $fd, "<?php\n\n\$known_languages = array(\n" );

   $prev_lang = '';
   $first = true;
   while( $row = mysql_fetch_array($result) )
   {
      list($lang,$charenc) = explode('.', $row['Language']);

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

   chdir('../');
}

function slashed($string)
{
   //all that can disturb a PHP string quoted with ""
   return str_replace( array( "\\", "\"", "\$" ), array( "\\\\", "\\\"", "\\\$" ), $string );
}


   // Now make all translation include-files

function make_include_files($language=null, $group=null)
{
   global $NOW;

   chdir( 'translations' );

   $query = "SELECT Translations.Text, TranslationGroups.Groupname, " .
      "TranslationLanguages.Language, TranslationTexts.Text AS Original " .
      "FROM Translations, TranslationTexts, TranslationLanguages, " .
      "TranslationFoundInGroup, TranslationGroups " .
      "WHERE Translations.Language_ID = TranslationLanguages.ID ".
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

         $fd = fopen( $Filename, 'w' )
            or error("couldnt_open_transl_file", $Filename);

         fwrite( $fd, "<?php\n\n/* Automatically generated at " .
                 gmdate('Y-m-d H:i:s T', $NOW) . " */\n\n");
      }

      fwrite( $fd, "\$Tr[\"" . slashed($row['Original']) . "\"] = \"" .
              slashed($row['Text']) . "\";\n" );
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

   $query = "SELECT Translations.Text,TranslationTexts.ID AS Original_ID," .
     "TranslationFoundInGroup.Group_ID ," . //ORDER BY columns not in the result is not allowed in ANSI SQL.
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
/* 
  Translations.Text IS NOT NULL (but maybe "" if the 'same' box is checked)
    and Translatable='Y' (instead of Done)
    is the default status for all the system messages.
  So Translations.Text IS NULL and Translatable!='N' mean "never translated".
*/
        "AND Translatable!='N' " .
        "AND (Translations.Text IS NULL OR Translatable='Changed') " .
/*>>Rod: Warning: Some items appear two times when in different groups
     but we can't use:
        . ( $untranslated ? "DISTINCT " : "") . 
     unless:
        "ORDER BY Original_ID LIMIT 50";
*/
        "ORDER BY TranslationFoundInGroup.Group_ID LIMIT 50";
   else
     $query .= "WHERE TranslationGroups.Groupname='$group' " .
        "AND TranslationFoundInGroup.Group_ID=TranslationGroups.ID " .
        "AND TranslationFoundInGroup.Text_ID=TranslationTexts.ID " .
        "AND TranslationLanguages.Language='$translate_lang' " .
        "AND Translatable!='N' ";

   return mysql_query($query);
}
?>
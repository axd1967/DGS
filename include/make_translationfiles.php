<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

   $first = true;
   while( $row = mysql_fetch_array($result) )
   {
      list($lang,$enc) = explode('.', $row['Language']);

      if( $lang === $prev_lang )
         fwrite( $fd, ",\n                 \"$enc\" => \"" . $row["Name"] . '"');
      else
         fwrite( $fd, ( $first ? '' : " ),\n" ) .
                 "  \"$lang\" => array( \"$enc\" => \"" . $row["Name"] . '"');

      $prev_lang = $lang;
      $first = false;
   }
   fwrite( $fd, " )\n);\n\n?>" );
   fclose($fd);
   unset($fd);

   chdir('../');
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

      fwrite( $fd, "\$Tr[\"" . $row['Original'] . "\"] = \"" . $row['Text'] . "\";\n" );
   }


   if( isset($fd) )
   {
      fwrite( $fd , "}\n?>");
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
?>
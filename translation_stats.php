<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_VFY_CHK );

   $page = "translation_stats.php";
   $title = T_('Translation-Statistics');

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=\"Header\">$title</h3>\n";

   show_translation_stats();

   end_page();
}//main


function show_translation_stats()
{
   global $page;

   $cnt_transl_texts = count_translation_texts();
   $cnt_translations = count_translations();

   $table = new Table( 'transl_stats', $page, '', '', TABLE_NO_SORT|TABLE_NO_SIZE|TABLE_ROW_NUM|TABLE_ROWS_NAVI );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('Language#header'), '', 0, 'TL.Name+');
   $table->add_tablehead( 2, T_('Translated#header'), 'Number');
   $table->add_tablehead( 3, T_('Missing#header'), 'Number');
   $table->add_tablehead( 4, T_('Total%#header'), 'Number');

   $table->set_default_sort( 2); // on Translated
   $table->set_found_rows( count($cnt_translations) );

   foreach ( $cnt_translations as $language => $count ) // $count = lang-specific translated-count
   {
      $row_str = array();
      if ( $table->Is_Column_Displayed[1] )
         $row_str[1] = $language;
      if ( $table->Is_Column_Displayed[2] )
         $row_str[2] = $count;
      if ( $table->Is_Column_Displayed[3] )
         $row_str[3] = $cnt_transl_texts - $count;
      if ( $table->Is_Column_Displayed[4] )
         $row_str[4] = sprintf( '%3.2f%%', $count / $cnt_transl_texts * 100 );

      $table->add_row( $row_str );
   }

   echo T_('Total texts to translate'), ": ", span('bold', $cnt_transl_texts), "<br>\n";

   $table->echo_table();
}//show_translation_stats

// returns count of total original translation texts
function count_translation_texts()
{
   $result = mysql_single_fetch( "transl_stats.count_translation_texts",
      "SELECT COUNT(*) AS X_Count FROM TranslationTexts AS TT " );
   return ($result) ? $result['X_Count'] : 0;
}//count_translation_texts

// returns count of language-specific translations in arr( Language => count, ... ) ordered by max-count first
function count_translations()
{
   $result = array();
   $db_result = db_query( "transl_stats.count_translations",
      "SELECT TL.Name AS Lang, COUNT(*) AS X_Count " .
      "FROM Translations AS T " .
         "INNER JOIN TranslationLanguages AS TL ON TL.ID=T.Language_ID " .
      "GROUP BY T.Language_ID " .
      "ORDER BY X_Count DESC" );

   while ( $row = mysql_fetch_array($db_result) )
      $result[$row['Lang']] = $row['X_Count'];
   mysql_free_result($db_result);

   return $result;
}//count_translations

?>

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

$TranslateGroups[] = "FAQ";

require_once( "include/std_functions.php" );
require_once( "include/faq_functions.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_("FAQ"), true, $logged_in, $player_row );
   $menu_array = array();

   echo "<table class=FAQ><tr><td>\n";
   echo "<h3 class=Header align=left><a name=\"general\">" .
         T_('Frequently Asked Questions') . "</a></h3>\n";

   // init vars
   $TW_ = 'T_'; // for non-const translation-texts
   $faq_url = 'faq.php?';
   //$faqhide = "AND entry.Hidden='N' AND (entry.Level=1 OR parent.Hidden='N') ";
   $faqhide = " AND entry.Hidden='N' AND parent.Hidden='N'"; //need a viewable root

   $arr_languages = get_language_descriptions_translated();
   $lang = get_request_arg('lang', 0);
   if ( !$lang ) $lang = recover_language( $player_row );

   // parse search terms
   $is_search = @$_REQUEST['search'];
   $qterm = trim(get_request_arg('qterm', ''));
   $orig = ( get_request_arg('orig', '') ) ? 1 : 0;
   $err_search = '';
   $rx_term = '';
   if( $is_search )
   {
      $err_search = check_faq_search_terms( $qterm );
      $rx_term = build_regex_term( $qterm );
      if( (string)$err_search != '' || (string)$rx_term == '' )
         $is_search = false;
   }

   // search form
   $faq_form = new Form( 'faq', $faq_url, FORM_GET );
   $faq_form->add_row( array(
      'DESCRIPTION',    T_('Search Terms#FAQ'),
      'TEXTINPUTX',     'qterm', 30, -1, $qterm,
                        array( 'title' => T_('Syntax[FAQ]: any words or characters (min. length 2)') ),
      'SUBMITBUTTONX',  'search', T_('Search'),
                        array( 'accesskey' => ACCKEY_ACT_FILT_SEARCH ),
      'TEXT',           ' ' . sprintf( T_('in language (%s)#FAQ'), $arr_languages[$lang] ),
      ));
   if( $err_search )
      $faq_form->add_row( array( 'TAB', 'TEXT', "<span class=ErrMsg>($err_search)</span>" ));
   $faq_form->add_row( array(
      'TAB',
      'CHECKBOX', 'orig', 1, T_('search only in original english FAQ (in case of untranslated entries)'), $orig ));

   echo "</td></tr><tr><td class=\"FAQsearch\">\n";
   $faq_form->echo_string(1);
   echo "<br>\n";

   $cat = @$_GET['cat'];
   if( $cat !== 'all' && !is_numeric($cat) ) $cat = 0;

   if( $is_search )
   { // show matching faq-entries
      // NOTES:
      // - read whole FAQ from DB, then match on translations read from file.
      // - read data from file can be cached //TODO
      // - as long as no UTF8 for all languages in the DB, db-search is unreliable.

      $query =
         "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM (FAQ AS entry, FAQ AS parent, TranslationTexts AS Question) " .
         "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
         "WHERE parent.ID=entry.Parent $faqhide AND Question.ID=entry.Question " .
         "ORDER BY CatOrder,ParentOrder,entry.SortOrder";
      $result = db_query( 'faq.search_entries', $query );

      $found_entries = 0;
      if( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQsearch>\n";

         echo faq_item_html( 0);
         $outbuf = '';
         $cntmatch = 0; // count of matches for level1-section
         while( $row = mysql_fetch_assoc( $result ) )
         { //expand answers
            $level = $row['Level'];
            if( $orig )
            { // no language-translation (use orig-texts from DB)
               $question = $row['Q'];
               $answer   = $row['A'];
            }
            else
            { // use user-language-specific translation
               $question = $TW_( $row['Q'] );
               $answer   = $TW_( $row['A'] );
            }

            $match = search_faq_match_terms( $question, $answer, $rx_term );
            if( $level == 1 || $match )
            {
               $a_attb = ( $level == 1 )
                  ? "href=\"faq.php#Title{$row['ID']}\""
                  : "name=\"Entry{$row['ID']}\"";
               $faqtext = faq_item_html( $level, $question, $answer, $a_attb, $rx_term );
            }
            else
               $faqtext = '';

            if( $level == 1 )
            {
               if( $cntmatch > 0 )
                  echo $outbuf;
               $cntmatch = 0;
               $outbuf = $faqtext;
            }
            else if( (string)$faqtext != '' )
            {
               $outbuf .= $faqtext;
               $cntmatch++;
               $found_entries++;
            }
         }
         if( $cntmatch > 0 )
            echo $outbuf;
         echo faq_item_html(-1);
      }
      mysql_free_result($result);

      if( $found_entries == 0 )
         echo '<strong>' . T_('No matching entries found in FAQ.') . "</strong>\n";

      $menu_array[T_('Go back to the FAQ index')]= "faq.php";
   }
   elseif( @$_GET["read"] == 't' )
   { // show one or all faq-section(s) with all entries expanded
      $result = db_query( 'faq.find_entries',
         "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM (FAQ AS entry, FAQ AS parent, TranslationTexts AS Question) " .
         "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
         "WHERE parent.ID=entry.Parent $faqhide AND Question.ID=entry.Question " .
         ( $cat === 'all' ? '' : "AND (entry.ID=$cat OR entry.Parent=$cat) " ) .
         "ORDER BY CatOrder,ParentOrder,entry.SortOrder" );

      if( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQread>\n";

         echo faq_item_html( 0);
         while( $row = mysql_fetch_assoc( $result ) )
         { //expand answers
            echo faq_item_html( $row['Level']
                           , $TW_( $row['Q'] ), $TW_( $row['A'] )
                           , $row['Level'] == 1
                              ? "href=\"faq.php#Title{$row['ID']}\""
                              : "name=\"Entry{$row['ID']}\""
                           );
            if( $row['Level'] == 1 )
               echo "<a name=\"Entry{$row['ID']}\"></a>\n";
         }
         echo faq_item_html(-1);
      }
      mysql_free_result($result);

      $menu_array[T_('Go back to the FAQ index')]= "faq.php";
   }
   else
   { // show only faq-titles
      $result = db_query( 'faq.find_titles',
         "SELECT entry.*, Question.Text AS Q, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM (FAQ AS entry, FAQ AS parent, TranslationTexts AS Question) " .
         "WHERE parent.ID=entry.Parent $faqhide AND Question.ID=entry.Question " .
            "AND entry.Level<3 AND entry.Level>0 " .
         "ORDER BY CatOrder,entry.Level,entry.SortOrder" );

      if( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQindex>\n";

         echo faq_item_html( 0);
         $tmp = 'href="faq.php?read=t'.URI_AMP.'cat=';
         while( $row = mysql_fetch_assoc( $result ) )
         { //titles only
            echo faq_item_html( $row['Level']
                           , $TW_( $row['Q'] ), ''
                           , $row['Level'] == 1
                              ? $tmp.$row['ID'].'#Entry'.$row['ID'].'"'
                              : $tmp.$row['Parent'].'#Entry'.$row['ID'].'"'
                           );
            if( $row['Level'] == 1 )
               echo "<a name=\"Title{$row['ID']}\"></a>\n";
         }
         echo faq_item_html(-1);
      }
      mysql_free_result($result);
   }
   echo "</td></tr></table>\n";

   if( $cat !== 'all' )
      $menu_array[T_('Show the whole FAQ in one page')]= "faq.php?read=t".URI_AMP."cat=all";

   end_page(@$menu_array);
}
?>

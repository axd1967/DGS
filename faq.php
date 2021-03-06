<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/faq_functions.php';
require_once 'include/form_functions.php';

$GLOBALS['ThePage'] = new Page('FAQ', 0, ROBOTS_NO_FOLLOW,
   "Help pages (FAQ) for the Dragon Go Server (DGS), where you can play turn-based Go (aka Baduk or Weichi).",
   'help, support, search, FAQ, questions, features, user guide' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_VFY_CHK );
   $is_admin = ( @$player_row['admin_level'] & ADMIN_FAQ );
   $is_op = ( @$player_row['admin_level'] & ADMINGROUP_EXECUTIVE ) || @$player_row['Translator'];

   start_page(T_("FAQ"), true, $logged_in, $player_row );
   $menu_array = array();

   echo "<table class=FAQ><tr><td>\n";
   echo "<h3 class=Header align=left>", name_anchor('general', '', T_('Frequently Asked Questions')), "</h3>\n";

   // init vars
   $TW_ = 'T_'; // for non-const translation-texts
   $faq_url = 'faq.php?';
   $omit_flags = ( $is_op ) ? HELPFLAG_HIDDEN : HELPFLAG_OPS_ONLY;
   $qpart_faqhidden = ( $is_admin )
      ? ' 1'
      : " entry.Flags < $omit_flags AND parent.Flags < $omit_flags"; //need a viewable root

   $arr_languages = get_language_descriptions_translated();
   $lang = get_request_arg('lang', 0);
   if ( !$lang ) $lang = recover_language( $player_row );

   // parse search terms
   $is_search = @$_REQUEST['search'];
   $qterm = trim(get_request_arg('qterm', ''));
   $orig = ( get_request_arg('orig', '') ) ? 1 : 0;
   $err_search = '';
   $rx_term = '';
   if ( $is_search )
   {
      $err_search = check_faq_search_terms( $qterm );
      $rx_term = build_regex_term( $qterm );
      if ( (string)$err_search != '' || (string)$rx_term == '' )
         $is_search = false;
   }

   // search form
   $faq_form = new Form( 'faq', $faq_url, FORM_GET );
   $faq_form->add_row( array(
      'DESCRIPTION',    T_('Search Terms'),
      'TEXTINPUTX',     'qterm', 30, -1, $qterm,
                        array( 'title' => T_('Syntax[FAQ]: any words or characters (min. length 2)') ),
      'SUBMITBUTTONX',  'search', T_('Search'),
                        array( 'accesskey' => ACCKEY_ACT_FILT_SEARCH ),
      'TEXT',           ' ' . sprintf( T_('in language (%s)'), $arr_languages[$lang] ),
      ));
   if ( $err_search )
      $faq_form->add_row( array( 'TAB', 'TEXT', span('ErrMsg', $err_search, '(%s)'), ));
   $faq_form->add_row( array(
      'TAB',
      'CHECKBOX', 'orig', 1, T_('search only in original english FAQ (in case of untranslated entries)'), $orig ));

   echo "</td></tr><tr><td class=\"FAQsearch\">\n";
   $faq_form->echo_string(1);
   echo "<br>\n";

   $cat = @$_GET['cat'];
   if ( $cat !== 'all' && !is_numeric($cat) ) $cat = 0;
   $entry = (int)@$_GET['e'];

   if ( $cat <= 0 )
      $q_part = '';
   elseif ( $entry )
      $q_part = "AND entry.ID IN ($cat,$entry)";
   else
      $q_part = "AND (entry.ID=$cat OR entry.Parent=$cat)";

   $href_base = 'href="faq.php?read=t'.URI_AMP.'cat=';

   if ( $is_search )
   { // show matching faq-entries
      // NOTES:
      // - read whole FAQ from DB, then match on translations read from file.
      // - read data from file can be cached //TODO(later if needed)
      // - as long as no UTF8 for all languages in the DB, db-search is unreliable.

      $query =
         "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM FAQ AS entry " .
            "INNER JOIN FAQ AS parent ON parent.ID=entry.Parent " .
            "INNER JOIN TranslationTexts AS Question ON Question.ID=entry.Question " .
            "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
         "WHERE $qpart_faqhidden $q_part " .
         "ORDER BY CatOrder,ParentOrder,entry.SortOrder";
      $result = db_query( 'faq.search_entries', $query );

      $found_entries = 0;
      if ( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQsearch>\n";
         $qterm_url = 'qterm='.urlencode($qterm).URI_AMP.'search=1';

         echo faq_item_html( 0);
         $outbuf = '';
         $cntmatch = 0; // count of matches for level1-section
         while ( $row = mysql_fetch_assoc( $result ) )
         { //expand answers
            $level = $row['Level'];
            if ( $orig )
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
            if ( $level == 1 || $match )
            {
               $href = ( $row['Level'] == 1 )
                  ? $href_base.$row['ID'].URI_AMP.$qterm_url."#Title{$row['ID']}\""
                  : $href_base.$row['Parent'].URI_AMP.'e='.$row['ID'].URI_AMP.$qterm_url.'#Entry'.$row['ID'].'"';
               $attb = "name=\"Entry{$row['ID']}\"";
               $faqtext = faq_item_html( $level, $row['Flags'], $question, $answer, $href, $attb, $rx_term );
            }
            else
               $faqtext = '';

            if ( $level == 1 )
            {
               if ( $cntmatch > 0 )
                  echo $outbuf;
               $cntmatch = 0;
               $outbuf = $faqtext;
            }
            else if ( (string)$faqtext != '' )
            {
               $outbuf .= $faqtext;
               $cntmatch++;
               $found_entries++;
            }
         }
         if ( $cntmatch > 0 )
            echo $outbuf;
         echo faq_item_html(-1);
      }
      mysql_free_result($result);

      if ( $found_entries == 0 )
         echo '<strong>' . T_('No matching entries found in FAQ.') . "</strong>\n";

      $menu_array[T_('Go back to the FAQ index')]= "faq.php";
   }
   elseif ( @$_GET["read"] == 't' )
   { // show one or all faq-section(s) with all entries expanded
      $result = db_query( 'faq.find_entries',
         "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM FAQ AS entry " .
            "INNER JOIN FAQ AS parent ON parent.ID=entry.Parent " .
            "INNER JOIN TranslationTexts AS Question ON Question.ID=entry.Question " .
            "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
         "WHERE $qpart_faqhidden $q_part " .
         "ORDER BY CatOrder,ParentOrder,entry.SortOrder" );

      if ( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQread>\n";

         echo faq_item_html( 0);
         while ( $row = mysql_fetch_assoc( $result ) )
         { //expand answers
            $href = ( $row['Level'] == 1 )
               ? "href=\"faq.php#Title{$row['ID']}\""
               : $href_base.$row['Parent'].URI_AMP.'e='.$row['ID'].'#Entry'.$row['ID'].'"';
            $attb = "name=\"Entry{$row['ID']}\"";
            echo faq_item_html( $row['Level'], $row['Flags'], $TW_( $row['Q'] ), $TW_( $row['A'] ), $href, $attb );
            if ( $row['Level'] == 1 )
               echo name_anchor("Entry{$row['ID']}");
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
         "FROM FAQ AS entry " .
            "INNER JOIN FAQ AS parent ON parent.ID=entry.Parent " .
            "INNER JOIN TranslationTexts AS Question ON Question.ID=entry.Question " .
         "WHERE $qpart_faqhidden AND entry.Level<3 AND entry.Level>0 " .
         "ORDER BY CatOrder,entry.Level,entry.SortOrder" );

      if ( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQindex>\n";

         echo faq_item_html( 0);
         while ( $row = mysql_fetch_assoc( $result ) )
         { //titles only
            $href = ( $row['Level'] == 1 )
               ? $href_base.$row['ID'].'#Entry'.$row['ID'].'"'
               : $href_base.$row['Parent'].'#Entry'.$row['ID'].'"';
            echo faq_item_html( $row['Level'], $row['Flags'], $TW_( $row['Q'] ), '', $href );
            if ( $row['Level'] == 1 )
               echo name_anchor("Title{$row['ID']}");
         }
         echo faq_item_html(-1);
      }
      mysql_free_result($result);
   }
   echo "</td></tr></table>\n";

   if ( $cat !== 'all' )
      $menu_array[T_('Show the whole FAQ in one page')]= "faq.php?read=t".URI_AMP."cat=all";

   end_page(@$menu_array);
}
?>

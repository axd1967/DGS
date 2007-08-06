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

$TranslateGroups[] = "FAQ";

require_once( "include/std_functions.php" );
require_once( "include/faq_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_("FAQ"), true, $logged_in, $player_row );
   $menu_array = array();

   //$faqhide = "AND entry.Hidden='N' AND (entry.Level=1 OR parent.Hidden='N') ";
   $faqhide = " AND entry.Hidden='N' AND parent.Hidden='N'"; //need a viewable root

   echo "<table class=FAQ><tr><td>\n";
   echo "<h3 class=Header align=left><a name=\"general\">" .
         T_('Frequently Asked Questions') . "</a></h3>\n";


   $cat = @$_GET['cat'];
   if( $cat !== 'all' && !is_numeric($cat) ) $cat = 0;
   if( @$_GET["read"] == 't' )
   { //expand answers
      $result = mysql_query(
         "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
         "Question.Text AS Q, Answer.Text AS A, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM (FAQ AS entry, FAQ AS parent, TranslationTexts AS Question) " .
         "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
         "WHERE parent.ID=entry.Parent$faqhide AND Question.ID=entry.Question " .
         ( $cat === 'all' ? '' : "AND (entry.ID=$cat OR entry.Parent=$cat) " ) .
         "ORDER BY CatOrder,ParentOrder,entry.SortOrder")
      or error('mysql_query_failed', 'faq.find_entries');

      if( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQread>\n";

         echo faq_item_html( 0);
         while( $row = mysql_fetch_assoc( $result ) )
         { //expand answers
            echo faq_item_html( $row['Level']
                           , T_( $row['Q'] ), T_( $row['A'] )
                           , $row['Level'] == 1
                              ? "href=\"faq.php#Title{$row['ID']}\""
                              : "name=\"Entry{$row['ID']}\""
                           );
            if( $row['Level'] == 1 )
               echo "<a name=\"Entry{$row['ID']}\"></a>\n";
         }
         echo faq_item_html(-1);
      }
      $menu_array[T_('Go back to the FAQ index')]= "faq.php";
   }
   else
   { //titles only
      $result = mysql_query(
         "SELECT entry.*, Question.Text AS Q, " .
         "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
         "FROM FAQ AS entry, FAQ AS parent, TranslationTexts AS Question " .
         "WHERE parent.ID=entry.Parent$faqhide AND Question.ID=entry.Question " .
            "AND entry.Level<3 AND entry.Level>0 " .
         "ORDER BY CatOrder,entry.Level,entry.SortOrder")
         or error('mysql_query_failed', 'faq.find_titles');

      if( mysql_num_rows($result) > 0 )
      {
         echo "</td></tr><tr><td class=FAQindex>\n";

         echo faq_item_html( 0);
         $tmp = 'href="faq.php?read=t'.URI_AMP.'cat=';
         while( $row = mysql_fetch_assoc( $result ) )
         { //titles only
            echo faq_item_html( $row['Level']
                           , T_( $row['Q'] ), ''
                           , $row['Level'] == 1
                              ? $tmp.$row['ID'].'#Entry'.$row['ID'].'"'
                              : $tmp.$row['Parent'].'#Entry'.$row['ID'].'"'
                           );
            if( $row['Level'] == 1 )
               echo "<a name=\"Title{$row['ID']}\"></a>\n";
         }
         echo faq_item_html(-1);
      }
   }
   echo "</td></tr></table>\n";

   if( $cat !== 'all' )
      $menu_array[T_('Show the whole FAQ in one page')]= "faq.php?read=t".URI_AMP."cat=all";

   end_page(@$menu_array);
}
?>

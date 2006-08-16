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

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  start_page(T_("FAQ"), true, $logged_in, $player_row );

  //$blk='ul';
  $blk='blockquote';
  //$faqhide = "AND entry.Hidden='N' AND (entry.Level=1 OR parent.Hidden='N') ";
  $faqhide = "AND entry.Hidden='N' AND parent.Hidden='N' "; //need a viewable root

  echo "<table align=center width=\"87%\" border=0><tr><td>\n";
  echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
    T_('Frequently Asked Questions') . "</font></h3>\n";


  $cat = @$_GET['cat'];
  if( $cat !== 'all' && !is_numeric($cat) ) $cat = 0;
  if( @$_GET["read"] == 't' )
  { //expand answers
     $result = mysql_query(
        "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
        "Question.Text AS Q, Answer.Text AS A, " .
        "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
        "FROM FAQ AS entry, FAQ AS parent, TranslationTexts AS Question " .
        "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
        "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question $faqhide" .
        ( $cat === 'all' ? '' : "AND ( entry.Parent = $cat OR entry.ID = $cat ) " ) .
        "ORDER BY CatOrder,ParentOrder,entry.SortOrder")
      or error('mysql_query_failed', 'faq.find_entries');

     if( mysql_num_rows($result) > 0 )
     {
        echo "<$blk><table width=\"93%\" cellpadding=2 cellspacing=0 border=0><tr><td>\n";

        $first = -1;
        while( $row = mysql_fetch_array( $result ) )
        {
           if( $row['Level'] == 1 )
           {
              if( !$first )
                 echo "</ul>\n";
              if( $first >= 0 )
                 echo "<hr><p></p>";
              $first = 1;
              echo '<b><A href="faq.php">' . T_( $row['Q'] ) . "</A></b><p></p>\n";
           }
           else
           {
              if( $first )
                 echo "<ul>\n";
              $first = 0;
              echo '<li><A name="Entry' . $row["ID"] . '"></a><b>' . T_( $row['Q'] ) .
                 "</b>\n<p></p>\n" 
                 //. add_line_breaks( T_( $row['A'] ) ) 
                 . make_html_safe( T_( $row['A'] ) , 'faq') 
                 . "<br>&nbsp;<p></p></li>\n";
           }
        }
        if( !$first )
          echo "</ul>\n";
        echo "</td></tr></table></$blk>\n";
     }
  }
  else
  { //titles only
     $result = mysql_query(
        "SELECT entry.*, Question.Text AS Q, " .
        "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
        "FROM FAQ AS entry, FAQ AS parent, TranslationTexts AS Question " .
        "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question $faqhide" .
         "AND entry.Level<3 AND entry.Level>0 " .
        "ORDER BY CatOrder,entry.Level,entry.SortOrder")
        or error('mysql_query_failed', 'faq.find_titles');

     if( mysql_num_rows($result) > 0 )
     {
        echo "<$blk><table width=\"93%\" border=0><tr><td>\n";

        $first = -1;
        while( $row = mysql_fetch_array( $result ) )
        {
           $question = (empty($row['Q']) ? '-' : T_($row['Q']));

           if( $row['Level'] == 1 )
           {
              if( !$first )
                 echo "</ul></td></tr></table>\n";
              if( $first >= 0 )
                 echo "<p></p>";
              $first = 1;
              echo '<b><A href="faq.php?read=t'.URI_AMP.'cat=' . $row['ID']  .
                 "\">$question</A></b>\n";
           }
           else
           {
              if( $first )
                 echo "<table><tr><td><ul>\n";
              $first = 0;
              echo '<li><A href="faq.php?read=t'.URI_AMP.'cat=' . $row['Parent'] .
                 '#Entry' . $row['ID'] . "\">$question</A></li>\n";
           }
        }
        if( !$first )
          echo "</ul></td></tr></table>\n";
        echo "</td></tr></table></$blk>\n";
     }
  }
  echo "</td></tr></table>\n";

  if( $cat !== 'all' )
     $menu_array = array( T_('Show the whole FAQ in one page') => "faq.php?read=t".URI_AMP."cat=all" );

   end_page(@$menu_array);
}
?>
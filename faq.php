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

$TranslateGroups[] = "FAQ";

require_once( "include/std_functions.php" );

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  start_page(T_("FAQ"), true, $logged_in, $player_row );

  echo "<table align=center width=\"87%\" border=0><tr><td>\n";
  echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
    T_('Frequently Asked Questions') . "</font></h3>\n";


  $cat = @$_GET['cat'];
  if( $cat !== 'all' && !is_numeric($cat) ) $cat = 0;
  if( @$_GET["read"] == 't' )
  {
     $result = mysql_query(
        "SELECT entry.*, parent.SortOrder AS ParentOrder, " .
        "Question.Text AS Q, Question.Translatable, Answer.Text AS A, " .
        "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
        "FROM FAQ AS entry, FAQ AS parent, TranslationTexts AS Question " .
        "LEFT JOIN TranslationTexts AS Answer ON Answer.ID=entry.Answer " .
        "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question " .
        ( $cat === 'all' ? '' : "AND ( entry.Parent = $cat OR entry.ID = $cat ) " ) .
        "ORDER BY CatOrder,ParentOrder,entry.SortOrder")
        or error("internal_error");

     echo "<ul><table width=\"93%\" cellpadding=2 cellspacing=0 border=0><tr><td>\n";

     $first = true;
     while( $row = mysql_fetch_array( $result ) )
     {
        if( $row['Level'] == 1 )
        {
           if( !$first )
              echo "</ul><hr>\n";
           echo '<p><b><A href="faq.php">' . T_( $row['Q'] ) . "</A></b><ul>\n";
           $first = false;
        }
        else
        {
           echo '<li><A name="Entry' . $row["ID"] . '"></a><b>' . T_( $row['Q'] ) .
              "</b>\n<p>\n" . add_line_breaks( T_( $row['A'] ) ) . "<br>&nbsp;<p>\n";
        }
     }


     echo "</ul></table></ul></table>\n";
  }
  else
  {
     $result = mysql_query(
        "SELECT entry.*, Question.Text AS Q, Question.Translatable, " .
        "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
        "FROM FAQ AS entry, FAQ AS parent, TranslationTexts AS Question " .
        "WHERE entry.Parent = parent.ID AND Question.ID=entry.Question " .
        "AND entry.Level<3 AND entry.Level>0 " .
        "ORDER BY CatOrder,entry.Level,entry.SortOrder");

     echo "<ul><table><tr><td>\n";
     $first = true;

     while( $row = mysql_fetch_array( $result ) )
     {
        $question = (empty($row['Q']) ? '-' : T_($row['Q']));

        if( $row['Level'] == 1 )
        {
           if( $first )
              $first = false;
           else
              echo "</ul></table>\n";

           echo '<p><b><A href="faq.php?read=t&cat=' . $row['ID'] . "\">$question</A></b>\n";
           echo "<table><tr><td><ul>\n";
        }
        else
           echo '<li><A href="faq.php?read=t&cat=' . $row['Parent'] .
              '#Entry' . $row['ID'] . "\">$question</A>\n";
     }

     echo "</ul></table></table></ul></table>\n";
  }

  if( $cat !== 'all' )
     $menu_array = array( T_('Show the whole FAQ in one page') => "faq.php?read=t&cat=all" );

   end_page(@$menu_array);
}
?>
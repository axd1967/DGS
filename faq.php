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

require( "include/std_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  start_page(T_("FAQ"), true, $logged_in, $player_row );

  echo "<table align=center width=\"85%\" border=0><tr><td>\n";
  echo "<h3 align=left><a name=\"general\"></a><font color=$h3_color>" .
    T_('Frequently Asked Questions') . "</font></h3>\n";


  if( $_GET["read"] == 't' )
  {
     $cat = is_numeric($_GET["cat"]) ? $_GET["cat"] : 0;
     $result = mysql_query( "SELECT entry.*, parent.SortOrder AS ParentOrder " .
                            "FROM FAQ AS entry, FAQ AS parent " .
                            "WHERE entry.Parent = parent.ID " .
                            "AND ( entry.Parent = $cat OR entry.ID = $cat ) " .
                            "ORDER BY ParentOrder,entry.SortOrder" );

     echo "<ul><table width=\"100%\" cellpadding=2 cellspacing=0 border=0><tr><td>\n";

     while( $row = mysql_fetch_array( $result ) )
     {
        if( $row['Level'] == 1 )
        {
           echo '<h4><A href="faq.php">' . $row['Question'] . "</A></h4><ul>\n";
        }
        else
           echo '<p><A name="Entry' . $row["ID"] . '"><li><h4>' . $row['Question'] . '</h4><P>' .$row['Answer'];
     }


     echo "</table></table></ul></ul>\n";
  }
  else
  {
     $result = mysql_query("SELECT entry.*, " .
                           "IF(entry.Level=1,entry.SortOrder,parent.SortOrder) AS CatOrder " .
                           "FROM FAQ AS entry, FAQ AS parent " .
                           "WHERE entry.Parent = parent.ID " .
                           "AND entry.Level<3 AND entry.Level>0 " .
                           "ORDER BY CatOrder,entry.Level,entry.SortOrder");

     echo "<ul><ul><table>\n";

     while( $row = mysql_fetch_array( $result ) )
     {
        $question = (empty($row['Question']) ? '-' : $row['Question']);

        if( $row['Level'] == 1 )
        {
           echo "</table></ul>\n";
           echo '<A href="faq.php?read=t&cat=' . $row['ID'] . "\">$question</A>\n";
           echo "<ul><table width=\"100%\" cellpadding=2 cellspacing=0 border=0><tr><td>\n";
        }
        else
           echo '<li><A href="faq.php?read=t&cat=' . $row['Parent'] .
              '#Entry' . $row['ID'] . "\">$question</A>\n";
     }


     echo "</table></table></ul></ul>\n";
  }

  end_page();
}
?>

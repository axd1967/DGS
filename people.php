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

$TranslateGroups[] = "Docs";
$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );

function add_contributor( $text, $contributor, $uid = -1 )
{
  echo "<tr><td>$text</td>\n";
  if( $uid === -1 )
    echo "<td><b>$contributor</b></td></tr>\n";
  else
    echo "<td><a href=\"userinfo.php?uid=$uid\">$contributor</a></td></tr>\n";
}

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  start_page(T_("People"), true, $logged_in, $player_row );

  echo "<table align=center><tr><td colspan=2>\n";
  echo "<center><h3><font color=$h3_color>" .
    T_('Contributors to Dragon') . "</font></h3></center>\n";
  echo "</td></tr>\n";

  add_contributor( T_("Current maintainer and founder of Dragon"), "Erik Ouchterlony" );


  $first = T_("Developer");
  foreach( array( "Ragnar Ouchterlony",
                  "Rod Ival",
                  "Kris Van Hulle",
                  ) as $name )
  {
      add_contributor( $first, $name );
      $first = '';
  }


  echo "<tr><td colspan=2>&nbsp;<p>\n";
  echo "<center><h3><font color=$h3_color>" .
     T_("FAQ") . "</font></h3></center>\n";
  echo "</td></tr>\n";

  add_contributor( T_("FAQ editor"), "Bjørn Ingmar Berg" );

  $query_result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level FROM Players " .
                               "WHERE (Adminlevel & " . ADMIN_FAQ . ") > 0" );

  $first = T_("FAQ co-editor");
  while( $row = mysql_fetch_array( $query_result ) )
  {
      //add_contributor( , "Frank Schlüter" );
      if( $row['ID'] != 0 ) //?? skip "Bjørn Ingmar Berg"
      {
         add_contributor( $first,
                          make_html_safe($row['Name']),
                          $row['ID'] );
         $first = '';
      }
  }


  echo "<tr><td colspan=2>&nbsp;<p>\n";
  echo "<center><h3><font color=$h3_color>" .
     T_('Current translators') . "</font></h3></center>\n";
  echo "</td></tr>\n";

  $query_result = mysql_query( "SELECT ID,Handle,Name,Translator FROM Players " .
                               "WHERE LENGTH(Translator)>0" );

  $translator_list = array();
  while( $row = mysql_fetch_array( $query_result ) )
  {
     $languages = explode(',', $row['Translator']);
     foreach( $languages as $language )
        {
           list($lang, $charenc) = explode('.', $language, 2);

           $lang_name = T_($known_languages[$lang][$charenc]);

           if( !isset($translator_list[$lang_name]) )
              $translator_list[$lang_name] = array();

           array_push($translator_list[$lang_name], $row);
        }
  }

  ksort($translator_list);

  foreach( $translator_list as $language => $translators )
     {
        $first = true;
        foreach( $translators as $translator )
           {
              add_contributor( $first ? $language : '',
                               make_html_safe($translator['Name']),
                               $translator['ID'] );
              $first = false;
           }
     }

  echo "</table>\n";
  echo "<br>&nbsp;\n";

  end_page();
}

?>

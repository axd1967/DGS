<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

function add_contributor( $text, $contributor, $uid = -1 )
{
  echo "<tr><td>$text</td>\n";
  if( $uid === -1 )
    echo "<td><b>$contributor</b></td></tr>\n";
  else
    echo "<td><a href=\"userinfo.php?uid=$uid\">$contributor</td></tr>\n";
}

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  start_page(T_("People"), true, $logged_in, $player_row );

  echo "<table align=center><tr><td colspan=2>\n";
  echo "<center><h3><font color=$h3_color>" .
    T_('Contributors to Dragon') . "</font></h3></center>\n";
  echo "</td></tr>\n";

  add_contributor( T_("Current maintainer and founder of Dragon"), "Erik Ouchterlony" );
  add_contributor( T_("Developer"), "Ragnar Ouchterlony" );

  echo "<tr><td colspan=2>\n";
  echo "<center><h3><font color=$h3_color>" .
    T_('Current translators') . "</font></h3></center>\n";
  echo "</td></tr>\n";

  $query_result = mysql_query( "SELECT ID,Handle,Name,Translator FROM Players " .
                               "WHERE LENGTH(Translator)>0" );

  $k_langs = get_known_languages_with_full_names();
  $per_language = array();
  while( $row = mysql_fetch_array( $query_result ) )
    {
      $langs = explode(',', $row['Translator']);
      foreach( $langs as $lang )
        {
          if( array_key_exists( $lang, $per_language ) )
            array_push( $per_language[$lang], $row );
          else
            $per_language[$lang] = array( $row );
        }
    }

  foreach( $per_language as $lang => $translators )
    {
      $first = true;
      foreach( $translators as $translator )
        {
          $text = '';
          if( $first )
            {
              $text = T_($k_langs[$lang]);
              $first = false;
            }

          add_contributor( $text,
                           $translator['Name'],
                           $translator['ID'] );
        }
    }

  echo "</table>\n";
  echo "<br>&nbsp;\n";

  end_page();
}

?>

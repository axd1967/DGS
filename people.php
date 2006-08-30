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

$TranslateGroups[] = "Docs";
$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );

$cols = 3;
function add_contributor( $text, $uref='', $name=false, $handle=false, $img='' )
{
   echo "<tr><td>$text</td>\n";
   //if( $img )
      echo "<td>$img</td>\n";   
   echo "<td><b>" .
      user_reference( ( $uref > '' ? REF_LINK : 0 ), 1, 'black', $uref, $name, $handle) .
      "</b></td></tr>\n";
}

{
  connect2mysql();

  $logged_in = who_is_logged( $player_row);

  start_page(T_("People"), true, $logged_in, $player_row );

  echo "<table align=center><tr><td colspan=$cols>\n";
  echo "<center><h3><font color=$h3_color>" .
    T_('Contributors to Dragon') . "</font></h3></center>\n";
  echo "</td></tr>\n";

  add_contributor( T_("Current maintainer and founder of Dragon"), 2, 'Erik Ouchterlony' );


  $first = T_("Developer");
  foreach( array( 'ragou' => 'Ragnar Ouchterlony',
                  'rodival' => 'Rod Ival',
                  4991 => 'Kris Van Hulle', //uid=4991 handle='uXd' ???
                  ) as $uref => $name )
  {
      add_contributor( $first, $uref, $name);
      $first = '';
  }


  echo "<tr><td colspan=$cols><p>&nbsp;</p>\n";
  echo "<center><h3><font color=$h3_color>" .
     T_("FAQ") . "</font></h3></center>\n";
  echo "</td></tr>\n";


  $FAQexclude = array( 'ejlo', 'rodival');
  $FAQmain = 'Ingmar';
  $query_result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level".
            ",(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel" .
                               " FROM Players" .
                               " WHERE (Adminlevel & " . ADMIN_FAQ . ") > 0" .
                               " AND Handle='$FAQmain'" .
                               " ORDER BY ID" )
     or error('mysql_query_failed', 'people.faq_main');

  if( $row = mysql_fetch_array( $query_result ) )
  {
         add_contributor( T_("FAQ editor"),
                          $row['ID'], $row['Name'], $row['Handle'] );
         $FAQexclude[] = $FAQmain; 
  } else $FAQmain='';


  $query_result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level".
                               ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            ",(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel" .
                               " FROM Players" .
                               " WHERE (Adminlevel & " . ADMIN_FAQ . ") > 0" .
                               " ORDER BY ID" )
     or error('mysql_query_failed', 'people.faq_admins');

  $first = T_("FAQ co-editor");
  while( $row = mysql_fetch_array( $query_result ) )
  {
      if( in_array( $row['Handle'], $FAQexclude) )
         continue;
      add_contributor( $first,
                       $row['ID'], $row['Name'], $row['Handle'] );
      $first = '';
  }


  echo "<tr><td colspan=$cols><p>&nbsp;</p>\n";
  echo "<center><h3><font color=$h3_color>" .
     T_('Current translators') . "</font></h3></center>\n";
  echo "</td></tr>\n";

  $query_result = mysql_query( "SELECT ID,Handle,Name,Translator" .
                               ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            ",(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel" .
                               " FROM Players" .
                               " WHERE LENGTH(Translator)>0" .
                               " ORDER BY Lastaccess DESC,ID" )
     or error('mysql_query_failed', 'people.translators');

  $translator_list = array();
  while( $row = mysql_fetch_array( $query_result ) )
  {
     $languages = explode( LANG_TRANSL_CHAR, $row['Translator']);
     foreach( $languages as $language )
        {
           @list($lang, $charenc) = explode( LANG_CHARSET_CHAR, $language, 2);

           $lang_name = T_($known_languages[$lang][$charenc]);

           if( !isset($translator_list[$lang_name]) )
              $translator_list[$lang_name] = array();

           array_push($translator_list[$lang_name], $row);
        }
  }

  ksort($translator_list);

  $info = $logged_in && $player_row['admin_level'] & ADMIN_TRANSLATORS ;
  foreach( $translator_list as $language => $translators )
     {
        $first = $language;
        foreach( $translators as $translator )
           {
              add_contributor( $first,
                               $translator['ID'],
                   ( $info ? '['.date($date_fmt2, $translator['Lastaccess']).'] ' : '') .
                               $translator['Name'], $translator['Handle']
                               , activity_string( $translator['ActivityLevel'])
                               );
              $first = '';
           }
     }

  echo "</table>\n";
  echo "<br>&nbsp;\n";

  end_page();
}

?>

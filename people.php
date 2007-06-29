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

require_once( "include/std_functions.php" );

$cols = 2;
function add_contributor( $text, $uref='', $name=false, $handle=false)
{
   echo "<tr><td>$text</td>\n";
   echo "<td><b>" .
      user_reference( ( $uref > '' ? REF_LINK : 0 ), 1, 'black', $uref, $name, $handle) .
      "</b></td></tr>\n";
}

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   start_page(T_("People"), true, $logged_in, $player_row );

   echo "<table align=center><tr><td colspan=$cols>\n";
   echo "<h3 class=Header>" . T_('Contributors to Dragon') . "</h3>\n";
   echo "</td></tr>\n";


   add_contributor( T_("Current maintainer and founder of Dragon")
                     , 2, 'Erik Ouchterlony' );


   $first = T_("Developer");
   foreach( array( 'ragou' => 'Ragnar Ouchterlony',
                  'rodival' => 'Rod Ival',
                  4991 => 'Kris Van Hulle', //uid=4991 handle='uXd' ???
                  ) as $uref => $name )
   {
      add_contributor( $first, $uref, $name);
      $first = '';
   }

   //---------

   echo "<tr><td colspan=$cols><p>&nbsp;</p>\n";
   echo "<h3 class=Header>" . T_("FAQ") . "</h3>\n";
   echo "</td></tr>\n";

   $extra_info = $logged_in && $player_row['admin_level'] & ADMIN_ADMINS ;
   if( $extra_info )
      $FAQexclude = array();
   else
      $FAQexclude = array( 'ejlo', 'rodival');
   $FAQmain = 'Ingmar';
   $FAQmainID = 0;

   $query_result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level".
            ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            " FROM Players" .
            " WHERE (Adminlevel & " . ADMIN_FAQ . ") > 0" .
            " ORDER BY ID" )
      or error('mysql_query_failed', 'people.faq_admins');

   $FAQ_list = array();
   while( $row = mysql_fetch_array( $query_result ) )
   {
      $uid = $row['ID'];
      if( $extra_info )
      {
         $query = 'SELECT UNIX_TIMESTAMP(T.Date) AS Date'
            . ' FROM (FAQlog AS T)'
            . " WHERE T.uid=$uid"
            . ' ORDER BY T.Date DESC LIMIT 1';
         $tmp = mysql_single_fetch( 'people.faq_admins.lastupdate', $query);
         $row['LastUpdate'] = $tmp ? $tmp['Date'] : 0;
      }

      if( $row['Handle'] == $FAQmain )
         $FAQmainID = $uid;
      
      $FAQ_list[$uid] = $row;
   }

   if( $FAQmainID > 0 )
   {
      $row = $FAQ_list[$FAQmainID];
      add_contributor( T_("FAQ editor"),
                     $row['ID'],
         ( $extra_info && $row['LastUpdate']
            ? '['.date($date_fmt2, $row['LastUpdate']).'] ' : '') .
                     $row['Name'], $row['Handle']
                     );
      $FAQexclude[] = $FAQmain;
   } else $FAQmain='';

   $first = T_("FAQ co-editor");
   foreach( $FAQ_list as $uid => $row )
   {
      if( in_array( $row['Handle'], $FAQexclude) )
         continue;
      add_contributor( $first,
                     $row['ID'],
         ( $extra_info && $row['LastUpdate']
            ? '['.date($date_fmt2, $row['LastUpdate']).'] ' : '') .
                     $row['Name'], $row['Handle']
                     );
      $first = '';
   }

   //---------

   echo "<tr><td colspan=$cols><p>&nbsp;</p>\n";
   echo "<h3 class=Header>" . T_('Current translators') . "</h3>\n";
   echo "</td></tr>\n";

   $extra_info = $logged_in && $player_row['admin_level'] & ADMIN_TRANSLATORS ;

   $query_result = mysql_query( "SELECT ID,Handle,Name,Translator" .
            ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            " FROM Players" .
            " WHERE LENGTH(Translator)>0" .
            " ORDER BY ID" )
      or error('mysql_query_failed', 'people.translators');

   $translator_list = array();
   while( $row = mysql_fetch_array( $query_result ) )
   {
      $uid = $row['ID'];
      $languages = explode( LANG_TRANSL_CHAR, $row['Translator']);
      foreach( $languages as $language )
      {
         @list($browsercode, $charenc) = explode( LANG_CHARSET_CHAR, $language, 2);
         // Normalization for the array_key_exists() matchings
         $browsercode = strtolower(trim($browsercode));
         $charenc = strtolower(trim($charenc));

         $langname = T_($known_languages[$browsercode][$charenc]);

         if( $extra_info )
         {
            $query = 'SELECT UNIX_TIMESTAMP(T.Date) AS Date'
               . ' FROM (Translationlog AS T,TranslationLanguages AS L)'
               . ' WHERE T.Language_ID=L.ID'
                  . " AND T.Player_ID=$uid AND L.Language='$language'"
               . ' ORDER BY T.Date DESC LIMIT 1';
            $tmp = mysql_single_fetch( 'people.translators.lastupdate', $query);
            $row['LastUpdate'] = $tmp ? $tmp['Date'] : 0;
         }

         $translator_list[$langname][$uid] = $row;
      }
   }

   ksort($translator_list);

   foreach( $translator_list as $langname => $translators )
   {
      //ksort($translators);
      $first = $langname;
      foreach( $translators as $row )
      {
         add_contributor( $first,
                        $row['ID'],
            ( $extra_info && $row['LastUpdate']
               ? '['.date($date_fmt2, $row['LastUpdate']).'] ' : '') .
                        $row['Name'], $row['Handle']
                        );
         $first = '';
      }
   }

   echo "</table>\n";
   echo "<br>&nbsp;\n";

   end_page();
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

function add_contributor( $text=false, $uref='', $name=false, $handle=false, $extra='')
{
   static $started = false;

   if( $text === false )
   {
      if( $started )
         echo "</table>\n";
      $started = false;
      return 0;
   }

   if( !$started )
   {
      echo "<table class=People>\n";
      $started = true;
   }

   echo "<tr><td class=Rubric>$text</td>\n"
      . "<td class=People>"
      . user_reference( ( $uref > '' ? REF_LINK : 0 ), 1, '', $uref, $name, $handle)
      . "</td><td>"
      . ( $extra ? "<span>[$extra]</span>" : '' )
      . "</td></tr>\n";
}


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'People'; //temporary solution to CSS problem
   start_page(T_('People'), true, $logged_in, $player_row );


   //---------
   section( 'Contributors', T_('Contributors to Dragon'));

   add_contributor( T_("Current maintainer and founder of Dragon")
                     , 2, 'Erik Ouchterlony' );


   $first = T_("Developer");
   foreach( array('rodival' => 'Rod Ival',
                  'JUG' => 'Jens-Uwe Gaspar',
                  'ragou' => 'Ragnar Ouchterlony',
                  4991 => 'Kris Van Hulle', //uid=4991 handle='uXd' ???
                  ) as $uref => $name )
   {
      add_contributor( $first, $uref, $name);
      $first = '';
   }

   add_contributor();

   //---------
   section( 'FAQ', T_('FAQ'));

   $extra_info = $logged_in && (@$player_row['admin_level'] & ADMIN_ADMINS);
   if( $extra_info )
      $FAQexclude = array();
   else
      $FAQexclude = array( 'ejlo', 'rodival');
   $FAQmain = 'Ingmar';
   $FAQmainID = 0;

   $result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level".
            ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            " FROM Players" .
            " WHERE (Adminlevel & " . ADMIN_FAQ . ") > 0" .
            " ORDER BY ID" )
      or error('mysql_query_failed', 'people.faq_admins');

   $FAQ_list = array();
   while( $row = mysql_fetch_array( $result ) )
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
   mysql_free_result($result);

   if( $FAQmainID > 0 )
   {
      $row = $FAQ_list[$FAQmainID];
      add_contributor( T_("FAQ editor"),
                     $row['ID'], $row['Name'], $row['Handle'],
         ( $extra_info && $row['LastUpdate']
            ? date($date_fmt2, $row['LastUpdate']) : '')
                     );
      $FAQexclude[] = $FAQmain;
   } else $FAQmain='';

   $first = T_("FAQ co-editor");
   foreach( $FAQ_list as $uid => $row )
   {
      if( in_array( $row['Handle'], $FAQexclude) )
         continue;
      add_contributor( $first,
                     $row['ID'], $row['Name'], $row['Handle'],
         ( $extra_info && $row['LastUpdate']
            ? date($date_fmt2, $row['LastUpdate']) : '')
                     );
      $first = '';
   }

   add_contributor();

   //---------
   section( 'Translators', T_('Current translators'));

   $extra_info = $logged_in && (@$player_row['admin_level'] & ADMIN_TRANSLATORS);

   $result = mysql_query( "SELECT ID,Handle,Name,Translator" .
            ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            " FROM Players" .
            " WHERE LENGTH(Translator)>0" .
            " ORDER BY ID" )
      or error('mysql_query_failed', 'people.translators');

   $translator_list = array();
   while( $row = mysql_fetch_array( $result ) )
   {
      $uid = $row['ID'];
      $languages = explode( LANG_TRANSL_CHAR, $row['Translator']);
      foreach( $languages as $language )
      {
         @list($browsercode, $charenc) = explode( LANG_CHARSET_CHAR, $language, 2);
         // Normalization for the array_key_exists() matchings
         $browsercode = strtolower(trim($browsercode));
         $charenc = strtolower(trim($charenc));

         $langname = (string)@$known_languages[$browsercode][$charenc];
         if( $langname )
            $langname = T_($langname);
         else
            $langname = $language;

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
   mysql_free_result($result);

   ksort($translator_list);

   foreach( $translator_list as $langname => $translators )
   {
      //ksort($translators);
      $first = $langname;
      foreach( $translators as $row )
      {
         add_contributor( $first,
                        $row['ID'], $row['Name'], $row['Handle'],
            ( $extra_info && $row['LastUpdate']
               ? date($date_fmt2, $row['LastUpdate']) : '')
                        );
         $first = '';
      }
   }

   add_contributor();


   end_page();
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );
$ThePage = new Page('People');

function add_contributor_link( $text=false, $link, $extra='')
{
   static $started = false;
   static $c = 0;

   if( $text === false )
   {
      if( $started )
         echo "</table>\n";
      $started = false;
      return -1;
   }
   if( $text )
   {
      $c = 0;
      $class = 'First';
   }
   else
   {
      $c=($c % LIST_ROWS_MODULO)+1;
      $class = 'Row'.$c;
   }

   if( !$started )
   {
      echo "<table class=People>\n";
      $started = true;
   }

   echo "<tr class=$class><td class=Rubric>$text</td>\n"
      . "<td class=People>$link</td>"
      . "<td class=Extra>"
      . ( $extra ? "<span>[$extra]</span>" : '' )
      . "</td></tr>\n";

   return $c;
}

function add_contributor( $text=false, $uref='', $name=false, $handle=false, $extra='' )
{
   $ulink = user_reference( ( $uref > '' ? REF_LINK : 0 ), 1, '', $uref, $name, $handle);
   add_contributor_link( $text, $ulink, $extra );
}

function build_icon( $icon_name, $text )
{
   global $base_path;
   return image( "{$base_path}images/$icon_name", $text, $text );
}



{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

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
   section( 'FAQ', T_('FAQ editors'));

   $extra_info = $logged_in && (@$player_row['admin_level'] & ADMIN_FAQ);
   $FAQexclude = array( 'ejlo', 'rodival', 'jug' );
   $FAQmain = 'Ingmar';
   //$FAQmain = 'jug';
   $FAQmainID = 0;

   $result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level".
            ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            " FROM Players" .
            " WHERE Adminlevel>0 AND (Adminlevel & " . ADMIN_FAQ . ") > 0" .
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
      add_contributor( T_("FAQ editor"), $row['ID'], $row['Name'], $row['Handle'],
         ( ($extra_info && $row['LastUpdate']) ? date(DATE_FMT2, $row['LastUpdate']) : '') );
      $FAQexclude[] = $FAQmain;
   }
   else
      $FAQmain='';

   $first = T_("FAQ co-editor");
   foreach( $FAQ_list as $uid => $row )
   {
      if( in_array( $row['Handle'], $FAQexclude) )
         continue;
      add_contributor( $first, $row['ID'], $row['Name'], $row['Handle'],
         ( ($extra_info && $row['LastUpdate']) ? date(DATE_FMT2, $row['LastUpdate']) : '') );
      $first = '';
   }

   add_contributor();

   //---------
   section( 'Moderators', T_('Forum moderators'));

   $MODexclude = array( 'ejlo', 'rodival' );

   $result = mysql_query( "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level".
            ",UNIX_TIMESTAMP(Lastaccess) AS Lastaccess".
            " FROM Players" .
            " WHERE Adminlevel>0 AND (Adminlevel & " . ADMIN_FORUM . ") > 0" .
            " ORDER BY ID" )
      or error('mysql_query_failed', 'people.forum_moderators');

   $first = T_('Forum moderator');
   while( $row = mysql_fetch_array( $result ) )
   {
      if( in_array( $row['Handle'], $MODexclude) )
         continue;

      add_contributor( $first, $row['ID'], $row['Name'], $row['Handle'], '' );
      $first = '';
   }
   mysql_free_result($result);

   add_contributor();

   //---------
   section( 'Translators', T_('Translators'));

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
         add_contributor( $first, $row['ID'], $row['Name'], $row['Handle'],
            ( ($extra_info && $row['LastUpdate']) ? date(DATE_FMT2, $row['LastUpdate']) : '') );
         $first = '';
      }
   }

   add_contributor();

   //---------
   section( 'Other credits', T_('Other credits'));

   $images_str = // image + originating icon-name from silk-collection
        build_icon('professional.gif', sprintf( T_('Professional from [%s]'), 'user_suite + wand'))
      . build_icon('teacher.gif', sprintf( T_('Teacher from [%s]'), 'user_comment'))
      . build_icon('robot.gif', sprintf( T_('Robot from [%s]'), 'computer'))
      . build_icon('team.gif', sprintf( T_('Team from [%s]'), 'group'))
      ;
   add_contributor_link(
      sprintf( T_('Taken and modified 4 icons from Mark James\' silk icons '
                . 'collection (version 1.3): %s'), $images_str ),
      anchor('http://www.famfamfam.com/archive/silk-icons-thats-your-lot/') );

   add_contributor();


   end_page();
}

?>

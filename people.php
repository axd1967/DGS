<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';

$GLOBALS['ThePage'] = new Page('People');

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
   return image( "{$base_path}images/$icon_name", $text, null );
}

function get_executives( $level )
{
   $out = array();
   // NOTE: don't include: ADMIN_TRANSLATORS, ADMIN_DATABASE, ADMIN_SKINNER
   if( $level & ADMIN_SUPERADMIN )
      $out[] = T_('Admin manager');
   if( $level & ADMIN_DEVELOPER )
      $out[] = T_('Site');
   if( $level & ADMIN_PASSWORD )
      $out[] = T_('Password');
   if( $level & ADMIN_FAQ )
      $out[] = T_('FAQ editor');
   if( $level & ADMIN_FORUM )
      $out[] = T_('Forum moderator');
   if( $level & ADMIN_TOURNAMENT )
      $out[] = T_('Tournaments');
   if( $level & ADMIN_GAME )
      $out[] = T_('Game & Rating');
   if( ALLOW_FEATURE_VOTE && ($level & ADMIN_FEATURE) )
      $out[] = T_('Feature-Vote');
   if( ALLOW_SURVEY_VOTE && ($level & ADMIN_SURVEY) )
      $out[] = T_('Survey');
   return array( count($out), implode(', ', $out) );
}



{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   $img_admin = MINI_SPACING . echo_image_admin(ADMINGROUP_EXECUTIVE);

   start_page(T_('People'), true, $logged_in, $player_row );

   //---------
   section( 'Contributors', T_('Contributors to Dragon'));

   add_contributor( T_("Current maintainer and founder of Dragon")
                     , 2, 'Erik Ouchterlony' );


   $first = T_("Developer");
   foreach( array('rodival' => 'Rod Ival',
                  'JUG' => 'Jens-Uwe Gaspar',
                  //'ragou' => 'Ragnar Ouchterlony',
                  //4991 => 'Kris Van Hulle', //uid=4991 handle='uXd' ???
                  ) as $uref => $name )
   {
      add_contributor( $first, $uref, $name);
      $first = '';
   }

   add_contributor();


   //---------
   section( 'FAQ', T_('FAQ editors') . $img_admin, 'faq' );

   $extra_info = $logged_in && (@$player_row['admin_level'] & ADMIN_FAQ);
   $FAQexclude = array( 'ejlo', 'rodival' );

   $result = db_query( 'people.find_faq_admins',
      "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level" .
      " FROM Players" .
      " WHERE Adminlevel>0 AND (Adminlevel & " . ADMIN_FAQ . ") > 0" .
      " ORDER BY ID" );

   $FAQ_list = array();
   while( $row = mysql_fetch_array( $result ) )
   {
      $uid = $row['ID'];
      if( $extra_info )
      {
         $query = 'SELECT UNIX_TIMESTAMP(T.Date) AS Date'
            . ' FROM FAQlog AS T'
            . " WHERE T.uid=$uid"
            . ' ORDER BY T.Date DESC LIMIT 1';
         $lastUpd = mysql_single_fetch( 'people.faq_admins.lastupdate', $query);
         $row['LastUpdate'] = ($lastUpd) ? $lastUpd['Date'] : 0;
      }

      $FAQ_list[$uid] = $row;
   }
   mysql_free_result($result);

   $first = T_('FAQ editor');
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
   section( 'Moderators', T_('Forum moderators') . $img_admin, 'moderators' );

   $MODexclude = array( 'ejlo', 'rodival' );

   $result = db_query( 'people.find_forum_moderators',
      "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level" .
      " FROM Players" .
      " WHERE Adminlevel>0 AND (Adminlevel & " . ADMIN_FORUM . ") > 0" .
      " ORDER BY ID" );

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
   section( 'Executives', T_('Administration - Dragon executives') . $img_admin, 'executives' );

   $MODexclude = array( 'ejlo' );

   $active_weeks = 6; // [weeks]
   $lastAccess = $NOW - $active_weeks * 7 * SECS_PER_DAY; // 6 weeks
   echo sprintf( T_('(have been online within the last %s weeks)'), $active_weeks )
      , ":<br>&nbsp;\n";

   $result = db_query( 'people.find_executives',
      "SELECT ID,Handle,Name,Adminlevel+0 AS admin_level,".
            " BIT_COUNT(Adminlevel+0) AS X_AdmLevBitCount," .
            " UNIX_TIMESTAMP(Lastaccess) AS X_Lastaccess" .
      " FROM Players" .
      " WHERE Adminlevel>0 AND Lastaccess > FROM_UNIXTIME($lastAccess)" .
      " ORDER BY X_AdmLevBitCount DESC, ID ASC" );

   $people = array(); // sort-id => row, ...
   while( $row = mysql_fetch_array( $result ) )
   {
      if( in_array( $row['Handle'], $MODexclude) )
         continue;

      list( $sortmetric, $executives) = get_executives( $row['admin_level'] );
      $people[] = array_merge( array( 'AL' => $executives ), $row );
   }
   mysql_free_result($result);

   //shuffle($people);
   $prevTitle = '<>nil';
   $title = '';
   foreach( $people as $row )
   {
      $title = ( $title != $prevTitle || $title != $row['AL'] ) ? $row['AL'] : '';
      $prevTitle = $title;
      add_contributor( $title, $row['ID'], $row['Name'], $row['Handle'],
         ( $logged_in && @$row['X_Lastaccess'] > 0 ? date(DATE_FMT2, $row['X_Lastaccess']) : '') );
   }

   add_contributor();


   //---------
   section( 'Translators', T_('Translators'), 'translators' );

   $TW_ = 'T_'; // for non-const translation-texts
   $extra_info = $logged_in && (@$player_row['admin_level'] & ADMIN_TRANSLATORS);

   $result = db_query( 'people.find_translators',
      "SELECT ID,Handle,Name,Translator FROM Players WHERE Translator>'' ORDER BY ID" );

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
            $langname = $TW_($langname);
         else
            $langname = $language;

         if( $extra_info )
         {
            $query = 'SELECT UNIX_TIMESTAMP(T.Date) AS Date ' .
               'FROM Translationlog AS T INNER JOIN TranslationLanguages AS L ON L.ID=T.Language_ID ' .
               "WHERE T.Player_ID=$uid AND L.Language='$language' " .
               'ORDER BY T.Date DESC LIMIT 1';
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
   section( 'Other credits', T_('Other credits'), 'credits' );

   $images_str = // image + originating icon-name from silk-collection
        build_icon('professional.gif', 'Professional created from [user_suite + wand]')
      . build_icon('teacher.gif', 'Teacher created from [user_comment]')
      . build_icon('robot.gif', 'Robot created from [computer]')
      . build_icon('team.gif', 'Team created from [group]')
      . build_icon('info.gif', 'Information created from [info]')
      . build_icon('picture.gif', 'User picture created from [photo]')
      . build_icon('admin.gif', 'Admin created from [user_grey]')
      . build_icon('online.gif', 'Being online (=in the house) created from [house]')
      . build_icon('wclock_stop.gif', 'Stopped clock created from [clock_stop]')
      . build_icon('table.gif', 'Table list created from [application_view_list]')
      . build_icon('thread.gif', 'Thread overview created from [tex/*T_*/align_right]')
      . build_icon('game_comment.gif', 'Hidden game comments created from [user_comment]')
      . build_icon('note.gif', 'Note created from [script]')
      . build_icon('newgame.gif', 'Plus created from [add]')
      . build_icon('sgf.gif', 'Save-SGF created from [disk]')
      ;
   add_contributor_link(
      'Taken and modified some icons from Mark James\' silk icons collection (version 1.3) under ' .
         'Creative Commons Attribution 2.5 License: ' . $images_str,
      anchor('http://www.famfamfam.com/archive/silk-icons-thats-your-lot/') );

   add_contributor_link( T_('Other Icons'),
      anchor( "http://www.iconarchive.com/show/sport-icons-by-icons-land/Trophy-Gold-icon.html",
              build_icon('tourney.gif', T_('Tournament')) )
      );

   add_contributor();


   end_page();
}

?>

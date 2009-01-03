<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( 'include/table_infos.php' );
require_once( "include/rating.php" );
require_once( "include/countries.php" );
require_once( "include/contacts.php" );
$ThePage = new Page('UserInfo');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $my_id = $player_row['ID'];
   $is_admin = (@$player_row['admin_level'] & ADMIN_DEVELOPER);

   get_request_user( $uid, $uhandle, true);
   if( $uhandle )
      $where = "Handle='".mysql_addslashes($uhandle)."'";
   elseif( $uid > 0 )
      $where = "ID=$uid";
   else
      $where = "ID=$my_id";

   $row = mysql_single_fetch( 'userinfo.find',
      "SELECT *"
      .",IF(Activity>$ActiveLevel2,2+(Activity>$ActiveLevel3),Activity>$ActiveLevel1) AS ActivityLevel"
      //i.e. Percent = 100*(Won+Jigo/2)/RatedGames
      .",ROUND(50*(RatedGames+Won-Lost)/RatedGames) AS Percent"
      .",IFNULL(UNIX_TIMESTAMP(Registerdate),0) AS X_Registerdate"
      .",IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS X_Lastaccess"
      .",IFNULL(UNIX_TIMESTAMP(LastMove),0) AS X_LastMove"
      ." FROM Players WHERE $where" );

   if( !$row )
      error('unknown_user');
   $uid = (int)$row['ID'];
   $hide_bio = (@$row['AdminOptions'] & ADMOPT_HIDE_BIO);

   // load bio
   $bio_result = db_query( 'userinfo.bio',
      "SELECT * FROM Bio WHERE uid=$uid order by SortOrder, ID");
   $count_bio = @mysql_num_rows($bio_result);

   if( $hide_bio )
   {
      mysql_free_result($bio_result);
      $bio_result = NULL; // hide bio
   }

   $has_contact = Contact::has_contact($my_id, $uid);

   $my_info = ( $my_id == $uid );
   $name_safe = make_html_safe($row['Name']);
   $handle_safe = $row['Handle'];

   $title = ( $my_info ? T_('My user info') :
              sprintf(T_('User info for %s'), user_reference( 0, 0, '', 0, $name_safe, $handle_safe)) );

   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   if( (@$row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      echo sprintf( "<p><font color=\"red\"><b>( %s )</b></font><br>\n",
         T_('Account blocked - Login denied') );

   $run_link = "show_games.php?uid=$uid";
   $fin_link = $run_link.URI_AMP.'finished=1';
   $rat_link = $fin_link.URI_AMP.'rated=1'; //Rated=yes
   $won_link = $rat_link.URI_AMP.'won=1'; //Won?=Won
   $los_link = $rat_link.URI_AMP.'won=2'; //Won?=Lost

   // get player clock
   $tmpTZ = setTZ($row['Timezone']); //for get_clock_used() and local time
   $user_time = time() + (int)$timeadjust; //see #NOW
   $user_gmt_offset = date('O', $user_time);
   $user_localtime  = date(DATE_FMT . ' T', $user_time); // +timezone-name
   $user_clockused  = get_clock_used($row['Nightstart']);
   setTZ($tmpTZ);

   { //User infos
      $activity = activity_string( $row['ActivityLevel']);
      $registerdate = (@$row['X_Registerdate'] > 0
                        ? date('Y-m-d', $row['X_Registerdate']) : '' );
      $lastaccess = (@$row['X_Lastaccess'] > 0
                        ? date(DATE_FMT2, $row['X_Lastaccess']) : '' );
      $lastmove = (@$row['X_LastMove'] > 0
                        ? date(DATE_FMT2, $row['X_LastMove']) : '' );

      $cntr = @$row['Country'];
      $cntrn = basic_safe(@$COUNTRIES[$cntr]);
      $cntrn = (empty($cntr) ? '' :
                "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");

      $percent = ( is_numeric($row['Percent']) ? $row['Percent'].'%' : '' );


      $itable= new Table_info('user');

      $itable->add_row( array(
               'sname' => T_('Name'),
               'sinfo' => $name_safe,
               ) );
      $itable->add_row( array(
               'sname' => T_('Userid'),
               'sinfo' => $handle_safe,
               ) );
      $itable->add_row( array(
               'sname' => T_('Country'),
               'sinfo' => $cntrn,
               ) );
      $itable->add_row( array(
               'sname' => T_('Time zone'),
               'sinfo' => $row['Timezone'] . " [GMT$user_gmt_offset]",
               ) );
      $itable->add_row( array(
               'sname' => T_('User local time'),
               'sinfo' => $user_localtime
               ) );
      $itable->add_row( array(
               'sname' => T_('Night Start'),
               'sinfo' => sprintf('%02d:00', $row['Nightstart']),
               ) );
      $itable->add_row( array(
               'sname' => T_('Open for matches?'),
               'sinfo' => make_html_safe(@$row['Open'],INFO_HTML),
               ) );
      $itable->add_row( array(
               'sname' => T_('Activity'),
               'sinfo' => $activity,
               ) );
      $itable->add_row( array(
               'sname' => T_('Rating'),
               'sinfo' => echo_rating(@$row['Rating2'],true,$row['ID']),
               ) );
      $itable->add_row( array(
               'sname' => T_('Rank info'),
               'sinfo' => make_html_safe(@$row['Rank'],INFO_HTML),
               ) );
      $itable->add_row( array(
               'sname' => T_('Registration date'),
               'sinfo' => $registerdate,
               ) );
      $itable->add_row( array(
               'sname' => T_('Last access'),
               'sinfo' => $lastaccess,
               ) );
      $itable->add_row( array(
               'sname' => T_('Last move'),
               'sinfo' => $lastmove,
               ) );
      $itable->add_row( array(
               'sname' => T_('Vacation days left'),
               'sinfo' => echo_day(floor($row["VacationDays"])),
               ) );
      if( $row['OnVacation'] > 0 )
      {
         $itable->add_row( array(
                  'nattb' => 'class=OnVacation',
                  'sname' => T_('On vacation'),
                  'sinfo' => echo_onvacation($row['OnVacation']),
                  ) );
      }
      $itable->add_row( array(
               'sname' => anchor( $run_link, T_('Running games')),
               'sinfo' => $row['Running'],
               ) );
      $itable->add_row( array(
               'sname' => anchor( $fin_link, T_('Finished games')),
               'sinfo' => $row['Finished'],
               ) );
      $itable->add_row( array(
               'sname' => anchor( $rat_link, T_('Rated games')),
               'sinfo' => $row['RatedGames'],
               ) );
      $itable->add_row( array(
               'sname' => anchor( $won_link, T_('Won games')),
               'sinfo' => $row['Won'],
               ) );
      $itable->add_row( array(
               'sname' => anchor( $los_link, T_('Lost games')),
               'sinfo' => $row['Lost'],
               ) );
      $itable->add_row( array(
               'sname' => T_('Percent'),
               'sinfo' => $percent,
               ) );
      if( $is_admin )
      { // show player clock
         $itable->add_row( array(
                  'rattb' => 'class=DebugInfo',
                  'sname' => 'night / used==used(night) ',
                  'sinfo' => $row['Nightstart'] .' / '.$row['ClockUsed']
                        .'=='.$user_clockused .' ('.$row['ClockChanged'].')'
                  ) );
      }
      $itable->echo_table();
      unset($itable);
   } //User infos


   if( is_null($bio_result) )
   {//Bio infos hidden by admin
      if( $count_bio > 0 )
         echo '<p></p><h3 class=Header>' . T_('Biographical info (hidden)') . "</h3>\n";
   }
   elseif( $count_bio > 0 )
   {//Bio infos
      echo '<p></p><h3 class=Header>' . T_('Biographical info') . "</h3>\n";

      $itable= new Table_info('bio');

      while( $row = mysql_fetch_assoc( $bio_result ) )
      {
         $cat = $row['Category'];
         if( substr( $cat, 0, 1) == '=' )
            $cat = make_html_safe(substr( $cat, 1), INFO_HTML);
         else
         {
            $tmp = T_($cat);
            if( $tmp == $cat )
               $cat = make_html_safe($cat, INFO_HTML);
            else
               $cat = $tmp;
         }
         $itable->add_sinfo( $cat
                  //don't use add_info() to avoid the INFO_HTML here:
                  ,make_html_safe($row['Text'], true)
                  );
      }

      $itable->echo_table();
      unset($itable);
      mysql_free_result($bio_result);
   }//Bio infos
   db_close();


   if( $my_info )
   {
      $menu_array = array( T_('Edit profile') => 'edit_profile.php',
                           T_('Change password') => 'edit_password.php',
                           T_('Edit bio') => 'edit_bio.php',
                           T_('Edit message folders') => 'edit_folders.php' );

      $days_left = floor($player_row['VacationDays']);
      $minimum_days = 7 - floor($player_row['OnVacation']);

      if( $player_row['OnVacation'] > 0 )
      {
         if(!( $minimum_days > $days_left ||
               ( $minimum_days == $days_left && $minimum_days == 0 )))
            $menu_array[T_('Change vacation length')] = 'edit_vacation.php';
      }
      else
         if( $days_left >= 7 )
            $menu_array[T_('Start vacation')] = 'edit_vacation.php';

      $menu_array[T_('Show my opponents')] = 'opponents.php';
   }
   else // others info
   {
      $menu_array =
         array( T_('Show running games') => $run_link,
                T_('Show finished games') => $fin_link,
                T_('Show opponents') => "opponents.php?uid=$uid",
                T_('Invite this user') => "message.php?mode=Invite".URI_AMP."uid=$uid",
                T_('Send message to user') => "message.php?mode=NewMessage".URI_AMP."uid=$uid" );

      if( $has_contact >= 0 )
      {
         $cstr = ( $has_contact ) ? T_('Edit contact') : T_('Add contact');
         $menu_array[$cstr] = "edit_contact.php?cid=$uid";
      }
   }


   end_page(@$menu_array);
}
?>

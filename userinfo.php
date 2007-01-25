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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( 'include/table_infos.php' );
require_once( "include/rating.php" );
require_once( "include/countries.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   get_request_user( $uid, $uhandle, true);
   if( $uhandle )
      $where = "Handle='".mysql_addslashes($uhandle)."'";
   elseif( $uid > 0 )
      $where = "ID=$uid";
   else
      $where = "ID=" . $player_row["ID"];

   $result = mysql_query(
      "SELECT *," .
      "ROUND(100*Won/RatedGames) AS Percent, " .
      //"UNIX_TIMESTAMP(Lastaccess) as Time," .
      "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, " .
      "IFNULL(UNIX_TIMESTAMP(Registerdate),0) AS Registerdate, " .
      "IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS lastaccess, " .
      "IFNULL(UNIX_TIMESTAMP(LastMove),0) AS Lastmove " .
      "FROM Players WHERE $where" )
      or error('mysql_query_failed', 'userinfo.find');

   if( mysql_affected_rows() != 1 )
      error("unknown_user");

   $row = mysql_fetch_assoc( $result );
   $uid = $row['ID'];

   $bio_result = mysql_query("SELECT * FROM Bio WHERE uid=" . $uid
               . " order by SortOrder, ID")
      or error('mysql_query_failed', 'userinfo.bio');


   $my_info = ( $player_row["ID"] == $uid );
   $name_safe = make_html_safe($row['Name']);
   $handle_safe = $row['Handle'];

   $title = ( $my_info ? T_('My user info') :
              sprintf(T_('User info for %s'), user_reference( 0, 0, '', 0, $name_safe, $handle_safe)) );

   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo "<center>";


   { //User infos
      $activity = activity_string( $row['ActivityLevel']);
      $registerdate = (@$row['Registerdate'] > 0
                        ? date('Y-m-d', $row['Registerdate']) : '' );
      $lastaccess = (@$row['lastaccess'] > 0
                        ? date($date_fmt2, $row['lastaccess']) : '' );
      $lastmove = (@$row['Lastmove'] > 0
                        ? date($date_fmt2, $row['Lastmove']) : '' );

      $cntr = @$row['Country'];
      $cntrn = T_(@$COUNTRIES[$cntr]);
      $cntrn = (empty($cntr) ? '' :
                "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");

      $run_link = "show_games.php?uid=$uid";
      $fin_link = $run_link.URI_AMP.'finished=1';
      $rat_link = $fin_link.URI_AMP.'sort1=Rated'.URI_AMP.'desc1=1';
      $los_link = $rat_link.URI_AMP.'sort2=Win';
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
               'sname' => T_('Open for matches?'),
               'info' => @$row['Open'],
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
               'info' => @$row['Rank'],
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
                  'sinfo' => echo_day(floor($row['OnVacation'])).' '.T_('left#2'),
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
               'sname' => anchor( $rat_link.URI_AMP.'sort2=ID', T_('Rated games')),
               'sinfo' => $row['RatedGames'],
               ) );
      $itable->add_row( array(
               'sname' => anchor( $los_link.URI_AMP.'desc2=1', T_('Won games')),
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

      $itable->echo_table();
      unset($itable);
   } //User infos


   if( @mysql_num_rows($bio_result) > 0 )
   {//Bio infos
      echo '<p></p><h3 class=Header>' . T_('Biographical info') . "</h3>\n";

      $itable= new Table_info('bio');

      while( $row = mysql_fetch_assoc( $bio_result ) )
      {
         $itable->add_sinfo(
                   make_html_safe(T_($row["Category"]), INFO_HTML)
                  //don't use add_info() to avoid the INFO_HTML here:
                  ,make_html_safe($row["Text"], true)
                  );
      }

      $itable->echo_table();
      unset($itable);
   }//Bio infos



   echo "</center>\n";

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
         if(!( $minimum_days > $days_left or
               ( $minimum_days == $days_left and $minimum_days == 0 )))
            $menu_array[T_('Change vacation length')] = 'edit_vacation.php';
      }
      else
         if( $days_left >= 7 )
            $menu_array[T_('Start vacation')] = 'edit_vacation.php';
   }
   else
   {
      $menu_array =
         array( T_('Show running games') => $run_link,
                T_('Invite this user') => "message.php?mode=Invite".URI_AMP."uid=$uid",
                T_('Send message to user') => "message.php?mode=NewMessage".URI_AMP."uid=$uid",
                T_('Show finished games') => $fin_link );
   }


   end_page(@$menu_array);
}
?>

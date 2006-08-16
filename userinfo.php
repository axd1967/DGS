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
require_once( "include/rating.php" );
require_once( "include/countries.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   get_request_user( $uid, $uhandle, true);
   if( $uhandle )
      $where = "Handle='".addslashes($uhandle)."'";
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

   $row = mysql_fetch_array( $result );
   $uid = $row['ID'];

   $my_info = ( $player_row["ID"] == $uid );
   $name_safe = make_html_safe($row['Name']);
   $handle_safe = $row['Handle'];

   $title = ( $my_info ? T_('My user info') :
              sprintf(T_('User info for %s'), user_reference( 0, 0, '', 0, $name_safe, $handle_safe)) );

   start_page($title, true, $logged_in, $player_row );

   echo "<center>";

   echo "<h3><font color=$h3_color>" . $title . '</font></h3>';

   $a = $row['ActivityLevel'];
   $activity = ( $a == 0 ? '' :
                 ( $a == 1 ? '<img align=middle alt="*" src="images/star2.gif">' :
                   '<img align=middle alt="*" src="images/star.gif">' .
                   '<img align=middle alt="*" src="images/star.gif">' ) );
   $registerdate = ($row['Registerdate'] > 0 ? date('Y-m-d', $row['Registerdate']) : NULL );
   $lastaccess = ($row['lastaccess'] > 0 ? date($date_fmt2, $row['lastaccess']) : NULL );
   $lastmove = ($row['Lastmove'] > 0 ? date($date_fmt2, $row['Lastmove']) : NULL );

   $cntr = @$row['Country'];
   $cntrn = T_(@$COUNTRIES[$cntr]);
   $cntrn = (empty($cntr) ? '' :
             "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");

   echo '
 <table id=\"user_infos\" class=infos border=1>
    <tr><td><b>' . T_('Name') . '</b></td><td>' . $name_safe . '</td></tr>
    <tr><td><b>' . T_('Userid') . '</b></td><td>' . $handle_safe . '</td></tr>
    <tr><td><b>' . T_('Country') . '</b></td><td>' . $cntrn . '</td></tr>
    <tr><td><b>' . T_('Open for matches') . '</b></td><td>' . make_html_safe($row['Open'],INFO_HTML) . '</td></tr>
    <tr><td><b>' . T_('Activity') . '</b></td><td>' . $activity . '</td></tr>
    <tr><td><b>' . T_('Rating') . '</b></td><td>' . echo_rating(@$row['Rating2'],true,$row['ID']) . '</td></tr>
    <tr><td><b>' . T_('Rank info') . '</b></td><td>' . make_html_safe(@$row['Rank'],INFO_HTML) . '</td></tr>
    <tr><td><b>' . T_('Registration date') . '</b></td><td>' . $registerdate . '</td></tr>
    <tr><td><b>' . T_('Last access') . '</b></td><td>' . $lastaccess . '</td></tr>
    <tr><td><b>' . T_('Last move') . '</b></td><td>' . $lastmove . '</td></tr>
    <tr><td><b>' . T_('Vacation days left') . '</b></td>' . 
                      '<td>' . echo_day(floor($row["VacationDays"])) . "</td></tr>\n";

   if( $row['OnVacation'] > 0 )
   {
      echo '<tr><td><b><font color=red>' . T_('On vacation') .
         '</font></b></td><td>' . echo_day(floor($row['OnVacation'])) . ' ' .T_('left') . "</td></tr>\n";
   }

    $run_link = "show_games.php?uid=$uid";
    $fin_link = $run_link.URI_AMP.'finished=1';
    $rat_link = $fin_link.URI_AMP.'sort1=Rated'.URI_AMP.'desc1=1';
    $percent = ( is_numeric($row['Percent']) ? $row['Percent'].'%' : '' );
   echo '
    <tr><td><b>' . anchor( $run_link
                  , T_('Running games'), '', 'class=hdr')
            . '</b></td><td>' . $row['Running'] . '</td></tr>
    <tr><td><b>' . anchor( $fin_link
                  , T_('Finished games'), '', 'class=hdr')
               . '</b></td><td>' . $row['Finished'] . '</td></tr>
    <tr><td><b>' . anchor( $rat_link.URI_AMP.'sort2=ID'
                  , T_('Rated games'), '', 'class=hdr')
               . '</b></td><td>' . $row['RatedGames'] . '</td></tr>
    <tr><td><b>' . anchor( $rat_link.URI_AMP.'sort2=Win'.URI_AMP.'desc2=1'
                  , T_('Won games'), '', 'class=hdr')
               . '</b></td><td>' . $row['Won'] . '</td></tr>
    <tr><td><b>' . anchor( $rat_link.URI_AMP.'sort2=Win'
                  , T_('Lost games'), '', 'class=hdr')
               . '</b></td><td>' . $row['Lost'] . '</td></tr>
    <tr><td><b>' . T_('Percent') . '</b></td><td>' . $percent . '</td></tr>
';

   echo " </table>\n";

   $result = mysql_query("SELECT * FROM Bio where uid=$uid" . " order by ID")
      or error('mysql_query_failed', 'userinfo.bio');

   if( @mysql_num_rows($result) > 0 )
   {
      echo '    <p>
    <h3><font color=' . $h3_color . '>' . T_('Biographical info') . '</font></h3>
    <table class="bio_infos" border=1>
';

      while( $row = mysql_fetch_assoc( $result ) )
      {
         echo '     <tr><td><b>' . make_html_safe(T_($row["Category"])) . '</b></td>' .
            '<td>' . make_html_safe($row["Text"],true) . "</td></tr>\n";
      }

      echo "    </table>\n";
   }


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

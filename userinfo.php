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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/countries.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( !$uid )
      $uid = $player_row["ID"];

   $my_info = ( $player_row["ID"] == $uid );

   $result = mysql_query(
      "SELECT *," .
      "IFNULL(100*Won/Finished,-0.01) AS Percent, " .
      "UNIX_TIMESTAMP(Lastaccess) as Time," .
      "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, " .
      "IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS lastaccess, " .
      "IFNULL(UNIX_TIMESTAMP(LastMove),0) AS Lastmove " .
      "FROM Players WHERE ID=$uid" );

   if( mysql_affected_rows() != 1 )
      error("unknown_user");


   $row = mysql_fetch_array( $result );

   $title = ( $my_info ? T_('My user info') :
              sprintf(T_('User info for %s'), $row['Name']) . ' (' . $row['Handle'] . ')');

   start_page($title, true, $logged_in, $player_row );




   echo "<center>";

   if( $msg )
      echo "\n<p><b><font color=green>$msg</font></b><hr>";

   echo "<h3><font color=$h3_color>" . $title . '</font></h3>';

   $a = $row['ActivityLevel'];
   $activity = ( $a == 0 ? '' :
                 ( $a == 1 ? '<img align=middle alt="*" src=images/star2.gif>' :
                   '<img align=middle alt="*" src=images/star.gif>' .
                   '<img align=middle alt="*" src=images/star.gif>' ) );
   $lastaccess = ($row["lastaccess"] > 0 ? date($date_fmt2, $row["lastaccess"]) : NULL );
   $lastmove = ($row["Lastmove"] > 0 ? date($date_fmt2, $row["Lastmove"]) : NULL );

   $cntr = $row['Country'];
   echo '
 <table border=3>
    <tr><td><b>' . T_('Name') . '</b></td><td>' . make_html_safe($row['Name']) . '</td></tr>
    <tr><td><b>' . T_('Userid') . '</b></td><td>' . make_html_safe($row['Handle']) . '</td></tr>
    <tr><td><b>' . T_('Country') . '</b></td><td>' .  (empty($cntr) ? '&nbsp;' :
             '<img title="' . T_($COUNTRIES[$cntr]) . "\" src=\"images/flags/$cntr.gif\">") . '</td>
    <tr><td><b>' . T_('Open for matches') . '</b></td><td>' . make_html_safe($row['Open'],true) . '</td></tr>
    <tr><td><b>' . T_('Activity') . '</b></td><td>' . $activity . '</td></tr>
    <tr><td><b>' . T_('Rating') . '</b></td><td>' . echo_rating($row['Rating2'],true,$row['ID']) . '</td></tr>
    <tr><td><b>' . T_('Rank info') . '</b></td><td>' . make_html_safe($row['Rank'],true) . '</td></tr>
    <tr><td><b>' . T_('Registration date') . '</b></td><td>' . $row['Registerdate'] . '</td></tr>
    <tr><td><b>' . T_('Last access') . '</b></td><td>' . $lastaccess . '</td></tr>
    <tr><td><b>' . T_('Last move') . '</b></td><td>' . $lastmove . '</td></tr>
    <tr><td><b>' . T_('Vacation days left') . '</b></td><td>' . sprintf("%d", $row["VacationDays"]) . "</td></tr>\n";

   if( $row['OnVacation'] > 0 )
   {
      $days = round($row['OnVacation']);
      echo '<tr><td><b><font color=red>' . T_('On vacation') .
         '</font></b></td><td>' . "$days " . ($days <= 1 ? T_('day') : T_('days')) .
         ' ' .T_('left') . "</td></tr>\n";
   }

   echo '
    <tr><td><b>' . T_('Running games') . '</b></td><td>' . $row['Running'] . '</td></tr>
    <tr><td><b>' . T_('Finished games') . '</b></td><td>' . $row['Finished'] . '</td></tr>
    <tr><td><b>' . T_('Won games') . '</b></td><td>' . $row['Won'] . '</td></tr>
    <tr><td><b>' . T_('Lost games') . '</b></td><td>' . $row['Lost'] . '</td></tr>
    <tr><td><b>' . T_('Percent') . '</b></td><td>' . round($row['Percent']) . '%</td></tr>
';

   echo " </table>\n";

   $result = mysql_query("SELECT * FROM Bio where uid=$uid");

   if( mysql_num_rows($result) > 0 )
   {
      echo '    <p>
    <h3><font color=' . $h3_color . '>' . T_('Biographical info') . '</font></h3>
    <table border=3>
';
   }

   while( $row = mysql_fetch_array( $result ) )
   {
      echo '     <tr><td><b>' . make_html_safe(T_($row["Category"])) . '</b></td>' .
         '<td>' . make_html_safe($row["Text"],true) . "</td></tr>\n";
   }

   if(  mysql_num_rows($result) > 0 )
      echo "    </table>\n";


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
         array( T_('Show running games') => "show_games.php?uid=$uid",
                T_('Invite this user') => "message.php?mode=Invite&uid=$uid",
                T_('Send message to user') => "message.php?mode=NewMessage&uid=$uid",
                T_('Show finished games') => "show_games.php?uid=$uid&finished=1" );
   }


   end_page( $menu_array );
}
?>

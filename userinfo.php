<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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
require( "include/rating.php" );

{
   connect2mysql();

   if( !$uid )
      error("no_uid");

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");


   $my_info = ( $player_row["ID"] == $uid );

   $result = mysql_query("SELECT *,UNIX_TIMESTAMP(Lastaccess) as Time " .
                         "FROM Players WHERE ID=$uid");

   if( mysql_affected_rows() != 1 )
      error("unknown_user");


   $row = mysql_fetch_array( $result );

   start_page(T_('User Info'), true, $logged_in, $player_row );




   echo "<center>";

   if( $msg )
      echo "\n<p><b><font color=green>$msg</font></b><hr>";

   echo '
 <table border=3>
    <tr><td><b>' . T_('Name') . '</b></td><td>' . $row['Name'] . '</td></tr>
    <tr><td><b>' . T_('Userid') . '</b></td><td>' . $row['Handle'] . '</td></tr>
    <tr><td><b>' . T_('Open for matches') . '</b></td><td>' . $row['Open'] . '</td></tr>
    <tr><td><b>' . T_('Rating') . '</b></td><td>' . echo_rating($row['Rating']) . '</td></tr>
    <tr><td><b>' . T_('Rank info') . '</b></td><td>' . $row['Rank'] . '</td></tr>
    <tr><td><b>' . T_('Registration date') . '</b></td><td>' . $row['Registerdate'] . '</td></tr>
    <tr><td><b>' . T_('Last access<') . '/b></td><td>' . date($date_fmt,$row['Time']) . '</td></tr>
 </table>
';

       $result = mysql_query("SELECT * FROM Bio where uid=$uid");

       if( mysql_num_rows($result) > 0 )
       {
          echo '    <p>
    <H4>' . T_('Biographical info') . '</H4>
    <table border=3>
';
       }

       while( $row = mysql_fetch_array( $result ) )
       {
          echo '     <tr><td><b>' . make_html_safe($row["Category"]) . '</b></td>' .
             '<td>' . make_html_safe($row["Text"],true) . "</td></tr>\n";
       }

       if(  mysql_num_rows($result) > 0 )
          echo "    </table>\n";


       if( $my_info )
       {
          $menu_array = array( T_('Edit profile') => 'edit_profile.php',
                               T_('Change password') => 'edit_password.php',
                               T_('Edit bio') => 'edit_bio.php' );
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

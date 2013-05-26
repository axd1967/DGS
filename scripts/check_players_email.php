<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir( '../' );
require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/time_functions.php';
require_once 'include/game_functions.php';


$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );

{
   connect2mysql();


   $logged_in = who_is_logged($player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.check_players_email');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.check_players_email');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.check_players_email');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'check_players_email', true/*no-cache*/ );

   echo "<hr>Find players with invalid email ...";

   $qsql = new QuerySQL(
         SQLP_FIELDS,
            'P.ID', 'P.Handle', 'P.Email', 'P.SendEmail',
         SQLP_FROM,
            'Players AS P',
         SQLP_WHERE,
            "P.Email > ''"
      );

   $query = $qsql->get_select();
   $result = db_query("scripts.check_players_email", $query ) or die(mysql_error());

   $p_cnt = @mysql_num_rows($result);
   echo "<br>Found $p_cnt players with email ...<br><br>\n";

   $curr_cnt = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      $uid = $row['ID'];
      $email = $row['Email'];
      $send_email = $row['SendEmail'];
      if ( verify_invalid_email("scripts.check_players_email($uid)", $email, /*err-die*/false) )
      {
         $curr_cnt++;
         echo sprintf("#%s. Player %6d [%s] : Email [%s] INVALID, SendEmail [%s]<br>\n",
                      $curr_cnt, $uid, $row['Handle'], $email, $send_email );
      }
   }
   mysql_free_result($result);

   echo '<br><br>Checking players email finished.';

   echo "<p></p>Done.";

   end_html();
}//main

?>

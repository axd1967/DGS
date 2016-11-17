<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir( '../../../' );
require_once 'include/std_functions.php';

$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS_ADM_OPS );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.fix_game_comments-1_0_15');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_game_comments-1_0_15');
   if ( !(@$player_row['admin_level'] & (ADMIN_DATABASE|ADMIN_GAME)) )
      error('adminlevel_too_low', 'scripts.fix_game_comments-1_0_15');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_game_comments', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if ( $do_it )
   {
      function dbg_query($s) {
         if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
         if ( DBG_QUERY>1 ) error_log("dbg_query(DO_IT): $s");
      }
      echo "<p>*** Fixes hidden game comments flag ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) {
         echo "<BR>$s; ";
         if ( DBG_QUERY>1 ) error_log("dbg_query(SIMUL): $s");
      }
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


   // NOTE: Using a GROUP-BY SQL-statement may run into memory problems,
   //       so scan MoveMessages for hidden game-comments separatly for each game.

   echo "<hr>Find games with hidden game comments ...";

   $arr_games = load_games_with_hidden_comment( GAMEFLAGS_HIDDEN_MSG ); // gid => 1

   $query = "SELECT DISTINCT gid FROM MoveMessages WHERE Text RLIKE '</?h(idden)?>'";
   $result = mysql_query( $query ) or die(mysql_error());

   $games_cnt = @mysql_num_rows($result);
   $curr_cnt = 0;
   echo "<br>Found $games_cnt games to check and potentially set hidden-comments-flag ...\n";

   // update Games.Flags
   $cnt_fix = 0;
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $gid = $row['gid'];
      if ( !isset($arr_games[$gid]) )
         $cnt_fix += update_games_flags( $gid, GAMEFLAGS_HIDDEN_MSG );
   }
   mysql_free_result($result);

   echo "<br>Found $cnt_fix games that required fixing ...\n";

   if ( $do_it )
      echo 'Games hidden-comments-flag fix finished.';

   end_html();
}//main


function update_games_flags( $gid, $flagval )
{
   global $games_cnt, $curr_cnt;
   if ( ($curr_cnt++ % 50) == 0 )
      echo "<br><br>... $curr_cnt of $games_cnt updated ...\n";
   $update_query = "UPDATE Games SET Flags=Flags | $flagval WHERE ID='$gid' LIMIT 1";
   dbg_query($update_query);
   return 1;
}

function load_games_with_hidden_comment( $flagval )
{
   $arr = array();
   $query = "SELECT ID FROM Games WHERE (Flags & $flagval) > 0";
   $result = mysql_query( $query ) or die(mysql_error());
   while ( $row = mysql_fetch_assoc( $result ) )
      $arr[$row['ID']] = 1;
   mysql_free_result($result);
   return $arr;
}//load_games_with_hidden_comment

?>

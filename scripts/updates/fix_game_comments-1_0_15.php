<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir( '../../' );
require_once( "include/std_functions.php" );

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'fix_game_comments-1_0_15');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_game_comments', false );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if( $do_it )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
      }
      _echo(
         "<p>*** Fixes hidden game comments flag ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>" );
   }
   else
   {
      function dbg_query($s) { _echo( "<BR>$s; "); }
      $tmp = array_merge($page_args,array('do_it' => 1));
      _echo(
         "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>" );
   }


   // NOTE: Using a GROUP-BY SQL-statement may run into memory problems,
   //       so scan MoveMessages for hidden game-comments separatly for each game.

   _echo( "<hr>Find games with hidden game comments ..." );

   $query = "SELECT DISTINCT gid FROM MoveMessages WHERE Text RLIKE '</?h(idden)?>'";
   $result = mysql_query( $query ) or die(mysql_error());

   $games_cnt = @mysql_num_rows($result);
   $curr_cnt = 0;
   _echo( "<br>Found $games_cnt games to process and set hidden-comments-flag ...\n" );

   // update Games.Flags
   while( $row = mysql_fetch_assoc( $result ) )
   {
      $gid = $row['gid'];
      update_games_flags( $gid, GAMEFLAGS_HIDDEN_MSG );
   }
   mysql_free_result($result);

   if( $do_it )
      _echo('Games hidden-comments-flag fix finished.');

   end_html();
}

function _echo($msg)
{
   echo $msg;
   ob_flush();
   flush();
}

function update_games_flags( $gid, $flagval )
{
   global $games_cnt, $curr_cnt;
   if( ($curr_cnt++ % 50) == 0 )
      _echo( "<br><br>... $curr_cnt of $games_cnt updated ...\n" );
   $update_query = "UPDATE Games SET Flags=Flags | $flagval WHERE ID='$gid' LIMIT 1";
   dbg_query($update_query);
}

?>

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

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/time_functions.php" );
require_once( "include/classlib_game.php" );

{
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'fix_games_timeleft');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();


   start_html( 'fix_games_timeleft', true/*no-cache*/ );

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;

   $do_it = @$_REQUEST['do_it'];
   if( $do_it )
   {
      function dbg_query($s) {
        if( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
      }
      _echo(
         "<p>*** Fixes Games.TimeOutDate ***"
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


   _echo( "<hr>Find running games ..." );

   $qsql = new QuerySQL(
         SQLP_FIELDS,
            "G.*",
            "black.ClockUsed AS X_BlackClock",
            "white.ClockUsed AS X_WhiteClock",
         SQLP_FROM,
            "Games AS G",
            "INNER JOIN Players AS black ON black.ID=G.Black_ID",
            "INNER JOIN Players AS white ON white.ID=G.White_ID",
         SQLP_WHERE,
            "Status ".IS_RUNNING_GAME
      );
   //$qsql->add_part( SQLP_WHERE, "TimeOutDate=0" ); // unset games
   //$qsql->add_part( SQLP_WHERE, "WeekendClock='N'" ); // games with no clock running on weekend

   $query = $qsql->get_select();
   //_echo($query);
   $result = mysql_query( $query ) or die(mysql_error());

   $games_cnt = @mysql_num_rows($result);
   $curr_cnt = 0;
   _echo( "<br>Found $games_cnt games to process and calculate time left ...\n" );

   // update Games.TimeOutDate
   while( $row = mysql_fetch_assoc($result) )
   {
      $gid = $row['ID'];
      $timeout_date = make_timeout_date( $row );
      if( $row['TimeOutDate'] != $timeout_date )
         update_games_timeoutdate( $gid, $timeout_date );
   }
   mysql_free_result($result);

   if( $do_it )
      _echo('Running games remaining-time fix for Games.TimeOutDate finished.');

   _echo( "<p></p>Done." );

   end_html();
}

function _echo($msg)
{
   echo $msg;
   ob_flush();
   flush();
}

function update_games_timeoutdate( $gid, $timeout_date )
{
   global $games_cnt, $curr_cnt;
   if( ($curr_cnt++ % 50) == 0 )
      _echo( "<br><br>... $curr_cnt of $games_cnt updated ...\n" );
   $update_query = "UPDATE Games SET TimeOutDate=$timeout_date WHERE ID='$gid' LIMIT 1";
   dbg_query($update_query);
}

function make_timeout_date( $grow )
{
   $to_move = ($grow['Black_ID'] == $grow['ToMove_ID']) ? BLACK : WHITE;
   return NextGameOrder::make_timeout_date( $grow, $to_move, $grow['LastTicks'] );
}

?>

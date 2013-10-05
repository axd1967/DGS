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

$TranslateGroups[] = "Game";

define('DBG_RATING', 1); // print new users rating

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/db/games.php';
require_once 'include/db/ratinglog.php';
require_once 'include/classlib_user.php';
require_once 'include/form_functions.php';
require_once 'include/rating.php';
require_once 'include/table_columns.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'game_calc');

   $page = "game_calc.php";

/* Actual REQUEST calls used
     show&gid=                      : show rating calculations for finished game
*/

   $gid = (int)get_request_arg('gid');
   if ( $gid <= 0 ) $gid = 0;

   $game = $game_row = $rlog_b = $rlog_w = null;
   if ( @$_REQUEST['show'] && $gid )
   {
      $game = Games::load_game( $gid );
      if ( !is_null($game) )
      {
         if ( $game->Status == GAME_STATUS_FINISHED )
         {
            $rlog = Ratinglog::load_ratinglog_with_query( new QuerySQL( SQLP_WHERE, "RL.gid=$gid", SQLP_ORDER, "RL.ID ASC" ) );
            $rlog_id = (is_null($rlog)) ? 0 : $rlog->ID;
            $game_row = array();
            foreach ( array( 'b', 'w' ) as $pfx )
            {
               $uid = ($pfx == 'b') ? $game->Black_ID : $game->White_ID;
               $rlog_data = load_rating_data( $uid, $rlog_id );
               $game_row[$pfx.'RatingStatus'] = ($rlog_id > 0) ? RATING_RATED : RATING_INIT;
               $game_row[$pfx.'Rating'] = $rlog_data->Rating;
               $game_row[$pfx.'RatingMin'] = $rlog_data->RatingMin;
               $game_row[$pfx.'RatingMax'] = $rlog_data->RatingMax;
            }
            $rlog_b = load_rating_data( $game->Black_ID, $rlog_id, $gid );
            $rlog_w = load_rating_data( $game->White_ID, $rlog_id, $gid );
         }
      }
   }//show


   $title = T_('Game calculations');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   $gcform = new Form('gcform', $page, FORM_GET, true);
   $gcform->add_row( array(
      'DESCRIPTION', T_('Game ID'),
      'TEXTINPUT',   'gid', 10, 10, $gid,
      'SUBMITBUTTON', 'show', T_('Show Rating Calculations'),
      ));

   $gcform->echo_string(1);

   section( 'result', T_('Result') );
   if ( !is_null($game) ) // show players-rating-results for finished or running game
   {
      echo "Game: ", make_html_safe("<game $gid>", true), "\n";

      echo "<table><tr><td><pre>\n";
      //echo print_r( $game_row, true), "\n\n";
      echo "Source-Code: ",
         anchor('https://sourceforge.net/p/dragongoserver/dgs-main/ci/2adb3f360d93e9b0137dcc931cb95b8a9cd4fbec/tree/include/rating.php#l289', 'update_rating2()'),
         ", ",
         anchor('https://sourceforge.net/p/dragongoserver/dgs-main/ci/2adb3f360d93e9b0137dcc931cb95b8a9cd4fbec/tree/include/rating.php#l118', 'change_rating'),
         "\n\n";

      $is_running = isRunningGame($game->Status);
      if ( $is_running )
      {
         print_rating_update( $game, array( 'Score' =>  1 ) );
         print_rating_update( $game, array( 'Score' => -1 ) );
         print_rating_update( $game, array( 'Score' =>  0 ) );
      }
      elseif ( $game->Status == GAME_STATUS_FINISHED )
         print_rating_update( $game, $game_row );
      else
         echo "Game-Status = [{$game->Status}]\n";

      echo "</pre></td></tr></table>\n";

      if ( !$is_running )
         echo_ratinglogs( $rlog_b, $rlog_w );
   }


   $menu_array = array(
      T_('New game calculation') => 'game_calc.php' );

   end_page(@$menu_array);
}//main


// loads RatingMin/Max for given user from previous Ratinglog-entry before rlog_id
// FIXME: could be wrong if user had rating-change inbetween
function load_rating_data( $uid, $rlog_id, $gid=0 )
{
   if ( $rlog_id > 0 )
   {
      $qsql = new QuerySQL(
         SQLP_WHERE, "RL.uid=$uid",
         SQLP_ORDER, "RL.ID DESC" );
      if ( $gid > 0 )
         $qsql->add_part( SQLP_WHERE, "RL.gid=$gid" );
      else
         $qsql->add_part( SQLP_WHERE, "RL.ID < $rlog_id" );
      $rlog = Ratinglog::load_ratinglog_with_query( $qsql );
   }
   else
      $rlog = null;

   if ( !$rlog ) // calc from initial-rating
   {
      $user = User::load_user( $uid );
      $initial_rating = $user->urow['InitialRating'];

      $rlog = new Ratinglog();
      $rlog->Rating = $initial_rating;
      $rlog->RatingMin = $initial_rating - 200 - max( 1600 - $initial_rating, 0 ) * 2 / 15;
      $rlog->RatingMax = $initial_rating + 200 + max( 1600 - $initial_rating, 0 ) * 2 / 15;
   }

   return $rlog;
}//load_rating_data

function echo_ratinglogs( $rlog_b, $rlog_w )
{
   if ( is_null($rlog_b) || is_null($rlog_w) )
   {
      echo "No ratinglog found!\n";
      return;
   }

   global $page;
   $table = new Table( 'result', $page );
   $table->use_show_rows( false );
   $table->add_tablehead( 1, 'Color' );
   $table->add_tablehead( 2, 'Rating', 'Number2' );
   $table->add_tablehead( 3, 'RatingDiff', 'Number2' );
   $table->add_tablehead( 4, 'RatingMin', 'Number' );
   $table->add_tablehead( 5, 'RatingMax', 'Number' );
   $table->add_row( array( 1 => 'White Ratinglog', 2 => $rlog_w->Rating, 3 => $rlog_w->RatingDiff,
      4 => $rlog_w->RatingMin, 5 => $rlog_w->RatingMax ));
   $table->add_row( array( 1 => 'Black Ratinglog', 2 => $rlog_b->Rating, 3 => $rlog_b->RatingDiff,
      4 => $rlog_b->RatingMin, 5 => $rlog_b->RatingMax ));

   echo "<style type=\"text/css\">\n",
      "table#resultTable td { font-size: 80%; }\n",
      "table#resultTable td.Number2 { font-weight: bold; text-align: right; }\n",
      "</style>\n";
   section('rlog_result', 'Ratinglogs from database:');
   $table->echo_table();
}//echo_ratinglogs

function convert_rating_result( $result )
{
   if ( $result == 0 )
      return '(RATED-game)';
   elseif ( $result == 1 )
      return '(game can be deleted)';
   elseif ( $result == 2 )
      return '(game not rated)';
   return '???';
}//convert_rating_result

function print_rating_update( $game, $game_row )
{
   // build title
   $score = $game->Score;
   if ( isset($game_row['Score']) )
      $score = $game_row['Score'];
   if ( $score < 0 )
      $title = 'Black wins';
   elseif ( $score > 0 )
      $title = 'White wins';
   else
      $title = 'Jigo';

   echo "<hr><h3>$title</h3>\n";

   // rating-debug logged by method-call
   list( $result, $result2 ) = update_rating2( $game->ID, /*check-done*/false, /*simul*/true, $game_row );
   $result_descr = convert_rating_result($result);
   echo "<b>RESULT for [$title]</b> update_rating2 = $result <b>$result_descr</b>\n",
      "<font size=smaller>(Note: 0=rated-game, 1=can-be-deleted, 2=not-rated)</font>\n\n";
}//print_rating_update

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

define('DBG_RATING', 1);

require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/db/games.php" );
require_once( "include/db/ratinglog.php" );
require_once( "include/classlib_user.php" );
require_once( "include/form_functions.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   $page = "game_calc.php";

/* Actual REQUEST calls used
     show&gid=                      : show rating calculations for finished game
*/

   $gid = (int)get_request_arg('gid');
   if( $gid <= 0 ) $gid = 0;

   $game_row = null;
   if( @$_REQUEST['show'] && $gid )
   {
      $game = Games::load_game( $gid );
      if( !is_null($game) && $game->Status == GAME_STATUS_FINISHED )
      {
         $rlog = Ratinglog::load_ratinglog_with_query( new QuerySQL( SQLP_WHERE, "RL.gid=$gid", SQLP_ORDER, "RL.ID ASC" ) );
         $rlog_id = (is_null($rlog)) ? 0 : $rlog->ID;
         $game_row = array();
         foreach( array( 'b', 'w' ) as $pfx )
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
   }//show

   $title = T_('Game calculations');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   $gcform = new Form('gcform', $page, FORM_GET, true);
   $gcform->add_row( array(
      'DESCRIPTION', T_('(finished) Game ID'),
      'TEXTINPUT',   'gid', 10, 10, $gid,
      'SUBMITBUTTON', 'show', T_('Show Rating Calculations'),
      ));

   $gcform->echo_string(1);

   section( 'result', T_('Result') );
   if( !is_null($game_row) )
   {
      echo "<table><tr><td><pre>\n";
      //echo print_r( $game_row, true), "\n\n";
      echo "Source-Code: ",
         anchor('http://dragongoserver.cvs.sourceforge.net/viewvc/dragongoserver/DragonGoServer/include/rating.php?revision=1.84&view=markup&pathrev=HEAD#l290', 'update_rating2()'),
         ", ",
         anchor('http://dragongoserver.cvs.sourceforge.net/viewvc/dragongoserver/DragonGoServer/include/rating.php?revision=1.84&view=markup&pathrev=HEAD#l119', 'change_rating'),
         "\n\n";
      $result = update_rating2( $gid, /*check-done*/false, /*simul*/true, $game_row );

      echo "RESULT update_rating2 = $result    (note: 0=rated-game, 1=can-be-deleted, 2=not-rated)\n";
      echo "</pre></td></tr></table>\n";
      echo_ratinglogs( $rlog_b, $rlog_w );
   }


   $menu_array = array(
      T_('New game calculation') => 'game_calc.php' );

   end_page(@$menu_array);
}


// loads RatingMin/Max for given user from previous Ratinglog-entry before rlog_id
function load_rating_data( $uid, $rlog_id, $gid=0 )
{
   if( $rlog_id > 0 )
   {
      $qsql = new QuerySQL(
         SQLP_WHERE, "RL.uid=$uid",
         SQLP_ORDER, "RL.ID DESC" );
      if( $gid > 0 )
         $qsql->add_part( SQLP_WHERE, "RL.gid=$gid" );
      else
         $qsql->add_part( SQLP_WHERE, "RL.ID < $rlog_id" );
      $rlog = Ratinglog::load_ratinglog_with_query( $qsql );
   }
   else
      $rlog = null;

   if( !$rlog ) // calc from initial-rating
   {
      $user = User::load_user( $uid );
      $initial_rating = $user->urow['InitialRating'];

      $rlog = new Ratinglog();
      $rlog->Rating = $initial_rating;
      $rlog->RatingMin = $initial_rating - 200 - max( 1600 - $initial_rating, 0 ) * 2 / 15;
      $rlog->RatingMax = $initial_rating + 200 + max( 1600 - $initial_rating, 0 ) * 2 / 15;
   }

   return $rlog;
}

function echo_ratinglogs( $rlog_b, $rlog_w )
{
   if( is_null($rlog_b) || is_null($rlog_w) )
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
}
?>

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


chdir('..');
require_once 'include/globals.php';
require_once 'include/std_functions.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_news.php';

$TheErrors->set_mode(ERROR_MODE_COLLECT);


if( ALLOW_TOURNAMENTS && !$is_down )
{
   $chk_time_diff = SECS_PER_HOUR / 4;
   if( $chained )
      $chained = $chk_time_diff;
   else
      connect2mysql();
   $chk_time_diff -= 100;
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'cron_tournament.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=".CLOCK_CRON_TOURNEY." LIMIT 1" );
   if( !$row )
      $TheErrors->dump_exit('cron_tournament');
   if( !DBG_TEST && $row['timediff'] < $chk_time_diff )
      $TheErrors->dump_exit('cron_tournament');

   db_query( 'cron_tournament.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_TOURNEY." LIMIT 1" )
      or $TheErrors->dump_exit('cron_tournament');


   // ---------- BEGIN ------------------------------

   $player_row['ID'] = 0; // for tourney-log
   $player_row['Handle'] = '#CRONT'; // for tourney-tables ChangedBy-fields
   $tg_order = "ORDER BY TG.tid ASC, TG.Lastchanged ASC, TG.ID ASC";
   $thelper = new TournamentHelper();


   // ---------- handle tournament-game ending by score/resignation/jigo/timeout

   $tg_iterator = new ListIterator( 'cron_tournament.load_tgames.score', null, $tg_order );
   $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, 0, 0, 0, TG_STATUS_SCORE );

   $clear_cache = array();
   while( list(,$arr_item) = $tg_iterator->getListIterator() )
   {
      list( $tgame, $orow ) = $arr_item;
      $tid = $tgame->tid;

      $tourney = TournamentCache::load_cache_tournament( 'cron_tournament.game_end', $tid );
      if( !is_null($tourney) && $thelper->process_tournament_game_end( $tourney, $tgame, /*check*/true ) )
      {
         $thelper->tcache->release_tournament_cron_lock( $tid );
         if( $thelper->tcache->set_tournament_cron_lock( $tid ) )
            $thelper->process_tournament_game_end( $tourney, $tgame, /*check*/false );
         $clear_cache[$tid] = $tourney->Type;
      }
   }
   $thelper->tcache->release_tournament_cron_lock();

   // clear caches
   foreach( $clear_cache as $tid => $ttype )
   {
      $dbgmsg = "cron_tournament.game_end($tid,$ttype)";
      TournamentGames::delete_cache_tournament_games( $dbgmsg, $tid );
      if( $ttype == TOURNEY_TYPE_LADDER )
         TournamentLadder::delete_cache_tournament_ladder( $dbgmsg, $tid );
   }



   // ---------- finish waiting, due tournament-games

   $wait_ticks = get_clock_ticks( 'cron_tournament.game_wait', CLOCK_TOURNEY_GAME_WAIT );
   TournamentGames::update_tournament_game_wait( 'cron_tournament', $wait_ticks );



   // ---------- jobs running only once a day ----------

   // NOTE: added special daily-jobs here to avoid the complexity of cron-locking
   //       of daily_cron/tournament-cron-scripts

   $row = mysql_single_fetch( 'cron_tournament.daily.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=".CLOCK_CRON_TOURNEY_DAILY." LIMIT 1" );
   if( $row )
   {
      if( DBG_TEST || $row['timediff'] >= 24*SECS_PER_HOUR ) // one day-check
      {
         db_query( 'cron_tournament.daily.next_run_date',
            "UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_TOURNEY_DAILY." LIMIT 1" );

         run_once_daily();
      }
   }

   // ---------- jobs running hourly -------------------

   // NOTE: added special hourly-jobs here to avoid the complexity of cron-locking
   //       of hourly_cron/tournament-cron-scripts

   $row = mysql_single_fetch( 'cron_tournament.hourly.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=".CLOCK_CRON_TOURNEY_HOURLY." LIMIT 1" );
   if( $row )
   {
      if( DBG_TEST || $row['timediff'] >= 1*SECS_PER_HOUR ) // hourly-check
      {
         db_query( 'cron_tournament.hourly.next_run_date',
            "UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=".CLOCK_CRON_TOURNEY_HOURLY." LIMIT 1" );

         run_hourly();
      }
   }

   // ---------- END --------------------------------

   db_query( 'cron_tournament.reset_tick',
      "UPDATE Clock SET Ticks=0, Finished=FROM_UNIXTIME(".time().") WHERE ID=".CLOCK_CRON_TOURNEY." LIMIT 1" );

   if( !$chained )
      $TheErrors->dump_exit('cron_tournament');
}//$is_down


function run_once_daily()
{
   global $thelper;

   // ---------- Handle long-user-absence for tournaments

   $tl_iterator = TournamentHelper::load_ladder_absent_users(
      new ListIterator( 'cron_tournament.load_ladder_user_absence' ));

   ta_begin();
   {//HOT-section to process long-user-absence
      while( list(,$arr_item) = $tl_iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         $tid = $tladder->tid;

         $thelper->tcache->release_tournament_cron_lock( $tid );
         if( $thelper->tcache->set_tournament_cron_lock( $tid ) )
            TournamentLadder::process_user_absence( $tid, $tladder->uid, $orow['TLP_UserAbsenceDays'] );
      }
      $thelper->tcache->release_tournament_cron_lock();
   }
   ta_end();


   // ---------- Update history/period-rank for ladder-tournaments

   $te_iterator = TournamentHelper::load_ladder_rank_period_update(
      new ListIterator( 'cron_tournament.load_ladder_rank_period_update' ));

   ta_begin();
   {//HOT-section to process rank-updates
      while( list(,$arr_item) = $te_iterator->getListIterator() )
      {
         list( $t_ext, $orow ) = $arr_item;
         $tid = $t_ext->tid;

         $thelper->tcache->release_tournament_cron_lock( $tid );
         if( $thelper->tcache->set_tournament_cron_lock( $tid ) )
            $thelper->process_rank_period( $t_ext );
      }
      $thelper->tcache->release_tournament_cron_lock();
   }
   ta_end();

   // ---------- Delete old tournament-news on DELETE-status

   TournamentNews::process_tournament_news_deleted( 7 ); // 7days

}//run_once_daily


function run_hourly()
{
   global $thelper;

   // ---------- Check for crowning of "King of the Hill"

   $tres_iterator = TournamentHelper::load_ladder_crown_kings(
      new ListIterator( 'cron_tournament.load_ladder_crown_kings' ));

   ta_begin();
   {//HOT-section to crown kings for ladder-tournaments
      while( list(,$arr_item) = $tres_iterator->getListIterator() )
      {
         list( ,$orow ) = $arr_item;
         $tid = $orow['tid'];

         $thelper->tcache->release_tournament_cron_lock( $tid );
         if( $thelper->tcache->set_tournament_cron_lock( $tid ) )
            TournamentHelper::process_tournament_ladder_crown_king( $orow );
      }
      $thelper->tcache->release_tournament_cron_lock();
   }
   ta_end();
}//run_hourly

?>

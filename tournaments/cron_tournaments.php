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


chdir('..');
require_once 'include/globals.php';
require_once 'include/std_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_games.php';
require_once 'tournaments/include/tournament_cache.php';

$TheErrors->set_mode(ERROR_MODE_COLLECT);


if( ALLOW_TOURNAMENTS && !$is_down )
{
   $chk_time_diff = 3600/4;
   if( $chained )
      $chained = $chk_time_diff;
   else
      connect2mysql();
   $chk_time_diff -= 100;


   // Check that updates are not too frequent

   $row = mysql_single_fetch( 'cron_tournament.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=205 LIMIT 1" );
   if( !$row )
      $TheErrors->dump_exit('cron_tournament');
//TODO commented only for testing
//   if( $row['timediff'] < $chk_time_diff )
//      $TheErrors->dump_exit('cron_tournament');

   db_query( 'cron_tournament.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=205 LIMIT 1" )
      or $TheErrors->dump_exit('cron_tournament');


   // ---------- BEGIN ------------------------------

   $player_row['Handle'] = '#CRON'; // for tourney-tables ChangedBy-fields
   $tg_order = "ORDER BY TG.tid ASC, TG.Lastchanged ASC, TG.ID ASC";
   $thelper = new TournamentHelper();

   // ---------- handle tournament-game ending by score/resignation/jigo/timeout

   $tg_iterator = new ListIterator( 'Tournament.cron.load_tgames.score', null, $tg_order );
   $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, 0, TG_STATUS_SCORE );

   while( list(,$arr_item) = $tg_iterator->getListIterator() )
   {
      list( $tgame, $orow ) = $arr_item;
      $tid = $tgame->tid;

      $tourney = $thelper->tcache->load_tournament( 'Tournament.cron.game_end', $tid);
      if( !is_null($tourney) )
      {
         if( $tourney->Type == TOURNEY_TYPE_LADDER )
            $thelper->process_tournament_ladder_game_end( $tourney, $tgame );
         elseif( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
            error('invalid_method', "Tournament.cron.game_end.ttype($tid,$tourney->Type)");
      }
   }

   // ---------- finish waiting, due tournament-games

   $wait_ticks = (int)$thelper->tcache->load_clock_ticks( 'Tournament.cron.game_wait', CLOCK_TOURNEY_GAME_WAIT );
   TournamentGames::update_tournament_game_wait( 'Tournament.cron', $player_row['Handle'], $wait_ticks );


   // ---------- END --------------------------------

   db_query( 'cron_tournament.reset_tick',
         "UPDATE Clock SET Ticks=0 WHERE ID=205 LIMIT 1" );

   if( !$chained )
      $TheErrors->dump_exit('cron_tournament');
}//$is_down
?>

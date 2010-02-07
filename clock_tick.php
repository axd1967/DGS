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


require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( 'include/classlib_game.php' );
require_once 'tournaments/include/tournament_games.php';

$TheErrors->set_mode(ERROR_MODE_COLLECT);

if( !$is_down )
{
   if( $chained )
      $tick_diff = $chained = floor(3600/TICK_FREQUENCY);
   else
      connect2mysql();
   $tick_diff -= 10;


   // Check that ticks are not too frequent

   $row = mysql_single_fetch( 'clock_tick.check_frequency',
      "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff"
      ." FROM Clock WHERE ID=201 LIMIT 1" );
   if( !$row )
      $TheErrors->dump_exit('clock_tick');
   if( $row['timediff'] < $tick_diff )
      $TheErrors->dump_exit('clock_tick');

   db_query( 'clock_tick.set_lastchanged',
         "UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=201 LIMIT 1" )
      or $TheErrors->dump_exit('clock_tick');


   // ---------- BEGIN ------------------------------

   // $NOW is time in UTC
   $hour = gmdate('G', $NOW);
   $day_of_week = gmdate('w', $NOW);

   // Now increase clocks that are not sleeping

   /* NIGHT_LEN hours night
    * Nightstart= N means night in: [ N,(N+NIGHT_LEN)%24 [    (see edit_profile.php)
    *                                 N <= time < (N + NIGHT_LEN)%24
    * if Timezone=='GMT', ClockUsed is always equal to Nightstart
    * if Timezone=='GMT+x'(ou UTC+x), ClockUsed is ( Nightstart+24-x )%24
    * NOTES:
    * - "+24" added to avoid becoming negative
    * - ClockUsed is the GMT hour on which the users night start
    * - ClockUsed also used for weekends (+weekend offset), so NightStart also influences start/end of weekend
    *
    * When the GMT hour is 22, clock_modified= [23]U[00,13], thus:
    *  UserTZ UserTime UserNight UserClockUsed UserClockModified
    *  GMT    22       [22,07[   22            -
    *  GMT+9  07       [22,07[   13            Y
    *  GMT+2  00       [22,07[   20            -
    *  GMT-2  20       [22,07[   00            Y
    *  GMT-9  13       [22,07[   07            Y
    *  GMT+2  00       [20,05[   18            -
    *  GMT+2  00       [22,07[   20            -
    *  GMT+2  00       [02,11[   00            Y
    *  GMT-2  20       [20,05[   22            -
    *  GMT-2  20       [22,07[   00            Y
    *  GMT-2  20       [02,11[   04            Y
    */

   // build a range SQL-query-part for the field $n
   // from $s to $e on a 24 clocks basis with the offset $o
   function clkrng( $n, $s, $e, $o=0)
   {
      if( $s>23 ) $s-= 24;
      if( $e>23 ) $e-= 24;
      if( $s>$e )
         return clkrng($n,0,$e,$o)." OR ".clkrng($n,$s,23,$o);
      $s+= $o; $e+= $o;
      if( $s==$e )
         return "$n=$s";
      return "($n BETWEEN $s AND $e)"; // ($n>=$s AND $n<=$e)
   }

   $clock_modified = clkrng( 'Clock.ID', $hour+1, $hour+24-NIGHT_LEN);
   if( $day_of_week > 0 && $day_of_week < 6 )
      $clock_modified.= " OR ".
         clkrng( 'Clock.ID', $hour+1, $hour+24-NIGHT_LEN, WEEKEND_CLOCK_OFFSET);

   db_query( 'clock_tick.increase_clocks',
      "UPDATE Clock SET Ticks=Ticks+1, Lastchanged=FROM_UNIXTIME($NOW) "
         . "WHERE $clock_modified " // game-clocks (also used below)
         . "OR Clock.ID=204" // clock for remaining-time ordering
      );



   // Check if any game has timed out

/* TODO BUG in old & new version: !?
  if maintenance spans more than a whole hour and graces the sleeping-clocks,
  the time-window has passed to detect time-outs within that hour.
  So time-outs are delayed by a full day till the games with the respective clock is checked again!

  => keep Clock.ID>=0 (as safety for clones; else join with Games would be empty anyway
     as there is no Clock.ID<0 any more)
  => remove Clock.Lastchanged=$NOW to match all clock-timeouts
 */
if(1){//new
/*
 In a first approximation, as $has_moved==false (see time_remaining()),
  $main>$hours will just produce $main-=$hours which will never
  activate $time_is_up and so, will produce nothing here...
 So the "find_timeout_games" query may be restricted with:
 - given IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime) AS ToMove_Maintime
 - WHERE ToMove_Maintime <= $hours
 because $hours is always < $ticks/TICK_FREQUENCY (see ticks_to_hours())
 - given ((ticks-LastTicks) / TICK_FREQUENCY) AS Upper_Ellapsed
 - WHERE ToMove_Maintime < Upper_Ellapsed
 which is:
 - WHERE IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime)
      < ((Clock.Ticks-Games.LastTicks) / TICK_FREQUENCY)

 During a test, this lowered the number of returned rows from 10,960 to 675.
 This is a table scan, but indeed reduces the number of affected rows.
*/
   $result = db_query( 'clock_tick.find_timeout_games',
      "SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, Games.Flags AS X_GameFlags"
      ." FROM Games"
      ." INNER JOIN Clock ON Clock.ID=Games.ClockUsed AND ($clock_modified)"
      .' WHERE Clock.Ticks - Games.LastTicks > '.TICK_FREQUENCY
         ." * IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime)"
      ." AND Status" . IS_RUNNING_GAME
      //slower: ." AND Games.Status!='INVITED' AND Games.Status!='FINISHED'"
      );
}else{//old
   // TODO: This query is sometimes slow and may return more than 10000 rows!

   $query = 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, Games.Flags AS X_GameFlags'
            . ' FROM (Games, Clock)'
            . " WHERE Clock.Lastchanged=FROM_UNIXTIME($NOW)"
            . ' AND Clock.ID>=0' // not VACATION_CLOCK
            . ' AND Games.ClockUsed=Clock.ID'
            //if both are <=0, the game will never finish by time:
            //. ' AND ( Maintime>0 OR Byotime>0 )'
            //slower: "AND Status" . IS_RUNNING_GAME
            . " AND Games.Status!='INVITED' AND Games.Status!='FINISHED'"
            ;
   $result = db_query( 'clock_tick.find_timeout_games', $query );
}//new/old

   /* TODO: The following UPDATE should be optimized, splited in smaller chunk?
            use TEMPORARY TABLEs for generated UPDATEs ??? */

   while($row = mysql_fetch_assoc($result))
   {
      extract($row);

      //$game_clause (lock) needed. See *** HOT_SECTION *** in confirm.php
      $game_clause = " WHERE ID=$gid AND Status!='FINISHED' AND Moves=$Moves LIMIT 1";

      $hours = ticks_to_hours($ticks - $LastTicks);

      if( $ToMove_ID == $Black_ID )
      {
         time_remaining( $hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $Black_Maintime == 0 && $Black_Byotime == 0 );
      }
      else if( $ToMove_ID == $White_ID )
      {
         time_remaining( $hours, $White_Maintime, $White_Byotime, $White_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $White_Maintime == 0 && $White_Byotime == 0 );
      }
      else
         continue; //$ToMove_ID ==0 if INVITED or FINISHED


      if( $time_is_up )
      {
         //TODO: Delete games with too few moves ???
         $score = ( $ToMove_ID == $Black_ID ? SCORE_TIME : -SCORE_TIME );
         $prow = mysql_single_fetch( "clock_tick.find_timeout_players($White_ID,$Black_ID)",
            'SELECT black.Handle as blackhandle, white.Handle as whitehandle'
               .', black.Name as blackname, white.Name as whitename'
            .' FROM (Players as white, Players as black)' // no JOIN (only to save 1 query) -> TODO: use Players where ID IN(..) in prep for TeamGo
            ." WHERE white.ID=$White_ID AND black.ID=$Black_ID" );
         if( !$prow )
            error('unknown_user', "clock_tick.find_timeout_players2($White_ID,$Black_ID)");
         extract($prow);

         //TODO: HOT_SECTION ???
         ta_begin();
         {//HOT-section to save game-timeout
            $game_query = "UPDATE Games SET Status='FINISHED', " . //See *** HOT_SECTION ***
                "Last_X=".POSX_TIME.", " .
                "ToMove_ID=0, " .
                "Score=$score, " .
                "Lastchanged=FROM_UNIXTIME($NOW)" ;

            db_query( "clock_tick.time_is_up($gid)", $game_query.$game_clause );

            if( @mysql_affected_rows() != 1)
            {
               error('mysql_update_game',"clock_tick.time_is_up2($gid)");
               continue;
            }

            // signal game-end for tournament
            if( $tid > 0 )
               TournamentGames::update_tournament_game_end( "clock_tick.tourney_game_end.timeout", $tid, $gid );

//FIXME(?)
/* To store the last $hours info (never used, to be checked)
         $to_move= ( $ToMove_ID == $Black_ID ? BLACK : WHITE );
         db_query( "clock_tick.update_moves($gid)",
            "INSERT INTO Moves SET " .
             "gid=$gid, " .
             "MoveNr=$Moves+1, " . //CAUTION: problem it's > Games.Moves
             "Stone=$to_move, " .
             "PosX=".POSX_TIME.", PosY=0, " .
             "Hours=$hours" );
         if( mysql_affected_rows() < 1 )
            error('mysql_insert_move', "clock_tick.update_moves($gid)");
*/

            // Send messages to the players
            $Text = "The result in the game:<center>"
                  . game_reference( REF_LINK, 1, '', $gid, 0, $whitename, $blackname)
                  . "</center>was:<center>"
                  . score2text($score,true,true)
                  . "</center>";
            if( $X_GameFlags & GAMEFLAGS_HIDDEN_MSG )
               $Text .= "<p><b>Info:</b> The game has hidden comments!";
            $Text.= "<p>Send a message to:<center>"
                  . send_reference( REF_LINK, 1, '', $White_ID, $whitename, $whitehandle)
                  . "<br>"
                  . send_reference( REF_LINK, 1, '', $Black_ID, $blackname, $blackhandle)
                  . "</center>" ;

            send_message( 'clock_tick', $Text, 'Game result'
               ,array($Black_ID,$White_ID), '', /*notify*/true
               , 0, 'RESULT', $gid);

            $rated_status = update_rating2($gid); //0=rated game

            // Change some stats
            db_query( "clock_tick.timeup_update_white($gid)",
               "UPDATE Players SET Running=Running-1, Finished=Finished+1"
               .($rated_status ? '' : ", RatedGames=RatedGames+1"
                  .($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
                ). " WHERE ID=$White_ID LIMIT 1" );

            db_query( "clock_tick.timeup_update_black($gid)",
               "UPDATE Players SET Running=Running-1, Finished=Finished+1"
               .($rated_status ? '' : ", RatedGames=RatedGames+1"
                  .($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
                ). " WHERE ID=$Black_ID LIMIT 1" );

            delete_all_observers($gid, $rated_status!=1, $Text);

            // GamesPriority-entries are kept for running games only, delete for finished games too
            NextGameOrder::delete_game_priorities( $gid );
         }
         ta_end();
      }
   }
   mysql_free_result($result);
   //unset($ticks);


   // ---------- END --------------------------------

   db_query( 'clock_tick.reset_tick',
         "UPDATE Clock SET Ticks=0 WHERE ID=201 LIMIT 1" );

   if( !$chained )
      $TheErrors->dump_exit('clock_tick');

}//$is_down
?>

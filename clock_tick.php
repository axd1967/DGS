<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TheErrors->set_mode(ERROR_MODE_COLLECT);

if( !$is_down )
{
   $chained = @$_REQUEST['chained'];
   if( @$chained ) //to be chained to other cron jobs (see at EOF)
   {
      $i = floor(3600/$tick_frequency);
      $tick_diff = $i - 10;
      $chained = $i;
   }
   else
   {
      $tick_diff = floor(3600/$tick_frequency) - 10;
   }
   connect2mysql();


   // Check that ticks are not too frequent

   $result = mysql_query( "SELECT ($NOW-UNIX_TIMESTAMP(Lastchanged)) AS timediff " .
                          "FROM Clock WHERE ID=201 LIMIT 1")
               or error('mysql_query_failed','clock_tick.check_frequency')
               or $TheErrors->dump_exit('clock_tick');

   $row = mysql_fetch_array( $result );
   mysql_free_result($result);

   if( $row['timediff'] < $tick_diff )
      //if( !@$_REQUEST['forced'] )
         $TheErrors->dump_exit('clock_tick');

   mysql_query("UPDATE Clock SET Ticks=1, Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=201")
               or error('mysql_query_failed','clock_tick.set_lastchanged')
               or $TheErrors->dump_exit('clock_tick');


   //setTZ('GMT');
   $hour = gmdate('G', $NOW);
   $day_of_week = gmdate('w', $NOW);

   // Now increase clocks that are not sleeping

   /* NIGHT_LEN hours night
    * Nightstart= N means night in [N,(N+NIGHT_LEN)%24[ (see edit_profile.php)
    * if Timezone=='GMT', ClockUsed is always equal to Nightstart
    * if Timezone=='GMT+x'(ou UTC+x), ClockUsed is ( Nightstart+24-x )%24
    *  ClockUsed is the GMT hour on which the user night start
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
   $cid = 0;
   $clock_modified = "((ID>$hour OR ID<".($hour-NIGHT_LEN+1).') AND ID<'.($hour+25-NIGHT_LEN)
                     ." AND ID>=$cid AND ID<=".($cid+23).')';

   $cid= WEEKEND_CLOCK_OFFSET;
   $hour+= $cid;
   if( $day_of_week > 0 && $day_of_week < 6 )
      $clock_modified.= " OR ((ID>$hour OR ID<".($hour-NIGHT_LEN+1).') AND ID<'.($hour+25-NIGHT_LEN)
                        ." AND ID>=$cid AND ID<=".($cid+23).')';


   $query = "UPDATE Clock SET Ticks=Ticks+1, Lastchanged=FROM_UNIXTIME($NOW)"
            .' WHERE '.$clock_modified;
   mysql_query( $query)
      or error('mysql_query_failed','clock_tick.increase_clocks');




   // Check if any game has timed out

if(1){//new
/*
 In a first approximation, as $has_moved==false (see time_remaining()),
  $main>$hours will just produce $main-=$hours which will never
  activate $time_is_up and so, will produce nothing here...
 So the "find_timeout_games" query may be restricted with:
 - given IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime) AS ToMove_Maintime
 - WHERE ToMove_Maintime <= $hours
 because $hours is always < $ticks/$tick_frequency (see ticks_to_hours())
 - given ((ticks-LastTicks) / $tick_frequency) AS Upper_Ellapsed
 - WHERE ToMove_Maintime < Upper_Ellapsed
 which is:
 - WHERE IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime)
      < ((Clock.Ticks-Games.LastTicks) / $tick_frequency)
 During a test, this lowered the number of returned rows from 10,960 to 675
*/
   $query= 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks'
         . ' FROM (Games, Clock)'
         . " WHERE Games.ClockUsed=Clock.ID"
         . ' AND '. str_replace('ID', 'Clock.ID', $clock_modified)
         . " AND Clock.Ticks - Games.LastTicks > $tick_frequency *"
            . ' IF(ToMove_ID=Black_ID, Black_Maintime, White_Maintime)'
         . " AND Games.Status!='INVITED' AND Games.Status!='FINISHED'"
         ;
}else{//old
/* TODO:
This query is sometime slow and may return more than 10000 rows!
It (and the following UPDATE) should be optimized, splited in smaller chunk?
use TEMPORARY TABLEs for generated UPDATEs ???
*/
   $query = 'SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks'
            . ' FROM (Games, Clock)'
            . " WHERE Clock.Lastchanged=FROM_UNIXTIME($NOW)"
            . ' AND Clock.ID>=0' // not VACATION_CLOCK
            . ' AND Games.ClockUsed=Clock.ID'
            //if both are <=0, the game will never finish by time:
            //. ' AND ( Maintime>0 OR Byotime>0 )'
            //slower: "AND Status" . IS_RUNNING_GAME
            . " AND Games.Status!='INVITED' AND Games.Status!='FINISHED'"
            ;
}//new/old
   $result = mysql_query( $query)
         or error('mysql_query_failed','clock_tick.find_timeout_games');

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
         $prow = mysql_single_fetch( 'clock_tick.find_timeout_players',
               'SELECT black.Handle as blackhandle, white.Handle as whitehandle' .
                     ', black.Name as blackname, white.Name as whitename' .
               ' FROM Players as white, Players as black' .
               " WHERE white.ID=$White_ID AND black.ID=$Black_ID" )
            or error('mysql_query_failed','clock_tick.find_timeout_players');
         extract($prow);

         //TODO: HOT_SECTION ???
         $game_query = "UPDATE Games SET Status='FINISHED', " . //See *** HOT_SECTION ***
             "Last_X=".POSX_TIME.", " .
             "ToMove_ID=0, " .
             "Score=$score, " .
             //"Flags=0, " . //Not useful
             "Lastchanged=FROM_UNIXTIME($NOW)" ;

         mysql_query( $game_query . $game_clause)
               or error('mysql_query_failed',"clock_tick.time_is_up($gid)");

         if( @mysql_affected_rows() != 1)
         {
            error('mysql_update_game',"clock_tick.time_is_up($gid)");
            continue;
         }

         // Send messages to the players
         $Text = "The result in the game:<center>"
               . game_reference( REF_LINK, 1, '', $gid, 0, $whitename, $blackname)
               . "</center>was:<center>"
               . score2text($score,true,true)
               . "</center>";
         $Text.= "Send a message to:<center>"
               . send_reference( REF_LINK, 1, '', $White_ID, $whitename, $whitehandle)
               . "<br>"
               . send_reference( REF_LINK, 1, '', $Black_ID, $blackname, $blackhandle)
               . "</center>" ;

         send_message( 'clock_tick', $Text, 'Game result'
            ,array($Black_ID,$White_ID), '', true
            , 0, 'RESULT', $gid);

//         update_rating($gid);
         $rated_status = update_rating2($gid); //0=rated game

         // Change some stats
         mysql_query( "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                      ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                       ($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
                      ) . " WHERE ID=$White_ID LIMIT 1" )
               or error('mysql_query_failed',"clock_tick.timeup_update_white($gid)");

         mysql_query( "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                      ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                       ($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
                      ) . " WHERE ID=$Black_ID LIMIT 1" )
               or error('mysql_query_failed',"clock_tick.timeup_update_black($gid)");

         delete_all_observers($gid, $rated_status!=1, $Text);

      }
   }
   mysql_free_result($result);
   unset($ticks);

   mysql_query("UPDATE Clock SET Ticks=0 WHERE ID=201")
               or error('mysql_query_failed','clock_tick.reset_tick');
if( !@$chained ) $TheErrors->dump_exit('clock_tick');
//the whole cron stuff in one cron job (else comments this line):
include_once( "halfhourly_cron.php" );
} //$is_down
?>

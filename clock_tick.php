<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/


$quick_errors = 1; //just store errors in log database
require_once( "include/std_functions.php" );
require_once( "include/rating.php" );

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
               or error('mysql_query_failed','clock_tick1');

   $row = mysql_fetch_array( $result );

   if( $row['timediff'] < $tick_diff )
      if( !@$_REQUEST['forced'] ) exit;

   mysql_query("UPDATE Clock SET Lastchanged=FROM_UNIXTIME($NOW) WHERE ID=201")
               or error('mysql_query_failed','clock_tick2');



   $hour = gmdate('G', $NOW);
   $day_of_week = gmdate('w', $NOW);

   // Now increase clocks that are not sleeping

   $query = "UPDATE Clock SET Ticks=Ticks+1, Lastchanged=FROM_UNIXTIME($NOW) " .
       "WHERE (ID>=0 AND (ID>$hour OR ID<". ($hour-8) . ') AND ID< '. ($hour+16) . ')';
       //WHERE ID>=0 AND ID<39 AND ((ID-$hour+23)%24)<15 //ID from 24 to 38 does not exist

   if( $day_of_week > 0 and $day_of_week < 6 )
      $query .= ' OR (ID>=100 AND (ID>' . ($hour+100) . ' OR ID<'. ($hour+92) .
         ') AND ID< '. ($hour+116) . ')';
       //WHERE ID>=100 AND ID<139 AND ((ID-100-$hour+23)%24)<15 //ID from 124 to 138 does not exist


   mysql_query( $query)
               or error('mysql_query_failed','clock_tick3');




   // Check if any game has timed out

   $result = mysql_query('SELECT Games.*, Games.ID as gid, Clock.Ticks as ticks, ' .
                         'black.Handle as blackhandle, white.Handle as whitehandle, ' .
                         'black.Name as blackname, white.Name as whitename ' .
                         'FROM Games, Clock ,Players as white, Players as black ' .
                         'WHERE Status!="INVITED" AND Status!="FINISHED" ' .
                         'AND Games.ClockUsed >= 0 ' .
                         "AND Clock.Lastchanged=FROM_UNIXTIME($NOW) " .
                         'AND ( Maintime>0 OR Byotime>0 ) ' .
                         'AND Games.ClockUsed=Clock.ID ' .
                         'AND white.ID=White_ID AND black.ID=Black_ID' )
               or error('mysql_query_failed','clock_tick4');

   while($row = mysql_fetch_array($result))
   {
      extract($row);
      $ticks = $ticks - $LastTicks;
      $hours = ( $ticks > $tick_frequency ? floor(($ticks-1) / $tick_frequency) : 0 );

      if( $ToMove_ID == $Black_ID )
      {
         time_remaining( $hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $Black_Maintime == 0 and $Black_Byotime == 0 );
      }
      else if( $ToMove_ID == $White_ID )
      {
         time_remaining( $hours, $White_Maintime, $White_Byotime, $White_Byoperiods,
            $Maintime, $Byotype, $Byotime, $Byoperiods, false);

         $time_is_up = ( $White_Maintime == 0 and $White_Byotime == 0 );
      }
      else
         continue;


      if( $time_is_up )
      {

         $score = ( $ToMove_ID == $Black_ID ? SCORE_TIME : -SCORE_TIME );

         //$game_clause (lock) needed. See *** HOT_SECTION *** in confirm.php
         $game_clause = " WHERE ID=$gid AND Status!='FINISHED' AND Moves=$Moves LIMIT 1";

         $game_query = "UPDATE Games SET Status='FINISHED', " . //See *** HOT_SECTION ***
             "Last_X=".POSX_TIME.", " .
             "ToMove_ID=0, " .
             "Score=$score, " .
             //"Flags=0, " . //Not useful
             "Lastchanged=FROM_UNIXTIME($NOW)" ;

         mysql_query( $game_query . $game_clause)
               or error('mysql_query_failed',"clock_tick5($gid)");

         if( mysql_affected_rows() != 1)
            error('mysql_update_game',"clock_tick10($gid)");

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

         $Text = addslashes( $Text);
         mysql_query( "INSERT INTO Messages SET Time=FROM_UNIXTIME($NOW), " .
                      "Game_ID=$gid, Subject='Game result', Text='$Text'")
               or error('mysql_query_failed',"clock_tick6($gid)");

         if( mysql_affected_rows() == 1)
         {
            $mid = mysql_insert_id();

            mysql_query("INSERT INTO MessageCorrespondents (uid,mid,Sender,Folder_nr) VALUES " .
                        "($Black_ID, $mid, 'N', ".FOLDER_NEW."), " .
                        "($White_ID, $mid, 'N', ".FOLDER_NEW.")");
         }

         // Notify players
         mysql_query( "UPDATE Players SET Notify='NEXT' " .
                      "WHERE (ID='$Black_ID' OR ID='$White_ID') " .
                      "AND SendEmail LIKE '%ON%' AND Notify='NONE' LIMIT 2")
               or error('mysql_query_failed',"clock_tick7($gid)");

//         update_rating($gid);
         $rated_status = update_rating2($gid); //0=rated game

         // Change some stats
         mysql_query( "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                      ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                       ($score > 0 ? ", Won=Won+1" : ($score < 0 ? ", Lost=Lost+1 " : ""))
                      ) . " WHERE ID=$White_ID LIMIT 1" )
               or error('mysql_query_failed',"clock_tick8($gid)");

         mysql_query( "UPDATE Players SET Running=Running-1, Finished=Finished+1" .
                      ($rated_status ? '' : ", RatedGames=RatedGames+1" .
                       ($score < 0 ? ", Won=Won+1" : ($score > 0 ? ", Lost=Lost+1 " : ""))
                      ) . " WHERE ID=$Black_ID LIMIT 1" )
               or error('mysql_query_failed',"clock_tick9($gid)");

         delete_all_observers($gid, $rated_status!=1, $Text);

      }
   }

if( !@$chained ) exit;
//the whole cron stuff in one cron job (else comments those 2 lines):
include_once( "halfhourly_cron.php" );
include_once( "daily_cron.php" );
}
?>
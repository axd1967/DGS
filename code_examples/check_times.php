<?php

chdir("../");
require_once("include/std_functions.php");
require_once( 'include/time_functions.php' );
chdir("code_examples/");

if(1){ //display/check time_remaining and add_time
 //require_once( "include/game_functions.php" );

   if( !@$dbcnx )
      connect2mysql(true);

   $logged_in = who_is_logged( $player_row);

   $game_rows[] = array(
         'Byotype' => BYOTYPE_FISCHER,
         'Maintime' => 6,
         'Byotime' => 2,
         'Byoperiods' => 99,
         'events' => array(
               array(0,0,0,0),
               array(0,1,0,0),
               array(1,1,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(2,1,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(2,0,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,1),
               array(0,0,1,1),
               array(0,1,0,0),
               array(0,0,3,0),
               array(0,0,0,1),
               array(0,0,0,1),
               array(0,0,0,1),
               array(0,0,3,1),
               array(0,0,1,1),
               array(0,0,99,0),
               array(0,1,0,0),
               array(1,0,0,0),
               array(15,0,0,0),
               ),
         );

   $game_rows[] = array(
         'Byotype' => BYOTYPE_CANADIAN,
         'Maintime' => 2,
         'Byotime' => 5,
         'Byoperiods' => 3,
         'events' => array(
               array(0,0,0,0),
               array(0,1,0,0),
               array(1,1,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(2,1,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(2,0,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,1,0,0),
               array(0,0,3,0),
               array(0,0,0,1),
               array(0,0,0,1),
               array(0,0,0,1),
               array(0,0,3,1),
               array(0,0,1,1),
               array(0,0,99,0),
               array(0,1,0,0),
               array(1,0,0,0),
               array(15,0,0,0),
               ),
         );

   $game_rows[] = array(
         'Byotype' => BYOTYPE_JAPANESE,
         'Maintime' => 2,
         'Byotime' => 2,
         'Byoperiods' => 3,
         'events' => array(
               array(0,0,0,0),
               array(0,1,0,0),
               array(1,1,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(2,1,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(2,0,0,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,0,1,0),
               array(0,1,0,0),
               array(0,0,3,0),
               array(0,0,0,1),
               array(0,0,3,1),
               array(0,0,1,1),
               array(0,0,99,0),
               array(0,1,0,0),
               array(1,0,0,0),
               array(15,0,0,0),
               ),
         );

   start_html( 'debug', 1);
   $nbcols = 5;

   foreach( $game_rows as $game_row )
   {
      echo "<br><table class=Infos>";

      $events = $game_row['events'];
      unset($game_row['events']);

      $str = '';
      $str = var_export($game_row,true);
      if( $str )
      {
         echo "<tr><td colspan=$nbcols>$str</td></tr>";
      }

      $game_row['White_Maintime'] = $game_row['Maintime'];
      $game_row['White_Byotime'] = 0;
      $game_row['White_Byoperiods'] = -1;

      echo '<tr>';
      echo "<th>#</th>";
      echo "<th>Add, Reset</th>";
      echo "<th>Hours, Moved</th>";
      echo "<th>Time remaining - long</th>";
      echo "<th>- short</th>";
      echo '</tr>';

      $lincnt = 0;
      foreach( $events as $sub)
      {
         list($add_hours,$reset_byo,$hours,$has_moved) = $sub;
         $lincnt++;

         $str = '';
         //$str = var_export($game_row,true);
         if( $str )
         {
            echo "<tr><td colspan=$nbcols>$str</td></tr>";
         }

         // see GameAddTime::add_time_opponent()
         if( $add_hours > 0 )
            $game_row["White_Maintime"]+= $add_hours;
         if( $reset_byo )
            $game_row["White_Byoperiods"] = -1;

         /*
         function time_remaining( $hours, &$main, &$byotime, &$byoper
            , $startmaintime, $byotype, $startbyotime, $startbyoper, $has_moved)
         */
         time_remaining($hours,
            $game_row['White_Maintime'], $game_row['White_Byotime'],
               $game_row['White_Byoperiods'],
            $game_row['Maintime'], $game_row['Byotype'],
               $game_row['Byotime'], $game_row['Byoperiods'],
            $has_moved);

         echo '<tr>';
         echo "<td>$lincnt</td>";
         echo "<td>$add_hours, $reset_byo</td>";
         echo "<td>$hours, $has_moved</td>";

         /*
         function echo_time_remaining( $maintime, $byotype, $byotime, $byoper
            , $startbyotime, $startbyoper, $fmtflags=TIMEFMT_..., $zero_value=NO_VALUE )
         */
         echo "<td>" .
            TimeFormat::echo_time_remaining( $game_row['White_Maintime'], $game_row['Byotype']
                          ,$game_row['White_Byotime'], $game_row['White_Byoperiods']
                          ,$game_row['Byotime'], $game_row['Byoperiods'],
                          TIMEFMT_SHORT | TIMEFMT_ADDTYPE | TIMEFMT_ADDEXTRA ) .
            "</td>";
         echo "<td>" .
            TimeFormat::echo_time_remaining( $game_row['White_Maintime'], $game_row['Byotype']
                          ,$game_row['White_Byotime'], $game_row['White_Byoperiods']
                          ,$game_row['Byotime'], $game_row['Byoperiods'],
                          TIMEFMT_SHORT | TIMEFMT_ADDTYPE | TIMEFMT_ADDEXTRA ) .
            "</td>";
         echo '</tr>';
      }

      echo "</table>";
   }
   end_html();
   exit;
}

?>

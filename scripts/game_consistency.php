<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// Checks and show errors in the Games database.

chdir( '../' );
require_once 'include/std_functions.php';
require_once 'include/board.php';
require_once 'include/move.php';
require_once 'include/game_functions.php';


{
   disable_cache();
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.game_consistency');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.game_consistency');
   if ( !(@$player_row['admin_level'] & ADMIN_DATABASE) )
      error('adminlevel_too_low', 'scripts.game_consistency');


   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   //gid could be '12' meaning for game>=12
   // or '12,27' meaning from game=12 to game=27
   @list( $gid1, $gid2) = explode( ',', @$_REQUEST['gid']);
   $gid1= (int)$gid1; $gid2= (int)$gid2;
   if ( $gid1 > 0 )
   {
      if ( $gid2 > 0 )
      {
         $page_args['gid'] = $gid1.','.$gid2;
         $where = " AND (Games.ID>=$gid1 AND Games.ID<=$gid2)";
      }
      else
      {
         $page_args['gid'] = $gid1;
         $where = " AND (Games.ID>=$gid1)";
      }
   }
   else
      $where = "";

   //limit could be '10' or '55,10'
   if ( ($lim=@$_REQUEST['limit']) > '' )
   {
      $page_args['limit'] = $lim;
      $limit = " LIMIT $lim";
   }
   else
      $limit = "";

   //since could be "2 DAY", "12 MONTH", ...
   if ( ($since=@$_REQUEST['since']) > '' )
   {
      $page_args['since'] = $since;
      $where.= " AND DATE_ADD(Games.Lastchanged,INTERVAL $since) > FROM_UNIXTIME($NOW)";
   }

   start_html( 'game_consistency', 0);

//echo ">>>> One shot fix. Do not run it again."; end_html(); exit;
echo ">>>> Most of them needs manual fixes.";
   if ( $do_it=@$_REQUEST['do_it'] )
   {
      function dbg_query($s) {
        if ( !mysql_query( $s) )
           die("<BR>$s;<BR>" . mysql_error() );
        echo " --- fixed. ";
      }
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      function dbg_query($s) { echo " --- query:<BR>$s; ";}
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }


//---------
   echo "\n<hr>check_consistency() on all running and finished games.\n";

   if ( $do_it )
   {
      echo "<br> >>> CAN'T BE FIXED\n";
   }
   else if ( 1 ) //long... could be skipped to check the others
   {
      $query = "SELECT ID"
         . " FROM Games WHERE Status ".not_in_clause( $ENUM_GAMES_STATUS, GAME_STATUS_KOMI, GAME_STATUS_SETUP, GAME_STATUS_INVITED )
         . "$where ORDER BY Games.ID$limit";

      echo "\n<br>query: $query;\n";
      $result = mysql_query($query)
          or die('<BR>' . mysql_error());

      $n= (int)@mysql_num_rows($result);
      echo "\n<br>=&gt; result: $n rows\n";

      if ( $n > 0 )
      while ( $row = mysql_fetch_assoc( $result ) )
      {
         //echo ' ' . $row['ID'];
         $gid = $row['ID'];
         if ( ($err=check_consistency($gid)) )
         {
            echo "<br>Game $gid: "
               . str_replace("\n","<br>\n&nbsp;- ",trim($err))."\n";
         }
         //else echo "Game $gid: Ok<br>\n";
      }
      mysql_free_result($result);

   } //do_it


//---------
   echo "\n<hr>Games start ratings check:";

   $query = "SELECT ID,Black_Start_Rating,White_Start_Rating"
      . " FROM Games WHERE"
      . " (  (Black_Start_Rating<".MIN_RATING." AND Black_Start_Rating!=-9999)"
      . " OR (White_Start_Rating<".MIN_RATING." AND White_Start_Rating!=-9999) )"
      . "$where ORDER BY Games.ID$limit";

   echo "\n<br>query: $query;\n";
   $result = mysql_query($query)
       or die('<BR>' . mysql_error());

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows\n";

   if ( $n > 0 )
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      if ( $do_it )
      {
         echo "<br> >>> CAN'T BE FIXED\n";
         break;
      }
      $GID= $row['ID'];
      echo "<br>Game $GID: Wrong start rating!\n";
      echo "<br>&nbsp;- B=".$row['Black_Start_Rating']." / W=".$row['White_Start_Rating']."\n";
   }
   mysql_free_result($result);


//---------
   /* TODO fix UNIX-time-handling for Starttime + Lastchanged :
   echo "\n<hr>Games start dates check:";

   $query = "SELECT ID,Starttime,Lastchanged"
      //. ",DATE_SUB(Lastchanged,INTERVAL '1 MONTH') as FakeStart"
      . " FROM Games WHERE Status ".not_in_clause( $ENUM_GAMES_STATUS, GAME_STATUS_SETUP, GAME_STATUS_INVITED )
         . " AND Starttime>Lastchanged"
      . "$where ORDER BY Games.ID$limit";

   echo "\n<br>query: $query;\n";
   $result = mysql_query($query)
       or die('<BR>' . mysql_error());

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows\n";

   if ( $n > 0 )
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $GID= $row['ID'];
      $date= $row['Starttime'];
      $dend= $row['Lastchanged'];
      echo "<br>Game $GID: Wrong start/end dates!\n";
      echo "<br>&nbsp;- $date >= $dend<br>\n";
      $date = substr($dend,0,11).'00:00:00';
      dbg_query("UPDATE Games SET Starttime=$date WHERE ID=$GID LIMIT 1");
   }
   mysql_free_result($result);
   */


//---------
   echo "\n<hr>Ratinglog end dates check:";

   $query = "SELECT Games.ID,Games.Lastchanged,B.Time as Black_Time,W.Time as White_Time " .
      "FROM Games " .
         "INNER JOIN Ratinglog AS B ON B.gid=Games.ID AND B.uid=Games.Black_ID " .
         "INNER JOIN Ratinglog AS W ON W.gid=Games.ID AND W.uid=Games.White_ID " .
      "WHERE Games.Rated='Done' AND (Games.Lastchanged!=B.Time OR Games.Lastchanged!=W.Time) $where " .
      "ORDER BY Games.ID $limit";

   echo "\n<br>query: $query;\n";
   $result = mysql_query($query)
       or die('<BR>' . mysql_error());

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows\n";

   if ( $n > 0 )
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      if ( $do_it )
      {
         echo "<br> >>> CAN'T BE FIXED\n";
         break;
      }
      $GID= $row['ID'];
      echo "<br>Game $GID: Wrong Ratinglog end dates!\n";
      echo "<br>&nbsp;- (".$row['Lastchanged'].",".$row['Black_Time'].",".$row['White_Time'].")\n";
   }
   mysql_free_result($result);

//---------
   echo "\n<hr>Games Priority check:";

   $query = "SELECT GPRIO.gid, IFNULL(Games.ID,0) AS X_MissGame, Games.Status "
      . "FROM GamesPriority AS GPRIO "
         . "LEFT JOIN Games ON Games.ID=GPRIO.gid "
      . "$where ORDER BY GPRIO.gid$limit";

   echo "\n<br>query: $query;\n";
   $result = mysql_query($query)
       or die('<BR>' . mysql_error());

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows\n";

   if ( $n > 0 )
   while ( $row = mysql_fetch_assoc( $result ) )
   {
      $gid = $row['gid'];
      $missgame_gid = $row['X_MissGame'];
      $status = $row['Status'];

      $errmsg = '';
      if ( $missgame_gid == 0 )
         $errmsg = "Found GamesPriority without Games-entry -> removing";
      elseif ( !isStartedGame($status) )
         $errmsg = "Found GamesPriority in non-started Games-status -> removing";
      if ( $errmsg )
      {
         echo "<br>Game $gid: $errmsg\n";
         dbg_query("DELETE FROM GamesPriority WHERE gid=$gid");
      }
   }
   mysql_free_result($result);

//---------
   echo "\n<hr>Done!!!\n";
   end_html();
}//main


function check_consistency( $gid)
{
   global $game_row;

   //echo "Game $gid: ";
   $result = mysql_query("SELECT * FROM Games WHERE ID=$gid");
   if ( @mysql_num_rows($result) != 1 )
   {
      if ( $result )
         mysql_free_result($result);
      return "Doesn't exist?";
   }

   $game_row = mysql_fetch_assoc($result);
   mysql_free_result($result);
   extract($game_row);
   $TheBoard = new Board( $gid, $Size, $Moves);

   $games_Black_Prisoners = $Black_Prisoners;
   $games_White_Prisoners = $White_Prisoners;
   $games_Last_X = $Last_X;
   $games_Last_Y = $Last_Y;
{//to fix the old way Ko detect. Could be removed when no more old way games.
  if ( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
}
   $games_Last_Move = $Last_Move;
   $game_flag_ko = ( $Flags & GAMEFLAGS_KO );

   $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid ORDER BY ID" )
       or die('<BR>' . mysql_error());

   $gchkmove = new GameCheckMove( $TheBoard );
   $Last_Move=''; $Last_X= $Last_Y= -1;
   $move_nr = 1; $to_move = BLACK; $GameFlags = 0;
   $Black_Prisoners = $White_Prisoners = $nr_prisoners = 0;
   $moves_Black_Prisoners = $moves_White_Prisoners = 0;
   $ID = 0;
   while ( $row = mysql_fetch_assoc($result) )
   {
      if ( !isset($row['ID']) )
         return "'ID' absent after ID=$ID!";
      $ID = $row['ID'];
      if ( !isset($row['MoveNr']) )
         return "'MoveNr' absent at ID=$ID!";
      $MoveNr = $row['MoveNr'];
      if ( !isset($row['Stone']) )
         return "'Stone' absent at ID=$ID!";
      $Stone = $row['Stone'];
      if ( !isset($row['PosX']) )
         return "'PosX' absent at ID=$ID!";
      $PosX = $row['PosX'];
      if ( !isset($row['PosY']) )
         return "'PosY' absent at ID=$ID!";
      $PosY = $row['PosY'];
      if ( !isset($row['Hours']) )
         return "'Hours' absent at ID=$ID!";
      $Hours = $row['Hours'];

      if ( !($Stone == WHITE || $Stone == BLACK ) || $PosX<0 )
      {
         if ( $PosX == POSX_ADDTIME )
         {
            //TODO(if SGF supports): include time-info, fields see const-def
            continue;
         }

         if ( $Stone == NONE )
         {
            $nr_prisoners++;
         }
         elseif ( $PosX < 0 )
         {
            if ( $move_nr != $MoveNr )
            {
               return "Wrong move number in Moves table!"
                  . "\n$MoveNr should be $move_nr";
            }
            if ( $to_move != $Stone )
            {
               return "Wrong color in Moves table!"
                  . "\nMove $MoveNr should be $to_move";
            }

            $Last_X = $PosX;
            $Last_Y = $PosY;

            $move_nr++;
            if ( $move_nr > $Handicap )
               $to_move = WHITE+BLACK-$to_move;
         }

         continue;
      }

      if ( $move_nr != $MoveNr )
      {
         return "Wrong move number in Moves table!"
            . "\n$MoveNr should be $move_nr";
      }
      if ( $to_move != $Stone )
      {
         return "Wrong color in Moves table!"
            . "\nMove $MoveNr should be $to_move";
      }

      if ( $to_move == BLACK )
         $moves_Black_Prisoners += $nr_prisoners;
      else
         $moves_White_Prisoners += $nr_prisoners;

      $coord = number2sgf_coords( $PosX, $PosY, $Size);
      if ( ($err = $gchkmove->check_move( $coord, $to_move, $Last_Move, $GameFlags, false)) )
         return "Problem at move $move_nr: $err";
      $gchkmove->update_prisoners( $Black_Prisoners, $White_Prisoners );

      if ( $gchkmove->nr_prisoners == 1 )
         $GameFlags |= GAMEFLAGS_KO;
      else
         $GameFlags &= ~GAMEFLAGS_KO;
      $Last_X = $PosX;
      $Last_Y = $PosY;
      $Last_Move = $coord;
      $nr_prisoners = 0;

      $move_nr++;
      if ( $move_nr > $Handicap )
         $to_move = WHITE+BLACK-$to_move;
   }
   mysql_free_result($result);

   $move_nr--;
   if ( $Moves != $move_nr )
   {
      return "Wrong number of moves!";
   }

   if ( $Black_Prisoners != $games_Black_Prisoners ||
       $White_Prisoners != $games_White_Prisoners )
   {
      return "Wrong number of prisoners in Games table!"
         . "\nBlack: $games_Black_Prisoners should be $Black_Prisoners"
         . "\nWhite: $games_White_Prisoners should be $White_Prisoners";
   }

   if ( $Black_Prisoners != $moves_Black_Prisoners ||
       $White_Prisoners != $moves_White_Prisoners )
   {
      return "Wrong number of prisoners removed!";
   }

   if ( isRunningGame($Status) )
   {
      //TODO handle shape-games W-to-start
      $handinr = ($Handicap < 2 ? 1 : $Handicap );
      $black_to_move = (($Moves < $handinr) || ($Moves-$handinr)%2 == 1 );
      $to_move = ( $black_to_move ? $Black_ID : $White_ID );
      if ( $ToMove_ID!=$to_move )
      {
         return "Wrong Player to move! Should be $to_move.";
      }

      if ( $game_flag_ko != $GameFlags
        || ( ($GameFlags & GAMEFLAGS_KO) && $games_Last_Move!=$Last_Move ) )
      {
         return "Wrong Ko status!"
            . "\nLast_Move: [$games_Last_Move] should be [$Last_Move]"
            . "\nFlags: $game_flag_ko should be $GameFlags";
      }

      if (  !($ClockUsed>=0 && $ClockUsed<24)
        && !($ClockUsed>=0+WEEKEND_CLOCK_OFFSET && $ClockUsed<24+WEEKEND_CLOCK_OFFSET)
        && !($ClockUsed==VACATION_CLOCK || $ClockUsed==VACATION_CLOCK+WEEKEND_CLOCK_OFFSET) )
      {
         return "Wrong ClockUsed! Can't be $ClockUsed.";
      }
   }
   elseif ( $Status == GAME_STATUS_INVITED )
   {
      //TODO add consistency-checks for INVITED
   }
   elseif ( $Status == GAME_STATUS_FINISHED )
   {
/* TODO? see time-out cleanup in clock_tick.php
      $few_moves = DELETE_LIMIT+$Handicap;
      if ( $Moves < $few_moves )
      {
         return "Too few moves ($Moves &lt; $few_moves)! Should be deleted.";
      }
*/
   }
   elseif ( $Status == GAME_STATUS_SETUP )
   {
      //TODO handle MP-game
   }
   elseif ( $Status == GAME_STATUS_KOMI )
   {
      //TODO handle fair-komi-game
   }

   return ''; //no error
} //check_consistency

?>

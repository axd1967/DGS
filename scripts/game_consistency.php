<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

// Checks and show errors in the Games database.

chdir( '../' );
require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );

{
   disable_cache();

   connect2mysql();

  $logged_in = who_is_logged( $player_row);

  if( !$logged_in )
    error("not_logged_in");

  $player_level = (int)$player_row['admin_level'];
  if( !($player_level & ADMIN_DATABASE) )
    error("adminlevel_too_low");


   start_html( 'game_consistency', 0);

      echo "<p></p>--- Report only:<br>";

   if( ($gid=@$_REQUEST['gid']) > 0 )
      $where = " AND ID>=$gid";
   else
      $where = "";

   if( ($lim=@$_REQUEST['limit']) > 0 )
      $limit = " LIMIT $lim";
   else
      $limit = "";

   //$since may be: "2 DAY", "12 MONTH", ...
   if( ($since=@$_REQUEST['since']) )
      $where.= " AND DATE_ADD(Lastchanged,INTERVAL $since) > FROM_UNIXTIME($NOW)";


   $query = "SELECT ID"
      . " FROM Games WHERE Status!='INVITED'$where ORDER BY ID$limit";

   echo "\n<p></p>query: $query;\n";
   $result = mysql_query($query);

   $n= (int)@mysql_num_rows($result);
   echo "\n<br>=&gt; result: $n rows<p></p>\n";

   if( $n > 0 )
   while( $row = mysql_fetch_assoc( $result ) )
   {
      //echo ' ' . $row["ID"];
      check_consistency($row["ID"]);
   }

   end_html();
}


function check_consistency( $gid)
{
   global $game_row;
   //to share them with check_move()
   global $prisoners, $nr_prisoners, $Black_Prisoners, $White_Prisoners,
      $colnr, $rownr, $Last_Move, $GameFlags;

   echo "Game $gid: ";
   $result = mysql_query("SELECT * from Games where ID=$gid");
   if( @mysql_num_rows($result) != 1 )
   {
      echo "Doesn't exist?<br>\n";
      return false;
   }

   $game_row = mysql_fetch_assoc($result);
   extract($game_row);
   $TheBoard = new Board( $gid, $Size, $Moves);

   $games_Black_Prisoners = $Black_Prisoners;
   $games_White_Prisoners = $White_Prisoners;
   $games_Last_X = $Last_X;
   $games_Last_Y = $Last_Y;
{//to fix the old way Ko detect. Could be removed when no more old way games.
  if( !@$Last_Move ) $Last_Move= number2sgf_coords($Last_X, $Last_Y, $Size);
}
   $games_Last_Move = $Last_Move;
   $games_Flags = ( $Flags ? KO : 0 );

   $result = mysql_query( "SELECT * FROM Moves WHERE gid=$gid order by ID" );

   $Last_Move=''; $Last_X= $Last_Y= -1;
   $move_nr = 1; $to_move = BLACK; $GameFlags = 0;
   $Black_Prisoners = $White_Prisoners = $nr_prisoners = 0;
   $moves_Black_Prisoners = $moves_White_Prisoners = 0;
   while( $row = mysql_fetch_assoc($result) )
   {
      extract($row);

      if( !($Stone == WHITE or $Stone == BLACK ) or $PosX<0 )
      {
         if( $Stone == NONE )
            $nr_prisoners++;
         elseif( $PosX < 0 )
         {
            if( $move_nr != $MoveNr )
            {
               echo "Wrong move number in Moves table!<br>\n";
               echo "&nbsp;- $MoveNr should be $move_nr<br>\n";
               return false;
            }
            if( $to_move != $Stone )
            {
               echo "Wrong color in Moves table!<br>\n";
               echo "&nbsp;- Move $MoveNr should be $to_move<br>\n";
               return false;
            }

            $Last_X = $PosX;
            $Last_Y = $PosY;

            $move_nr++;
            if( $move_nr > $Handicap )
               $to_move = WHITE+BLACK-$to_move;
         }

         continue;
      }

      if( $move_nr != $MoveNr )
      {
         echo "Wrong move number in Moves table!<br>\n";
         echo "&nbsp;- $MoveNr should be $move_nr<br>\n";
         return false;
      }
      if( $to_move != $Stone )
      {
         echo "Wrong color in Moves table!<br>\n";
         echo "&nbsp;- Move $MoveNr should be $to_move<br>\n";
         return false;
      }

      if( $to_move == BLACK )
         $moves_Black_Prisoners += $nr_prisoners;
      else
         $moves_White_Prisoners += $nr_prisoners;

      $coord = number2sgf_coords( $PosX, $PosY, $Size);

//ajusted globals by check_move(): $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners, $colnr, $rownr;
//here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)
      if( !check_move( $TheBoard, $coord, $to_move, false) )
      {
         echo ", problem at move $move_nr<br>\n";
         return false;
      }

      if( $nr_prisoners == 1 )
         $GameFlags |= KO;
      else
         $GameFlags &= ~KO;
      $Last_X = $PosX;
      $Last_Y = $PosY;
      $Last_Move = $coord;
      $nr_prisoners = 0;

      $move_nr++;
      if( $move_nr > $Handicap )
         $to_move = WHITE+BLACK-$to_move;
   }

   $move_nr--;
   if( $Moves != $move_nr )
   {
      echo "Wrong number of moves!<br>\n";
      return false;
   }

   if( $Black_Prisoners != $games_Black_Prisoners or
       $White_Prisoners != $games_White_Prisoners )
   {
      echo "Wrong number of prisoners in Games table!<br>\n";
      echo "&nbsp;- Black: $games_Black_Prisoners should be $Black_Prisoners<br>\n";
      echo "&nbsp;- White: $games_White_Prisoners should be $White_Prisoners<br>\n";
      return false;
   }

   if( $Black_Prisoners != $moves_Black_Prisoners or
       $White_Prisoners != $moves_White_Prisoners )
   {
      echo "Wrong number of prisoners removed!<br>\n";
      return false;
   }

   if( $Status!='FINISHED' )
   {
      $handinr = ($Handicap < 2 ? 1 : $Handicap );
      $black_to_move = (($Moves < $handinr) or ($Moves-$handinr)%2 == 1 );
      $to_move = ( $black_to_move ? $Black_ID : $White_ID );
      if( $ToMove_ID!=$to_move )
      {
         echo "Wrong Player to move! Should be $to_move.<br>\n";
         return false;
      }

      if( $games_Flags!=$GameFlags 
        or ( $GameFlags & KO && $games_Last_Move!=$Last_Move ) )
      {
         echo "Wrong Ko status!<br>\n";
         echo "&nbsp;- Last_Move: '$games_Last_Move' should be '$Last_Move'<br>\n";
         echo "&nbsp;- Flags: $games_Flags should be $GameFlags<br>\n";
         return false;
      }

      if(  !($ClockUsed>=0 && $ClockUsed<24)
        && !($ClockUsed>=0+WEEKEND_CLOCK_OFFSET && $ClockUsed<24+WEEKEND_CLOCK_OFFSET)
        && !($ClockUsed==VACATION_CLOCK or $ClockUsed==VACATION_CLOCK+WEEKEND_CLOCK_OFFSET) )
      {
         echo "Wrong ClockUsed! Can't be $ClockUsed.<br>\n";
         return false;
      }
   }
   else //$Status=='FINISHED'
   {
/*
      $few_moves = DELETE_LIMIT+$Handicap;
      if( $Moves < $few_moves )
      {
         echo "Too few moves ($Moves &lt; $few_moves)! Chould be deleted.<br>\n";
         return false;
      }
*/
   }

   echo "Ok<br>\n";
   return true;
} //check_consistency

?>
<?php
/*
Dragon Go Server
Copyright (C) 2001  Erik Ouchterlony

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

header ("Cache-Control: no-cache, must-revalidate, max_age=0"); 

include( "std_functions.php" );
include( "board.php" );

if( !$gid )
{
    header("Location: error.php?err=no_game_nr");
    exit;
}

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
{
    header("Location: error.php?err=not_logged_in");
    exit;
}

$result = mysql_query( "SELECT *,Flags+0 AS flags FROM Games WHERE ID=$gid" );

if(  mysql_num_rows($result) != 1 )
{
    header("Location: error.php?err=unknown_game");
    exit;
}

extract(mysql_fetch_array($result));

if( $player_row["ID"] != $ToMove_ID )
{
    header("Location: error.php?err=not_your_turn");
    exit;
}

if( $Status == 'INVITED' or $Status == 'FINISHED')
{
    header("Location: error.php?err=unknown_game");
    exit;
}

if( $Black_ID == $ToMove_ID )
     $to_move = BLACK;
else
     $to_move = WHITE;

$next_to_move = 3-$to_move;

$Moves++;

if( $Moves < $Handicap ) $next_to_move = BLACK;

$next_to_move_ID = ( $next_to_move == BLACK ? $Black_ID : $White_ID );

if( $message )
     make_html_safe( $message );



switch( $action )
{
 case 'move':
     {

         $prisoners = unserialize(urldecode($prisoners));
         reset($prisoners);

         $nr_prisoners = count($prisoners);

         if( $nr_prisoners == 1 )
             $flags |= KO;
         else
             $flags &= ~KO;

         $query = "INSERT INTO Moves$gid ( MoveNr, Stone, PosX, PosY, Text ) VALUES ";


         while( list($dummy, list($x,$y)) = each($prisoners) )
             {
                 $query .= "($Moves, \"NONE\", $x, $y, NULL), ";
             }


         if( $message )
             $query .= "($Moves, $to_move, $colnr, $rownr, '$message') ";
         else
             $query .= "($Moves, $to_move, $colnr, $rownr, NULL) ";


         $game_query = "UPDATE Games SET " .
              "Moves=$Moves, " .
              "Last_X=$colnr, " .
              "Last_Y=$rownr, " .
              "Status='PLAY', ";

         if( $nr_prisoners > 0 )
             if( $to_move == BLACK )
                 $game_query .= "Black_Prisoners=" . ( $Black_Prisoners + $nr_prisoners ) . ", ";
             else
                 $game_query .= "White_Prisoners=" . ( $White_Prisoners + $nr_prisoners ) . ", ";
         
         $game_query .= "ToMove_ID=$next_to_move_ID, " .
              "Flags=$flags " .
              "WHERE ID=$gid";
     }
     break;

 case 'pass':
     {
         if( $Moves < $Handicap )
             {
                 header("Location: error.php?err=early_pass");
                 exit;
             }

         if( $Status == 'PLAY' )
             $next_status = 'PASS';
         else if( $Status == 'PASS' )
             $next_status = 'SCORE';
         else
             {
                  header("Location: error.php?err=invalid_action");
                  exit;
             }

         $query = "INSERT INTO Moves$gid SET " . 
              "MoveNr=$Moves, " .
              "Stone=$to_move, " .
              "PosX=-1";

         if( $message )
             $query .= ", Text='$message'";

         $game_query = "UPDATE Games SET " .
              "Moves=$Moves, " .
              "Last_X=-1, " .
              "Status='$next_status', " .
              "ToMove_ID=$next_to_move_ID, " .
              "Flags=0 " .
              "WHERE ID=$gid";
     }
     break;
     
 case 'handicap':
     {
         if( $Status != 'PLAY' )
             {
                 header("Location: error.php?err=invalid_action");
                 exit;
             }

         if( strlen( $stonestring ) != 2 * $Handicap + 1 )
             {
                 header("Location: error.php?err=wrong_number_of_handicap_stone");
                 exit;
             }

         $query = "INSERT INTO Moves$gid ( MoveNr, Stone, PosX, PosY, Text ) VALUES ";


         for( $i=1; $i <= $Handicap; $i++ )
             {
                 $colnr = ord($stonestring[$i*2-1])-ord('a');
                 $rownr = ord($stonestring[$i*2])-ord('a');

                 if( $i == $Handicap )
                     if( $message )
                         $query .= "($i, " . BLACK . ", $colnr, $rownr, '$message')";
                     else
                         $query .= "($i, " . BLACK . ", $colnr, $rownr, NULL)";
                 
                 else
                     $query .= "($i, " . BLACK . ", $colnr, $rownr, NULL), ";
             }


         $game_query = "UPDATE Games SET " .
              "Moves=$Handicap, " .
              "Last_X=$colnr, " .
              "Last_Y=$rownr, " .
              "ToMove_ID=$White_ID " .
              "WHERE ID=$gid";
     }
     break;

 case 'resign':
     {
         $query = "INSERT INTO Moves$gid SET " . 
              "MoveNr=$Moves, " .
              "Stone=$to_move, " .
              "PosX=-2";

         if( $message )
             $query .= ", Text='$message'";

         if( $to_move == BLACK )
             $score = -1000;
         else
             $score = 1000;

         $game_query = "UPDATE Games SET " .
              "Moves=$Moves, " .
              "Last_X=-1, " .
              "Status='FINISHED', " .
              "ToMove_ID=NULL, " .
              "Score=$score, " .
              "Flags=0" .
              " WHERE ID=$gid";
     }
     break;

 case 'done':
     {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
             {
                 header("Location: error.php?err=invalid_action");
                 exit;
             }

         $prisoners = unserialize(urldecode($prisoners));
         reset($prisoners);

         $nr_prisoners = count($prisoners);

         $next_status = 'SCORE2';
         if( $Status == 'SCORE2' and  $nr_prisoners == 0 )
             $next_status = 'FINISHED';

         $query = "INSERT INTO Moves$gid ( MoveNr, Stone, PosX, PosY, Text ) VALUES ";


         while( list($dummy, list($x,$y)) = each($prisoners) )
             {
                 $query .= "($Moves, " . (9 - $to_move ) . ", $x, $y, NULL), ";
             }


         if( $message )
             $query .= "($Moves, $to_move, -2, NULL, '$message') ";
         else
             $query .= "($Moves, $to_move, -2, NULL, NULL) ";



         $game_query = "UPDATE Games SET " .
              "Moves=$Moves, " .
              "Last_X=-1, " .
              "Status='$next_status', ";

              if( $next_to_move == BLACK )
                  $game_query .= "ToMove_ID=$Black_ID, ";
              else
                  $game_query .= "ToMove_ID=$White_ID, ";

              $game_query .=
                   "Flags=0, " .
                   "Score=$score" .
                   " WHERE ID=$gid";
         
     }
     break;

 default:
     {
         header("Location: error.php?err=invalid_action");
         exit;
     }
}



$result = mysql_query( $query );

if( mysql_affected_rows() < 1)
{
    header("Location: error.php?err=mysql_insert_move");
    exit;
}



$result = mysql_query( $game_query );


if( mysql_affected_rows() != 1)
{
    header("Location: error.php?err=mysql_update_game");
    exit;
}


// Notify opponent about move

if( $next_to_move_ID != $player_row["ID"] )
{
  $result = mysql_query( "SELECT Flags+0 AS flags, Notify " .
                           "FROM Players WHERE ID='$next_to_move_ID'" );

  if( $row = mysql_fetch_array($result) and 
      $row["flags"] & WANT_EMAIL and $row["Notify"] == 'NONE' )
      {
          $result = mysql_query( "UPDATE Players SET Notify='NEXT' " .
                                 "WHERE ID='$next_to_move_ID'" );
      }
}


if( $next == "Submit and go to status" )
{
    header("Location: status.php");
    exit;
}
else if( $next == "Submit and go to next game" )
{
    // Should go to next game
    header("Location: status.php");
    exit;
}

header("Location: game.php?gid=$gid");
exit;
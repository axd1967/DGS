<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

require( "include/std_functions.php" );
require( "include/board.php" );
require( "include/move.php" );
require( "include/rating.php" );

{
   // abbreviations used to reduce file size
   if( $g ) $gid=$g;
   if( $a ) $action=$a;
   if( $m ) $move==$m;
   if( $c ) $coord=$c;
   if( $s ) $stonestring=$s;

   if( !$gid )
      error("no_game_nr");

   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);


   if( !$logged_in )
      error("not_logged_in");

   $result = mysql_query( "SELECT Games.*, " .
                          "Games.Flags+0 AS flags, " .
                          "black.Name AS Blackname, " .
                          "black.Handle AS Blackhandle, " .
                          "black.Rank AS Blackrank, " .
                          "black.Rating AS Blackrating, " .
                          "black.RatingStatus AS Blackratingstatus, " .
                          "white.Name AS Whitename, " .
                          "white.Handle AS Whitehandle, " .
                          "white.Rank AS Whiterank, " .
                          "white.Rating AS Whiterating, " .
                          "white.RatingStatus AS Whiteratingstatus " .
                          "FROM Games, Players AS black, Players AS white " .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

   if(  mysql_num_rows($result) != 1 )
      error("unknown_game");


   extract(mysql_fetch_array($result));

   if( $Status == 'INVITED' )
      error("unknown_game");


   if( $action and $player_row["ID"] != $ToMove_ID )
      error("not_your_turn");


   $may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID and (!$move or $move == $Moves) );

   if( $Black_ID == $ToMove_ID )
      $to_move = BLACK;
   else if( $White_ID == $ToMove_ID )
      $to_move = WHITE;
   else if( isset($to_move) )
   {
      error("database_corrupted");
   }

   if( !$action )
   {
      $action = 'just_looking';
      if( $may_play )
      {
         if( $Status == 'PLAY' or $Status == 'PASS' )
         {
            $action = 'choose_move';
            if( $Moves == 0 and $Handicap > 0 )
               $action = 'handicap';
         }
         else if( $Status == 'SCORE' or $Status == 'SCORE2' )
            $action = 'remove';
      }
   }

   if( $Status != 'FINISHED' and ($Maintime > 0 or $Byotime > 0) )
   {
      $ticks = get_clock_ticks($ClockUsed) - $LastTicks;
      $hours = ( $ticks > 0 ? (int)(($ticks-1) / $tick_frequency) : 0 );

      if( $to_move == BLACK )
      {
         time_remaining($hours, $Black_Maintime, $Black_Byotime, $Black_Byoperiods, $Maintime,
         $Byotype, $Byotime, $Byoperiods, false);
      }
      else
      {
         time_remaining($hours, $White_Maintime, $White_Byotime, $White_Byoperiods, $Maintime,
         $Byotype, $Byotime, $Byoperiods, false);
      }
   }



   $no_marked_dead = ( $Status == 'PLAY' or $Status == 'PASS' or
   $action == 'choose_move' or $action == 'move' );

   list($lastx,$lasty) =
      make_array( $gid, $array, $msg, $Moves, $move, $moves_result, $marked_dead, $no_marked_dead );

   $enable_message = true;

   switch( $action )
   {
      case 'just_looking':
      {
         if( $Status == 'FINISHED' )
            $extra_message = "<font color=\"blue\">" . score2text($Score, true) . "</font>";
         $enable_message = false;
         if( $move )
         {
            $Last_X = $lastx;
            $Last_Y = $lasty;
         }
      }
      break;

      case 'choose_move':
      {
         $enable_message = false;
      }
      break;

      case 'move':
      {
         check_move();

         reset($prisoners);
         $prisoner_string = "";

         while( list($dummy, list($x,$y)) = each($prisoners) )
         {
            $prisoner_string .= number2sgf_coords($x, $y, $Size);
         }

         if( strlen($prisoner_string) != $nr_prisoners*2 )
            error("move_problem");


         $Moves++;
         $Last_X = $colnr;
         $Last_Y = $rownr;
      }
      break;

      case 'handicap':
      {
         if( $Status != 'PLAY' )
            error("invalid_action");

         check_handicap();

      }
      break;

      case 'resign':
      {
         $extra_message = "<font color=\"red\">" . T_('Resigning') . "</font>";
      }
      break;


      case 'pass':
      {
         if( $Status != 'PLAY' and $Status != 'PASS' )
            error("invalid_action");

         $extra_message = "<font color=\"green\">" . T_('Passing') . "</font>";
      }
      break;

      case 'delete':
      {
         if( $Status != 'PLAY' or ( $Moves >= 4+$Handicap ) )
            error("invalid_action");

         $extra_message = "<font color=\"red\">" . T_('Deleting game') . "</font>";
      }
      break;

      case 'remove':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action");

         check_remove();

         $enable_message = false;

         $extra_message = "<font color=\"green\">" . T_('Please mark dead stones and click 'done' when finished.') . "</font>";
      }
      break;

      case 'done':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action");

         check_done();

         $extra_message = "<font color=\"blue\">" . T_('Score') . ": " .
             score2text($score, true) . "</font>";
      }
      break;

      default:
      {
         error("illegal_action");
      }
   }

   start_page(T_("Game"), true, $logged_in, $player_row);

   if( $enable_message ) $may_play = false;

   if( !$logged_in or ( $player_row["ID"] != $Black_ID and $player_row["ID"] != $White_ID ) )
      unset( $msg );
   else
      make_html_safe($msg, 'game');

   draw_board($Size, $array, $may_play, $gid, $Last_X, $Last_Y,
   $player_row["Stonesize"], $player_row["Boardfontsize"], $msg, $stonestring, $handi,
   $player_row["Boardcoords"], $player_row["Woodcolor"]);

   if( $extra_message )
      echo "<P><center>$extra_message</center>\n";

   if( $enable_message )
   {
      draw_message_box();
   }

   echo "<HR>\n";
   draw_game_info();
   echo "<HR>\n";

// display moves

   if( !$enable_message and $Moves > 0 )
   {
      draw_moves();
   }

   if( $action == 'remove' or $action == 'choose_move' or $action == 'just_looking' or
   $action == 'handicap' )
   {
      $width="100%";
      echo '
    <p>
    <table width="100%" border=0 cellspacing=0 cellpadding=4>
      <tr align="center">
';
      if( $action == 'choose_move' )
      {
         $width= ( $Moves < 4+$Handicap ? '20%' : '25%' );

         echo "<td width=\"$width\"><B><A href=\"game.php?gid=$gid&action=pass\">" . T_('Pass') . "</A></B></td>\n";

         if( $Moves < 4+$Handicap )
            echo "<td width=\"$width\"><B><A href=\"game.php?gid=$gid&action=delete\">" . T_('Delete game') . "</A></B></td>\n";
      }
      else if( $action == 'remove' )
      {
         $width="25%";
         echo "<td width=\"$width\"><B><A href=\"game.php?gid=$gid&action=done&stonestring=$stonestring\">" . T_('Done') . "</A></B></td>\n";
         echo "<td width=\"$width\"><B><A href=\"game.php?gid=$gid&action=choose_move\">" . T_('Resume playing') . "</A></B></td>\n";

      }
      else if( $action == 'handicap' )
      {
         $width="33%";
         echo "<td width=\"$width\"><B><A href=\"game.php?gid=$gid&action=delete\">" . T_('Delete game') . "</A></B></td>\n";

      }

      echo "<td width=\"$width\"><B><A href=\"" .
         ( $has_sgf_alias ? "game$gid.sgf" : "sgf.php?gid=$gid") .
         "\">" . T_('Download sgf') . "</A></B></td>\n";


      if( $action == 'choose_move' )
         echo "<td width=\"$width\"><B><A href=\"game.php?gid=$gid&action=resign\">" . T_('Resign') . "</A></B></td>\n";

      if( $action == 'choose_move' or $action == 'handicap' or $action == 'remove' )
         echo "<td width=\"$width\"><B><A href=\"confirm.php?gid=$gid&next=Skip+to+next+game\">" . T_('Skip to next game') . "</A></B></td>\n";


      echo"
      </tr>
    </table>
";
      end_page(false);
   }
   else
      end_page();
}
?>

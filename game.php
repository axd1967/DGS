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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/board.php" );
require_once( "include/move.php" );
require_once( "include/rating.php" );


{
   $gid = @$_GET['gid'];
   $action = @$_GET['action'];
   $move = @$_GET['move'];
   $coord = @$_GET['coord'];
   $stonestring = @$_GET['stonestring'];
   $toggleobserve = @$_GET['toggleobserve'];
   if( @$_GET['msg'] )
      $msg = $_GET['msg'];
   else
      $msg = '';

   // abbreviations used to reduce file size
   if( @$_GET['g'] ) $gid=$_GET['g'];
   if( @$_GET['a'] ) $action=$_GET['a'];
   if( @$_GET['m'] ) $move=$_GET['m'];
   if( @$_GET['c'] ) $coord=$_GET['c'];
   if( @$_GET['s'] ) $stonestring=$_GET['s'];

   connect2mysql();

   if( !$gid )
      error("no_game_nr");

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( $toggleobserve and $logged_in )
      toggle_observe_list($gid, $player_row["ID"]);



//    if( !$logged_in )
//       error("not_logged_in");

   $result = mysql_query( "SELECT Games.*, " .
                          "Games.Flags+0 AS flags, " .
                          "black.Name AS Blackname, " .
                          "black.Handle AS Blackhandle, " .
                          "black.Rank AS Blackrank, " .
                          "black.Rating2 AS Blackrating, " .
                          "black.RatingStatus AS Blackratingstatus, " .
                          "white.Name AS Whitename, " .
                          "white.Handle AS Whitehandle, " .
                          "white.Rank AS Whiterank, " .
                          "white.Rating2 AS Whiterating, " .
                          "white.RatingStatus AS Whiteratingstatus " .
                          "FROM Games, Players AS black, Players AS white " .
                          "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" );

   if(  mysql_num_rows($result) != 1 )
      error("unknown_game");


   $row = mysql_fetch_assoc($result);
   extract($row);

   if( $Status == 'INVITED' )
      error("unknown_game");


   if( $action and $player_row["ID"] != $ToMove_ID )
      error("not_your_turn");

   if( !$move ) $move = $Moves;
   $may_play = ( $logged_in and $player_row["ID"] == $ToMove_ID and $move == $Moves );

   $my_game = ( $player_row["ID"] == $Black_ID or $player_row["ID"] == $White_ID );

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
         time_remaining($hours, $row['Black_Maintime'], $row['Black_Byotime'],
                        $row['Black_Byoperiods'], $Maintime,
                        $Byotype, $Byotime, $Byoperiods, false);
      }
      else
      {
         time_remaining($hours, $row['White_Maintime'], $row['White_Byotime'],
                        $row['White_Byoperiods'], $Maintime,
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
         if( $lastx > 0 && $lasty > 0 )
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
  //ajusted globals by check_move(): $array, $Black_Prisoners, $White_Prisoners, $prisoners, $nr_prisoners;
  //here, $prisoners list the captured stones of play (or suicided stones if, a day, $suicide_allowed==true)

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
         if( $Status != 'PLAY' or ( $Moves >= DELETE_LIMIT+$Handicap ) )
            error("invalid_action");

         $extra_message = "<font color=\"red\">" . T_('Deleting game') . "</font>";
      }
      break;

      case 'remove':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action");

         check_remove( $coord );
  //ajusted globals by check_remove(): $array, $score, $stonestring;

         $enable_message = false;

         $extra_message = "<font color=\"blue\">" . T_('Score') . ": " .
             score2text($score, true) . "</font>";
         $extra_message.= "<P>";
         $extra_message.= "<font color=\"green\">" . T_("Please mark dead stones and click 'done' when finished.") . "</font>";
      }
      break;

      case 'done':
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            error("invalid_action");

         check_remove();
  //ajusted globals by check_remove(): $array, $score, $stonestring;

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
      $msg = make_html_safe($msg, 'game');


   $show_notes = false;
   if ($player_row["ID"] == $Black_ID)
     {
     $show_notes = true;
     $notes = $Black_Notes;
     }
   if ($player_row["ID"] == $White_ID)
     {
     $show_notes = true;
     $notes = $White_Notes;
     }
     
   if ($Size > $player_row["NotesCutoff"])
     {
     $notesheight = $player_row["NotesLargeHeight"];
     $noteswidth = $player_row["NotesLargeWidth"];
     $notesposition = $player_row["NotesLargePosition"];
     $notesenabled = $player_row["NotesLargeEnabled"];
     }
   else
     {
     $notesheight = $player_row["NotesSmallHeight"];
     $noteswidth = $player_row["NotesSmallWidth"];
     $notesposition = $player_row["NotesSmallPosition"];
     $notesenabled = $player_row["NotesSmallEnabled"];
     }

   echo "<table><tr><td>";
   draw_board($Size, $array, $may_play, $gid, $Last_X, $Last_Y,
              $player_row["Stonesize"], $msg, $stonestring, $handi,
              $player_row["Boardcoords"], $player_row["Woodcolor"]);

   if ($notesposition == 'BELOW')
     {
     echo "</td></tr><tr><td valign=top><center>";
     if ($notesenabled == 'ON' and $show_notes)
       draw_notes($notes, $notesheight, $noteswidth);
     echo "</center></td></tr></table>";
     if( $enable_message )
       {
       draw_message_box(); //use $stonestring, $prisoner_string, $move
       }
     }
   else
     {
     if( $enable_message )
       {
       draw_message_box(); //use $stonestring, $prisoner_string, $move
       }
     echo "</td><td valign=top><center>";
     if ($notesenabled == 'ON' and $show_notes)
       draw_notes($notes, $notesheight, $noteswidth);
     echo "</center></td></tr></table>";
     }


   if( $extra_message )
      echo "<P><center>$extra_message</center>\n";


   echo "<HR>\n";
   draw_game_info($row);
   echo "<HR>\n";

// display moves

   if( !$enable_message and $Moves > 0 )
   {
      draw_moves();
   }

   if( $action == 'remove' or $action == 'choose_move' or $action == 'just_looking' or
       $action == 'handicap' )
   {
      if( $action == 'choose_move' )
      {
         if( $Status != 'SCORE' and $Status != 'SCORE2' )
            $menu_array[T_('Pass')] = "game.php?gid=$gid&action=pass";

         if( $Moves < DELETE_LIMIT+$Handicap )
            $menu_array[T_('Delete game')] = "game.php?gid=$gid&action=delete";
      }
      else if( $action == 'remove' )
      {
         $menu_array[T_('Done')] = "game.php?gid=$gid&action=done&stonestring=$stonestring";
         $menu_array[T_('Resume playing')] = "game.php?gid=$gid&action=choose_move";
      }
      else if( $action == 'handicap' )
      {
         $menu_array[T_('Delete game')] = "game.php?gid=$gid&action=delete";
      }

      if( $action == 'choose_move' )
         $menu_array[T_('Resign')] = "game.php?gid=$gid&action=resign";

      $menu_array[T_('Download sgf')] = ( $has_sgf_alias ? "game$gid.sgf" : "sgf.php?gid=$gid");

      if( $my_game && !$has_sgf_alias )
      {
         $menu_array[T_('Download sgf with all comments')] = "sgf.php?gid=$gid&owned_comments=1" ;
      }

      if( $action == 'choose_move' or $action == 'handicap' or $action == 'remove' )
         $menu_array[T_('Skip to next game')] = "confirm.php?gid=$gid&skip=t";

      if( ($Status != 'FINISHED') and !$my_game and $logged_in )
      {
         if( is_on_observe_list( $gid, $player_row["ID"] ) )
            $menu_array[T_('Remove from observe list')] = "game.php?gid=$gid&toggleobserve=t";
         else
            $menu_array[T_('Add to observe list')] = "game.php?gid=$gid&toggleobserve=t";
      }
   }

   end_page($menu_array);
}
?>

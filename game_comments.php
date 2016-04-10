<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/table_columns.php';
require_once 'include/game_functions.php';
require_once 'include/game_comments.php';
require_once 'include/board.php';
require_once 'include/dgs_cache.php';
require_once 'include/classlib_goban.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/goban_handler_gfx.php';

$TheErrors->set_mode(ERROR_MODE_PRINT);


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('not_logged_in', 'game_comments');
   $my_id = $player_row['ID'];

   $gid = (int)@$_GET['gid'];
   if ( $gid <= 0 )
   {
      if ( preg_match('/game([0-9]+)/i', @$_SERVER['REQUEST_URI'], $result) )
         $gid = $result[1];
   }
   if ( $gid <= 0 )
      error('unknown_game', "game_comments($gid)");

   $game = mysql_single_fetch( 'game_comments.find_game',
      'SELECT G.Status, G.GameType, G.GamePlayers, G.Handicap, G.Moves, G.Black_ID, G.White_ID' .
         ', black.Name AS Blackname, white.Name AS Whitename' .
         ', black.Handle AS B_Handle, white.Handle AS W_Handle' .
      ' FROM Games AS G' .
         ' INNER JOIN Players AS black ON black.ID=G.Black_ID ' .
         ' INNER JOIN Players AS white ON white.ID=G.White_ID ' .
      " WHERE G.ID=$gid LIMIT 1" );
   if ( !$game )
      error('unknown_game', "game_comments.find_game($gid)");
   $gstatus = $game['Status'];
   if ( $gstatus == GAME_STATUS_SETUP || $gstatus == GAME_STATUS_INVITED )
      error('invalid_game_status', "game_comments.find_game($gid,$gstatus)");
   $game_type = $game['GameType'];

   if ( $my_id == $game['Black_ID'] )
      $my_color = BLACK;
   elseif ( $my_id == $game['White_ID'] )
      $my_color = WHITE;
   else
      $my_color = DAME;
   $my_game = ( $logged_in && $my_color != DAME );

   $is_mp_game = ( $game_type != GAMETYPE_GO );
   $mpg_users = array();
   $mpg_active_user = null;
   if ( $is_mp_game )
   {
      GamePlayer::load_users_for_mpgame( $gid, '', false, $mpg_users );
      $mpg_active_user = GamePlayer::find_mpg_user( $mpg_users, $my_id );
   }
   $gc_helper = new GameCommentHelper( $gid, $gstatus, $game_type, $game['GamePlayers'], $game['Handicap'],
      $mpg_users, $mpg_active_user );

   list( $arr_moves, $arr_movemsg ) = load_game_comments_data( $gid );

   $style_str = null; // if set, <igoban>-tag present in one of the move-messages
   foreach ( $arr_movemsg as $mvmsg )
   {
      if ( MarkupHandlerGoban::contains_goban($mvmsg) )
      {
         $cfg_board = ConfigBoard::load_config_board_or_default($my_id);
         $style_str = GobanHandlerGfxBoard::style_string( $cfg_board->get_stone_size() );
         break;
      }
   }


   start_html(T_('Comments'), true, @$player_row['SkinName'], $style_str );

   $str = game_reference( REF_LINK, 1, '', $gid, 0, $game )
      . " - #$gid - " . ( $gstatus == GAME_STATUS_FINISHED ? T_('Finished') : T_('Running') );
   echo "<h3 class=Header>$str</h3>";


   $ctable = new Table( 'comment', '', '', '', TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ctable->add_tablehead(1, T_('Moves'), 'Move');
   $ctable->add_tablehead(3, T_('Player') );
   $ctable->add_tablehead(2, T_('Comments'), 'Comment');

   $cnt_comments = $mpg_user = 0;
   $href_game_move = $base_path.'game.php?gid='.$gid.URI_AMP.'move=';
   foreach ( $arr_moves as $row )
   {
      $move_nr = (int)$row['MoveNr'];
      $Text = @$arr_movemsg[$move_nr];
      $is_black = ( $row['Stone'] == BLACK );

      $Text = $gc_helper->filter_comment( $Text, $move_nr, $row['Stone'], $my_color, /*html*/true );
      if ( (string)$Text == '' )
         continue;
      $mpg_user = ( $is_mp_game ) ? $gc_helper->get_mpg_user() : null;
      if ( $style_str )
         $Text = MarkupHandlerGoban::replace_igoban_tags( $Text );
      $cnt_comments++;

      $color_class = ' class="InTextStone"';
      if ( $is_black )
         $colortxt = '<img src="17/b.gif" alt="' . T_('Black') . "\"$color_class>" ;
      else
         $colortxt = '<img src="17/w.gif" alt="' . T_('White') . "\"$color_class>" ;

      $crow_strings = array();
      $crow_strings[1] = anchor( $href_game_move.$move_nr, $move_nr ) . "&nbsp;$colortxt";
      $crow_strings[2] = $Text;
      if ( $is_mp_game && is_array($mpg_user) )
         $crow_strings[3] = user_reference( REF_LINK, 1, '', $mpg_user['uid'], $mpg_user['Handle'], '' );
      else
         $crow_strings[3] = user_reference( REF_LINK, 1, '',
            ($is_black ? $game['Black_ID'] : $game['White_ID']),
            ($is_black ? $game['B_Handle'] : $game['W_Handle']), '' );

      $ctable->add_row( $crow_strings );
   }

   echo spacing(sprintf( T_('(%s moves, %s comments)'), (int)$game['Moves'], $cnt_comments ), 0, 'center'), "\n";
   $ctable->echo_table();

   end_html();
}//main


// return arr( $arr_moves, $arr_movemsg )
function load_game_comments_data( $gid )
{
   // load moves
   $arr_moves = array();
   $arr_cached_moves = Board::load_cache_game_moves(
      'game_comments.load_game_comments_data', $gid, /*fetch*/true, /*store*/false );
   if ( is_array($arr_cached_moves) )
   {
      foreach ( $arr_cached_moves as $row )
      {
         $stone = $row['Stone'];

         // include moves: PASS, SCORE, RESIGN
         if ( $row['PosX'] >= POSX_RESIGN && ($stone == BLACK || $stone == WHITE) )
            $arr_moves[] = $row;
      }
   }
   unset($arr_cached_moves);

   // load move-messages
   $arr_movemsg = Board::load_cache_game_move_message(
      'game_comments.load_game_comments_data', $gid, /*move*/null, /*fetch*/true, /*store*/false );

   return array( $arr_moves, $arr_movemsg );
}//load_game_comments_data

?>

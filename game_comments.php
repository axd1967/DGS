<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
      ' FROM Games AS G' .
         ' INNER JOIN Players AS black ON black.ID=G.Black_ID ' .
         ' INNER JOIN Players AS white ON white.ID=G.White_ID ' .
      " WHERE G.ID=$gid LIMIT 1" );
   if ( !$game )
      error('unknown_game', "game_comments.find_game($gid)");
   $gstatus = $game['Status'];
   if ( $gstatus == GAME_STATUS_SETUP || $gstatus == GAME_STATUS_INVITED )
      error('invalid_game_status', "game_comments.find_game($gid,$gstatus)");
   $game_players = $game['GamePlayers'];
   $handicap = $game['Handicap'];


   $my_game = ( $logged_in && ( $player_row['ID'] == $game['Black_ID'] || $player_row['ID'] == $game['White_ID'] ) ) ;
   if ( !$my_game )
      $my_color = DAME ;
   else
      $my_color = $player_row['ID'] == $game['Black_ID'] ? BLACK : WHITE ;

   $is_mp_game = ( $game['GameType'] != GAMETYPE_GO );
   $my_mpgame = ( !$my_game && $is_mp_game ) ? MultiPlayerGame::is_game_player($gid, $my_id) : $my_game;
   $html_mode = ( $gstatus == GAME_STATUS_FINISHED ) ? 'gameh' : 'game';

   $arr_users = array();
   if ( $is_mp_game )
      GamePlayer::load_users_for_mpgame( $gid, '', false, $arr_users );

   list( $arr_moves, $arr_movemsg ) = load_game_comments_data( $gid );

   $style_str = null; // if set, <igoban>-tag present in one of the move-messages
   foreach ( $arr_movemsg as $mvmsg )
   {
      if ( MarkupHandlerGoban::contains_goban($mvmsg) )
      {
         $cfg_board = ConfigBoard::load_config_board($my_id);
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
   if ( $is_mp_game )
      $ctable->add_tablehead(3, T_('Player') );
   $ctable->add_tablehead(2, T_('Comments'), 'Comment');

   $cnt_comments = $mpg_user = 0;
   foreach ( $arr_moves as $row )
   {
      $move_nr = (int)$row['MoveNr'];
      $Text = @$arr_movemsg[$move_nr];

      if ( $is_mp_game )
      {
         list( $group_color, $group_order, $move_color ) =
            MultiPlayerGame::calc_game_player_for_move( $game_players, $move_nr, $handicap, -1 );
         $mpg_user = GamePlayer::get_user_info( $arr_users, $group_color, $group_order );

         $move_html_mode = ( $my_id == @$mpg_user['uid'] ) ? 'gameh' : $html_mode;
      }
      else
         $move_html_mode = ( $row['Stone'] == $my_color ) ? 'gameh' : $html_mode;
      if ( !$my_game && !$my_mpgame )
         $Text = game_tag_filter( $Text);
      $Text = trim( make_html_safe($Text, $move_html_mode) );
      if ( (string)$Text == '' )
         continue;
      if ( $style_str )
         $Text = MarkupHandlerGoban::replace_igoban_tags( $Text );
      $cnt_comments++;

      $color_class = ' class="InTextStone"';
      if ( $row['Stone'] == BLACK )
         $colortxt = '<img src="17/b.gif" alt="' . T_('Black') . "\"$color_class>" ;
      else
         $colortxt = '<img src="17/w.gif" alt="' . T_('White') . "\"$color_class>" ;

      $crow_strings = array();
      $crow_strings[1] = "$move_nr&nbsp;$colortxt";
      $crow_strings[2] = $Text;
      if ( $is_mp_game && is_array($mpg_user) )
         $crow_strings[3] = user_reference( REF_LINK, 1, '', $mpg_user['uid'], $mpg_user['Handle'], '' );

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

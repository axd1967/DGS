<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TheErrors->set_mode(ERROR_MODE_PRINT);


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $gid = (int)@$_GET['gid'];
   if( $gid <= 0 )
   {
      if( preg_match('/game([0-9]+)/i', @$_SERVER['REQUEST_URI'], $result) )
         $gid = $result[1];
   }
   if( $gid <= 0 )
      error('unknown_game');

   $game = mysql_single_fetch( 'game_comments.find_game',
      'SELECT Games.Status, Games.GameType, Games.GamePlayers, Games.Handicap' .
         ', Games.Black_ID, Games.White_ID' .
         ', black.Name AS Blackname, white.Name AS Whitename' .
      ' FROM (Games, Players AS black, Players AS white)' .
      " WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID LIMIT 1" );
   if( !$game )
      error('unknown_game', "game_comments.find_game($gid)");
   $game_players = $game['GamePlayers'];
   $handicap = $game['Handicap'];


   $my_game = ( $logged_in && ( $player_row['ID'] == $game['Black_ID'] || $player_row['ID'] == $game['White_ID'] ) ) ;
   if( !$my_game )
      $my_color = DAME ;
   else
      $my_color = $player_row['ID'] == $game['Black_ID'] ? BLACK : WHITE ;

   $is_mp_game = ( $game['GameType'] != GAMETYPE_GO );
   $my_mpgame = ( !$my_game && $is_mp_game ) ? MultiPlayerGame::is_game_player($gid, $my_id) : $my_game;

   if( $game['Status'] == 'FINISHED' )
      $html_mode= 'gameh';
   else
      $html_mode= 'game';

   $arr_users = array();
   if( $is_mp_game )
      GamePlayer::load_users_for_mpgame( $gid, '', false, $arr_users );


   // include moves: PASS, SCORE, RESIGN
   $result = db_query( 'game_comments.messages',
      'SELECT Moves.MoveNr,Moves.Stone,MoveMessages.Text'
      .' FROM MoveMessages'
      ." INNER JOIN Moves ON Moves.gid=$gid AND Moves.MoveNr=MoveMessages.MoveNr"
      ." WHERE MoveMessages.gid=$gid"
            .' AND Moves.PosX>='.POSX_RESIGN.' AND Moves.Stone IN ('.WHITE.','.BLACK.')'
      .' ORDER BY Moves.MoveNr' );


   start_html(T_('Comments'), true, @$player_row['SkinName']);

   $str = game_reference( 0, 1, '', 0, 0, $game['Whitename'], $game['Blackname']);
   $str.= " - #$gid";
   $str.= ' - '.( $game['Status'] == GAME_STATUS_FINISHED ? T_('Finished') : T_('Running'));
   echo "<h3 class=Header>$str</h3>";


   $ctable = new Table( 'comment', '', '', '', TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ctable->add_tablehead(1, T_('Moves'), 'Move');
   if( $is_mp_game )
      $ctable->add_tablehead(3, T_('Player') );
   $ctable->add_tablehead(2, T_('Comments'), 'Comment');

   while( $row = mysql_fetch_assoc($result) )
   {
      $crow_strings = array();
      $Text = $row['Text'];
      if( !$my_game && !$my_mpgame )
         $Text = game_tag_filter( $Text);
      $Text = trim(make_html_safe( $Text, $row['Stone']==$my_color ? 'gameh' : $html_mode));
      if( empty($Text) ) continue;

      $color_class = ' class="InTextStone"';
      if( $row['Stone'] == BLACK )
         $colortxt = '<img src="17/b.gif" alt="' . T_('Black') . "\"$color_class>" ;
      else
         $colortxt = '<img src="17/w.gif" alt="' . T_('White') . "\"$color_class>" ;

      $move_nr = (int)$row['MoveNr'];

      $crow_strings[1] = "$move_nr&nbsp;$colortxt";
      $crow_strings[2] = $Text;
      if( $is_mp_game )
      {
         list( $group_color, $group_order, $move_color ) =
            MultiPlayerGame::calc_game_player_for_move( $game_players, $move_nr, $handicap, -1 );
         $mpg_user = GamePlayer::get_user_info( $arr_users, $group_color, $group_order );
         if( is_array($mpg_user) )
            $crow_strings[3] = user_reference( REF_LINK, 1, '', $mpg_user['uid'], $mpg_user['Handle'], '' );
      }

      $ctable->add_row( $crow_strings );
   }
   mysql_free_result($result);

   $ctable->echo_table();

   end_html();
}
?>

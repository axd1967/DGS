<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $gid = (int)@$_GET['gid'];
   if( $gid <= 0 )
   {
      if( preg_match('/game([0-9]+)/i', @$_SERVER['REQUEST_URI'], $result) )
         $gid = $result[1];
   }
   if( $gid <= 0 )
      error('unknown_game');

   $game = mysql_single_fetch( 'game_comments.find_game',
      "SELECT Games.Status, Games.Black_ID, Games.White_ID" .
      ", black.Name AS Blackname, white.Name AS Whitename" .
      " FROM Games, Players AS black, Players AS white" .
      " WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID LIMIT 1"
      );
   if( !$game )
      error('unknown_game');


   $my_game = ( $logged_in && ( $player_row['ID'] == $game['Black_ID'] || $player_row['ID'] == $game['White_ID'] ) ) ;
   if( !$my_game )
      $my_color = DAME ;
   else
      $my_color = $player_row['ID'] == $game['Black_ID'] ? BLACK : WHITE ;

   if( $game['Status'] == 'FINISHED' )
     $html_mode= 'gameh';
   else
     $html_mode= 'game';


   $result = db_query( 'game_comments.messages',
      'SELECT DISTINCT Moves.MoveNr,Moves.Stone,MoveMessages.Text'
      .' FROM MoveMessages'
      ." INNER JOIN Moves ON Moves.gid=$gid AND Moves.MoveNr=MoveMessages.MoveNr"
      ." WHERE MoveMessages.gid=$gid"
            .' AND Moves.PosX>=0 AND Moves.Stone IN ('.WHITE.','.BLACK.')'
      .' ORDER BY Moves.MoveNr' );


   start_html(T_('Comments'), true, @$player_row['SkinName']);

   $str = game_reference( 0, 1, '', 0, 0, $game['Whitename'], $game['Blackname']);
   $str.= " - #$gid";
   $str.= ' - '.( $game['Status'] == 'FINISHED' ? T_('Finished') : T_('Running'));
   echo "<h3 class=Header>$str</h3>";


   $ctable = new Table( 'comment', '');

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ctable->add_tablehead(1, T_('Moves'), 'Move');
   $ctable->add_tablehead(2, T_('Comments'), 'Comment');


   while( $row = mysql_fetch_assoc($result) )
   {
      $crow_strings = array();
      $Text = $row['Text'];
      if( !$my_game )
         $Text = game_tag_filter( $Text);
      $Text = trim(make_html_safe( $Text, $row['Stone']==$my_color ? 'gameh' : $html_mode));
      if( empty($Text) ) continue;

      $colortxt = " class=InTextStone";
      if( $row['Stone'] == BLACK )
        $colortxt = "<img src='17/b.gif' alt=\"" . T_('Black') . "\"$colortxt>" ;
      else
        $colortxt = "<img src='17/w.gif' alt=\"" . T_('White') . "\"$colortxt>" ;

      $movetxt = (int)$row['MoveNr'];

      $crow_strings[1] = "$movetxt&nbsp;$colortxt";
      $crow_strings[2] = $Text;

      $ctable->add_row( $crow_strings );
   }

   $ctable->echo_table();

   end_html();
}

?>

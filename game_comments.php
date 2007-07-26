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

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $gid = (int)@$_GET['gid'];
   if( $gid <= 0 )
   {
      if( eregi("game([0-9]+)", @$_SERVER['REQUEST_URI'], $result) )
         $gid = $result[1];
   }
   if( $gid <= 0 )
      error("unknown_game");

   $result = mysql_query(
      'SELECT Games.*, ' .
      "black.Name AS Blackname, " .
      "white.Name AS Whitename " .
      'FROM Games, Players AS black, Players AS white ' .
      "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" )
      or error('mysql_query_failed', 'game_comments.find_game');

   if( @mysql_num_rows($result) != 1 )
      error("unknown_game");

   $row = mysql_fetch_array($result);
   extract($row);


   $my_game = ( $logged_in and ( $player_row["ID"] == $Black_ID or $player_row["ID"] == $White_ID ) ) ;
   if( !$my_game )
      $my_color = DAME ;
   else
      $my_color = $player_row["ID"] == $Black_ID ? BLACK : WHITE ;

   if( $Status == 'FINISHED' )
     $html_mode= 'gameh';
   else
     $html_mode= 'game';


   $query= "SELECT DISTINCT Moves.MoveNr,Moves.Stone,MoveMessages.Text " .
           "FROM Moves, MoveMessages " .
           "WHERE Moves.gid=$gid " .
            "AND MoveMessages.gid=$gid AND MoveMessages.MoveNr=Moves.MoveNr " .
            "AND (Moves.Stone=".WHITE." OR Moves.Stone=".BLACK.") " .
           "ORDER BY Moves.MoveNr";
   $result = mysql_query($query)
      or error('mysql_query_failed', 'game_comments.messages');



   start_html(T_('Comments'), true, @$player_row['SkinName']);

   $str = game_reference( 0, 1, '', 0, 0, $Whitename, $Blackname);
   $str.= " - #$gid";
   $str.= ' - '.( $Status == 'FINISHED' ? T_('Finished') : T_('Running'));
   echo "<h3 class=Header>$str</h3>";


   $ctable = new Table( 'comment', '');
   
   $ctable->add_tablehead(1, T_('Moves'), NULL, true, true);
   $ctable->add_tablehead(2, T_('Comments'), NULL, true, true);


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

      $crow_strings[1] = "<TD class=Move>$movetxt&nbsp;$colortxt</TD>";
      $crow_strings[2] = "<TD class=Comment>$Text</TD>";

      $ctable->add_row( $crow_strings );
   }

   $ctable->echo_table();

   end_html();
}

?>

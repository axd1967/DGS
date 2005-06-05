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


//if( @$_GET['quick_mode'] )
   $quick_errors = 1;
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $gid = @$_GET['gid'];
   if( !$gid )
   {
      if( eregi("game([0-9]+)", @$_SERVER['REQUEST_URI'], $result) )
         $gid = $result[1];
   }
   if( !$gid )
      error("unknown_game");

   $result = mysql_query(
      'SELECT Games.*, ' .
      "black.Name AS Blackname, " .
      "white.Name AS Whitename " .
      'FROM Games, Players AS black, Players AS white ' .
      "WHERE Games.ID=$gid AND Black_ID=black.ID AND White_ID=white.ID" )
     or error("mysql_query_failed");

   if( @mysql_num_rows($result) != 1 )
      error("unknown_game");

   $row = mysql_fetch_array($result);
   extract($row);


   $my_game = ( $logged_in and ( $player_row["ID"] == $Black_ID or $player_row["ID"] == $White_ID ) ) ;
   if( !$my_game )
      error('not_owned_game');

   //if( !$my_game ) $my_color = DAME ; else
      $my_color = $player_row["ID"] == $Black_ID ? BLACK : WHITE ;

   if( $Status == 'FINISHED' )
     $html_mode= 'gameh';
   else
     $html_mode= 'game';


   $result = mysql_query( "SELECT DISTINCT Moves.MoveNr,Moves.Stone,MoveMessages.Text " .
                          "FROM Moves, MoveMessages " .
                          "WHERE Moves.gid=$gid " .
                           "AND MoveMessages.gid=$gid AND MoveMessages.MoveNr=Moves.MoveNr " .
                           "AND (Moves.Stone=".WHITE." OR Moves.Stone=".BLACK.") " .
                          "ORDER BY Moves.MoveNr" )
               or error("mysql_query_failed");



   start_html(T_('Comments'), true);
   echo "<center>";

   $str = "#$gid - " . game_reference( 0, 1, 0, 0, $Whitename, $Blackname);
   echo "<h3><font color=$h3_color>$str</font></h3>";


   $ctable = new Table('');
   
   $ctable->add_tablehead(1, T_('Moves'), NULL, true, true);
   $ctable->add_tablehead(2, T_('Comments'), NULL, true, true);


   while( $row = mysql_fetch_assoc($result) )
   {
      $Text = trim(make_html_safe($row['Text'], $row['Stone']==$my_color ? 'gameh' : $html_mode));
      if( empty($Text) ) continue;

      $colortxt = " align='top'";
      if( $row['Stone'] == BLACK )
        $colortxt = "<img src='17/b.gif' alt=\"" . T_('Black') . "\"$colortxt>" ;
      else
        $colortxt = "<img src='17/w.gif' alt=\"" . T_('White') . "\"$colortxt>" ;

      $crow_strings = array();

      $crow_strings[1] = "<TD align='right' valign='top'>" . $row['MoveNr'] .
                         "&nbsp;&nbsp;$colortxt</TD>";
      $crow_strings[2] = "<TD>$Text</TD>";

      $ctable->add_row( $crow_strings );
   }

   $ctable->echo_table();

   echo "</center>";
   end_html();
}

?>

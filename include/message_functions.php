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


// Prints game setting form used by invite.php

function game_settings_form($my_ID, $gid=NULL)
{

   // Default values:
   $Size = 19;
   $Komi = 6.5;
   $Handicap = 0;
   $MyColor = 'White';
   $Maintime = 3;
   $MaintimeUnit = 'months';
   $Byotype = 'JAP';
   $Byotime_jap = 1;
   $ByotimeUnit_jap = 'days';
   $Byoperiods_jap = 10;
   $Byotime_can = 15;
   $ByotimeUnit_can = 'days';
   $Byoperiods_can = 15;
   $Byotime_fis = 1;
   $ByotimeUnit_fis = 'days';
   $Weekendclock = true;
   $Rated = true;


   // If dispute, use values from game $gid
   if( $gid > 0 )
   {
      $my_ID = $player_row['ID'];
      $result = mysql_query( "SELECT Handle,Size,Komi,Handicap," .
                             "Maintime,Byotype,Byotime,Byoperiods,Rated,Weekendclock, " .
                             "(White_ID=$my_ID)+1 AS Color " .
                             "FROM Games,Players WHERE Games.ID=$gid " .
                             "AND ((Players.ID=Black_ID AND White_ID=$my_ID) " .
                             "OR (Players.ID=White_ID AND Black_ID=$my_ID)) " .
                             "AND Status='INVITED'" );

      if( mysql_num_rows($result) != 1 )
         error("unknown_game");

      $game_row = mysql_fetch_array($result);

      extract($game_row);

      $MyColor = ( $color == BLACK ? 'Black' : 'White' );
      $Rated = ( $Rated == 'Y' );
      $Weekendclock = ( $Weekendclock == 'Y' );

      $ByotimeUnit = 'hours';
      time_convert_to_longer_unit($Byotime, $ByotimeUnit);

      $MaintimeUnit = 'hours';
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);

      switch( $Byotype )
      {
         case 'JAP':
         {
            $Byotime_jap = $Byotime;
            $ByotimeUnit_jap = $ByotimeUnit;
            $Byoperiods_jap = $Byoperiods;
         }
         break;

         case 'CAN':
         {
            $Byotime_can = $Byotime;
            $ByotimeUnit_can = $ByotimeUnit;
            $Byoperiods_can = $Byoperiods;
         }
         break;

         case 'FIS':
         {
            $Byotime_fis = $Byotime;
            $ByotimeUnit_fis = $ByotimeUnit;
         }
         break;
      }

   }


   $value_array=array();
   for( $bs = 5; $bs <= 25; $bs++ )
     $value_array[$bs]=$bs;

   echo form_insert_row( 'DESCRIPTION', 'Board size',
                         'SELECTBOX', 'size', 1, $value_array, $Size, false );

   $value_array=array( 'White' => 'White', 'Black' => 'Black' );
   echo form_insert_row( 'DESCRIPTION', 'My color',
                         'SELECTBOX', 'color', 1, $value_array, $MyColor, false );

   $value_array=array( 0 => 0 );
   for( $bs = 2; $bs <= 20; $bs++ )
     $value_array[$bs]=$bs;

   echo form_insert_row( 'DESCRIPTION', 'Handicap',
                         'SELECTBOX', 'handicap', 1, $value_array, $Handicap, false );

   echo form_insert_row( 'DESCRIPTION', 'Komi',
                         'TEXTINPUT', 'komi', 5, 5, $Komi );

   $value_array=array( 'hours' => 'hours', 'days' => 'days', 'months' => 'months' );
   echo form_insert_row( 'DESCRIPTION', 'Main time',
                         'TEXTINPUT', 'timevalue', 5, 5, $Maintime,
                         'SELECTBOX', 'timeunit', 1, $value_array, $MaintimeUnit, false );

   echo form_insert_row( 'DESCRIPTION', 'Japanese byo-yomi',
                         'RADIOBUTTONS', 'byoyomitype', array( 'JAP' => '' ), $Byotype,
                         'TEXTINPUT', 'byotimevalue_jap', 5, 5, $Byotime_jap,
                         'SELECTBOX', 'timeunit_jap', 1, $value_array, $ByotimeUnit_jap, false,
                         'TEXT', 'with&nbsp;',
                         'TEXTINPUT', 'byoperiods_jap', 5, 5, $Byoperiods_jap,
                         'TEXT', 'extra periods.' );

   echo form_insert_row( 'DESCRIPTION', 'Canadian byo-yomi',
                         'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), $Byotype,
                         'TEXTINPUT', 'byotimevalue_can', 5, 5, $Byotime_can,
                         'SELECTBOX', 'timeunit_can', 1, $value_array, $ByotimeUnit_can, false,
                         'TEXT', 'for&nbsp;',
                         'TEXTINPUT', 'byoperiods_can', 5, 5, $Byoperiods_can,
                         'TEXT', 'stones.' );

   echo form_insert_row( 'DESCRIPTION', 'Fischer time',
                         'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), $Byotype,
                         'TEXTINPUT', 'byotimevalue_fis', 5, 5, $Byotime_fis,
                         'SELECTBOX', 'timeunit_fis', 1, $value_array, $ByotimeUnit_fis, false,
                         'TEXT', 'extra&nbsp;per move.' );

   echo form_insert_row( 'DESCRIPTION', 'Clock runs on weekends',
                         'CHECKBOX', 'weekendclock', 'Y', "", $Weekendclock );
   echo form_insert_row( 'DESCRIPTION', 'Rated',
                         'CHECKBOX', 'rated', 'Y', "", $Rated );
}

function message_info_table($date, $to_me, $sender_id, $sender_name, $sender_handle,
                            $subject, $reply_mid, $text)
{
   global $date_fmt;

   echo "<table>\n" .
      "<tr><td>Date:</td><td>" . date($date_fmt, $date) . "</td></tr>\n" .
      "<tr><td>" . ($to_me ? "From" : "To" ) . ":</td>\n" .
      "<td><A href=\"userinfo.php?uid=$sender_id\">$sender_name ($sender_handle)</A>" .
      "</td></tr>\n" .
      "<tr><td>Subject:</td><td>$subject</td></tr>\n" .
      "<tr><td valign=\"top\">" .
      ( $reply_mid > 0 ?
        "<a href=\"message.php?mode=ShowMessage&mid=$reply_mid\">Replied:</a>" :
        "Message:" ) . "</td>\n" .
      "<td align=\"center\">\n" .
      "<table border=2 align=center><tr>" .
      "<td width=475 align=left>" . make_html_safe($text, true) . "</td></tr></table><BR>\n" .
      "</td></tr>\n</table>\n";
}


function game_info_table($Size, $col, $Komi, $Handicap,
                         $Maintime, $Byotype, $Byotime, $Byoperiods,
                         $Rated, $WeekendClock, $gid=NULL)
{
   echo '    <table align=center border=2 cellpadding=3 cellspacing=3>';

   if( $gid > 0 )
      echo "\n<tr><td>Game ID: </td><td><a href=\"game.php?gid=$gid\">$gid</a></td></tr>";

   echo '
      <tr><td>Size: </td><td>' . $Size .'</td></tr>
      <tr><td>Color: </td><td>' . $col . '</td></tr>
      <tr><td>Komi: </td><td>' . $Komi . '</td></tr>
      <tr><td>Handicap: </td><td>' . $Handicap . '</td></tr>
      <tr><td>Main time: </td><td>'; echo_time($Maintime); echo "</td></tr>\n";

   if( $Byotype == 'JAP' )
   {
      echo '        <tr><td>Byo-yomi: </td><td> Japanese: ';
      echo_time($Byotime);
      echo ' per move and ' . $Byoperiods . ' extra periods </td></tr>' . "\n";
   }
   else if ( $Byotype == 'CAN' )
   {
      echo '        <tr><td>Byo-yomi: </td><td> Canadian: ';
      echo_time($Byotime);
      echo ' per ' .$Byoperiods . ' stones </td></tr>' . "\n";
   }
   else if ( $Byotype == 'FIS' )
   {
      echo '        <tr><td>Fischer time: </td><td> ';
      echo_time($Byotime);
      echo ' extra per move </td></tr>' . "\n";
   }

    echo '<tr><td>Rated: </td><td>' . ( $Rated == 'Y' ? 'Yes' : 'No' ) . '</td></tr>
<tr><td>Clock runs on weekends: </td><td>' . ( $WeekendClock == 'Y' ? 'Yes' : 'No' ) . '</td></tr>
</table>
';

}

?>

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
include( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( !$uid )
   {
      if( eregi("uid=([0-9]+)", $HTTP_REFERER, $result) )
         $uid = $result[1];
   }

   if( $uid and $uid != $player_row["ID"] )
   {
      $result = mysql_query( "SELECT Handle FROM Players WHERE ID=$uid" );

      if( mysql_num_rows( $result ) == 1 )
      {
         extract(mysql_fetch_array($result));
      }
   }


   start_page("Send Invitation", true, $logged_in, $player_row );

   echo "<center>\n";
   echo "<B><font size=+1>Invitation message:</font></B>\n<HR>\n";
   echo form_start( 'inviteform', 'send_message.php', 'POST' );

   echo form_insert_row( 'DESCRIPTION', 'To (userid)',
                         'TEXTINPUT', 'to', 50, 80, $Handle );

   echo form_insert_row( 'DESCRIPTION', 'Message',
                         'TEXTAREA', 'message', 50, 8, "" );

   $value_array=array();
   for( $bs = 5; $bs <= 25; $bs++ )
     $value_array[$bs]=$bs;

   echo form_insert_row( 'DESCRIPTION', 'Board size',
                         'SELECTBOX', 'size', 1, $value_array, 19, false );

   $value_array=array( 'White' => 'White', 'Black' => 'Black' );
   echo form_insert_row( 'DESCRIPTION', 'My color',
                         'SELECTBOX', 'color', 1, $value_array, 'White', false );

   $value_array=array( 0 => 0 );
   for( $bs = 2; $bs <= 20; $bs++ )
     $value_array[$bs]=$bs;

   echo form_insert_row( 'DESCRIPTION', 'Handicap',
                         'SELECTBOX', 'handicap', 1, $value_array, 0, false );

   echo form_insert_row( 'DESCRIPTION', 'Komi',
                         'TEXTINPUT', 'komi', 5, 5, '6.5' );

   $value_array=array( 'hours' => 'hours', 'days' => 'days', 'months' => 'months' );
   echo form_insert_row( 'DESCRIPTION', 'Main time',
                         'TEXTINPUT', 'timevalue', 5, 5, 3,
                         'SELECTBOX', 'timeunit', 1, $value_array, 'months', false );

   echo form_insert_row( 'DESCRIPTION', 'Japanese byo-yomi',
                         'RADIOBUTTONS', 'byoyomitype', array( 'JAP' => '' ), 'JAP',
                         'TEXTINPUT', 'byotimevalue_jap', 5, 5, 1,
                         'SELECTBOX', 'timeunit_jap', 1, $value_array, 'days', false,
                         'TEXT', 'with&nbsp;',
                         'TEXTINPUT', 'byoperiods_jap', 5, 5, 10,
                         'TEXT', 'extra periods.' );

   echo form_insert_row( 'DESCRIPTION', 'Canadian byo-yomi',
                         'RADIOBUTTONS', 'byoyomitype', array( 'CAN' => '' ), 'CAN',
                         'TEXTINPUT', 'byotimevalue_can', 5, 5, 15,
                         'SELECTBOX', 'timeunit_can', 1, $value_array, 'days', false,
                         'TEXT', 'for&nbsp;',
                         'TEXTINPUT', 'byoperiods_can', 5, 5, 15,
                         'TEXT', 'stones.' );

   echo form_insert_row( 'DESCRIPTION', 'Fischer time',
                         'RADIOBUTTONS', 'byoyomitype', array( 'FIS' => '' ), 'FIS',
                         'TEXTINPUT', 'byotimevalue_fis', 5, 5, 1,
                         'SELECTBOX', 'timeunit_fis', 1, $value_array, 'days', false,
                         'TEXT', 'extra&nbsp;per move.' );

   echo form_insert_row( 'DESCRIPTION', 'Clock runs on weekends',
                         'CHECKBOX', 'weekendclock', 'Y', "", true );
   echo form_insert_row( 'DESCRIPTION', 'Rated',
                         'CHECKBOX', 'rated', 'Y', "", true );
   echo form_insert_row( 'HIDDEN', 'type', 'INVITATION' );
   echo form_insert_row( 'SUBMITBUTTON', 'send', 'Send Invitation' );

   echo "</center>\n";

   end_page();
}
?>
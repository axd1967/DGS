<?php
/*
Dragon Go Server
Copyright (C) 2005-2006  Erik Ouchterlony, Rod Ival

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

require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

function slashed($string)
{
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
}


if( $is_down )
{
   warning($is_down_message);
}
else
{
   disable_cache();

   connect2mysql();

   $uhandle = '';  
   $uid = @$_REQUEST['uid'];
   if( $uid > 0 )
      $idmode= 'uid';
   else
   {
      $uid = 0;
      $uhandle = @$_REQUEST[UHANDLE_NAME];
      if( $uhandle )
         $idmode= 'handle';
      else
      {
         $uhandle= safe_getcookie('handle');
         if( $uhandle ) 
            $idmode= 'cookie';
         else
            error('no_uid','quick_status');
      }
   }
   

   $player_row = mysql_single_fetch( 'quick_status.find_player',
                  "SELECT ID, Timezone, " .
                  "UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode " .
                  "FROM Players WHERE " .
                  ( $idmode=='uid'
                        ? "ID=".$uid
                        : "Handle='".mysql_addslashes($uhandle)."'"
                  ) );

   if( !$player_row )
   {
      error('unknown_user','quick_status.find_player');
   }


   if( $idmode == 'cookie' )
   {
      if( $player_row['Sessioncode'] !== safe_getcookie('sessioncode')
          or $player_row["Expire"] < $NOW )
      {
         error("not_logged_in",'quick_status.expired');
      }
      $logged_in = true;
      setTZ( $player_row['Timezone']);
      $datfmt = 'Y-m-d H:i';
   }
   else
   {
      $logged_in = false;
      setTZ( 'GMT');
      $datfmt = 'Y-m-d H:i \G\M\T';
   }

   $my_id = $player_row['ID'];

   $nothing_found = true;

   if( $logged_in )
   {
      // New messages?
   
      $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
         "Messages.Subject, Players.Name AS sender " .
         "FROM (Messages, MessageCorrespondents AS me) " .
         "LEFT JOIN MessageCorrespondents AS other " .
           "ON other.mid=me.mid AND other.Sender!=me.Sender " .
         "LEFT JOIN Players ON Players.ID=other.uid " .
         "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
                 "AND Messages.ID=me.mid " .
                 "AND me.Sender='N' " . //exclude message to myself
         "ORDER BY date DESC";
   
      $result = mysql_query( $query )
         or error('mysql_query_failed','quick_status.find_messages');
   
      while( $row = mysql_fetch_assoc($result) )
      {
         $nothing_found = false;
         if( !@$row['sender'] ) $row['sender']='[Server message]';
         echo "'M', {$row['mid']}, '".slashed(@$row['sender'])."', '" .
            slashed(@$row['Subject']) . "', '" .
            date($datfmt, @$row['date']) . "'\n";
      }

   } //$logged_in
   else
   {
      warning('messages list not shown');
   } //$logged_in


   // Games to play?

   $query = "SELECT Black_ID,White_ID,Games.ID, (White_ID=$my_id)+0 AS Color, " .
       "UNIX_TIMESTAMP(LastChanged) as date, " .
       "opponent.Name, opponent.Handle, opponent.ID AS pid " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status!='INVITED' AND Status!='FINISHED' " .
         "AND opponent.ID=(Black_ID+White_ID-$my_id) " .
       "ORDER BY date DESC, Games.ID";

   $result = mysql_query( $query )
      or error('mysql_query_failed','quick_status.find_games');

   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      echo "'G', {$row['ID']}, '" . slashed(@$row['Name']) .
         "', '" . $clrs{@$row['Color']} . "', '" .
         date($datfmt, @$row['date']) . "'\n";
   }

    
   if( $nothing_found )
      warning('empty lists');
}
?>
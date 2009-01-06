<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

//force $language_used and $encoding_used
//see also recover_language() for regular recovery of $encoding_used
$encoding_used = 'utf-8'; //LANG_DEF_CHARSET;
$language_used = 'en'.LANG_CHARSET_CHAR.$encoding_used; //lowercase

function slashed($string)
{
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
}


function loc_start_page()
{
   global $encoding_used, $NOW;
   ob_start('ob_gzhandler');

   header('Content-Type: text/plain;charset='.$encoding_used);
   // this one open the text/plain in the browser by default
   // this one exist and put a costume of binary on the text
   //header( 'Content-type: application/octet-stream' );

   //header( "Content-Disposition: inline; filename=\"$filename\"" );
   //header( "Content-Disposition: attachment; filename=\"$filename\"" );
   header( "Content-Description: PHP Generated Data" );

   header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*60));
   header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));

}

function loc_end_page()
{
   ob_end_flush();
}


if( $is_down )
{
   //recover_language(); //set $language_used and $encoding_used
   loc_start_page();
   warning($is_down_message);
   loc_end_page();
}
else
{
   loc_start_page();
   //disable_cache();

   connect2mysql();

   $uhandle = '';
   $uid = (int)@$_REQUEST['uid'];
   if( $uid > 0 )
      $idmode= 'uid';
   else
   {
      $uid = 0;
      $uhandle = trim(@$_REQUEST[UHANDLE_NAME]);
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
                  "SELECT ID, Timezone, AdminOptions, " .
                  'UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode ' .
                  'FROM Players WHERE ' .
                  ( $idmode=='uid'
                        ? "ID=".((int)$uid)
                        : "Handle='".mysql_addslashes($uhandle)."'"
                  ) );

   if( !$player_row )
   {
      error('unknown_user','quick_status.find_player');
   }

   //TODO: fever vault check
   if( $idmode == 'cookie' )
   {
      if( $player_row['Sessioncode'] !== safe_getcookie('sessioncode')
          || $player_row["Expire"] < $NOW )
      {
         error('not_logged_in','quick_status.expired');
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
      if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
         error('login_denied');

      // New messages?

      $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
         "Messages.Subject, Players.Handle AS sender " .
         //"Messages.Subject, Players.Name AS sender " .
         "FROM (Messages, MessageCorrespondents AS me) " .
         "LEFT JOIN MessageCorrespondents AS other " .
           "ON other.mid=me.mid AND other.Sender!=me.Sender " .
         "LEFT JOIN Players ON Players.ID=other.uid " .
         "WHERE me.uid=$my_id AND me.Folder_nr=".FOLDER_NEW." " .
                 "AND Messages.ID=me.mid " .
                 "AND me.Sender IN('N','S') " . //exclude message to myself
         "ORDER BY date DESC";

      $result = mysql_query( $query )
         or error('mysql_query_failed','quick_status.find_messages');

      while( $row = mysql_fetch_assoc($result) )
      {
         $nothing_found = false;
         if( !@$row['sender'] ) $row['sender']='[Server message]';
         //Message.ID, correspondent.Handle, message.subject, message.date
         //N.B.: Subject is still in the correspondent's encoding.
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
       "UNIX_TIMESTAMP(Lastchanged) as date, " .
       "opponent.Name AS oName, opponent.Handle AS oHandle, opponent.ID AS oId " .
       "FROM Games,Players AS opponent " .
       "WHERE ToMove_ID=$my_id AND Status" . IS_RUNNING_GAME .
         "AND opponent.ID=(Black_ID+White_ID-$my_id) " .
       "ORDER BY LastChanged DESC, Games.ID";

   $result = mysql_query( $query )
      or error('mysql_query_failed','quick_status.find_games');

   $clrs="BW"; //player's color... so color to play.
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      //game.ID, opponent.handle, player.color, Lastmove.date
      echo "'G', {$row['ID']}, '" . slashed(@$row['oHandle']) .
      //echo "'G', {$row['ID']}, '" . slashed(@$row['oName']) .
         "', '" . $clrs{@$row['Color']} . "', '" .
         date($datfmt, @$row['date']) . "'\n";
   }


   if( $nothing_found )
      warning('empty lists');

   loc_end_page();
}
?>

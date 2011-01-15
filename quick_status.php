<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/translation_functions.php" );
require_once( "include/time_functions.php" );
require_once( "include/game_functions.php" );

$TheErrors->set_mode(ERROR_MODE_PRINT);

//force $language_used and $encoding_used
//see also recover_language() for regular recovery of $encoding_used
$encoding_used = 'utf-8'; //LANG_DEF_CHARSET;
$language_used = 'en'.LANG_CHARSET_CHAR.$encoding_used; //lowercase

function slashed($string)
{
   return str_replace( array( '\\', '\''), array( '\\\\', '\\\''), $string );
}


function loc_start_page( $use_cache=true )
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

   if( $use_cache )
   {
      header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*60));
      header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));
   }
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
   loc_start_page( !((bool)get_request_arg('no_cache','0')) );
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
            error('no_uid');
      }
   }


   $player_row = mysql_single_fetch( "quick_status.find_player($uid,$uhandle)",
                  "SELECT ID, Timezone, AdminOptions, " .
                  'UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode ' .
                  'FROM Players WHERE ' .
                  ( $idmode=='uid'
                        ? "ID=".((int)$uid)
                        : "Handle='".mysql_addslashes($uhandle)."'"
                  ) );

   if( !$player_row )
      error('unknown_user', "quick_status.find_player2($uid,$uhandle)");

   //TODO: fever vault check
   if( $idmode == 'cookie' )
   {
      if( $player_row['Sessioncode'] !== safe_getcookie('sessioncode') || $player_row["Expire"] < $NOW )
         error('not_logged_in','quick_status.expired');
      $logged_in = true;
      setTZ( $player_row['Timezone']);
      $datfmt = 'Y-m-d H:i:s';
   }
   else
   {
      $logged_in = false;
      setTZ( 'GMT');
      $datfmt = 'Y-m-d H:i:s \G\M\T';
   }

   $player_id = $player_row['ID'];

   $nothing_found = true;

   if( $logged_in )
   {
      if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
         error('login_denied');

      // New messages?

      $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, me.mid, " .
         "Messages.Subject, Players.Handle AS sender " .
         "FROM (Messages, MessageCorrespondents AS me) " .
         "LEFT JOIN MessageCorrespondents AS other " .
           "ON other.mid=me.mid AND other.Sender!=me.Sender " .
         "LEFT JOIN Players ON Players.ID=other.uid " .
         "WHERE me.uid=$player_id AND me.Folder_nr=".FOLDER_NEW." " .
                 "AND Messages.ID=me.mid " .
                 "AND me.Sender IN('N','S') " . //exclude message to myself
         "ORDER BY Messages.Time DESC";

      $result = db_query( 'quick_status.find_messages', $query );

      // message-header: type=M, Messages.ID, correspondent.Handle, message.Subject, message.Date
      echo "## M,message_id,'sender','subject','message_date'\n";

      while( $row = mysql_fetch_assoc($result) )
      {
         $nothing_found = false;
         if( !@$row['sender'] ) $row['sender']='[Server message]';

         // Message.ID, correspondent.Handle, message.subject, message.date
         //N.B.: Subject is still in the correspondent's encoding.
         echo sprintf( "M,%s,'%s','%s','%s'\n",
                       $row['mid'], slashed(@$row['sender']), slashed(@$row['Subject']),
                       date($datfmt, @$row['date']) );
      }

   } //$logged_in
   else
   {
      warning('messages list not shown');
   } //$logged_in


   // Games to play?

   $query = "SELECT Black_ID,White_ID,Games.ID, tid, " .
       "UNIX_TIMESTAMP(Games.Lastchanged) as date, " .
       "Maintime, Byotype, Byotime, Byoperiods, " .
       "White_Maintime, White_Byotime, White_Byoperiods, " .
       "Black_Maintime, Black_Byotime, Black_Byoperiods, " .
       "LastTicks, COALESCE(Clock.Ticks,0) AS X_Ticks, " .
       "Games.Moves, Games.Status, Games.GameType, Games.GamePlayers, " .
       "opponent.Name AS oName, opponent.Handle AS oHandle, opponent.ID AS oId " .
       "FROM Games " .
         "INNER JOIN Players AS opponent ON opponent.ID=(Black_ID+White_ID-$player_id) " .
         "LEFT JOIN Clock ON Clock.ID=Games.ClockUsed " .
       "WHERE ToMove_ID=$player_id AND Status" . IS_RUNNING_GAME . " " .
       "ORDER BY Games.LastChanged ASC, Games.ID";

   $result = db_query( 'quick_status.find_games', $query );

   // game-header: type=G, game.ID, opponent.handle, player.color, Lastmove.date, TimeRemaining, GameStatus, MovesId, tid, game_type
   echo "## G,game_id,'opponent_handle',player_color,'lastmove_date','time_remaining',game_status,move_id,tournament_id,game_type\n";

   $arr_colors = array( BLACK => 'B', WHITE => 'W' );
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;

      $player_color = ($player_id == $row['White_ID']) ? WHITE : BLACK;
      $time_remaining = build_time_remaining( $row, $player_color,
            /*is_to_move*/true, // always users turn
            TIMEFMT_ENGL | TIMEFMT_ADDTYPE | TIMEFMT_ADDEXTRA );

      $chk_game_status = strtoupper($row['Status']);
      $game_status = isRunningGame($chk_game_status) ? $chk_game_status : '';

      // type, game.ID, opponent.handle, player.color, Lastmove.date, TimeRemaining, GameStatus, Moves, tid, GameType
      echo sprintf( "G,%s,'%s',%s,'%s','%s',%s,%s,%s,'%s'\n",
                    $row['ID'], slashed(@$row['oHandle']), $arr_colors[$player_color],
                    date($datfmt, @$row['date']), $time_remaining['text'],
                    $game_status, $row['Moves'], $row['tid'],
                    MultiPlayerGame::format_game_type($row['GameType'], $row['GamePlayers'], true) );
   }
   mysql_free_result($result);


   if( $nothing_found )
      warning('empty lists');

   loc_end_page();
}
?>

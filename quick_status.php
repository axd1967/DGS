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

require_once( "include/quick_common.php" );
require_once( "include/connect2mysql.php" );
require_once( "include/translation_functions.php" );
require_once( "include/time_functions.php" );
require_once( "include/game_functions.php" );
require_once( "include/db/bulletin.php" );

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
   // format-version: 0|1 = DGS 1.0.14, 2 = DGS 1.0.15
   $version = (int)get_request_arg('version');

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
            error('no_uid', "quick_status.miss_user($uid,$uhandle)");
      }
   }


   $player_row = mysql_single_fetch( "quick_status.find_player($uid,$uhandle)",
                  "SELECT ID, Timezone, AdminOptions, CountBulletinNew, SkipBulletin, GamesMPG, NextGameOrder, " .
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
      $datfmt = DATE_FMT_QUICK;
   }
   else
   {
      $logged_in = false;
      setTZ( 'GMT');
      $datfmt = DATE_FMT_QUICK.' \G\M\T';
   }

   $player_id = @$player_row['ID'];
   if( $logged_in && (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied', "quick_status($player_id)");

   $nothing_found = true;

   if( $version == 2 && $logged_in && $player_id > 0 && $player_row['CountBulletinNew'] < 0 )
      Bulletin::update_count_bulletin_new( 'quick_status', $player_id, COUNTNEW_RECALC );

   if( $version == 2 && $logged_in && $player_row['CountBulletinNew'] > 0 )
   { // show unread bulletins
      $iterator = new ListIterator( 'quick_status.bulletins.unread',
         new QuerySQL( SQLP_WHERE,
               "BR.bid IS NULL", // only unread
               "B.Status='".BULLETIN_STATUS_SHOW."'" ),
         'ORDER BY B.PublishTime DESC' );
      $iterator->addQuerySQLMerge( Bulletin::build_view_query_sql( /*adm*/false, /*count*/false ) );
      $iterator = Bulletin::load_bulletins( $iterator );

      if( $iterator->ResultRows > 0 )
         $nothing_found = false;

      // Bulletin-header: type=B, Bulletin.ID, TargetType, Category, PublishTime, ExpireTime, Subject
      echo "## B,bulletin_id,target_type,category,'publish_time','expire_time','subject'\n";

      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $bulletin, $orow ) = $arr_item;

         // type, Bulletin.ID, TargetType, Category, PublishTime, ExpireTime, Subject
         echo sprintf( "B,%s,%s,%s,'%s','%s','%s'\n",
                       $bulletin->ID, $bulletin->TargetType, $bulletin->Category,
                       ($bulletin->PublishTime > 0) ? date(DATE_FMT_QUICK, $bulletin->PublishTime) : '',
                       ($bulletin->ExpireTime > 0) ? date(DATE_FMT_QUICK, $bulletin->ExpireTime) : '',
                       slashed($bulletin->Subject)
                     );
      }
   } // bulletins


   if( $logged_in )
   {
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
      if( $version == 2 )
      {
         echo "## M,message_id,'sender','subject','message_date'\n";
         $msg_fmt = "M,%s,'%s','%s','%s'\n";
      }
      else
         $msg_fmt = "'M',%s,'%s','%s','%s'\n"; // diff: M <- 'M'

      while( $row = mysql_fetch_assoc($result) )
      {
         $nothing_found = false;
         if( !@$row['sender'] ) $row['sender']='[Server message]';

         // Message.ID, correspondent.Handle, message.subject, message.date
         //N.B.: Subject is still in the correspondent's encoding.
         echo sprintf( $msg_fmt,
                       $row['mid'], slashed(@$row['sender']), slashed(@$row['Subject']),
                       date($datfmt, @$row['date']) );
      }
   }
   else
   {
      warning('messages list not shown');
   } //$logged_in


   // Games to play?
   $load_prio = ( $player_id > 0 );
   $game_order = strtoupper( trim( get_request_arg('order', @$player_row['NextGameOrder']) ));
   if( (string)$game_order == '' )
      $game_order = @$player_row['NextGameOrder'];
   $sql_order = NextGameOrder::get_next_game_order( $game_order, 'Games' ); // enum -> order
   $status_op = ( $version < 2 ) ? IS_RUNNING_GAME : IS_STARTED_GAME;

   $query = "SELECT Black_ID,White_ID,Games.ID, tid, " .
       "UNIX_TIMESTAMP(Games.Lastchanged) as date, " .
       "Maintime, Byotype, Byotime, Byoperiods, " .
       "White_Maintime, White_Byotime, White_Byoperiods, " .
       "Black_Maintime, Black_Byotime, Black_Byoperiods, " .
       "LastTicks, COALESCE(Clock.Ticks,0) AS X_Ticks, " .
       "Games.Moves, Games.Status, Games.GameType, Games.GamePlayers, Games.ShapeID, " .
       ( $load_prio ? "COALESCE(GP.Priority,0) AS X_Priority, " : "0 AS X_Priority, " ) .
       "opponent.Name AS oName, opponent.Handle AS oHandle, opponent.ID AS oId, " .
       "UNIX_TIMESTAMP(opponent.Lastaccess) AS oLastaccess " .
       "FROM Games " .
         "INNER JOIN Players AS opponent ON opponent.ID=(Black_ID+White_ID-$player_id) " .
         "LEFT JOIN Clock ON Clock.ID=Games.ClockUsed " .
         ( $load_prio ? "LEFT JOIN GamesPriority AS GP ON GP.gid=Games.ID AND GP.uid=$player_id " : '' ) .
       "WHERE ToMove_ID=$player_id AND Status$status_op " .
       $sql_order;

   $result = db_query( 'quick_status.find_games', $query );

   $timefmt_flags = TIMEFMT_ENGL | TIMEFMT_ADDTYPE;

   // game-header: type=G, game.ID, opponent.handle, player.color, Lastmove.date, TimeRemaining, GameStatus, MovesId, tid, ShapeID, GameType, GamePrio, opponent.LastAccess.date
   if( $version == 2 )
   {
      echo "## G,game_id,'opponent_handle',player_color,'lastmove_date','time_remaining',game_status,move_id,tournament_id,shape_id,game_type,game_prio,'opponent_lastaccess_date'\n";
      $timefmt_flags |= TIMEFMT_ADDEXTRA;
   }

   $arr_colors = array( BLACK => 'B', WHITE => 'W' );
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;

      $player_color = ($player_id == $row['White_ID']) ? WHITE : BLACK;
      $time_remaining = build_time_remaining( $row, $player_color, /*is_to_move*/true, // always users turn
            $timefmt_flags );

      $chk_game_status = strtoupper($row['Status']);
      $game_status = isStartedGame($chk_game_status) ? $chk_game_status : '';

      if( $version == 2 )
      {
         // type, game.ID, opponent.handle, player.color, Lastmove.date, TimeRemaining, GameStatus, MovesId, tid, ShapeID, GameType, GamePrio, opponent.LastAccess.date
         echo sprintf( "G,%s,'%s',%s,'%s','%s',%s,%s,%s,%s,'%s',%s,'%s'\n",
                       $row['ID'], slashed(@$row['oHandle']), $arr_colors[$player_color],
                       date($datfmt, @$row['date']), $time_remaining['text'],
                       $game_status, $row['Moves'], $row['tid'], (int)$row['ShapeID'],
                       GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true),
                       (int)@$row['X_Priority'],
                       date($datfmt, @$row['oLastaccess'])
                     );
      }
      else // older-version
      {
         // 'type', game.ID, 'opponent.handle', 'player.color', 'Lastmove.date', 'TimeRemaining'
         echo sprintf( "'G', %d, '%s', '%s', '%s', '%s'\n",
                       $row['ID'], slashed(@$row['oHandle']), $arr_colors[$player_color],
                       date($datfmt, @$row['date']), $time_remaining['text'] );
      }
   }
   mysql_free_result($result);


   // Multi-player-Games to manage?

   if( $version == 2 && $logged_in && $player_row['GamesMPG'] > 0 )
   {
      $query = "SELECT G.ID, G.GameType, G.GamePlayers, G.Ruleset, G.Size, G.Moves AS X_Joined, GP.Flags, "
         . "UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged "
         . "FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid "
         . "WHERE GP.uid=$player_id AND G.Status='".GAME_STATUS_SETUP."' "
         . "ORDER BY GP.gid";

      $result = db_query( "quick_status.find_mp_games($player_id)", $query );

      // MP-game-header: type=MPG, game.ID, game_type, Ruleset, Size, Lastchanged, ReadyToStart
      echo "## MPG,game_id,game_type,ruleset,size,'lastchanged_date',ready_to_start\n";

      while( $row = mysql_fetch_assoc($result) )
      {
         $nothing_found = false;

         $cnt_players = MultiPlayerGame::determine_player_count($row['GamePlayers']);

         // type, game.ID, game_type, Ruleset, Size, Lastchanged, ReadyToStart
         echo sprintf( "MPG,%s,%s,%s,%s,'%s',%s\n",
                       $row['ID'],
                       GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true),
                       $row['Ruleset'], $row['Size'],
                       ($row['X_Lastchanged'] > 0) ? date(DATE_FMT_QUICK, $row['X_Lastchanged']) : '',
                       ($row['X_Joined'] == $cnt_players) ? 1 : 0 );
      }
      mysql_free_result($result);
   }


   if( $nothing_found )
      warning('empty lists');

   loc_end_page();
}
?>

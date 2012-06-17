<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

define('MIN_REQ_IVAL_GAMES', 1); // min. request-interval [mins] for status-games
define('DEFAULT_MIN_REQ_IVAL', 15); // default min. request-interval [mins]

define('QST_CHECK_MIN', 1*60 - 5); //secs
define('QST_CHECK_MAX', DEFAULT_MIN_REQ_IVAL*60); //secs

//force $language_used and $encoding_used
//see also recover_language() for regular recovery of $encoding_used
$encoding_used = 'utf-8'; //LANG_DEF_CHARSET;
$language_used = 'en'.LANG_CHARSET_CHAR.$encoding_used; //lowercase


if( $is_down )
{
   //recover_language(); //set $language_used and $encoding_used
   loc_start_page();
   warning('server_down', str_replace("\n", "  ", $is_down_message));
   loc_end_page();
}
else
{
   // format-version: 0|1 = DGS 1.0.14, 2 = DGS 1.0.15
   $version = (int)get_request_arg('version');

   $no_cache = (int)get_request_arg('no_cache', '0');
   if( $no_cache == 2 && (FRIENDLY_SHORT_NAME == 'DGS') )
      error('invalid_args', "quick_status.check.no_cache.live_server($no_cache)");
   loc_start_page( !$no_cache );

   // login required to allow caching!
   $uhandle = trim( get_request_arg('userid') );
   $passwd = get_request_arg('passwd');
   if( $uhandle && $passwd )
      $login_mode = 'password';
   else
   {
      $uhandle = safe_getcookie('handle');
      $login_mode = 'cookie';
   }


   // authenticate + authorisation

   connect2mysql();

   $player_row = mysql_single_fetch( "quick_status.find_player($uhandle)",
         "SELECT ID, Timezone, AdminOptions, CountBulletinNew, SkipBulletin, GamesMPG, NextGameOrder, " .
         "UNIX_TIMESTAMP(Sessionexpire) AS Expire, Sessioncode, Password, Newpassword " .
         "FROM Players WHERE Handle='" . mysql_addslashes($uhandle)."' LIMIT 1" );
   if( !$player_row )
      error('unknown_user', "quick_status.find_player2($uhandle)");

   if( $login_mode == 'password' )
   {
      if( !check_password( $uhandle, $player_row['Password'], $player_row['Newpassword'], $passwd ) )
         error('wrong_password', "quick_status.check_password($uhandle)");
   }
   else //if( $login_mode == 'cookie' )
   {
      if( $player_row['Sessioncode'] !== safe_getcookie('sessioncode') || $player_row['Expire'] < $NOW )
         error('not_logged_in', "quick_status.expired($uhandle)");
   }


   // check for excessive usage, handle progressive block-specific caching

   $player_id = @$player_row['ID'];
   list( $allow_exec, $last_call_time, $path_cache, $content_header, $content_body ) =
      enforce_min_timeinterval( 'qst', "quick_status-$player_id", QST_CHECK_MIN, QST_CHECK_MAX, /*read-cont*/true );

   // block-specific caching-info
   $datablocks  = array(); // QST_CACHE_... key => rows-string
   $load_data   = array(); // QST_CACHE_... key => true (=data has to be loaded), false (=take from cache)
   $expire_time = array(); // QST_CACHE_... key => expire-time when to reload data
   $expire_min  = array(); // QST_CACHE_... key => minutes used to calculate current expire-time
   $crc32       = array(); // QST_CACHE_... key => crc32() of last printed data
   $ARR_CACHEKEYS = array( QST_CACHE_BULLETIN, QST_CACHE_MSG, QST_CACHE_GAMES, QST_CACHE_MPG );
   foreach( $ARR_CACHEKEYS as $block )
   {
      $datablocks[$block] = '';
      $load_data[$block] = true;
      $crc32[$block] = 0;

      $min = ($block == QST_CACHE_GAMES) ? MIN_REQ_IVAL_GAMES : DEFAULT_MIN_REQ_IVAL;
      $expire_min[$block] = $min;
      $expire_time[$block] = $NOW + 60 * $min;
   }

   if( $no_cache != 2 )
      $clear_cache = parse_cache_content( $content_body );
   else
      $clear_cache = true;

   if( !$allow_exec && !$clear_cache )
   {
      warning('excessive_usage',
         "quick_status.returning_cached_data($uhandle)" .
         ".see_faq[http://www.dragongoserver.net/faq.php?read=t&cat=15#Entry302]");
      writeIpStats('QSTC'); // cached
   }
   else
      writeIpStats('QST');

   setTZ( $player_row['Timezone'] );

   if( (@$player_row['AdminOptions'] & ADMOPT_DENY_LOGIN) )
      error('login_denied', "quick_status($player_id)");


   // Data-Blocks -------------------------------

   if( $version != 2 )
      warning('deprecated_version');

   $nothing_found = true;

   if( $version == 2 )
   {
      if( $load_data[QST_CACHE_BULLETIN] )
         print_bulletins( $player_row );
      else
         print_datablock(QST_CACHE_BULLETIN);
   }

   if( $load_data[QST_CACHE_MSG] )
      print_messages( $version, $player_id );
   else
      print_datablock(QST_CACHE_MSG);

   if( $load_data[QST_CACHE_GAMES] )
      print_status_games( $version, $player_row );
   else
      print_datablock(QST_CACHE_GAMES);

   if( $version == 2 )
   {
      if( $load_data[QST_CACHE_MPG] )
         print_mpg( $player_row );
      else
         print_datablock(QST_CACHE_MPG);
   }


   if( $nothing_found )
      warning('empty lists');

   if( $no_cache != 2 )
      write_quick_status_datastore( $path_cache, $content_header );

   loc_end_page();
}//main


function append_data( $block, $line=null )
{
   global $datablocks;
   if( is_null($line) )
      $datablocks[$block] = ''; // init
   else
      $datablocks[$block] .= $line;
   echo $line;
}

function print_datablock( $block )
{
   global $datablocks, $nothing_found;
   if( !empty($datablocks[$block]) )
   {
      echo $datablocks[$block];
      $nothing_found = false;
   }
}


function print_bulletins( $player_row )
{
   global $nothing_found, $expire_time, $expire_min;

   append_data(QST_CACHE_BULLETIN); //clear block

   $player_id = @$player_row['ID'];
   if( $player_id > 0 && $player_row['CountBulletinNew'] < 0 )
      Bulletin::update_count_bulletin_new( 'quick_status', $player_id, COUNTNEW_RECALC );

   // Bulletin-header: type=B, Bulletin.ID, TargetType, Category, PublishTime, ExpireTime, Subject
   append_data(QST_CACHE_BULLETIN,
      "## B,bulletin_id,target_type,category,'publish_time','expire_time','subject'\n" );

   if( $player_row['CountBulletinNew'] > 0 ) // show unread bulletins
   {
      $iterator = new ListIterator( 'quick_status.bulletins.unread',
         new QuerySQL( SQLP_WHERE,
               "BR.bid IS NULL", // only unread
               "B.Status='".BULLETIN_STATUS_SHOW."'" ),
         'ORDER BY B.PublishTime DESC' );
      $iterator->addQuerySQLMerge( Bulletin::build_view_query_sql( /*adm*/false, /*count*/false ) );
      $iterator = Bulletin::load_bulletins( $iterator );

      if( $iterator->ResultRows > 0 )
         $nothing_found = false;

      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $bulletin, $orow ) = $arr_item;

         // type, Bulletin.ID, TargetType, Category, PublishTime, ExpireTime, Subject
         append_data(QST_CACHE_BULLETIN,
              sprintf( "B,%s,%s,%s,'%s','%s','%s'\n",
                       $bulletin->ID, $bulletin->TargetType, $bulletin->Category,
                       ($bulletin->PublishTime > 0) ? date(DATE_FMT_QUICK, $bulletin->PublishTime) : '',
                       ($bulletin->ExpireTime > 0) ? date(DATE_FMT_QUICK, $bulletin->ExpireTime) : '',
                       slashed($bulletin->Subject)
                     ));
      }
   }

   $expire_time[QST_CACHE_BULLETIN] = $GLOBALS['NOW'] + $expire_min[QST_CACHE_BULLETIN] * 60;
}//print_bulletins

function print_messages( $version, $player_id )
{
   global $nothing_found, $expire_time, $expire_min;

   append_data(QST_CACHE_MSG); //clear block

   $query = "SELECT UNIX_TIMESTAMP(M.Time) AS date, me.mid, " .
      "me.Folder_nr, M.Type, M.Subject, Players.Handle AS sender " .
      "FROM Messages AS M " .
         "INNER JOIN MessageCorrespondents AS me ON me.mid=M.ID " .
         "LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender!=me.Sender " .
         "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$player_id AND me.Folder_nr IN (".FOLDER_NEW.",".FOLDER_REPLY.") " .
         "AND me.Sender IN ('N','S') " . //exclude message to myself
      "ORDER BY M.Time DESC";

   $result = db_query( 'quick_status.find_messages', $query );

   if( $version == 2 )
   {
      // message-header: type=M, Messages.ID, me.Folder_nr, Messages.Type, correspondent.Handle, message.Subject, message.Date
      append_data(QST_CACHE_MSG,
         "## M,message_id,folder_id,type,'sender','subject','date'\n" );
      $msg_fmt = "M,%s,%s,%s,'%s','%s','%s'\n";
   }
   else
   {
      // message-header: type=M, Messages.ID, correspondent.Handle, message.Subject, message.Date
      $msg_fmt = "'M',%s,'%s','%s','%s'\n"; // diff: M <- 'M'
   }

   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;
      if( !@$row['sender'] )
         $row['sender'] = '[Server message]';

      if( $version == 2 )
      {
         // type, msg.ID, me.Folder_nr, msg.Type, correspondent.Handle, msg.Subject, msg.Date
         append_data(QST_CACHE_MSG,
              sprintf( $msg_fmt,
                       $row['mid'], $row['Folder_nr'], strtoupper($row['Type']),
                       slashed(@$row['sender']), slashed(@$row['Subject']),
                       date(DATE_FMT_QUICK, @$row['date']) ));
      }
      else // older-version
      {
         // type, msg.ID, correspondent.Handle, msg.subject, msg.date
         //N.B.: Subject is still in the correspondent's encoding.
         append_data(QST_CACHE_MSG,
              sprintf( $msg_fmt,
                       $row['mid'], slashed(@$row['sender']), slashed(@$row['Subject']),
                       date(DATE_FMT_QUICK, @$row['date']) ));
      }
   }

   $expire_time[QST_CACHE_MSG] = $GLOBALS['NOW'] + $expire_min[QST_CACHE_MSG]  * 60;
}//print_messages

function print_status_games( $version, $player_row )
{
   global $datablocks, $nothing_found, $expire_time, $expire_min, $crc32;

   append_data(QST_CACHE_GAMES); //clear block

   $player_id = @$player_row['ID'];
   $load_prio = ( $player_id > 0 );
   $game_order = strtoupper( trim( get_request_arg('order', @$player_row['NextGameOrder']) ));
   if( (string)$game_order == '' )
      $game_order = @$player_row['NextGameOrder'];

   // build status-query (including next-game-order)
   $status_op = ( $version < 2 ) ? IS_RUNNING_GAME : IS_STARTED_GAME;
   $qsql = NextGameOrder::build_status_games_query( $player_id, $status_op, $game_order );
   $qsql->add_part( SQLP_FIELDS,
      "COALESCE(Clock.Ticks,0) AS X_Ticks", //always my clock because always my turn (status page)
      "UNIX_TIMESTAMP(opp.Lastaccess) AS opp_Lastaccess" );
   $qsql->add_part( SQLP_FROM,
      "LEFT JOIN Clock ON Clock.ID=Games.ClockUsed" );

   $result = db_query( 'quick_status.find_games', $qsql->get_select() );

   $timefmt_flags = TIMEFMT_ENGL | TIMEFMT_ADDTYPE;

   // game-header: type=G, game.ID, opponent.handle, player.color, Lastmove.date, TimeRemaining, GameAction, GameStatus, MovesId, tid, ShapeID, GameType, GamePrio, opponent.LastAccess.date, Handicap
   if( $version == 2 )
   {
      append_data(QST_CACHE_GAMES,
           "## G,game_id,'opponent_handle',player_color,'lastmove_date','time_remaining',game_action,game_status,move_id,tournament_id,shape_id,game_type,game_prio,'opponent_lastaccess_date',handicap\n" );
      $timefmt_flags |= TIMEFMT_ADDEXTRA;
   }

   $arr_colors = array( BLACK => 'B', WHITE => 'W' );
   $crc_val = (int)$version;
   while( $row = mysql_fetch_assoc($result) )
   {
      $nothing_found = false;

      $player_color = ($player_id == $row['White_ID']) ? WHITE : BLACK;
      $time_remaining = build_time_remaining( $row, $player_color, /*is_to_move*/true, // always users turn
            $timefmt_flags );

      $chk_game_status = strtoupper($row['Status']);
      $game_status = isStartedGame($chk_game_status) ? $chk_game_status : '';
      $crc_val .= ':' . $row['ID'] . '/' . $row['X_Lastchanged'];

      if( $version == 2 )
      {
         $game_action = GameHelper::get_quick_game_action( $row['Status'], $row['Handicap'], $row['Moves'],
            new FairKomiNegotiation( GameSetup::new_from_game_setup($row['GameSetup']), $row ) );

         // type, game.ID, opponent.handle, player.color, Lastmove.date, TimeRemaining, GameAction, GameStatus, MovesId, tid, ShapeID, GameType, GamePrio, opponent.LastAccess.date, Handicap
         append_data(QST_CACHE_GAMES,
              sprintf( "G,%s,'%s',%s,'%s','%s',%s,%s,%s,%s,%s,'%s',%s,'%s',%s\n",
                       $row['ID'], slashed(@$row['opp_Handle']), $arr_colors[$player_color],
                       date(DATE_FMT_QUICK, @$row['X_Lastchanged']), $time_remaining['text'],
                       $game_action, $game_status, $row['Moves'], $row['tid'], (int)$row['ShapeID'],
                       GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true),
                       (int)@$row['X_Priority'], date(DATE_FMT_QUICK, @$row['opp_Lastaccess']), $row['Handicap']
                     ));
      }
      else // older-version
      {
         // 'type', game.ID, 'opponent.handle', 'player.color', 'Lastmove.date', 'TimeRemaining'
         append_data(QST_CACHE_GAMES,
              sprintf( "'G', %d, '%s', '%s', '%s', '%s'\n",
                       $row['ID'], slashed(@$row['opp_Handle']), $arr_colors[$player_color],
                       date(DATE_FMT_QUICK, @$row['X_Lastchanged']), $time_remaining['text'] ));
      }
   }
   mysql_free_result($result);

   // progressive caching
   $new_crc = crc32($crc_val);
   if( $crc32[QST_CACHE_GAMES] == 0 || $crc32[QST_CACHE_GAMES] != $new_crc ) // changed data
   {
      $crc32[QST_CACHE_GAMES] = $new_crc;
      $exp_min = MIN_REQ_IVAL_GAMES;
   }
   else // same data -> double waiting-time
      $exp_min = min( 2 * $expire_min[QST_CACHE_GAMES], DEFAULT_MIN_REQ_IVAL );

   $expire_min[QST_CACHE_GAMES] = $exp_min;
   $expire_time[QST_CACHE_GAMES] = $GLOBALS['NOW'] + $exp_min * 60;
}//print_status_games

function print_mpg( $player_row )
{
   global $nothing_found, $expire_time, $expire_min;

   append_data(QST_CACHE_MPG); //clear block

   if( $player_row['GamesMPG'] > 0 )
   {
      $player_id = @$player_row['ID'];
      $query = "SELECT G.ID, G.GameType, G.GamePlayers, G.Ruleset, G.Size, G.Moves AS X_Joined, GP.Flags, "
         . "UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged "
         . "FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid "
         . "WHERE GP.uid=$player_id AND G.Status='".GAME_STATUS_SETUP."' "
         . "ORDER BY GP.gid";

      $result = db_query( "quick_status.find_mp_games($player_id)", $query );

      // MP-game-header: type=MPG, game.ID, game_type, Ruleset, Size, Lastchanged, ReadyToStart
      append_data(QST_CACHE_MPG,
         "## MPG,game_id,game_type,ruleset,size,'lastchanged_date',ready_to_start\n" );

      while( $row = mysql_fetch_assoc($result) )
      {
         $nothing_found = false;

         $cnt_players = MultiPlayerGame::determine_player_count($row['GamePlayers']);

         // type, game.ID, game_type, Ruleset, Size, Lastchanged, ReadyToStart
         append_data(QST_CACHE_MPG,
              sprintf( "MPG,%s,%s,%s,%s,'%s',%s\n",
                       $row['ID'],
                       GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true),
                       $row['Ruleset'], $row['Size'],
                       ($row['X_Lastchanged'] > 0) ? date(DATE_FMT_QUICK, $row['X_Lastchanged']) : '',
                       ($row['X_Joined'] == $cnt_players) ? 1 : 0 ));
      }
      mysql_free_result($result);
   }

   $expire_time[QST_CACHE_MPG] = $GLOBALS['NOW'] + $expire_min[QST_CACHE_MPG] * 60;
}//print_mpg


function write_quick_status_datastore( $path, $header )
{
   global $version, $datablocks, $expire_time, $expire_min, $crc32, $ARR_CACHEKEYS;

   $out = $header . "\n";
   foreach( $ARR_CACHEKEYS as $block )
   {
      // format: "BLOCK block version expire-time expire-min crc32" LF data "END" LF
      $out .= sprintf( "BLOCK %s %s %s %s %s\n%sEND\n",
         $block, $version, $expire_time[$block], $expire_min[$block], $crc32[$block],
         $datablocks[$block] );
   }
   $cnt = write_to_file( $path, $out, /*err-quit*/false );
   return $cnt;
}//write_quick_status_datastore

// returns true, if some block-cache has to be cleared
function parse_cache_content( $content )
{
   global $version, $datablocks, $load_data, $expire_time, $expire_min, $crc32, $NOW;

   $pcont = $content;
   while( preg_match("/^BLOCK (\S+) (\S+) (\S+) (\S+) (\S+)\n(.*?)END\n(.*)$/s", $pcont, $matches) )
   {
      // NOTE: $expire = expire-time, when reached -> reload data
      list( $tmp, $block, $data_version, $expire, $exp_min, $crc, $data, $rem_pcnt ) = $matches;

      $expire_time[$block] = $expire;
      $expire_min[$block] = $exp_min;
      $crc32[$block] = $crc;
      $pcont = $rem_pcnt;

      if( $version == $data_version && $expire > 0 && $NOW < $expire )
      {
         $datablocks[$block] = $data; // row-data
         $load_data[$block] = false; // take from cache
      }
   }

   // handle clear-cache commands, appended by write-operations on objects: B, M, G MPG (see QST_CACHE_...)
   $clear_cache = false;
   while( preg_match("/^CLEAR (\S+)\n+(.*?)$/s", $pcont, $matches) )
   {
      list( $tmp, $block, $rem_pcnt ) = $matches;
      $pcont = $rem_pcnt;
      $load_data[$block] = true; // clear cache for block
      $clear_cache = true;
   }
   return $clear_cache;
}//parse_cache_content

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
      header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*60)); // 5min
      header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));
   }
}//loc_start_page

function loc_end_page()
{
   ob_end_flush();
}

?>

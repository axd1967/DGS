<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

// translations remove for admin page: $TranslateGroups[] = "Admin";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/db/games.php';
require_once 'include/board.php';
require_once 'include/classlib_user.php';
require_once 'include/form_functions.php';

define('SEPLINE', "\n<p><hr>\n");

$GLOBALS['ThePage'] = new Page('Script', PAGEFLAG_IMPLICIT_FLUSH );


{
   connect2mysql();
   set_time_limit(0); // don't want script-break during "transaction" with multi-db-queries or for large-datasets

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scrips.fix_game_snapshot');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.fix_game_snapshot');
   if ( !(@$player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_GAME|ADMIN_DATABASE)) )
      error('adminlevel_too_low', 'scripts.fix_game_snapshot');

   $page = "fix_game_snapshot.php";

/* Actual REQUEST calls used
     fix_single&gid=                      : fix snapshot of single game
     fix_bulk&status=&uid=&limit=&sleep=  : fix snapshot of multiple-games
*/

   $gid = (int)get_request_arg('gid');
   if ( $gid <= 0 ) $gid = '';
   $sleep = (int)get_request_arg('sleep');
   if ( $sleep < 0 ) $sleep = 0;
   $limit = (int)get_request_arg('limit', 100);
   if ( $limit < 0 ) $limit = 0;


   $title = T_('Fix Games Snapshot');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   show_form();


   section( 'result', T_('Result') );

   if ( @$_REQUEST['fix_single'] ) // single-fix + show game-snapshot
      fix_single_game( $gid );
   elseif ( @$_REQUEST['fix_bulk'] ) // bulk-fix
   {
      $status = get_request_arg('status');
      $uid = (int)get_request_arg('uid');
      if ( $uid <= 0 ) $uid = 0;
      $startgid = (int)get_request_arg('startgid');
      if ( $startgid <= 0 ) $startgid = 0;
      bulk_fix_missing_game_snapshots( $status, $uid, $startgid, $limit, $sleep );
   }


   $menu_array = array( T_('Fix game snapshot') => "scripts/$page" );
   end_page(@$menu_array);
}//main


function show_form()
{
   global $page, $limit, $sleep, $gid;

   $gcform = new Form('gcform', $page, FORM_GET, true);
   $arr_status = array(
         ''  => T_('All games'),
         'F' => T_('Finished games only'),
         'R' => T_('Running games only'),
      );
   $gcform->add_row( array(
         'DESCRIPTION', T_('Game Status'),
         'SELECTBOX',   'status', 1, $arr_status, get_request_arg('status'), false,
      ));
   $gcform->add_row( array(
         'DESCRIPTION', T_('Player (uid)'),
         'TEXTINPUT',   'uid', 8, -1, get_request_arg('uid'),
      ));
   $gcform->add_row( array(
         'DESCRIPTION', T_('Start Game-ID'),
         'TEXTINPUT',   'startgid', 8, -1, get_request_arg('startgid'),
      ));
   $gcform->add_row( array(
         'DESCRIPTION', T_('Limit'),
         'TEXTINPUT',   'limit', 8, -1, $limit,
      ));
   $gcform->add_row( array(
         'DESCRIPTION', T_('Sleep (secs)'),
         'TEXTINPUT',   'sleep', 8, -1, $sleep,
      ));
   $gcform->add_row( array(
         'TAB', 'CELL', 1, '',
         'SUBMITBUTTON', 'fix_bulk', T_('Bulk-Fix Missing Game Snapshots'),
      ));

   $gcform->add_empty_row();
   $gcform->add_row( array( 'HR' ));

   $gcform->add_row( array(
         'DESCRIPTION', T_('Game ID'),
         'TEXTINPUT',   'gid', 10, 10, $gid,
         'SUBMITBUTTON', 'fix_single', T_('Fix Single Game Snapshot'),
      ));

   $gcform->echo_string(1);
}//show_form

function fix_single_game( $gid )
{
   if ( !$gid )
      return;

   // load game
   $game_row = Games::load_game( $gid, /*row*/true );
   if ( is_null($game_row) )
      error('unknown_game', "fix_game_snaphost.find_game($gid)");

   $game = Games::new_from_row($game_row);
   if ( $game->Status == GAME_STATUS_SETUP || $game->Status == GAME_STATUS_INVITED )
      error('invalid_game_status', "fix_game_snaphost.check.status($gid,{$game->Status})");

   $board_opts = ( GameHelper::game_need_mark_dead($game->Status) ) ? BOARDOPT_MARK_DEAD : 0;

   $board = new Board();
   if ( !$board->load_from_db( $game_row, $game->Moves, $board_opts) )
      error('internal_error', "fix_game_snapshost.load_from_db($gid)");
   $new_snapshot = GameSnapshot::make_game_snapshot( $board->size, $board );

   global $base_path;

   $linefmt = "<tr><td>%s</td><td><tt>%s</tt></td></tr>\n";
   echo "Game: ", anchor($base_path."game.php?gid=$gid", "#$gid"),
      "<br><br>\n",
      "<table>\n",
      sprintf( $linefmt, 'Old snapshot:', $game->Snapshot ),
      sprintf( $linefmt, 'New snapshot:', $new_snapshot ),
      "</table><br>\n\n";

   if ( $game->Snapshot != $new_snapshot )
   {
      db_query( "fix_game_snapshost.fix_single_game.upd_game_snapshot($gid)",
            "UPDATE Games SET Snapshot='$new_snapshot' WHERE ID=$gid LIMIT 1" );
      echo "<br><span class=ErrorMsg>Updated game-snapshot!!</span><br>\n\n";
   }
   else
      echo "<span class=ErrorMsg>No UPDATE required</span><br>\n\n";
}//fix_single_game

function bulk_fix_missing_game_snapshots( $status, $uid, $startgid, $limit, $sleep )
{
   global $ENUM_GAMES_STATUS;

   $qsql = new QuerySQL(
      SQLP_FIELDS, 'G.ID', 'G.Status', 'G.Size', 'G.Moves', 'G.ShapeSnapshot',
      SQLP_FROM,   'Games AS G',
      SQLP_WHERE,  "G.Snapshot=''", // games without snapshot
      SQLP_ORDER,  'G.ID ASC' );

   if ( $status == 'R' ) // running-games
      $qsql->add_part( SQLP_WHERE, "G.Status".IS_STARTED_GAME );
   elseif ( $status == 'F' ) // finished-games
      $qsql->add_part( SQLP_WHERE, "G.Status='".GAME_STATUS_FINISHED."'" );
   else
      $qsql->add_part( SQLP_WHERE, "G.Status ".not_in_clause( $ENUM_GAMES_STATUS, GAME_STATUS_SETUP, GAME_STATUS_INVITED ) );

   if ( $uid > 0 )
      $qsql->add_part( SQLP_WHERE, "(G.Black_ID=$uid OR G.White_ID=$uid)" );
   if ( $startgid > 0 )
      $qsql->add_part( SQLP_WHERE, "G.ID >= $startgid" );
   if ( $limit > 0 )
      $qsql->add_part( SQLP_LIMIT, $limit );

   $begin = getmicrotime();
   echo SEPLINE;
   echo "Fix Missing Games.Snapshot ...<br>\n";

   $result = db_query( "fix_game_snapshost.find_games($status,$uid,$limit,$sleep)", $qsql->get_select() );
   $count_rows = (int)@mysql_num_rows($result);
   echo sprintf( "<br>Found %d games to fix ...<br>\n", $count_rows );

   $cnt = $cnt_err = $lasterr_gid = 0;
   $error_gids = array();
   while ( $game_row = mysql_fetch_assoc($result) )
   {
      $cnt++;
      $gid = $game_row['ID'];
      $game_status = $game_row['Status'];

      $board_opts = (( GameHelper::game_need_mark_dead($game_status) ) ? BOARDOPT_MARK_DEAD : 0 ) | BOARDOPT_STOP_ON_FIX;

      $board = new Board();
      if ( $board->load_from_db( $game_row, $game_row['Moves'], $board_opts) )
      {
         $new_snapshot = GameSnapshot::make_game_snapshot( $board->size, $board );

         db_query( "fix_game_snapshost.bulkfix.upd_game_snapshot($gid)",
            "UPDATE Games SET Snapshot='$new_snapshot' WHERE ID=$gid LIMIT 1" );
         echo sprintf( "Game #%s -> fixed %d. of %d<br>\n", $gid, $cnt, $count_rows );

         if ( $sleep > 0 )
            sleep($sleep);
      }
      else
      {
         $error_gids[] = $gid;
         $cnt_err++;
         $lasterr_gid = $gid;
      }
   }
   mysql_free_result($result);

   if ( $cnt_err > 0 )
   {
      echo sprintf( "<br><span class=ErrorMsg>Found %d errors: last game-id with error = %d</span><br>\n",
                    $cnt_err, $lasterr_gid ),
         sprintf( "<br><span class=ErrorMsg>Game-IDs with errors: [ %s ]</span><br>\n", implode(' ', $error_gids));
   }

   echo "\n<br>Needed: " . sprintf("%1.3fs", (getmicrotime() - $begin))
      , " - Bulk-Fix Done.";
}//bulk_fix_missing_game_snapshots

?>

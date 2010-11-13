<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/std_classes.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'include/table_infos.php';
require_once 'include/game_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/rating.php';
require_once 'include/countries.php';

$GLOBALS['ThePage'] = new Page('GamePlayers');


define('CMD_WR_ADD', 'wr_add');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      error('not_logged_in');

   $my_id = $player_row['ID'];

   $gid = (int) get_request_arg('gid', 0);
   if( $gid <= 0 )
      error('unknown_game', "game_players.check.game($gid)");

/* Actual REQUEST calls used:
     gid=                      : show game-players-info
     cmd=wr_add&gid=&...       : show add-new-game-to-waiting-room
     cmd=wr_add&gid=&save...   : execute add new-game to waiting-room with args:
                                 slots, must_be_rated, rating1, rating2, min_rated_games, comment
*/
   $cmd = get_request_arg('cmd');

   //TODO handle GroupColor
   //TODO handle handle GroupOrder
   //TODO handle game-settings


   // load data
   $grow = load_game( $gid );
   $arr_game_players = load_game_players( $gid ); // -> $arr_users, $arr_free_slots
   $status = $grow['Status'];
   $cnt_free_slots = count($arr_free_slots);

   // waiting-room-game: edit allowed for game-master (user who started game)
   $allow_edit = ( $status == GAME_STATUS_SETUP ) && ( $grow['ToMove_ID'] == $my_id )
      && @$arr_users[$my_id] && ($arr_users[$my_id]->Flags & GPFLAG_MASTER);

   // ------------------------

   $form = null;

   if( $allow_edit && $cmd == CMD_WR_ADD && $cnt_free_slots > 0 ) // add to waiting-room
   {
      if( get_request_arg('save') )
      {
         add_waiting_room_mpgame( $grow, $my_id );
         set_request_arg('sysmsg', T_('Game added!') );
      }
      else
         $form = build_form_add_waiting_room( $gid, $cnt_free_slots, $cmd );
   }

   $utable = build_table_game_players( $arr_game_players );


   // ------------------------

   $title = T_('Game-Players information');
   start_page( $title, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title</h3>\n";

   $itable_game_settings = build_game_settings( $grow );
   echo $itable_game_settings->make_table(), "<br>\n";

   if( !is_null($utable) )
      $utable->echo_table();
   if( !is_null($form) )
      $form->echo_string();


   $menu_array = array();
   if( $status != GAME_STATUS_SETUP )
   {
      $menu_array[T_('Show game')] = "game.php?gid=$gid";
      $menu_array[T_('Show game info')] = "gameinfo.php?gid=$gid";
   }
   $menu_array[T_('Show game-players')] = "game_players.php?gid=$gid";
   if( $allow_edit && $status == GAME_STATUS_SETUP )
   {
      if( $cnt_free_slots )
         $menu_array[T_('Add to waiting room')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_WR_ADD;
   }

   end_page(@$menu_array);
}//main


function build_game_settings( $grow )
{
   $itable = new Table_info('game_settings', TABLEOPT_LABEL_COLON);
   $itable->add_sinfo(
         T_('Game settings'),
         sprintf( "%s, %d x %d, %s: %s, %s: %s",
               MultiPlayerGame::format_game_type($grow['GameType'], $grow['GamePlayers']),
               $grow['Size'], $grow['Size'],
               T_('Ruleset'), getRulesetText($grow['Ruleset']),
               T_('Rated game'), yesno($grow['Rated']) // normally never Rated
         ));
   //TODO show correct/calculated handicap-settings for current game-players with chosen handicap-type if possible -> may require manual-setting
   //TODO maybe always allow "dispute" setting manual handicap/komi
   $itable->add_sinfo(
         T_('Handicap settings'),
         sprintf( "%s: %s, %s: %s, %s: %s",
               T_('Komi'), $grow['Komi'],
               T_('Handicap'), $grow['Handicap'],
               T_('Standard Handicap'), yesno($grow['StdHandicap'])
         ));
   $itable->add_sinfo(
         T_('Time settings'),
         sprintf( "%s, %s\n",
               TimeFormat::echo_time_limit( $grow['Maintime'], $grow['Byotype'], $grow['Byotime'], $grow['Byoperiods'],
                     TIMEFMT_SHORT|TIMEFMT_HTMLSPC|TIMEFMT_ADDTYPE ),
               ( $grow['WeekendClock'] == 'Y' ? 'Clock running on weekend' : 'Clock stopped on weekend' )
         ));
   return $itable;
}//build_game_settings

function build_user_status( $gp )
{
   $arr = array();
   if( $gp->Flags & GPFLAG_MASTER )
      $arr[] = T_('Master#gpflag');
   if( $gp->Flags & GPFLAG_RESERVED )
      $arr[] = sprintf( '%s[%s]', T_('Reserved#gpflag'), ($gp->Flags & GPFLAG_WAITINGROOM ? 'WR' : 'INV') );
   elseif( $gp->Flags & GPFLAG_JOINED )
   {
      if( $gp->Flags & GPFLAG_WAITINGROOM )
         $arr[] = sprintf( '%s[%s]', T_('Joined#gpflag'), 'WR' );
      elseif( $gp->Flags & GPFLAG_INVITATION )
         $arr[] = sprintf( '%s[%s]', T_('Joined#gpflag'), 'INV' );
      else
         $arr[] = T_('Joined#gpflag');
   }
   if( $gp->uid > 0 )
   {
      $onVac = $gp->user->urow['OnVacation'];
      if( $onVac > 0 )
         $arr[] = echo_image_vacation( $onVac, TimeFormat::echo_onvacation($onVac) );
   }
   return implode(', ', $arr);
}//build_user_status

function load_game( $gid )
{
   $qsql = new QuerySQL(
      SQLP_FIELDS, 'G.*',
      SQLP_FROM, 'Games AS G',
      SQLP_WHERE, "G.ID=$gid" );
   $query = $qsql->get_select() . ' LIMIT 1';

   $grow = mysql_single_fetch( "game_players.find.game($gid)", $query );
   if( !$grow )
      error('unknown_game', "game_players.find.game2($gid)");
   $status = $grow['Status'];
   if( $status == GAME_STATUS_INVITED )
      error('invalid_game_status', "game_players.find.game3($gid,$status)");
   if( $grow['GameType'] != GAMETYPE_TEAM_GO && $grow['GameType'] != GAMETYPE_ZEN_GO )
      error('invalid_game_status', "game_players.find.std_gametype($gid,{$grow['GameType']})");

   return $grow;
}//load_game

// RETURN: [ GamePlayer, ... ]
// global OUTPUT: $cnt_free_slots, $arr_users[uid>0] = GamePlayer, $arr_free_slots[] = GamePlayer
function load_game_players( $gid )
{
   global $arr_users, $arr_free_slots;
   $arr_users = array();
   $arr_free_slots = array();

   $result = db_query( "game_players.find.game_players($gid)", //TODO optimize/cleanup
      "SELECT GP.*, " .
         "P.Name, P.Handle, P.Rating2, P.Country, P.OnVacation, " .
         "UNIX_TIMESTAMP(P.Lastaccess) AS X_Lastaccess " .
      "FROM GamePlayers AS GP LEFT JOIN Players AS P ON P.ID=GP.uid " .
      "WHERE gid=$gid ORDER BY GroupColor ASC, GroupOrder ASC" );

   $arr_gp = array();
   while( $row = mysql_fetch_assoc($result) )
   {
      $uid = (int)$row['uid'];
      if( $uid > 0 )
      {
         $user = new User( $uid, $row['Name'], $row['Handle'], 0, $row['X_Lastaccess'],
            $row['Country'], $row['Rating2'] );
         $user->urow = array( 'OnVacation' => $row['OnVacation'] );
      }
      else
         $user = null;

      $gp = GamePlayer::build_game_player( $row['ID'], $row['gid'], $row['GroupColor'],
         $row['GroupOrder'], $row['Flags'], $uid, $user );
      $arr_gp[] = $gp;
      if( $uid > 0 )
         $arr_users[$uid] = $gp;
      if( ($gp->Flags & GPFLAG_SLOT_TAKEN) == 0 )
         $arr_free_slots[] = $gp;
   }
   mysql_free_result($result);

   return $arr_gp;
}//load_game_players

function build_table_game_players( $arr_gp )
{
   global $base_path;

   $utable = new Table( 'gameplayers', 'game_players.php' );
   $utable->use_show_rows( false );
   $utable->add_tablehead( 1, '#', 'Number' );
   $utable->add_tablehead( 2, T_('Color#header'), 'Image' );
   $utable->add_tablehead( 3, T_('Player#header'), 'User' );
   $utable->add_tablehead( 5, T_('Country#header'), 'Image' );
   $utable->add_tablehead( 4, T_('Rating#header'), 'Rating' );
   $utable->add_tablehead( 6, T_('Last access#header'), 'Date' );
   $utable->add_tablehead( 7, T_('Status#header'), 'ImagesLeft' );

   $idx = 0;
   foreach( $arr_gp as $gp )
   {
      $idx++;
      $row_str = array(
         1 => $gp->GroupOrder . '.',
         2 => GamePlayer::build_image_group_color( $gp->GroupColor ),
      );
      if( $gp->uid )
      {
         $row_str[3] = $gp->user->user_reference();
         $row_str[4] = echo_rating( $gp->user->Rating, true, $gp->uid );
         $row_str[5] = getCountryFlagImage( $gp->user->Country );
         $row_str[6] = ( $gp->user->Lastaccess > 0 ? date(DATE_FMT2, $gp->user->Lastaccess) : '' );
      }
      else
         $row_str[3] = NO_VALUE;
      $row_str[7] = build_user_status( $gp );

      $utable->add_row( $row_str );
   }

   return $utable;
}//build_table_game_players

function build_form_add_waiting_room( $gid, $slot_count, $cmd )
{
   $form = new Form( 'addgame', 'game_players.php', FORM_POST );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', $cmd );
   $form->add_row( array( 'SPACE' ) );
   $form->add_row( array( 'HEADER', T_('Add new game to waiting room') ) );

   // how many slots to use: 1..N
   $arr_slots = build_num_range_map( 1, $slot_count );
   $form->add_row( array( 'DESCRIPTION', T_('Player-slots'),
                          'SELECTBOX', 'slots', 1, $arr_slots, $slot_count, false, ));

   append_form_add_waiting_room_game( $form, GSETVIEW_MPGAME );

   $form->add_row( array( 'SPACE' ) );
   $form->add_row( array( 'TAB', 'CELL', 1, '',
                          'SUBMITBUTTON', 'save', T_('Add Game') ));

   return $form;
}//build_form_add_waiting_room

function add_waiting_room_mpgame( $grow, $uid )
{
   global $NOW, $cnt_free_slots, $arr_free_slots;
   $gid = (int)$grow['ID'];

   $slots = limit( (int)get_request_arg('slots', 1), 1, $cnt_free_slots, 1 );
   list( $must_be_rated, $rating1, $rating2 ) = parse_waiting_room_rating_range();
   $min_rated_games = limit( (int)get_request_arg('min_rated_games', 0), 0, 10000, 0 );
   $comment = trim( get_request_arg('comment') );

   $query_wroom = "INSERT INTO Waitingroom SET " .
      "uid=$uid, " .
      "gid=$gid, " .
      "nrGames=$slots, " .
      "Time=FROM_UNIXTIME($NOW), " .
      "GameType='" . mysql_addslashes($grow['GameType']) . "', " .
      "GamePlayers='" . mysql_addslashes($grow['GamePlayers']) . "', " .
      "Ruleset='" . mysql_addslashes($grow['Ruleset']) . "', " .
      "Size={$grow['Size']}, " .
      "Handicaptype='" . mysql_addslashes(HTYPE_NIGIRI) . "', " .
      "Maintime={$grow['Maintime']}, " .
      "Byotype='{$grow['Byotype']}', " .
      "Byotime={$grow['Byotime']}, " .
      "Byoperiods={$grow['Byoperiods']}, " .
      "WeekendClock='{$grow['WeekendClock']}', " .
      "Rated='N', " .
      "StdHandicap='{$grow['StdHandicap']}', " .
      "MustBeRated='$must_be_rated', " .
      "Ratingmin=$rating1, " .
      "Ratingmax=$rating2, " .
      "MinRatedGames=$min_rated_games, " .
      "SameOpponent=-1, " . // same-opponent can only join once
      "Comment=\"" . mysql_addslashes($comment) . "\"";

   // update free slots: set RESERVED + WR; change flags in table
   $arr_upd = array();
   for( $i=0; $i < $slots; $i++ )
   {
      $arr_free_slots[$i]->Flags |= GPFLAG_RESERVED | GPFLAG_WAITINGROOM;
      $arr_upd[] = $arr_free_slots[$i]->id;
   }

   ta_begin();
   {//HOT-section for creating waiting-room game
      db_query( "game_players.add_waiting_room_mpgame.insert_wroom($gid,$slots)", $query_wroom );
      db_query( "game_players.add_waiting_room_mpgame.gp_upd_flags($gid,$slots)",
         "UPDATE GamePlayers SET Flags = Flags | ".(GPFLAG_RESERVED | GPFLAG_WAITINGROOM)." " .
         "WHERE ID IN (" . implode(',', $arr_upd) . ") AND (Flags & ".GPFLAG_SLOT_TAKEN.") = 0 " .
         "LIMIT $slots" );
   }
   ta_end();
}//add_waiting_room_mpgame

?>

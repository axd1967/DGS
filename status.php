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

$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/rating.php" );
require_once( 'include/table_infos.php' );
require_once( "include/table_columns.php" );
require_once( "include/game_functions.php" );
require_once( "include/message_functions.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_game.php' );
require_once( 'include/gui_bulletin.php' );

$GLOBALS['ThePage'] = new Page('Status');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $page = 'status.php';

   if( get_request_arg('set_order') )
   {
      $value = get_request_arg('stg_order', 1);
      $next_game_order = NextGameOrder::get_next_game_order( $value );
      if( !$next_game_order )
         error('invalid_args', "status.check.stg_order($value)");
      if( $player_row['NextGameOrder'] != $next_game_order )
      {
         db_query( "status.update.next_game_order($uid,$value)",
            "UPDATE Players SET NextGameOrder='".mysql_addslashes($next_game_order) .
            "' WHERE ID=$my_id LIMIT 1" );
      }
      jump_to($page);
   }

   // mark bulletin as read + reload (for recount remaining bulletins)
   $markread = (int)get_request_arg('mr');
   if( $markread > 0 )
   {
      Bulletin::mark_bulletin_as_read( $markread );
      jump_to($page);
   }

   $cfg_pages = ConfigPages::load_config_pages( $my_id, CFGCOLS_STATUS_GAMES );
   $cfg_tblcols = $cfg_pages->get_table_columns();


   // NOTE: game-list can't allow TABLE-SORT until jump_to_next_game() adjusted to follow the sort;
   //       ordering is implemented using explicit "Set Order" saved in Players.NextGameOrder !!
   $table_mode= TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE; //|TABLE_NO_HIDE
   $gtable = new Table( 'game', $page, $cfg_tblcols, '', $table_mode|TABLE_ROWS_NAVI );

   start_page(T_('Status'), true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   section( 'Status',
      sprintf( T_('Status for %1$s: %2$s'),
         user_reference( 0, 1, '', $player_row ),
         echo_rating( @$player_row["Rating2"], true, $my_id )
      ));

{ // show user infos
   if( $player_row['OnVacation'] > 0 )
   {
      $itable= new Table_info('user');

      $onVacationText = TimeFormat::echo_onvacation($player_row['OnVacation']);
      $itable->add_sinfo(
            anchor( "edit_vacation.php", T_('Vacation days left') ),
            TimeFormat::echo_day( floor($player_row["VacationDays"])) );
      $itable->add_sinfo(
            anchor( "edit_vacation.php", T_('On vacation') )
               . MINI_SPACING . echo_image_vacation($player_row['OnVacation'], $onVacationText, true),
            $onVacationText,
            '', 'class=OnVacation' );

      $itable->echo_table();
      unset($itable);
   }
} // show user infos


if( @$player_row['CountBulletinNew'] > 0 )
{ // show unread bulletins
   $limit = 3;
   $iterator = new ListIterator( 'status.list_bulletin.unread',
      new QuerySQL( SQLP_WHERE,
            "BR.bid IS NULL", // only unread
            "B.Status='".BULLETIN_STATUS_SHOW."'" ),
      'ORDER BY B.PublishTime DESC',
      'LIMIT '.($limit+1) );
   $iterator->addQuerySQLMerge( Bulletin::build_view_query_sql( /*adm*/false, /*count*/false ) );
   $iterator = Bulletin::load_bulletins( $iterator );

   if( $iterator->ResultRows > 0 )
   {
      section('bulletin', T_('Message of the Day (unread bulletins)'));

      $show_rows = $limit;
      while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $bulletin, $orow ) = $arr_item;
         $mark_as_read_url = ( !@$orow['BR_Read'] && $bulletin->Status == BULLETIN_STATUS_SHOW ) ? $page : '';
         echo GuiBulletin::build_view_bulletin($bulletin, $mark_as_read_url);
      }

      if( $iterator->ResultRows > $limit )
      {
         echo "<font size=\"+1\">...</font>\n";
         $sectmenu = array();
         $sectmenu[ sprintf( T_('Show all (%s) unread bulletins'), $player_row['CountBulletinNew'] ) ] =
            "list_bulletins.php?text=1".URI_AMP."view=1".URI_AMP."no_adm=1";
         make_menu($sectmenu, false);
      }
   }
} // unread bulletins


$folder_nr_querystr = $cfg_pages->get_status_folders_querypart();
if( (string)$folder_nr_querystr != '' )
{ // show messages

   // NOTE: msg-list can't allow TABLE-SORT, because of the fixed LIMIT and no prev/next feature
   $mtable = new Table( 'message', $page, '', 'MSG', $table_mode|TABLE_NO_HIDE );

   //$mtable->add_or_del_column();
   $msglist_builder = new MessageListBuilder( $mtable, FOLDER_NONE /*FOLDER_ALL_RECEIVED*/,
      /*no_sort=no_mark*/true, /*full*/true );
   $msglist_builder->message_list_head();
   //no_sort must stay true because of the fixed LIMIT and no prev/next feature

   $mtable->set_default_sort( 4); //on 'date' (to display the sort-image)
   $mtable->use_show_rows(false);
   $order = $mtable->current_order_string(/*'date+'*/);
   $limit = ' LIMIT 20'; //$mtable->current_limit_string();

   list( $result ) = message_list_query($my_id, $folder_nr_querystr, $order, $limit);
   if( @mysql_num_rows($result) > 0 )
   {
      init_standard_folders();
      $my_folders = get_folders($my_id);

      section( 'Message', T_('New messages'));

      $msglist_builder->message_list_body( $result, 20, $my_folders) ; // also frees $result
      $mtable->echo_table();
   }
   else
      mysql_free_result($result);
   //unset($mtable);
} // show messages



{ // show games
   $uid = $my_id;

   $gtable->add_or_del_column();

   // NOTE: check after add_or_del_column()-call
   // only activate if column shown for user to reduce server-load for page
   // avoiding additional outer-join on GamesNotes-table !!
   $show_notes = (LIST_GAMENOTE_LEN>0);
   $load_notes = ($show_notes && $gtable->is_column_displayed(12) );

   $show_prio = ($player_row['NextGameOrder'] == NGO_PRIO);
   $load_prio = ($show_prio || $gtable->is_column_displayed(17) );

   // NOTE: mostly but not always same col-IDs used as in show_games-page (except: 10, 11, 12, 15) + <=30(!)
   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $gtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $gtable->add_tablehead(15, new TableHead( T_('Game information'), 'images/info.gif'), 'ImagesLeft', 0 );
   $gtable->add_tablehead( 2, T_('sgf#header'), 'Sgf', TABLE_NO_SORT );
   if( $show_notes )
      $gtable->add_tablehead(12, T_('Notes#header'), '', 0, 'X_Note-');
   $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'Name+');
   $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'Handle+');
   $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'Rating2-');
   $gtable->add_tablehead( 5, T_('Color#header'), 'Image', 0, 'X_Color+');
   $gtable->add_tablehead(19, T_('GameType#header'), '', 0, 'GameType+');
   $gtable->add_tablehead(18, T_('Ruleset#header'), '', 0, 'Ruleset-');
   $gtable->add_tablehead( 6, T_('Size#header'), 'Number', 0, 'Size-');
   $gtable->add_tablehead( 7, T_('Handicap#header'), 'Number', 0, 'Handicap+');
   $gtable->add_tablehead( 8, T_('Komi#header'), 'Number', 0, 'Komi-');
   $gtable->add_tablehead( 9, T_('Moves#header'), 'Number', 0, 'Moves-');
   $gtable->add_tablehead(14, T_('Rated#header'), '', 0, 'X_Rated-');
   $gtable->add_tablehead(11, new TableHead( T_('User online#header'),
      'images/online.gif', sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS) ), 'Image', 0 );
   $gtable->add_tablehead(13, T_('Last move#header'), 'Date', 0, 'Lastchanged+');
   $gtable->add_tablehead(17, T_('Priority#header'), 'Number',
      ($show_prio ? TABLE_NO_HIDE : 0), 'X_Priority-');
   $gtable->add_tablehead(10, T_('Time remaining#header'), null, 0, 'TimeOutDate+');

   // static order for status-games (coupled with "next game" on game-page)
   if( $player_row['NextGameOrder'] == NGO_LASTMOVED )
      $gtable->set_default_sort( 13, 1); //on Lastchanged,ID
   elseif( $player_row['NextGameOrder'] == NGO_MOVES )
      $gtable->set_default_sort( 9, 13); //on Moves,Lastchanged
   elseif( $player_row['NextGameOrder'] == NGO_PRIO )
      $gtable->set_default_sort( 17, 13); //on GamesPriority.Priority,Lastchanged
   elseif( $player_row['NextGameOrder'] == NGO_TIMELEFT )
      $gtable->set_default_sort( 10, 13); //on TimeRemaining,Lastchanged
   //$order = $gtable->current_order_string('ID-');
   $gtable->make_sort_images();
   $order = NextGameOrder::get_next_game_order( $player_row['NextGameOrder'], 'Games' ); // enum -> order
   $gtable->use_show_rows(false);


   $query = "SELECT Games.*, UNIX_TIMESTAMP(Games.Lastchanged) AS Time"
      .",IF(Rated='N','N','Y') AS X_Rated"
      .",opponent.Name,opponent.Handle,opponent.Rating2 AS Rating,opponent.ID AS pid"
      .",UNIX_TIMESTAMP(opponent.Lastaccess) AS X_OppLastaccess"
      //extra bits of X_Color are for sorting purposes
      //b0= White to play, b1= I am White, b4= not my turn, b5= bad or no ToMove info
      .",IF(ToMove_ID=$uid,0,0x10)+IF(White_ID=$uid,2,0)+IF(White_ID=ToMove_ID,1,IF(Black_ID=ToMove_ID,0,0x20)) AS X_Color"
      .",COALESCE(Clock.Ticks,0) AS X_Ticks" //always my clock because always my turn (status page)
      .(!$load_notes ? '': ",GN.Notes AS X_Note" )
      .($load_prio ? ",COALESCE(GP.Priority,0) AS X_Priority" : ",0 AS X_Priority")
      ." FROM (Games,Players AS opponent)"
      .(!$load_notes ? '': " LEFT JOIN GamesNotes AS GN ON GN.gid=Games.ID AND GN.uid=$uid" )
      .(!$load_prio ? '': " LEFT JOIN GamesPriority AS GP ON GP.gid=Games.ID AND GP.uid=$uid" )
      ." LEFT JOIN Clock ON Clock.ID=Games.ClockUsed"
      ." WHERE ToMove_ID=$uid AND Status".IS_RUNNING_GAME
      ." AND opponent.ID=(Black_ID+White_ID-$uid)"
      . $order;


   if( $DEBUG_SQL ) echo "QUERY-GAMES: " . make_html_safe($query) ."<br>\n";

   $result = db_query( "status.find_games($uid)", $query );

   section( 'Games', T_('Your turn to move in the following games:'));

   if( @mysql_num_rows($result) == 0 )
   {
      echo T_('No games found');
   }
   else
   {
      $gtable->set_extend_table_form_function( 'status_games_extend_table_form' ); //defined below

      $cnt_rows = 0;
      while( $row = mysql_fetch_assoc( $result ) )
      {
         $cnt_rows++;
         $Rating=NULL;
         extract($row);

         $grow_strings = array();
         //if( $gtable->Is_Column_Displayed[0] )
            $grow_strings[ 1] = button_TD_anchor( "game.php?gid=$ID", $ID);
         if( $gtable->Is_Column_Displayed[2] )
            $grow_strings[ 2] = "<A href=\"sgf.php?gid=$ID\">" . T_('sgf') . "</A>";
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[ 3] = "<A href=\"userinfo.php?uid=$pid\">" .
               make_html_safe($Name) . "</a>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[ 4] = "<A href=\"userinfo.php?uid=$pid\">$Handle</a>";
         if( $load_notes && $gtable->Is_Column_Displayed[12] )
         {
            // keep the first line up to LIST_GAMENOTE_LEN chars
            $grow_strings[12] = make_html_safe( strip_gamenotes($X_Note) );
         }
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = echo_rating($Rating,true,$pid);
         if( $gtable->Is_Column_Displayed[5] )
         {
            $colors = ( $X_Color & 2 ) ? 'w' : 'b'; //my color
            $grow_strings[ 5] = "<img src=\"17/$colors.gif\" alt=\"$colors\">";
         }
         if( $gtable->Is_Column_Displayed[6] )
            $grow_strings[ 6] = $Size;
         if( $gtable->Is_Column_Displayed[7] )
            $grow_strings[ 7] = $Handicap;
         if( $gtable->Is_Column_Displayed[8] )
            $grow_strings[ 8] = $Komi;
         if( $gtable->Is_Column_Displayed[9] )
            $grow_strings[ 9] = $Moves;
         if( $gtable->Is_Column_Displayed[14] )
            $grow_strings[14] = ($X_Rated == 'N' ? T_('No') : T_('Yes') );
         if( $gtable->Is_Column_Displayed[13] )
            $grow_strings[13] = date(DATE_FMT, $Time);
         if( $gtable->Is_Column_Displayed[10] )
         {
            $my_col = ( $X_Color & 2 ) ? WHITE : BLACK;
            $grow_strings[10] = build_time_remaining( $row, $my_col, /*is_to_move*/true );
         }
         if( $gtable->Is_Column_Displayed[11] )
         {
            $is_online = ($NOW - @$X_OppLastaccess) < SPAN_ONLINE_MINS * 60; // online up to X mins ago
            $grow_strings[11] = echo_image_online( $is_online, @$X_OppLastaccess, false );
         }
         if( $gtable->Is_Column_Displayed[15] )
         {
            $snapshot = ($Snapshot) ? $Snapshot : null;
            $grow_strings[15] = echo_image_gameinfo($ID, /*sep*/false, $Size, $snapshot)
               . echo_image_tournament_info($tid, true);
         }
         if( $gtable->Is_Column_Displayed[17] )
            $grow_strings[17] = ($X_Priority) ? $X_Priority : ''; // don't show 0
         if( $gtable->Is_Column_Displayed[18] )
            $grow_strings[18] = getRulesetText($Ruleset);
         if( $gtable->Is_Column_Displayed[19] )
            $grow_strings[19] = GameTexts::format_game_type( $GameType, $GamePlayers )
               . ($GameType == GAMETYPE_GO ? '' : MINI_SPACING . echo_image_game_players( $ID ) );

         $gtable->add_row( $grow_strings );
      }
      $gtable->set_found_rows( $cnt_rows );
      $gtable->echo_table();
   }
   mysql_free_result($result);
   //unset($gtable);
}// status-games



if( $player_row['GamesMPG'] > 0 )
{ // show multi-player-games
   $mpgtable = new Table( 'mpgame', $page, null, '', $table_mode|TABLE_ROWS_NAVI );

   $mpgtable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $mpgtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $mpgtable->add_tablehead( 2, T_('GameType#header'), '', TABLE_NO_HIDE, 'GameType+');
   $mpgtable->add_tablehead( 6, T_('Joined#header'), 'NumberC', 0, 'Moves+');
   $mpgtable->add_tablehead( 3, T_('Ruleset#header'), '', 0, 'Ruleset-');
   $mpgtable->add_tablehead( 4, T_('Size#header'), 'Number', 0, 'Size-');
   $mpgtable->add_tablehead( 5, T_('Last change#header'), 'Date', 0, 'Lastchanged-');

   $mpgtable->set_default_sort( 5/*, 1*/); //on Lastchanged,ID
   $order = $mpgtable->current_order_string('ID-');
   $mpgtable->use_show_rows(false);

   $query = "SELECT G.ID, G.GameType, G.GamePlayers, G.Ruleset, G.Size, G.Moves AS X_Joined, GP.Flags, "
      . "UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged "
      . "FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid "
      . "WHERE GP.uid=$uid AND G.Status='SETUP'"
      . $order;

   if( $DEBUG_SQL ) echo "QUERY-MP-GAMES: " . make_html_safe($query) ."<br>\n";
   $result = db_query( "status.find_mp_games($uid)", $query );
   if( @mysql_num_rows($result) > 0 )
   {
      section( 'MPGames', T_('Your multi-player-games to manage:'));

      $cnt_rows = 0;
      while( $row = mysql_fetch_assoc( $result ) )
      {
         $cnt_rows++;

         $cnt_players = MultiPlayerGame::determine_player_count($row['GamePlayers']);
         $joined_players = sprintf( '%d / %d', $row['X_Joined'], $cnt_players );
         if( $row['X_Joined'] == $cnt_players )
            $joined_players = span('MPGWarning', $joined_players);

         $row_arr = array(
            1 => button_TD_anchor( "game_players.php?gid=".$row['ID'], $row['ID'] ),
            2 => GameTexts::format_game_type( $row['GameType'], $row['GamePlayers'] ),
            3 => getRulesetText($row['Ruleset']),
            4 => $row['Size'],
            5 => ($row['X_Lastchanged'] > 0) ? date(DATE_FMT, $row['X_Lastchanged']) : '',
            6 => $joined_players,
         );
         $mpgtable->add_row( $row_arr );
      }
      $mpgtable->set_found_rows( $cnt_rows );
      $mpgtable->echo_table();
   }
   mysql_free_result($result);
   unset($mpgtable);
}// multi-player-games



{ // show pending posts
   if( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      section( 'Pending', '');
      require_once('forum/forum_functions.php'); // NOTE: always included, but only executed here !!
      display_posts_pending_approval();
   }
} // show pending posts



   $menu_array = array(
         T_('My running games') => "show_games.php?uid=$my_id",
         T_('My finished games') => "show_games.php?uid=$my_id".URI_AMP."finished=1",
         T_('Games I\'m observing') => "show_games.php?observe=$my_id",
      );
   if( ALLOW_TOURNAMENTS )
      $menu_array[T_('My tournaments')] = "tournaments/list_tournaments.php?uid=$my_id";

   end_page(@$menu_array);
}


// callback-func for games-status Table-form adding form-elements below table
function status_games_extend_table_form( &$gtable, &$form )
{
   global $player_row;

   $result = $form->print_insert_select_box(
         'stg_order', '1', NextGameOrder::get_next_game_orders_selection(),
         NextGameOrder::get_next_game_order( $player_row['NextGameOrder'] ), // enum -> idx
         false);
   $result .= $form->print_insert_submit_button( 'set_order', T_('Set Order') );
   return $result;
}

?>

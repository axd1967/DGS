<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/table_infos.php';
require_once 'include/table_columns.php';
require_once 'include/game_functions.php';
require_once 'include/message_functions.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/gui_bulletin.php';

$GLOBALS['ThePage'] = new Page('Status');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'status');
   $my_id = $uid = $player_row['ID'];

   $page = 'status.php';

   if ( get_request_arg('set_order') ) // change NextGameOrder for user
   {
      $value = get_request_arg('stg_order', 1);
      $next_game_order = NextGameOrder::get_next_game_order( $value );
      if ( !$next_game_order )
         error('invalid_args', "status.check.stg_order($value)");
      if ( $player_row['NextGameOrder'] != $next_game_order )
      {
         db_query( "status.update.next_game_order($uid,$value)",
            "UPDATE Players SET NextGameOrder='".mysql_addslashes($next_game_order) .
            "' WHERE ID=$my_id LIMIT 1" );

         clear_cache_quick_status( $my_id, QST_CACHE_GAMES );
         GameHelper::delete_cache_status_games( "status.update.next_game_order", $my_id );
      }
      jump_to($page);
   }

   // mark bulletin as read + reload (for recount of remaining bulletins)
   $markread = (int)get_request_arg('mr');
   if ( $markread > 0 )
   {
      Bulletin::mark_bulletin_as_read( $markread );
      jump_to($page);
   }

   $cfg_pages = ConfigPages::load_config_pages( $my_id, CFGCOLS_STATUS_GAMES );
   if ( !$cfg_pages )
      error('user_init_error', 'status.init.config_pages');
   $cfg_tblcols = $cfg_pages->get_table_columns();


   // NOTE: game-list can't allow TABLE-SORT until jump_to_next_game() adjusted to follow the sort;
   //       ordering is implemented using explicit "Set Order" saved in Players.NextGameOrder !!
   $table_mode= TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE; //|TABLE_NO_HIDE
   $gtable = new Table( 'game', $page, $cfg_tblcols, '', $table_mode|TABLE_ROWS_NAVI );
   $cnt_game_rows = load_games_to_move( $my_id, $gtable );

   $title = sprintf( '%s (%s)', T_('Status'), $cnt_game_rows );
   start_page( $title, true, $logged_in, $player_row, button_style($player_row['Button']) );

   section( 'Status',
      sprintf( T_('Status for %1$s: %2$s'),
         user_reference( 0, 1, '', $player_row ),
         echo_rating( @$player_row["Rating2"], true, $my_id )
      ));

{ // show user infos
   if ( $player_row['OnVacation'] > 0 )
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


if ( @$player_row['CountBulletinNew'] > 0 )
{ // show unread bulletins
   $limit = 3;
   $arr_bulletins = Bulletin::load_cache_bulletins( 'status', $my_id );

   $cnt_bulletins = count($arr_bulletins);
   if ( $cnt_bulletins > 0 )
   {
      section('bulletin', T_('Message of the Day (unread bulletins)'));

      $show_rows = $limit;
      foreach ( $arr_bulletins as $bulletin )
      {
         if ( $show_rows-- <= 0 )
            break;
         $mark_as_read_url = ( !$bulletin->ReadState && $bulletin->Status == BULLETIN_STATUS_SHOW ) ? $page : '';
         echo GuiBulletin::build_view_bulletin($bulletin, $mark_as_read_url);
      }

      if ( $cnt_bulletins > $limit )
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
if ( (string)$folder_nr_querystr != '' )
{ // show messages

   // NOTE: msg-list can't allow TABLE-SORT, because of the fixed LIMIT and no prev/next feature
   $mtable = new Table( 'message', $page, '', 'MSG', $table_mode|TABLE_NO_HIDE );

   //$mtable->add_or_del_column();
   $msglist_builder = new MessageListBuilder( $mtable, FOLDER_NONE /*FOLDER_ALL_RECEIVED*/,
      /*no_sort=no_mark*/true, /*full*/true ); //no_sort must=true because of the fixed LIMIT and no prev/next-paging
   $msglist_builder->message_list_head();

   $mtable->set_default_sort( 4); //on 'date' (to display the sort-image)
   $mtable->use_show_rows(false);
   $order = $mtable->current_order_string();
   $limit = 20;

   list( $arr_msg, $num_rows, $found_rows ) =
      MessageListBuilder::load_cache_message_list('status', $my_id, $folder_nr_querystr, $order, $limit + 1);
   $cnt_msg = count($arr_msg);
   if ( $cnt_msg > 0 )
   {
      init_standard_folders();
      $my_folders = get_folders($my_id);

      section( 'Message', T_('New messages'));

      $msglist_builder->message_list_body( $arr_msg, $limit, $my_folders) ; // also frees $result
      $mtable->echo_table();

      if ( $cnt_msg > $limit )
      {
         echo "<font size=\"+1\">...</font>\n";
         make_menu( array( T_('Show all messages') => 'list_messages.php' ), false );
      }
   }
} // show messages



{ // show games
   section( 'Games', T_('Your turn to move in the following games:'));
   if ( $cnt_game_rows > 0 )
      $gtable->echo_table();
   else
      echo T_('No games found');
}// status-games



if ( $player_row['GamesMPG'] > 0 )
{ // show multi-player-games
   $mpgtable = new Table( 'mpgame', $page, null, '', $table_mode|TABLE_ROWS_NAVI );

   $mpgtable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $mpgtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $mpgtable->add_tablehead( 2, T_('GameType#header'), '', TABLE_NO_HIDE, 'GameType+');
   $mpgtable->add_tablehead( 6, T_('Joined#header'), 'NumberC', 0, 'Moves+');
   $mpgtable->add_tablehead( 7, T_('Game master#header'), 'User', 0, 'ToMove_ID+');
   $mpgtable->add_tablehead( 3, T_('Ruleset#header'), '', 0, 'Ruleset-');
   $mpgtable->add_tablehead( 4, T_('Size#header'), 'Number', 0, 'Size-');
   $mpgtable->add_tablehead( 5, T_('Last changed#header'), 'Date', 0, 'Lastchanged-');

   $mpgtable->set_default_sort( 5/*, 1*/); //on Lastchanged,ID
   $order = $mpgtable->current_order_string('ID-');
   $mpgtable->use_show_rows(false);

   $query = "SELECT G.ID, G.GameType, G.GamePlayers, G.ToMove_ID, G.Ruleset, G.Size, G.Moves AS X_Joined, GP.Flags, "
      . "UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged, "
      . "GM.ID AS GM_ID, GM.Handle AS GM_Handle, GM.Name AS GM_Name " // game-master
      . "FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid INNER JOIN Players AS GM ON GM.ID=G.ToMove_ID "
      . "WHERE GP.uid=$uid AND G.Status='".GAME_STATUS_SETUP."'"
      . $order;

   $result = db_query( "status.find_mp_games($uid)", $query );
   if ( @mysql_num_rows($result) > 0 )
   {
      section( 'MPGames', T_('Your multi-player-games to manage:'));

      $cnt_rows = 0;
      while ( $row = mysql_fetch_assoc( $result ) )
      {
         $cnt_rows++;
         $game_master = User::new_from_row( $row, 'GM_' );

         $cnt_players = MultiPlayerGame::determine_player_count($row['GamePlayers']);
         $joined_players = sprintf( '%d / %d', $row['X_Joined'], $cnt_players );
         if ( $row['X_Joined'] == $cnt_players )
            $joined_players = span('MPGWarning', $joined_players);

         $row_arr = array(
            1 => button_TD_anchor( "game_players.php?gid=".$row['ID'], $row['ID'] ),
            2 => GameTexts::format_game_type( $row['GameType'], $row['GamePlayers'] ),
            3 => Ruleset::getRulesetText($row['Ruleset']),
            4 => $row['Size'],
            5 => ($row['X_Lastchanged'] > 0) ? date(DATE_FMT, $row['X_Lastchanged']) : '',
            6 => $joined_players,
            7 => $game_master->user_reference(),
         );
         $mpgtable->add_row( $row_arr );
      }
      $mpgtable->set_found_rows( $cnt_rows );
      $mpgtable->echo_table();
   }
   mysql_free_result($result);
}// multi-player-games



{ // show pending posts
   if ( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      section( 'Pending', '');
      require_once 'forum/forum_functions.php'; // NOTE: always included, but only executed here !!
      display_posts_pending_approval();
   }
} // show pending posts



   $menu_array = array(
         T_('My running games') => "show_games.php?uid=$my_id",
         T_('My finished games') => "show_games.php?uid=$my_id".URI_AMP."finished=1",
         T_('Games I\'m observing') => "show_games.php?observe=$my_id",
      );
   if ( ALLOW_TOURNAMENTS )
      $menu_array[T_('My tournaments')] = "tournaments/list_tournaments.php?uid=$my_id";

   end_page(@$menu_array);
}//main



function load_games_to_move( $uid, &$gtable )
{
   global $player_row, $NOW, $base_path;

   $next_game_order = $player_row['NextGameOrder'];
   $gtable->add_or_del_column();

   // NOTE: check after add_or_del_column()-call
   // only activate if column shown for user to reduce server-load for page
   // avoiding additional outer-join on GamesNotes-table !!
   $show_notes = (LIST_GAMENOTE_LEN>0);
   $load_notes = ($show_notes && $gtable->is_column_displayed(12) );

   $show_prio = ($next_game_order == NGO_PRIO);
   $load_prio = ($show_prio || $gtable->is_column_displayed(17) );

   // NOTE: mostly but not always same col-IDs used as in show_games-page (except: 10, 11, 12, 15) + <=30(!)
   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $gtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $gtable->add_tablehead(15, new TableHeadImage( T_('Game information'), 'images/info.gif'), 'ImagesLeft', 0 );
   $gtable->add_tablehead( 2, T_('sgf#header'), 'Sgf', TABLE_NO_SORT );
   if ( $show_notes )
      $gtable->add_tablehead(12, T_('Notes#header'), '', 0, 'X_Note-');
   $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'opp_Name+');
   $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'opp_Handle+');
   $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'opp_Rating-');
   $gtable->add_tablehead( 5, T_('Color#header'), 'Image', 0, 'X_Color+');
   $gtable->add_tablehead(19, T_('GameType#header'), '', 0, 'GameType+');
   $gtable->add_tablehead(18, T_('Ruleset#header'), '', 0, 'Ruleset-');
   $gtable->add_tablehead( 6, T_('Size#header'), 'Number', 0, 'Size-');
   $gtable->add_tablehead( 7, new TableHead( T_('Handicap#header'), T_('Handicap')), 'Number', 0, 'Handicap+');
   $gtable->add_tablehead( 8, T_('Komi#header'), 'Number', 0, 'Komi-');
   $gtable->add_tablehead( 9, new TableHead( T_('Moves#header'), T_('Moves')), 'Number', 0, 'Moves-');
   $gtable->add_tablehead(14, T_('Rated#header'), '', 0, 'X_Rated-');
   $gtable->add_tablehead(11, new TableHeadImage( T_('User online#header'), 'images/online.gif',
      sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS)
         . ', ' . T_('or on vacation#header') ), 'Image', 0 );
   if ( $next_game_order == NGO_LASTMOVED_NEW_FIRST )
      $gtable->add_tablehead(13, T_('Last move#header'), 'Date', 0, 'Lastchanged-');
   else
      $gtable->add_tablehead(13, T_('Last move#header'), 'Date', 0, 'Lastchanged+');
   $gtable->add_tablehead(17, T_('Priority#header'), 'Number',
      ($show_prio ? TABLE_NO_HIDE : 0), 'X_Priority-');
   $gtable->add_tablehead(10, T_('Time remaining#header'), null, 0, 'TimeOutDate+');

   // static order for status-games (coupled with "next game" on game-page); for table-sort-indicators
   if ( $next_game_order == NGO_LASTMOVED_OLD_FIRST || $next_game_order == NGO_LASTMOVED_NEW_FIRST )
      $gtable->set_default_sort( 13, 1); //on Lastchanged,ID
   elseif ( $next_game_order == NGO_MOVES )
      $gtable->set_default_sort( 9, 13); //on Moves,Lastchanged
   elseif ( $next_game_order == NGO_PRIO )
      $gtable->set_default_sort( 17, 13); //on GamesPriority.Priority,Lastchanged
   elseif ( $next_game_order == NGO_TIMELEFT )
      $gtable->set_default_sort( 10, 13); //on TimeRemaining,Lastchanged
   $gtable->make_sort_images();
   $gtable->use_show_rows(false);

   $game_rows = GameHelper::load_cache_status_games( 'status', $next_game_order, 'X_Lastaccess', $load_prio, $load_notes );

   $cnt_rows = count($game_rows);
   if ( $cnt_rows > 0 )
   {
      $arr_titles_colors = get_color_titles();
      $gtable->set_extend_table_form_function( 'status_games_extend_table_form' ); //func

      $timefmt = TIMEFMT_ADDTYPE | TIMEFMT_SHORT | TIMEFMT_ZERO;
      foreach ( $game_rows as $row )
      {
         $Rating = NULL;
         extract($row);

         $row_arr = array();
         //if ( $gtable->Is_Column_Displayed[0] )
            $row_arr[ 1] = button_TD_anchor( "game.php?gid=$ID", $ID);
         if ( $gtable->Is_Column_Displayed[2] )
            $row_arr[ 2] = "<A href=\"sgf.php?gid=$ID\">" . T_('sgf') . "</A>";
         if ( $gtable->Is_Column_Displayed[3] )
            $row_arr[ 3] = "<A href=\"userinfo.php?uid=$opp_ID\">" .
               make_html_safe($opp_Name) . "</a>";
         if ( $gtable->Is_Column_Displayed[4] )
            $row_arr[ 4] = "<A href=\"userinfo.php?uid=$opp_ID\">$opp_Handle</a>";
         if ( $load_notes && $gtable->Is_Column_Displayed[12] )
         {
            // keep the first line up to LIST_GAMENOTE_LEN chars
            $row_arr[12] = make_html_safe( strip_gamenotes($X_Note) );
         }
         if ( $gtable->Is_Column_Displayed[16] )
            $row_arr[16] = echo_rating($opp_Rating, true, $opp_ID);
         if ( $gtable->Is_Column_Displayed[5] )
         {
            if ( $Status == GAME_STATUS_KOMI )
            {
               $colors = 'y';
               $hover_title = sprintf( T_('Fair Komi Negotiation#fairkomi'), $player_row['Handle'] );
            }
            else
            {
               $colors = ( $X_Color & 2 ) ? 'w' : 'b'; //my color
               $hover_title = @$arr_titles_colors[$colors];
            }
            $row_arr[ 5] = image( $base_path."17/$colors.gif", $colors, $hover_title );
         }
         if ( $gtable->Is_Column_Displayed[6] )
            $row_arr[ 6] = $Size;
         if ( $gtable->Is_Column_Displayed[7] )
            $row_arr[ 7] = $Handicap;
         if ( $gtable->Is_Column_Displayed[8] )
            $row_arr[ 8] = $Komi;
         if ( $gtable->Is_Column_Displayed[9] )
            $row_arr[ 9] = $Moves;
         if ( $gtable->Is_Column_Displayed[14] )
            $row_arr[14] = ($X_Rated == 'N' ? T_('No') : T_('Yes') );
         if ( $gtable->Is_Column_Displayed[13] )
            $row_arr[13] = date(DATE_FMT, $X_Lastchanged);
         if ( $gtable->Is_Column_Displayed[10] )
         {
            $my_col = ( $X_Color & 2 ) ? WHITE : BLACK;
            $row_arr[10] = build_time_remaining( $row, $my_col, /*is_to_move*/true, $timefmt );
         }
         if ( $gtable->Is_Column_Displayed[11] )
            $row_arr[11] = echo_user_online_vacation( @$opp_OnVacation, @$opp_Lastaccess );
         if ( $gtable->Is_Column_Displayed[15] )
         {
            $snapshot = ($Snapshot) ? $Snapshot : null;
            $row_arr[15] = echo_image_gameinfo($ID, /*sep*/false, $Size, $snapshot, $Last_X, $Last_Y)
               . echo_image_shapeinfo( $ShapeID, $Size, $ShapeSnapshot, false, true)
               . echo_image_tournament_info($tid, @$T_Title, true);
         }
         if ( $gtable->Is_Column_Displayed[17] )
            $row_arr[17] = ($X_Priority) ? $X_Priority : ''; // don't show 0
         if ( $gtable->Is_Column_Displayed[18] )
            $row_arr[18] = Ruleset::getRulesetText($Ruleset);
         if ( $gtable->Is_Column_Displayed[19] )
            $row_arr[19] = GameTexts::format_game_type( $GameType, $GamePlayers )
               . ($GameType == GAMETYPE_GO ? '' : MINI_SPACING . echo_image_game_players( $ID ) )
               . GameTexts::build_fairkomi_gametype($Status);

         $gtable->add_row( $row_arr );
      }
      $gtable->set_found_rows( $cnt_rows );
   }

   return $cnt_rows;
}//load_games_to_move

// callback-func for games-status Table-form adding form-elements below table
function status_games_extend_table_form( &$table, &$form )
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

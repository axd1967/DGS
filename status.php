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

$TranslateGroups[] = "Common";

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/rating.php" );
require_once( 'include/table_infos.php' );
require_once( "include/table_columns.php" );
require_once( "include/message_functions.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_game.php' );

$GLOBALS['ThePage'] = new Page('Status');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   if( get_request_arg('set_order') )
   {
      $value = get_request_arg('stg_order', 1);
      $next_game_order = NextGameOrder::get_next_game_order( '', $value, false );
      if( $player_row['NextGameOrder'] != $next_game_order )
      {
         db_query( "status.update.next_game_order($uid,$value)",
            "UPDATE Players SET NextGameOrder='".mysql_addslashes($next_game_order) .
            "' WHERE ID=$my_id LIMIT 1" );
      }
      jump_to('status.php');
   }

   $cfg_pages = ConfigPages::load_config_pages( $my_id, CFGCOLS_STATUS_GAMES );
   $cfg_tblcols = $cfg_pages->get_table_columns();


   // NOTE: game-list can't allow TABLE-SORT until jump_to_next_game() adjusted to follow the sort;
   //       ordering is implemented using explicit "Set Order" saved in Players.NextGameOrder !!
   $table_mode= TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE; //|TABLE_NO_HIDE
   $gtable = new Table( 'game', "status.php", $cfg_tblcols, '', $table_mode|TABLE_ROWS_NAVI );

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


$folder_nr_querystr = $cfg_pages->get_status_folders_querypart();
if( (string)$folder_nr_querystr != '' )
{ // show messages

   // NOTE: msg-list can't allow TABLE-SORT, because of the fixed LIMIT and no prev/next feature
   $mtable = new Table( 'message', 'status.php', '', 'MSG', $table_mode|TABLE_NO_HIDE );

   //$mtable->add_or_del_column();
   message_list_head( $mtable, FOLDER_NONE /*FOLDER_ALL_RECEIVED*/
         , /*no_sort=*/true, /*no_mark=*/true ) ;
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

      message_list_body( $mtable, $result, 20, $my_folders) ; // also frees $result

      $mtable->echo_table();
   }
   else
      mysql_free_result($result);
   //unset($mtable);
} // show messages


{ // show games
   $uid = $my_id;
   $ginfo_str = T_('Game information');

   $gtable->add_or_del_column();

   // NOTE: check after add_or_del_column()-call
   // only activate if column shown for user to reduce server-load for page
   // avoiding additional outer-join on GamesNotes-table !!
   $show_notes = (LIST_GAMENOTE_LEN>0);
   $load_notes = ($show_notes && $gtable->is_column_displayed(12) );

   $show_prio = ($player_row['NextGameOrder'] == 'PRIO');
   $load_prio = ($show_prio || $gtable->is_column_displayed(17) );

   // NOTE: mostly but not always same col-IDs used as in show_games-page (except: 10, 11, 12, 15) + <=30(!)
   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $gtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $gtable->add_tablehead(15, new TableHead( $ginfo_str, 'images/info.gif', $ginfo_str), 'ImagesLeft', 0 );
   $gtable->add_tablehead( 2, T_('sgf#header'), 'Sgf', TABLE_NO_SORT );
   if( $show_notes )
      $gtable->add_tablehead(12, T_('Notes#header'), '', 0, 'X_Note-');
   $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'Name+');
   $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'Handle+');
   $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'Rating2-');
   $gtable->add_tablehead( 5, T_('Color#header'), 'Image', 0, 'X_Color+');
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
   if( $player_row['NextGameOrder'] == 'LASTMOVED' )
      $gtable->set_default_sort( 13, 1); //on Lastchanged,ID
   elseif( $player_row['NextGameOrder'] == 'MOVES' )
      $gtable->set_default_sort( 9, 13); //on Moves,Lastchanged
   elseif( $player_row['NextGameOrder'] == 'PRIO' )
      $gtable->set_default_sort( 17, 13); //on GamesPriority.Priority,Lastchanged
   elseif( $player_row['NextGameOrder'] == 'TIMELEFT' )
      $gtable->set_default_sort( 10, 13); //on TimeRemaining,Lastchanged
   //$order = $gtable->current_order_string('ID-');
   $gtable->make_sort_images();
   $order = ' ORDER BY ' .
      NextGameOrder::get_next_game_order( 'Games', $player_row['NextGameOrder'], true ); // enum -> order

   $limit = ''; //$gtable->current_limit_string();
   $gtable->use_show_rows(false);


   $query = "SELECT Games.*, UNIX_TIMESTAMP(Games.Lastchanged) AS Time"
      .",IF(Rated='N','N','Y') AS X_Rated"
      .",opponent.Name,opponent.Handle,opponent.Rating2 AS Rating,opponent.ID AS pid"
      .",UNIX_TIMESTAMP(opponent.Lastaccess) AS X_OppLastaccess"
      //extra bits of X_Color are for sorting purposes
      //b0= White to play, b1= I am White, b4= not my turn, b5= bad or no ToMove info
      .",IF(ToMove_ID=$uid,0,0x10)+IF(White_ID=$uid,2,0)+IF(White_ID=ToMove_ID,1,IF(Black_ID=ToMove_ID,0,0x20)) AS X_Color"
      .",Clock.Ticks" //always my clock because always my turn (status page)
      .(!$load_notes ? '': ",GN.Notes AS X_Note" )
      .($load_prio ? ",COALESCE(GP.Priority,0) AS X_Priority" : ",0 AS X_Priority")
      ." FROM (Games,Players AS opponent)"
      .(!$load_notes ? '': " LEFT JOIN GamesNotes AS GN ON GN.gid=Games.ID AND GN.uid=$uid" )
      .(!$load_prio ? '': " LEFT JOIN GamesPriority AS GP ON GP.gid=Games.ID AND GP.uid=$uid" )
      ." LEFT JOIN Clock ON Clock.ID=Games.ClockUsed"
      ." WHERE ToMove_ID=$uid AND Status".IS_RUNNING_GAME
      ." AND opponent.ID=(Black_ID+White_ID-$uid)"
      ."$order$limit";

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

      $gtable->set_found_rows( mysql_found_rows("status.find.my_games($my_id)") );
      while( $row = mysql_fetch_assoc( $result ) )
      {
         $Rating=NULL;
         $Ticks=0;
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
            if( $X_Color & 2 )
            {
               $my_Maintime   = $White_Maintime;
               $my_Byotime    = $White_Byotime;
               $my_Byoperiods = $White_Byoperiods;
            }
            else
            {
               $my_Maintime   = $Black_Maintime;
               $my_Byotime    = $Black_Byotime;
               $my_Byoperiods = $Black_Byoperiods;
            }
            //if( !(($Color+1) & 2) ) //is it my turn? (always set in status page)
            $hours = ticks_to_hours($Ticks - $LastTicks);

            time_remaining($hours, $my_Maintime, $my_Byotime, $my_Byoperiods,
                           $Maintime, $Byotype, $Byotime, $Byoperiods, false);
            $hours_remtime = time_remaining_value( $Byotype, $Byotime, $Byoperiods,
                  $my_Maintime, $my_Byotime, $my_Byoperiods );
            $class_remtime = get_time_remaining_warning_class( $hours_remtime );

            $content = TimeFormat::echo_time_remaining( $my_Maintime, $Byotype,
                  $my_Byotime, $my_Byoperiods, $Byotime, $Byoperiods,
                  TIMEFMT_ADDTYPE | TIMEFMT_ABBEXTRA | TIMEFMT_ZERO );
            $grow_strings[10] = array(
                  'attbs' => array( 'class' => $class_remtime ),
                  'text'  => $content,
               );
         }
         if( $gtable->Is_Column_Displayed[11] )
         {
            $is_online = ($NOW - @$X_OppLastaccess) < SPAN_ONLINE_MINS * 60; // online up to X mins ago
            $grow_strings[11] = echo_image_online( $is_online, @$X_OppLastaccess, false );
         }
         if( $gtable->Is_Column_Displayed[15] )
            $grow_strings[15] = echo_image_gameinfo($ID) . echo_image_tournament_info($tid, true);
         if( $gtable->Is_Column_Displayed[17] )
            $grow_strings[17] = ($X_Priority) ? $X_Priority : ''; // don't show 0

         $gtable->add_row( $grow_strings );
      }

      $gtable->echo_table();
   }
   mysql_free_result($result);
   //unset($gtable);
}


{ // show pending posts
   if( (@$player_row['admin_level'] & ADMIN_FORUM) )
   {
      section( 'Pending', '');
      require_once('forum/forum_functions.php'); // NOTE: always included, but only executed here !!
      display_posts_pending_approval();
   }
} // show pending posts



   $menu_array = array(
         T_('My user info') => "userinfo.php?uid=$my_id",
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
         NextGameOrder::get_next_game_order( '', $player_row['NextGameOrder'], false ), // enum -> idx
         false);
   $result .= $form->print_insert_submit_button( 'set_order', T_('Set Order') );
   return $result;
}

?>

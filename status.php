<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );

$ThePage = new Page('Status');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   $cfg_pages = ConfigPages::load_config_pages( $my_id, CFGCOLS_STATUS_GAMES );
   $cfg_tblcols = $cfg_pages->get_table_columns();

   //check if the player's clock need an adjustment from/to summertime
   if( $player_row['ClockChanged'] != 'Y' &&
      $player_row['ClockUsed'] != get_clock_used($player_row['Nightstart']) )
   {
      // ClockUsed is updated once a day...
      db_query( 0 /* "status.summertime($my_id)" */,
         "UPDATE Players SET ClockChanged='Y' WHERE ID=$my_id LIMIT 1" );
   }


   // NOTE: game-list can't allow TABLE-SORT until jump_to_next_game() adjusted to follow the sort
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

      $onVacationText = echo_onvacation($player_row['OnVacation']);
      $itable->add_sinfo(
            anchor( "edit_vacation.php", T_('Vacation days left') ),
            echo_day( floor($player_row["VacationDays"])) );
      $itable->add_sinfo(
            anchor( "edit_vacation.php", T_('On vacation') )
               . MINI_SPACING . echo_image_vacation($player_row['OnVacation'], $onVacationText, true),
            $onVacationText,
            '', 'class=OnVacation' );

      $itable->echo_table();
      unset($itable);
   }
} // show user infos


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

   $status_folders = $cfg_pages->get_status_folders();
   $folderstring = $status_folders . (empty($status_folders) ? '' : ',')
      . FOLDER_NEW . ',' . FOLDER_REPLY;

   list( $result ) = message_list_query($my_id, $folderstring, $order, $limit);
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

   // NOTE: mostly but not always same col-IDs used as in show_games-page (except: 10, 11, 12, 15) + <=30(!)
   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $gtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $gtable->add_tablehead(15, new TableHead( $ginfo_str, 'images/info.gif', $ginfo_str), 'Image', 0 );
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
   $gtable->add_tablehead(10, T_('Time remaining#header'), null, TABLE_NO_SORT);

   $gtable->set_default_sort( 13/*, 1*/); //on Lastchanged,ID
   $order = $gtable->current_order_string('ID-');
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
      ." FROM (Games,Players AS opponent)"
      .(!$load_notes ? '': " LEFT JOIN GamesNotes AS GN ON GN.gid=Games.ID AND GN.uid=$uid" )
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
            $grow_strings[ 4] = "<A href=\"userinfo.php?uid=$pid\">" .
               $Handle . "</a>";
         if( $load_notes && $gtable->Is_Column_Displayed[12] )
         { //keep the first line up to LIST_GAMENOTE_LEN chars
            $X_Note= trim( substr(
               preg_replace("/[\\x00-\\x1f].*\$/s",'',$X_Note)
               , 0, LIST_GAMENOTE_LEN) );
            $grow_strings[12] = make_html_safe($X_Note);
         }
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = echo_rating($Rating,true,$pid);
         if( $gtable->Is_Column_Displayed[5] )
         {
            if( $X_Color & 2 ) //my color
               $colors = 'w';
            else
               $colors = 'b';
      /*
            if( !($X_Color & 0x20) )
            {
               if( $X_Color & 1 ) //to move color
                  $colors.= '_w';
               else
                  $colors.= '_b';
            }
      */
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
               $my_Maintime = $White_Maintime;
               $my_Byotime = $White_Byotime;
               $my_Byoperiods = $White_Byoperiods;
            }
            else
            {
               $my_Maintime = $Black_Maintime;
               $my_Byotime = $Black_Byotime;
               $my_Byoperiods = $Black_Byoperiods;
            }
            //if( !(($Color+1) & 2) ) //is it my turn? (always set in status page)
            $hours = ticks_to_hours($Ticks - $LastTicks);

            time_remaining($hours, $my_Maintime, $my_Byotime, $my_Byoperiods,
                           $Maintime, $Byotype, $Byotime, $Byoperiods, false);
            $hours_remtime = time_remaining_value( $Byotype, $Byotime, $Byoperiods,
                  $my_Maintime, $my_Byotime, $my_Byoperiods );
            $class_remtime = get_time_remaining_warning_class( $hours_remtime );

            $content = echo_time_remaining( $my_Maintime, $Byotype,
                  $my_Byotime, $my_Byoperiods, $Byotime, false, true, true);
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
            $grow_strings[15] = echo_image_gameinfo($ID);

         $gtable->add_row( $grow_strings );
      }

      $gtable->echo_table();
   }
   mysql_free_result($result);
   //unset($gtable);
}


if( ALLOW_TOURNAMENTS )
{ // show tournament applications
   $ta_cfg_pages = ConfigPages::load_config_pages( $my_id, CFGCOLS_STATUS_TOURNAMENTS );
   $tatable = new Table( 'tournament', "status.php", $ta_cfg_pages->get_table_columns(), '', $table_mode );
   $tatable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $tatable->add_tablehead( 1, T_('Tournament ID#headert'), 'Button', TABLE_NO_HIDE, 'ID-');
   $tatable->add_tablehead( 8, '', 'Image', TABLE_NO_HIDE|TABLE_NO_SORT); // tourney-registration
   $tatable->add_tablehead( 2, T_('Scope#headert'), 'Enum', 0, 'Scope+');
   $tatable->add_tablehead( 3, T_('Type#headert'), 'Enum', 0, 'Type+');
   $tatable->add_tablehead( 4, T_('Status#headert'), 'Enum', 0, 'Status+');
   $tatable->add_tablehead( 5, T_('Title#headert'), '', 0, 'Title+');
   $tatable->add_tablehead( 6, T_('Registration Status#headert'), 'Enum', TABLE_NO_HIDE, 'Title+');
   $tatable->add_tablehead( 7, T_('Updated#T_reg'), 'Date', 0, 'TP.Lastchanged-');

   $tatable->set_default_sort( 1 ); //on ID
   $tatable->use_show_rows(false);

   $ta_qsql = $tatable->get_query();
   $ta_qsql->add_part( SQLP_FIELDS,
         'TP.Status AS TP_Status',
         'UNIX_TIMESTAMP(TP.Lastchanged) AS TP_Lastchanged' );
   $ta_qsql->add_part( SQLP_FROM,
         'INNER JOIN TournamentParticipant AS TP ON TP.tid=T.ID' );
   $ta_qsql->add_part( SQLP_WHERE,
         "TP.uid='" . mysql_addslashes($my_id) . "'",
         "TP.Status<>'".TP_STATUS_REGISTER."'" );

   // build SQL-query (for tournaments)
   $iterator = new ListIterator( 'TournamentApplications',
         $ta_qsql,
         $tatable->current_order_string('ID-'),
         '' // no limit
         );
   $iterator = Tournament::load_tournaments( $iterator );

   if( $DEBUG_SQL ) echo "QUERY-TOURNEYS: " . make_html_safe($iterator->Query) ."<br>\n";

   if( $iterator->ResultRows > 0 )
   {
      section( 'TournamentApplications', T_('Your open tournament applications:'));

      $show_rows = $tatable->compute_show_rows( $iterator->ResultRows );
      while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tourney, $orow ) = $arr_item;
         $ID = $tourney->ID;
         $row_str = array();

         if( $tatable->Is_Column_Displayed[ 1] )
            $row_str[ 1] = button_TD_anchor( "tournaments/view_tournament.php?tid=$ID", $ID );
         if( $tatable->Is_Column_Displayed[ 2] )
            $row_str[ 2] = Tournament::getScopeText( $tourney->Scope );
         if( $tatable->Is_Column_Displayed[ 3] )
            $row_str[ 3] = Tournament::getTypeText( $tourney->Type );
         if( $tatable->Is_Column_Displayed[ 4] )
            $row_str[ 4] = Tournament::getStatusText( $tourney->Status );
         if( $tatable->Is_Column_Displayed[ 5] )
            $row_str[ 5] = make_html_safe( $tourney->Title );
         if( $tatable->Is_Column_Displayed[ 6] )
            $row_str[ 6] = TournamentParticipant::getStatusText($orow['TP_Status']);
         if( $tatable->Is_Column_Displayed[ 7] )
            $row_str[ 7] = (@$orow['TP_Lastchanged'] > 0) ? date(DATE_FMT2, $orow['TP_Lastchanged']) : '';
         if( $tatable->Is_Column_Displayed[ 8] )
         {
            $row_str[ 8] = anchor( "tournaments/register.php?tid=$ID",
               image( $base_path.'images/info.gif',
                  sprintf( T_('My registration for tournament %s'), $ID ), null,
                  'class=InTextImage'));
         }

         $tatable->add_row( $row_str );
      }

      $tatable->echo_table();
   }
} // show tournament applications


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
      $menu_array[T_('My tournaments')] = "tournaments/list_tournaments.php?user=".urlencode($player_row['Handle']);

   end_page(@$menu_array);

}
?>

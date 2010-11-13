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


// commands / use-cases
define('CMD_ADD_WAITINGROOM_GAME', 'wr_add'); // add game in waiting-room
define('CMD_CHANGE_COLOR', 'chg_col'); // set group-color
define('CMD_CHANGE_ORDER', 'chg_ord'); // set group-order

define('KEY_GROUP_COLOR', 'gpc');
define('KEY_GROUP_ORDER', 'gpo');

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

     cmd=chg_col&gid=&...      : show change-group-color settings
     cmd=chg_col&gid=&save...  : update GroupColor on change-group with args: gpc<ID>

     cmd=chg_ord&gid=&...      : show change-group-order settings
     cmd=chg_ord&gid=&save...  : update GroupOrder on change-group with args: gpo<ID>
*/
   $cmd = get_request_arg('cmd');


   // load data
   $grow = load_game( $gid );
   $arr_game_players = load_game_players( $gid ); // -> $arr_users, $arr_free_slots
   $status = $grow['Status'];
   $cnt_free_slots = count($arr_free_slots);

   // waiting-room-game: edit allowed for game-master (user who started game)
   $allow_edit = ( $status == GAME_STATUS_SETUP ) && ( $grow['ToMove_ID'] == $my_id )
      && @$arr_users[$my_id] && ($arr_users[$my_id]->Flags & GPFLAG_MASTER);

   // ------------------------

   $form = $extform = null;

   if( $allow_edit && $cmd == CMD_ADD_WAITINGROOM_GAME && $cnt_free_slots > 0 ) // add to waiting-room
   {
      if( get_request_arg('save') )
         add_waiting_room_mpgame( $grow, $my_id );
      else
         $form = build_form_add_waiting_room( $gid, $cnt_free_slots, $cmd );
   }
   elseif( $allow_edit && $cmd == CMD_CHANGE_COLOR ) // change groups (color)
   {
      if( get_request_arg('save') )
         change_group_color($gid);
      else
         $extform = build_form_change_group( $gid, $cmd, 'changegroupcolor' );
   }
   elseif( $allow_edit && $cmd == CMD_CHANGE_ORDER ) // change groups (order)
   {
      if( get_request_arg('save') )
         change_group_order($gid, $grow['GameType']);
      else
         $extform = build_form_change_group( $gid, $cmd, 'changegrouporder' );
   }

   $arr_ratings = calc_group_ratings();
   $utable = build_table_game_players( $grow, $arr_game_players, $cmd, $extform );


   // ------------------------

   $title = T_('Game-Players information');
   start_page( $title, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title</h3>\n";

   $itable_game_settings = build_game_settings( $grow );
   echo $itable_game_settings->make_table(), "<br>\n";

   // use extform | form
   if( !is_null($extform) )
      echo $extform->print_start_default();
   if( !is_null($utable) )
      $utable->echo_table();
   if( !is_null($extform) )
      echo $extform->get_form_string(), $extform->print_end();
   elseif( !is_null($form) )
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
         $menu_array[T_('Add to waiting room')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_ADD_WAITINGROOM_GAME;
      $menu_array[T_('Change color')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_CHANGE_COLOR;
      $menu_array[T_('Change order')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_CHANGE_ORDER;
   }

   end_page(@$menu_array);
}//main


function build_game_settings( $grow )
{
   global $arr_ratings;

   $itable = new Table_info('game_settings', TABLEOPT_LABEL_COLON);
   $itable->add_sinfo(
         T_('Game settings'),
         sprintf( "%s, %d x %d, %s: %s, %s: %s",
               MultiPlayerGame::format_game_type($grow['GameType'], $grow['GamePlayers']),
               $grow['Size'], $grow['Size'],
               T_('Ruleset'), getRulesetText($grow['Ruleset']),
               T_('Rated game'), yesno($grow['Rated']) // normally never Rated
         ));
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

   if( count($arr_ratings) )
   {
      $arr = array();
      build_group_rating( $arr, GPCOL_B, GPCOL_W );
      build_group_rating( $arr, GPCOL_G1, GPCOL_G2 );
      $itable->add_sinfo(
            T_('Group ratings'), implode("<br>\n", $arr) );
   }
   return $itable;
}//build_game_settings

function build_group_rating( &$arr, $grcol1, $grcol2 )
{
   global $arr_ratings;
   if( isset($arr_ratings[$grcol1]) || isset($arr_ratings[$grcol2]) )
   {
      $buf = '';
      foreach( array( $grcol1, $grcol2 ) as $gr_col )
      {
         $buf .= sprintf( "<b>%s:</b> %s",
               GamePlayer::get_group_color_text($gr_col),
               ( isset($arr_ratings[$gr_col]) ) ? echo_rating( $arr_ratings[$gr_col] ) : NO_VALUE );
         if( $gr_col == $grcol1 )
            $buf .= ', ';
      }
      $arr[] = $buf;
   }
}//build_group_rating

function build_user_flags( $gp )
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
   return implode(', ', $arr);
}//build_user_flags

function build_user_status( $gp )
{
   $arr = array();
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
         "P.Name, P.Handle, P.Rating2, P.RatingStatus, P.Country, P.OnVacation, " .
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
            $row['Country'], $row['Rating2'], $row['RatingStatus'] );
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

// return: $arr_ratings[$group_color] = average-rating
function calc_group_ratings()
{
   global $arr_game_players;

   $calc_ratings = array();
   foreach( $arr_game_players as $gp )
   {
      if( !is_null($gp->user) && $gp->user->hasRating() )
         $calc_ratings[$gp->GroupColor][] = $gp->user->Rating;
   }

   // calc average rating for groups B/W
   $arr_ratings = array();
   foreach( $calc_ratings as $gr_col => $arr )
   {
      $cnt = count($arr);
      if( $gr_col != GPCOL_BW && $cnt )
         $arr_ratings[$gr_col] = array_sum($arr) / $cnt;
   }

   return $arr_ratings;
}//calc_group_ratings

function build_table_game_players( $grow, $arr_gp, $cmd, &$form )
{
   global $base_path;
   $chg_group_color = ($cmd == CMD_CHANGE_COLOR) && $form;
   $chg_group_order = ($cmd == CMD_CHANGE_ORDER) && $form;
   $arr_group_colors = ($chg_group_color) ? GamePlayer::get_group_color_text() : null;

   $utable = new Table( 'GamePlayers', 'game_players.php' );
   $utable->use_show_rows( false );
   if( $chg_group_order )
      $utable->add_tablehead(10, T_('Set Order#header'), '', TABLE_NO_HIDE, '');
   $utable->add_tablehead( 1, '#', 'Number' );
   if( $chg_group_color )
      $utable->add_tablehead( 9, T_('Set Group#header'), '', TABLE_NO_HIDE, '');
   $utable->add_tablehead( 2, T_('Color#header'), 'Image' );
   $utable->add_tablehead( 3, T_('Player#header'), 'User' );
   $utable->add_tablehead( 5, T_('Country#header'), 'Image' );
   $utable->add_tablehead( 4, T_('Rating#header'), 'Rating' );
   $utable->add_tablehead( 6, T_('Last access#header'), 'Date' );
   $utable->add_tablehead( 7, T_('Flags#header'), 'ImagesLeft' );
   $utable->add_tablehead( 8, T_('Status#header'), 'ImagesLeft' );

   $group_max_players = MultiPlayerGame::determine_groups_player_count( $grow['GamePlayers'] );

   $idx = 0;
   $last_group = null;
   foreach( $arr_gp as $gp )
   {
      $idx++;
      if( !is_null($last_group) && $gp->GroupColor != $last_group )
      {//add some separator without chaning row-col for next content-row
         $utable->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
         $utable->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
      }

      $row_str = array(
         1 => ($gp->GroupOrder > 0 ) ? $gp->GroupOrder . '.' : NO_VALUE,
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
      $row_str[7] = build_user_flags( $gp );
      $row_str[8] = build_user_status( $gp );

      if( $chg_group_color && $gp->uid > GUESTS_ID_MAX ) // command: set-group-color
      {
         $gpkey = KEY_GROUP_COLOR.$gp->id;
         $row_str[9] = $form->print_insert_select_box( $gpkey, 1, $arr_group_colors,
            get_request_arg($gpkey, $gp->GroupColor) );
      }
      if( $chg_group_order && $gp->uid > GUESTS_ID_MAX ) // command: set-group-order
      {
         if( allow_change_group_order($grow['GameType'], $gp->GroupColor) )
         {
            $gpkey = KEY_GROUP_ORDER.$gp->id;
            $arr_group_order = array( 0 => NO_VALUE ) + build_num_range_map( 1, $group_max_players );
            $row_str[10] = $form->print_insert_select_box( $gpkey, 1, $arr_group_order,
               get_request_arg($gpkey, $gp->GroupOrder) );
         }
      }

      $utable->add_row( $row_str );
      $last_group = $gp->GroupColor;
   }

   return $utable;
}//build_table_game_players

function allow_change_group_order( $game_type, $group_color )
{
   return ( $game_type == GAMETYPE_TEAM_GO && $group_color != GPCOL_BW )
       || ( $game_type == GAMETYPE_ZEN_GO  && $group_color == GPCOL_BW );
}

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

function build_form_change_group( $gid, $cmd, $formname )
{
   $form = new Form( $formname, 'game_players.php', FORM_GET, false );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', $cmd );

   $form->add_row( array( 'SPACE' ) );
   $form->add_row( array( 'TAB', 'CELL', 1, '',
                          'SUBMITBUTTON', 'save', T_('Update#submit') ));
   return $form;
}//build_form_change_group

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

   set_request_arg('sysmsg', T_('Game added!') );
}//add_waiting_room_mpgame

function change_group_color( $gid )
{
   global $arr_game_players;

   // if changed, update group-color in database + table
   $cnt_upd = 0;
   foreach( $arr_game_players as $gp )
   {
      $new_grcol = get_request_arg(KEY_GROUP_COLOR.$gp->id);
      if( $gp->uid > GUESTS_ID_MAX && $new_grcol != $gp->GroupColor )
      {
         db_query( "game_players.change_group_color.gp_upd($gid)",
            "UPDATE GamePlayers SET GroupColor='" . mysql_addslashes($new_grcol) . "' " .
            "WHERE ID={$gp->id} AND uid>0 LIMIT 1" );
         $gp->setGroupColor($new_grcol);
         $cnt_upd++;
      }
   }

   if( $cnt_upd > 0 )
      set_request_arg('sysmsg', T_('Groups updated!') );
}//change_group_color

function change_group_order( $gid, $gametype )
{
   global $arr_game_players;

   // if changed, update group-order in database + table
   $cnt_upd = 0;
   foreach( $arr_game_players as $gp )
   {
      $new_order = (int)get_request_arg(KEY_GROUP_ORDER.$gp->id);
      if( $gp->uid > GUESTS_ID_MAX && $new_order != $gp->GroupOrder
            && allow_change_group_order($gametype, $gp->GroupColor) )
      {
         db_query( "game_players.change_group_order.gp_upd($gid)",
            "UPDATE GamePlayers SET GroupOrder='" . mysql_addslashes($new_order) . "' " .
            "WHERE ID={$gp->id} AND uid>0 LIMIT 1" );
         $gp->groupOrder = $new_order;
         $cnt_upd++;
      }
   }

   if( $cnt_upd > 0 ) // reload to get new order
      jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=" . urlencode(T_('Groups updated!')) );
}//change_group_order

?>

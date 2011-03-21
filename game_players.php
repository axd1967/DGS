<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/make_game.php';
require_once 'include/error_codes.php';

$GLOBALS['ThePage'] = new Page('GamePlayers');


// commands / use-cases
define('CMD_ADD_WAITINGROOM_GAME', 'wr_add'); // add game in waiting-room
define('CMD_INVITE', 'inv'); // personal invitation
define('CMD_CHANGE_COLOR', 'chg_col'); // set group-color
define('CMD_CHANGE_ORDER', 'chg_ord'); // set group-order
define('CMD_START_GAME', 'start'); // start game
define('CMD_DEL_INVITE', 'delinv'); // delete reserved (not yet joined) invitation
define('CMD_ACK_INVITE', 'ackinv'); // accept invitation
define('CMD_DEL_JOINED', 'deljoined'); // delete joined player

define('FACT_SAVE', 'save');
define('FACT_REJECT', 'reject');
define('FACT_CANCEL', 'cancel');
define('FACT_PREVIEW', 'preview');
define('FACT_USE_CONV', 'use_conv');
define('FACT_USE_PROP', 'use_prop');

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

     cmd=start&gid=&...        : start game, check setup

     cmd=inv&gid=&to=&...      : invite user
     cmd=inv&gid=&to=&save...  : execute invitation of user

     cmd=ackinv&gid=&save...   : approve invitation by invited-user

     cmd=delinv&gid=&uid=&...       : delete reservation with user-invitation
     cmd=delinv&gid=&uid=&save...   : execution of deleting reserved invitation
     cmd=delinv&gid=&uid=&cancel... : cancel operation

     cmd=deljoined&gid=&uid=&...       : delete joined player
     cmd=deljoined&gid=&uid=&save...   : execution of deleting joined player
     cmd=deljoined&gid=&uid=&cancel... : cancel operation

*/
   $cmd = get_request_arg('cmd');
   if( get_request_arg(FACT_CANCEL) )
      jump_to("game_players.php?gid=$gid");


   // load data
   $grow = load_game( $gid );
   // load game-players + set vars: $arr_users, $arr_free_slots, $has_wroom_entry, $ack_invite_uid, $master_uid
   $arr_game_players = load_game_players( $gid );

   $status = $grow['Status'];
   $game_type = $grow['GameType'];
   $cnt_free_slots = count($arr_free_slots);
   $enable_edit_HK = ( count($arr_users) == count($arr_game_players) ); // HK = handicap + komi

   // waiting-room-game: edit allowed for game-master (user who started game)
   $allow_edit = ( $status == GAME_STATUS_SETUP ) && ( $grow['ToMove_ID'] == $my_id )
      && @$arr_users[$my_id] && ($arr_users[$my_id]->Flags & GPFLAG_MASTER);

   // ------------------------

   $form = $extform = null;
   $errors = null;
   if( $status == GAME_STATUS_SETUP )
   {
      $is_preview = get_request_arg(FACT_PREVIEW);
      $is_save = get_request_arg(FACT_SAVE);

      if( $allow_edit )
      {
         if( $cmd == CMD_ADD_WAITINGROOM_GAME && $cnt_free_slots > 0 ) // add to waiting-room
         {
            if( $is_save && !$has_wroom_entry )
               add_waiting_room_mpgame( $grow, $my_id );
            else
               $form = build_form_add_waiting_room( $gid, $cnt_free_slots, $cmd, $has_wroom_entry );
         }
         elseif( $cmd == CMD_INVITE ) // invitation
         {
            $to_handle = get_request_arg('to');
            list( $errors, $to_uid, $to_handle ) = check_invite( $to_handle );
            if( $is_save && count($errors) == 0 )
               add_invitation_mpgame( $gid, $to_uid );
            else
               $form = build_form_invite( $gid, $to_handle, $errors );
         }
         elseif( $cmd == CMD_DEL_INVITE ) // delete-reservation of user-invitation
         {
            $uid = (int)get_request_arg('uid');
            $errors = check_delete_invite( $uid );
            if( $is_save && count($errors) == 0 )
               delete_invite( $gid, $uid );
            else
               $form = build_form_delete_invite( $gid, $uid, $errors );
         }
         elseif( $cmd == CMD_DEL_JOINED ) // delete joined player
         {
            $uid = (int)get_request_arg('uid');
            $errors = check_delete_joined_player( $uid );
            if( $is_save && count($errors) == 0 )
               delete_joined_player( $gid, $uid );
            else
               $form = build_form_delete_joined_player( $gid, $uid, $errors );
         }
         elseif( $cmd == CMD_CHANGE_COLOR ) // change groups (color)
         {
            if( get_request_arg(FACT_USE_CONV) || get_request_arg(FACT_USE_PROP) )
            {
               $is_preview = true;
               use_handicap_suggestion( $grow );
            }
            else
            {
               if( $is_preview || ($is_save && $game_type != GAMETYPE_ZEN_GO) )
                  change_group_color( $gid, $is_preview );
               if( $is_preview || $is_save )
                  update_handicap_komi( $grow, $gid, $is_preview,
                     get_request_arg('handicap', 0), get_request_arg('komi', 6.5) );
            }

            if( !$is_save )
            {
               $extform = new Form( 'changegroupcolor', 'game_players.php', FORM_GET, false );
               $itable_handicap_suggestion = build_form_change_group_with_handicap( $extform, $grow, $cmd, $enable_edit_HK );
            }
         }
         elseif( $cmd == CMD_CHANGE_ORDER ) // change groups (order)
         {
            if( $is_save )
               change_group_order($gid, $grow['GameType']);
            else
               $extform = build_form_change_order( $grow, $gid, $cmd );
         }
      }//allow_edit

      if( $cmd == CMD_START_GAME ) // check/start game
      {
         list( $errors, $new_game_players ) = check_game_setup( $game_type, $grow['GamePlayers'] );
         $cnt_err = count($errors);
         if( $allow_edit && $is_save && $cnt_err == 0 )
            start_multi_player_game( $grow, $new_game_players );
         else
            $extform = build_form_start_game( $gid, $cmd, $errors );
      }
      elseif( $cmd == CMD_ACK_INVITE ) // accept user-invite
      {
         $errors = check_accept_invite( $my_id );
         $cnt_err = count($errors);
         if( $cnt_err == 0 && $is_save )
            accept_invite( $gid, $my_id );
         elseif( $cnt_err == 0 && get_request_arg(FACT_REJECT) )
            reject_invite( $gid, $my_id );
         else
            $form = build_form_accept_invite( $gid, $my_id, $errors );
      }
      elseif( $ack_invite_uid == $my_id ) // accept user-invite (show form)
      {
         $form = build_form_accept_invite( $gid, $my_id );
      }
   }//setup-status

   $arr_ratings = MultiPlayerGame::calc_average_group_ratings($arr_game_players);
   $utable = build_table_game_players( $grow, $cmd, $extform );


   // ------------------------

   $title = T_('Game-Players information');
   $title_page = build_page_title( $title, $grow['Status'] );
   start_page( $title, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title_page</h3>\n";

   $itable_game_settings = build_game_settings( $grow );
   echo $itable_game_settings->make_table(), "<br>\n";

   // use extform | form
   if( !is_null($extform) )
      echo $extform->print_start_default();
   if( !is_null($utable) )
      $utable->echo_table();

   if( @$itable_handicap_suggestion )
      echo "<br>\n", $itable_handicap_suggestion->make_table();

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
   if( $status == GAME_STATUS_SETUP )
   {
      if( $has_wroom_entry && $cnt_free_slots == 0 )
         $menu_array[T_('Show waiting-room entry#mpg')] = "waiting_room.php?gid=$gid#joingameForm";

      if( $allow_edit )
      {
         if( $cnt_free_slots )
         {
            $menu_array[T_('Add to waiting room')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_ADD_WAITINGROOM_GAME;
            $menu_array[T_('Invite')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_INVITE;
         }

         $chg_col_title = ($enable_edit_HK)
            ? ( $game_type == GAMETYPE_ZEN_GO ? T_('Change handicap') : T_('Change color & handicap') )
            : T_('Change color');
         if( $enable_edit_HK || $game_type == GAMETYPE_TEAM_GO )
            $menu_array[$chg_col_title] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_CHANGE_COLOR;

         $menu_array[T_('Change order')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_CHANGE_ORDER;
         $menu_array[T_('Start game')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_START_GAME;
      }
      else
         $menu_array[T_('Check game')] = "game_players.php?gid=$gid".URI_AMP.'cmd='.CMD_START_GAME;
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
               GameTexts::format_game_type($grow['GameType'], $grow['GamePlayers']),
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
      $itable->add_sinfo(
            T_('Group ratings'), implode("<br>\n", build_group_rating()) );
   }
   return $itable;
}//build_game_settings

function build_group_rating()
{
   global $arr_ratings;

   $arr = array();
   foreach( array( GPCOL_B, GPCOL_W, GPCOL_G1, GPCOL_G2, GPCOL_BW ) as $gr_col )
   {
      if( isset($arr_ratings[$gr_col]) )
      {
         $arr[] = sprintf( "<b>%s:</b> %s",
                  ( $gr_col != GPCOL_BW ? GamePlayer::get_group_color_text($gr_col) : T_('All#gpcol') ),
                  ( isset($arr_ratings[$gr_col]) ) ? echo_rating( $arr_ratings[$gr_col] ) : NO_VALUE );
      }
   }
   return $arr;
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

function build_user_actions( $gp )
{
   global $gid, $base_path, $allow_edit, $my_id;

   $arr = array();
   $uid = $gp->uid;
   if( $uid > 0 )
   {
      $gid = $gp->gid;
      if( $my_id != $uid )
         $arr[] = anchor("message.php?mode=NewMessage".URI_AMP."uid=$uid",
            image( $base_path.'images/send.gif', 'M' ), T_('Send message'), 'class="ButIcon"' );
      if( $allow_edit && !($gp->Flags & GPFLAG_MASTER) )
      {
         if( ($gp->Flags & GPFLAGS_RESERVED_INVITATION) == GPFLAGS_RESERVED_INVITATION )
         {
            $arr[] = anchor("message.php?mode=NewMessage".URI_AMP."mpgid=$gid".URI_AMP."mpmt=".MPGMSG_INVITE .
                     URI_AMP."mpuid=$uid".URI_AMP."preview=1",
                  image( $base_path.'images/invite.gif', 'I' ), T_('Send invitation message'), 'class="ButIcon"' );
            $arr[] = anchor("game_players.php?gid=$gid".URI_AMP."cmd=".CMD_DEL_INVITE.URI_AMP."uid=$uid",
               image( $base_path.'images/trashcan.gif', 'X' ), T_('Delete reservation#mpg'), 'class="ButIcon"' );
         }
         if( $gp->Flags & GPFLAG_JOINED )
         {
            $arr[] = anchor("game_players.php?gid=$gid".URI_AMP."cmd=".CMD_DEL_JOINED.URI_AMP."uid=$uid",
               image( $base_path.'images/trashcan.gif', 'X' ), T_('Delete joined player#mpg'), 'class="ButIcon"' );
         }
      }
   }
   return implode('', $arr);
}//build_user_actions

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
// global OUTPUT: $arr_users[uid>0] = GamePlayer, $arr_free_slots[] = GamePlayer,
//                $has_wroom_entry = true|false, $ack_invite_uid = 0|uid, $master_uid = uid
function load_game_players( $gid )
{
   global $my_id, $arr_users, $arr_free_slots, $has_wroom_entry, $ack_invite_uid, $master_uid;
   $arr_users = array();
   $arr_free_slots = array();
   $has_wroom_entry = false;
   $ack_invite_uid = $master_uid = 0;

   $result = db_query( "game_players.find.game_players($gid)",
      "SELECT GP.*, " .
         "P.Name, P.Handle, P.Rating2, P.RatingStatus, P.Country, P.OnVacation, P.ClockUsed, " .
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
         $user->urow = array(
               'OnVacation' => $row['OnVacation'],
               'ClockUsed'  => $row['ClockUsed'],
            );
      }
      else
         $user = null;

      $gp = GamePlayer::build_game_player( $row['ID'], $row['gid'], $row['GroupColor'],
         $row['GroupOrder'], $row['Flags'], $uid, $user );
      $arr_gp[] = $gp;

      if( $uid > 0 )
      {
         $arr_users[$uid] = $gp;
         if( $my_id == $uid && ($gp->Flags & GPFLAGS_RESERVED_INVITATION) == GPFLAGS_RESERVED_INVITATION )
            $ack_invite_uid = $uid;
         if( $master_uid == 0 && $gp->Flags & GPFLAG_MASTER )
            $master_uid = $uid;
      }
      if( ($gp->Flags & GPFLAGS_SLOT_TAKEN) == 0 )
         $arr_free_slots[] = $gp;
      if( ($gp->Flags & GPFLAGS_RESERVED_WAITINGROOM) == GPFLAGS_RESERVED_WAITINGROOM )
         $has_wroom_entry = true;
   }
   mysql_free_result($result);

   return $arr_gp;
}//load_game_players

// return arr( group-colors that appear at least once, ... )
function count_group_colors()
{
   global $arr_game_players;

   $arr = array();
   foreach( $arr_game_players as $gp )
      $arr[$gp->GroupColor] = 1;
   return array_keys($arr);
}//count_group_colors

function build_table_game_players( $grow, $cmd, &$form )
{
   global $arr_game_players, $base_path;
   $chg_group_color = ($cmd == CMD_CHANGE_COLOR) && $form && ($grow['GameType'] == GAMETYPE_TEAM_GO);
   $chg_group_order = ($cmd == CMD_CHANGE_ORDER) && $form;
   $arr_group_colors = ($chg_group_color) ? GamePlayer::get_group_color_text() : null;

   list( $next_gr_col, $next_gr_order, $gpmove_color )
      = MultiPlayerGame::calc_game_player_for_move( $grow['GamePlayers'], $grow['Moves'], $grow['Handicap'], 1 );

   $utable = new Table( 'GamePlayers', 'game_players.php' );
   $utable->use_show_rows( false );
   if( $chg_group_order )
      $utable->add_tablehead(10, T_('Set Order#header'), '', TABLE_NO_HIDE, '');
   $utable->add_tablehead( 1, '#', 'Number' );
   if( $chg_group_color )
      $utable->add_tablehead( 9, T_('Set Group#header'), '', TABLE_NO_HIDE, '');
   $utable->add_tablehead( 2, T_('Color#headermp'), 'ImageGroupColor' );
   $utable->add_tablehead( 3, T_('Player#header'), 'User' );
   $utable->add_tablehead( 5, T_('Country#header'), 'Image' );
   $utable->add_tablehead( 4, T_('Rating#header'), 'Rating' );
   $utable->add_tablehead( 6, T_('Last access#header'), 'Date' );
   $utable->add_tablehead( 7, T_('Flags#headermp'), 'ImagesLeft' );
   $utable->add_tablehead( 8, T_('Actions#headermp'), 'ImagesLeft' );

   $group_max_players = MultiPlayerGame::determine_groups_player_count( $grow['GamePlayers'] );

   $idx = 0;
   $last_group = null;
   foreach( $arr_game_players as $gp )
   {
      $idx++;
      if( !is_null($last_group) && $gp->GroupColor != $last_group )
      {//add some separator with 2 rows, so without changing row-col for next content-row
         $utable->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
         $utable->add_row_one_col( '', array( 'extra_class' => 'Empty' ) );
      }

      $imgstr = '';
      if( isRunningGame($grow['Status']) )
      {
         if( $gp->uid == $grow['ToMove_ID'] )
         {
            $img = ($grow['ToMove_ID'] == $grow['Black_ID']) ? 'bm.gif' : 'wm.gif';
            $imgstr = image( $base_path.'17/'.$img, T_('Player to move'), null, 'class="InTextImage"' );
         }
         elseif( $gp->GroupColor == $next_gr_col && $gp->GroupOrder == $next_gr_order )
            $imgstr = image( $base_path.'images/forward.gif', T_('Next player'), null, 'class="InTextImage"' );
         if( $imgstr )
            $imgstr .= MED_SPACING;
      }

      $row_str = array(
         1 => $imgstr . ( ($gp->GroupOrder > 0 ) ? $gp->GroupOrder . '.' : NO_VALUE ),
         2 => GamePlayer::build_image_group_color($gp->GroupColor) .
              MINI_SPACING . GamePlayer::get_group_color_text($gp->GroupColor),
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
      $row_str[8] = build_user_actions( $gp );

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

function build_form_add_waiting_room( $gid, $slot_count, $cmd, $has_wroom_entry )
{
   $form = new Form( 'addgame', 'game_players.php', FORM_POST );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', $cmd );
   $form->add_empty_row();
   $form->add_row( array( 'HEADER', T_('Add new game to waiting room#mpg') ));

   if( $has_wroom_entry )
   {
      $wrow = mysql_single_fetch( "game_players.build_form_add_waiting_room.find_wroom($gid)",
         "SELECT ID FROM Waitingroom WHERE gid=$gid LIMIT 1" );
      if( $wrow )
      {
         $wr_id = (int)$wrow['ID'];
         $form->add_row( array( 'TEXT',
               T_('There can only be one waiting-room entry for a multi-player-game!')
               . "<br>\n"
               . T_('To change it you have to re-add it after deleting the existing one.#mpg')
               . "<br><br>\n" . SMALL_SPACING.SMALL_SPACING
               . anchor( "waiting_room.php?info=$wr_id#joingameForm", T_('Show waiting-room entry (to delete)#mpg') )
               ));
         return $form;
      }
   }

   // how many slots to use: 1..N
   $arr_slots = build_num_range_map( 1, $slot_count );
   $form->add_row( array( 'DESCRIPTION', T_('Player-slots'),
                          'SELECTBOX', 'slots', 1, $arr_slots, $slot_count, false, ));

   append_form_add_waiting_room_game( $form, GSETVIEW_MPGAME );

   $form->add_empty_row();
   $form->add_row( array( 'TAB', 'CELL', 1, '',
                          'SUBMITBUTTON', FACT_SAVE, T_('Add Game') ));

   return $form;
}//build_form_add_waiting_room

function build_form_invite( $gid, $to_handle, $errors )
{
   $form = new Form( 'invite', 'game_players.php', FORM_POST );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', CMD_INVITE );

   show_errors( $form, $errors );

   $form->add_empty_row();
   $form->add_row( array( 'HEADER', T_('Invite to multi-player-game#mpg') ));

   $form->add_row( array( 'DESCRIPTION', T_('To (userid)'),
                          'TEXTINPUT', 'to', 20, 16, $to_handle,
                          'TEXT', sprintf( ' (%s)', T_('only rated user#mpg')) ));

   $form->add_row( array( 'TAB', 'CELL', 1, '',
                          'SUBMITBUTTON', FACT_SAVE, T_('Invite') ));

   return $form;
}//build_form_invite

function build_form_delete_invite( $gid, $uid, $errors )
{
   $cnt_err = show_errors( $form, $errors );
   if( $cnt_err > 0 )
      return null;

   $form = new Form( 'delinvite', 'game_players.php', FORM_POST );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'uid', $uid );
   $form->add_hidden( 'cmd', CMD_DEL_INVITE );

   $form->add_empty_row();
   $form->add_row( array( 'HEADER', T_('Delete reserved invitation#mpg') ));

   $form->add_row( array( 'TEXT', sprintf( T_('User [%s] will be notified about this deletion.#mpg'),
                                           user_reference(0, 1, '', $uid) ) ));
   $form->add_row( array( 'TEXT', T_('Are you sure to delete the reserved invitation of this user ?#mpg') ));

   $form->add_row( array( 'CELL', 1, '',
                          'SUBMITBUTTON', FACT_SAVE, T_('Yes'),
                          'SUBMITBUTTON', FACT_CANCEL, T_('No'), ));

   return $form;
}//build_form_delete_invite

function build_form_delete_joined_player( $gid, $uid, $errors )
{
   $cnt_err = show_errors( $form, $errors );
   if( $cnt_err > 0 )
      return null;

   $form = new Form( 'deljoined', 'game_players.php', FORM_POST );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'uid', $uid );
   $form->add_hidden( 'cmd', CMD_DEL_JOINED );

   $form->add_empty_row();
   $form->add_row( array( 'HEADER', T_('Delete joined player#mpg') ));

   $form->add_row( array( 'TEXT', sprintf( T_('User [%s] will be notified about this deletion.#mpg'),
                                           user_reference(0, 1, '', $uid) ) ));
   $form->add_row( array( 'TEXT', T_('Are you sure to delete this joined player ?#mpg') ));

   $form->add_row( array( 'CELL', 1, '',
                          'SUBMITBUTTON', FACT_SAVE, T_('Yes'),
                          'SUBMITBUTTON', FACT_CANCEL, T_('No'), ));

   return $form;
}//build_form_delete_joined_player

function build_form_accept_invite( $gid, $uid, $errors=null )
{
   global $master_uid;

   $cnt_err = show_errors( $form, $errors );
   if( $cnt_err > 0 )
      return null;

   $form = new Form( 'ackinvite', 'game_players.php', FORM_POST );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', CMD_ACK_INVITE );

   $form->add_empty_row();
   $form->add_row( array( 'HEADER', T_('Approval of invitation#mpg') ));

   $form->add_row( array( 'TEXT', T_('You have been invited to this multi-player-game.#mpg') ));
   $form->add_empty_row();
   $form->add_row( array( 'TEXT', T_('Please carefully review the game- and time-settings:#mpg') ));
   $form->add_row( array( 'TEXT', '* ' . T_('You are free to discuss the variable settings with the game-master before you accept.#mpg') ));
   $form->add_row( array( 'TEXT', '* ' . T_('However, the grouping, color and handicap for the game can only be changed by the game-master.#mpg') ));
   $form->add_empty_row();
   $form->add_row( array( 'TEXT', T_('You can either accept or reject this invitation.#mpg') ));
   $form->add_row( array( 'TEXT', sprintf( T_('The game-master [%s] will be notified about your decision.#mpg'),
                                           user_reference(0, 1, '', $master_uid) ) ));
   $form->add_empty_row();
   $form->add_row( array( 'TEXT', T_('Do you accept the invitation?#mpg') ));

   $form->add_row( array( 'CELL', 1, '',
                          'SUBMITBUTTON', FACT_SAVE, T_('Accept'),
                          'SUBMITBUTTON', FACT_REJECT, T_('Reject'), ));
   $form->add_empty_row();

   return $form;
}//build_form_accept_invite

function build_form_change_group_with_handicap( &$form, $grow, $cmd, $enable_edit_HK )
{
   $game_type = $grow['GameType'];
   $arr_color_keys = count_group_colors();

   $itable = null;
   if( $enable_edit_HK && $game_type == GAMETYPE_TEAM_GO && count($arr_color_keys) == 2 )
   {
      global $arr_game_players;

      $show_edit_hk = true;
      $arr_ratings = MultiPlayerGame::calc_average_group_ratings($arr_game_players);
      $rating1 = $arr_ratings[$arr_color_keys[0]];
      $rating2 = $arr_ratings[$arr_color_keys[1]];
      $arr_conv_sugg = suggest_conventional( $rating1, $rating2, $grow['Size'] ); // H,K,i'm-black
      $arr_prop_sugg = suggest_proper( $rating1, $rating2, $grow['Size'] );

      $arr_gp_id = array(
            implode(',', get_group_color_game_player_id( $arr_color_keys[0] )),
            implode(',', get_group_color_game_player_id( $arr_color_keys[1] )) );

      $form->add_row( array(
            'HIDDEN', 'conv_b', $arr_conv_sugg[2] ? $arr_gp_id[0] : $arr_gp_id[1],
            'HIDDEN', 'conv_w', $arr_conv_sugg[2] ? $arr_gp_id[1] : $arr_gp_id[0],
            'HIDDEN', 'conv_h', $arr_conv_sugg[0],
            'HIDDEN', 'conv_k', $arr_conv_sugg[1],
            'HIDDEN', 'prop_b', $arr_prop_sugg[2] ? $arr_gp_id[0] : $arr_gp_id[1],
            'HIDDEN', 'prop_w', $arr_prop_sugg[2] ? $arr_gp_id[1] : $arr_gp_id[0],
            'HIDDEN', 'prop_h', $arr_prop_sugg[0],
            'HIDDEN', 'prop_k', $arr_prop_sugg[1],
         ));

      $itable = build_tableinfo_handicap_suggestion( $form, $arr_color_keys[0],
         $arr_conv_sugg, $arr_prop_sugg );
   }
   elseif( $enable_edit_HK && $game_type == GAMETYPE_ZEN_GO && count($arr_color_keys) == 1 )
      $show_edit_hk = true;
   else
      $show_edit_hk = false;

   build_form_change_group( $form, $grow, $cmd, $show_edit_hk );

   return $itable;
}//build_form_change_group_with_handicap

function build_form_change_group( &$form, $grow, $cmd, $edit_hk=false )
{
   $gid = $grow['ID'];

   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', $cmd );

   $form->add_empty_row();

   if( $edit_hk )
   {
      $arr_handicap = build_arr_handicap_stones();
      $val_handicap = $grow['Handicap'];
      $form->add_row( array( 'TEXT', sptext(T_('Handicap'),1),
                             'SELECTBOX', 'handicap', 1, $arr_handicap, $val_handicap, false,
                             'TEXT', sptext(T_('Komi'),1),
                             'TEXTINPUT', 'komi', 5, 5, $grow['Komi'], ));
      $form->add_empty_row();
   }

   $form->add_row( array( 'SUBMITBUTTON', FACT_SAVE, T_('Update#submit'),
                          'TEXT', SMALL_SPACING,
                          'SUBMITBUTTON', FACT_PREVIEW, T_('Preview#submit') ));
   return $form;
}//build_form_change_group

function build_form_change_order( $grow, $gid, $cmd, $edit_hk=false )
{
   $form = new Form( 'changegrouporder', 'game_players.php', FORM_GET, false );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', $cmd );

   $form->add_empty_row();
   $form->add_row( array( 'SUBMITBUTTON', FACT_SAVE, T_('Update#submit') ));
   return $form;
}//build_form_change_order

function build_form_start_game( $gid, $cmd, $errors )
{
   global $base_path, $allow_edit;

   $form = new Form( 'startgame', 'game_players.php', FORM_GET, false );
   $form->add_hidden( 'gid', $gid );
   $form->add_hidden( 'cmd', $cmd );

   $cnt_err = show_errors( $form, $errors );
   if( $cnt_err == 0 ) // allowed to start game
   {
      $form->add_empty_row();
      $form->add_row( array(
            'TEXT', image( $base_path."images/msg.gif", T_('Send message'), null, 'class=InTextImage' ) . ' '
                  . anchor( "message.php?mode=NewMessage".URI_AMP."mpgid=$gid"
                              . URI_AMP."mpmt=".MPGMSG_STARTGAME.URI_AMP."preview=1",
                            T_('Send message to all game-players#mpg') ), ));

      if( $allow_edit )
      {
         $form->add_empty_row();
         $form->add_row( array(
               'TEXT', T_('Do you agree to start the game now?#mpg'), ));
         $form->add_row( array(
               'CELL', 1, '',
               'SUBMITBUTTON', FACT_SAVE, T_('Start Game') ));
      }
   }

   return $form;
}//build_form_start_game

function add_waiting_room_mpgame( $grow, $uid )
{
   global $NOW, $cnt_free_slots, $arr_free_slots;
   $gid = (int)$grow['ID'];
   $is_mpgame = ( $grow['GameType'] != GAMETYPE_GO );

   $slots = limit( (int)get_request_arg('slots', 1), 1, $cnt_free_slots, 1 );
   list( $must_be_rated, $rating1, $rating2 ) = parse_waiting_room_rating_range( $is_mpgame );
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
      $arr_free_slots[$i]->Flags |= GPFLAGS_RESERVED_WAITINGROOM;
      $arr_upd[] = $arr_free_slots[$i]->id;
   }

   ta_begin();
   {//HOT-section for creating waiting-room game
      db_query( "game_players.add_waiting_room_mpgame.insert_wroom($gid,$slots)", $query_wroom );
      db_query( "game_players.add_waiting_room_mpgame.gp_upd_flags($gid,$slots)",
         "UPDATE GamePlayers SET Flags = Flags | ".GPFLAGS_RESERVED_WAITINGROOM." " .
         "WHERE ID IN (" . implode(',', $arr_upd) . ") AND (Flags & ".GPFLAGS_SLOT_TAKEN.") = 0 " .
         "LIMIT $slots" );
   }
   ta_end();

   set_request_arg('sysmsg', T_('Game added!') );
}//add_waiting_room_mpgame

function add_invitation_mpgame( $gid, $inv_uid )
{
   global $arr_free_slots;

   // update free slots: set RESERVED + INV; change flags in table
   $arr_free_slots[0]->Flags |= GPFLAGS_RESERVED_INVITATION;
   $upd_gp_id = $arr_free_slots[0]->id;

   ta_begin();
   {//HOT-section for creating invitation
      db_query( "game_players.add_invitation_mpgame.gp_upd_flags($gid,$inv_uid)",
         "UPDATE GamePlayers SET uid=$inv_uid, " .
            "Flags = Flags | ".GPFLAGS_RESERVED_INVITATION." " .
         "WHERE ID=$upd_gp_id AND uid=0 AND (Flags & ".GPFLAGS_SLOT_TAKEN.") = 0 " .
         "LIMIT 1" );
   }
   ta_end();

   jump_to("message.php?mode=NewMessage".URI_AMP."mpgid=$gid".URI_AMP."mpmt=".MPGMSG_INVITE .
           URI_AMP."mpuid=$inv_uid".URI_AMP."preview=1".URI_AMP .
           "sysmsg=".urlencode(T_('Invitation reservation added for multi-player-game!')));
}//add_invitation_mpgame

function delete_invite( $gid, $uid )
{
   global $master_uid;

   ta_begin();
   {//HOT-section for removing reserved invitation
      // 1. delete reserved invitation
      GamePlayer::delete_reserved_invitation( $gid, $uid );

      // 2. notify user
      send_message( "game_players.delete_invite.notify_user($gid,$uid,$master_uid)",
         sprintf( T_('Game-master %s deleted your invitation for the game %s.#mpg'),
                  "<user $master_uid>", "<game $gid>" ),
         'Invitation for multi-player-game removed',
         $uid, '', /*notify*/true );
   }
   ta_end();

   jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=".urlencode(T_('Invitation reservation deleted!#mpg')));
}//delete_invite

function accept_invite( $gid, $uid )
{
   global $master_uid;

   ta_begin();
   {//HOT-section for invited-user accepting reserved invitation
      // 1. joining reserved invitation
      db_query( "game_players.accept_invite.gp_upd($gid,$uid)",
         "UPDATE GamePlayers SET Flags = (Flags & ~".GPFLAG_RESERVED.") | ".GPFLAG_JOINED." " .
         "WHERE gid=$gid AND uid=$uid AND " .
            "(Flags & ".GPFLAGS_RESERVED_INVITATION.") = ".GPFLAGS_RESERVED_INVITATION." " .
         "LIMIT 1" );

      // 2. increase players MP-game count
      db_query( "game_players.accept_invite.update_players($gid,$uid)",
         "UPDATE Players SET GamesMPG=GamesMPG+1 WHERE ID=$uid LIMIT 1" );

      // 3. notify game-master
      send_message( "game_players.accept_invite.notify_user($gid,$uid,$master_uid)",
         sprintf( T_('User %s has accepted your invitation to the multi-player game %s.#mpg'),
                  "<user $uid>", "<game $gid>" ),
         'Invitation for multi-player-game accepted',
         $master_uid, '', /*notify*/true );
   }
   ta_end();

   jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=".urlencode(T_('Invitation accepted!#mpg')));
}//accept_invite

function reject_invite( $gid, $uid )
{
   global $master_uid;

   ta_begin();
   {//HOT-section for invited-user rejecting reserved invitation
      // 1. delete reserved invitation
      GamePlayer::delete_reserved_invitation( $gid, $uid );

      // 2. notify game-master
      send_message( "game_players.reject_invite.notify_user($gid,$uid,$master_uid)",
         sprintf( T_('User %s has rejected your invitation to the multi-player game %s.#mpg'),
                  "<user $uid>", "<game $gid>" ),
         'Invitation for multi-player-game rejected',
         $master_uid, '', /*notify*/true );
   }
   ta_end();

   jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=".urlencode(T_('Invitation rejected!#mpg')));
}//reject_invite

function delete_joined_player( $gid, $uid )
{
   global $master_uid;

   ta_begin();
   {//HOT-section for removing joined player
      // 1. delete joined player
      GamePlayer::delete_joined_player( $gid, $uid );

      // 2. notify user
      send_message( "game_players.delete_joined_player.notify_user($gid,$uid,$master_uid)",
         sprintf( T_('Game-master %s revoked your participation for the game %s.#mpg'),
                  "<user $master_uid>", "<game $gid>" ),
         'Player of multi-player-game removed',
         $uid, '', /*notify*/true );
   }
   ta_end();

   jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=".urlencode(T_('Joined player removed!#mpg')));
}//delete_joined_player

function change_group_color( $gid, $preview )
{
   global $arr_game_players;

   // if changed, update group-color in database + table
   $cnt_upd = 0;
   $need_reorder = false;
   foreach( $arr_game_players as $gp )
   {
      $new_grcol = get_request_arg(KEY_GROUP_COLOR.$gp->id);
      if( (string)$new_grcol != '' && $gp->uid > GUESTS_ID_MAX && $new_grcol != $gp->GroupColor )
      {
         if( !$preview )
         {
            db_query( "game_players.change_group_color.gp_upd($gid)",
               "UPDATE GamePlayers SET GroupColor='" . mysql_addslashes($new_grcol) . "' " .
               "WHERE ID={$gp->id} AND uid>0 LIMIT 1" );
            $cnt_upd++;
         }
         $gp->setGroupColor($new_grcol);
         $need_reorder = true;
      }
   }

   if( $cnt_upd > 0 )
      set_request_arg('sysmsg', T_('Groups updated!') );
   if( $need_reorder )
      reorder_game_players();
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
            "UPDATE GamePlayers SET GroupOrder=$new_order WHERE ID={$gp->id} AND uid>0 LIMIT 1" );
         $gp->GroupOrder = $new_order;
         $cnt_upd++;
      }
   }

   if( $cnt_upd > 0 ) // reload to get new order
   {
      set_request_arg('sysmsg', T_('Groups updated!') );
      reorder_game_players();
   }
}//change_group_order

function build_tableinfo_handicap_suggestion( &$form, $group, $arr_conv_sugg, $arr_prop_sugg )
{
   $itable = new Table_info('suggestHK');
   $itable->add_scaption(
         sprintf( T_('Handicap suggestion for group [%s]#mpg'),
                  GamePlayer::get_group_color_text($group) ));
   $itable->add_sinfo(
         T_('Conventional handicap'),
         array(
            sptext( build_suggestion_shortinfo($arr_conv_sugg), true ),
            $form->print_insert_submit_button(FACT_USE_CONV, T_('Preview#submit') )
         ));
   $itable->add_sinfo(
         T_('Proper handicap'),
         array(
            sptext( build_suggestion_shortinfo($arr_prop_sugg), true ),
            $form->print_insert_submit_button(FACT_USE_PROP, T_('Preview#submit') )
         ));
   return $itable;
}//build_tableinfo_handicap_suggestion

function update_handicap_komi( &$grow, $gid, $preview, $handicap, $komi )
{
   global $NOW;

   $handicap = adjust_handicap( (int)$handicap, 0 );
   $komi = adjust_komi( (float)$komi, 0, JIGOMODE_KEEP_KOMI );

   if( !$preview )
   {
      db_query( "game_players.update_handicap_komi.games_upd($gid,$handicap,$komi)",
         "UPDATE Games SET Handicap=$handicap, Komi=$komi, Lastchanged=FROM_UNIXTIME($NOW) "
            . "WHERE ID=$gid LIMIT 1" );
   }
   $grow['Handicap'] = $handicap;
   $grow['Komi'] = $komi;
   $grow['Lastchanged'] = $NOW;
}//update_handicap_komi

function use_handicap_suggestion( &$grow )
{
   global $arr_game_players;

   if( get_request_arg(FACT_USE_CONV) )
      $prefix = 'conv_';
   elseif( get_request_arg(FACT_USE_PROP) )
      $prefix = 'prop_';
   else
      return;

   // 1. update color: use conv_b/w = GRCOL: change GRCOL -> b/w
   $arr = array();
   foreach( explode(',', get_request_arg($prefix.'b')) as $gp_id )
      $arr[$gp_id] = GPCOL_B;
   foreach( explode(',', get_request_arg($prefix.'w')) as $gp_id )
      $arr[$gp_id] = GPCOL_W;

   $need_reorder = false;
   foreach( $arr_game_players as $gp )
   {
      if( isset($arr[$gp->id]) )
      {
         if( $gp->GroupColor != $arr[$gp->id] )
            $need_reorder = true;
         $gp->GroupColor = $arr[$gp->id];
         set_request_arg( KEY_GROUP_COLOR.$gp->id, $gp->GroupColor );
      }
   }

   // 2. update H+K
   $grow['Handicap'] = adjust_handicap( (int)get_request_arg($prefix.'h'), 0 );
   $grow['Komi'] = adjust_komi( (float)get_request_arg($prefix.'k'), 0, JIGOMODE_KEEP_KOMI );

   if( $need_reorder )
      reorder_game_players();
}//use_handicap_suggestion

function get_group_color_game_player_id( $group_color )
{
   global $arr_game_players;

   $arr = array();
   foreach( $arr_game_players as $gp )
   {
      if( $gp->GroupColor == $group_color )
         $arr[] = $gp->id;
   }
   return $arr;
}//get_group_color_game_player_id

function reorder_game_players()
{
   global $arr_game_players;
   usort( $arr_game_players, '_sort_game_players' ); // by GroupColor ASC, GroupOrder ASC
}

function _sort_game_players( $gp1, $gp2 )
{
   $cmp_group_color = cmp_int( $gp1->getGroupColorOrder(), $gp2->getGroupColorOrder() );
   return ($cmp_group_color == 0 ) ? cmp_int( $gp1->GroupOrder, $gp2->GroupOrder ) : $cmp_group_color;
}

// check what would prevent the invitation of a user for multi-player-game
// returns arr( [error..], invite-uid, invite-handle )
function check_invite( $invite_handle )
{
   global $arr_game_players;

   $errors = array();
   if( (string)$invite_handle == '' ) // nothing to check
      return array( $errors, 0, $invite_handle );

   // load user
   $invite_user = User::load_user_by_handle( $invite_handle );
   if( is_null($invite_user) && is_numeric($invite_handle) )
      $invite_user = User::load_user( (int)$invite_handle );
   if( is_null($invite_user) )
   {
      $errors[] = ErrorCode::get_error_text('unknown_user');
      return array( $errors, 0, $invite_handle );
   }
   $invite_uid = $invite_user->ID;
   $invite_handle = $invite_user->Handle;

   // CHECKS: need free slot, only join game once, rated user, no guest, not game-master
   $arr_uid = array(); // uid => 1
   foreach( $arr_game_players as $gp )
   {
      if( $gp->uid > 0 )
         $arr_uid[$gp->uid] = 1;
      if( $gp->uid == $invite_uid && ($gp->Flags & GPFLAG_MASTER) )
         $errors[] = T_('Game-master already joined the game and can not be invited.#mpg');
   }
   if( count($arr_uid) == count($arr_game_players) )
      $errors[] = T_('No free slot available for invitation of user#mpg');
   if( $invite_uid <= GUESTS_ID_MAX )
      $errors[] = ErrorCode::get_error_text('guest_no_invite');
   if( !$invite_user->hasRating() )
      $errors[] = sprintf( T_('User [%s] has no rating#mpg'), $invite_handle );
   if( isset($arr_uid[$invite_uid]) )
      $errors[] = sprintf( T_('User [%s] already invited or joined the game#mpg'), $invite_handle );

   return array( $errors, $invite_uid, $invite_handle );
}//check_invite

// check what would prevent the deletion of reserved-invitation of given user
// returns [error..]
function check_delete_invite( $uid )
{
   global $arr_game_players;

   $errors = array();

   // CHECKS for delete: invite-reservation exists, user not joined yet, not game-master
   $found = false;
   foreach( $arr_game_players as $gp )
   {
      if( $gp->uid == $uid )
      {
         $found = true;
         if( $gp->Flags & GPFLAG_MASTER )
            $errors[] = T_('Game-master should not have reservation and can not be deleted.#mpg');
         if( $gp->Flags & GPFLAG_JOINED )
            $errors[] = T_('User to delete has already joined the game.#mpg');
         if( ($gp->Flags & GPFLAGS_RESERVED_INVITATION) != GPFLAGS_RESERVED_INVITATION )
            $errors[] = T_('No reserved invitation found for user.#mpg');
      }
   }
   if( !$found )
      $errors[] = T_('Reservation of user to be deleted can not be found.#mpg');

   return $errors;
}//check_delete_invite

// check what would prevent approval of reserved-invitation by invited user
// returns [error..]
function check_accept_invite( $uid )
{
   global $ack_invite_uid;

   // CHECKS for approval: need invitation-reservation-slot
   $errors = array();
   if( $ack_invite_uid == 0 || $ack_invite_uid != $uid )
      $errors[] = T_('There is no reserved invitation for you to approve.#mpg');

   return $errors;
}//check_accept_invite

// check what would prevent the deletion of a joined player of given user
// returns [error..]
function check_delete_joined_player( $uid )
{
   global $arr_game_players;

   $errors = array();

   // CHECKS for delete: player is joined, not game-master
   $found = false;
   foreach( $arr_game_players as $gp )
   {
      if( $gp->uid == $uid )
      {
         $found = true;
         if( $gp->Flags & GPFLAG_MASTER )
            $errors[] = T_('Game-master is required and can not be deleted with this action.#mpg');
         if( !($gp->Flags & GPFLAG_JOINED) )
            $errors[] = T_('User to delete has not joined the game.#mpg');
      }
   }
   if( !$found )
      $errors[] = T_('Joined player to be deleted can not be found.#mpg');

   return $errors;
}//check_delete_joined_player

// check what would prevent the start of the game
// returns arr( [error..], new_game_players )
function check_game_setup( $game_type, $game_players )
{
   global $arr_game_players, $arr_users;

   $errors = array();
   $new_game_players = $game_players;

   $cnt_reserved = $cnt_joined = 0;
   $arr_uid = array(); // uid => count
   $arr_grcol = array(); // groupcolor => count
   $arr_order = array(); // groupcolor => [ group-orders, ... ] (ordered)
   foreach( $arr_game_players as $gp )
   {
      if( $gp->uid > 0 )
      {
         if( $gp->Flags & GPFLAG_JOINED )
            $cnt_joined++;
         if( $gp->Flags & GPFLAG_RESERVED )
            $cnt_reserved++;
         if( !$gp->user->hasRating() ) // CHECK: all users must have a rating
            $errors[] = sprintf( T_('User [%s] has no rating#mpg'), $gp->user->Handle );
         if( isset($arr_uid[$gp->uid]) )
            $arr_uid[$gp->uid]++;
         else
            $arr_uid[$gp->uid] = 1;
      }
      if( isset($arr_grcol[$gp->GroupColor]) )
         $arr_grcol[$gp->GroupColor]++;
      else
         $arr_grcol[$gp->GroupColor] = 1;
      $arr_order[$gp->GroupColor][] = $gp->GroupOrder;
   }

   // CHECK: all users must be joined, no reserved slots any more
   if( $cnt_joined != count($arr_game_players) )
      $errors[] = T_('Some game-players are missing#mpg');
   if( $cnt_reserved > 0 )
      $errors[] = T_('Not all reservation-slots have been joined by players#mpg');

   // CHECK: error, if same user appears more than once
   foreach( $arr_uid as $uid => $cnt )
   {
      if( $arr_uid[$uid] > 1 )
         $errors[] = sprintf( T_('Player [%s] joined the game more than once#mpg'),
            $arr_users[$uid]->user->Handle );
   }

   $arr_group_cnt = MultiPlayerGame::determine_groups_player_count( $game_players, false );
   $arr_grcol_vals = array_values( $arr_grcol );
   $arr_grcol_keys = array_keys( $arr_grcol );
   $arr_grcol_keys_text = build_arr_group_color_texts( $arr_grcol_keys );
   if( $game_type == GAMETYPE_TEAM_GO )
   {
      // CHECK: need both group-colors: B + W
      if( count($arr_grcol) == 2 && isset($arr_grcol[GPCOL_B]) && isset($arr_grcol[GPCOL_W]) )
      {
         // CHECK: GroupColor-count per group must match GamePlayers-config
         if( min($arr_grcol_vals) != $arr_group_cnt[0] || max($arr_grcol_vals) != $arr_group_cnt[1] )
            $errors[] = sprintf( T_('Numbers of players (%s for %s, %s for %s) do not match the game-config [%s]#mpg'),
               $arr_grcol[GPCOL_B], GamePlayer::get_group_color_text(GPCOL_B),
               $arr_grcol[GPCOL_W], GamePlayer::get_group_color_text(GPCOL_W),
               $game_players );
         else
         {
            // determine new 'GamePlayers'-info in order B:W (needed for playing)
            if( $arr_group_cnt[0] != $arr_group_cnt[1] && $arr_grcol[GPCOL_B] != $arr_group_cnt[0] )
               $new_game_players = sprintf( '%d:%d', $arr_group_cnt[1], $arr_group_cnt[0] );
         }
      }
      else
         $errors[] = sprintf( T_('Groups [%s, %s] are required, but found [%s]#mpg'),
            GamePlayer::get_group_color_text(GPCOL_B),
            GamePlayer::get_group_color_text(GPCOL_W),
            implode(', ', $arr_grcol_keys_text) );
   }
   else //if( $game_type == GAMETYPE_ZEN_GO )
   {
      // CHECK: need one group-color: BW
      if( count($arr_grcol) == 1 && isset($arr_grcol[GPCOL_BW]) )
      {
         // CHECK: GroupColor-count per group must match GamePlayers-config
         if( $arr_grcol_vals[0] != $arr_group_cnt[0] )
            $errors[] = sprintf( T_('Number of players (%s for %s) does not match the game-config [%s]#mpg'),
               $arr_grcol[GPCOL_BW], GamePlayer::get_group_color_text(GPCOL_BW),
               GameTexts::format_game_type($game_type, $game_players) );
      }
      else
         $errors[] = sprintf( T_('Only %s-group is allowed, but found [%s]#mpg'),
            GamePlayer::get_group_color_text(GPCOL_BW),
            implode(', ', $arr_grcol_keys_text) );
   }

   // CHECK: GroupOrder must be consecutive (1,2,3,4...), starting at 1, unique per group(s)
   foreach( $arr_order as $grcol => $arr )
   {
      $cnt = count($arr);
      $grcol_text = GamePlayer::get_group_color_text($grcol);
      if( $arr[0] == 0 )
         $errors[] = sprintf( T_('Order of group [%s] must be set#mpg'), $grcol_text );
      elseif( $arr[0] != 1 )
         $errors[] = sprintf( T_('Order of group [%s] must start at 1#mpg'), $grcol_text );
      elseif( array_sum($arr) != ($cnt * ($cnt+1) / 2) )
         $errors[] = sprintf( T_('Order of group [%s] must be unique and consecutive: 1,2,3, ...#mpg'), $grcol_text );
   }

   return array( $errors, $new_game_players );
}//check_game_setup

function build_arr_group_color_texts( $arr_group_colors )
{
   $arr = array();
   foreach( $arr_group_colors as $grcol )
      $arr[] = GamePlayer::get_group_color_text($grcol);
   return $arr;
}

function start_multi_player_game( $grow, $upd_game_players )
{
   global $arr_game_players;
   $gid = $grow['ID'];
   $handicap = $grow['Handicap'];
   $game_type = $grow['GameType'];

   $arr_ratings = MultiPlayerGame::calc_average_group_ratings($arr_game_players);

   list( $group_color, $group_order, $gpmove_color )
      = MultiPlayerGame::calc_game_player_for_move( $upd_game_players, 0, $handicap, 0 );
   $black_id = GamePlayer::load_uid_for_move( $gid, $group_color, $group_order );
   $black_row = build_start_game_user_row( $black_id );
   $black_row['Rating2'] = $arr_ratings[($game_type == GAMETYPE_ZEN_GO) ? GPCOL_BW : GPCOL_B];

   list( $group_color, $group_order, $gpmove_color )
      = MultiPlayerGame::calc_game_player_for_move( $upd_game_players, 0, $handicap, 1 );
   $white_id = GamePlayer::load_uid_for_move( $gid, $group_color, $group_order );
   $white_row = build_start_game_user_row( $white_id );
   $white_row['Rating2'] = $arr_ratings[($game_type == GAMETYPE_ZEN_GO) ? GPCOL_BW : GPCOL_W];

   $gdata = array() + $grow;
   $gdata['Black_ID'] = $black_row['ID'];
   $gdata['White_ID'] = $white_row['ID'];
   $gdata['Rated'] = 'N';
   $gdata['GamePlayers'] = $upd_game_players; // correct GamePlayers for right-playing-order

   ta_begin();
   {//HOT-section for starting multi-player-game
      create_game($black_row, $white_row, $gdata, $gid );
      MultiPlayerGame::update_players_start_mpgame( $gid ); // Players.Running++
   }
   ta_end();

   jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=".urlencode(T_('Game started!')));
}//start_multi_player_game

function build_start_game_user_row( $uid )
{
   global $arr_users;

   if( !isset($arr_users[$uid]) )
      error('internal_error', "game_players.build_start_game_user_row($uid)");
   $gp = $arr_users[$uid];

   return array(
      'ID'           => $gp->uid,
      'RatingStatus' => RATING_RATED,
      'Rating2'      => $gp->user->Rating,
      'OnVacation'   => $gp->user->urow['OnVacation'],
      'ClockUsed'    => $gp->user->urow['ClockUsed'],
   );
}//build_start_game_user_row

function build_page_title( $title, $status )
{
   if( $status == GAME_STATUS_SETUP )
      $status_str = T_('Game setup');
   elseif( $status == GAME_STATUS_FINISHED )
      $status_str = T_('Game finished');
   else
      $status_str = T_('Game running');
   return sprintf( '%s - (%s)', $title, $status_str );
}

function show_errors( &$form, $errors )
{
   if( is_null($errors) )
      return 0;

   $cnt_err = count($errors);
   if( $cnt_err )
   {
      $form->add_empty_row();
      $form->add_row( array(
            'DESCRIPTION', T_('Errors'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
   }
   return $cnt_err;
}//show_errors

?>

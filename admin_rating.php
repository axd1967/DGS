<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/game_functions.php';
require_once 'include/game_texts.php';
require_once 'include/gui_functions.php';
require_once 'include/rating.php';
require_once 'include/classlib_user.php';
require_once 'include/db/ratingchangeadmin.php';
require_once 'include/table_columns.php';
require_once 'include/utilities.php';
require_once 'tournaments/include/tournament_globals.php';

$GLOBALS['ThePage'] = new Page('RatingAdmin');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin_rating');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_rating');
   if ( !(@$player_row['admin_level'] & ADMIN_GAME) )
      error('adminlevel_too_low', 'admin_rating');

   $page = "admin_rating.php";
   $title = T_('Admin Editor of DGS-rating');

/* Actual REQUEST calls used:
     (no args)          : ask for user
     uid=               : edit rank-info/DGS-rating of user
     save&uid=          : save changes: ratingtype, rating, reset_confival
*/

   $uid = (int)get_request_arg('uid');
   if ( $uid <= GUESTS_ID_MAX ) $uid = 0;

   $arg_rating = trim(get_request_arg('rating'));
   $arg_ratingtype = get_request_arg('ratingtype', 'dragonrank');
   $do_reset_confidence = @$_REQUEST['reset_confival']; // reset rating confidence-interval

   // init
   $errors = array();
   $user = $upd_user = $rcatable = null;
   $arr_std_games = $arr_mp_games = $arr_tourneys = $arr_invitations = 0;
   $changes = 0;
   if ( $uid )
   {
      $user = User::load_user( $uid );
      if ( is_null($user) )
         error('unknown_user', "admin_rating.find_user($uid)");
      $upd_user = array(
         'Rating'    => $user->Rating,
         'RatingMin' => $user->urow['RatingMin'],
         'RatingMax' => $user->urow['RatingMax'],
      );

      if ( $user->RatingStatus != RATING_RATED )
         $errors[] = sprintf( T_('Rating can only be changed for rating-status [%s].'), RATING_RATED );
      if ( $uid == $my_id )
         $errors[] = T_('You can\'t change your own rating. Ask another admin.');

      if ( (string)$arg_rating != '' ) // update rating
      {
         $upd_user['Rating'] = convert_to_rating($arg_rating, $arg_ratingtype);
         if ( abs($upd_user['Rating'] - $user->Rating) > 0.005 )
            $changes |= RCADM_CHANGE_RATING;
      }

      // prepare changes
      if ( $do_reset_confidence ) // reset rating confidence-interval
      {
         $newrating = $upd_user['Rating'];
         $upd_user['RatingMin'] = $newrating - 200 - max(1600 - $newrating, 0) * 2 / 15;
         $upd_user['RatingMax'] = $newrating + 200 + max(1600 - $newrating, 0) * 2 / 15;
         $changes |= RCADM_RESET_CONFIDENCE;
      }

      if ( !is_valid_rating($upd_user['Rating']) )
         $errors[] = sprintf( T_('Rating [%s] is invalid'), $upd_user['Rating'] );
      if ( $changes == 0 && (@$_REQUEST['preview'] || @$_REQUEST['save']) )
         $errors[] = T_('No rating-change or it is too small.');

      $rcatable = load_old_rating_changes( $uid );

      $arr_std_games = find_running_normal_games( $uid ); // gid/opp_uid/opp_handle/color => ..
      $arr_mp_games = find_running_multi_player_games( $uid ); // gid/GameType/GamePlayers/GroupColor => ..
      $arr_tourneys = find_running_tournaments( $uid ); // tid, ..
      $arr_invitations = find_open_invitations( $uid ); // gid/opp_uid/opp_handle/color => ..
   }//load-/check-user
   else
      $errors[] = T_('Missing user to change rating. This page is normally called from user-info page.');



   // ---------- Process actions ------------------------------------------------

   if ( count($errors) == 0 )
   {
      if ( @$_REQUEST['save'] && $changes > 0 )
      {
         $diff = array();
         $diff[] = sprintf( '%s[%s > %s]', 'Rating',    $user->Rating, $upd_user['Rating'] );
         $diff[] = sprintf( '%s[%s > %s]', 'RatingMin', $user->urow['RatingMin'], $upd_user['RatingMin'] );
         $diff[] = sprintf( '%s[%s > %s]', 'RatingMax', $user->urow['RatingMax'], $upd_user['RatingMax'] );
         $diff[] = 'reset-confidence=' . yesno($do_reset_confidence);

         ta_begin();
         {//HOT-SECTION for admin changing user-rating
            $new_rating = $upd_user['Rating'];
            admin_log( $my_id, $user->Handle, sprintf( "Change rating: %s", implode(', ', $diff) ) );
            change_user_rating( $uid, $changes, $new_rating, $upd_user['RatingMin'], $upd_user['RatingMax'] );

            // fix running games/MPGs, create bulletins for running games and tournaments
            $has_bulletins = notify_fix_running_games( $uid, $user->Handle, $changes, $user->Rating, $new_rating );

            User::delete_cache_user_handle('admin_rating', $user->Handle );
         }
         ta_end();

         $extra_msg = ($has_bulletins) ? T_('Ensure that created bulletins are activated!') : '';
         jump_to("admin_rating.php?uid=$uid".URI_AMP."sysmsg=".urlencode(T_('Rating changed!') . " $extra_msg"));
      }
   }//actions


   // ---------- Rank Edit Form ----------------------------------------------

   $rform = new Form( 'adminrating', $page, FORM_GET );
   $rform->add_hidden('uid', $uid);

   if ( $uid )
   {
      $rform->add_row( array( 'DESCRIPTION', T_('User'),
                              'TEXT', $user->user_reference(), ));
      $rform->add_row( array( 'DESCRIPTION', T_('Rating Status'),
                              'TEXT', $user->RatingStatus, ));
      $rform->add_row( array( 'DESCRIPTION', T_('Current Rating'),
                              'TEXT', echo_rating($user->Rating, true, $uid, false ) ));
   }

   if ( count($errors) )
   {
      $rform->add_row( array( 'HR' ));
      $rform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
   }
   $rform->add_row( array( 'HR' ));

   if ( $uid && $user->RatingStatus == RATING_RATED )
   {
      $rform->add_row( array(
            'DESCRIPTION', T_('New Rating'),
            'TEXTINPUT', 'rating', 16, 16, $arg_rating,
            'SELECTBOX', 'ratingtype', 1, getRatingTypes(), $arg_ratingtype, false, ));
      $rform->add_row( array(
            'TAB',
            'CHECKBOX', 'reset_confival', 1, T_('Reset Confidence interval'),
                        (int)get_request_arg('reset_confival'), ));

      $rform->add_empty_row();
      $rform->add_row( array(
            'DESCRIPTION', T_('New Rating'),
            'TEXT', echo_rating($upd_user['Rating'], true, 0, false), ));
      $rform->add_row( array(
            'DESCRIPTION', T_('Current Confidence Interval'),
            'TEXT', sprintf( '%1.6f (%s) > %1.6f < (%s) %1.6f',
                             $user->urow['RatingMin'], diff( $user->Rating - $user->urow['RatingMin'] ),
                             $user->Rating,
                             diff( $user->urow['RatingMax'] - $user->Rating ), $user->urow['RatingMax'] ), ));
      $rform->add_row( array(
            'DESCRIPTION', T_('New Confidence Interval'),
            'TEXT', sprintf( '%1.6f (%s) > %1.6f < (%s) %1.6f',
                             $upd_user['RatingMin'], diff( $upd_user['Rating'] - $upd_user['RatingMin'] ),
                             $upd_user['Rating'],
                             diff( $upd_user['RatingMax'] - $upd_user['Rating'] ), $upd_user['RatingMax'] ), ));
      $rform->add_row( array(
            'DESCRIPTION', T_('Delta Rating Change'),
            'TEXT', sprintf( '%s (%s) > %s < (%s) %s',
                             diff( $user->urow['RatingMin'] - $upd_user['RatingMin'], '%1.2f' ),
                             percent( $user->Rating - $user->urow['RatingMin'], $upd_user['Rating'] - $upd_user['RatingMin'] ),
                             diff( $user->Rating - $upd_user['Rating'], '%1.2f' ),
                             percent( $user->urow['RatingMax'] - $user->Rating, $upd_user['RatingMax'] - $upd_user['Rating'] ),
                             diff( $user->urow['RatingMax'] - $upd_user['RatingMax'], '%1.2f' ) ), ));


      // show running games and tournaments for player
      $lines = build_user_activities( $changes );
      $rform->add_empty_row();
      $rform->add_row( array( 'CHAPTER', T_('Running games and tournaments for this player'), ));
      $rform->add_row( array( 'CELL', 2, '', 'TEXT', implode("<br>\n", $lines), ));

      $rform->add_empty_row();
      $rform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'preview', T_('Preview'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'save', T_('Save changes'), ));

      $lines = build_user_activities_list();
      $rform->add_row( array( 'HEADER', T_('List of running games and active tournaments'), ));
      $rform->add_row( array( 'CELL', 2, '', 'TEXT', '<ul><li>' . implode("<br>\n<li>", $lines) . '</ul>', ));
   }


   // ---------- Main ----------

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $rform->echo_string();

   if ( !is_null($rcatable) )
   {
      section('old_ratingchanges', T_('Former rating-changes of user'));
      $rcatable->echo_table();
   }


   $notes = array();
   $notes[] = T_('WARNING: No rating-updates on change of daylight-saving-time to avoid side-effects on rating-recalculations, because time of rating-changes and game-ends must be synchronized.');
   $notes[] = null;
   $notes[] = sprintf( T_('Check notes about rating-change on page %s.'), anchor("edit_rating.php", T_('Change rating & rank')) );
   $notes[] = T_('Rating-change is OK if ranking-diff >6k.');
   $notes[] = T_('Reset of confidence-interval is OK if ranking-diff >3k and SHOULD always be used if rating is changed.');
   echo_notes( 'adminratingnotes', T_('Important notes about Rating changes'), $notes );

   $menu_array = array();
   $menu_array[T_('Refresh')] = "$page?uid=$uid";

   end_page(@$menu_array);
}//main


function diff( $diff, $fmt='%d' )
{
   $diff = sprintf( $fmt, $diff );
   return ($diff < 0) ? $diff : ($diff > 0 ? '+'.$diff : 0 );
}

function percent( $old, $new )
{
   return sprintf( '%d%%', 100 * ($new / $old) );
}

function load_old_rating_changes( $uid )
{
   global $page;
   if ( !$uid )
      return null;

   $rcatable = new Table( 'ratingchanges', $page, null, '',
      TABLE_NO_HIDE|TABLE_NO_SORT|TABLE_NO_SIZE|TABLE_ROWS_NAVI );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $rcatable->add_tablehead( 1, T_('Created#header'), 'Date', 0, 'Created-');
   $rcatable->add_tablehead( 2, T_('Changes#header'), 'Enum' );
   $rcatable->add_tablehead( 3, T_('Rating#header'), 'Rating' );

   $iterator = new ListIterator( 'admin_rating.RatingChangeAdmin', null, 'ORDER BY Created DESC' );
   $iterator = RatingChangeAdmin::load_ratingchangeadmin( $iterator, $uid );
   $rcatable->set_found_rows( $iterator->getItemCount() );

   while ( list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $rca, $orow ) = $arr_item;
      $rcatable->add_row( array(
            1 => ($rca->Created > 0) ? date(DATE_FMT2, $rca->Created) : '',
            2 => format_ratingchangeadmin_changes($rca->Changes, ' + '),
            3 => ($rca->Changes & RCADM_CHANGE_RATING )
                    ? sprintf( '%s = ELO %f', echo_rating($rca->Rating, true, 0, false), $rca->Rating )
                    : NO_VALUE,
            'extra_class' => 'TCells',
         ));
   }

   return $rcatable;
}//load_old_rating_changes


/*!
 * \brief Finds normal running non-tournament games played by player with given $uid.
 * \return arr( { gid/opp_uid/opp_handle => .., color => BLACK|WHITE of pivot-user $uid }, ... )
 */
function find_running_normal_games( $uid )
{
   $out = array();
   $base_query = "SELECT G.ID AS gid, G.%s AS opp_uid, P.Handle AS opp_handle, %s AS color " .
      "FROM Games AS G INNER JOIN Players AS P ON P.ID=G.%s " .
      "WHERE G.GameType='".GAMETYPE_GO."' AND G.Status ".IS_STARTED_GAME." AND G.tid=0 " .
         "AND G.%s=$uid AND G.%s_Start_Rating > -".OUT_OF_RATING;

   $result = db_query("admin_rating.find_running_normal_games.black($uid)",
      sprintf( $base_query, 'White_ID', BLACK, 'White_ID', 'Black_ID', 'Black' ) );
   while ( $row = mysql_fetch_array($result) )
      $out[] = $row;
   mysql_free_result($result);

   $result = db_query("admin_rating.find_running_normal_games.white($uid)",
      sprintf( $base_query, 'Black_ID', WHITE, 'Black_ID', 'White_ID', 'White' ) );
   while ( $row = mysql_fetch_array($result) )
      $out[] = $row;
   mysql_free_result($result);

   return $out;
}//find_running_normal_games

/*!
 * \brief Finds multi-player running games played by player with given $uid.
 * \return arr( { gid/GameType/GamePlayers/GroupColor => .. },  ... )
 */
function find_running_multi_player_games( $uid )
{
   $out = array();

   $result = db_query("admin_rating.find_running_multi_player_games($uid)",
      "SELECT GP.gid, G.GameType, G.GamePlayers, GP.GroupColor " .
      "FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid " .
      "WHERE GP.uid=$uid AND G.Status ".IS_RUNNING_GAME );
   while ( $row = mysql_fetch_array($result) )
      $out[] = $row;
   mysql_free_result($result);

   return $out;
}//find_running_multi_player_games

/*!
 * \brief Finds running tournaments where given $uid has registered.
 * \return arr( tid, ... )
 */
function find_running_tournaments( $uid )
{
   $out = array();

   if( ALLOW_TOURNAMENTS )
   {
      $tstats = array( TOURNEY_STATUS_ADMIN, TOURNEY_STATUS_NEW, TOURNEY_STATUS_REGISTER, TOURNEY_STATUS_PAIR,
         TOURNEY_STATUS_PLAY );
      $result = db_query("admin_rating.find_running_tournaments($uid)",
         "SELECT TP.tid FROM TournamentParticipant AS TP INNER JOIN Tournament AS T ON T.ID=TP.tid " .
         "WHERE TP.uid=$uid AND T.Status IN ('".implode("','", $tstats)."')" );
      while ( $row = mysql_fetch_array($result) )
         $out[] = $row['tid'];
      mysql_free_result($result);
   }

   return $out;
}//find_running_tournaments

/*!
 * \brief Finds open invitations for given $uid.
 * \return arr( { gid/opp_uid/opp_handle => .., color => BLACK|WHITE of pivot-user $uid }, ... )
 */
function find_open_invitations( $uid )
{
   $out = array();
   $base_query = "SELECT G.ID AS gid, G.%s AS opp_uid, P.Handle AS opp_handle, %s AS color " .
      "FROM Games AS G INNER JOIN Players AS P ON P.ID=G.%s " .
      "WHERE G.Status='".GAME_STATUS_INVITED."' AND G.%s=$uid";

   $result = db_query("admin_rating.find_open_invitations.black($uid)",
      sprintf( $base_query, 'White_ID', BLACK, 'White_ID', 'Black_ID' ) );
   while ( $row = mysql_fetch_array($result) )
      $out[] = $row;
   mysql_free_result($result);

   $result = db_query("admin_rating.find_open_invitations.white($uid)",
      sprintf( $base_query, 'Black_ID', WHITE, 'Black_ID', 'White_ID' ) );
   while ( $row = mysql_fetch_array($result) )
      $out[] = $row;
   mysql_free_result($result);

   return $out;
}//find_open_invitations

function build_user_activities( $changes )
{
   global $base_path, $arr_std_games, $arr_mp_games, $arr_invitations, $arr_tourneys;

   $lines = array();
   $lines[] = sprintf( T_('There are %s normal games, %s multi-player-games, %s open invitations. See below for full list.'),
      count($arr_std_games), count($arr_mp_games), count($arr_invitations) );
   if ( $changes & RCADM_CHANGE_RATING )
      $lines[] = T_('The start-rating will be adjusted for the running games. All opponents will be informed about the rating-change.');
   $lines[] = '';
   $lines[] = sprintf( T_('There are %s active tournaments where the player registered. See below for full list.'), count($arr_tourneys) );
   if ( $changes & RCADM_CHANGE_RATING )
      $lines[] = T_('The tournament directors will be informed about the rating-change.');

   $lines[] = '';
   $lines[] = span('ErrMsg', T_('On rank-changes bulletins are created to inform the opponents and tournament-directors!'));
   $lines[] = span('ErrMsg',
      sprintf( T_('On page with %s choose \'Fresh\'-Status selection to see the bulletins to review and set to show.'),
         anchor( $base_path.'list_bulletins.php?read=2', T_('All Bulletins') ) ));

   return $lines;
}//build_user_activities

function build_user_activities_list()
{
   global $base_path, $arr_std_games, $arr_mp_games, $arr_invitations, $arr_tourneys;
   $lines = array();

   $out = array();
   foreach ( $arr_std_games as $arr )
   {
      $gid = $arr['gid'];
      $out[] = anchor( $base_path."gameinfo.php?gid=$gid", "#$gid ({$arr['opp_handle']})" );
   }
   if( count($out) )
      $lines[] = T_('Running normal games (opponents)#adm') . ":<br>\n" . build_text_block($out, 5) . "<br>\n";

   $out = array();
   foreach ( $arr_mp_games as $arr )
   {
      $gid = $arr['gid'];
      $out[] = anchor( $base_path."game_players.php?gid=$gid",
         "#$gid " . GameTexts::format_game_type( $arr['GameType'], $arr['GamePlayers'] ) . " [{$arr['GroupColor']}]");
   }
   if( count($out) )
      $lines[] = T_('Running multi-player-games [player-color]#adm') . ":<br>\n" . build_text_block($out, 5) . "<br>\n";

   $out = array();
   foreach ( $arr_invitations as $arr )
      $out[] = anchor( $base_path."userinfo.php?uid={$arr['opp_uid']}", $arr['opp_handle'] );
   if( count($out) )
      $lines[] = T_('Open invitations (opponents)#adm') . ":<br>\n" . build_text_block($out, 10) . "<br>\n";

   if ( ALLOW_TOURNAMENTS )
   {
      $out = array();
      foreach ( $arr_tourneys as $tid )
         $out[] = anchor( $base_path."tournaments/view_tournament.php?tid=$tid", "#$tid " );
      if( count($out) )
         $lines[] = T_('Active tournaments#adm') . ":<br>\n" . build_text_block($out, 15) . "<br>\n";
   }

   return $lines;
}//build_user_activities_list


/*!
 * \brief Fixes running normal and multi-player games and creates bulletins to inform opponents and tournament-directors.
 * \param $uid Players.ID and Players.Handle for the user with the changed rating
 * \return true = bulletins created; false = no bulletins created
 *
 * \note Players.Rating must have already been changed to new rating!
 *
 * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
 */
function notify_fix_running_games( $uid, $handle, $changes, $old_rating, $new_rating )
{
   global $player_row, $NOW, $arr_std_games, $arr_mp_games, $arr_invitations, $arr_tourneys;

   // fixes/notifies only required on rank-change (not confidence-interval-reset)
   if ( !($changes & RCADM_CHANGE_RATING) )
      return false;

   $uids = array( $uid ); // notify player with rating-change
   $old_rating_str = echo_rating($old_rating, /*%*/false, 0, /*engl*/true, /*short*/1 );
   $new_rating_str = echo_rating($new_rating, /*%*/false, 0, /*engl*/true, /*short*/1 );

   // fix normal running games -------------

   $base_query = "UPDATE Games SET Rated='N', %s_Start_Rating=$new_rating " .
      "WHERE GameType='".GAMETYPE_GO."' AND %s=$uid AND tid=0 " .
         "AND Status ".IS_STARTED_GAME." AND %s_Start_Rating > -".OUT_OF_RATING;
   db_query("admin_rating.notify_fix_running_games.run_games.black($uid)",
      sprintf($base_query, 'Black', 'Black_ID', 'Black' ) );
   db_query("admin_rating.notify_fix_running_games.run_games.white($uid)",
      sprintf($base_query, 'White', 'White_ID', 'White' ) );

   foreach ( $arr_std_games as $arr ) // arr: gid/opp_uid/opp_handle/color => ..
      $uids[] = $arr['opp_uid'];


   // fix multi-player-games ---------------
   // NOTE: changed start-rating will not be accurate anymore, because group rating uses
   //       the current rating of the players, which could have changed since the MP-game has been started.
   //       But it's considered better to change it regardless to reflect the (big) rating change.

   foreach ( $arr_mp_games as $arr ) // arr: gid/GameType/GamePlayers/GroupColor => ..
   {
      $gid = $arr['gid'];
      $game_type = $arr['GameType'];
      $arr_game_players = MultiPlayerGame::load_game_players( $gid );
      $arr_ratings = MultiPlayerGame::calc_average_group_ratings($arr_game_players, /*rat-upd*/true);

      if ( $game_type == GAMETYPE_TEAM_GO )
      {
         // NOTE: group-color can only be B|W, because games on SETUP-game-status are not processed
         $dbcol = ( $arr['GroupColor'] == GPCOL_B ) ? 'Black' : 'White';
         $key = ( $arr['GroupColor'] == GPCOL_B ) ? 'bRating' : 'wRating';
         $grp_rating = (float)$arr_ratings[$key];
         db_query( "admin_rating.notify_fix_running_games.run_mpg.team_go($gid)",
            "UPDATE Games SET {$dbcol}_Start_Rating=$grp_rating " .
            "WHERE ID=$gid AND Status ".IS_RUNNING_GAME." LIMIT 1" );
      }
      elseif ( $game_type == GAMETYPE_ZEN_GO )
      {
         $grp_rating = (float)$arr_ratings['bRating'];
         db_query( "admin_rating.notify_fix_running_games.run_mpg.zen_go($gid)",
            "UPDATE Games SET Black_Start_Rating=$grp_rating, White_Start_Rating=$grp_rating " .
            "WHERE ID=$gid AND Status ".IS_RUNNING_GAME." LIMIT 1" );
      }
      else
         continue; // something wrong, but don't throw error to avoid breaking "transaction"

      if ( mysql_affected_rows() == 1 ) // game could be finished in the meantime
      {
         foreach ( $arr_game_players as $gp )
            $uids[] = $gp->uid;
      }
   }//mpg


   // notify tournament-directors ----------

   $cnt_bulletins = 0;
   foreach ( $arr_tourneys as $tid )
   {
      $bulletin = new Bulletin( 0, $player_row['ID'], null, BULLETIN_CAT_ADMIN_MSG, BULLETIN_STATUS_NEW,
            BULLETIN_TRG_TD, BULLETIN_FLAG_ADMIN_CREATED, $NOW, $NOW + 30*SECS_PER_DAY, $tid, /*gid*/0,
            0, 'created automatically by admin_rating-script',
            sprintf( T_('Rating change of opponent [%s] in your running games and invitations'), $handle ),
            sprintf( T_('Hello,

the rating of the player <user %s> has been changed from %s to %s.
This player participates in the <tourney %s>.  As tournament-directors you have to handle this in any case you see fit.

Cheers,
DGS-Admin'), $uid, $old_rating_str, $new_rating_str, $tid )
         );
      if ( $bulletin->persist() )
         ++$cnt_bulletins;
   }


   // notify opponents of invitations ------

   foreach ( $arr_invitations as $arr ) // arr: gid/opp_uid/opp_handle/color => ..
      $uids[] = $arr['opp_uid'];


   // create bulletins

   $bulletin = new Bulletin( 0, $player_row['ID'], null, BULLETIN_CAT_ADMIN_MSG, BULLETIN_STATUS_NEW,
         BULLETIN_TRG_USERLIST, BULLETIN_FLAG_ADMIN_CREATED, $NOW, $NOW + 30*SECS_PER_DAY, /*tid*/0, /*gid*/0,
         0, 'created automatically by admin_rating-script',
         sprintf( T_('Rating change of opponent [%s] in your running games and invitations'), $handle ),
         sprintf( T_('Hello,

the rating of your opponent <user %s> has been changed from %s to %s.  All corresponding RATED non-tournament games have been changed to UNRATED to prevent your and your opponents rating to jump too much when the games end.  The start rating for those games have been recalculated for all running normal and multi-player games.  Invitations are not changed, so ensure you check invitations with that player for changed game settings that can be caused by the rating change.
Tournament games have to be handled by the respective tournament-directors which are informed about this rating change as well.

It\'s up to you if you want to continue your games under the changed pretext. You can resign the games without a rating-change (because the games are unrated now) or ask an admin for deletion if you can\'t do by yourself.

Cheers,
DGS-Admin'), $uid, $old_rating_str, $new_rating_str )
      );
   $bulletin->persist();
   $bid = $bulletin->ID;
   if ( $bid > 0 )
   {
      Bulletin::persist_bulletin_userlist( $bid, $uids );
      ++$cnt_bulletins;
   }

   return ( $cnt_bulletins > 0 );
}//notify_fix_running_games

?>

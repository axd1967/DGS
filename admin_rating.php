<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/gui_functions.php';
require_once 'include/rating.php';
require_once 'include/classlib_user.php';
require_once 'include/db/ratingchangeadmin.php';
require_once 'include/table_columns.php';

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
   $user = $upd_user = null;
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
   }


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
            admin_log( $my_id, $user->Handle, sprintf( "Change rating: %s", implode(', ', $diff) ) );
            change_user_rating( $uid, $changes, $upd_user['Rating'], $upd_user['RatingMin'], $upd_user['RatingMax'] );
         }
         ta_end();

         jump_to("admin_rating.php?uid=$uid".URI_AMP."sysmsg=".urlencode(T_('Rating changed!')));
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

      $rform->add_empty_row();
      $rform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'preview', T_('Preview'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'save', T_('Save changes'), ));

      $rform->add_empty_row();
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

   $iterator = new ListIterator( 'AdminRating', null, 'ORDER BY Created DESC' );
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


/* TODO TODO TODO
function find_running_normal_games( $uid )
{
   // handle tid, FK, B/W
   $result = db_query("admin_rating.find_running_normal_games($uid)",
      "SELECT ID AS gid, Status, tid, Black_ID, White_ID FROM Games WHERE GameType='".GAMETYPE_GO."' AND Status ".IS_STARTED_GAME." AND ( (Black_ID=$uid AND Black_Start_Rating > -".OUT_OF_RATING.") OR (White_ID=$uid AND White_Start_Rating > -".OUT_OF_RATING.") )" );

   //update Games set White_Start_Rating=-900 where GameType='GO' and White_ID=75273 and Status in ('play','pass','score','score2') and White_Start_Rating > -9999
   //update Games set Black_Start_Rating=-900 where GameType='GO' and Black_ID=75273 and Status in ('play','pass','score','score2') and Black_Start_Rating > -9999
}

function find_running_multi_player_games( $uid )
{
   $result = db_query("admin_rating.find_running_multi_player_games($uid)",
      "SELECT GP.gid, G.GameType, G.Status FROM GamePlayers AS GP INNER JOIN Games AS G ON G.ID=GP.gid WHERE GP.uid=$uid AND Status IN ('SETUP','PLAY','PASS','SCORE','SCORE2')" );

   //select avg(Rating2) from GamePlayers as GP inner join Players as P on P.ID=GP.uid where gid=784456 and GroupColor ='W'
   //update Games set  White_Start_Rating = -165.540034484907  where ID=784456 limit 1
}

function find_running_tournaments( $uid )
{
   $result = db_query("admin_rating.find_running_tournaments($uid)",
      "SELECT TP.tid, T.Status FROM TournamentParticipant AS TP INNER JOIN Tournament AS T ON T.ID=TP.tid WHERE TP.uid=$uid AND T.Status IN ()" );
}
*/

?>

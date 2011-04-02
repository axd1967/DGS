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

$TranslateGroups[] = "Admin";

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
   if( !$logged_in )
      error('not_logged_in');
   if( !(@$player_row['admin_level'] & ADMIN_GAME) )
      error('adminlevel_too_low');
   $my_id = $player_row['ID'];

   $page = "admin_rating.php";
   $title = T_('Admin editor of DGS-rating#rankadm');

/* Actual REQUEST calls used:
     (no args)          : ask for user
     uid=               : edit rank-info/DGS-rating of user
     save&uid=          : save changes: ratingtype, rating, reset_confival
*/

   $uid = (int)get_request_arg('uid');
   if( $uid <= GUESTS_ID_MAX ) $uid = 0;
   $arg_rating = trim(get_request_arg('rating'));
   $arg_ratingtype = get_request_arg('ratingtype', 'dragonrank');
   $do_reset_confidence = @$_REQUEST['reset_confival']; // reset rating confidence-interval

   // init
   $errors = array();
   $user = $upd_user = null;
   $changes = 0;
   if( $uid )
   {
      $user = User::load_user( $uid );
      if( is_null($user) )
         error('unknown_user', "admin_rating.find_user($uid)");
      $upd_user = array(
         'Rating'    => $user->Rating,
         'RatingMin' => $user->urow['RatingMin'],
         'RatingMax' => $user->urow['RatingMax'],
      );

      if( $user->RatingStatus != RATING_RATED )
         $errors[] = sprintf( T_('Rating can only be changed for rating-status [%s].#rankadm'), RATING_RATED );
      if( $uid == $my_id )
         $errors[] = T_('You can\'t change your own rating. Ask another admin.#rankadm');

      if( (string)$arg_rating != '' ) // update rating
      {
         $upd_user['Rating'] = convert_to_rating($arg_rating, $arg_ratingtype);
         if( abs($upd_user['Rating'] - $user->Rating) > 0.005 )
            $changes |= RCADM_CHANGE_RATING;
      }

      // prepare changes
      if( $do_reset_confidence ) // reset rating confidence-interval
      {
         $newrating = $upd_user['Rating'];
         $upd_user['RatingMin'] = $newrating - 200 - max(1600 - $newrating, 0) * 2 / 15;
         $upd_user['RatingMax'] = $newrating + 200 + max(1600 - $newrating, 0) * 2 / 15;
         $changes |= RCADM_RESET_CONFIDENCE;
      }

      if( !is_valid_rating($upd_user['Rating']) )
         $errors[] = sprintf( T_('Rating [%s] is invalid#rankadm'), $upd_user['Rating'] );
      if( $changes == 0 && (@$_REQUEST['preview'] || @$_REQUEST['save']) )
         $errors[] = T_('No rating-change or it is too small.#rankadm');

      $rcatable = load_old_rating_changes( $uid );
   }


   // ---------- Process actions ------------------------------------------------

   if( count($errors) == 0 )
   {
      if( @$_REQUEST['save'] && $changes > 0 )
      {
         $diff = array();
         $diff[] = sprintf( '%s[%s > %s]', 'Rating',    $user->Rating, $upd_user['Rating'] );
         $diff[] = sprintf( '%s[%s > %s]', 'RatingMin', $user->urow['RatingMin'], $upd_user['RatingMin'] );
         $diff[] = sprintf( '%s[%s > %s]', 'RatingMax', $user->urow['RatingMax'], $upd_user['RatingMax'] );
         $diff[] = 'reset-confidence=' . yesno($do_reset_confidence);

         ta_begin();
         {//HOT-SECTION for admin changing user-rating
            change_user_rating( $uid, $changes, $upd_user['Rating'], $upd_user['RatingMin'], $upd_user['RatingMax'] );
            admin_log( $my_id, $user->Handle, sprintf( "Change rating: %s", implode(', ', $diff) ) );
         }
         ta_end();

         jump_to("admin_rating.php?uid=$uid".URI_AMP."sysmsg=".urlencode(T_('Rating changed!#rankadm')));
      }
   }//actions


   // ---------- Rank Edit Form ----------------------------------------------

   $rform = new Form( 'adminrating', $page, FORM_GET );
   $rform->add_hidden('uid', $uid);

   if( $uid )
   {
      $rform->add_row( array( 'DESCRIPTION', T_('User#rankadm'),
                              'TEXT', $user->user_reference(), ));
      $rform->add_row( array( 'DESCRIPTION', T_('Rating status#rankadm'),
                              'TEXT', $user->RatingStatus, ));
      $rform->add_row( array( 'DESCRIPTION', T_('Current Rating#rankadm'),
                              'TEXT', echo_rating($user->Rating, true, $my_id, false ) ));
   }

   if( count($errors) )
   {
      $rform->add_row( array( 'HR' ));
      $rform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
   }
   $rform->add_row( array( 'HR' ));

   if( $uid && $user->RatingStatus == RATING_RATED )
   {
      $rform->add_row( array(
            'DESCRIPTION', T_('New Rating#rankadm'),
            'TEXTINPUT', 'rating', 16, 16, $arg_rating,
            'SELECTBOX', 'ratingtype', 1, getRatingTypes(), $arg_ratingtype, false, ));
      $rform->add_row( array(
            'TAB',
            'CHECKBOX', 'reset_confival', 1, T_('Reset Confidence interval#rankadm'),
                        (int)get_request_arg('reset_confival'), ));

      $rform->add_empty_row();
      $rform->add_row( array(
            'DESCRIPTION', T_('New Rating#rankadm'),
            'TEXT', echo_rating($upd_user['Rating'], true, 0, false), ));
      $rform->add_row( array(
            'DESCRIPTION', T_('Current Confidence Interval#rankadm'),
            'TEXT', sprintf( '%1.6f (%s) > %1.6f < (%s) %1.6f',
                             $user->urow['RatingMin'], diff( $user->Rating - $user->urow['RatingMin'] ),
                             $user->Rating,
                             diff( $user->urow['RatingMax'] - $user->Rating ), $user->urow['RatingMax'] ), ));
      $rform->add_row( array(
            'DESCRIPTION', T_('New Confidence Interval#rankadm'),
            'TEXT', sprintf( '%1.6f (%s) > %1.6f < (%s) %1.6f',
                             $upd_user['RatingMin'], diff( $upd_user['Rating'] - $upd_user['RatingMin'] ),
                             $upd_user['Rating'],
                             diff( $upd_user['RatingMax'] - $upd_user['Rating'] ), $upd_user['RatingMax'] ), ));
      $rform->add_row( array(
            'DESCRIPTION', T_('Delta Rating Change#rankadm'),
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

   if( !is_nulL($rcatable) )
   {
      section('old_ratingchanges', T_('Former rating-changes of user#rankadm'));
      $rcatable->echo_table();
   }


   $notes = array();
   $notes[] = T_("WARNING: No rating-updates on change of daylight-saving-time to avoid side-effects on rating-recalculations, because time of rating-changes and game-ends must be synchronized.#rankadm");
   $notes[] = null;
   $notes[] = sprintf( T_("Check notes about rating-change on page %s.#rankadm"), anchor("edit_rating.php", T_('Change rating & rank')) );
   $notes[] = T_("Rating-change is OK if ranking-diff >6k.#rankadm");
   $notes[] = T_("Reset of confidence-interval is OK if ranking-diff >3k and SHOULD always be used if rating is changed.#rankadm");
   echo_notes( 'adminratingnotes', T_('Important notes about Rating changes#rankadm'), $notes );

   $menu_array = array();
   $menu_array[T_('')] = "$page?uid=$uid";

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
   if( !$uid )
      return null;

   $rcatable = new Table( 'ratingchanges', $page, null, '',
      TABLE_NO_HIDE|TABLE_NO_SORT|TABLE_NO_SIZE|TABLE_ROWS_NAVI );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $rcatable->add_tablehead( 1, T_('Created#rankadm'), 'Date', 0, 'Created-');
   $rcatable->add_tablehead( 2, T_('Changes#rankadm'), 'Enum' );
   $rcatable->add_tablehead( 3, T_('Rating#rankadm'), 'Rating' );

   $iterator = new ListIterator( 'AdminRating', null, 'ORDER BY Created DESC' );
   $iterator = RatingChangeAdmin::load_ratingchangeadmin( $iterator, $uid );
   $rcatable->set_found_rows( $iterator->getItemCount() );

   while( list(,$arr_item) = $iterator->getListIterator() )
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

?>

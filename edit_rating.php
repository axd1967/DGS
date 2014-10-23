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

$TranslateGroups[] = "Users";

require_once 'include/std_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/form_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/rating.php';
require_once 'include/rank_converter.php';


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'edit_rating');

   $my_id = $player_row['ID'];
   $is_guest = ( $my_id <= GUESTS_ID_MAX );

/* Actual REQUEST calls used:
     (no args)          : edit rank-info/DGS-rating
     save               : save changes
     convert            : convert ranks
*/

   // save changes
   if ( @$_REQUEST['save'] )
   {
      if ( $is_guest ) // view ok, edit forbidden
         error('not_allowed_for_guest', 'edit_rating');

      // update RankInfo
      $upd_players = new UpdateQuery('Players');
      $upd_players->upd_txt('Rank', trim(get_request_arg('rank')) );
      $message = T_('Rank updated!');

      // update Rating
      $ratingtype = get_request_arg('ratingtype') ;
      $newrating = convert_to_rating(get_request_arg('rating'), $ratingtype, MAX_START_RATING);
      $oldrating = $player_row['Rating2'];
      if ( $player_row['RatingStatus'] != RATING_RATED
            && (is_numeric($newrating) && $newrating >= MIN_RATING)
            && ( $ratingtype != 'dragonrank'
               || !(is_numeric($oldrating) && $oldrating >= MIN_RATING)
               || abs($newrating - $oldrating) > 0.005 )
               || $player_row['RatingStatus'] == RATING_NONE )
      {
         update_player_rating( $my_id, $newrating, $upd_players );
         $message = T_('Rank & rating updated!');
      }
      else
         update_player_rating( $my_id, /*new-rating*/null, $upd_players );

      User::delete_cache_user_handle('edit_rating', $player_row['Handle']);

      jump_to("edit_rating.php?sysmsg=".urlencode($message));
   }

   $page = "edit_rating.php";
   $title = T_('Change DGS-rating and rank-info');


   // ---------- Rank Edit Form ----------------------------------------------

   $rform = new Form( 'editrating', $page, FORM_GET );

   $rform->add_row( array( 'DESCRIPTION', T_('User'),
                           'TEXT', user_reference( 0, 1, '', $player_row ), ));
   if ( $player_row['RatingStatus'] != RATING_RATED )
   {
      $rform->add_row( array(
            'DESCRIPTION', T_('Rating'),
            'TEXTINPUT', 'rating', 16, 16, echo_rating($player_row['Rating2'], 2, 0, true),
            'SELECTBOX', 'ratingtype', 1, getRatingTypes(), 'dragonrank', false, ));
   }
   else
   {
      $rform->add_row( array( 'DESCRIPTION', T_('Rating'),
                              'TEXT', echo_rating($player_row['Rating2'], true, $my_id, false ) ));
   }
   $rform->add_row( array( 'DESCRIPTION', T_('Rank info'),
                           'TEXTINPUT', 'rank', 32, 40, $player_row['Rank'], ));

   $rform->add_empty_row();
   $rform->add_row( array(
      'TAB', 'CELL', 1, '', // align submit-buttons
      'SUBMITBUTTON', 'save', T_('Save changes'), ));
   $rform->add_empty_row();
   $rform->add_row( array( 'HR' ));

   // ---------- Rank Converter Form -----------------------------------------

   $rcform = RankConverter::buildForm( $page, FORM_GET );

   // ---------- Main ----------

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $rform->echo_string();
   echo name_anchor('rankconv');
   $rcform->echo_string();


   $notes = array();
   $notes[] = array(
      T_("You need to <b>specify a 'Rating'</b> if you want to <b>play RATED games</b>#ranknotes"),
         T_("Choose your rating wisely, because this can normally not be changed after you started a rated game.#ranknotes"),
         T_("If you know your rank from another source, e.g. from another Go-Server or from an official rating-list, " .
            "you may use the Rank Converter to find an appropriate rank on DGS.#ranknotes"),
         T_("If you don't know your rating at all, you may ask for help in the forums. An absolute beginner can start " .
            "with a rank between 25k-30k.#ranknotes"),
         T_("We encourage you to trust the rating-system to adjust your rating. Playing many games helps in quickly " .
            "adjusting your rating to the proper level.#ranknotes"),
         T_("The average rating-diff is about 0.3k for a balanced game, so you need to win ca. 3-4 games with an evenly " .
            "matched player to increase your rating by 1k. However, this depends on the players confidence-interval, " .
            "strength and the used game-handicap-settings and can therefore vary, i.e. require more or less games.#ranknotes"),
      );
   $notes[] = T_("<b>'Rank info'</b> is only an informal field, which can be used to refer to other rank-systems, " .
                 "e.g. KGS, EGF, 3d pro, etc.#ranknotes");
   $notes[] = array(
      T_("<b>Changing an established Rating</b> can only be done on certain conditions#ranknotes"),
         T_("Keep in mind, that playing many games letting the rating-system adjust your rating is the preferred way.#ranknotes"),
         T_("A rating reset/change can normally be asked once a year.#ranknotes"),
         sprintf( T_("If the ranking-diff is >%s, you may ask for a reset of your rating confidence level to let the rating-system adjust faster.#ranknotes"), '3k' ),
         sprintf( T_("If the ranking-diff is >%s, you may ask for a rating-change.#ranknotes"), '5k' ),
         sprintf(
            T_("Add a request in the Support-forum with subject \"%s\". A game-admin will then check and process your application.<br>\n" .
               "Please provide infos about: your target rank, sources that verify your claim preferrably with matching user-IDs, " .
               "e.g. other go-server, official rating-lists, recommendations from a player/teacher higher ranked than yourself. " .
               "For ranks or ratings from other servers, please first use the rank-converter above.#ranknotes"),
            T_('Request for rating-change#ranknotes') ),
      );
   echo_notes( 'editratingnotes', T_('Important notes about Rating & Rank changes'), $notes );

   end_page();
}
?>

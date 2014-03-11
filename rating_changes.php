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

require_once 'include/classlib_user.php';
require_once 'include/form_functions.php';
require_once 'include/game_functions.php';
require_once 'include/rating.php';
require_once 'include/std_functions.php';
require_once 'include/table_infos.php';


{
   connect2mysql();
   $logged_in = who_is_logged($player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'rating_change');

   $page = "rating_changes.php";
   $title = T_('Show rating changes');

/* Actual REQUEST calls used:
     (no args)                            : ask for users
     show=1&b=&w=&size=&handicap=&komi=   : show rating-changes for B/W for selected board-size/handi/komi
     switch=1&b=&w=                       : switch colors
*/

   $b_id = get_request_arg('b');
   $w_id = get_request_arg('w');
   $board_size = (int)get_request_arg('size', 19);
   $handicap = (int)get_request_arg('handicap', 0);
   $komi = (float)get_request_arg('komi', DEFAULT_KOMI);
   if ( @$_REQUEST['switch'] )
      swap( $b_id, $w_id );

   $errors = array();
   $data_row = load_data_rating_changes( $b_id, $w_id, $errors );


   $rform = new Form( 'rating_changes', $page, FORM_GET, false );
   $rform->set_config( FEC_EXTERNAL_FORM, true );
   $itable = new Table_info('RatingChg');

   if ( count($errors) )
   {
      $rform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString( T_('There are some errors'), $errors ) ));
      $rform->add_empty_row();
   }

   if ( $data_row )
   {
      $data_row['Size'] = $board_size;
      $data_row['Handicap'] = $handicap;
      $data_row['Komi'] = $komi;
      fill_user_rating_changes( $data_row, $rform, $itable, $b_id, $w_id );
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo "<center>\n",
      $rform->print_start_default(),
      $rform->get_form_string(),
      $itable->make_table(),
      $rform->print_end(),
      "</center>\n<br><br>\n";

   end_page();
}//main


// treat user (uid) as BLACK, opponent (oid) as WHITE
function load_data_rating_changes( $b_id, $w_id, &$errors )
{
   $user_b = $user_w = null;

   if ( (string)$b_id != '' )
   {
      $user_b = User::load_cache_user_by_handle( "rating_changes.load_data_rating_changes.load_b", $b_id );
      if ( is_null($user_b) )
         $errors[] = sprintf( T_('Can\'t find user with userid [%s]#ratchg'), $b_id );
   }
   else
      $errors[] = T_('Missing userid for black player#ratchg');

   if ( (string)$w_id != '' )
   {
      $user_w = User::load_cache_user_by_handle( "rating_changes.load_data_rating_changes.load_w", $w_id );
      if ( is_null($user_w) )
         $errors[] = sprintf( T_('Can\'t find user with userid [%s]#ratchg'), $w_id );
   }
   else
      $errors[] = T_('Missing userid for white player#ratchg');

   $out = array(
         // additional data needed for rating-change calculations:
         'tid' => 0, // no tournament
         'GameType' => GAMETYPE_GO, // no multi-player-game
         'Rated' => 'Y', // assume rated-game
         'Moves' => DELETE_LIMIT + 50, // keep game rated
      );

   if ( $user_b )
   {
      $out = $out + array(
         BLACK => $user_b,
         'Black_ID' => $user_b->ID,
         'Black_Start_Rating' => $user_b->Rating,
         'bRating' => $user_b->Rating,
         'bRatingStatus' => $user_b->RatingStatus,
         'bRatingMin' => $user_b->urow['RatingMin'],
         'bRatingMax' => $user_b->urow['RatingMax'],
         );
   }
   if ( $user_w )
   {
      $out = $out + array(
         WHITE => $user_w,
         'White_ID' => $user_w->ID,
         'White_Start_Rating' => $user_w->Rating,
         'wRating' => $user_w->Rating,
         'wRatingStatus' => $user_w->RatingStatus,
         'wRatingMin' => $user_w->urow['RatingMin'],
         'wRatingMax' => $user_w->urow['RatingMax'],
         );
   }

   return $out;
}//load_data_rating_changes

function fill_user_rating_changes( $data, &$rform, &$itable, $b_id, $w_id )
{
   $ok = ( isset($data[BLACK]) && isset($data[WHITE]) );
   $userb = @$data[BLACK];
   $userw = @$data[WHITE];
   $fmt_elo = '%1.2f';
   $has_b_rating = ($userb ? $userb->hasRating() : false);
   $has_w_rating = ($userw ? $userw->hasRating() : false);

   $itable->add_caption( T_('User info') );
   $itable->add_sinfo( '', array( T_('Black'), T_('White'), T_('Comments') ), 'class=bold' );
   $itable->add_sinfo( T_('Player'), array(
         ($userb ? @$userb->user_reference() : NO_VALUE),
         ($userw ? @$userw->user_reference() : NO_VALUE),
         '' ) );
   $itable->add_sinfo( T_('Userid'), array(
         $rform->print_insert_text_input('b', 16, 16, $b_id ),
         $rform->print_insert_text_input('w', 16, 16, $w_id ),
         $rform->print_insert_submit_button( 'switch', T_('Switch colors#ratchg') ) ) );
   $itable->add_sinfo( T_('Rating Status'), array(
         ($userb ? @$userb->RatingStatus : NO_VALUE),
         ($userw ? @$userw->RatingStatus : NO_VALUE),
         '' ) );
   $itable->add_sinfo( T_('Rating'), array(
         ($has_b_rating ? echo_rating(@$userb->Rating, true, $userb->ID, false) : NO_VALUE),
         ($has_w_rating ? echo_rating(@$userw->Rating, true, $userw->ID, false) : NO_VALUE),
         '' ) );
   $itable->add_sinfo( T_('ELO Rating#ratchg'), array(
         ($has_b_rating ? sprintf($fmt_elo, @$userb->Rating) : NO_VALUE),
         ($has_w_rating ? sprintf($fmt_elo, @$userw->Rating) : NO_VALUE),
         ($has_b_rating && $has_w_rating
            ? T_('DIFF#ratchg') . ': ' . sprintf($fmt_elo, abs(@$userb->Rating - @$userw->Rating))
            : '' )) );
   $itable->add_sinfo( T_('ELO Rating Min - Max#ratchg'), array(
         ($has_b_rating ? sprintf($fmt_elo, @$userb->urow['RatingMin']) : NO_VALUE) . ' - ' .
            ($has_b_rating ? sprintf($fmt_elo, @$userb->urow['RatingMax']) : NO_VALUE),
         ($has_w_rating ? sprintf($fmt_elo, @$userw->urow['RatingMin']) : NO_VALUE) . ' - ' .
            ($has_w_rating ? sprintf($fmt_elo, @$userw->urow['RatingMax']) : NO_VALUE),
         T_('Confidence interval') ) );

   $size_value_arr = array_value_to_key_and_value( range( MIN_BOARD_SIZE, MAX_BOARD_SIZE ));
   $handi_stones = build_arr_handicap_stones( /*def*/false );
   $game_settings = new GameSettings( $data['Size'], RULESET_JAPANESE,  0, 0, MAX_HANDICAP,  0, JIGOMODE_KEEP_KOMI );

   $itable->add_caption( T_('Game settings') );
   $itable->add_sinfo( T_('Board Size'), array(
         array( $rform->print_insert_select_box('size', 1, $size_value_arr, $data['Size'] ), 'colspan=2' ),
         '' ) );
   $itable->add_sinfo( T_('Handicap'), array(
         array( $rform->print_insert_select_box('handicap', 1, $handi_stones, $data['Handicap'] ), 'colspan=2' ),
         '' ) );
   $itable->add_sinfo( T_('Komi'), array(
         array( $rform->print_insert_text_input('komi', 5, 5, $data['Komi'] ), 'colspan=2' ),
         T_('range -200..200#ratchg') ) );
   $itable->add_sinfo( '', array(
         array( $rform->print_insert_submit_button( 'show', T_('Show rating changes') ), 'colspan=2' ),
         '' ) );

   if ( $ok )
   {
      list( $calc_handi, $calc_komi, $iamblack, $is_nigiri ) =
         $game_settings->suggest_conventional( $userb->Rating, $userw->Rating );
      $itable->add_sinfo( T_('Conventional handicap'), array(
            sprintf( T_('Handicap: %d#ratchg'), $calc_handi),
            sprintf( T_('Komi: %1.1f#ratchg'), $calc_komi ),
            T_('depends on board size') ) );
      list( $calc_handi, $calc_komi, $iamblack, $is_nigiri ) =
         $game_settings->suggest_proper( $userb->Rating, $userw->Rating );
      $itable->add_sinfo( T_('Proper handicap'), array(
            sprintf( T_('Handicap: %d#ratchg'), $calc_handi),
            sprintf( T_('Komi: %1.1f#ratchg'), $calc_komi ),
            T_('depends on board size') ) );

      if ( $has_b_rating && $has_w_rating )
      {
         list( $b_ratdiff_won, $w_ratdiff_lost ) = calculate_rating_change_prediction( $data, -1 ); // black won
         list( $b_ratdiff_jigo, $w_ratdiff_jigo ) = calculate_rating_change_prediction( $data, 0 ); // jigo
         list( $b_ratdiff_lost, $w_ratdiff_won ) = calculate_rating_change_prediction( $data, 1 ); // black lost

         $itable->add_scaption( T_('Rating changes (ELO)') );
         $diff_note = T_('100 ELO points = 1 kyu#ratchg');
         $itable->add_sinfo( T_('Rating diff on Win') . MED_SPACING
            . image('images/yes.gif', T_('Yes'), null, 'class=InTextImage'),
            array( rdiff($b_ratdiff_won, $fmt_elo), rdiff($w_ratdiff_won, $fmt_elo), $diff_note ) );
         $itable->add_sinfo( T_('Rating diff on Jigo') . MED_SPACING
            . image('images/dash.gif', T_('Jigo'), null, 'class=InTextImage'),
            array( rdiff($b_ratdiff_jigo, $fmt_elo), rdiff($w_ratdiff_jigo, $fmt_elo), $diff_note ) );
         $itable->add_sinfo( T_('Rating diff on Loss') . MED_SPACING
            . image('images/no.gif', T_('No'), null, 'class=InTextImage'),
            array( rdiff($b_ratdiff_lost, $fmt_elo), rdiff($w_ratdiff_lost, $fmt_elo), $diff_note ) );
      }
   }
}//fill_user_rating_changes

function calculate_rating_change_prediction( $data, $score )
{
   $data['bRatingStatus'] = $data['wRatingStatus'] = RATING_RATED; // always assume rated user
   $data['Score'] = $score; // <0 = B won, >0 B lost, =0 jigo
   list( $tmp, $result ) = update_rating2( /*gid*/0, /*chk*/false, /*simul*/true, $data );
   return array( $result['bRating'] - $data['bRating'], $result['wRating'] - $data['wRating'] );
}//calculate_rating_change_prediction

function rdiff( $diff, $fmt )
{
   $diff = sprintf( $fmt, $diff );
   return ($diff < 0) ? $diff : ($diff > 0 ? '+'.$diff : 0 );
}

?>

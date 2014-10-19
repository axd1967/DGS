<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Messages";

require_once 'include/std_classes.php';
require_once 'include/gui_functions.php';
require_once 'include/table_infos.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/db/game_invitation.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/utilities.php';
require_once 'include/error_codes.php';
require_once 'include/shape_control.php';
require_once 'include/make_game.php';
require_once 'include/dgs_cache.php';
require_once 'include/classlib_goban.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/classlib_user.php';


// game-settings form-/table-style defs
define('GSET_WAITINGROOM', 'waitingroom');
define('GSET_TOURNAMENT_LADDER', 'tournament_ladder');
define('GSET_TOURNAMENT_ROUNDROBIN', 'tournament_roundrobin');
define('GSET_MSG_INVITE',  'invite');
define('GSET_MSG_DISPUTE', 'dispute');
define('CHECK_GSET', 'waitingroom|tournament_ladder|tournament_roundrobin|invite|dispute');

define('FOLDER_COLS_MODULO', 8); //number of columns of "tab" layouts

define('MAX_MSG_RECEIVERS', 16); // oriented at max. for multi-player-game



function init_standard_folders()
{
   global $STANDARD_FOLDERS;

   if ( !isset($STANDARD_FOLDERS[FOLDER_NEW]) )
   {
      $STANDARD_FOLDERS = array(  // arr=( Name, BGColor, FGColor ); $bg_color value (#f7f5e3)
         //FOLDER_DESTROYED => array(T_//('Destroyed'), 'ff88ee00', '000000'), // non-visible folder!!
         FOLDER_ALL_RECEIVED => array(T_('All Received'),'00000000','000000'), // pseudo-folder (grouping other folders)
         FOLDER_MAIN => array(T_('Main'), '00000000', '000000'),
         FOLDER_NEW => array(T_('New'), 'aaffaa90', '000000'),
         FOLDER_REPLY => array(T_('Reply!'), 'ffaaaa80', '000000'),
         FOLDER_DELETED => array(T_('Trashcan'), 'ff88ee00', '000000'),
         FOLDER_SENT => array(T_('Sent'), '00000000', '0000ff'),
      );
   }
}


/*!
 * \brief Prints game setting form for some pages.
 * \param $formstyle:
 *     GSET_MSG_INVITE | GSET_MSG_DISPUTE = for message.php
 *     GSET_WAITINGROOM = for waiting_room.php / new_game.php
 *     GSET_TOURNAMENT_LADDER, GSET_TOURNAMENT_ROUNDROBIN = for tournaments/edit_rules.php
 * \param $viewmode:
 *     GSETVIEW_STANDARD = standard game, view with all possible settings (no fair-komi/multi-player-stuff); auto for tourney
 *     GSETVIEW_FAIRKOMI = fair-komi view with restricted settings (even, no handicap, no adjustments, normal type)
 *     GSETVIEW_MPGAME = view with settings allowed for multi-player-game; auto for MP-game-type
 * \param $my_ID user-id for invite/dispute, then $gid is game-id;
 *     my_ID='redraw' for invite/dispute/tourney and $gid then is the $_POST[] of the form asking preview
 * \param $arr_url_or_gid if null, shape + snapshot args are read from $_REQUEST (normally used for Invite and NewGame);
 *     can be game-id or array with URL-args, e.g. for tournament-rules (see also $my_ID argument)
 * \param $map_ratings:
 *     if set, contain map with keys (rating1, rating2) ->
 *     then add probable game-settings for conventional/proper-handicap-type
 * \param $gsc GameSetupChecker-object containing error-fields to highlight; or NULL
 * \return true if settings would be allowed; false if they would not be allowed
 *     (e.g. unrated player using calulcated handicap-type conventional or proper).
 */
function game_settings_form(&$mform, $formstyle, $viewmode, $iamrated=true, $my_ID=NULL, $arr_url_or_gid=NULL,
      $map_ratings=NULL, $gsc=NULL )
{
   if ( !preg_match( "/^(".CHECK_GSET.")$/", $formstyle ) )
      $formstyle = GSET_MSG_INVITE;
   if ( !preg_match( "/^(".CHECK_GSETVIEW.")$/", $viewmode ) )
      $viewmode = GSETVIEW_STANDARD;
   if ( $viewmode == GSETVIEW_MPGAME && $formstyle != GSET_WAITINGROOM )
      $viewmode = GSETVIEW_STANDARD;

   $gid = ( is_null($arr_url_or_gid) ) ? 0 : (int)$arr_url_or_gid;

   if ( is_null($gsc) )
      $gsc = new GameSetupChecker( $viewmode );

   $is_fstyle_tourney = ( $formstyle == GSET_TOURNAMENT_LADDER || $formstyle == GSET_TOURNAMENT_ROUNDROBIN );
   $is_fstyle_invite = ( $formstyle == GSET_MSG_INVITE || $formstyle == GSET_MSG_DISPUTE );
   if ( $is_fstyle_tourney )
      $viewmode = GSETVIEW_STANDARD;
   $is_view_mpgame = ( $viewmode == GSETVIEW_MPGAME );
   $is_view_fairkomi = ( $viewmode == GSETVIEW_FAIRKOMI );

   $allowed = true;
   $shape_init = true;
   $shape_id = ( $gid == 0 ) ? (int)@$_REQUEST['shape'] : 0;

   // Default values: for invite/waitingroom/tournament (dispute comes from DB)
   $ShapeID = $orig_shape_id = 0;
   $ShapeSnapshot = '';
   $Size = 19;
   $Handitype = ( $iamrated && $shape_id <= 0 ) ? HTYPE_CONV : HTYPE_NIGIRI;
   $Color_m = HTYPE_NIGIRI; // always my-color of current-user (also for dispute)
   $CategoryHandiType = get_category_handicaptype( $Handitype );
   $Ruleset = Ruleset::get_default_ruleset();
   $Handicap_m = 0;
   $Komi_m = Ruleset::getRulesetDefaultKomi( $Ruleset );
   $AdjKomi = 0.0;
   $JigoMode = JIGOMODE_KEEP_KOMI;
   $AdjHandicap = 0;
   $MinHandicap = 0;
   $MaxHandicap = DEFAULT_MAX_HANDICAP;
   $GamePlayers = '';
   $Maintime = 30;
   $MaintimeUnit = 'days';
   // NOTE: take note, that '36 hours' eval to '2d + 6h' because of sleeping time
   $Byotype = BYOTYPE_FISCHER;
   $Byotime_jap = 1;
   $ByotimeUnit_jap = 'days';
   $Byoperiods_jap = 10;
   $Byotime_can = 15;
   $ByotimeUnit_can = 'days';
   $Byoperiods_can = 15;
   $Byotime_fis = 1;
   $ByotimeUnit_fis = 'days';
   $WeekendClock = true;
   $StdHandicap = true;
   $Rated = true;

   if ( $is_view_mpgame ) // defaults for MP-game
   {
      $GamePlayers = '2:2'; //rengo
      $Handitype = HTYPE_NIGIRI;
      $CategoryHandiType = CAT_HTYPE_MANUAL;
      $Rated = false;
   }
   if ( $shape_id > 0 ) // handle shape-game
   {
      $ShapeID = $shape_id;
      $ShapeSnapshot = @$_REQUEST['snapshot'];
   }

   if ( $my_ID==='redraw' && is_array($arr_url_or_gid) )
   {
      // If 'redraw', use values from array $arr_url_or_gid,
      // which is the $_POST[] of the form asking the preview (i.e. this form)
      $url = $arr_url_or_gid;

      if ( isset($url['shape']) )
      {
         $orig_shape_id = trim($url['shape']);
         $ShapeID = (int)$url['shape'];
         if ( $ShapeID > 0 && isset($url['snapshot']) )
            $ShapeSnapshot = $url['snapshot'];
      }

      if ( isset($url['ruleset']) )
         $Ruleset = $url['ruleset'];
      if ( isset($url['size']) )
         $Size = (int)$url['size'];

      if ( isset($url['cat_htype']) )
         $CategoryHandiType = (string)$url['cat_htype'];
      if ( isset($url['color_m']) )
         $Color_m = $url['color_m'];

      if ( $CategoryHandiType === CAT_HTYPE_MANUAL )
         $Handitype = $Color_m;
      elseif ( $CategoryHandiType === CAT_HTYPE_FAIR_KOMI )
         $Handitype = ( isset($url['fk_htype']) ) ? $url['fk_htype'] : DEFAULT_HTYPE_FAIRKOMI;
      else
         $Handitype = $CategoryHandiType;

      if ( isset($url['handicap_m']) )
         $Handicap_m = (int)$url['handicap_m'];
      if ( isset($url['komi_m']) )
         $Komi_m = (float)$url['komi_m'];

      if ( isset($url['adj_komi']) )
         $AdjKomi = (float)$url['adj_komi'];
      if ( isset($url['jigo_mode']) )
         $JigoMode = $url['jigo_mode'];

      if ( isset($url['adj_handicap']) )
         $AdjHandicap = (int)$url['adj_handicap'];
      if ( isset($url['min_handicap']) )
         $MinHandicap = (int)$url['min_handicap'];
      if ( isset($url['max_handicap']) )
         $MaxHandicap = DefaultMaxHandicap::limit_max_handicap( (int)$url['max_handicap'] );

      if ( isset($url['game_players']) )
         $GamePlayers = $url['game_players'];

      // NOTE on time-hours: 36 hours eval to 2d + 6h (because of sleeping time)

      if ( isset($url['byoyomitype']) )
         $Byotype = $url['byoyomitype'];

      if ( isset($url['timevalue']) )
         $Maintime = (int)$url['timevalue'];
      if ( isset($url['timeunit']) )
         $MaintimeUnit = (string)$url['timeunit'];

      if ( isset($url['byotimevalue_jap']) )
         $Byotime_jap = (int)$url['byotimevalue_jap'];
      if ( isset($url['timeunit_jap']) )
         $ByotimeUnit_jap = (string)$url['timeunit_jap'];
      if ( isset($url['byoperiods_jap']) )
         $Byoperiods_jap = (int)$url['byoperiods_jap'];

      if ( isset($url['byotimevalue_can']) )
         $Byotime_can = (int)$url['byotimevalue_can'];
      if ( isset($url['timeunit_can']) )
         $ByotimeUnit_can = (string)$url['timeunit_can'];
      if ( isset($url['byoperiods_can']) )
         $Byoperiods_can = (int)$url['byoperiods_can'];

      if ( isset($url['byotimevalue_fis']) )
         $Byotime_fis = (int)$url['byotimevalue_fis'];
      if ( isset($url['timeunit_fis']) )
         $ByotimeUnit_fis = $url['timeunit_fis'];

      $WeekendClock = ( @$url['weekendclock'] == 'Y' );
      $StdHandicap = ( @$url['stdhandicap'] == 'Y' );
      $Rated = ( @$url['rated'] == 'Y' );
   }
   else if ( $gid > 0 && $my_ID > 0 ) //'Dispute'
   {
      // If dispute, use values from Games-table
      $query = "SELECT Black_ID,White_ID, Ruleset,Size,Komi,Handicap,ToMove_ID," .
                 "Maintime,Byotype,Byotime,Byoperiods," .
                 "Rated,StdHandicap,WeekendClock, ShapeID,ShapeSnapshot, GameSetup " .
                 "FROM Games WHERE ID=$gid AND Status='".GAME_STATUS_INVITED."' LIMIT 1" ;
      $game_row = mysql_single_fetch( "game_settings_form($gid)", $query );
      if ( !$game_row )
         error('unknown_game', "game_settings_form($gid)");
      $black_id = (int)$game_row['Black_ID'];
      if ( $black_id != $my_ID && (int)$game_row['White_ID'] != $my_ID )
         error('wrong_dispute_game', "game_settings_form.find_gameinv.check.dispute($gid,$my_ID)");

      $game_inv = GameInvitation::load_game_invitation( $gid, $game_row['ToMove_ID'] );
      if ( is_null($game_inv) )
         error('invite_bad_gamesetup', "game_settings_form.dispute.miss_game_inv($gid,{$game_row['ToMove_ID']})");
      $inv_gs = GameSetup::new_from_game_invitation( $game_row, $game_inv );


      // shape-game
      $ShapeID = (int)$game_row['ShapeID'];
      $ShapeSnapshot = $game_row['ShapeSnapshot'];
      $shape_init = false;

      // overwrite game-row fields with user-specific invitation-game-setup
      // - fields from sender (Games.ToMove_ID) of GameInvitation already copied by make_invite_game() into Games-fields
      $Ruleset = $game_row['Ruleset'];
      $Size = (int)$game_row['Size'];
      $Rated = ( $game_row['Rated'] == 'Y' );
      $StdHandicap = ( $game_row['StdHandicap'] == 'Y' );
      $WeekendClock = ( $game_row['WeekendClock'] == 'Y' );
      $Handicap_m = (int)$game_row['Handicap'];
      // - fields not stored in Games-entry
      $Handitype = $inv_gs->get_user_view_handicaptype( $my_ID );
      $AdjKomi = $inv_gs->AdjKomi;
      $JigoMode = $inv_gs->JigoMode;
      $AdjHandicap = $inv_gs->AdjHandicap;
      $MinHandicap = $inv_gs->MinHandicap;
      $MaxHandicap = $inv_gs->MaxHandicap;

      $CategoryHandiType = get_category_handicaptype( $Handitype );
      $Color_m = ( $CategoryHandiType == CAT_HTYPE_MANUAL ) ? $Handitype : HTYPE_NIGIRI;
      $Komi_m = ( $CategoryHandiType == CAT_HTYPE_MANUAL )
         ? (float)$game_row['Komi']
         : Ruleset::getRulesetDefaultKomi($Ruleset);


      $MaintimeUnit = 'hours';
      $Maintime = $game_row['Maintime'];
      time_convert_to_longer_unit($Maintime, $MaintimeUnit);

      $game_row['ByotimeUnit'] = 'hours';
      time_convert_to_longer_unit($game_row['Byotime'], $game_row['ByotimeUnit']);

      $Byotype = $game_row['Byotype'];
      switch ( (string)$Byotype )
      {
         case BYOTYPE_JAPANESE:
            $Byotime_jap = $game_row['Byotime'];
            $ByotimeUnit_jap = $game_row['ByotimeUnit'];
            $Byoperiods_jap = $game_row['Byoperiods'];
            break;

         case BYOTYPE_CANADIAN:
            $Byotime_can = $game_row['Byotime'];
            $ByotimeUnit_can = $game_row['ByotimeUnit'];
            $Byoperiods_can = $game_row['Byoperiods'];
            break;

         default: //case BYOTYPE_FISCHER:
            $Byotype = BYOTYPE_FISCHER;
            $Byotime_fis = $game_row['Byotime'];
            $ByotimeUnit_fis = $game_row['ByotimeUnit'];
            break;
      }
   } //collecting datas

   if ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $Ruleset ) )
      error('feature_disabled', "game_settings_form.disabled.ruleset($Ruleset)");

   // handle shape-game implicit settings (ShapeID unset if invalid shape used)
   if ( $ShapeID > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($ShapeSnapshot);
      if ( is_array($arr_shape) ) // overwrite with defaults
      {
         $ShapeBlackFirst = (bool)@$arr_shape['PlayColorB'];
         if ( $shape_init )
         {
            $Size = (int)$arr_shape['Size'];
            $StdHandicap = false;
            $Rated = false;
         }
      }
      else // invalid snapshot
      {
         $ShapeID = 0;
         $ShapeSnapshot = '';
      }
   }


   // Draw game-settings form

   $mform->add_hidden('view', $viewmode);
   $mform->add_hidden('gsc', 1); // signal for game-setup-checker

   // shape-game
   if ( $is_fstyle_tourney )
   {
      $mform->add_row( array(
            'DESCRIPTION', T_('Shape-Game ID'),
            'TEXTINPUT', 'shape', 5, 10, $orig_shape_id, ));
   }
   if ( $ShapeID && $ShapeSnapshot )
   {
      if ( !$is_fstyle_tourney )
         $mform->add_hidden( 'shape', $ShapeID );
      $mform->add_hidden( 'snapshot', $ShapeSnapshot );

      $mform->add_row( array(
            'DESCRIPTION', T_('Shape Game'),
            'TEXT', ShapeControl::build_snapshot_info( $ShapeID, $Size, $ShapeSnapshot, $ShapeBlackFirst ), ));
      $mform->add_empty_row();
   }

   if ( $formstyle == GSET_WAITINGROOM && !$is_view_mpgame )
   {
      $maxGamesCheck = new MaxGamesCheck();
      $max_games = $maxGamesCheck->get_allowed_games(NEWGAME_MAX_GAMES);
      $numGames = limit( (int)get_request_arg('nrGames'), 1, $max_games, 1 );

      $vals = array_value_to_key_and_value( range(1, $max_games) );
      $mform->add_row( array( 'DESCRIPTION', T_('Number of games to add'),
                              'SELECTBOX', 'nrGames', 1, $vals, $numGames, false ) );
      $mform->add_row( array( 'SPACE' ) );
   }

   $arr_rulesets = Ruleset::getRulesetText(); // non-empty
   $arr = array( 'DESCRIPTION', T_('Ruleset') );
   if ( count($arr_rulesets) > 1 )
   {
      // NOTE: need also build_game_settings_javascript() in start_page()-call on respective-page!
      $attb_arr = ( is_javascript_enabled() ) ? array( 'onchange' => 'updateRulesetDefaulKomi(this)' ) : array();
      array_push( $arr, 'SELECTBOXX', 'ruleset', 1, $arr_rulesets, $Ruleset, false, $attb_arr );
   }
   else // one static ruleset
   {
      $ruleset_vals = array_values($arr_rulesets);
      array_push( $arr, 'TEXT', $ruleset_vals[0] );
   }
   array_push( $arr, 'TEXT', SMALL_SPACING . T_('use default komi') . ' ' . span('id="RulesetDefKomi"', $Komi_m) );
   $mform->add_row( $arr );

   $value_array = array_value_to_key_and_value( range( MIN_BOARD_SIZE, MAX_BOARD_SIZE ));
   $attb_arr = array( 'disabled' => $ShapeID );
   if ( is_javascript_enabled() )
      $attb_arr['onchange'] = 'updateDefaultMaxHandicap(this)';
   $mform->add_row( array( 'DESCRIPTION', T_('Board Size'),
                           'SELECTBOXX', 'size', 1, $value_array, $Size, false, $attb_arr ));

   $mform->add_row( array( 'SPACE' ) );

   // Conventional & Proper handicap
   $text_need_rating = '(' . T_('needs a user-rating to be enabled') . ')';
   if ( !$is_view_mpgame && !$is_view_fairkomi )
   {
      if ( $formstyle == GSET_MSG_DISPUTE && is_htype_calculated($Handitype) && !$iamrated ) // user-unrated
      {
         $Handitype = HTYPE_NIGIRI; // fallback to default
         $CategoryHandiType = get_category_handicaptype( $Handitype );
         $allowed = false;
      }

      $sugg_conv = $sugg_prop = '';
      if ( $iamrated && is_array($map_ratings) ) // user has a rating
      {
         $game_settings = new GameSettings( $Size, $Ruleset, $AdjHandicap, $MinHandicap, $MaxHandicap,
            $AdjKomi, $JigoMode );

         $r1 = $map_ratings['rating1'];
         $r2 = $map_ratings['rating2'];
         $arr_conv_sugg = $game_settings->suggest_conventional( $r1, $r2 );
         $arr_prop_sugg = $game_settings->suggest_proper( $r1, $r2 );
         $sugg_conv = span('Suggestion', sptext( build_suggestion_shortinfo($arr_conv_sugg) ));
         $sugg_prop = span('Suggestion', sptext( build_suggestion_shortinfo($arr_prop_sugg) ));
      }
      elseif ( !$iamrated )
         $sugg_conv = $sugg_prop = span('WarnMsg', $text_need_rating);

      $calc_htype_disabled = ( $iamrated && $ShapeID <= 0 ) ? '' : 'disabled=1';
      $mform->add_row( array(
            'DESCRIPTION', T_('Conventional handicap (komi 0.5 if not even)'),
            'RADIOBUTTONSX', 'cat_htype', array( CAT_HTYPE_CONV => '' ), $CategoryHandiType, $calc_htype_disabled,
            'TEXT', $sugg_conv ));
      $mform->add_row( array(
            'DESCRIPTION', T_('Proper handicap (komi adjusted by system)'),
            'RADIOBUTTONSX', 'cat_htype', array( CAT_HTYPE_PROPER => '' ), $CategoryHandiType, $calc_htype_disabled,
            'TEXT', $sugg_prop ));
   }//conv/proper-HType

   // Manual game: nigiri, double, black, white
   $handi_stones = build_arr_handicap_stones( /*def*/false );
   if ( !$is_view_mpgame && !$is_view_fairkomi )
   {
      $color_arr = GameTexts::get_manual_handicap_types();
      if ( ( $formstyle == GSET_TOURNAMENT_LADDER ) || $is_view_mpgame )
         unset($color_arr[HTYPE_DOUBLE]);

      if ( $formstyle == GSET_TOURNAMENT_LADDER )
         $color_txt = T_('Color Challenger#T_ladder');
      elseif ( $formstyle == GSET_TOURNAMENT_ROUNDROBIN )
         $color_txt = T_('Color Stronger#T_RRobin');
      else
         $color_txt = T_('My Color');

      $attb_arr = ( is_javascript_enabled() ) ? array( 'onclick' => 'this.form.cat_htype_manual.checked=true;' ) : array();
      $mform->add_row( array(
         'DESCRIPTION', T_('Manual setting (even or handicap game)'),
         'RADIOBUTTONSX', 'cat_htype', array( CAT_HTYPE_MANUAL => '' ), $CategoryHandiType, 'id="cat_htype_manual"',
         'TEXT', sptext($color_txt),
         'SELECTBOXX', 'color_m', 1, $color_arr, $Color_m, false, $attb_arr,
         'TEXT', sptext(T_('Handicap'),1),
         'SELECTBOXX', 'handicap_m', 1, $handi_stones, $Handicap_m, false, $attb_arr,
         'TEXT', sptext(T_('Komi'),1),
         'TEXTINPUTX', 'komi_m', 5, 5, $Komi_m,
            $attb_arr + array( 'id' => 'GSF_komi_m '. $gsc->get_class_error_field('komi_m') ), // 'id' for javascript-update
         ));
   }//manual HType


   // Fair-komi
   if ( $is_view_fairkomi || $is_fstyle_invite )
   {
      $row_arr = array( 'DESCRIPTION', T_('Fair Komi (even game)') );
      $attb_arr = array();
      if ( $is_view_fairkomi )
         $mform->add_hidden('cat_htype', CAT_HTYPE_FAIR_KOMI);
      else
      {
         $attb_arr['onclick'] = 'this.form.cat_htype_fk.checked=true;';
         array_push( $row_arr, 'RADIOBUTTONSX', 'cat_htype', array( CAT_HTYPE_FAIR_KOMI => '' ), $CategoryHandiType,
            'id="cat_htype_fk"' );
      }
      array_push( $row_arr,
         'SELECTBOXX', 'fk_htype', 1, GameTexts::get_fair_komi_types(), $Handitype, false, $attb_arr );
      if ( $is_fstyle_invite )
         array_push( $row_arr,
            'TEXT', T_('+ set Jigo mode (see below)#fairkomi') );
      $mform->add_row( $row_arr );
   }

   if ( ENABLE_STDHANDICAP && !$is_view_fairkomi )
   {
      $mform->add_row( array(
            'DESCRIPTION', T_('Handicap stones'),
            'CHECKBOXX', 'stdhandicap', 'Y', "", $StdHandicap, array( 'disabled' => $ShapeID ),
            'TEXT', T_('Standard placement'), ));
   }


   $adjustments_view = ( $is_fstyle_tourney || ($formstyle == GSET_WAITINGROOM && $viewmode == GSETVIEW_STANDARD)
      || $is_fstyle_invite );
   if ( $adjustments_view )
   {
      $mform->add_row( array( 'SPACE' ) );
      $mform->add_row( array( 'CELL', 2, 'class="center WarnMsg"',
                              'TEXT', T_('Adjustments only apply for conventional and proper handicap type!'), ));

      // adjust handicap stones
      $adj_handi_stones = array();
      $HSTART = max(5, (int)(MAX_HANDICAP/3));
      for ( $bs = -$HSTART; $bs <= $HSTART; $bs++ )
         $adj_handi_stones[$bs] = ($bs <= 0) ? $bs : "+$bs";
      $adj_handi_stones[0] = '&nbsp;0';

      $max_handi_stones = build_arr_handicap_stones( /*def*/true );
      $txt_def_max_handi = span('id="DefMaxHandicap" class="smaller"',
         sprintf( T_('(Def. is %s for size %s)#defmaxhandi'), // adjust also build_game_settings_javascript()
            DefaultMaxHandicap::calc_def_max_handicap($Size), $Size ));

      $mform->add_row( array( 'DESCRIPTION', T_('Handicap stone adjustments'),
                              'TEXT', sptext(T_('Adjust by#handi')),
                              'SELECTBOX', 'adj_handicap', 1, $adj_handi_stones, $AdjHandicap, false,
                              'TEXT', sptext(T_('Min.'), 1),
                              'SELECTBOX', 'min_handicap', 1, $handi_stones, $MinHandicap, false,
                              'TEXT', sptext(T_('Max.'), 1),
                              'SELECTBOX', 'max_handicap', 1, $max_handi_stones, $MaxHandicap, false,
                              'TEXT', ' ' . $txt_def_max_handi,
                              ));
   }
   else
   {
      $mform->add_row( array(
            'HIDDEN', 'adj_handicap', $AdjHandicap,
            'HIDDEN', 'min_handicap', $MinHandicap,
            'HIDDEN', 'max_handicap', $MaxHandicap,
            ));
   }

   if ( $adjustments_view )
   {
      // adjust komi
      $mform->add_row( array(
            'DESCRIPTION', T_('Komi adjustments'),
            'TEXT', sptext(T_('Adjust by#komi')),
            'TEXTINPUTX', 'adj_komi', 5, 5, $AdjKomi, $gsc->get_class_error_field('adj_komi'),
            'TEXT', sptext(T_('Jigo mode'), 1),
            'SELECTBOX', 'jigo_mode', 1, GameTexts::get_jigo_modes(), $JigoMode, false,
         ));
   }
   elseif ( $is_view_fairkomi )
   {
      $mform->add_hidden('adj_komi', $AdjKomi);
      $mform->add_row( array(
            'DESCRIPTION', T_('Komi'),
            'TEXT', sptext(T_('Jigo mode')),
            'SELECTBOX', 'jigo_mode', 1, GameTexts::get_jigo_modes(), $JigoMode, false,
         ));
   }
   elseif ( !$is_view_fairkomi && !$is_fstyle_invite )
   {
      $mform->add_hidden('adj_komi', $AdjKomi);
      $mform->add_hidden('jigo_mode', $JigoMode);
   }


   if ( $formstyle == GSET_WAITINGROOM && $is_view_mpgame )
   {
      $mform->add_row( array( 'HEADER', T_('Multi-player settings') ) );

      $mform->add_row( array(
            'DESCRIPTION', T_('Game Players'),
            'TEXTINPUTX', 'game_players', 6, 5, $GamePlayers, $gsc->get_class_error_field('game_players'),
            'TEXT', MINI_SPACING.T_('e.g. 2:2 (Rengo), 3 (Zen-Go)'), ));
   }


   $value_array = array(
         'hours'  => T_('hours'),
         'days'   => T_('days'),
         'months' => T_('months') );

   $mform->add_row( array( 'HEADER', T_('Time settings') ) );

   $mform->add_row( array(
         'DESCRIPTION', T_('Main time'),
         'TEXTINPUTX', 'timevalue', 5, 5, $Maintime, $gsc->get_class_error_field('timevalue'),
         'SELECTBOX', 'timeunit', 1, $value_array, $MaintimeUnit, false ) );

   $attb_arr_jap = ( is_javascript_enabled() ) ? array( 'onclick' => 'this.form.time_byotype_jap.checked=true;' ) : array();
   $mform->add_row( array(
         'DESCRIPTION', T_('Japanese byoyomi'),
         //'CELL', 1, 'nowrap',
         'RADIOBUTTONSX', 'byoyomitype', array( BYOTYPE_JAPANESE => '' ), $Byotype,
            array( 'id' => 'time_byotype_jap' ) + $attb_arr_jap,
         'TEXTINPUTX', 'byotimevalue_jap', 5, 5, $Byotime_jap,
            $gsc->get_class_error_field('byotimevalue_jap', true) + $attb_arr_jap,
         'SELECTBOXX', 'timeunit_jap', 1, $value_array, $ByotimeUnit_jap, false, $attb_arr_jap,
         'TEXT', sptext(T_('with')),
         'TEXTINPUTX', 'byoperiods_jap', 5, 5, $Byoperiods_jap,
            $gsc->get_class_error_field('byoperiods_jap', true) + $attb_arr_jap,
         'TEXT', sptext(T_('extra periods')),
      ));

   $attb_arr_can = ( is_javascript_enabled() ) ? array( 'onclick' => 'this.form.time_byotype_can.checked=true;' ) : array();
   $mform->add_row( array(
         'DESCRIPTION', T_('Canadian byoyomi'),
         'RADIOBUTTONSX', 'byoyomitype', array( BYOTYPE_CANADIAN => '' ), $Byotype,
            array( 'id' => 'time_byotype_can' ) + $attb_arr_can,
         'TEXTINPUTX', 'byotimevalue_can', 5, 5, $Byotime_can,
            $gsc->get_class_error_field('byotimevalue_can', true) + $attb_arr_can,
         'SELECTBOXX', 'timeunit_can', 1, $value_array, $ByotimeUnit_can, false, $attb_arr_can,
         'TEXT', sptext(T_('for')),
         'TEXTINPUTX', 'byoperiods_can', 5, 5, $Byoperiods_can,
            $gsc->get_class_error_field('byoperiods_can', true) + $attb_arr_can,
         'TEXT', sptext(T_('stones')),
      ));

   $attb_arr_fis = ( is_javascript_enabled() ) ? array( 'onclick' => 'this.form.time_byotype_fis.checked=true;' ) : array();
   $mform->add_row( array(
         'DESCRIPTION', T_('Fischer time'),
         'RADIOBUTTONSX', 'byoyomitype', array( BYOTYPE_FISCHER => '' ), $Byotype,
            array( 'id' => 'time_byotype_fis' ) + $attb_arr_fis,
         'TEXTINPUTX', 'byotimevalue_fis', 5, 5, $Byotime_fis,
            $gsc->get_class_error_field('byotimevalue_fis', true) + $attb_arr_fis,
         'SELECTBOXX', 'timeunit_fis', 1, $value_array, $ByotimeUnit_fis, false, $attb_arr_fis,
         'TEXT', sptext(T_('extra per move')),
      ));

   $mform->add_row( array( 'SPACE' ) );

   $mform->add_row( array(
         'DESCRIPTION', T_('Clock runs on weekends'),
         'CHECKBOX', 'weekendclock', 'Y', "", $WeekendClock,
         'TEXT', sprintf( '(%s)', T_('UTC timezone') ), ));

   if ( !$is_view_mpgame )
   {
      if ( $formstyle == GSET_WAITINGROOM )
         $mform->add_row( array( 'HEADER', T_('Restrictions') ) );

      if ( $formstyle == GSET_MSG_DISPUTE && $Rated && !$iamrated ) // user-unrated
         $allowed = false;

      if ( $ShapeID )
         $mform->add_hidden('rated', '');
      else
      {
         $mform->add_row( array(
               'DESCRIPTION', T_('Rated game'),
               'CHECKBOXX', 'rated', 'Y', "", ( $iamrated && $Rated ), ( $iamrated ? '' : 'disabled=1' ),
               'TEXT', ( $iamrated ? '' : span('WarnMsg', $text_need_rating) ) ));
      }
   }

   if ( $formstyle == GSET_WAITINGROOM && !$is_view_mpgame )
   {
      // read init-vals from URL for rematch or profile-template
      append_form_add_waiting_room_game( $mform, $viewmode, ( $my_ID === 'redraw' ), $gsc );
   }

   return $allowed;
}//game_settings_form


define('FLOW_ANSWER',   0x1);
define('FLOW_ANSWERED', 0x2);

global $msg_icones; //PHP5
$msg_icones = array(
      0                         => array('images/msg.gif'   ,'&nbsp;-&nbsp;'),
      FLOW_ANSWER               => array('images/msg_lr.gif','&gt;-&nbsp;'), //is an answer
                  FLOW_ANSWERED => array('images/msg_rr.gif','&nbsp;-&gt;'), //is answered
      FLOW_ANSWER|FLOW_ANSWERED => array('images/msg_2r.gif','&gt;-&gt;'),
   );

// $other_id: uid or array( [ ID/Handle/Name => ..., ...]; the latter ignoring other_name/handle
function message_info_table($mid, $date, $to_me, //$mid==0 means preview
                            $other_id, $other_name, $other_handle, //must be html_safe
                            $subject, $text, //must NOT be html_safe
                            $flags=0, $thread=0, $reply_mid=0, $flow=0,
                            $folders=null, $folder_nr=null, $form=null, $delayed_move=false,
                            $rx_term='')
{
   global $msg_icones, $bg_color, $base_path;

   if ( is_array($other_id) ) // multi-receiver (bulk) message
   {
      $arr = array();
      foreach ( $other_id as $urow )
         $arr[] = user_reference( REF_LINK, 0, '', $urow );
      $name = implode("<br>\n", $arr);
   }
   elseif ( $other_id > 0 )
      $name = user_reference( REF_LINK, 0, '', $other_id, $other_name, $other_handle) ;
   else
      $name = $other_name; //i.e. T_("Server message"); or T_('Receiver not found');

   $oid_url = ''; // other-uid URL-part
   $is_bulk = ($flags & MSGFLAG_BULK);
   if ( $is_bulk && $mid > 0 )
   {
      $oid_url = ($other_id > 0) ? URI_AMP."oid=$other_id" : '';
      $bulk_info = T_('Bulk-Message with other receivers');
      if ( $thread > 0 )
         $bulk_info = anchor( "message_thread.php?thread=$thread".URI_AMP."mid=$mid$oid_url#mid$mid", $bulk_info );
      $bulk_info = SMALL_SPACING . "[ $bulk_info ]";
   }
   else
      $bulk_info = '';

   $cols = 2;
   echo "<table class=MessageInfos>\n",
      "<tr class=Date>",
      "<td class=Rubric>", T_('Date'), ":</td>",
      "<td colspan=$cols>", date(DATE_FMT, $date), "</td></tr>\n",
      "<tr class=Correspondent>",
      "<td class=Rubric>", ($to_me ? T_('From') : T_('To') ), ":</td>\n",
      "<td colspan=$cols>$name$bulk_info</td>",
      "</tr>\n";

   $subject = make_html_safe( $subject, SUBJECT_HTML, $rx_term);
   $text = make_html_safe( $text, true, $rx_term);
   $text = MarkupHandlerGoban::replace_igoban_tags( $text );

   // warn on empty subject
   $subj_fmt = $subject;
   if ( (string)$subject == '' )
      $subj_fmt = span('InlineWarning', T_('(no subject)') );

   echo "<tr class=Subject>",
      "<td class=Rubric>", T_('Subject'), ":</td>",
      "<td colspan=$cols>", $subj_fmt, "</td></tr>\n",
      "<tr class=Message>",
      "<td class=Rubric>", T_('Message'), ":" ;

   $str0 = $str = '';
   if ( ($flow & FLOW_ANSWER) && $reply_mid > 0 )
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWER];
      $str.= "<a href=\"message.php?mode=ShowMessage".URI_AMP."mid=$reply_mid\">" .
             "<img border=0 alt='$alt' src='$ico' title=\"" . T_("Previous message") . "\"></a>&nbsp;";
   }
   if ( ($flow & FLOW_ANSWERED) && $mid > 0)
   {
      list($ico,$alt) = $msg_icones[FLOW_ANSWERED];
      $str.= "<a href=\"list_messages.php?find_answers=$mid\">" .
             "<img border=0 alt='$alt' src='$ico' title=\"" . T_("Next messages") . "\"></a>&nbsp;";
   }
   if ( ($str || $is_bulk) && $thread > 0 ) // $str set if msg is answer or has answer
      $str0 .= anchor( "message_thread.php?thread=$thread".URI_AMP."mid=$mid$oid_url#mid$mid",
         image( $base_path.'images/thread.gif', T_('Message thread') ),
         T_('Show message thread') ) . MINI_SPACING;
   if ( $thread > 0 && $thread != $mid )
      $str0 .= anchor( 'message.php?mode=ShowMessage'.URI_AMP.'mid='.$thread,
         image( $base_path.'images/msg_first.gif', T_('First message in thread') ),
         T_('Show initial message in thread') ) . MINI_SPACING;
   if ( $str0 || $str )
     echo "<div class=MessageFlow>$str0$str</div>";

   echo "</td>\n"
      , "<td colspan=$cols>\n";

   echo "<table class=MessageBox><tr><td>"
      , $text
      , "</td></tr></table>";

   echo "</td></tr>\n";

   if ( isset($folders) && $mid > 0 )
   {
      echo "<tr class=Folder>\n";

      echo "<td class=Rubric>" . T_('Folder') . ":</td>\n"
         , "<td><table class=FoldersTabs><tr>"
         , echo_folder_box($folders, $folder_nr, substr($bg_color, 2, 6))
         , "</tr></table></td>\n";

      echo "<td>";
      $deleted = ( $folder_nr == FOLDER_DESTROYED );
      if ( !$deleted )
      {
         $fldrs = array( FOLDER_NONE => '');
         foreach ( $folders as $key => $val )
         {
            if ( $key != $folder_nr && $key != FOLDER_NEW && (!$to_me || $key != FOLDER_SENT) )
               $fldrs[$key] = $val[0];
         }

         $arg_folder = @$_REQUEST['folder'];
         echo $form->print_insert_select_box('folder', '1', $fldrs, $arg_folder, '');
         if ( $delayed_move )
            echo T_('Move to folder when replying');
         else
            echo $form->print_insert_submit_button('foldermove', T_('Move to folder'));

         echo $form->print_insert_hidden_input("mark$mid", 'Y') ;
         if ( $folder_nr > FOLDER_ALL_RECEIVED )
            echo $form->print_insert_hidden_input("current_folder", $folder_nr) ;

         $follow = (bool)@$_REQUEST['follow']; // follow into target folder?
         echo "<br>\n",
            $form->print_insert_checkbox('follow', '1', T_('Follow moving'), $follow ),
            $form->print_insert_hidden_input('foldermove_mid', $mid);
      }
      echo "\n</td></tr>\n";
   }

   echo "</table>\n";
} // end of 'message_info_table'


/*!
 * \brief Prints game-info-table for some pages.
 * \param $tablestyle:
 *     GSET_MSG_INVITE | GSET_MSG_DISPUTE = for message.php
 *     GSET_WAITINGROOM = for waiting_room.php
 *     GSET_TOURNAMENT_LADDER = for ladder-tournament challenge (current player = challenger)
 *     GSET_TOURNAMENT_ROUNDROBIN = for round-robin-tournament (NOT USED at the moment)
 * \param $game_row see inline-comment below for expected fields
 * \param $game_setup GameSetup-object with GameSetup.Handicaptype aligned to GameSetup.uid
 */
function game_info_table( $tablestyle, $game_row, $player_row, $iamrated, $game_setup )
{
   global $base_path, $player_row;

   if ( $tablestyle == GSET_TOURNAMENT_ROUNDROBIN ) // though not supported at the moment, already contains some implementation for it
      error('invalid_args', "msg_func.game_info_table.check.bad_tablestyle($tablestyle)");
   if ( is_null($game_setup) )
      error('invalid_args', "msg_func.game_info_table.miss_game_setup($tablestyle)");

   // defaults (partly overwritten by $game_row)
   $GameType = GAMETYPE_GO;
   $GamePlayers = '';
   $AdjKomi = 0.0;
   $JigoMode = JIGOMODE_KEEP_KOMI;
   $AdjHandicap = 0;
   $MinHandicap = 0;
   $MaxHandicap = DEFAULT_MAX_HANDICAP;

   // $game_row containing:
   // - for GSET_WAITINGROOM: Waitingroom.*; calculated, goodrated, haverating; WaitingroomJoined.JoinedCount; X_TotalCount
   // - for GSET_TOURNAMENT_LADDER: TournamentRules.*, X_Handitype, X_Color, X_Calculated
   // - for GSET_MSG_INVITE:
   //   Players ($player_row): other_id, other_handle, other_name, other_rating, other_ratingstatus,
   //   Games: fields mentioned in DgsMessage::build_message_base_query-func with full-data;
   //          X_TotalCount
   extract($game_row);

   $my_id = $player_row['ID'];
   $my_rating = $player_row['Rating2'];
   $is_my_game = ( $game_row['other_id'] == $my_id ); // used for waiting-room-checks only

   $Handicaptype = $game_setup->Handicaptype; // preserve original handicap-type
   $CategoryHandiType = get_category_handicaptype( $Handicaptype );
   $my_htype = $game_setup->get_user_view_handicaptype( $my_id );
   $is_fairkomi = ( $CategoryHandiType === CAT_HTYPE_FAIR_KOMI );
   $restricted_text = T_('Restricted#wroom') . ': ';

   // handle shape-games
   if ( $ShapeID > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($ShapeSnapshot);
      if ( !is_array($arr_shape) )
         error('invalid_snapshot', "msg_func.game_info_table($tablestyle,$ShapeID,$ShapeSnapshot)");

      $ShapeBlackFirst = (bool)@$arr_shape['PlayColorB'];
   }

   switch( (string)$tablestyle )
   {
      case GSET_WAITINGROOM:
         //$calculated passed in from $game_row
         $goodmingames = ( $MinRatedGames > 0 ) ? ((int)@$player_row['RatedGames'] >= $MinRatedGames) : true;
         //$goodhero passed in from $game_row
         //$goodrated passed in from $game_row
         //$haverating passed in from $game_row
         break;

      case GSET_TOURNAMENT_LADDER:
      case GSET_TOURNAMENT_ROUNDROBIN:
         // for transparency put following into separate fields (see tournaments/ladder/challenge.php)
         // - for GSET_TOURNAMENT_LADDER: TournamentRules.*, X_Color, calculated

         //$calculated passed in from $game_row
         $goodrating = 1;
         $goodmingames = true;
         $goodhero = true;
         $goodrated = 1; // tournament-participants always have a rating
         $haverating = ( $iamrated || !$calculated );
         break;

      //case GSET_MSG_INVITE: // invite|dispute
      default:
         // overwrite game-row fields with user-specific invitation-game-setup
         // - fields also stored in Games-entry need to be copied here, because $game_setup
         //   can be from GameInvitation not matching Games.ToMove_ID
         $Ruleset = $game_setup->Ruleset;
         $Size = $game_setup->Size;
         $Komi = $game_setup->Komi;
         $Handicap = $game_setup->Handicap;
         $Maintime = $game_setup->Maintime;
         $Byotype = $game_setup->Byotype;
         $Byotime = $game_setup->Byotime;
         $Byoperiods = $game_setup->Byoperiods;
         $WeekendClock = ( $game_setup->WeekendClock ) ? 'Y' : 'N';
         $Rated = ( $game_setup->Rated ) ? 'Y' : 'N';
         $StdHandicap = ( $game_setup->StdHandicap ) ? 'Y' : 'N';
         // - fields not stored in Games-entry
         $AdjKomi = $game_setup->AdjKomi;
         $JigoMode = $game_setup->JigoMode;
         $AdjHandicap = $game_setup->AdjHandicap;
         $MinHandicap = $game_setup->MinHandicap;
         $MaxHandicap = $game_setup->MaxHandicap;

         $tablestyle = GSET_MSG_INVITE;

         $calculated = is_htype_calculated( $Handicaptype );
         $goodrating = 1;
         $goodmingames = true;
         $goodhero = true;
         $goodrated = ( $iamrated || $Rated == 'N' );
         $haverating = ( $iamrated || !$calculated );
         break;
   }//switch $tablestyle


   // ---------- start game-info table ------------------------

   $itable = new Table_info('game'); //==> ID='gameTableInfos'

   if ( $tablestyle == GSET_WAITINGROOM )
   {
      $itable->add_scaption(T_('Info'));
      $itable->add_sinfo(
            (( $GameType == GAMETYPE_GO ) ? T_('Number of games') : T_('Number of game-players')),
            $nrGames );
      $itable->add_sinfo(
            T_('Player'),
            user_reference( REF_LINK, 1, '', $other_id, $other_name, $other_handle) .
            echo_image_hero_badge( @$WRP_HeroRatio ) .
            SMALL_SPACING .
            echo_image_opp_games( $my_id, $other_handle, /*fin*/true ) );

      $itable->add_sinfo( T_('Rating'), echo_rating($other_rating,true,$other_id) );
   }
   elseif ( $tablestyle == GSET_MSG_INVITE )
   {
      $itable->add_scaption(T_('Players info'));
      $itable->add_sinfo( T_('My Rating'), echo_rating($my_rating,true,$my_id) );
      $itable->add_sinfo( T_('Opponent Rating'), echo_rating($other_rating,true,$other_id) );
      $itable->add_sinfo( T_('Started games'), sprintf( T_('%s (games with opponent)'), (int)@$game_row['X_TotalCount'] ) );

      $itable->add_scaption(T_('Game info'));
   }
   elseif ( $tablestyle == GSET_TOURNAMENT_LADDER )
      $itable->add_scaption(T_('Game info'));

   if ( $ShapeID && ($tablestyle == GSET_MSG_INVITE || $tablestyle == GSET_WAITINGROOM || $tablestyle == GSET_TOURNAMENT_LADDER) ) // invite & dispute, w-room
      $itable->add_sinfo( T_('Shape Game'),
            ShapeControl::build_snapshot_info( $ShapeID, $Size, $ShapeSnapshot, $ShapeBlackFirst ));

   if ( $tablestyle == GSET_WAITINGROOM )
      $itable->add_sinfo( T_('Game Type'), GameTexts::format_game_type($GameType, $GamePlayers) );

   $itable->add_sinfo( T_('Ruleset'), Ruleset::getRulesetText($Ruleset) );
   $itable->add_sinfo( T_('Size'), $Size );

   $color_class = 'class=InTextImage';
   $color_note = '';
   $my_subtype = null;
   switch ( (string)$CategoryHandiType )
   {
      case CAT_HTYPE_CONV: // Conventional handicap
         $itable->add_sinfo(
                  T_('Type'), T_('Conventional handicap (komi 0.5 if not even)'),
                  ( $haverating ? '' : warning_cell_attb( $restricted_text . T_('User has no rating')) ) );
         break;

      case CAT_HTYPE_PROPER: // Proper handicap
         $itable->add_sinfo(
                  T_('Type'), T_('Proper handicap'),
                  ( $haverating ? '' : warning_cell_attb( $restricted_text . T_('User has no rating')) ) );
         break;

      case CAT_HTYPE_MANUAL: // Manual game: Nigiri/Double/Black/White
      {
         if ( $Handicaptype == HTYPE_NIGIRI )
         {
            if ( $GameType == GAMETYPE_GO )
            {
               $subtype = ($Handicap == 0) ? T_('Even game with nigiri') : T_('Handicap game with nigiri');
               $colortxt = image( $base_path.'17/y.gif', T_('Nigiri'), null, $color_class );
            }
            else // mp-game
            {
               $subtype = T_('Color set by game-master#color');
               $colortxt = image( $base_path.'17/y.gif',
                  T_('Color set by game-master for multi-player-game#color'), null, $color_class );
            }
         }
         elseif ( $Handicaptype == HTYPE_DOUBLE )
         {
            $subtype = T_('Double game');
            $colortxt = build_image_double_game( true, $color_class );
         }
         elseif ( $Handicaptype == HTYPE_BLACK || $Handicaptype == HTYPE_WHITE )
         {
            // determine handicap-type for users
            if ( $tablestyle == GSET_WAITINGROOM && $is_my_game ) // show own wroom-game
            {
               $subtype = ( $my_htype == HTYPE_BLACK ) ? T_('Color Black') : T_('Color White');
               $colortxt =
                  (( $my_htype == HTYPE_BLACK )
                     ? image( $base_path.'17/b.gif', T_('Black'), null, $color_class)
                     : image( $base_path.'17/w.gif', T_('White'), null, $color_class) )
                  . MINI_SPACING . user_reference( 0, 1, '', $player_row );
            }
            else // for wroom (game-joiner) & invitation & tournament
            {
               switch ( (string)$tablestyle )
               {
                  case GSET_TOURNAMENT_LADDER:
                     $subtype = ( $my_htype == HTYPE_BLACK ) ? T_('Color Challenger Black#T_ladder') : T_('Color Challenger White#T_ladder');
                     break;

                  case GSET_TOURNAMENT_ROUNDROBIN: // not used at the moment
                     // check original htype
                     $subtype = ( $Handicaptype == HTYPE_BLACK ) ? T_('Color Stronger Black#T_RRobin') : T_('Color Stronger White#T_RRobin');
                     break;

                  //case GSET_WAITINGROOM:
                  //case GSET_MSG_INVITE: // invite|dispute
                  default:
                     $subtype = T_('Fix Color');
                     break;
               }//switch $tablestyle

               // determine user-white/black
               $user_b = $player_row;
               $user_w = array( 'ID' => $other_id, 'Handle' => $other_handle, 'Name' => $other_name );
               if ( $my_htype == HTYPE_WHITE )
                  swap( $user_b, $user_w );

               $colortxt = image( $base_path.'17/w.gif', T_('White'), null, $color_class) . MINI_SPACING
                  . user_reference( 0, 1, '', $user_w )
                  . SMALL_SPACING
                  . image( $base_path.'17/b.gif', T_('Black'), null, $color_class) . MINI_SPACING
                  . user_reference( 0, 1, '', $user_b )
                  ;
            }
         }
         else
            error('internal_error', "game_info_table.check.htype($gid,$Handicaptype)");

         $itable->add_sinfo( T_('Type'), sprintf( '%s [%s]', $subtype, T_('Manual setting#htype') ) );
         $itable->add_sinfo( T_('Colors'), $colortxt );
         $itable->add_sinfo( T_('Handicap'), $Handicap );
         $itable->add_sinfo( T_('Komi'), (float)$Komi );
         break;
      }//case CAT_HTYPE_MANUAL

      case CAT_HTYPE_FAIR_KOMI: // Fair Komi
      {
         if ( $tablestyle == GSET_WAITINGROOM )
         {
            $color_note = ( $is_my_game )
               ? GameTexts::get_fair_komi_types( $Handicaptype, NULL, $player_row['Handle'], /*opp*/NULL )
               : GameTexts::get_fair_komi_types( $Handicaptype, NULL, $other_handle, $player_row['Handle'] );
         }
         elseif ( $tablestyle == GSET_MSG_INVITE && is_htype_divide_choose($Handicaptype) ) // invite|dispute Div&Choose
         {
            $game_status = $game_row['Status'];
            if ( $game_status != GAME_STATUS_INVITED )
               error('invalid_game_status', "game_info_table.invite.check.game_status($Game_ID,$game_status,$Handicaptype)");

            $color_note = GameTexts::get_fair_komi_types( $my_htype, null, $player_row['Handle'], $other_handle );
         }
         else
            $color_note = GameTexts::get_fair_komi_types($Handicaptype);

         $itable->add_sinfo( T_('Type'), sprintf( '%s [%s]', $color_note, T_('Fair Komi#htype') ) );
         break;
      }//case CAT_HTYPE_FAIR_KOMI
   }//switch $CategoryHandiType

   if ( $tablestyle == GSET_MSG_INVITE || $tablestyle == GSET_WAITINGROOM || $tablestyle == GSET_TOURNAMENT_LADDER ) // Handicap adjustment
   {
      $adj_handi_str = GameSettings::build_adjust_handicap( $Size, $AdjHandicap, $MinHandicap, $MaxHandicap );
      if ( (string)$adj_handi_str != '' )
         $itable->add_sinfo( T_('Handicap adjustment'), $adj_handi_str );
   }

   if ( ENABLE_STDHANDICAP && !$is_fairkomi )
      $itable->add_sinfo( T_('Standard placement'), yesno( $StdHandicap) );

   if ( $tablestyle == GSET_MSG_INVITE || $tablestyle == GSET_WAITINGROOM || $tablestyle == GSET_TOURNAMENT_LADDER ) // Komi adjustment
   {
      if ( !$is_fairkomi )
      {
         $adj_komi_str = GameSettings::build_adjust_komi( $AdjKomi, $JigoMode );
         if ( (string)$adj_komi_str != '' )
            $itable->add_sinfo( T_('Komi adjustment'), $adj_komi_str );
      }
   }

   if ( $tablestyle == GSET_WAITINGROOM )
   {
      // check + show if there are restrictions for rating-range & min-rated-games
      $ratinglimit_str = echo_game_restrictions( $MustBeRated, $RatingMin, $RatingMax, $MinRatedGames );
      if ( $ratinglimit_str != NO_VALUE )
      {
         $r_out = array();
         if ( !$goodrating )
            $r_out[] = ( $iamrated ) ? T_('User rating is out of range#wroom') : T_('User has no rating');
         if ( !$goodmingames )
            $r_out[] = T_('User has not enough finished rated games#wroom');
         $itable->add_sinfo(
            T_('Rating restrictions'), $ratinglimit_str,
            ( count($r_out) ? warning_cell_attb( $restricted_text . implode(', ', $r_out)) : '' ) );
      }

      // check + show if there are restrictions on hero-ratio
      if ( $MinHeroRatio > 0 )
      {
         $my_hero_ratio = User::calculate_hero_ratio( $player_row['GamesWeaker'], $player_row['Finished'],
            $player_row['Rating2'], $player_row['RatingStatus'] );
         $itable->add_sinfo(
            T_('Min. hero percentage'),
            sprintf('%s%% %s(%s: %1.1f%%)', $MinHeroRatio, SMALL_SPACING, T_('Your ratio#hero'), 100*$my_hero_ratio ),
            ( $goodhero ? '' : warning_cell_attb( $restricted_text . T_('User hero percentage is too small#wroom')) ) );
      }

      // check + show if there are restrictions for same-opponent-check
      $same_opp_str = echo_accept_same_opponent($SameOpponent, $game_row);
      if ( $SameOpponent != 0 )
      {
         $itable->add_sinfo(
            T_('Accept same opponent'), $same_opp_str,
            ( $goodsameopp ? '' : warning_cell_attb( $restricted_text . T_('User already has started games#wroom')) ) );
      }
   }


   $itable->add_sinfo( T_('Main time'), TimeFormat::echo_time($Maintime) );
   $itable->add_sinfo(
         TimeFormat::echo_byotype($Byotype),
         TimeFormat::echo_time_limit( -1, $Byotype, $Byotime, $Byoperiods, 0) );

   $itable->add_sinfo(
         T_('Rated game'), yesno( $Rated),
         ( $goodrated ? '' : warning_cell_attb( $restricted_text . T_('User has no rating')) ) );
   $itable->add_sinfo( T_('Clock runs on weekends'), yesno( $WeekendClock) );

   if ( $tablestyle == GSET_WAITINGROOM ) // Comment
   {
      $itable->add_row( array(
            'sname' => T_('Comment'),
            'info' => $Comment, //INFO_HTML
         ));
   }

   // compute the probable game settings
   if ( $haverating && $goodrating && $goodmingames && ( !$is_my_game || $tablestyle != GSET_WAITINGROOM ) )
   {
      $gs_calc = new GameSettingsCalculator( $game_setup, $my_rating, $other_rating, $calculated );
      $gs_calc->calculate_settings( $my_id );

      if ( $tablestyle == GSET_WAITINGROOM && !$is_my_game )
         $itable->add_sinfo( T_('Started games'), (int)@$game_row['X_TotalCount'] );

      // determine color
      if ( $gs_calc->calc_color == GSC_COL_DOUBLE )
         $colortxt = build_image_double_game( true, $color_class );
      elseif ( $gs_calc->calc_color == GSC_COL_FAIRKOMI )
         $colortxt = image( $base_path.'17/y.gif', $color_note, NULL, $color_class ) . MED_SPACING . $color_note;
      else
         $colortxt = get_colortext_probable( ($gs_calc->calc_color == GSC_COL_BLACK), ($gs_calc->calc_color == GSC_COL_NIGIRI) );

      $itable->add_scaption( ($gs_calc->calc_type == 1) ? T_('Probable game settings') : T_('Game settings') );

      $itable->add_sinfo( T_('My Color'), $colortxt );
      $itable->add_sinfo( T_('Handicap'), $gs_calc->calc_handicap );

      $komi_text = ( $is_fairkomi )
         ? T_('negotiated by Fair Komi#fairkomi')
         : sprintf("%.1f", $gs_calc->calc_komi);
      $itable->add_sinfo( T_('Komi'), $komi_text );

      if ( $is_fairkomi )
         $itable->add_sinfo( T_('Jigo mode'), GameTexts::get_jigo_modes($JigoMode) );
   } //Probable settings

   $itable->echo_table();
}//game_info_table


// see also echo_game_restrictions()-func for abbreviations
function build_game_restriction_notes()
{
   $restrictions = array(
         T_('Rating range (user rating must be within the requested rating range), e.g. "25k-2d"#wroom'), // rating-range
         sprintf( T_('Number of rated finished games, e.g. "%s"#wroom'),
                  sprintf(T_('Rated Games[%s]#short'),2) ),
         sprintf( T_('Max. number of opponents started games must not exceed limits, marked by "%s"#wroom'),
                  'MXG' ),
         sprintf( T_('Min. hero percentage, e.g. "%s"#wroom'), 'H%[40-]' ),
         sprintf( T_('Acceptance mode for challenges from same opponent, e.g. "%s" (total) or "%s" or "%s"#wroom'),
                  'SOT[1]', 'SO[1x]', 'SO[&gt;7d]' ),
         sprintf( T_('Handicap-type (conventional and proper handicap-type need a rating for calculations), marked by "%s"#wroom'),
                  'CHT' ), // calculated-handicap-type
         sprintf( T_('User has no rating, marked by "%s"#wroom'), 'RT' ),
         sprintf( T_('Contact-option \'Hide waiting room games\', marked by "%s"'),
                  sprintf('[%s]', T_('Hidden#wroom')) ),
      );
   return $restrictions;
}//build_game_restriction_notes

/*!
 * \brief Returns restrictions for waiting-room-offer on rating-range, rated-finished-games,
 *       hero-ratio-range, acceptance-mode-same-opponent, contact-hidden option.
 * \param $OppGoodMaxGames ignore if null
 * \param $SameOpponent ignore if null
 * \param $Hidden ignore if null
 * \param $goodrated ignore if null
 * \param $haverating ignore if null
 * \param $html false=no HTML-entities
 * \return NO_VALUE if no restrictions; otherwise string-list of with found restriction
 *
 * \note Difference between "Restrictions"-column from waiting-room-list and "real" restrictions in game-info-table:
 *   - waiting-room-restrictions show all "potentially" restricting factors (even if some of them are not restricted
 *     for the particular offer and user viewing the offers).
 *   - while in the game-info-table the "real" restricted attributes are shown with a restricted-warning!
 *
 * \see also build_game_restriction_notes() for used abbreviations and check-order of restrictions
 */
function echo_game_restrictions($MustBeRated, $RatingMin, $RatingMax, $MinRatedGames,
      $MinHeroRatio=null, $OppGoodMaxGames=null, $SameOpponent=null, $Hidden=null,
      $goodrated=null, $haverating=null, $short=true, $html=true )
{
   $out = array();

   if ( $MustBeRated == 'Y' )
   {
      // +/-50 reverse the inflation from new-game handle_add_game()-func
      $r1 = echo_rating( $RatingMin + 50, false, 0, false, $short );
      $r2 = echo_rating( $RatingMax - 50, false, 0, false, $short );
      if ( $r1 == $r2 )
         $Ratinglimit = sprintf( T_('%s only'), $r1);
      else
         $Ratinglimit = $r1 . ' - ' . $r2;
      $out[] = $Ratinglimit;
   }

   if ( $MinRatedGames > 0 )
   {
      $rg_str = ($short) ? T_('Rated Games[%s]#short') : T_('Rated finished Games[&gt;=%s]');
      $out[] = sprintf( $rg_str, $MinRatedGames );
   }

   if ( !is_null($MinHeroRatio) && $MinHeroRatio > 0 )
      $out[] = "H%[{$MinHeroRatio}-]";

   if ( !is_null($OppGoodMaxGames) && !$OppGoodMaxGames )
      $out[] = 'MXG';

   if ( !is_null($SameOpponent) )
   {
      if ( $SameOpponent < SAMEOPP_TOTAL )
         $out[] = sprintf( 'SOT[%s]', -$SameOpponent + SAMEOPP_TOTAL ); // N total times
      elseif ( $SameOpponent < 0 )
         $out[] = sprintf( 'SO[%sx]', -$SameOpponent ); // N times
      elseif ( $SameOpponent > 0 )
         $out[] = sprintf( 'SO[&gt;%sd]', $SameOpponent ); // after N days
   }

   if ( !is_null($haverating) && !$haverating )
      $out[] = 'CHT';

   if ( !is_null($goodrated) && !$goodrated )
      $out[] = 'RT';

   if ( !is_null($Hidden) && $Hidden )
      $out[] = sprintf( '[%s]', T_('Hidden#wroom') );

   return ( count($out) ) ? preg_replace("/\\&gt;/", '>', implode(', ', $out)) : NO_VALUE;
}//echo_game_restrictions


function interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                    $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                    $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                    $byotimevalue_fis, $timeunit_fis)
{
   $max = time_convert_to_hours( 365, 'days');

   $mainhours = time_convert_to_hours($timevalue, $timeunit);
   if ( $mainhours > $max )
      $mainhours = $max;
   elseif ( $mainhours < 0 )
      $mainhours = 0;

   if ( $byoyomitype == BYOTYPE_JAPANESE )
   {
      $byohours = time_convert_to_hours($byotimevalue_jap, $timeunit_jap);
      if ( $byohours > $max )
         $byohours = $max;
      elseif ( $byohours < 0 )
         $byohours = 0;

      $byoperiods = (int)$byoperiods_jap;
      if ( $byoperiods < 1 )
         $byoperiods = 1;
      if ( $byohours * $byoperiods > $max )
         $byoperiods = floor($max/$byohours);
   }
   else if ( $byoyomitype == BYOTYPE_CANADIAN )
   {
      $byohours = time_convert_to_hours($byotimevalue_can, $timeunit_can);
      if ( $byohours > $max )
         $byohours = $max;
      elseif ( $byohours < 0 )
         $byohours = 0;

      $byoperiods = (int)$byoperiods_can;
      if ( $byoperiods < 1 )
         $byoperiods = 1;
   }
   else // if ( $byoyomitype == BYOTYPE_FISCHER )
   {
      $byoyomitype = BYOTYPE_FISCHER;
      $byohours = time_convert_to_hours($byotimevalue_fis, $timeunit_fis);
      if ( $byohours > $mainhours )
         $byohours = $mainhours;
      elseif ( $byohours < 0 )
         $byohours = 0;

      $byoperiods = 0;
   }

   return array($mainhours, $byohours, $byoperiods);
}

// FOLDER_DESTROYED is NOT in standard-folders
// return: [ folder_id => [ Name, BGColor, FGColor ], ...]
function get_folders($uid, $remove_all_received=true, $user_folder_nr=null)
{
   global $STANDARD_FOLDERS;

   $arr_folders = load_cache_folders( $uid, $user_folder_nr );
   $fldrs = $STANDARD_FOLDERS;

   foreach ( $arr_folders as $folder_nr => $row )
   {
      if ( empty($row['Name']))
         $row['Name'] = ( $folder_nr < USER_FOLDERS ) ? $STANDARD_FOLDERS[$folder_nr][0] : T_('Folder name');
      $fldrs[$folder_nr] = array( $row['Name'], $row['BGColor'], $row['FGColor'] );
   }

   if ( $remove_all_received )
      unset($fldrs[FOLDER_ALL_RECEIVED]);

   return $fldrs;
}//get_folders

function load_cache_folders( $uid, $folder_nr=null )
{
   static $fields = 'Folder_nr, Name, BGColor, FGColor';
   $dbgmsg = "load_cache_folders.get_folders($uid,$folder_nr)";
   $key = "Folders.$uid";

   $use_cache = DgsCache::is_persistent( CACHE_GRP_FOLDERS );
   $load_single_folder = ( !is_null($folder_nr) && is_numeric($folder_nr) );
   $query_all_folders = "SELECT $fields FROM Folders WHERE uid=$uid ORDER BY Folder_nr";
   $query = false;

   // need special handling to load only specific folder or caching all
   if ( $use_cache )
   {
      $result = DgsCache::fetch( $dbgmsg, CACHE_GRP_FOLDERS, $key );
      if ( is_null($result) )
         $query = $query_all_folders;
   }
   elseif ( $load_single_folder )
      $query = "SELECT $fields FROM Folders WHERE uid=$uid AND Folder_nr=$folder_nr LIMIT 1";
   else // load all folders
      $query = $query_all_folders;

   if ( $query ) // load and collect folders
   {
      $db_result = db_query( $dbgmsg, $query );

      $result = array();
      while ( $row = mysql_fetch_assoc($db_result) )
         $result[$row['Folder_nr']] = $row;
      mysql_free_result($db_result);

      if ( $use_cache )
         DgsCache::store( $dbgmsg, CACHE_GRP_FOLDERS, $key, $result, SECS_PER_DAY );
   }

   // extract single folder from all folders
   if ( $use_cache && $load_single_folder && count($result) > 0 && isset($result[$folder_nr]) )
      $result = array( $folder_nr => $result[$folder_nr] );

   return $result;
}//load_cache_folders

// builds array with folder-ids without ALL_RECEIVED/SENT/DELETED-folder (i.e. normally all-received and user-folders)
// param $folders folder-array in format as returned by get_folders()
function build_folders_all_received( $folders )
{
   unset($folders[FOLDER_ALL_RECEIVED]);
   unset($folders[FOLDER_SENT]);
   unset($folders[FOLDER_DELETED]);
   return array_keys($folders);
}

function change_folders_for_marked_messages($uid, $folders)
{
   if ( isset($_GET['move_marked']) )
   {
      if ( !isset($_GET['folder']) )
         return -1; //i.e. no move query
      $new_folder = (int)$_GET['folder'];
   }
   else if ( isset($_GET['destroy_marked'] ) )
      $new_folder = FOLDER_DESTROYED;
   else
      return -1; //i.e. no move query

   $message_ids = array();
   foreach ( $_GET as $key => $val )
   {
      if ( preg_match("/^mark(\d+)$/", $key, $matches) )
         $message_ids[]= $matches[1];
   }

   return change_folders($uid, $folders, $message_ids, $new_folder, @$_GET['current_folder']);
}

// return >0 success (messages moved), 0 = no messages to move or no (new-)target-folder specified
// \param $need_replied false = change only messages that have been replied,
//                      true = change only message that need NO reply
function change_folders($uid, $folders, $message_ids, $new_folder, $current_folder=false, $need_replied=false, $quick_suite=false)
{
   if ( count($message_ids) <= 0 || $new_folder == FOLDER_NONE )
      return 0;

   if ( $new_folder == FOLDER_DESTROYED )
   {
      if ( $quick_suite )
         $where_clause = '';
      else
      {
         // destroy'ing only allowed from Trashcan-folder
         $where_clause = "AND Folder_nr='" .FOLDER_DELETED. "' ";
      }
   }
   else
   {
      if ( !isset($new_folder) || !isset($folders[$new_folder])
            || $new_folder == FOLDER_NEW || $new_folder == FOLDER_ALL_RECEIVED )
         error('folder_not_found', "change_folders.check.new_folder($uid,$new_folder)");

      if ( $new_folder == FOLDER_SENT )
         $where_clause = "AND Sender IN('Y','M') ";
      else if ( $new_folder == FOLDER_REPLY )
         $where_clause = "AND Sender IN('N','M','S') ";
      else
         $where_clause = '';

      if ( $current_folder > FOLDER_ALL_RECEIVED && isset($folders[$current_folder])
            && $current_folder != FOLDER_DESTROYED )
         $where_clause.= "AND Folder_nr='$current_folder' ";
   }

   if ( $need_replied )
      $where_clause.= "AND Replied='Y' ";
   else
      $where_clause.= "AND Replied!='M' ";

   ta_begin();
   {//HOT-section to change folders
      $msg_id_str = implode(',', $message_ids);
      db_query( "change_folders.update($uid,$new_folder,[$msg_id_str])",
         "UPDATE MessageCorrespondents SET Folder_nr=$new_folder " .
                  "WHERE uid='$uid' $where_clause" .
                  'AND Folder_nr > '.FOLDER_ALL_RECEIVED.' ' .
                  "AND mid IN ($msg_id_str) " .
                  "LIMIT " . count($message_ids) );
      $rows_updated = mysql_affected_rows() ;

      if ( $rows_updated > 0 )
         update_count_message_new( "change_folders.update.upd_cnt_msg_new", $uid, COUNTNEW_RECALC );
   }
   ta_end();

   return $rows_updated;
}//change_folders

// fix invitation-messages: Replied='M' (=need-reply) only valid if Games-entry still existing
// for example on invitation-cleanup, referenced Games may be deleted
// => and so, Replied must be set to 'N' then (otherwise messages can not be changed folders for)
// returns count of updated messages
function fix_invitations_replied( $dbgmsg, $limit )
{
   if ( !is_numeric($limit) || $limit < 1 )
      $limit = 100;

   // fix invitation-messages without game, that cannot be replied any more
   $result = db_query( "$dbgmsg.fix_invitations_replied.find",
      "SELECT MC.mid " .
      "FROM MessageCorrespondents AS MC " .
         "INNER JOIN Messages AS M ON M.ID=MC.mid " .
         "LEFT JOIN Games AS G ON G.ID=M.Game_ID " .
      "WHERE MC.Sender='N' AND MC.Replied='M' AND M.Type='".MSGTYPE_INVITATION."' AND M.Game_ID>0 AND G.ID IS NULL " .
      "LIMIT $limit" );

   $msg_ids = array();
   while ( $row = mysql_fetch_assoc($result) )
      $msg_ids[] = $row['mid'];
   mysql_free_result($result);

   if ( count($msg_ids) )
   {
      db_query( "$dbgmsg.fix_invitations_replied.upd(".count($msg_ids).")",
         "UPDATE MessageCorrespondents SET Replied='N' " .
         "WHERE Sender='N' AND Replied='M' AND mid IN (" . implode(',', $msg_ids) . ") LIMIT 100" );
      $upd_count = mysql_affected_rows();
   }
   else
      $upd_count = 0;

   return $upd_count;
}//fix_invitations_replied

/*!
 * \brief Updates or resets Players.CountMsgNew.
 * \param $diff null|omit to reset to -1 (=recalc later); COUNTNEW_RECALC to recalc now;
 *        otherwise increase or decrease counter
 */
function update_count_message_new( $dbgmsg, $uid, $diff=null )
{
   $dbgmsg .= "update_count_message_new($uid,$diff)";
   if ( !is_numeric($uid) )
      error( 'invalid_args', "$dbgmsg.check.uid" );

   if ( is_null($diff) )
   {
      db_query( "$dbgmsg.reset",
         "UPDATE Players SET CountMsgNew=-1 WHERE ID='$uid' LIMIT 1" );
   }
   elseif ( is_numeric($diff) && $diff != 0 )
   {
      db_query( "$dbgmsg.upd",
         "UPDATE Players SET CountMsgNew=CountMsgNew+($diff) WHERE CountMsgNew>=0 AND ID='$uid' LIMIT 1" );
   }
   elseif ( (string)$diff == COUNTNEW_RECALC )
   {
      global $player_row;
      $count_new = count_messages_new( $uid );
      if ( @$player_row['ID'] == $uid )
         $player_row['CountMsgNew'] = $count_new;
      db_query( "$dbgmsg.recalc",
         "UPDATE Players SET CountMsgNew=$count_new WHERE ID='$uid' LIMIT 1" );

   }

   clear_cache_quick_status( $uid, QST_CACHE_MSG );
   delete_cache_message_list( $dbgmsg, $uid );
}//update_count_message_new

/*!
 * \brief Builds string with user folders in table with links to browse respective folder.
 * \param $current_folder current-folder (will be marked)
 * \param $curr_linked false if current folder should not be shown with link
 */
function echo_folders( $folders, $current_folder, $curr_linked=true )
{
   global $STANDARD_FOLDERS;

   $string = '<table class=FoldersTabs><tr>' . "\n" .
      '<td class=Rubric>' . T_('Folder') . ":</td>\n";

   $folders[FOLDER_ALL_RECEIVED] = $STANDARD_FOLDERS[FOLDER_ALL_RECEIVED];
   ksort($folders);

   $i = 0;
   foreach ( $folders as $nr => $val )
   {
      if ( $i > 0 && ($i % FOLDER_COLS_MODULO) == 0 )
          $string .= "</tr>\n<tr><td></td>"; //empty cell under title
      $i++;

      $fclass = ( $nr == $current_folder ) ? 'Selected' : 'Tab';
      $link_fmt = ( $curr_linked || $nr != $current_folder )
         ? "<a href=\"list_messages.php?folder=$nr\">%s</a>" : '';
      $string .= echo_folder_box( $folders, $val, null, 'class='.$fclass, $link_fmt );
   }
   $i = ($i % FOLDER_COLS_MODULO);
   if ( $i > 0 ) //empty cells of last line
   {
      $i = FOLDER_COLS_MODULO - $i;
      if ( $i > 1 )
         $string .= "<td colspan=$i></td>";
      else
         $string .= "<td></td>";
   }

   $string .= "</tr></table>\n";

   return $string;
}

// param bgcolor: if null, fall back to default-val (in blend_alpha_hex-func)
// $folder_nr: id of the folders, may also be an array with the folder properties like in $STANDARD_FOLDERS
function echo_folder_box( $folders, $folder_nr, $bgcolor=null, $attbs='', $layout_fmt='')
{
   global $STANDARD_FOLDERS;

   if ( $folder_nr == FOLDER_DESTROYED ) //case of $deleted messages
     list($foldername, $folderbgcolor, $folderfgcolor) = array(NO_VALUE,0,0);
   else if ( is_array($folder_nr) )
     list($foldername, $folderbgcolor, $folderfgcolor) = $folder_nr;
   else
     list($foldername, $folderbgcolor, $folderfgcolor) = @$folders[$folder_nr];

   if ( empty($foldername) )
   {
     if ( $folder_nr < USER_FOLDERS )
       list($foldername, $folderbgcolor, $folderfgcolor) = $STANDARD_FOLDERS[$folder_nr];
     else
       $foldername = T_('Folder name');
   }

   $folderbgcolor = blend_alpha_hex($folderbgcolor, $bgcolor);
   if ( empty($folderfgcolor) )
      $folderfgcolor = "000000" ;

   $foldername= "<font color=\"#$folderfgcolor\">" . make_html_safe($foldername) . "</font>";
   if ( $layout_fmt )
      $foldername= sprintf( $layout_fmt, $foldername);

   if ( !$attbs )
      $attbs = 'class=FolderBox';

   return "<td bgcolor=\"#$folderbgcolor\" $attbs>$foldername</td>";
}

function folder_is_empty($nr, $uid)
{
   $result = db_query( 'folder_is_empty',
      "SELECT ID FROM MessageCorrespondents " .
      "WHERE uid='$uid' AND Folder_nr='$nr' LIMIT 1" );

   $nr = (@mysql_num_rows($result) === 0);
   mysql_free_result($result);
   return $nr;
}

function get_message_directions()
{
   return array(
      'M' => T_('Myself#msgdir'),
      'S' => T_('Server#msgdir'),
      'Y' => T_('To#msgdir'),
      'N' => T_('From#msgdir'),
   );
}




/*!
 * \brief Object to store and handle a single message.
 */
class DgsMessage
{
   private $recipients = array();
   private $errors = array();

   /*! \brief Returns true, if there is exactly ONE recipient. */
   public function has_recipient()
   {
      return (count($this->recipients) == 1);
   }

   public function count_recipients()
   {
      return count($this->recipients);
   }

   public function get_recipients()
   {
      return $this->recipients;
   }

   public function get_recipient()
   {
      return ($this->has_recipient()) ? $this->recipients[0] : null;
   }

   public function add_recipient( $user_row )
   {
      $this->recipients[] = $user_row;
   }

   public function clear_errors()
   {
      $this->errors = array();
   }

   public function count_errors()
   {
      return count($this->errors);
   }

   public function add_error( $error )
   {
      if ( $error )
         $this->errors[] = $error;
      return $error;
   }

   public function add_errors( $errors )
   {
      if ( is_array($errors) )
      {
         foreach ( $errors as $err )
            $this->add_error($err);
      }
   }

   public function get_errors()
   {
      return $this->errors;
   }

   public function build_recipient_user_row()
   {
      $user_row = $this->get_recipient();
      if ( is_null($user_row) )
         $user_row = array( 'ID' => 0, 'Handle' => '', 'Name' => span('InlineWarning', T_('Receiver not found')) );
      else
         $user_row['Name'] = make_html_safe($user_row['Name']);
      return $user_row;
   }


   // ------------ static functions ----------------------------

   /*!
    * \brief Loads single message for message-id and current user-id.
    * \param $other_uid other-uid needed for bulk-message to identify correct message;
    *        optional for non-bulk-message
    * \param $with_fulldata true = load with other-player-info and game-info (if game-message)
    * \return see build_message_base_query()-func
    */
   public static function load_message( $dbgmsg, $mid, $uid, $other_uid, $with_fulldata )
   {
      if ( !is_numeric($mid) || $mid <= 0 )
         error('unknown_message', "$dbgmsg.DgsMessage:load_message.check.mid($mid)");

      $qsql = self::build_message_base_query( $uid, $with_fulldata, /*single*/true, $mid );
      if ( $other_uid > 0 )
         $qsql->add_part( SQLP_WHERE, "other.uid=$other_uid" );

      $msg_row = mysql_single_fetch( "$dbgmsg.DgsMessage:load_message.find($mid)", $qsql->get_select() );
      if ( !$msg_row )
         error('unknown_message', "$dbgmsg.DgsMessage:load_message.find.not_found($mid)");

      return $msg_row;
   }//load_message

   /*!
    * \brief Builds QuerySQL to load single message for message-id and current user-id, or list of user-id's messages.
    * \param $with_fulldata true = build query with other-player-info and game-info (if game-message)
    * \param $single_msg true = build query for single message
    * \return message-row; multi-receiver message determined by Flags-field
    *    message-row-fields:
    *       ID, Type, Flags, Thread, Level, ReplyTo, Game_ID, Time, Subject, Text,
    *       X_Time (=M.Time), X_Flow; Replied, Sender, Folder_nr, other_id
    *
    *    additional fields $with_fulldata == true:
    *       Players: other_handle, other_name, other_rating, other_ratingstatus;
    *       Games: Game_mid, Status, Black_ID, White_ID, myColor, GameType, GamePlayers,
    *          Ruleset, Size, Rated, ToMove_ID, Handicap, StdHandicap, Komi,
    *          Maintime, Byotype, Byotime, Byoperiods, WeekendClock,
    *          ShapeID, ShapeSnapshot, GameSetup
    */
   public static function build_message_base_query( $uid, $with_fulldata, $single_msg, $mid=0 )
   {
      /**
       * Actually, the DGS-message-code does normally not support
       * multiple receivers (i.e. more than one "other" LEFT JOINed row).
       * Multiple receivers are allowed when it is a message from
       * the server (ID=0) because the message is not read BY the server.
       *
       * However in DGS 1.0.15 support for multi-receiver (bulk-)messages has been added.
       * See also: specs/db/table-Messages.txt
       * See also: send_message()
       **/

      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'M.*',
            'UNIX_TIMESTAMP(M.Time) AS X_Time',
            "IF(NOT ISNULL(prev.mid),".FLOW_ANSWER.",0)" .
               "+IF(me.Replied='Y' OR other.Replied='Y',".FLOW_ANSWERED.",0) AS X_Flow",
            'me.Replied', 'me.Sender', 'me.Folder_nr',
            'other.uid AS other_id',
         SQLP_FROM,
            'Messages AS M',
            "INNER JOIN MessageCorrespondents AS me ON me.mid=M.ID AND me.uid=$uid",
            "LEFT JOIN MessageCorrespondents AS other ON other.mid=M.ID AND other.Sender!=me.Sender",
            "LEFT JOIN MessageCorrespondents AS prev ON M.ReplyTo>0 AND prev.mid=M.ReplyTo AND prev.uid=$uid"
      );
      if ( $mid > 0 )
         $qsql->add_part( SQLP_WHERE, "M.ID=$mid" );

      if ( $single_msg )
      {
         // sort old messages to myself with Sender='N' first if both 'N' and 'Y' remains
         $qsql->add_part( SQLP_ORDER, 'Sender' ); // me.Sender
         $qsql->add_part( SQLP_LIMIT, '1' );
      }
      if ( $with_fulldata )
      {
         $qsql->add_part( SQLP_FIELDS,
            'P.Handle AS other_handle', 'P.Name AS other_name',
            'P.Rating2 AS other_rating', 'P.RatingStatus AS other_ratingstatus',
            'P.Country AS other_country',
            // from Games-table:
            'G.mid AS Game_mid', 'G.Status',
            'G.Black_ID', 'G.White_ID', // for invite/dispute fair-komi
            'G.GameType', 'G.GamePlayers', 'G.Ruleset', 'G.Size', 'G.Rated',
            'G.ToMove_ID', 'G.Handicap', 'G.StdHandicap', 'G.Komi',
            'G.Maintime', 'G.Byotype', 'G.Byotime', 'G.Byoperiods', 'G.WeekendClock',
            'G.ShapeID', 'G.ShapeSnapshot', 'G.GameSetup',
            "IF(White_ID=$uid,".WHITE.",".BLACK.") AS myColor" );
         $qsql->add_part( SQLP_FROM,
            "LEFT JOIN Players AS P ON P.ID=other.uid",
            "LEFT JOIN Games AS G ON G.ID=M.Game_ID" );
      }

      return $qsql;
   }//build_message_base_query

   /*!
    * \brief Finds receivers of the message and make some validity-checks.
    * \param $type Message.Type
    * \param $invitation_step true, if sending-message is part of invitation-ending/disputing
    * \param $to_handles non-empty (unique) array with user-id of recipients
    * \return array( [ handle => user-row, ... ], error|empty-array )
    */
   public static function load_message_receivers( $type, $invitation_step, $to_handles )
   {
      global $player_row;
      $my_id = (int)@$player_row['ID']; // sender
      if ( !is_array($to_handles) || count($to_handles) == 0 )
         error('invalid_args', "DgsMessage:load_message_receivers.check.to($my_id,$type,$invitation_step)");
      $to_handles = array_unique($to_handles);
      $to_handles = explode(' ', strtolower(implode(' ', $to_handles))); // lower-case to find all handles
      $arr_receivers = array();
      $errors = array();

      $chk_guest = $chk_invself = false;
      $arr_qhandles = array();
      foreach ( $to_handles as $handle )
      {
         if ( $handle == 'guest' ) // check strtolower(handle)
            $chk_guest = true;
         if ( $type == MSGTYPE_INVITATION && $player_row['Handle'] == $handle )
            $chk_invself = true;
         $arr_qhandles[] = mysql_addslashes($handle);
      }
      if ( $chk_guest )
         $errors[] = ErrorCode::get_error_text('guest_may_not_receive_messages');
      if ( $chk_invself )
         $errors[] = ErrorCode::get_error_text('invite_self');
      if ( count($errors) )
         return array( $arr_receivers, $errors );

      // CSYSFLAG_REJECT_INVITE only blocks the invitations at starting point
      // CSYSFLAG_REJECT_MESSAGE blocks the messages except those from the invitation sequence
      $ctmp = ( $type == MSGTYPE_INVITATION )
         ? CSYSFLAG_REJECT_INVITE
         : ( $invitation_step ? 0 : CSYSFLAG_REJECT_MESSAGE );
      $result = db_query( "DgsMessage:load_message_receivers.find($my_id,$type,$invitation_step)",
            "SELECT P.ID, P.Handle, P.Name, P.ClockUsed, P.OnVacation, P.Rating2, P.RatingStatus, " .
               "(P.Running + P.GamesMPG) AS X_OppGamesCount, " .
               "IF(ISNULL(C.uid),0,C.SystemFlags & $ctmp) AS C_denied " .
            "FROM Players AS P " .
               "LEFT JOIN Contacts AS C ON C.uid=P.ID AND C.cid=$my_id " .
            "WHERE P.Handle IN ('" . implode("','", $arr_qhandles) . "') " .
            "LIMIT " . count($to_handles) );
      while ( $row = mysql_fetch_assoc($result) )
         $arr_receivers[strtolower($row['Handle'])] = $row;
      mysql_free_result($result);

      // checks for unknown users and rejects-msg/inv
      foreach ( $to_handles as $handle )
      {
         if ( !isset($arr_receivers[$handle]) ) // handle must be lower-case to check
            $errors[] = sprintf( T_('Unknown message receiver [%s]'), $handle );
         else
         {
            // message can not be rejected coming from some admins
            $user_row = $arr_receivers[$handle];
            if ( $user_row['C_denied'] && !($player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_FORUM|ADMIN_GAME)) )
            {
               $msgtmpl = ( $type == MSGTYPE_INVITATION )
                  ? T_('Invitation rejected by user [%s] !')
                  : T_('Message rejected by user [%s] !');
               $errors[] = sprintf( $msgtmpl, $handle );
            }
         }
      }

      return array( $arr_receivers, $errors );
   }//load_message_receivers

   /*!
    * \briefs Moves message into target folder.
    * \param $Sender additional query-check; if null omitted in query (combination of mid/uid should be unique anyway)
    * \note Throws error if no update is generated.
    */
   public static function update_message_folder( $mid, $uid, $Sender, $new_folder, $die=true )
   {
      $dbgmsg = "DgsMessage.update_message_folder($mid,$uid,$Sender,$new_folder)";
      db_query( "$dbgmsg.1",
         "UPDATE MessageCorrespondents SET Folder_nr=$new_folder " .
         "WHERE mid=$mid AND uid=$uid" . (is_null($Sender) ? '' : " AND Sender='$Sender'") . " LIMIT 1" );
      $upd_count = mysql_affected_rows();
      if ( $die && $upd_count != 1)
         error('mysql_update_message', "$dbgmsg.2" );
      return $upd_count;
   }//update_message_folder

}// end 'DgsMessage'




/*!
 * \brief Helper class to display message-list.
 */
class MessageListBuilder
{
   private $table;
   private $current_folder;
   private $no_mark;
   private $full_details;

   // \param full_details: if true, show additional fields for message-search
   public function __construct( &$table, $current_folder, $no_mark=true, $full_details=false )
   {
      $this->table = $table;
      $this->current_folder = $current_folder;
      $this->no_mark = $no_mark;
      $this->full_details = $full_details;
   }

   public function message_list_head()
   {
      global $base_path, $msg_icones;

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $this->table->add_tablehead( 1, T_('Folder#header'), 'Folder',
            ($this->current_folder > FOLDER_ALL_RECEIVED ? TABLE_NO_SORT : 0), 'folder-');
      $this->table->add_tablehead( 9, new TableHead( T_('Message thread#header'),
            'images/thread.gif', T_('Show message thread') ), 'Image', 0, 'Thread+' );

      if ( $this->full_details )
      {
         // additional fields for search-messages
         $this->table->add_tablehead( 6, T_('Type#header'), '', TABLE_NO_HIDE, 'M.Type+');
         $this->table->add_tablehead( 7, T_('Direction#header'), 'MsgDir', 0, 'Sender+');
         $this->table->add_tablehead( 2, T_('Correspondent#header'), 'User', 0, 'other_name+');
      }
      else
         $this->table->add_tablehead( 2,
               ($this->current_folder == FOLDER_SENT) ? T_('To#header') : T_('From#header'),
               'User', 0, 'other_name+');

      $this->table->add_tablehead( 3, T_('Subject#header'), '', 0, 'Subject+');
      list($ico,$alt) = $msg_icones[0];
      $this->table->add_tablehead( 8, image( $ico, '*-*'), 'Image', TABLE_NO_HIDE, 'flow+');
      $this->table->add_tablehead(10, new TableHead( T_('First message in thread#header'),
            'images/msg_first.gif', T_('Show initial message in thread') ), 'Image', TABLE_NO_SORT );
      $this->table->add_tablehead( 4, T_('Created#header'), 'Date', 0, 'me.mid-'); // order of me.mid == order of msg-date
      if ( !$this->no_mark )
         $this->table->add_tablehead( 5, T_('Mark#header'), 'Mark', TABLE_NO_HIDE|TABLE_NO_SORT);
   }//message_list_head

   // param $arr_msg: typically coming from message_list_query()
   // param rx_terms: rx with terms to be marked within text
   // NOTE: frees given mysql $result
   public function message_list_body( $arr_msg, $show_rows, $my_folders, $toggle_marks=false, $rx_term='' )
   {
      global $base_path, $msg_icones, $player_row;

      $can_move_messages = false;
      //$page = ''; //not used, see below

      $p = T_('Answer');
      $n = T_('Replied');
      $tits = array(
         0                         => T_('Message'),
         FLOW_ANSWER               => $p ,
                     FLOW_ANSWERED => $n ,
         FLOW_ANSWER|FLOW_ANSWERED => "$p - $n" ,
         );
      $dirs = get_message_directions();

      $url_terms = ($rx_term != '') ? URI_AMP."xterm=".urlencode($rx_term) : '';
      $arr_marks = array(); // mid => 1

      foreach ( $arr_msg as $row )
      {
         if ( $show_rows-- <= 0 )
            break;
         $mid = $row["mid"];
         $oid = $row['other_ID'];
         $is_bulk = ( $row['Flags'] & MSGFLAG_BULK );

         $folder_nr = $row['folder'];
         $deleted = ( $folder_nr == FOLDER_DESTROYED );
         $bgcolor = $this->table->blend_next_row_color_hex();
         $thread = $row['Thread'];

         // link to message
         $oid_url = ( $is_bulk && $oid > 0 ) ? URI_AMP."oid=$oid" : '';
         $msg_url = 'message.php?mode=ShowMessage'.URI_AMP."mid=$mid$oid_url$url_terms";

         $mrow_strings = array();
         $mrow_strings[ 1] = array(
            'owntd' => echo_folder_box($my_folders, $folder_nr, $bgcolor) );

         // link to user
         $str = self::message_build_user_string( $row, $player_row, $this->full_details );
         if ( !$this->full_details && ($row['Sender'] === 'Y') )
            $str = T_('To') . ': ' . $str;
         $mrow_strings[ 2] = $str;

         $subject = make_html_safe( $row['Subject'], SUBJECT_HTML, $rx_term);
         $mrow_strings[ 3] = anchor( $msg_url, $subject );

         $flowval = $row['flow'];
         list($ico,$alt) = $msg_icones[$flowval];
         $mrow_strings[ 8] = anchor( $msg_url, image( $ico, $alt, $tits[$flowval] ));

         $mrow_strings[ 4] = date(DATE_FMT, $row["Time"]);

         // additional fields for search-messages
         if ( $this->full_details )
         {
            static $MSG_TYPES = array( // keep them untranslated(!)
                  MSGTYPE_NORMAL     => 'Normal',
                  MSGTYPE_INVITATION => 'Invitation',
                  MSGTYPE_DISPUTED   => 'Dispute',
                  MSGTYPE_RESULT     => 'Result',
               );
            $mrow_strings[ 6] = $MSG_TYPES[$row['Type']];

            $mrow_strings[ 7] = $dirs[$row['Sender']];
         }

         $mrow_strings[ 9] = '';
         $mrow_strings[10] = '';
         if ( $thread )
         {
            $mrow_strings[ 9] = anchor( "message_thread.php?thread=$thread".URI_AMP."mid=$mid$oid_url",
                  image( $base_path.'images/thread.gif', T_('Message thread') ),
                  T_('Show message thread') );

            if ( $thread != $mid )
               $mrow_strings[10] = anchor( 'message.php?mode=ShowMessage'.URI_AMP."mid=$thread",
                     image( $base_path.'images/msg_first.gif', T_('First message in thread') ),
                     T_('Show initial message in thread') );
         }

         if ( !$this->no_mark )
         {
            if ( $row['Replied'] == 'M' )
            {// message needs reply (so forbid marking and force inspection); invitation, not-replied yet, valid game
               $mrow_strings[ 5] = '';
            }
            elseif ( !isset($arr_marks[$mid]) ) // normal|bulk (for bulk-msg only mark first)
            {
               $can_move_messages = true;
               $n = $this->table->get_prefix() . "mark$mid";
               $checked = (('Y'==(string)@$_REQUEST[$n]) xor (bool)$toggle_marks);
               //if ( $checked ) $page.= "$n=Y".URI_AMP;
               $mrow_strings[ 5] = "<input type='checkbox' name='$n' value='Y'" . ($checked ? ' checked' : '') . '>';
               $arr_marks[$mid] = 1;
            }
            else if ( $is_bulk )
            {
               $mrow_strings[ 5] = image( $base_path.'images/up_bulk.gif',
                     T_('Use toggle above for this bulk-message'), null );
            }
         }
         $this->table->add_row( $mrow_strings );
      }

      return $can_move_messages;
   }//message_list_body


   // ------------ static functions ----------------------------

   /*!
    * \brief Builds user-string for message-list.
    * \param $row expected fields: Sender, other_ID, other_name, other_handle
    * \param $my_row most often $player_row
    */
   public static function message_build_user_string( &$row, $my_row, $full_details )
   {
      if ( $row['Sender'] === 'M' ) // Message to myself
         $row['other_name'] = '(' . T_('Myself') . ')';
      else if ( $row['other_ID'] <= 0 )
         $row['other_name'] = '[' . T_('Server message') . ']';
      if ( empty($row['other_name']) )
         $row['other_name'] = NO_VALUE;

      // link to user
      if ( $row['Sender'] === 'M' ) // Message to myself
      {
         if ( $full_details )
            $user_str = user_reference( REF_LINK, 1, '', $my_row );
         else
            $user_str = $row['other_name'];
      }
      else if ( $row['other_ID'] > 0 )
         $user_str = user_reference( REF_LINK, 1, '',
            $row['other_ID'], $row['other_name'], $row['other_handle'] );
      else
         $user_str = $row['other_name']; // server-msg or unknown

      return $user_str;
   }//message_build_user_string

   /*!
    * \brief Read message-list.
    * \param $order (default sort on me.mid equals to sort on date) !!
    * \param $extra_querysql QuerySQL-object to extend query
    * \return arr( msg-row, ... )
    */
   public static function message_list_query( $my_id, $folderstring='all', $order=' ORDER BY me.mid', $limit='', $extra_querysql=null )
   {
      /**
       * N.B.: On 2007-10-15, we have found, in the DGS database,
       *  30 records of MessageCorrespondents with .mid == 0
       *  all between "2004-06-03 09:25:17" and "2006-08-10 20:40:31".
       * While this should not have occured, those "lost" records can disturb
       *  some queries like this one where .mid is compared to .ReplyTo which
       *  may be 0 (meaning "no reply").
       * We have strengthened this query but also manually changed the faulty
       *  .mid from 0 to -9999 (directly in the database) to move them apart.
       **/
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'M.Type', 'M.Flags', 'M.Thread', 'M.Level', 'M.Subject', 'M.Game_ID',
         'UNIX_TIMESTAMP(M.Time) AS Time',
         "IF(NOT ISNULL(previous.mid),".FLOW_ANSWER.",0)" .
             "+IF(me.Replied='Y' OR other.Replied='Y',".FLOW_ANSWERED.",0) AS flow",
         'me.mid', 'me.Replied', 'me.Sender', 'me.Folder_nr AS folder',
         "IF(me.Sender='M',' ',otherP.Name) AS other_name", // the ' ' helps to sort
         'otherP.ID AS other_ID',
         'otherP.Handle AS other_handle' );
      $qsql->add_part( SQLP_FROM,
         'Messages AS M',
         'INNER JOIN MessageCorrespondents AS me USE INDEX (uid) ON M.ID=me.mid', // prefer uid-index as reported by mysql-SLOW-log
         'LEFT JOIN MessageCorrespondents AS other ON other.mid=me.mid AND other.Sender!=me.Sender',
         'LEFT JOIN Players AS otherP ON otherP.ID=other.uid',
         'LEFT JOIN MessageCorrespondents AS previous ON M.ReplyTo>0 AND previous.mid=M.ReplyTo AND previous.uid='.$my_id );
      $qsql->add_part( SQLP_WHERE, "me.uid=$my_id" );
      if ( $folderstring != "all" && $folderstring != '' )
         $qsql->add_part( SQLP_WHERE, "me.Folder_nr IN ($folderstring)" );
      $qsql->merge( $extra_querysql );
      $query = $qsql->get_select() . $order . $limit;

      $result = db_query( 'MLB.message_list_query', $query );

      $arr_msg = array();
      while ( $row = mysql_fetch_assoc($result) )
         $arr_msg[] = $row;
      mysql_free_result($result);

      return $arr_msg;
   }//message_list_query

   public static function load_cache_message_list( $dbgmsg, $uid, $folderstring='all', $order=' ORDER BY me.mid', $arg_limit=0 )
   {
      $dbgmsg .= ".MLB:load_cache_message_list($uid,$folderstring,$order,$arg_limit)";
      $key = "Messages.$uid";

      $arr_msg = DgsCache::fetch( $dbgmsg, CACHE_GRP_MSGLIST, $key );
      if ( is_null($arr_msg) )
      {
         $limit = ( $arg_limit ) ? ' LIMIT '.((int)$arg_limit) : '';
         $arr_msg = self::message_list_query( $uid, $folderstring, $order, $limit );
         DgsCache::store( $dbgmsg, CACHE_GRP_MSGLIST, $key, $arr_msg, 4*SECS_PER_HOUR );
      }

      return $arr_msg;
   }//load_cache_message_list

} // end of 'MessageListBuilder'



 /*!
  * \class MessageControl
  *
  * \brief Controller-Class to handle message-stuff.
  */
class MessageControl
{
   private $folders;
   private $allow_bulk;
   private $mpg_type;
   private $mpg_gid;
   private $mpg_col;
   private $arr_mpg_users;

   private $dgs_message;
   private $max_games_check;

   public function __construct( $folders, $allow_bulk, $mpg_type=0, $mpg_gid=0, $mpg_col='', $arr_mpg_users=null )
   {
      $this->folders = $folders;
      $this->allow_bulk = $allow_bulk;
      $this->mpg_type = (int)$mpg_type;
      $this->mpg_gid = (int)$mpg_gid;
      $this->mpg_col = $mpg_col;
      $this->arr_mpg_users = $arr_mpg_users;

      $this->dgs_message = new DgsMessage();
      $this->max_games_check = new MaxGamesCheck();
   }

   public function get_dgs_message()
   {
      return $this->dgs_message;
   }

   public function get_max_games_check()
   {
      return $this->max_games_check;
   }

   // return: false=success, otherwise true on failure
   public function read_message_receivers( &$dgs_msg, $msg_type, $invitation_step, &$to_handles )
   {
      global $player_row;
      static $cache = array();
      $my_id = (int)@$player_row['ID'];
      $is_bulk_admin = self::is_bulk_admin();

      $to_handles = strtolower( str_replace(',', ' ', trim($to_handles)) );
      $arr_to = array_unique( preg_split( "/\s+/", $to_handles, null, PREG_SPLIT_NO_EMPTY ) );
      $cnt_to = count($arr_to);
      sort($arr_to);
      $to_handles = implode(' ', $arr_to); // lower-case
      $dgs_msg->clear_errors();

      if ( !isset($cache[$to_handles]) ) // need lower-case for check
      {
         $cache[$to_handles] = 1; // handle(s) checked

         if ( $cnt_to < 1 )
            return $dgs_msg->add_error( T_('Missing message receiver') );
         elseif ( $cnt_to > MAX_MSG_RECEIVERS )
            return $dgs_msg->add_error( sprintf( T_('Too much receivers (max. %s)'), MAX_MSG_RECEIVERS ) );
         else // single | multi
         {
            if ( $cnt_to > 1 && $msg_type == MSGTYPE_INVITATION )
               return $dgs_msg->add_error( T_('Only one receiver for invitation allowed!') );

            $sender_uid = $my_id; // sender
            list( $arr_receivers, $errors ) =
               DgsMessage::load_message_receivers( $msg_type, $invitation_step, $arr_to );
            $dgs_msg->add_errors( $errors );

            if ( $this->mpg_type > 0 && $this->mpg_gid > 0 && is_null($this->arr_mpg_users) )
               $this->arr_mpg_users = GamePlayer::load_users_for_mpgame(
                  $this->mpg_gid, $this->mpg_col, /*skip-myself*/true, $tmp_arr );

            $arr_handles = array();
            foreach ( $arr_receivers as $handle => $user_row )
            {
               $uid = $user_row['ID'];
               if ( $uid == $sender_uid && $cnt_to > 1 )
                  $dgs_msg->add_error( ErrorCode::get_error_text('bulkmessage_self') );

               $dgs_msg->add_recipient( $user_row );
               $arr_handles[] = $user_row['Handle']; // original case

               // MPG-message only allows MPG-players as recipients
               if ( $this->mpg_type > 0 && $this->mpg_gid > 0 )
               {
                  $is_mpg_player = ( is_array($this->arr_mpg_users) )
                     ? isset($this->arr_mpg_users[$uid])
                     : GamePlayer::exists_game_player($this->mpg_gid, $uid); // fallback-check
                  if ( !$is_mpg_player )
                     $dgs_msg->add_error(
                        sprintf( T_('User [%s] is no participant of multi-player-game.'), $user_row['Handle'] ));
               }
            }
            $to_handles = implode(' ', $arr_handles); // reset original case

            // bulk-message only allowed for MPG|admin as sender
            if ( $dgs_msg->count_recipients() > 1 )
            {
               $check_flags = ( $this->mpg_type == MPGMSG_INVITE ) ? GPFLAG_MASTER : 0;
               if ( !$is_bulk_admin && !( $this->mpg_type > 0 && GamePlayer::exists_game_player($this->mpg_gid, $sender_uid, $check_flags) ) )
                  $dgs_msg->add_error( ErrorCode::get_error_text('bulkmessage_forbidden') );
            }
         }
      }

      return ( $dgs_msg->count_errors() > 0 );
   }//read_message_receivers

   /*!
    * \brief Checks and if no error occured performs message-actions.
    * \param $arg_to single or multi-receivers
    * \param $input map with input from URL with the following keys:
    *    # see also 'message.php' (URL-input-args)
    *    'action' = send_msg | accept_inv | decline_inv
    *    'senderid' = uid sending message (mandatory)
    *    'folder' = target-folder-id (optional)
    *    'reply' = message-id to reply to (mandatory for accept_inv|decline_inv)
    *    'mpgid' = game-id for MP-game (optional)
    *    'subject' = message-title (mandatory for send_msg)
    *    'message' = message-textbody (optional)
    *    'gid' = game-id of invitation (mandatory for accept_inv|decline_inv)
    *    'disputegid' = if set, game-id for dispute
    * \return 0=success for sending simple message;
    *         msg_gid (>0) = success for sending bulk-mpg-message;
    *         otherwise array with error-texts on check-failure
    *
    * \note IMPORTANT NOTE: caller needs to open TA with HOT-section!!
    */
   public function handle_send_message( $arg_to, $msg_type, $input )
   {
      global $player_row;

      $my_id = (int)@$player_row['ID'];
      if ( $my_id <= GUESTS_ID_MAX )
         return array( ErrorCode::get_error_text('not_allowed_for_guest') );

      $new_folder = (int)@$input['folder'];

      $sender_id = (int)@$input['senderid'];
      if ( $sender_id > 0 && $my_id != $sender_id )
         return array( ErrorCode::get_error_text('user_mismatch') );

      $prev_mid = max( 0, (int)@$input['reply']); //ID of message replied.
      $accepttype = ( @$input['action'] == 'accept_inv' );
      $declinetype = ( @$input['action'] == 'decline_inv' );
      $disputegid = -1;
      if ( $msg_type == MSGTYPE_INVITATION )
      {
         $disputegid = (int)@$input['disputegid'];
         if ( $disputegid < 0 )
            $disputegid = 0;
      }
      $invitation_step = $accepttype || $declinetype || ($disputegid > 0); //not needed: || ($msg_type == MSGTYPE_INVITATION)

      // find receiver of the message
      if ( $this->read_message_receivers( $this->dgs_message, $msg_type, $invitation_step, $arg_to ) )
         return $this->dgs_message->get_errors();

      // check own/opp max-games
      $opponent_row = $this->dgs_message->get_recipient();
      if ( ($msg_type == MSGTYPE_INVITATION) || $accepttype )
      {
         $chk_errors = $this->check_max_games($opponent_row);
         if ( count($chk_errors) )
         {
            foreach ( $chk_errors as $err )
               $this->dgs_message->add_error( $err );
            return $this->dgs_message->get_errors();
         }
      }

      $subject = trim(@$input['subject']);
      $message = @$input['message'];

      if ( (string)$subject == '' )
      {
         $this->dgs_message->add_error( T_('Missing message subject') );
         return $this->dgs_message->get_errors();
      }

      // Update database

      $msg_gid = 0;
      if ( $this->dgs_message->has_recipient() ) // single-receiver
      {
         $opponent_ID = $opponent_row['ID'];

         if ( $msg_type == MSGTYPE_INVITATION )
         {
            $msg_gid = make_invite_game($player_row, $opponent_row, $disputegid);
            $subject = ( $disputegid > 0 ) ? 'Game invitation dispute' : 'Game invitation';
         }
         else if ( $accepttype )
         {
            $msg_gid = (int)@$input['gid'];
            $gids = accept_invite_game( $msg_gid, $player_row, $opponent_row );
            $msg_gid = $gids[0];
            $subject = 'Game invitation accepted';
         }
         else if ( $declinetype )
         {
            // game will be deleted
            $msg_gid = (int)@$input['gid'];
            if ( !GameHelper::delete_invitation_game( 'send_message.decline', $msg_gid, $my_id, $opponent_ID ) )
               exit;
            $subject = 'Game invitation decline';
         }
         else if ( $this->mpg_type == MPGMSG_INVITE )
         {
            $msg_gid = (int)@$input['mpgid'];
         }

         $to_uids = $opponent_ID;
      }
      else // multi-receiver (bulk)
      {
         if ( !$this->allow_bulk )
         {
            $this->dgs_message->add_error( ErrorCode::get_error_text('bulkmessage_forbidden') );
            return $this->dgs_message->get_errors();
         }

         $to_uids = array();
         $recipients = $this->dgs_message->get_recipients();
         foreach ( $recipients as $user_row )
            $to_uids[] = (int)@$user_row['ID'];
      }

      // Send message

      send_message( 'send_message', $message, $subject
         , $to_uids, '', /*notify: $opponent_row['Notify'] == 'NONE'*/true
         , $my_id, $msg_type, $msg_gid
         , $prev_mid, ($disputegid > 0 ? MSGTYPE_DISPUTED : '')
         , isset($this->folders[$new_folder])
               ? $new_folder
               : ( $invitation_step ? FOLDER_MAIN : MOVEMSG_REPLY_TO_MAIN_FOLDER )
         );

      return ( $this->mpg_type == MPGMSG_INVITE && $msg_gid > 0 ) ? $msg_gid : 0;
   }//handle_send_message

   // return non-null error-arr checking on OWN/opponents max-games
   public function check_max_games( $opp_row )
   {
      $errors = array();

      if ( !$this->max_games_check->allow_game_start() )
         $errors[] = $this->max_games_check->get_error_text(false);

      if ( !is_null($opp_row) )
      {
         $oppMaxGamesCheck = new MaxGamesCheck( (int)@$opp_row['X_OppGamesCount'] );
         if ( !$oppMaxGamesCheck->allow_game_start() )
            $errors[] = ErrorCode::get_error_text('max_games_opp');
      }

      return $errors;
   }//check_max_games


   // ------------ static functions ----------------------------

   private static function is_bulk_admin()
   {
      global $player_row;
      return ( @$player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_FORUM|ADMIN_GAME) );
   }

} // end of 'MessageControl'

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/rating.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'include/classlib_game.php';
require_once 'include/message_functions.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once "include/rating.php";
require_once 'include/utilities.php';


{
   $handle_add_game = ( @$_REQUEST['add_game'] || @$_REQUEST['save_template'] );

   if( $handle_add_game )
      disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];

   $arg_viewmode = @$_REQUEST['view'];

   // load template for profile
   $prof_tmpl_id = (int)@$_REQUEST['tmpl'];
   if( $prof_tmpl_id > 0 )
   {
      $profile = Profile::load_profile( $prof_tmpl_id, $my_id ); // loads only if user-id correct
      if( is_null($profile) )
         error('invalid_profile', "new_game.check.profile($prof_tmpl_id)");

      // check profile-type vs. msg-mode (also allow invite-templates)
      if( $profile->Type != PROFTYPE_TMPL_NEWGAME && $profile->Type != PROFTYPE_TMPL_INVITE )
         error('invalid_profile', "new_game.check.profile.type($prof_tmpl_id,{$profile->Type})");

      $profile_template = ProfileTemplate::decode( $profile->Type, $profile->get_text(/*raw*/true) );
      $profile_template->fill( $_REQUEST, PROFTYPE_TMPL_NEWGAME );
      if( $profile->Type == PROFTYPE_TMPL_INVITE )
         $profile_template->fill_new_game_with_invite( $_REQUEST, PROFTYPE_TMPL_NEWGAME );
      $need_redraw = true;

      // allow template-conversion for other views
      $tmpl_suffix = URI_AMP . "tmpl=$prof_tmpl_id";
   }
   else
   {
      $tmpl_suffix = '';
      $need_redraw = @$_REQUEST['rematch'];
   }

   $viewmode = (int) get_request_arg('view', GSETVIEW_SIMPLE);
   if( is_numeric($arg_viewmode) && $viewmode != (int)$arg_viewmode ) // view-URL-arg has prio over template
      $viewmode = (int)$arg_viewmode;
   if( $viewmode < 0 || $viewmode > MAX_GSETVIEW )
      $viewmode = GSETVIEW_SIMPLE;
   if( $viewmode != GSETVIEW_SIMPLE && @$player_row['RatingStatus'] == RATING_NONE )
      error('multi_player_need_initial_rating',
            "new_game.check.viewmode_rating($my_id,$viewmode,{$player_row['RatingStatus']})");

   if( $handle_add_game )
      $errors = handle_add_game( $my_id, $viewmode );
   else
      $errors = array();


   // handle shape-game (passing-on for new-games)
   $shape_id = (int)get_request_arg('shape');
   $shape_snapshot = get_request_arg('snapshot');
   $shape_url_suffix = ( $shape_id > 0 && $shape_snapshot )
      ? URI_AMP."shape=$shape_id".URI_AMP."snapshot=".urlencode($shape_snapshot)
      : '';

   $my_rating = @$player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
      && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $page = "new_game.php?";
   if( $viewmode == GSETVIEW_MPGAME )
      $title = T_('Add new game#mpg');
   else
      $title = T_('Add new game to waiting room');
   start_page($title, true, $logged_in, $player_row );
   echo "<h3 class=Header>", sprintf( "%s (%s)", $title, get_gamesettings_viewmode($viewmode) ), "</h3>\n";

   $maxGamesCheck = new MaxGamesCheck();
   if( $maxGamesCheck->allow_game_start() )
   {
      echo $maxGamesCheck->get_warn_text();

      if( count($errors) > 0 )
      {
         echo "<br>\n<table><tr>",
            buildErrorListString( T_('There have been some errors'), array_unique($errors), 1 ),
            "</tr></table>";
         $need_redraw = true;
      }

      add_new_game_form( 'addgame', $viewmode, $iamrated, $need_redraw ); //==> ID='addgameForm'
   }
   else
      echo $maxGamesCheck->get_error_text();


   $menu_array = array();
   $menu_array[T_('New game')] = 'new_game.php?view='.GSETVIEW_SIMPLE . $shape_url_suffix . $tmpl_suffix;
   $menu_array[T_('Shapes#shape')] = 'list_shapes.php';
   ProfileTemplate::add_menu_link( $menu_array );

   $menu_array[T_('New expert game')] = 'new_game.php?view='.GSETVIEW_EXPERT . $shape_url_suffix . $tmpl_suffix;
   $menu_array[T_('New fair-komi game')] = 'new_game.php?view='.GSETVIEW_FAIRKOMI . $shape_url_suffix . $tmpl_suffix;
   if( @$player_row['RatingStatus'] != RATING_NONE )
      $menu_array[T_('New multi-player-game')] = 'new_game.php?view='.GSETVIEW_MPGAME . $shape_url_suffix . $tmpl_suffix;

   end_page(@$menu_array);
}//main


function add_new_game_form( $form_id, $viewmode, $iamrated, $need_redraw )
{
   $addgame_form = new Form( $form_id, 'new_game.php', FORM_POST );

   if( $need_redraw )
      game_settings_form($addgame_form, GSET_WAITINGROOM, $viewmode, $iamrated, 'redraw', $_REQUEST );
   else
      game_settings_form($addgame_form, GSET_WAITINGROOM, $viewmode, $iamrated);

   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'TAB', 'CELL', 1, '', // align submit buttons
         'SUBMITBUTTON', 'add_game', T_('Add Game'),
         'TEXT', span('BigSpace'),
         'SUBMITBUTTON', 'save_template', T_('Save Template'), ));

   $addgame_form->echo_string(1);
} //add_new_game_form



function handle_add_game( $my_id, $viewmode )
{
   global $player_row, $NOW;

   $gsc = GameSetupChecker::check_fields( $viewmode );
   if( $gsc->has_errors() )
   {
      $gsc->add_default_values_info();
      return $gsc->get_errors();
   }

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );
   $shape_id = (int)@$_REQUEST['shape'];
   $shape_snapshot = @$_REQUEST['snapshot'];

   $cat_handicap_type = @$_REQUEST['cat_htype'];
   $is_fairkomi = false;
   switch( (string)$cat_handicap_type )
   {
      case CAT_HTYPE_CONV:
         if( !$iamrated )
            error('no_initial_rating');
         $handicap_type = HTYPE_CONV;
         $handicap = 0; //further computing
         $komi = 0.0;
         break;

      case CAT_HTYPE_PROPER:
         if( !$iamrated )
            error('no_initial_rating');
         $handicap_type = HTYPE_PROPER;
         $handicap = 0; //further computing
         $komi = 0.0;
         break;

      case CAT_HTYPE_MANUAL:
         $handicap_type = @$_REQUEST['color_m'];
         if( empty($handicap_type) )
            $handicap_type = HTYPE_NIGIRI;

         $handicap = (int)@$_REQUEST['handicap_m'];
         $komi = (float)@$_REQUEST['komi_m'];
         break;

      case CAT_HTYPE_FAIR_KOMI:
         $is_fairkomi = true;
         $handicap_type = @$_REQUEST['fk_htype'];
         if( !preg_match("/^(".CHECK_HTYPES_FAIRKOMI.")$/", $handicap_type) )
            error('invalid_args', "new_game.handle_add_game.check.fairkomi_htype($handicap_type)");
         $handicap = 0;
         $komi = 0.0;
         break;

      default:
         $cat_handicap_type = CAT_HTYPE_MANUAL;
         $handicap_type = HTYPE_NIGIRI;
         $handicap = (int)@$_REQUEST['handicap_m'];
         $komi = (float)@$_REQUEST['komi_m'];
         break;
   }

   if( !($komi <= MAX_KOMI_RANGE && $komi >= -MAX_KOMI_RANGE) )
      error('komi_range', "new_game.handle_add_game.check.komi($komi)");
   if( floor(2 * $komi) != 2 * $komi ) // check for x.0|x.5
      error('komi_bad_fraction', "new_game.handle_add_game.check.komi.fraction($komi)");

   if( !($handicap <= MAX_HANDICAP && $handicap >= 0) )
      error('handicap_range', "new_game.handle_add_game.check.handicap($handicap)");

   if( $viewmode < 0 || $viewmode > MAX_GSETVIEW )
      error('invalid_args', "new_game.handle_add_game.check.viewmode($viewmode)");

   // ruleset
   $ruleset = @$_REQUEST['ruleset'];

   // komi adjustment
   $adj_komi = (float)@$_REQUEST['adj_komi'];
   if( $is_fairkomi )
      $adj_komi = 0;
   if( abs($adj_komi) > MAX_KOMI_RANGE )
      $adj_komi = ($adj_komi<0 ? -1 : 1) * MAX_KOMI_RANGE;
   if( floor(2 * $adj_komi) != 2 * $adj_komi ) // round to x.0|x.5
      $adj_komi = ($adj_komi<0 ? -1 : 1) * round(2 * abs($adj_komi)) / 2.0;

   $jigo_mode = (string)@$_REQUEST['jigo_mode'];
   if( $jigo_mode == '' )
      $jigo_mode = JIGOMODE_KEEP_KOMI;
   elseif( !preg_match("/^(".CHECK_JIGOMODE.")$/", $jigo_mode) )
      error('invalid_args', "new_game.handle_add_game.check.jigo_mode($jigo_mode)");

   // handicap adjustment
   $adj_handicap = (int)@$_REQUEST['adj_handicap'];
   if( $is_fairkomi )
      $adj_handicap = 0;
   if( abs($adj_handicap) > MAX_HANDICAP )
      $adj_handicap = ($adj_handicap<0 ? -1 : 1) * MAX_HANDICAP;

   $min_handicap = min( MAX_HANDICAP, max( 0, (int)@$_REQUEST['min_handicap'] ));
   if( $is_fairkomi )
      $min_handicap = 0;

   $max_handicap = (int)@$_REQUEST['max_handicap'];
   if( $max_handicap > MAX_HANDICAP )
      $max_handicap = -1; // don't save potentially changeable "default"

   if( $max_handicap >= 0 && $min_handicap > $max_handicap )
      swap( $min_handicap, $max_handicap );

   // multi-player
   $game_players = (string)@$_REQUEST['game_players'];
   $game_type = MultiPlayerGame::determine_game_type($game_players);
   if( is_null($game_type) )
      error('invalid_args', "new_game.handle_add_game.check.game_players($game_players)");
   $is_std_go = ( $game_type == GAMETYPE_GO );
   if( $is_std_go && $viewmode == GSETVIEW_MPGAME )
      error('invalid_args', "new_game.handle_add_game.check.game_players.viewmode($viewmode,$game_players)");

   if( $is_fairkomi ) // fair-komi checks
   {
      if( $viewmode != GSETVIEW_FAIRKOMI )
         error('invalid_args', "new_game.handle_add_game.check.fairkomi.viewmode($viewmode,$game_type)");
      if( !$is_std_go )
         error('invalid_args', "new_game.handle_add_game.check.game_type.fairkomi_no_mpg");
   }


   $maxGamesCheck = new MaxGamesCheck();
   $max_games = $maxGamesCheck->get_allowed_games(NEWGAME_MAX_GAMES);
   $nrGames = max( 1, (int)@$_REQUEST['nrGames']);
   if( $nrGames > NEWGAME_MAX_GAMES )
      error('invalid_args', "new_game.handle_add_game.check.nr_games($nrGames)");
   elseif( $nrGames > $max_games )
      error('max_games', "new_game.handle_add_game.check.max_games.nr_games($nrGames,$max_games)");

   $size = min(MAX_BOARD_SIZE, max(MIN_BOARD_SIZE, (int)@$_REQUEST['size']));

   $byoyomitype = @$_REQUEST['byoyomitype'];
   $timevalue = @$_REQUEST['timevalue'];
   $timeunit = @$_REQUEST['timeunit'];

   $byotimevalue_jap = @$_REQUEST['byotimevalue_jap'];
   $timeunit_jap = @$_REQUEST['timeunit_jap'];
   $byoperiods_jap = @$_REQUEST['byoperiods_jap'];

   $byotimevalue_can = @$_REQUEST['byotimevalue_can'];
   $timeunit_can = @$_REQUEST['timeunit_can'];
   $byoperiods_can = @$_REQUEST['byoperiods_can'];

   $byotimevalue_fis = @$_REQUEST['byotimevalue_fis'];
   $timeunit_fis = @$_REQUEST['timeunit_fis'];

   list($hours, $byohours, $byoperiods) =
      interpret_time_limit_forms($byoyomitype, $timevalue, $timeunit,
                                 $byotimevalue_jap, $timeunit_jap, $byoperiods_jap,
                                 $byotimevalue_can, $timeunit_can, $byoperiods_can,
                                 $byotimevalue_fis, $timeunit_fis);

   if( $hours<1 && ($byohours<1 || $byoyomitype == BYOTYPE_FISCHER) )
      error('time_limit_too_small');


   if( ($rated = @$_REQUEST['rated']) != 'Y' || $player_row['RatingStatus'] == RATING_NONE )
      $rated = 'N';

   if( ENABLE_STDHANDICAP )
   {
      if( ($stdhandicap=@$_REQUEST['stdhandicap']) != 'Y' )
         $stdhandicap = 'N';
   }
   else
      $stdhandicap = 'N';

   if( ($weekendclock = @$_REQUEST['weekendclock']) != 'Y' )
      $weekendclock = 'N';

   if( $is_std_go )
      list( $MustBeRated, $rating1, $rating2 ) = parse_waiting_room_rating_range();
   else
   {
      // enforce defaults for template-saving
      list( $MustBeRated, $rating1, $rating2 ) = parse_waiting_room_rating_range( /*mpg*/true, '30 kyu', '9 dan' );
   }

   $min_rated_games = limit( (int)@$_REQUEST['min_rated_games'], 0, 999, 0 ); // 3-chars max.
   $same_opponent = (int)@$_REQUEST['same_opp'];


   // insert game (standard-game or multi-player-game)

   if( !$is_std_go ) // use defaults for MP-game
   {
      //$nrGames = 1;
      $handicap_type = HTYPE_NIGIRI;
      $handicap = 0;
      $komi = 6.5;
      $rated = 'N';
      //$min_rated_games = 0;
      //$same_opponent = -1; // same-opp only ONCE for Team-/Zen-Go
   }

   // handle shape-game implicit settings (error if invalid)
   // NOTE: same handling as for make_invite_game()-func in 'include/make_game.php'
   if( $shape_id > 0 )
   {
      $arr_shape = GameSnapshot::parse_check_extended_snapshot($shape_snapshot);
      if( !is_array($arr_shape) ) // overwrite with defaults
         error('invalid_snapshot', "new_game.handle_add_game.check.shape($shape_id,$shape_snapshot)");

      // implicit defaults for shape-game
      $size = (int)$arr_shape['Size'];
      $stdhandicap = 'N';
      $rated = 'N';
   }
   else
   {
      $shape_id = 0;
      $shape_snapshot = '';
   }


   // handle save-template
   if( @$_REQUEST['save_template'] )
   {
      // convert form-values into GameSetup to save as template
      $gs = new GameSetup( 0 );
      $gs->Handicaptype = $handicap_type; // HTYPE_...
      $gs->Handicap = $handicap;
      $gs->AdjustHandicap = $adj_handicap;
      $gs->MinHandicap = $min_handicap;
      $gs->MaxHandicap = ( $max_handicap < 0 ) ? 0 : $max_handicap;
      $gs->Komi = $komi;
      $gs->AdjustKomi = $adj_komi;
      $gs->JigoMode = $jigo_mode; // JIGOMODE_...
      $gs->MustBeRated = ( $MustBeRated == 'Y' ); // bool
      $gs->RatingMin = $rating1;
      $gs->RatingMax = $rating2;
      $gs->MinRatedGames = $min_rated_games;
      $gs->SameOpponent = $same_opponent;

      if( $cat_handicap_type == CAT_HTYPE_FAIR_KOMI )
         $gs->Komi = $gs->OppKomi = null; // start with empty komi-bids

      // additional games-fields to PLAY game
      $gs->ShapeID = $shape_id;
      $gs->ShapeSnapshot = $shape_snapshot;
      $gs->GamePlayers = $game_players;
      $gs->Ruleset = $ruleset; // RULESET_...
      $gs->Size = $size;
      $gs->Rated = ( $rated != 'N' ); // bool
      $gs->StdHandicap = ( $stdhandicap == 'Y' ); // bool
      $gs->Maintime = $hours;
      $gs->Byotype = $byoyomitype; // BYOTYPE_...
      $gs->Byotime = $byohours;
      $gs->Byoperiods = $byoperiods;
      $gs->WeekendClock = ( $weekendclock == 'Y' ); // bool

      // new-game fields
      $gs->NumGames = $nrGames;
      $gs->ViewMode = $viewmode;

      $comment = trim( @$_REQUEST['comment'] );
      $tmpl = ProfileTemplate::new_template_game_setup_newgame( $comment );
      $tmpl->GameSetup = $gs;

      jump_to("templates.php?cmd=new".URI_AMP."type={$tmpl->TemplateType}".URI_AMP."data=" . urlencode( $tmpl->encode() ));
   }//save-template


   // add waiting-room game
   $query_mpgame = $query_wroom = '';
   if( !$is_std_go ) // mp-game
   {
      $query_mpgame = "INSERT INTO Games SET " .
         "Black_ID=$my_id, " . // game-master
         "White_ID=0, " .
         "ToMove_ID=$my_id, " . // appear as status-game
         "Starttime=FROM_UNIXTIME($NOW), " .
         "Lastchanged=FROM_UNIXTIME($NOW), " .
         "ShapeID=$shape_id, " .
         "ShapeSnapshot='" . mysql_addslashes($shape_snapshot) . "', " .
         "GameType='" . mysql_addslashes($game_type) . "', " .
         "GamePlayers='" . mysql_addslashes($game_players) . "', " .
         "Ruleset='" . mysql_addslashes($ruleset) . "', " .
         "Size=$size, " .
         "Handicap=$handicap, " .
         "Komi=ROUND(2*($komi))/2, " .
         "Status='".GAME_STATUS_SETUP."', " .
         "Maintime=$hours, " .
         "Byotype='$byoyomitype', " .
         "Byotime=$byohours, " .
         "Byoperiods=$byoperiods, " .
         "Black_Maintime=$hours, " .
         "White_Maintime=$hours, " .
         "WeekendClock='$weekendclock', " .
         "StdHandicap='$stdhandicap', " .
         "Rated='$rated'";
   }
   else // std-game
   {
      $query_wroom = "INSERT INTO Waitingroom SET " .
         "uid=$my_id, " .
         "nrGames=$nrGames, " .
         "Time=FROM_UNIXTIME($NOW), " .
         "GameType='" . mysql_addslashes($game_type) . "', " .
         "Ruleset='" . mysql_addslashes($ruleset) . "', " .
         "Size=$size, " .
         "Komi=ROUND(2*($komi))/2, " .
         "Handicap=$handicap, " .
         "Handicaptype='" . mysql_addslashes($handicap_type) . "', " .
         "AdjKomi=$adj_komi, " .
         "JigoMode='" . mysql_addslashes($jigo_mode) . "', " .
         "AdjHandicap=$adj_handicap, " .
         "MinHandicap=$min_handicap, " .
         ($max_handicap < 0 ? '' : "MaxHandicap=$max_handicap, " ) .
         "Maintime=$hours, " .
         "Byotype='$byoyomitype', " .
         "Byotime=$byohours, " .
         "Byoperiods=$byoperiods, " .
         "WeekendClock='$weekendclock', " .
         "Rated='$rated', " .
         "StdHandicap='$stdhandicap', " .
         "MustBeRated='$MustBeRated', " .
         "RatingMin=$rating1, " .
         "RatingMax=$rating2, " .
         "MinRatedGames=$min_rated_games, " .
         "SameOpponent=$same_opponent, " .
         "ShapeID=$shape_id, " .
         "ShapeSnapshot='" . mysql_addslashes($shape_snapshot) . "', " .
         "Comment=\"" . mysql_addslashes(trim(get_request_arg('comment'))) . "\"";
   }

   ta_begin();
   {//HOT-section for creating waiting-room game
      $gid = 0;
      if( $query_wroom )
         db_query( 'new_game.handle_add_game.insert.waitingroom', $query_wroom );
      else if( $query_mpgame )
      {
         $result = db_query( 'new_game.handle_add_game.insert.game', $query_mpgame, 'mysql_insert_game' );
         if( mysql_affected_rows() != 1)
            error('mysql_start_game', 'new_game.handle_add_game.insert.game2');
         $gid = mysql_insert_id();
         if( $gid <= 0 )
            error('internal_error', "new_game.handle_add_game.insert.game.err($gid)");

         MultiPlayerGame::init_multi_player_game( "add_to_waitingroom",
            $gid, $my_id, MultiPlayerGame::determine_player_count($game_players) );
      }
   }
   ta_end();

   $msg = urlencode(T_('Game added!'));

   if( $gid > 0 )
      jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=$msg");
   else
      jump_to("waiting_room.php?showall=1".URI_AMP."sysmsg=$msg");
   return array(); //for safety (no-errors)
}//handle_add_game

?>

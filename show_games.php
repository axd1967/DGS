<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/game_functions.php" );
require_once( "include/rating.php" );
require_once( "include/filter.php" );
require_once( "include/classlib_profile.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/time_functions.php' );
require_once( 'tournaments/include/tournament_games.php' );

$GLOBALS['ThePage'] = new Page('GamesList');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");
   $my_id = $player_row['ID'];

   $ext_tid = (int)@$_REQUEST['tid']; // tourney
   if( !ALLOW_TOURNAMENTS || $ext_tid < 0 ) $ext_tid = 0;

   $observe = @$_GET['observe']; // all | user (only my_id)
   $observe_all = false;
   if( $observe ) //OB=OU+OA
   {
      $observe = ( is_numeric($observe) && $observe == $my_id ) ? $observe : 'all';
      $observe_all = !is_numeric($observe); //OA
      $finished = false; //by definition
      $uid = $my_id; //by definition
      $all = false;
   }
   else //FU+RU+FA+RA
   {
      $finished = isset($_GET['finished']);
      $uid = @$_GET['uid'];
      $all = ($uid == 'all');
      if( !$all ) //FU+RU
      {
         if( !get_request_user( $uid, $uhandle) )
         {
            $uid= $my_id;
            $user_row=& $player_row;
         }
         else
         {
            if( $uhandle )
               $where = "Handle='".mysql_addslashes($uhandle)."'";
            else if( $uid > 0 )
               $where = "ID=$uid";
            else
               error('no_uid');

            $user_row = mysql_single_fetch( "show_games.find_player($uid,$uhandle)",
                  "SELECT ID, Name, Handle, Rating2 FROM Players WHERE $where" )
               or error('unknown_user', "show_games.find_player2($uid,$uhandle)");

            $uid = $user_row['ID'];
         }
      }
   }
   $is_mine = ($my_id == $uid); // my games-list
   $is_other = ( $uid > 0 && !$is_mine );
   $running = !$observe && !$finished;

   $page = 'show_games.php?';
   if( $observe )
   {
      $tableid = 'observed';
      $column_set_name = ($observe_all) ? CFGCOLS_GAMES_OBSERVED_ALL : CFGCOLS_GAMES_OBSERVED;
      $fprefix = 'o';
      $profile_type = ($observe_all) ? PROFTYPE_FILTER_GAMES_OBSERVED_ALL : PROFTYPE_FILTER_GAMES_OBSERVED;
   }
   else if( $finished )
   {
      $tableid = 'finished';
      $column_set_name = ($all) ? CFGCOLS_GAMES_FINISHED_ALL : CFGCOLS_GAMES_FINISHED_USER;
      $fprefix = 'f';
      if( $all )
         $profile_type = PROFTYPE_FILTER_GAMES_FINISHED_ALL;
      elseif( $is_mine )
         $profile_type = PROFTYPE_FILTER_GAMES_FINISHED_MY;
      else
         $profile_type = PROFTYPE_FILTER_GAMES_FINISHED_OTHER;
   }
   else if( $running )
   {
      $tableid = 'running';
      $column_set_name = ($all) ? CFGCOLS_GAMES_RUNNING_ALL : CFGCOLS_GAMES_RUNNING_USER;
      $fprefix = 'r';
      if( $all )
         $profile_type = PROFTYPE_FILTER_GAMES_RUNNING_ALL;
      elseif( $is_mine )
         $profile_type = PROFTYPE_FILTER_GAMES_RUNNING_MY;
      else
         $profile_type = PROFTYPE_FILTER_GAMES_RUNNING_OTHER;
   }

   // load table-columns
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, $column_set_name );

   $restrict_games = '';
   if( RESTRICT_SHOW_GAMES_ALL && $all )
      $restrict_games = ($finished) ? min(30, 5*RESTRICT_SHOW_GAMES_ALL) : RESTRICT_SHOW_GAMES_ALL;

   // init search profile
   $search_profile = new SearchProfile( $my_id, $profile_type );
   $gfilter = new SearchFilter( $fprefix, $search_profile );
   $named_filters = 'rated|won';
   if( !$observe && !$all ) //FU+RU
      $named_filters .= '|opp_hdl';
   $search_profile->register_regex_save_args( $named_filters ); // named-filters FC_FNAME
   $gtable = new Table( $tableid, $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $gtable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   //Filter & add_filter(int id, string type, string dbfield, [bool active=false], [array config])
   $gfilter->add_filter( 1, 'Numeric', 'Games.ID', true, array( FC_SIZE => 8 ) );
   if( $finished && !$all ) //FU
      $gfilter->add_filter(41, 'Boolean', 'Games.Flags>0 AND (Games.Flags & 2)', true );
   if( ALLOW_TOURNAMENTS )
      $gfilter->add_filter(32, 'Boolean', 'Games.tid>0', true,
            array( FC_LABEL => echo_image_tournament_info(1, true, true) ));
   $gfilter->add_filter( 6, 'Numeric', 'Games.Size', true,
         array( FC_SIZE => 3 ));
   $gfilter->add_filter( 7, 'Numeric', 'Games.Handicap', true,
         array( FC_SIZE => 3 ));
   $gfilter->add_filter( 8, 'Numeric', 'Games.Komi', true,
         array( FC_SIZE => 3 ));
   $gfilter->add_filter( 9, 'Numeric', 'Games.Moves', true,
         array( FC_SIZE => 4 ));
   $gfilter->add_filter(12, 'BoolSelect', 'Games.Weekendclock', true);
   $gfilter->add_filter(13, 'RelativeDate', 'Games.Lastchanged', true, // Games
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8, FC_DEFAULT => $restrict_games ));
   $gfilter->add_filter(14, 'RatedSelect', 'Games.Rated', true,
         array( FC_FNAME => 'rated' ));
   $gfilter->add_filter(43, 'Selection', build_ruleset_filter_array(), true);

   if( !$observe && !$all ) //FU+RU
   {
      $gfilter->add_filter( 3, 'Text',   'Opp.Name',   true);
      $gfilter->add_filter( 4, 'Text',   'Opp.Handle', true,
         array( FC_FNAME => 'opp_hdl' ));
      $gfilter->add_filter(16, 'Rating', 'Opp.Rating2', true);
   }
   if( $running && !$all ) //RU
   {
      $gfilter->add_filter( 5, 'Selection',   // filter on my color / my move
            array( T_('All#filter') => '',
                   T_('User B#filtercol')  => new QuerySQL( SQLP_HAVING, '!(X_Color&2)' ),
                   T_('User W#filtercol')  => new QuerySQL( SQLP_HAVING, '(X_Color&2)' ),
                   T_('User move#filtercol') => "Games.ToMove_ID=$uid",
                   T_('Opp move#filtercol')  => "Games.ToMove_ID<>$uid" ), // <- no db-index used
            true);
      $gfilter->add_filter(23, 'Rating', 'oppStartRating', true,
            array( FC_ADD_HAVING => 1 ));
      $gfilter->add_filter(36, 'Rating', 'userStartRating', true,
            array( FC_ADD_HAVING => 1 ));
      $gfilter->add_filter(15, 'RelativeDate', 'Opp.Lastaccess', true, // Players
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8 ) );
   }
   if( $finished ) //FU+FA
   {
      $gfilter->add_filter(10, 'Score', 'Games.Score', false,
            array( FC_SIZE => 3, FC_HIDE => 1 ));
      if( $all ) //FA
      {
         $gfilter->add_filter(27, 'Rating', 'Games.Black_End_Rating', true);
         $gfilter->add_filter(30, 'Rating', 'Games.White_End_Rating', true);
         $gfilter->add_filter(28, 'RatingDiff', 'blog.RatingDiff', true);
         $gfilter->add_filter(31, 'RatingDiff', 'wlog.RatingDiff', true);
      }
      else //FU
      {
         $gfilter->add_filter( 5, 'Selection',
               array( T_('All#filter') => '',
                      T_('B#filter')   => "Games.Black_ID=$uid",
                      T_('W#filter')   => "Games.White_ID=$uid" ),
               true);
         $gfilter->add_filter(11, 'Selection',
               array( T_('All#filter')  => '',
                      T_('Won')  => new QuerySQL( SQLP_HAVING, 'X_Score>0' ),
                      T_('Lost') => new QuerySQL( SQLP_HAVING, 'X_Score<0' ),
                      T_('Jigo') => 'Games.Score=0' ),
               true,
               array( FC_FNAME => 'won' ));
         $gfilter->add_filter(23, 'Rating', 'oppStartRating', true,
               array( FC_ADD_HAVING => 1 ));
         $gfilter->add_filter(24, 'Rating', 'oppEndRating', true,
               array( FC_ADD_HAVING => 1 ));
         $gfilter->add_filter(25, 'RatingDiff', 'oppRlog.RatingDiff', true);
         $gfilter->add_filter(36, 'Rating', 'userStartRating', true,
               array( FC_ADD_HAVING => 1 ));
         $gfilter->add_filter(37, 'Rating', 'userEndRating', true,
               array( FC_ADD_HAVING => 1 ));
         $gfilter->add_filter(38, 'RatingDiff', 'userRlog.RatingDiff', true);
      }
   }
   if( $observe || $all ) //OB+FA+RA
   {
      $gfilter->add_filter(17, 'Text',   'black.Name',   true);
      $gfilter->add_filter(18, 'Text',   'black.Handle', true);
      $gfilter->add_filter(19, 'Rating', 'black.Rating2', true);
      $gfilter->add_filter(26, 'Rating', 'Games.Black_Start_Rating', true);
      $gfilter->add_filter(20, 'Text',   'white.Name',   true);
      $gfilter->add_filter(21, 'Text',   'white.Handle', true);
      $gfilter->add_filter(22, 'Rating', 'white.Rating2', true);
      $gfilter->add_filter(29, 'Rating', 'Games.White_Start_Rating', true);
   }
   if( $observe_all ) //OA
   {
      $gfilter->add_filter(34, 'Numeric', 'X_ObsCount', true,
            array( FC_SIZE => 3, FC_ADD_HAVING => 1 ));
      $gfilter->add_filter(35, 'BoolSelect', 'X_MeObserved', true,
            array( FC_ADD_HAVING => 1 ));
   }
   $gfilter->init(); // parse current value from _GET

   // init table
   $gtable->register_filter( $gfilter );
   $gtable->add_or_del_column();

   // attach external URL-parameters to table
   $extparam = new RequestParameters();
   if( $observe )
      $extparam->add_entry( 'observe', $observe );
   else
   {
      $extparam->add_entry( 'uid', $uid );
      if( $finished )
         $extparam->add_entry( 'finished', 1 );
   }
   if( $ext_tid > 0 )
      $extparam->add_entry( 'tid', $ext_tid );
   $gtable->add_external_parameters( $extparam, true ); // also for hiddens

   // NOTE: check after add_or_del_column()-call
   // only activate if column shown for user to reduce server-load for page
   // avoiding additional outer-join on GamesNotes-table !!
   $show_notes = (LIST_GAMENOTE_LEN>0
         && !$observe && !$all && $is_mine); // FU+RU subset
   $load_notes = ($show_notes && $gtable->is_column_displayed(33) );
   $load_user_ratingdiff = $gtable->is_column_displayed(38);

   // NOTE: check after add_or_del_column()-call
   // only activate if column shown for user to reduce server-load for page
   // avoiding additional outer-join on Clock-table !!
   $load_remaining_time = ( $running && !$all
      && ($gtable->is_column_displayed(39) || $gtable->is_column_displayed(40)) );

/*****
 * Views-pages identification:
 *   views:
 *   - OB=OU+OA=observe (splitted in OU=user-observes, OA=observed-all)
 *   - FU=finished-user, FA=finished-all
 *   - RU=running-user, RA=running-all
 *
 *****
 * Database-columns FROM:
 * Notes:
 * - Games fields are all SELECTed and the FROM should stay without table prefix!
 * - Table prefix for the fields is needed when the SELECTed fields have a naming-clash.
 *   Then a prefix is needed in the FROM-clause and the field needs an alias, which is
 *   unique amongst all selected fields.
 * - Rating is common to Players and Ratinglog but only Players.Rating2 is SELECTed
 *
 * - The filters may use the 'tablename.' prefix.
 * - The sorts can't use the 'tablename.' prefix (must use alias), because of the possible UNION.
 *   Actually (FU+RU) may use the ?UNION
 *
 * Games (OB+FU+RU+FA+RA) AS Games:
 *   ID, Starttime, Lastchanged, mid, Black_ID, White_ID, ToMove_ID, Ruleset, Size, Komi, Handicap,
 *   Status, Moves, Black_Prisoners, White_Prisoners, Last_X, Last_Y, Last_Move, Flags, Score,
 *   Maintime, Byotype, Byotime, Byoperiods, Black_Maintime, White_Maintime,
 *   Black_Byotime, White_Byotime, Black_Byoperiods, White_Byoperiods, LastTicks, ClockUsed,
 *   Rated, StdHandicap, WeekendClock, Black_Start_Rating, White_Start_Rating,
 *   Black_End_Rating, White_End_Rating
 *
 * Players (OB+FA+RA) AS white, AS black - (FU+RU) AS Players(+UNION):
 *   ID, Handle, Password, Newpassword, Sessioncode, Sessionexpire, Lastaccess, LastMove,
 *   Registerdate, Hits, VaultCnt, VaultTime, Moves, Activity, Name, Email, Rank,
 *   SendEmail, Notify, MenuDirection, Adminlevel, Timezone, Nightstart, ClockUsed, ClockChanged,
 *   Rating, RatingMin, RatingMax, Rating2, InitialRating, RatingStatus, Open, Lang,
 *   VacationDays, OnVacation, Button, Running, Finished, RatedGames,
 *   Won, Lost, Translator, IP, Browser, Country, MayPostOnForum, TableMaxRows
 *
 * Observers (OB) AS Obs:
 *   ID, uid, gid
 *
 * Ratinglog (FA) AS blog, AS wlog - (FU) AS log:
 *   ID, uid, gid, Time, Rating, RatingMin, RatingMax, RatingDiff
 *
 * GamesNotes (FU+RU) AS Gnt:
 *   ID, gid, uid, Hidden, Notes
 *
 * TournamentGames (OB+FU+RU+FA+RA) AS TG:
 *   Status
 *
 *****
 * Views-columns usage:
 * Notes:
 * - '> ' indicates a column not common to all views, usage given for specific views
 * - When a column number is shared between two fields, they must be displayed
 *   inside different (not intersecting) "hide/show columns" groups i.e.:
 *   - (OU) => ColumnsGamesObserved
 *   - (OA) => ColumnsGamesObservedAll
 *   - (FA) => ColumnsGamesFinishedAll
 *   - (FU) => ColumnsGamesFinishedUser
 *   - (RA) => ColumnsGamesRunningAll
 *   - (RU) => ColumnsGamesRunningUser
 *
 * no: description of displayed info
 *  1:    ID
 *  2:    sgf
 *  3: >  FU+RU [oppName] (Opponent-Name)
 *  4: >  FU+RU [oppHandle] (Opponent-Handle)
 *  5: >  FU (User-Color-Graphic), RU (2-Colors-Graphic, who-to-move)
 *  6:    Size
 *  7:    Handicap
 *  8:    Komi
 *  9:    Moves
 * 10: >  FU+FA [Score] (Score)
 * 11: >  FU [User-Score AS X_Score] (Win-graphic) -> fname=won
 * 12:    Weekendclock
 * 13:    FU+FA [Games.Lastchanged] (End date), OB+RU+RA [Games.Lastchanged] (Last move)
 * 14:    [Rated AS X_Rated] (Rated) -> fname=rated
 * 15: >  RU [oppLastaccess] (Opponents-LastAccess)
 * 16: >  FU+RU [Rating2 AS oppRating] (Oppent-current-Rating)
 * 17: >  OB+FA+RA (Black-Name)
 * 18: >  OB+FA+RA (Black-Handle)
 * 19: >  OB+FA+RA (Black-Rating)
 * 20: >  OB+FA+RA (White-Name)
 * 21: >  OB+FA+RA (White-Handle)
 * 22: >  OB+FA+RA (White-Rating)
 * 23: >  FU+RU [oppStartRating] (Opponent-StartRating)
 * 24: >  FU [oppEndRating] (Opponent-EndRating)
 * 25: >  FU [oppRatingDiff] (Opponent-RatingDiff)
 * 26: >  OB+FA+RA (Black-StartRating)
 * 27: >  FA (Black-EndRating)
 * 28: >  FA (Black-RatingDiff)
 * 29: >  OB+FA+RA (White-StartRating)
 * 30: >  FA (White-EndRating)
 * 31: >  FA (White-RatingDiff)
 * 32:    (Link to game-info page)
 * 33: >  FU+RU [Notes AS X_Note] (Notes)
 * 34: >  OA [X_ObsCount] (Observer-count)
 * 35: >  OA [X_MeObserved] (My-Games-observed?)
 * 36: >  FU+RU [userStartRating] (User-StartRating)
 * 37: >  FU [userEndRating] (User-EndRating)
 * 38: >  FU [userRatingDiff] (User-RatingDiff)
 * 39: >  RU (my remaining time)
 * 40: >  RU (oppenent remaining time)
 * 41: >  FU (Indicator if there are (hidden) game-comments)
 * 42:    TournamentGames.Status
 * 43:    Ruleset
 *****/

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   // NOTE: The TABLE_NO_HIDEs are needed, because the columns are needed
   //       for the "static" filtering(!) of: Win/Rated; also see named-filters
   $gtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $gtable->add_tablehead(32, new TableHead( T_('Game information'), 'images/info.gif'), 'ImagesLeft', 0 ); // game-info
   if( $finished && !$all ) //FU
      $gtable->add_tablehead(41, new TableHead( T_('Hidden game comments'), 'images/game_comment.gif'), 'Image', 0 ); // game-comment
   $gtable->add_tablehead( 2, T_('sgf#header'), 'Sgf', TABLE_NO_SORT);
   if( $ext_tid )
      $gtable->add_tablehead(42, T_('TGame-Status#header'), 'Enum', 0 );
   if( $observe_all )
   {
      $gtable->add_tablehead(34, T_('#Observers#header'), 'NumberC', 0, 'X_ObsCount-');
      $gtable->add_tablehead(35, T_('Mine#header'), '', 0, 'X_MeObserved-');
   }
   if( !$observe && !$all ) //FU+RU ?UNION
   {
      if( $show_notes )
         $gtable->add_tablehead(33, T_('Notes#header'), '', 0, 'X_Note-');
   }

   if( $observe )
   {
      $gtable->add_tablehead(17, T_('Black name#header'), 'User', 0, 'blackName+');
      $gtable->add_tablehead(18, T_('Black userid#header'), 'User', 0, 'blackHandle+');
      $gtable->add_tablehead(26, T_('Black start rating#header'), 'Rating', 0, 'blackStartRating-');
      $gtable->add_tablehead(19, T_('Black rating#header'), 'Rating', 0, 'blackRating-');
      $gtable->add_tablehead(20, T_('White name#header'), 'User', 0, 'whiteName+');
      $gtable->add_tablehead(21, T_('White userid#header'), 'User', 0, 'whiteHandle+');
      $gtable->add_tablehead(29, T_('White start rating#header'), 'Rating', 0, 'whiteStartRating-');
      $gtable->add_tablehead(22, T_('White rating#header'), 'Rating', 0, 'whiteRating-');
   }
   else if( $finished ) //FU+FA ?UNION
   {
      if( $all ) //FA
      {
         $gtable->add_tablehead(17, T_('Black name#header'), 'User', 0, 'blackName+');
         $gtable->add_tablehead(18, T_('Black userid#header'), 'User', 0, 'blackHandle+');
         $gtable->add_tablehead(26, T_('Black start rating#header'), 'Rating', 0, 'blackStartRating-');
         $gtable->add_tablehead(27, T_('Black end rating#header'), 'Rating', 0, 'blackEndRating-');
         $gtable->add_tablehead(19, T_('Black rating#header'), 'Rating', 0, 'blackRating-');
         $gtable->add_tablehead(28, T_('Black rating diff#header'), 'Number', 0, 'blackDiff-');
         $gtable->add_tablehead(20, T_('White name#header'), 'User', 0, 'whiteName+');
         $gtable->add_tablehead(21, T_('White userid#header'), 'User', 0, 'whiteHandle+');
         $gtable->add_tablehead(29, T_('White start rating#header'), 'Rating', 0, 'whiteStartRating-');
         $gtable->add_tablehead(30, T_('White end rating#header'), 'Rating', 0, 'whiteEndRating-');
         $gtable->add_tablehead(22, T_('White rating#header'), 'Rating', 0, 'whiteRating-');
         $gtable->add_tablehead(31, T_('White rating diff#header'), 'Number', 0, 'whiteDiff-');
      }
      else //FU ?UNION
      {
         $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'oppName+');
         $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'oppHandle+');
         $gtable->add_tablehead(23, T_('Start rating#header'), 'Rating', 0, 'oppStartRating-');
         $gtable->add_tablehead(24, T_('End rating#header'), 'Rating', 0, 'oppEndRating-');
         $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'oppRating-');
         $gtable->add_tablehead(25, T_('Rating diff#header'), 'Number', 0, 'oppRatingDiff-');
         $gtable->add_tablehead(36, T_('User Start rating#header'), 'Rating', 0, 'userStartRating-');
         $gtable->add_tablehead(37, T_('User End rating#header'), 'Rating', 0, 'userEndRating-');
         $gtable->add_tablehead(38, T_('User Rating diff#header'), 'Number', 0, 'userRatingDiff-');
         $gtable->add_tablehead( 5, T_('Color#header'), 'Image', 0, 'X_Color+');
      }
   }
   else if( $running ) //RU+RA ?UNION
   {
      if( $all ) //RA
      {
         $gtable->add_tablehead(17, T_('Black name#header'), 'User', 0, 'blackName+');
         $gtable->add_tablehead(18, T_('Black userid#header'), 'User', 0, 'blackHandle+');
         $gtable->add_tablehead(26, T_('Black start rating#header'), 'Rating', 0, 'blackStartRating-');
         $gtable->add_tablehead(19, T_('Black rating#header'), 'Rating', 0, 'blackRating-');
         $gtable->add_tablehead(20, T_('White name#header'), 'User', 0, 'whiteName+');
         $gtable->add_tablehead(21, T_('White userid#header'), 'User', 0, 'whiteHandle+');
         $gtable->add_tablehead(29, T_('White start rating#header'), 'Rating', 0, 'whiteStartRating-');
         $gtable->add_tablehead(22, T_('White rating#header'), 'Rating', 0, 'whiteRating-');
      }
      else //RU ?UNION
      {
         $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'oppName+');
         $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'oppHandle+');
         $gtable->add_tablehead(23, T_('Start rating#header'), 'Rating', 0, 'oppStartRating-');
         $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'oppRating-');
         $gtable->add_tablehead(36, T_('User Start rating#header'), 'Rating', 0, 'userStartRating-');
         $gtable->add_tablehead( 5, T_('Colors#header'), 'Image', 0, 'X_Color+');
      }
   }

   $gtable->add_tablehead(43, T_('Ruleset#header'), '', 0, 'Ruleset-');
   $gtable->add_tablehead( 6, T_('Size#header'), 'Number', 0, 'Size-');
   $gtable->add_tablehead( 7, T_('Handicap#header'), 'Number', 0, 'Handicap+');
   $gtable->add_tablehead( 8, T_('Komi#header'), 'Number', 0, 'Komi-');
   $gtable->add_tablehead( 9, T_('Moves#header'), 'Number', 0, 'Moves-');

   if( $finished ) //FU+FA
   {
      if( $all ) //FA
         $gtable->add_tablehead(10, T_('Score#header'), '', 0, 'Score-'); //no UNION
      else //FU ?UNION
      {
         $gtable->add_tablehead(10, T_('Score#header'), '', 0, 'Score-'); //despite ?UNION else X_Score
         $gtable->add_tablehead(11, T_('Win?#header'), 'Image', 0, 'X_Score-');
      }
   }

   $gtable->add_tablehead(14, T_('Rated#header'), '', 0, 'X_Rated-');

   // col 13 must be static for RESTRICT_SHOW_GAMES_ALL
   $table_mode13 = ($restrict_games) ? TABLE_NO_HIDE : 0;
   $restrict_gametext = '';
   if( $observe ) //OB
      $gtable->add_tablehead(13, T_('Last move#header'), 'Date', 0, 'Lastchanged-');
   else if( $finished ) //FU+FA ?UNION
   {
      $restrict_gametext = T_('End date#header');
      $gtable->add_tablehead(13, T_('End date#header'), 'Date', $table_mode13, 'Lastchanged-');
   }
   else if( $running ) //RU+RA ?UNION
   {
      $restrict_gametext = T_('Last move#header');
      $gtable->add_tablehead(13, T_('Last move#header'), 'Date', $table_mode13, 'Lastchanged-');
      if( !$all ) //RU ?UNION
      {
         $gtable->add_tablehead(15, T_('Opponents Last Access#header'), 'Date', 0, 'oppLastaccess-');
      }
   }
   $gtable->add_tablehead(12, T_('Weekend Clock#header'), 'Date', 0, 'WeekendClock-');

   if( $running && !$all && $is_mine ) //RU
   {
      $gtable->add_tablehead(39, T_('My time remaining#header'), null, TABLE_NO_SORT);
      $gtable->add_tablehead(40, T_('Opponent time remaining#header'), null, TABLE_NO_SORT);
   }

   if( $observe_all )
      $gtable->set_default_sort( 34, 13 ); //on ObsCount,Lastchanged
   else
      $gtable->set_default_sort( 13/*, 1*/); //on Lastchanged,ID
   $order = $gtable->current_order_string('ID-');
   $limit = $gtable->current_limit_string();


   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS, // std-fields
      'Games.*',
      'Games.Flags+0 AS X_GameFlags',
      'UNIX_TIMESTAMP(Games.Lastchanged) AS X_Lastchanged',
      "IF(Games.Rated='N','N','Y') AS X_Rated" );

   if( $observe ) //OB
   {
      $qsql->add_part( SQLP_FIELDS,
         'black.Name AS blackName', 'black.Handle AS blackHandle',
         'white.Name AS whiteName', 'white.Handle AS whiteHandle',
         'black.Rating2 AS blackRating', 'black.ID AS blackID',
         'white.Rating2 AS whiteRating', 'white.ID AS whiteID',
         'Games.Black_Start_Rating AS blackStartRating',
         'Games.White_Start_Rating AS whiteStartRating' );
      $qsql->add_part( SQLP_FROM,
         'Observers AS Obs',
         'INNER JOIN Games ON Games.ID=Obs.gid',
         'INNER JOIN Players AS white ON white.ID=Games.White_ID',
         'INNER JOIN Players AS black ON black.ID=Games.Black_ID' );

      if( $observe_all ) //OA
      {
         $qsql->add_part( SQLP_FIELDS,
            'COUNT(Obs.uid) AS X_ObsCount',
            "IF(Games.Black_ID=$my_id OR Games.White_ID=$my_id,'Y','N') AS X_MeObserved" );
         $qsql->add_part( SQLP_GROUP,
            'Obs.gid' );
      }
      else //OU
      {
         $qsql->add_part( SQLP_WHERE,
            'Obs.uid=' . $my_id );
      }
   }
   else if( $all ) //FA+RA
   {
      $qsql->add_part( SQLP_FIELDS,
         'black.Name AS blackName', 'black.Handle AS blackHandle',
         'white.Name AS whiteName', 'white.Handle AS whiteHandle',
         'black.Rating2 AS blackRating', 'black.ID AS blackID',
         'white.Rating2 AS whiteRating', 'white.ID AS whiteID',
         'Games.Black_Start_Rating AS blackStartRating',
         'Games.White_Start_Rating AS whiteStartRating' );
      $qsql->add_part( SQLP_FROM,
         'Games',
         'INNER JOIN Players AS white ON white.ID=Games.White_ID',
         'INNER JOIN Players AS black ON black.ID=Games.Black_ID' );

      if( $finished ) //FA
      {
         $qsql->add_part( SQLP_FIELDS,
            'Black_End_Rating AS blackEndRating',
            'White_End_Rating AS whiteEndRating',
            'blog.RatingDiff AS blackDiff',
            'wlog.RatingDiff AS whiteDiff' );
         $qsql->add_part( SQLP_FROM,
            'LEFT JOIN Ratinglog AS blog ON blog.gid=Games.ID AND blog.uid=Games.Black_ID',
            'LEFT JOIN Ratinglog AS wlog ON wlog.gid=Games.ID AND wlog.uid=Games.White_ID' );
         $qsql->add_part( SQLP_WHERE, "Games.Status='FINISHED'" );
      }
      else if( $running ) //RA
         $qsql->add_part( SQLP_WHERE, 'Games.Status' . IS_RUNNING_GAME );
   }
   else //FU+RU ?UNION
   {
      $qsql->add_part( SQLP_FIELDS,
         'Opp.Name AS oppName',
         'Opp.Handle AS oppHandle',
         'Opp.ID AS oppID',
         'Opp.Rating2 AS oppRating',
         "IF(Games.Black_ID=$uid, Games.White_Start_Rating, Games.Black_Start_Rating) AS oppStartRating",
         "IF(Games.Black_ID=$uid, Games.Black_Start_Rating, Games.White_Start_Rating) AS userStartRating",
         "IF(Games.Black_ID=$uid, $uid, Games.White_ID) AS userID",
         'UNIX_TIMESTAMP(Opp.Lastaccess) AS oppLastaccess',
         //extra bits of Color are for sorting purposes
         //b0= White to play, b1= I am White, b4= not my turn, b5= bad or no ToMove info
         "IF(Games.ToMove_ID=$uid,0,0x10)+IF(Games.White_ID=$uid,2,0)+"
            . "IF(Games.White_ID=Games.ToMove_ID,1,IF(Games.Black_ID=Games.ToMove_ID,0,0x20)) AS X_Color" );
      $qsql->add_part( SQLP_FROM, 'Games', 'Players AS Opp' );

      if( $load_notes ) //FU+RU ?UNION
      {
         $qsql->add_part( SQLP_FIELDS, "GN.Notes AS X_Note" );
         $qsql->add_part( SQLP_FROM,
            "LEFT JOIN GamesNotes AS GN ON GN.gid=Games.ID AND GN.uid=$my_id" );
      }

      if( $finished ) //FU ?UNION
      {
         $qsql->add_part( SQLP_FIELDS,
            "IF(Games.Black_ID=$uid, -Games.Score, Games.Score) AS X_Score",
            "IF(Games.Black_ID=$uid, Games.White_End_Rating, Games.Black_End_Rating) AS oppEndRating",
            "IF(Games.White_ID=$uid, Games.White_End_Rating, Games.Black_End_Rating) AS userEndRating",
            'oppRlog.RatingDiff AS oppRatingDiff' );
         $qsql->add_part( SQLP_FROM,
            "LEFT JOIN Ratinglog AS oppRlog ON oppRlog.gid=Games.ID AND oppRlog.uid=$uid" );
         $qsql->add_part( SQLP_WHERE, "Games.Status='FINISHED'" );

         if( $load_user_ratingdiff )
         {
            $qsql->add_part( SQLP_FIELDS,
               'userRlog.RatingDiff AS userRatingDiff' );
            $qsql->add_part( SQLP_FROM,
               "LEFT JOIN Ratinglog AS userRlog ON userRlog.gid=Games.ID AND userRlog.uid=Games.White_ID+Games.Black_ID-$uid" );
         }
      }
      else if( $running ) //RU ?UNION
      {
         $qsql->add_part( SQLP_WHERE, 'Games.Status' . IS_RUNNING_GAME );

         if( $load_remaining_time ) //RU
         {
            $qsql->add_part( SQLP_FIELDS, "COALESCE(Clock.Ticks,0) AS X_Ticks" );
            $qsql->add_part( SQLP_FROM,
               "LEFT JOIN Clock ON Clock.ID=Games.ClockUsed" );
         }
      }

      if( ALLOW_SQL_UNION ) //FU+RU ?UNION
      {
         $qsql->add_part( SQLP_UNION_WHERE,
            "Games.White_ID=$uid AND Opp.ID=Games.Black_ID",
            "Games.Black_ID=$uid AND Opp.ID=Games.White_ID" );
         $qsql->useUnionAll();
      }
      else //FU+RU
      {
         $qsql->add_part( SQLP_WHERE,
            //"(( Games.Black_ID=$uid AND Games.White_ID=Opp.ID ) OR
            //( Games.White_ID=$uid AND Games.Black_ID=Opp.ID ))"
            "(Games.White_ID=$uid OR Games.Black_ID=$uid)",
            "Opp.ID=Games.White_ID+Games.Black_ID-$uid" );
      }
   }

   if( $ext_tid )
   {
      $qsql->add_part( SQLP_WHERE, "Games.tid=$ext_tid" );

      $qsql->add_part( SQLP_FIELDS, 'TG.Status AS TG_Status' );
      $qsql->add_part( SQLP_FROM, 'LEFT JOIN TournamentGames AS TG ON TG.gid=Games.ID' );
   }

   $qsql->merge( $gtable->get_query() );
   $query = $qsql->get_select() . "$order$limit";

   $result = db_query( 'show_games.find_games', $query);

   $show_rows = $gtable->compute_show_rows(mysql_num_rows($result));
   $gtable->set_found_rows( mysql_found_rows('show_games.found_rows') );


   if( $observe ) //OB
   {
      $title1 = $title2 = ( $observe_all )
         ? T_('All observed games') : T_('Games I\'m observing');
   }
   elseif( $all) //FA+RA
   {
      $title1 = $title2 = ( $finished )
         ? T_('All finished games') : T_('All running games');
   }
   else //FU+RU
   {
      $games_for = ( $finished ? T_('Finished games for %s') : T_('Running games for %s') );
      $title1 = sprintf( $games_for, make_html_safe($user_row['Name']) );

      $games_for = ( $finished ? T_('Finished games for %1$s: %2$s') : T_('Running games for %1$s: %2$s') );
      $title2 = sprintf( $games_for,
         user_reference( REF_LINK, 1, '', $user_row),
         echo_rating( @$user_row['Rating2'], true, $uid ));
   }
   if( $ext_tid > 0 )
   {
      $title2 .= SEP_SPACING . '('
         . anchor( $base_path."tournaments/view_tournament.php?tid=$ext_tid",
                   T_('Tournament') . ' #' . $ext_tid ) . ')';
   }


   start_page( $title1, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title2</h3>\n";

   if( $restrict_games != '' && !$gfilter->is_init() )
   {
      echo sprintf(
            T_('NOTE: The full games list is per default restricted to show only recent games within %s day(s). '
               . 'This can be changed in the filter for [%s].'),
               $restrict_games, $restrict_gametext ),
         "<br><br>\n";
   }

   // hover-texts for colors-column
   // (don't add 'w' and 'b', or else need to show in status.php too)
   $arr_titles_colors = array( // %s=user-handle
      'w_w' => T_('[%s] has White, White to move#hover'),
      'w_b' => T_('[%s] has White, Black to move#hover'),
      'b_w' => T_('[%s] has Black, White to move#hover'),
      'b_b' => T_('[%s] has Black, Black to move#hover'),
   );

   while( ($show_rows-- > 0) && ($row = mysql_fetch_assoc( $result )) )
   {
      $oppRating = $blackRating = $whiteRating = NULL;
      $oppStartRating = $blackStartRating = $whiteStartRating = NULL;
      $oppEndRating = $blackEndRating = $whiteEndRating = NULL;
      $oppRatingDiff = $blackDiff = $whiteDiff = NULL;
      extract($row);

      $grow_strings = array();
      if( $gtable->Is_Column_Displayed[1] )
         $grow_strings[1] = button_TD_anchor( "game.php?gid=$ID", $ID);
      if( $gtable->Is_Column_Displayed[32] )
         $grow_strings[32] = echo_image_gameinfo($ID)
            . ( ALLOW_TOURNAMENTS ? echo_image_tournament_info($tid,true) : '' );
      if( $gtable->Is_Column_Displayed[2] )
         $grow_strings[2] = "<A href=\"sgf.php?gid=$ID\">" . T_('sgf') . "</A>";
      if( $observe_all )
      {
         if( $gtable->Is_Column_Displayed[34] )
            $grow_strings[34] = $X_ObsCount;
         if( $gtable->Is_Column_Displayed[35] )
            $grow_strings[35] = ($X_MeObserved == 'N' ? T_('No') : T_('Yes') );
      }

      if( $observe || $all ) //OB+FA+RA
      {
         if( $gtable->Is_Column_Displayed[17] )
            $grow_strings[17] = "<A href=\"userinfo.php?uid=$blackID\">" .
               make_html_safe($blackName) . "</a>";
         if( $gtable->Is_Column_Displayed[18] )
            $grow_strings[18] = "<A href=\"userinfo.php?uid=$blackID\">" .
               $blackHandle . "</a>";
         if( $gtable->Is_Column_Displayed[26] )
            $grow_strings[26] = echo_rating($blackStartRating,true,$blackID);
         if( $finished && $gtable->Is_Column_Displayed[27] )
            $grow_strings[27] = echo_rating($blackEndRating,true,$blackID);
         if( $gtable->Is_Column_Displayed[19] )
            $grow_strings[19] = echo_rating($blackRating,true,$blackID);
         if( $finished && $gtable->Is_Column_Displayed[28] )
         {
            if( isset($blackDiff) )
               $grow_strings[28] = ( $blackDiff > 0 ? '+' : '' ) . sprintf( "%0.2f", $blackDiff / 100 );
         }
         if( $gtable->Is_Column_Displayed[20] )
            $grow_strings[20] = "<A href=\"userinfo.php?uid=$whiteID\">" .
               make_html_safe($whiteName) . "</a>";
         if( $gtable->Is_Column_Displayed[21] )
            $grow_strings[21] = "<A href=\"userinfo.php?uid=$whiteID\">" .
               $whiteHandle . "</a>";
         if( $gtable->Is_Column_Displayed[29] )
            $grow_strings[29] = echo_rating($whiteStartRating,true,$whiteID);
         if( $finished && $gtable->Is_Column_Displayed[30] )
            $grow_strings[30] = echo_rating($whiteEndRating,true,$whiteID);
         if( $gtable->Is_Column_Displayed[22] )
            $grow_strings[22] = echo_rating($whiteRating,true,$whiteID);
         if( $finished && $gtable->Is_Column_Displayed[31] )
         {
            if( isset($whiteDiff) )
               $grow_strings[31] = ( $whiteDiff > 0 ? '+' : '' ) . sprintf( "%0.2f", $whiteDiff / 100 );
         }
      }
      else //FU+RU ?UNION
      {
         if( $load_notes && $gtable->Is_Column_Displayed[33] )
         {
            // keep the first line up to LIST_GAMENOTE_LEN chars
            $grow_strings[33] = make_html_safe( strip_gamenotes($X_Note) );
         }
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[3] = "<A href=\"userinfo.php?uid=$oppID\">" .
               make_html_safe($oppName) . "</a>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[4] = "<A href=\"userinfo.php?uid=$oppID\">" . $oppHandle . "</a>";
         if( $gtable->Is_Column_Displayed[5] )
         {
            if( $X_Color & 0x2 ) //my color
               $colors = 'w';
            else
               $colors = 'b';
            if( !($X_Color & 0x20) )
            {
               if( $X_Color & 0x1 ) //to move color
                  $colors.= '_w';
               else
                  $colors.= '_b';
            }
            $hover_title = ( isset($arr_titles_colors[$colors]) )
               ? sprintf( $arr_titles_colors[$colors], $oppHandle ) : '';
            $grow_strings[5] = image( "17/$colors.gif", $colors, $hover_title );
         }
         if( $gtable->Is_Column_Displayed[23] )
            $grow_strings[23] = echo_rating($oppStartRating,true,$oppID);
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = echo_rating($oppRating,true,$oppID);
         if( $gtable->Is_Column_Displayed[36] )
            $grow_strings[36] = echo_rating($userStartRating,true,$userID);

         if( $finished ) //FU
         {
            if( $gtable->Is_Column_Displayed[11] )
            {
               if( $X_Score > 0 )
                  $attrstr = 'yes.gif" alt="' . T_('Yes');
               elseif( $X_Score < 0 )
                  $attrstr = 'no.gif" alt="' . T_('No');
               else
                  $attrstr = 'dash.gif" alt="' . T_('Jigo');
               $grow_strings[11] = "<img src=\"images/$attrstr\">";
            }
            if( $gtable->Is_Column_Displayed[24] )
               $grow_strings[24] = echo_rating($oppEndRating,true,$oppID);
            if( $gtable->Is_Column_Displayed[25] )
            {
               if( isset($oppRatingDiff) )
                  $grow_strings[25] = ( $oppRatingDiff > 0 ? '+' : '' )
                     . sprintf( "%0.2f", $oppRatingDiff / 100 );
            }
            if( $gtable->Is_Column_Displayed[37] )
               $grow_strings[37] = echo_rating($userEndRating,true,$userID);
            if( $gtable->Is_Column_Displayed[38] )
            {
               if( isset($userRatingDiff) )
                  $grow_strings[38] = ( $userRatingDiff > 0 ? '+' : '' )
                     . sprintf( "%0.2f", $userRatingDiff / 100 );
            }

            if( $gtable->Is_Column_Displayed[41] && ($X_GameFlags & GAMEFLAGS_HIDDEN_MSG) )
               $grow_strings[41] = echo_image_gamecomment($ID);
         }
         else //RU
         {
            if( $gtable->Is_Column_Displayed[15] )
               $grow_strings[15] = ( $oppLastaccess > 0 ? date(DATE_FMT, $oppLastaccess) : '' );
            if( $is_mine && $gtable->Is_Column_Displayed[39] ) // my-RemTime
            {
               // X_Color: b0= White to play, b1= I am White, b4= not my turn
               $my_col = ( $X_Color & 2 ) ? WHITE : BLACK;
               $is_to_move = !( $X_Color & 0x10 ); // my-turn -> use clock
               $grow_strings[39] = build_time_remaining( $row, $my_col, $is_to_move );
            }
            if( $is_mine && $gtable->Is_Column_Displayed[40] ) // opp-RemTime
            {
               // X_Color: b0= White to play, b1= I am White, b4= not my turn
               $opp_col = !( $X_Color & 2 ) ? WHITE : BLACK;
               $is_to_move = ( $X_Color & 0x10 ); // opp-turn -> use clock
               $grow_strings[40] = build_time_remaining( $row, $opp_col, $is_to_move );
            }
         }
      } //else //OB

      if( $gtable->Is_Column_Displayed[6] )
         $grow_strings[6] = $Size;
      if( $gtable->Is_Column_Displayed[7] )
         $grow_strings[7] = $Handicap;
      if( $gtable->Is_Column_Displayed[8] )
         $grow_strings[8] = $Komi;
      if( $gtable->Is_Column_Displayed[9] )
         $grow_strings[9] = $Moves;
      if( $gtable->Is_Column_Displayed[12] )
         $grow_strings[12] = ($WeekendClock == 'Y' ? T_('Yes') : T_('No'));
      if( $gtable->Is_Column_Displayed[13] )
         $grow_strings[13] = ( $X_Lastchanged > 0 ? date(DATE_FMT, $X_Lastchanged) : '' );
      if( $gtable->Is_Column_Displayed[14] )
         $grow_strings[14] = ($X_Rated == 'N' ? T_('No') : T_('Yes') );

      if( $ext_tid && $gtable->Is_Column_Displayed[42] && @$TG_Status )
         $grow_strings[42] = TournamentGames::getStatusText($TG_Status);

      if( $gtable->Is_Column_Displayed[43] )
         $grow_strings[43] = getRulesetText($Ruleset);

      if( $finished ) //FU+FA
      {
         if( $gtable->Is_Column_Displayed[10] )
            $grow_strings[10] = score2text($Score, false);
      }

      $gtable->add_row( $grow_strings );
   }
   if ( $result )
      mysql_free_result($result);
   $gtable->echo_table();

   // build bottom menu
   // (use more detailed link-texts to show where you are and where you can go)

   $menu_array = array();
   $row_str = $gtable->current_rows_string();
   $need_my_games = $all || $is_other;

   if( $ext_tid > 0 )
      $page .= "tid=$ext_tid".URI_AMP;

   if( $is_other ) //RU+FU (other)
   {
      if( !$running )
         $menu_array[T_('Users running games')] = $page."uid=$uid".URI_AMP.$row_str;
      if( !$finished )
         $menu_array[T_('Users finished games')] = $page."uid=$uid".URI_AMP."finished=1".URI_AMP.$row_str;
   }

   if( $is_mine || $need_my_games ) //RU+FU (mine)
   {
      if( $need_my_games || !$running )
         $menu_array[T_('My running games')] = $page."uid=$my_id".URI_AMP.$row_str;
      if( $need_my_games || !$finished )
         $menu_array[T_('My finished games')] = $page."uid=$my_id".URI_AMP."finished=1".URI_AMP.$row_str;
   }

   //RA+FA (all)
   if( !$all || !$running )
      $menu_array[T_('All running games')]  = $page."uid=all".URI_AMP.$row_str;
   if( !$all || !$finished )
      $menu_array[T_('All finished games')] = $page."uid=all".URI_AMP."finished=1".URI_AMP.$row_str;

   if( $observe_all || !$observe ) //OA+RU+RA+FU+FA
      $menu_array[T_('Games I\'m observing')] = $page."observe=$my_id".URI_AMP.$row_str;
   if( !$observe_all || !$observe ) //OU+RU+RA+FU+FA
      $menu_array[T_('All observed games')] = $page."observe=all".URI_AMP.$row_str;

   if( $is_other ) //RU+FU (viewing games from other user)
   {
      $menu_array[T_('Show userinfo')] = "userinfo.php?uid=$uid";
      $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$uid";
   }

   end_page(@$menu_array);
}
?>

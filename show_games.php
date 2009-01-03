<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/rating.php" );
$ThePage = new Page('GamesList');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row['ID'];

   $observe = isset($_GET['observe']);
   if( $observe ) //OB
   {
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

            $user_row = mysql_single_fetch('show_games.find_player',
                  "SELECT ID, Name, Handle FROM Players WHERE $where" )
               or error('unknown_user', 'show_games.find_player');

            $uid = $user_row['ID'];
         }
      }
   }
   $running = !$observe && !$finished;

   $page = 'show_games.php?';
   if( $observe )
   {
      $tableid = 'observed';
      $column_set_name = "ObservedGamesColumns";
      $fprefix = 'o';
   }
   else if( $finished )
   {
      $tableid = 'finished';
      $column_set_name = "FinishedGamesColumns";
      $fprefix = 'f';
   }
   else if( $running )
   {
      $tableid = 'running';
      $column_set_name = "RunningGamesColumns";
      $fprefix = 'r';
   }
   $show_notes= (LIST_GAMENOTE_LEN>0
         && !$observe && !$all && $uid==$my_id); //FU+RU subset

   $def_arr_lastmove = array();
   if( RESTRICT_SHOW_GAMES_ALL && $all )
      $def_arr_lastmove[FC_DEFAULT] = RESTRICT_SHOW_GAMES_ALL;

   // table filters
   $gfilter = new SearchFilter( $fprefix );
   //Filter & add_filter(int id, string type, string dbfield, [bool active=false], [array config])
   $gfilter->add_filter( 1, 'Numeric', 'Games.ID', true, array( FC_SIZE => 8 ) );
   $gfilter->add_filter( 6, 'Numeric', 'Size', true,
         array( FC_SIZE => 3 ));
   $gfilter->add_filter( 7, 'Numeric', 'Handicap', true,
         array( FC_SIZE => 3 ));
   $gfilter->add_filter( 8, 'Numeric', 'Komi', true,
         array( FC_SIZE => 3 ));
   $gfilter->add_filter( 9, 'Numeric', 'Games.Moves', true,
         array( FC_SIZE => 4 ));
   $gfilter->add_filter(13, 'RelativeDate', 'Games.Lastchanged', true, $def_arr_lastmove ); // Games
   $gfilter->add_filter(14, 'RatedSelect', 'Games.Rated', true,
         array( FC_FNAME => 'rated' ));
   if( !$observe && !$all ) //FU+RU
   {
      $gfilter->add_filter( 3, 'Text',   'Name',   true);
      $gfilter->add_filter( 4, 'Text',   'Handle', true);
      $gfilter->add_filter(16, 'Rating', 'Rating2', true);
   }
   if( $running && !$all ) //RU
   {
      $gfilter->add_filter( 5, 'Selection',   // filter on my color / my move
            array( T_('All#filter') => '',
                   T_('I\'m B#filtercol')  => new QuerySQL( SQLP_HAVING, '!(X_Color&2)' ),
                   T_('I\'m W#filtercol')  => new QuerySQL( SQLP_HAVING, '(X_Color&2)' ),
                   T_('My move#filtercol') => "ToMove_ID=$uid",
                   T_('Op move#filtercol') => "ToMove_ID<>$uid" ), // <- no idx used
            true);
      $gfilter->add_filter(23, 'Rating', 'startRating', true,
            array( FC_ADD_HAVING => 1 ));
      $gfilter->add_filter(24, 'BoolSelect', 'Weekendclock', true);
      $gfilter->add_filter(25, 'RelativeDate', 'Players.Lastaccess', true); // Players
   }
   if( $finished ) //FU+FA
   {
      $gfilter->add_filter(10, 'Score', 'Score', false,
            array( FC_SIZE => 3, FC_HIDE => 1 ));
      if( $all ) //FA
      {
         $gfilter->add_filter(27, 'Rating', 'Black_End_Rating', true);
         $gfilter->add_filter(30, 'Rating', 'White_End_Rating', true);
         $gfilter->add_filter(28, 'RatingDiff', 'blog.RatingDiff', true);
         $gfilter->add_filter(31, 'RatingDiff', 'wlog.RatingDiff', true);
      }
      else //FU
      {
         $gfilter->add_filter( 5, 'Selection',
               array( T_('All#filter') => '',
                      T_('B#filter')   => "Black_ID=$uid",
                      T_('W#filter')   => "White_ID=$uid" ),
               true);
         $gfilter->add_filter(11, 'Selection',
               array( T_('All#filter')  => '',
                      T_('Won')  => new QuerySQL( SQLP_HAVING, 'X_Score>0' ),
                      T_('Lost') => new QuerySQL( SQLP_HAVING, 'X_Score<0' ),
                      T_('Jigo') => 'Score=0' ),
               true,
               array( FC_FNAME => 'won' ));
         $gfilter->add_filter(23, 'Rating', 'startRating', true,
               array( FC_ADD_HAVING => 1 ));
         $gfilter->add_filter(24, 'Rating', 'endRating',   true,
               array( FC_ADD_HAVING => 1 ));
         $gfilter->add_filter(25, 'RatingDiff', 'log.RatingDiff', true);
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
   $gfilter->init(); // parse current value from _GET

   $gtable = new Table( $tableid, $page, $column_set_name );
   $gtable->register_filter( $gfilter );
   $gtable->add_or_del_column();

   // attach external URL-parameters to table
   $extparam = new RequestParameters();
   if( $observe )
      $extparam->add_entry( 'observe', 1 );
   else
   {
      $extparam->add_entry( 'uid', $uid );
      if( $finished )
         $extparam->add_entry( 'finished', 1 );
   }
   $gtable->add_external_parameters( $extparam, true ); // also for hiddens

/*****
 * Views-pages identification:
 *   views: OB=observe, FU=finished-user, FA=finished-all, RU=running-user, RA=running-all
 *
 *****
 * Database-columns FROM:
 * Notes:
 * - Games fields are all SELECTed and the from should stay without table prefix!
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
 *   ID, Starttime, Lastchanged, mid, Black_ID, White_ID, ToMove_ID, Size, Komi, Handicap,
 *   Status, Moves, Black_Prisoners, White_Prisoners, Last_X, Last_Y, Last_Move, Flags, Score,
 *   Maintime, Byotype, Byotime, Byoperiods, Black_Maintime, White_Maintime,
 *   Black_Byotime, White_Byotime, Black_Byoperiods, White_Byoperiods, LastTicks, ClockUsed,
 *   Rated, StdHandicap, WeekendClock, Black_Start_Rating, White_Start_Rating,
 *   Black_End_Rating, White_End_Rating
 *
 * Players (OB+FA+RA) AS white, AS black - (FU+RU) AS Players(+UNION):
 *   ID, Handle, Password, Newpassword, Sessioncode, Sessionexpire, Lastaccess, LastMove,
 *   Registerdate, Hits, VaultCnt, VaultTime, Moves, Activity, Name, Email, Rank, Stonesize,
 *   SendEmail, Notify, MenuDirection, Adminlevel, Timezone, Nightstart, ClockUsed, ClockChanged,
 *   Rating, RatingMin, RatingMax, Rating2, InitialRating, RatingStatus, Open, Lang,
 *   VacationDays, OnVacation, SkinName, Woodcolor, Boardcoords, MoveNumbers, MoveModulo, Button,
 *   UsersColumns, GamesColumns, RunningGamesColumns, FinishedGamesColumns, ObservedGamesColumns,
 *   TournamentsColumns, WaitingroomColumns,  ContactColumns, Running, Finished, RatedGames,
 *   Won, Lost, Translator, StatusFolders, IP, Browser, Country, NotesSmallHeight, NotesSmallWidth,
 *   NotesSmallMode, NotesLargeHeight, NotesLargeWidth, NotesLargeMode, NotesCutoff,
 *   MayPostOnForum, TableMaxRows
 *
 * Observers (OB) AS Obs:
 *   ID, uid, gid
 *
 * Ratinglog (FA) AS blog, AS wlog - (FU) AS log:
 *   ID, uid, gid, Time, Rating, RatingMin, RatingMax, RatingDiff
 *
 * GamesNotes (FU+RU) AS Gnt:
 *   ID, gid, player, Hidden, Notes
 *
 *****
 * Views-columns usage:
 * Notes:
 * - '> ' indicates a column not common to all views, usage given for specific views
 * - When a column number is shared between two fields, they must be displayed
 *   inside different (not intersecting) "hide/show columns" groups i.e.:
 *   - (OB)    => ObservedGamesColumns
 *   - (FU+FA) => FinishedGamesColumns
 *   - (RU+RA) => RunningGamesColumns
 *
 * no: description of displayed info
 *  1:    ID
 *  2:    sgf
 *  3: >  FU+RU (Opponent-Name)
 *  4: >  FU+RU (Opponent-Handle)
 *  5: >  FU (User-Color-Graphic), RU (2-Colors-Graphic, who-to-move)
 *  6:    Size
 *  7:    Handicap
 *  8:    Komi
 *  9:    Moves
 * 10: >  FU+FA [Score] (Score)
 * 11: >  FU [User-Score AS X_Score] (Win-graphic) -> fname=won
 * 12:    --- unused ---
 * 13:    FU+FA [Lastchanged] (End date), OB+RU+RA [Lastchanged] (Last move)
 * 14:    [Rated AS X_Rated] (Rated) -> fname=rated
 * 15:    --- unused ---
 * 16: >  FU+RU [Rating AS X_Rating] (User-Rating)
 * 17: >  OB+FA+RA (Black-Name)
 * 18: >  OB+FA+RA (Black-Handle)
 * 19: >  OB+FA+RA (Black-Rating)
 * 20: >  OB+FA+RA (White-Name)
 * 21: >  OB+FA+RA (White-Handle)
 * 22: >  OB+FA+RA (White-Rating)
 * 23: >  FU+RU (User-StartRating)
 * 24: >  FU (User-EndRating), RU (Weekendclock)
 * 25: >  FU (User-RatingDiff), RU (Opponents-LastAccess)
 * 26: >  OB+FA+RA (Black-StartRating)
 * 27: >  FA (Black-EndRating)
 * 28: >  FA (Black-RatingDiff)
 * 29: >  OB+FA+RA (White-StartRating)
 * 30: >  FA (White-EndRating)
 * 31: >  FA (White-RatingDiff)
 * 32: >  FU+RU [Notes AS X_Note] (Notes)
 *****/

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $gtable->add_tablehead( 1, T_('Game ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $gtable->add_tablehead( 2, T_('sgf#header'), 'Sgf', TABLE_NO_SORT);
   if( !$observe && !$all ) //FU+RU ?UNION
   {
      if( $show_notes )
         $gtable->add_tablehead(32, T_('Notes#header'), '', 0, 'X_Note-');
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
         $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'Name+');
         $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'Handle+');
         $gtable->add_tablehead(23, T_('Start rating#header'), 'Rating', 0, 'startRating-');
         $gtable->add_tablehead(24, T_('End rating#header'), 'Rating', 0, 'endRating-');
         $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'X_Rating-');
         $gtable->add_tablehead(25, T_('Rating diff#header'), 'Number', 0, 'ratingDiff-');
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
         $gtable->add_tablehead( 3, T_('Opponent#header'), 'User', 0, 'Name+');
         $gtable->add_tablehead( 4, T_('Userid#header'), 'User', 0, 'Handle+');
         $gtable->add_tablehead(23, T_('Start rating#header'), 'Rating', 0, 'startRating-');
         $gtable->add_tablehead(16, T_('Rating#header'), 'Rating', 0, 'X_Rating-');
         $gtable->add_tablehead( 5, T_('Colors#header'), 'Image', 0, 'X_Color+');
      }
   }

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
         $gtable->add_tablehead(11, T_('Win?#header'), 'Image', TABLE_NO_HIDE, 'X_Score-');
      }
   }

   $gtable->add_tablehead(14, T_('Rated#header'), '', TABLE_NO_HIDE, 'X_Rated-');

   if( $observe ) //OB
      $gtable->add_tablehead(13, T_('Last move#header'), 'Date', 0, 'Lastchanged-');
   else if( $finished ) //FU+FA ?UNION
      $gtable->add_tablehead(13, T_('End date#header'), 'Date', 0, 'Lastchanged-');
   else if( $running ) //RU+RA ?UNION
   {
      $gtable->add_tablehead(13, T_('Last move#header'), 'Date', 0, 'Lastchanged-');
      if( !$all ) //RU ?UNION
      {
         $gtable->add_tablehead(25, T_('Opponents Last Access#header'), 'Date', 0, 'Lastaccess-');
         $gtable->add_tablehead(24, T_('Weekend Clock#header'), 'Date', 0, 'WeekendClock-');
      }
   }
   $gtable->set_default_sort( 13/*, 1*/); //on Lastchanged,ID
   $order = $gtable->current_order_string('ID-');
   $limit = $gtable->current_limit_string();

   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS, // std-fields
      'Games.*',
      'UNIX_TIMESTAMP(Lastchanged) AS X_Lastchanged',
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
         'INNER JOIN Players AS white ON white.ID=White_ID',
         'INNER JOIN Players AS black ON black.ID=Black_ID' );
      $qsql->add_part( SQLP_WHERE,
         'Obs.uid=' . $my_id );
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
         'INNER JOIN Players AS white ON white.ID=White_ID',
         'INNER JOIN Players AS black ON black.ID=Black_ID' );

      if( $finished ) //FA
      {
         $qsql->add_part( SQLP_FIELDS,
            'Black_End_Rating AS blackEndRating',
            'White_End_Rating AS whiteEndRating',
            'blog.RatingDiff AS blackDiff',
            'wlog.RatingDiff AS whiteDiff' );
         $qsql->add_part( SQLP_FROM,
            'LEFT JOIN Ratinglog AS blog ON blog.gid=Games.ID AND blog.uid=Black_ID',
            'LEFT JOIN Ratinglog AS wlog ON wlog.gid=Games.ID AND wlog.uid=White_ID' );
         $qsql->add_part( SQLP_WHERE, "Status='FINISHED'" );
      }
      else if( $running ) //RA
         $qsql->add_part( SQLP_WHERE, 'Status' . IS_RUNNING_GAME );
   }
   else //FU+RU ?UNION
   {
      $qsql->add_part( SQLP_FIELDS,
         'Name',
         'Handle',
         'Players.ID AS pid',
         'Players.Rating2 AS X_Rating',
         "IF(Black_ID=$uid, Games.White_Start_Rating, Games.Black_Start_Rating) AS startRating",
         'UNIX_TIMESTAMP(Players.Lastaccess) AS X_Lastaccess',
         //extra bits of Color are for sorting purposes
         //b0= White to play, b1= I am White, b4= not my turn, b5= bad or no ToMove info
         "IF(ToMove_ID=$uid,0,0x10)+IF(White_ID=$uid,2,0)+IF(White_ID=ToMove_ID,1,IF(Black_ID=ToMove_ID,0,0x20)) AS X_Color" );
      $qsql->add_part( SQLP_FROM, 'Games', 'Players' );

      if( $show_notes ) //FU+RU ?UNION
      {
         $qsql->add_part( SQLP_FIELDS, "Gnt.Notes AS X_Note" );
         $qsql->add_part( SQLP_FROM,
            "LEFT JOIN GamesNotes AS Gnt ON Gnt.gid=Games.ID"
               ." AND Gnt.player=IF(White_ID=$my_id,'W','B')" );
      }

      if( $finished ) //FU ?UNION
      {
         $qsql->add_part( SQLP_FIELDS,
            "IF(Black_ID=$uid, -Score, Score) AS X_Score",
            "IF(Black_ID=$uid, Games.White_End_Rating, Games.Black_End_Rating) AS endRating",
            'log.RatingDiff AS ratingDiff' );
         $qsql->add_part( SQLP_FROM, "LEFT JOIN Ratinglog AS log ON log.gid=Games.ID AND log.uid=$uid" );
         $qsql->add_part( SQLP_WHERE, "Status='FINISHED'" );
      }
      else if( $running ) //RU ?UNION
      {
         $qsql->add_part( SQLP_WHERE, 'Status' . IS_RUNNING_GAME );
      }

      if( ALLOW_SQL_UNION ) //FU+RU ?UNION
      {
         $qsql->add_part( SQLP_UNION_WHERE,
            "White_ID=$uid AND Players.ID=Black_ID",
            "Black_ID=$uid AND Players.ID=White_ID" );
      }
      else //FU+RU
      {
         $qsql->add_part( SQLP_WHERE,
            //"(( Black_ID=$uid AND White_ID=Players.ID ) OR
            //( White_ID=$uid AND Black_ID=Players.ID ))"
            "(White_ID=$uid OR Black_ID=$uid)",
            "Players.ID=White_ID+Black_ID-$uid" );
      }
   }

   $qsql->merge( $gtable->get_query() );
   $query = $qsql->get_select() . "$order$limit";

   $result = db_query( 'show_games.find_games', $query);
   db_close();

   if( $observe || $all) //OB+FA+RA
   {
      $title1 = $title2 = ( $observe ? T_('Observed games') :
                            ( $finished ? T_('Finished games') : T_('Running games') ) );
   }
   else //FU+RU
   {
      $games_for = ( $finished ? T_('Finished games for %s') : T_('Running games for %s') );
      $title1 = sprintf( $games_for, make_html_safe($user_row["Name"]) );
      $title2 = sprintf( $games_for, user_reference( REF_LINK, 1, '', $user_row) );
   }


   start_page( $title1, true, $logged_in, $player_row,
               $gtable->button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) ."<br>\n";
   echo "<h3 class=Header>$title2</h3>\n";

   if( RESTRICT_SHOW_GAMES_ALL && $all && !$gfilter->is_init() )
      echo sprintf(
            T_('NOTE: The full games list is per default restricted to show only recent games within %s day(s). '
               . 'This can be changed in the filter for [%s].'),
               RESTRICT_SHOW_GAMES_ALL, T_('Last move#header') ),
         "<br><br>\n";

   // hover-texts for colors-column
   // (don't add 'w' and 'b', or else need to show in status.php too)
   $arr_titles_colors = array(
      'w_w' => T_('You have White, White to move#hover'),
      'w_b' => T_('You have White, Black to move#hover'),
      'b_w' => T_('You have Black, White to move#hover'),
      'b_b' => T_('You have Black, Black to move#hover'),
   );

   $show_rows = $gtable->compute_show_rows(mysql_num_rows($result));

   while( ($show_rows-- > 0) && ($row = mysql_fetch_assoc( $result )) )
   {
      $X_Rating = $blackRating = $whiteRating = NULL;
      $startRating = $blackStartRating = $whiteStartRating = NULL;
      $endRating = $blackEndRating = $whiteEndRating = NULL;
      $blackDiff = $whiteDiff = $ratingDiff = NULL;
      extract($row);

      $grow_strings = array();
      if( $gtable->Is_Column_Displayed[1] )
         $grow_strings[1] = $gtable->button_TD_anchor( "game.php?gid=$ID", $ID);
      if( $gtable->Is_Column_Displayed[2] )
         $grow_strings[2] = "<A href=\"sgf.php?gid=$ID\">"
            . T_('sgf') . "</A>";

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
         if( $show_notes && $gtable->Is_Column_Displayed[32] )
         { //keep the first line up to LIST_GAMENOTE_LEN chars
            $X_Note= trim( substr(
               preg_replace("/[\\x00-\\x1f].*\$/s",'',$X_Note)
               , 0, LIST_GAMENOTE_LEN) );
            $grow_strings[32] = make_html_safe($X_Note);
         }
         if( $gtable->Is_Column_Displayed[3] )
            $grow_strings[3] = "<A href=\"userinfo.php?uid=$pid\">" .
               make_html_safe($Name) . "</a>";
         if( $gtable->Is_Column_Displayed[4] )
            $grow_strings[4] = "<A href=\"userinfo.php?uid=$pid\">" . $Handle . "</a>";
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
               ? " title=\"" . $arr_titles_colors[$colors] . "\"" : '';
            $grow_strings[5] = "<img src=\"17/$colors.gif\" "
               . "alt=\"$colors\"$hover_title>";
         }
         if( $gtable->Is_Column_Displayed[23] )
            $grow_strings[23] = echo_rating($startRating,true,$pid);
         if( $gtable->Is_Column_Displayed[16] )
            $grow_strings[16] = echo_rating($X_Rating,true,$pid);

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
               $grow_strings[24] = echo_rating($endRating,true,$pid);
            if( $gtable->Is_Column_Displayed[25] )
            {
               if( isset($ratingDiff) )
                  $grow_strings[25] = ( $ratingDiff > 0 ? '+' : '' ) . sprintf( "%0.2f", $ratingDiff / 100 );
            }
         }
         else //RU
         {
            if( $gtable->Is_Column_Displayed[24] )
               $grow_strings[24] = ($WeekendClock == 'Y' ? T_('Yes') : T_('No'));
            if( $gtable->Is_Column_Displayed[25] )
               $grow_strings[25] = date(DATE_FMT, $X_Lastaccess);
         }
      }

      if( $gtable->Is_Column_Displayed[6] )
         $grow_strings[6] = $Size;
      if( $gtable->Is_Column_Displayed[7] )
         $grow_strings[7] = $Handicap;
      if( $gtable->Is_Column_Displayed[8] )
         $grow_strings[8] = $Komi;
      if( $gtable->Is_Column_Displayed[9] )
         $grow_strings[9] = $Moves;
      if( $gtable->Is_Column_Displayed[13] )
         $grow_strings[13] = date(DATE_FMT, $X_Lastchanged);
      if( $gtable->Is_Column_Displayed[14] )
         $grow_strings[14] = ($X_Rated == 'N' ? T_('No') : T_('Yes') );

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

   $menu_array = array();

   if( !$all && $uid > 0 && $uid != $my_id ) //FU+RU
   {
      $menu_array[T_('User info')] = "userinfo.php?uid=$uid";
      $menu_array[T_('Invite this user')] = "message.php?mode=Invite".URI_AMP."uid=$uid";
   }

   $row_str = $gtable->current_rows_string();

   // use more detailed link-texts to show where you are and where you can go
   if( !$running ) //OB+FU+FA
   {
      // where am I ?
      if( $my_id == $uid ) //OB+FU (my games)
         $menukey = T_('Show my running games');
      else if( $all ) //FA
         $menukey = T_('Show all running games');
      else //FU (other user)
         $menukey = T_('Show running games');
      $menu_array[$menukey] = $page."uid=$uid".URI_AMP.$row_str;
   }
   if( !$finished ) //OB+RU+RA
   {
      // where am I ?
      if( $my_id == $uid ) //OB+RU (my games)
         $menukey = T_('Show my finished games');
      else if( $all ) //RA
         $menukey = T_('Show all finished games');
      else //RU (other user)
         $menukey = T_('Show finished games');
      $menu_array[$menukey] = $page."uid=$uid".URI_AMP."finished=1".URI_AMP.$row_str;
   }
   if( $observe ) //OB
   { // allow back navigation to all-games (with potentially shared URL-vars)
      $menu_array[T_('Show all running games')]  = $page."uid=all".URI_AMP.$row_str;
      $menu_array[T_('Show all finished games')] = $page."uid=all".URI_AMP."finished=1".URI_AMP.$row_str;
   }
   else //FU+RU+FA+RA
      $menu_array[T_('Show games I\'m observing')] = $page."observe=1".URI_AMP.$row_str;

   end_page(@$menu_array);
}
?>

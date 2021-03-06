<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/globals.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/std_classes.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'include/filter.php';
require_once 'include/filterlib_country.php';
require_once 'include/classlib_profile.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/classlib_userpicture.php';

define('FSTATVAL_ALL', 0);
define('FSTATVAL_RUN', 1);
define('FSTATVAL_FIN', 2);


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'opponents');

   /*
    * uid = player to show opponents for, if empty, use myself
    * opp = if unset, show user-table of players opponents to select for stats
    *       if set, show game-stats for player $uid and opponent $opp
    * tid = tournament-ID for convenience tournament-invitations (optional)
    */

   // check vars
   $my_id = $player_row['ID'];
   $uid = (int)@$_REQUEST['uid'];
   $opp = (int)@$_REQUEST['opp'];
   if ( empty($uid) )
      $uid = $my_id;
   if ( $uid <= 0 )
      error('invalid_user', "opponents.bad_user($uid)");
   if ( $opp < 0 || $opp == $uid )
   {
      $opp = 0;
      //error('invalid_opponent', "opponents.bad_opponent($opp)");
   }
   $tid = (int)get_request_arg('tid'); // convenience for tourney-invites
   if ( !ALLOW_TOURNAMENTS || $tid < 0 ) $tid = 0;

   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_OPPONENTS );
   if ( !$cfg_tblcols )
      error('user_init_error', 'opponents.init.config_table_cols');


   // who are player (uid) and opponent (opp) ?
   $players = array(); // uid => ( Players.field => value )
   $query = "SELECT ID,Handle,Name,Country,Open,Rank,Rating2"
      . ",(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel"
      . ",UNIX_TIMESTAMP(Lastaccess) AS LastaccessU"
      . ",UNIX_TIMESTAMP(LastMove) AS LastMoveU"
      . " FROM Players WHERE ID".( $opp ?" IN('$uid','$opp')" :"='$uid'");
   $result = db_query( "opponents.find_users($uid,$opp)", $query );
   while ( $row = mysql_fetch_assoc( $result ) )
      $players[ $row['ID'] ] = $row;
   mysql_free_result($result);

   if ( !isset($players[$uid]) )
      error('unknown_user', "opponents.load_user($uid)");
   if ( $opp && !isset($players[$opp]) )
      error('unknown_user', "opponents.load_opponent($opp)");

   // config for usertype-filter
   $query_usertype = 'Type>0 AND (Type & %d)';
   $usertypes_array = array(
         T_('All')      => '',
         T_('Special')  => 'Type>0',
         T_('Pro')      => sprintf( $query_usertype, USERTYPE_PRO),
         T_('Teacher')  => sprintf( $query_usertype, USERTYPE_TEACHER),
         T_('Robot')    => sprintf( $query_usertype, USERTYPE_ROBOT),
         //T_('Team')     => sprintf( $query_usertype, USERTYPE_TEAM),
         );

   $page = "opponents.php?";

   // init search profile
   if ( $uid == $my_id )
      $profile_type = PROFTYPE_FILTER_OPPONENTS_MY;
   else
      $profile_type = PROFTYPE_FILTER_OPPONENTS_OTHER;
   $search_profile = new SearchProfile( $my_id, $profile_type );
   $usfilter = new SearchFilter( 's', $search_profile );
   $ufilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'active' ); // named-filters FC_FNAME
   $utable = new Table( 'user', $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $utable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // static filters
   $usfilter->add_filter( 1, 'Numeric',      'G.Size', true );
   $usfilter->add_filter( 2, 'RatedSelect',  'G.Rated', true );
   $usfilter->add_filter( 3, 'Date',         'G.Lastchanged', true );
   $usfilter->add_filter( 4, 'Selection',
         array( T_('All games#filteropp')      => 'G.Status' . not_in_clause($ENUM_GAMES_STATUS, GAME_STATUS_SETUP, GAME_STATUS_INVITED),
                T_('Running games#filteropp')  => 'G.Status' . IS_STARTED_GAME,
                T_('Finished games#filteropp') => "G.Status='".GAME_STATUS_FINISHED."'" ),
         true,
         array( FC_DEFAULT => 2 ) );
   $usfilter->init();
   $f_status =& $usfilter->get_filter(4);
   $f_status_val = $f_status->get_value();
   $finished = ( $f_status_val == FSTATVAL_FIN );

   // table filters: use same table-IDs as in users.php(!)
   // NOTE: Filters on Players.Name/Handle must not allow leading wildcard
   //       like on the users-page. The pages are very much alike, but allowing
   //       leading-wildcards make up VERY SLOW queries for the opponents-page,
   //       so must'nt be used here.
   //       The logic behind that is, to use the users-page to find a specific user
   //       (if unknown or just knowing a part of it), then use that userid as
   //       restrictions on other-pages.
   $ufilter->add_filter( 1, 'Numeric', 'P.ID', true);
   $ufilter->add_filter( 2, 'Text',    'P.Name', true,
         array( FC_SIZE => 12 )); // no leading-wildcard (see NOTE above)
   $ufilter->add_filter( 3, 'Text',    'P.Handle', true,
         array( FC_SIZE => 12 )); // no leading-wildcard (see NOTE above)
   //$ufilter->add_filter( 4, 'Text',    'P.Rank', true); # Rank info (don't use here, no index)
   $ufilter->add_filter( 5, 'Rating',  'P.Rating2', true);
   //$ufilter->add_filter( 6, 'Text',    'P.Open', true); # Open for matches (don't use here, no index)
   $ufilter->add_filter( 7, 'Numeric', 'Games', true,   # =P.Running+P.Finished
         array( FC_SIZE => 4, FC_ADD_HAVING => 1 ));
   $ufilter->add_filter( 8, 'Numeric', 'P.Running', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter( 9, 'Numeric', 'P.Finished', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(10, 'Numeric', 'P.Won', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(11, 'Numeric', 'P.Lost', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(12, 'Numeric', 'IF(P.RatedGames>0,(50*(P.RatedGames+P.Won-P.Lost)/P.RatedGames),0)', true,
         array( FC_SIZE => 4, FC_SYNTAX_HELP => 'NUM %' ));
   $ufilter->add_filter(13, 'Boolean', "P.Activity>$ActiveLevel1", true,
         array( FC_FNAME => 'active', FC_LABEL => T_('Active'), FC_STATIC => 1 ) );
   $ufilter->add_filter(14, 'RelativeDate', 'P.Lastaccess', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS ) );
   $ufilter->add_filter(15, 'RelativeDate', 'P.LastMove', true,
         array( FC_TIME_UNITS => FRDTU_DHM|FRDTU_ABS ));
   $ufilter->add_filter(16, 'Country', 'P.Country', false,
         array( FC_HIDE => 1 ));
   $ufilter->add_filter(17, 'Numeric', 'P.RatedGames', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(18, 'Selection', $usertypes_array, true );
   $ufilter->add_filter(19, 'Boolean', "P.UserPicture", true );
   $ufilter->add_filter(22, 'RelativeDate', 'P.Registerdate', true,
         array( FC_TIME_UNITS => FRDTU_YMWD|FRDTU_ABS ) );
   $ufilter->add_filter(23, 'Numeric', 'IF(P.Finished>='.MIN_FIN_GAMES_HERO_AWARD.',100*P.GamesWeaker/P.Finished,0)', true,
         array( FC_SIZE => 4, FC_SYNTAX_HELP => 'NUM %' ));
   $ufilter->add_filter(24, 'Numeric', 'COALESCE(GS.Running,0)', true,
         array( FC_SIZE => 4 ));
   $ufilter->add_filter(25, 'Numeric', 'COALESCE(GS.Finished,0)', true,
         array( FC_SIZE => 4 ));
   $ufilter->init(); // parse current value from _GET

   // init table
   $utable->register_filter( $ufilter );
   $utable->add_or_del_column();

   // External-Form
   $usform = new Form( $utable->get_prefix(), $page, FORM_GET, false);
   $usform->set_layout( FLAYOUT_GLOBAL, ( $opp ? '1,2|3' : '1' ) );
   $usform->set_config( FEC_EXTERNAL_FORM, true );
   $utable->set_externalform( $usform ); // also attach offset, sort, manage-filter as hidden (table) to ext-form
   $usform->attach_table( $usfilter ); // attach manage-filter as hiddens (static) to ext-form

   // page-vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'uid', $uid );
   if ( $opp )
      $page_vars->add_entry( 'opp', $opp );
   if ( $tid ) $page_vars->add_entry( 'tid', $tid );
   $usform->attach_table( $page_vars ); // for page-vars as hiddens in form

   // attach external URL-parameters from static filter and page-vars for table-links
   $utable->add_external_parameters( $usfilter->get_req_params(), false );
   $utable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   // table: use same table-IDs as in users.php(!)
   // NOTE: The TABLE_NO_HIDEs are needed, because the columns are needed
   //       for the "static" filtering(!) of: Activity; also see named-filters
   $header_1 = ($tid) ? T_('Invite#header') : T_('ID#header');
   $utable->add_tablehead( 1, $header_1, 'Button', TABLE_NO_HIDE, 'ID+');
   $utable->add_tablehead(21, new TableHeadImage( T_('Opponent games#header'),
      'images/table.gif', T_('Link to games with opponent') ), 'Image', TABLE_NO_SORT );
   $utable->add_tablehead(18, T_('Type#header'), 'Enum', 0, 'Type+');
   if ( USERPIC_FOLDER != '' )
      $utable->add_tablehead(19, new TableHeadImage( T_('User picture#header'),
         'images/picture.gif', T_('Indicator for existing user picture') ), 'Image', 0, 'UserPicture+' );
   $utable->add_tablehead( 2, T_('Name#header'), 'User', 0, 'Name+');
   $utable->add_tablehead( 3, T_('Userid#header'), 'User', 0, 'Handle+');
   $utable->add_tablehead(16, T_('Country#header'), 'Image', 0, 'Country+');
   $utable->add_tablehead( 4, T_('Rank info#header'), null, TABLE_NO_SORT );
   $utable->add_tablehead( 5, T_('Rating#header'), 'Rating', 0, 'Rating2-');
   $utable->add_tablehead( 6, T_('Open for matches?#header'), null, TABLE_NO_SORT );
   $utable->add_tablehead( 7, new TableHead( T_('#Games#header'), T_('#Games') ), 'Number', 0, 'Games-');
   $utable->add_tablehead( 8, new TableHead( T_('Running#header'), T_('Running games') ), 'Number', 0, 'Running-');
   $utable->add_tablehead( 9, new TableHead( T_('Finished#header'), T_('Finished games') ), 'Number', 0, 'Finished-');
   $utable->add_tablehead(24, new TableHead( T_('#Running games with user#header'),
      sprintf('%s (%s)', T_('Running games with user'), T_('Restrictions do not apply'))), 'Number', 0, 'GS_Running-');
   $utable->add_tablehead(25, new TableHead( T_('#Finished games with user#header'),
      sprintf('%s (%s)', T_('Finished games with user'), T_('Restrictions do not apply'))), 'Number', 0, 'GS_Finished-');
   $utable->add_tablehead(17, T_('Rated#header'), 'Number', 0, 'RatedGames-');
   $utable->add_tablehead(10, T_('Won#header'), 'Number', 0, 'Won-');
   $utable->add_tablehead(11, T_('Lost#header'), 'Number', 0, 'Lost-');
   $utable->add_tablehead(12, T_('Win%#header'), 'Number', 0, 'RatedWinPercent-');
   $utable->add_tablehead(23, T_('Hero%#header'), 'Number', 0, 'HeroRatio-');
   $utable->add_tablehead(13, T_('Activity#header'), 'Image', TABLE_NO_HIDE, 'ActivityLevel-');
   $utable->add_tablehead(20, new TableHeadImage( T_('User online#header'), 'images/online.gif',
      sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS)
         . ', ' . T_('or on vacation#header') ), 'Image', 0 );
   $utable->add_tablehead(22, T_('Registered#header'), 'Date', 0, 'Registerdate+');
   $utable->add_tablehead(14, T_('Last access#header'), 'Date', 0, 'Lastaccess-');
   $utable->add_tablehead(15, T_('Last move#header'), 'Date', 0, 'LastMove-');

   $utable->set_default_sort( 1); //on ID
   $order = $utable->current_order_string();
   $limit = $utable->current_limit_string();


   // form for static filters
   $usform->set_area( 1 );
   $usform->set_layout( FLAYOUT_AREACONF, 1,
      array(
         'title' => T_('Restrictions on games with opponent (without MP-games)'),
         FAC_TABLE => 'id=gameFilter',
      ) );
   $usform->add_row( array(
         'DESCRIPTION', T_('Size'),
         'FILTER',      $usfilter, 1,
         'FILTERERROR', $usfilter, 1, '<br>'.$FERR1, $FERR2, true ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Rated'),
         'FILTER',      $usfilter, 2 ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Status'),
         'FILTER',      $usfilter, 4,
         'TEXT',        span('smaller', T_('full stats only for finished games'), '(%s)'),
         ));
   $usform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'FILTER',      $usfilter, 3,
         'FILTERERROR', $usfilter, 3, '<br>'.$FERR1, $FERR2, true ));
   if ( $opp )
   {
      $usform->add_row( array(
            'TAB',
            'CELL',     1, 'align=left',
            'OWNHTML',  implode( '', $ufilter->get_submit_elements( $usform ) ), ));
   }


   // build SQL-query (for user-table)
   $query_usfilter = $usfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_ufilter  = $utable->get_query(); // clause-parts for filter

   $uqsql = new QuerySQL( // base-query is to show only opponents
      SQLP_OPTS, 'DISTINCT',
      SQLP_FROM, 'Games AS G',
      SQLP_UNION_WHERE,
         "G.White_ID=$uid AND P.ID=G.Black_ID",
         "G.Black_ID=$uid AND P.ID=G.White_ID" );
   $uqsql->useUnionAll(false); // need distinct-UNION
   // NOTE: SQL without union: SQLP_WHERE, "(G.White_ID=$uid OR G.Black_ID=$uid)", "P.ID=G.White_ID+G.Black_ID-$uid"

   $uqsql->add_part( SQLP_FIELDS,
      'P.*', 'P.Rank AS Rankinfo',
      "(P.Activity>$ActiveLevel1)+(P.Activity>$ActiveLevel2) AS ActivityLevel",
      'P.Running+P.Finished AS Games',
      //i.e. RatedWinPercent = 100*(Won+Jigo/2)/RatedGames
      'ROUND(50*(RatedGames+Won-Lost)/RatedGames) AS RatedWinPercent',
      'IF(P.Finished>='.MIN_FIN_GAMES_HERO_AWARD.',P.GamesWeaker/P.Finished,0) AS HeroRatio',
      'UNIX_TIMESTAMP(P.Lastaccess) AS LastaccessU',
      'UNIX_TIMESTAMP(P.LastMove) AS LastMoveU',
      'UNIX_TIMESTAMP(P.Registerdate) AS X_Registerdate' );
   $uqsql->add_part( SQLP_FROM, 'Players AS P' );
   $uqsql->add_part( SQLP_WHERE, "G.GameType='".GAMETYPE_GO."'" );
   if ( !$opp )
   {
      $uqsql->add_part( SQLP_FIELDS,
         'COALESCE(GS.Running,0) AS GS_Running', 'COALESCE(GS.Finished,0) AS GS_Finished' );
      $uqsql->add_part( SQLP_FROM,
         "LEFT JOIN GameStats AS GS ON GS.uid=IF($uid<P.ID,$uid,P.ID) AND GS.oid=IF($uid<P.ID,P.ID,$uid)" );
   }
   $uqsql->merge( $query_usfilter );
   $uqsql->merge( $query_ufilter );
   $query = $uqsql->get_select() . "$order$limit";

   if ( $opp )
   {
      // build SQL-query (for stats-fields)
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'COUNT(*) as cntGames',
         'SUM(IF(G.Handicap>0,1,0)) as cntHandicap',
         'MAX(G.Handicap) as maxHandicap',
         'SUM(IF(G.Score=0 AND (G.Flags & '.GAMEFLAGS_NO_RESULT.')=0,1,0)) as cntJigo',
         'SUM(IF(G.Score=0 AND G.Flags > 0 AND (G.Flags & '.GAMEFLAGS_NO_RESULT.'),1,0)) as cntVoid' );
      $qsql->add_part( SQLP_FROM, 'Games AS G' );
      $qsql->merge ( $query_usfilter ); // clause-parts for filter

      // uid is black
      $qsql_black = new QuerySQL( SQLP_WHERE, "G.Black_ID=$uid", "G.White_ID=$opp" );
      $qsql_black->add_part( SQLP_FIELDS,
         // won
         'SUM(IF(G.Score<0,1,0)) as cntWon',
         'SUM(IF(G.Score='.-SCORE_FORFEIT.',1,0)) as cntWonForfeit',
         'SUM(IF(G.Score='.-SCORE_TIME.',1,0)) as cntWonTime',
         'SUM(IF(G.Score='.-SCORE_RESIGN.',1,0)) as cntWonResign',
         'SUM(IF(G.Score>='.-SCORE_MAX.' AND G.Score<0,1,0)) as cntWonScore',
         // lost
         'SUM(IF(G.Score>0,1,0)) as cntLost',
         'SUM(IF(G.Score='.SCORE_FORFEIT.',1,0)) as cntLostForfeit',
         'SUM(IF(G.Score='.SCORE_TIME.',1,0)) as cntLostTime',
         'SUM(IF(G.Score='.SCORE_RESIGN.',1,0)) as cntLostResign',
         'SUM(IF(G.Score>0 AND G.Score<='.SCORE_MAX.',1,0)) as cntLostScore' );
      $qsql_black->merge( $qsql );

      // uid is white
      $qsql_white = new QuerySQL( SQLP_WHERE, "G.White_ID=$uid", "G.Black_ID=$opp" );
      $qsql_white->add_part( SQLP_FIELDS,
         // won
         'SUM(IF(G.Score>0,1,0)) as cntWon',
         'SUM(IF(G.Score='.SCORE_FORFEIT.',1,0)) as cntWonForfeit',
         'SUM(IF(G.Score='.SCORE_TIME.',1,0)) as cntWonTime',
         'SUM(IF(G.Score='.SCORE_RESIGN.',1,0)) as cntWonResign',
         'SUM(IF(G.Score>0 AND G.Score<='.SCORE_MAX.',1,0)) as cntWonScore',
         // lost
         'SUM(IF(G.Score<0,1,0)) as cntLost',
         'SUM(IF(G.Score='.-SCORE_FORFEIT.',1,0)) as cntLostForfeit',
         'SUM(IF(G.Score='.-SCORE_TIME.',1,0)) as cntLostTime',
         'SUM(IF(G.Score='.-SCORE_RESIGN.',1,0)) as cntLostResign',
         'SUM(IF(G.Score>='.-SCORE_MAX.' AND G.Score<0,1,0)) as cntLostScore' );
      $qsql_white->merge( $qsql );

      // query database for user-stats for black & white (2 queries)
      $query_black = $qsql_black->get_select();
      $query_white = $qsql_white->get_select();
      $AB = extract_user_stats( 'black', $query_black );
      $AW = extract_user_stats( 'white', $query_white );
   }
   else
   {
      // dummy fill with 0-vals
      $AB = extract_user_stats( 'black' );
      $AW = extract_user_stats( 'white' );
   }

   // query database for user-table
   if ( $opp )
      $result = NULL;
   else
   {
      $result = db_query( "opponents.find_stats($uid,$opp)", $query );

      $show_rows = $utable->compute_show_rows(mysql_num_rows($result));
      $utable->set_found_rows( mysql_found_rows('opponents.found_rows') );
   }

   if ( $opp )
   {
      //players infos
      $usform->set_area( 2 );
      $usform->add_row( array(
            'TEXT', print_players_table( $players, $uid, $opp, $finished ) ));

      // add stats-table into form (0-value-table if no opp)
      $usform->set_area( 3 );
      $usform->add_row( array(
            'TEXT', print_stats_table( $players, $AB, $AW, $finished ) ));
   }


   $stats_for = T_('Game statistics for player %s');
   $opp_for   = T_('Opponents of player %1$s: %2$s');
   $title1 = sprintf( $stats_for, make_html_safe( $players[$uid]['Name']) );
   $tmp = user_reference( REF_LINK, 1, '', $players[$uid]);
   $title2 = sprintf( $stats_for, $tmp );
   $title3 = sprintf( $opp_for,   $tmp, echo_rating( @$players[$uid]['Rating2'], true, $uid ) );

   start_page( $title1, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   // static filter-values
   $arrtmp = array();
   $filterURL = $usfilter->get_url_parts( $arrtmp );
   if ( $filterURL )
      $filterURL .= URI_AMP;

   // build user-table
   if ( !is_null($result) ) // only if !$opp
   {
      while ( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $ID = $row['ID'];

         $urow_strings = array();
         if ( $utable->Is_Column_Displayed[ 1] )
         {
            $ulink = ( $tid )
               ? "tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$ID"
               : "{$page}{$filterURL}uid=$uid".URI_AMP."opp=$ID";
            $urow_strings[1] = button_TD_anchor( $ulink, $ID );
         }
         if ( $utable->Is_Column_Displayed[ 2] )
            $urow_strings[ 2] = "<A href=\"userinfo.php?uid=$ID\">" .
               make_html_safe($row['Name']) . "</A>";
         if ( $utable->Is_Column_Displayed[ 3] )
            $urow_strings[ 3] = "<A href=\"userinfo.php?uid=$ID\">" . $row['Handle'] . "</A>";
         if ( $utable->Is_Column_Displayed[16] )
            $urow_strings[16] = getCountryFlagImage( @$row['Country'] );
         if ( $utable->Is_Column_Displayed[ 4] )
            $urow_strings[ 4] = make_html_safe(@$row['Rankinfo'],INFO_HTML);
         if ( $utable->Is_Column_Displayed[ 5] )
            $urow_strings[ 5] = echo_rating(@$row['Rating2'],true,$ID);
         if ( $utable->Is_Column_Displayed[ 6] )
            $urow_strings[ 6] = make_html_safe($row['Open'],INFO_HTML);
         if ( $utable->Is_Column_Displayed[ 7] )
            $urow_strings[ 7] = $row['Games'];
         if ( $utable->Is_Column_Displayed[ 8] )
            $urow_strings[ 8] = $row['Running'];
         if ( $utable->Is_Column_Displayed[ 9] )
            $urow_strings[ 9] = $row['Finished'];
         if ( $utable->Is_Column_Displayed[17] )
            $urow_strings[17] = $row['RatedGames'];
         if ( $utable->Is_Column_Displayed[10] )
            $urow_strings[10] = $row['Won'];
         if ( $utable->Is_Column_Displayed[11] )
            $urow_strings[11] = $row['Lost'];
         if ( $utable->Is_Column_Displayed[12] )
            $urow_strings[12] = ( is_numeric($row['RatedWinPercent']) ? $row['RatedWinPercent'].'%' : '' );
         if ( $utable->Is_Column_Displayed[13] )
            $urow_strings[13] = activity_string( $row['ActivityLevel']);
         if ( $utable->Is_Column_Displayed[14] )
            $urow_strings[14] = ($row['LastaccessU'] > 0 ? date(DATE_FMT2, $row['LastaccessU']) : '' );
         if ( $utable->Is_Column_Displayed[15] )
            $urow_strings[15] = ($row['LastMoveU'] > 0 ? date(DATE_FMT2, $row['LastMoveU']) : '' );
         if ( $utable->Is_Column_Displayed[18] )
            $urow_strings[18] = build_usertype_text($row['Type'], ARG_USERTYPE_NO_TEXT, true, ' ');
         if ( @$row['UserPicture'] && $utable->Is_Column_Displayed[19] )
            $urow_strings[19] = UserPicture::getImageHtml( @$row['Handle'], true );
         if ( $utable->Is_Column_Displayed[20] )
            $urow_strings[20] = echo_user_online_vacation( @$row['OnVacation'], @$row['LastaccessU'] );
         if ( $utable->Is_Column_Displayed[21] )
         {
            $out = array();
            if ( $f_status_val != FSTATVAL_FIN ) // ALL | RUN
               $out[] = ($row['GS_Running'] > 0) ? echo_image_opp_games( $uid, $row['Handle'], false ) : insert_width(20);
            if ( $f_status_val != FSTATVAL_RUN ) // ALL | FIN
               $out[] = ($row['GS_Finished'] > 0) ? echo_image_opp_games( $uid, $row['Handle'], true ) : insert_width(20);
            $urow_strings[21] = implode('', $out);
         }
         if ( $utable->Is_Column_Displayed[22] )
            $urow_strings[22] = ($row['X_Registerdate'] > 0 ? date(DATE_FMT_YMD, $row['X_Registerdate']) : '' );
         if ( $utable->Is_Column_Displayed[23] )
         {
            $hero_ratio = $row['HeroRatio'];
            $urow_strings[23] = ( $hero_ratio * 100 >= HERO_BRONZE )
               ? echo_image_hero_badge($hero_ratio) . ' ' . sprintf('%d%%', 100*$hero_ratio)
               : '';
         }
         if ( $utable->Is_Column_Displayed[24] )
            $urow_strings[24] = $row['GS_Running'];
         if ( $utable->Is_Column_Displayed[25] )
            $urow_strings[25] = $row['GS_Finished'];

         $utable->add_row( $urow_strings );
      }
      mysql_free_result($result);
   }//user-list

   // print form with user-table
   if ( $opp ) // has opp
   {
      // print static-filter, player-info, stats-table
      echo "<h3 class=Header>$title2</h3>\n"
         . $usform->print_start_default()
         . $usform->get_form_string() // static form
         . $usform->print_end()
         ;
   }
   else // no opp
   {
      // print static-filter, user-table
      echo "<h3 class=Header>$title3</h3>\n"
         . $usform->print_start_default()
         . $usform->get_form_string() // static form
         . $utable->make_table()
         . $usform->print_end()
         ;
   }

   // end of table

   $menu_array = array();
   if ( $tid )
      $menu_array[ T_('Show users') ] = "users.php?tid=$tid";
   if ( $opp )
      $menu_array[ T_('Show opponents') ] = "{$page}{$filterURL}uid=$uid";

   if ( $uid != $my_id ) // other opponents
   {
      $menu_array[ T_('Show my opponents') ]   = "{$page}{$filterURL}uid=$my_id";
      if ( $opp != $my_id )
      {
         $menu_array[ T_('Show me as opponent') ] = "{$page}{$filterURL}uid=$uid".URI_AMP."opp=$my_id";
         $menu_array[ T_('Show as my opponent') ] = "{$page}{$filterURL}uid=$my_id".URI_AMP."opp=$uid";
      }
   }

   if ( $opp )
      $menu_array[ T_('Switch opponent role') ] = "{$page}{$filterURL}uid=$opp".URI_AMP."opp=$uid";

   end_page(@$menu_array);
}//main


// return array with dbfields extracted from passed db-result
// keys: cntGames, cntJigo, cntVoid, (cnt|max)Handicap, cnt(Won|Lost)(|Time|Resign|Score)
// param query: if null, only fill array with 0-values
function extract_user_stats( $color, $query = null )
{
   static $ARR_DBFIELDKEYS = array(
      'cntGames',
      'cntJigo',
      'cntVoid',
      'cntHandicap', 'maxHandicap',
      'cntWon',  'cntWonForfeit',  'cntWonTime',  'cntWonResign',  'cntWonScore',
      'cntLost', 'cntLostForfeit', 'cntLostTime', 'cntLostResign', 'cntLostScore'
   );

   $arr = array();
   if ( !is_null($query) )
   {
      $result = db_query( "opponents.extract_user_stats($color)", $query );
      if ( $row = mysql_fetch_assoc( $result ) )
      {
         foreach ( $ARR_DBFIELDKEYS as $key )
            $arr[$key] = $row[$key];
      }
      mysql_free_result($result);
   }

   foreach ( $ARR_DBFIELDKEYS as $key )
   {
      if ( @$arr[$key] == '' )
         $arr[$key] = 0;
   }

   return $arr;
}//extract_user_stats

// echo table with info about both players: uid and opponent
// param p: players-array[$uid,$opp] = ( ID, Name, Handle, Rating2, Country )
// param opp: maybe 0|empty
function print_players_table( $p, $uid, $opp, $fin )
{
   $p1 = $p[$uid];
   $p2 = ( $opp && isset($p[$opp]) ) ? $p[$opp] : null;
   $SPC = ''; //'&nbsp;';

   $rowpatt = "  <tr><td class=Rubric>%s</td><td>%s</td><td>%s</td></tr>\n";

   $r = "<table id=playersInfos class=Infos>\n";
   //TODO CSS; review it:
   //$r .= "<colgroup><col class=ColRubric><col class=ColInfo><col class=ColInfo></colgroup>\n";

   // header
   $r .= "  <tr>\n";
   $r .= "    <th></th>\n";
   $r .= "    <th>".T_('Player')."</th>\n";
   $r .= "    <th>".T_('Opponent')."</th>\n";
   $r .= "  </tr>\n";

   // Name, Handle
   $r .= sprintf( $rowpatt, T_('Name'),
      "<A href=\"userinfo.php?uid=$uid\">" . make_html_safe( $p1['Name']) . "</A>",
      ( $p2
         ? "<A href=\"userinfo.php?uid=$opp\">" . make_html_safe( $p2['Name']) . "</A>"
         : NO_VALUE ) );
   $r .= sprintf( $rowpatt, T_('Userid'),
      $p1['Handle'] . ( $p2 ? MED_SPACING . echo_image_opp_games( $uid, $p2['Handle'], $fin ) : '' ),
      ( $p2 ? $p2['Handle'] : $SPC) );

   // Country
   $c1 = getCountryFlagImage( $p1['Country'] );
   $c2 = getCountryFlagImage( ($p2 ? $p2['Country'] : '') );
   $r .= sprintf( $rowpatt, T_('Country'),
      $c1,
      ( $p2 ? $c2 : $SPC ) );

   // Open for matches?
   $r .= sprintf( $rowpatt, T_('Open for matches?'),
      make_html_safe(@$p1['Open'],INFO_HTML),
      $p2 ? make_html_safe(@$p2['Open'],INFO_HTML) : $SPC );

   // Activity
   $r .= sprintf( $rowpatt, T_('Activity'),
      activity_string( $p1['ActivityLevel'] ),
      ( $p2 ? activity_string( $p2['ActivityLevel'] ) : $SPC ) );

   // Rating2
   $r .= sprintf( $rowpatt, T_('Rating'),
      echo_rating( $p1['Rating2'], true, $uid ),
      ( $p2 ? echo_rating( $p2['Rating2'], true, $opp ) : $SPC ) );

   // Rank info
   $r .= sprintf( $rowpatt, T_('Rank info'),
      make_html_safe(@$p1['Rank'],INFO_HTML),
      $p2 ? make_html_safe(@$p2['Rank'],INFO_HTML) : $SPC );

   // Last accessed, Last move
   $r .= sprintf( $rowpatt, T_('Last access'),
      ( $p1['LastaccessU'] > 0 ? date(DATE_FMT2, $p1['LastaccessU']) : $SPC ),
      ( $p2 && $p2['LastaccessU'] > 0 ? date(DATE_FMT2, $p2['LastaccessU']) : $SPC ) );
   $r .= sprintf( $rowpatt, T_('Last move'),
      ( $p1['LastMoveU'] > 0 ? date(DATE_FMT2, $p1['LastMoveU']) : $SPC ),
      ( $p2 && $p2['LastMoveU'] > 0 ? date(DATE_FMT2, $p2['LastMoveU']) : $SPC ) );

   $r .= '</table>';
   return $r;
}//print_players_table

// echo table with info about both players: uid and opponent
// param p: players-array[$uid,$opp] = ( ID, Name, Handle, Rating2, Country )
// param B|W: array with $ARR_DBFIELDKEYS for black and white
function print_stats_table( $p, $B, $W, $fin )
{

   $rowpatt   = "  <tr %s> <td class=Rubric>%s</td>  <td colspan=2>%s</td>  <td colspan=2>%s</td>  <td colspan=2 class=\"Sum\">%s</td>  </tr>\n";
   $rowpatt2  = "  <tr %s> <td class=Rubric>%s</td>  <td>%s</td>  <td>%s</td>  <td>%s</td>  <td>%s</td>  <td class=\"Sum\">%s</td>  <td class=\"Sum\">%s</td>  </tr>\n";
   #$rowpatt   = "  <tr %s> <td nowrap=\"1\"><b>%s</b></td>  <td colspan=2>%s</td>  <td colspan=2>%s</td>  <td colspan=2>%s</td>  </tr>\n";
   #$rowpatt2  = "  <tr %s> <td nowrap=\"1\"><b>%s</b></td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  <td width=30>%s</td>  </tr>\n";
   $trclass_sum = 'class="Sum Number"';
   $trclass_num = 'class="Number"';

   $r = "<table id=gameStats class=Infos>\n";
   //TODO CSS; review it:
   //$r .= "<colgroup><col class=ColRubric><col class=ColInfo><col class=ColInfo></colgroup>\n";

   // header
   $r .= "  <tr>\n";
   $r .= "    <th class=Rubric>" . T_('Players color') .":</th>\n";
   $r .= "    <th colspan=2><img src=\"17/b.gif\" alt=\"as-black\"></th>\n";
   $r .= "    <th colspan=2><img src=\"17/w.gif\" alt=\"as-white\"></th>\n";
   $r .= "    <th colspan=2 class=Sum>" . T_('Sum') . "</th>\n";
   $r .= "  </tr>\n";

   $cnt_games  = $B['cntGames'] + $W['cntGames'];
   $won_games  = $B['cntWon'] + $W['cntWon'];
   $jigo_games = $B['cntJigo'] + $W['cntJigo'];
   $void_games = $B['cntVoid'] + $W['cntVoid'];

   if ( $fin )
   {
      // stats: win/lost-ratio
      $ratio = ( $cnt_games == 0) ? 0 : round( 100 * ( $won_games + $jigo_games/2 ) / $cnt_games );
      $ratio_black = ( $B['cntGames'] == 0) ? 0 : round( 100 * ( $B['cntWon'] + $B['cntJigo']/2 ) / $B['cntGames'] );
      $ratio_white = ( $W['cntGames'] == 0) ? 0 : round( 100 * ( $W['cntWon'] + $W['cntJigo']/2 ) / $W['cntGames'] );
      $r .= sprintf( $rowpatt,
         $trclass_num, T_('Win-Ratio on #Games'),
         $ratio_black.'%', $ratio_white.'%', $ratio.'%' );
   }

   // stats: total games
   $r .= sprintf( $rowpatt,
      $trclass_num, T_('#Games'),
      $B['cntGames'],
      $W['cntGames'],
      $cnt_games );

   if ( $fin )
   {
      // stats: won + lost games
      $r .= sprintf( $rowpatt2,
         $trclass_sum, T_('#Games won : lost'),
         $B['cntWon'], $B['cntLost'],
         $W['cntWon'], $W['cntLost'],
         ($won_games), ($B['cntLost'] + $W['cntLost']) );
      $r .= sprintf( $rowpatt2,
         $trclass_num, T_('#Games won : lost by Score'),
         $B['cntWonScore'], $B['cntLostScore'],
         $W['cntWonScore'], $W['cntLostScore'],
         ($B['cntWonScore'] + $W['cntWonScore']), ($B['cntLostScore'] + $W['cntLostScore']) );
      $r .= sprintf( $rowpatt2,
         $trclass_num, T_('#Games won : lost by Resignation'),
         $B['cntWonResign'], $B['cntLostResign'],
         $W['cntWonResign'], $W['cntLostResign'],
         ($B['cntWonResign'] + $W['cntWonResign']), ($B['cntLostResign'] + $W['cntLostResign']) );
      $r .= sprintf( $rowpatt2,
         $trclass_num, T_('#Games won : lost by Time'),
         $B['cntWonTime'], $B['cntLostTime'],
         $W['cntWonTime'], $W['cntLostTime'],
         ($B['cntWonTime'] + $W['cntWonTime']), ($B['cntLostTime'] + $W['cntLostTime']) );
      $r .= sprintf( $rowpatt2,
         $trclass_num, T_('#Games won : lost by Forfeit'),
         $B['cntWonForfeit'], $B['cntLostForfeit'],
         $W['cntWonForfeit'], $W['cntLostForfeit'],
         ($B['cntWonForfeit'] + $W['cntWonForfeit']), ($B['cntLostForfeit'] + $W['cntLostForfeit']) );

      // stats: jigo
      $r .= sprintf( $rowpatt,
         $trclass_num, T_('#Games with Jigo'),
         $B['cntJigo'],
         $W['cntJigo'],
         $B['cntJigo'] + $W['cntJigo'] );

      // stats: void
      $r .= sprintf( $rowpatt,
         $trclass_num, T_('#Games with No-Result'),
         $B['cntVoid'],
         $W['cntVoid'],
         $B['cntVoid'] + $W['cntVoid'] );
   }

   // stats: handicap-games
   $r .= sprintf( $rowpatt,
      $trclass_num, T_('#Games with handicap'),
      $B['cntHandicap'],
      $W['cntHandicap'],
      $B['cntHandicap'] + $W['cntHandicap'] );
   $r .= sprintf( $rowpatt,
      $trclass_num, T_('Maximum handicap'),
      $B['maxHandicap'],
      $W['maxHandicap'],
      max($B['maxHandicap'], $W['maxHandicap']) );

   $r .= '</table>';
   return $r;
}//print_stats_table

?>

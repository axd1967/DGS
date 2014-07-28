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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/std_classes.php';
require_once 'include/countries.php';
require_once 'include/rulesets.php';
require_once 'include/rating.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'include/game_functions.php';
require_once 'include/time_functions.php';
require_once 'include/contacts.php';
require_once 'include/filter.php';
require_once 'include/filterlib_country.php';
require_once 'include/classlib_profile.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/wroom_control.php';

{
   // NOTE: using page: join_waitingroom_game.php

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'waiting_room');

   $my_id = $player_row['ID'];

   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_WAITINGROOM );
   if ( !$cfg_tblcols )
      error('user_init_error', 'waiting_room.init.config_table_cols');

   //short descriptions for table
   $handi_array = array(
      HTYPE_CONV   => T_('Conventional#wr_htype'),
      HTYPE_PROPER => T_('Proper#wr_htype'),
      HTYPE_NIGIRI => T_('Nigiri#wr_htype'),
      HTYPE_DOUBLE => T_('Double game#wr_htype'),
      HTYPE_BLACK  => T_('Color Black#wr_htype'),
      HTYPE_WHITE  => T_('Color White#wr_htype'),
      HTYPEMP_MANUAL => T_('Manual#wr_htype'),
      HTYPE_AUCTION_OPEN => T_('Open Auction#wr_htype'),
      HTYPE_AUCTION_SECRET => T_('Secret Auction#wr_htype'),
      HTYPE_YOU_KOMI_I_COLOR => T_('You cut, I choose#wr_htype'),
      HTYPE_I_KOMI_YOU_COLOR => T_('I cut, you choose#wr_htype'),
   );

   // config for handicap-filter
   $handi_filter_array = array(
      T_('All')            => '',
      T_('Conventional')   => "Handicaptype='conv'",
      T_('Proper')         => "Handicaptype='proper'",
      T_('Nigiri')         => "Handicaptype='nigiri'",
      T_('Double')         => "Handicaptype='double'",
      T_('Manual')         => "Handicaptype IN ('nigiri','double','black','white')",
      T_('Fix Color')      => "Handicaptype IN ('double','black','white')",
      T_('Fair Komi')      => "Handicaptype IN ('auko_sec','auko_opn','div_ykic','div_ikyc')",
      T_('No Fair Komi')   => "Handicaptype NOT IN ('auko_sec','auko_opn','div_ykic','div_ikyc')",
   );

   // sync with QuickHandlerWaitingroom list-cmd
   $suitable_filter_array = array(
      T_('All')      => new QuerySQL( SQLP_WHERE, "WR.uid<>$my_id" ),
      T_('Suitable') => WaitingroomControl::extend_query_waitingroom_suitable(
         new QuerySQL( SQLP_WHERE, "WR.uid<>$my_id" ) ),
      T_('Mine')     => new QuerySQL( SQLP_WHERE, "WR.uid=$my_id" ),
   );

   $game_type_filter_array = MultiPlayerGame::build_game_type_filter_array();
   $game_type_filter_array[T_('Shape-Game')] = "ShapeID>0";
   $game_type_filter_array[T_('No Shapes')] = "ShapeID=0";

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $idinfo = (int)@$_GET['info'];
   if ( $idinfo < 0)
      $idinfo = 0;
   else
   {
      $gid = (int)get_request_arg('gid');
      if ( $gid > 0 )
      {
         list( $idinfo, $wr_uid ) = load_waitingroom_info( $gid );
         if ( $idinfo )
            set_request_arg('good', ( $wr_uid == $my_id ) ? 2 : 0 ); // my|all-wrgames-view
      }
   }

   $page = "waiting_room.php";

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_WAITINGROOM );
   $wrfilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'good' ); // named-filters FC_FNAME
   $wrtable = new Table( 'waitingroom', $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $wrtable->set_profile_handler( $search_profile );
   $search_profile->handle_action( ( $idinfo ? SPROF_CURR_VALUES : null ) ); // special handling b/c info on same page

   // table filters
   $wrfilter->add_filter( 1, 'Text',      'WRP.Name', true);
   $wrfilter->add_filter( 2, 'Text',      'WRP.Handle', true);
   $wrfilter->add_filter( 3, 'Rating',    'WRP.Rating2', true);
   $wrfilter->add_filter( 5, 'Selection', $handi_filter_array, true);
   $wrfilter->add_filter( 6, 'Numeric',   'Komi', true, array( FC_SIZE => 4 ));
   $wrfilter->add_filter( 7, 'Numeric',   'Size', true, array( FC_SIZE => 4 ));
   $wrfilter->add_filter( 8, 'Selection', $suitable_filter_array, true,
         array( FC_FNAME => 'good', FC_STATIC => 1, FC_DEFAULT => 1 ));
   $wrfilter->add_filter( 9, 'Selection',
         array( T_('All') => '',
                T_('Japanese') => sprintf( "Byotype='%s'", BYOTYPE_JAPANESE ),
                T_('Canadian') => sprintf( "Byotype='%s'", BYOTYPE_CANADIAN ),
                T_('Fischer')  => sprintf( "Byotype='%s'", BYOTYPE_FISCHER ), ),
         true);
   $wrfilter->add_filter(11, 'RatedSelect', 'Rated', true);
   $wrfilter->add_filter(12, 'BoolSelect', 'Weekendclock', true);
   if ( ENABLE_STDHANDICAP )
      $wrfilter->add_filter(13, 'BoolSelect', 'StdHandicap', true);
   $wrfilter->add_filter(15, 'Country', 'WRP.Country', false,
         array( FC_HIDE => 1 ));
   $wrfilter->add_filter(19, 'Selection', Ruleset::build_ruleset_filter_array(), true);
   $wrfilter->add_filter(20, 'Selection', $game_type_filter_array, true);
   $wrfilter->init();
   $f_suitable =& $wrfilter->get_filter(8);
   $suitable = ( $f_suitable->get_value() == 1 ); // all=0, suitable=1, mine=2


   // init table
   $wrtable->register_filter( $wrfilter );
   $wrtable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   // NOTE: Keep the headers "..#headerwr" to allow translators to solve local language ambiguities
   // NOTE: The TABLE_NO_HIDEs are needed, because the columns are needed
   //       for the "static" filtering(!) of; also see named-filters
   $wrtable->add_tablehead(17, T_('Info#header'), 'Button', TABLE_NO_HIDE|TABLE_NO_SORT);
   $wrtable->add_tablehead(16, T_('UserType#headerwr'), 'User', 0, 'WRP_Type+');
   $wrtable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'WRP_Name+');
   $wrtable->add_tablehead( 2, T_('Userid#header'), 'User', 0, 'WRP_Handle+');
   $wrtable->add_tablehead(15, T_('Country#header'), 'Image', 0, 'WRP_Country+');
   $wrtable->add_tablehead( 3, T_('Rating#header'), 'Rating', 0, 'WRP_Rating2-');
   $wrtable->add_tablehead( 4, T_('Comment#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead(20, T_('GameType#header'), '', TABLE_NO_HIDE, 'GameType+');
   $wrtable->add_tablehead(19, T_('Ruleset#header'), '', 0, 'Ruleset+');
   $wrtable->add_tablehead( 7, T_('Size#header'), 'Number', 0, 'Size-');
   $wrtable->add_tablehead( 5, T_('Type#headerwr'), '', 0, 'Handicaptype+');
   $wrtable->add_tablehead(18, T_('Settings#headerwr'), 'GameSettings', TABLE_NO_HIDE|TABLE_NO_SORT );
   $wrtable->add_tablehead(14, T_('Handicap#header'), 'Number', 0, 'Handicap+');
   $wrtable->add_tablehead( 6, T_('Komi#header'), 'Number', 0, 'Komi-');
   $wrtable->add_tablehead( 8, T_('Restrictions#header'), '', TABLE_NO_HIDE|TABLE_NO_SORT);
   $wrtable->add_tablehead( 9, T_('Time limit#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead(11, T_('Rated#header'), '', 0, 'Rated-');
   $wrtable->add_tablehead(10, T_('#Games#header'), 'Number', 0, 'nrGames-');
   $wrtable->add_tablehead(12, T_('Weekend Clock#header'), '', 0, 'WeekendClock-');
   // NOTE: User can choose:
   // View "merged" "Handicap + StdPlacement (in Settings column),
   // but has separate column on StdPlacement for filtering on it:
   if ( ENABLE_STDHANDICAP )
      $wrtable->add_tablehead(13, T_('Standard placement#header'), '', 0, 'StdHandicap-');

   $wrtable->set_default_sort( 3, 2); //on WRP_Rating2,WRP_Handle
   $order = $wrtable->current_order_string('ID+');
   $limit = $wrtable->current_limit_string();

   $baseURLMenu = "waiting_room.php?"
      . $wrtable->current_rows_string(1)
      . $wrtable->current_sort_string(1); //end sep
   $baseURL = $baseURLMenu
      . $wrtable->current_filter_string(1)
      . $wrtable->current_from_string(1); //end sep

   $qsql = WaitingroomControl::build_waiting_room_query( 0, $suitable );

   $qsql->merge( $wrtable->get_query() );
   $query = $qsql->get_select() . "$order$limit";
   $result = db_query( 'waiting_room.find_waiters', $query );

   $show_rows = $wrtable->compute_show_rows(mysql_num_rows($result));
   $wrtable->set_found_rows( mysql_found_rows('waiting_room.found_rows') );


   if ( $suitable )
      $title = T_('Suitable waiting games');
   elseif ( $f_suitable->get_value() == 2 )
      $title = T_('My waiting games');
   else
      $title = T_('All waiting games');

   start_page($title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   echo "<h3 class=Header>". $title . "</h3>\n";

   $maxGamesCheck = new MaxGamesCheck();
   if ( !$idinfo )
      echo $maxGamesCheck->get_warn_text();

   $info_row = $info_joinable = NULL;
   if ( $show_rows > 0 || $wrfilter->has_query() )
   {
      while ( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $WRP_Rating2 = NULL;
         $wro = new WaitingroomOffer( $row );
         $wro_settings = $wro->calculate_offer_settings();
         $is_fairkomi = $wro->is_fairkomi();
         extract($row); //including: $calculated, $goodrated, $haverating, $goodrating, $goodmingames, $goodhero, $goodmaxgames, $goodsameopp, $X_Time

         list( $restrictions, $joinable ) = WaitingroomControl::get_waitingroom_restrictions( $row, $suitable );

         if ( $GameType != GAMETYPE_GO ) // MPG
            $Handicaptype = HTYPEMP_MANUAL;
         if ( $idinfo == (int)$ID )
         {
            $info_row = $row;
            $info_joinable = $joinable;
         }

         $row_arr = array();
         if ( $wrtable->Is_Column_Displayed[ 1] )
            $row_arr[ 1] = user_reference( REF_LINK, 1, '', $WRP_ID, $WRP_Name, '');
         if ( $wrtable->Is_Column_Displayed[ 2] )
         {
            $row_arr[ 2] = user_reference( REF_LINK, 1, '', $WRP_ID, $WRP_Handle, '') .
               echo_image_hero_badge( $WRP_HeroRatio );
         }
         if ( $wrtable->Is_Column_Displayed[ 3] )
            $row_arr[ 3] = echo_rating($WRP_Rating2, true, $WRP_ID);
         if ( $wrtable->Is_Column_Displayed[ 4] )
            $row_arr[ 4] = make_html_safe($Comment, INFO_HTML);
         if ( $wrtable->Is_Column_Displayed[ 5] ) // Handicaptype
         {
            $row_arr[ 5] = array('text' => $handi_array[$Handicaptype]);
            if ( !$haverating )
               $row_arr[ 5]['attbs'] = warning_cell_attb( T_('User has no rating'), true);
         }
         if ( $wrtable->Is_Column_Displayed[ 6] ) // Komi
         {
            if ( $calculated )
               $k_str = GameSettings::build_adjust_komi( $AdjKomi, $JigoMode, /*short*/true );
            elseif ( $is_fairkomi )
               $k_str = '';
            else
               $k_str = (float)$Komi;
            $row_arr[ 6] = ( (string)$k_str != '' ) ? $k_str : NO_VALUE;
         }
         if ( $wrtable->Is_Column_Displayed[ 7] )
            $row_arr[ 7] = $Size;
         if ( $wrtable->Is_Column_Displayed[ 8] )
         {
            $row_arr[ 8] = array( 'text' => $restrictions );
            if ( !$joinable )
               $row_arr[ 8]['attbs']= warning_cell_attb( T_('Restricted#wroom'), true);
         }
         if ( $wrtable->Is_Column_Displayed[ 9] )
            $row_arr[ 9] = TimeFormat::echo_time_limit(
                  $Maintime, $Byotype, $Byotime, $Byoperiods, TIMEFMT_SHORT|TIMEFMT_ADDTYPE);
         if ( $wrtable->Is_Column_Displayed[10] )
            $row_arr[10] = ($wro->mp_player_count) ? 1 : $nrGames;
         if ( $wrtable->Is_Column_Displayed[11] )
         {
            $row_arr[11] = array( 'text' => yesno($Rated) );
            if ( !$goodrated )
               $row_arr[11]['attbs'] = warning_cell_attb( T_('User has no rating'), true );
         }
         if ( $wrtable->Is_Column_Displayed[12] )
            $row_arr[12] = yesno( $WeekendClock);
         if ( ENABLE_STDHANDICAP && $wrtable->Is_Column_Displayed[13] )
            $row_arr[13] = yesno( $StdHandicap);
         if ( $wrtable->Is_Column_Displayed[14] ) // Handicap
         {
            $h_str = ( $calculated )
               ? GameSettings::build_adjust_handicap( $Size, $AdjHandicap, $MinHandicap, $MaxHandicap, /*short*/true )
               : $Handicap;
            $row_arr[14] = ( (string)$h_str != '' ) ? $h_str : NO_VALUE;
         }
         if ( $wrtable->Is_Column_Displayed[15] )
            $row_arr[15] = getCountryFlagImage( @$row['WRP_Country'] );
         if ( $wrtable->Is_Column_Displayed[16] )
            $row_arr[16] = build_usertype_text($WRP_Type, ARG_USERTYPE_NO_TEXT, true, '');
         if ( $wrtable->Is_Column_Displayed[17] )
            $row_arr[17] = button_TD_anchor( $baseURL."info=$ID#joingameForm", T_('Info'));
         if ( $wrtable->Is_Column_Displayed[18] ) // Settings (resulting Color + Handi + Komi)
            $row_arr[18] = $wro_settings;
         if ( $wrtable->Is_Column_Displayed[19] )
            $row_arr[19] = Ruleset::getRulesetText($Ruleset);
         if ( $wrtable->Is_Column_Displayed[20] )
         {
            $row_arr[20] =
               ( $ShapeID > 0 ? echo_image_shapeinfo($ShapeID, $Size, $ShapeSnapshot) . ' ' : '' ) .
               GameTexts::format_game_type($GameType, $GamePlayers) .
               ( $is_fairkomi ? GameTexts::build_fairkomi_gametype(GAME_STATUS_KOMI) : '' );
         }

         $wrtable->add_row( $row_arr );
      }//while

      // print table
      echo $wrtable->make_table();

      $show_info = ( $idinfo && is_array($info_row) );
      if ( !$show_info )
      {
         $notes = array();
         $notes[] = T_('Column \'Settings\' shows the probable game-color, handicap and komi.')
               . sprintf( '<br>%s = %s', T_('(Free Handicap)#handicap_tablewr'), T_('indicator of free handicap stone placement') );
         $notes[] =
            sprintf( T_('Column \'%s\' shows the handicap or its limitations, e.g. 5 or [0,9] or [0,D9] or %s#wroom'),
                     T_('Handicap#header'), NO_VALUE )
               . "<br>'D' = " . T_('indicator for calculated default max. handicap for board-size#wroom')
               . '; '.NO_VALUE.' = ' . T_('calculated handicap#wroom');
         $notes[] = null;
         $notes[] = T_('A waiting game is <b>suitable</b> when a player matches the requested game restrictions on:')
               . "\n* " . implode(",\n* ", build_game_restriction_notes() );
         echo_notes( 'waitingroomnotes', T_('Waiting room notes'), $notes );
      }
   }
   else
      echo '<p></p>&nbsp;<p></p>' . T_('Seems to be empty at the moment.');
   mysql_free_result($result);


   if ( @$show_info )
      add_old_game_form( 'joingame', $info_row, $iamrated, $info_joinable );


   $menu_array = array();
   $menu_array[T_('New Game')] = 'new_game.php';
   $menu_array[T_('All waiting games')] = $baseURLMenu.'good=0';
   $menu_array[T_('Suitable waiting games')] = $baseURLMenu.'good=1';
   $menu_array[T_('My waiting games')] = $baseURLMenu.'good=2'.SPURL_NO_DEF;

   end_page(@$menu_array);
}//main


function add_old_game_form( $form_id, $game_row, $iamrated, $joinable )
{
   global $player_row;
   static $ARR_COPY_FIELDS = array(
      'WRP_ID' => 'other_id',
      'WRP_Handle' => 'other_handle',
      'WRP_Name' => 'other_name',
      'WRP_Rating2' => 'other_rating',
      'WRP_RatingStatus' => 'other_ratingstatus', );

   $wro = new WaitingroomOffer( $game_row );
   $is_my_game = $wro->is_my_game();
   $my_id = $player_row['ID'];
   $opp_id = $game_row['uid'];
   list( $can_join, $html_out, $join_warning, $join_error ) = $wro->check_joining_waitingroom(/*html*/true);

   $game_form = new Form($form_id, 'join_waitingroom_game.php', FORM_POST, true);

   $game_row['X_TotalCount'] = ($is_my_game) ? 0 : GameHelper::count_started_games( $my_id, $opp_id );
   foreach ( $ARR_COPY_FIELDS as $src => $trg )
      $game_row[$trg] = $game_row[$src];

   $gs_wroom = GameSetup::new_from_waitingroom_game_row( $game_row );
   $gs_wroom->read_waitingroom_fields( $game_row );

   game_info_table( GSET_WAITINGROOM, $game_row, $player_row, $iamrated, $gs_wroom );

   $game_form->add_hidden( 'id', $game_row['ID'] ); // wroom-id
   if ( $is_my_game )
   {
      $game_form->add_row( array(
            'HIDDEN', 'delete', 't',
            'SUBMITBUTTON', 'deletebut', T_('Delete'),
         ));
   }
   elseif ( $can_join && $joinable )
   {
      $game_form->add_row( array(
            'SUBMITBUTTONX', 'join', T_('Join'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
         ));
   }

   $game_form->echo_string(1);
   echo $html_out;
}//add_old_game_form

// find waiting-room-id with WR-owner-user-id for given game-id
function load_waitingroom_info( $gid )
{
   $row = mysql_single_fetch( "waiting_room.load_waitingroom_info($gid)",
      "SELECT ID, uid FROM Waitingroom WHERE gid=$gid LIMIT 1" );
   return ($row) ? array( $row['ID'], $row['uid'] ) : array( 0, 0 );
}//load_waitingroom_info

?>

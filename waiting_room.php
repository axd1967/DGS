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

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/std_classes.php" );
require_once( "include/countries.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( 'include/game_functions.php' );
require_once( 'include/time_functions.php' );
require_once( "include/message_functions.php" );
require_once( "include/contacts.php" );
require_once( "include/filter.php" );
require_once( "include/filterlib_country.php" );
require_once( "include/classlib_profile.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/wroom_control.php' );

{
   // NOTE: using page: join_waitingroom_game.php

   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'waiting_room');
   $my_id = $player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_WAITINGROOM );

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

   $game_type_filter_array = MultiPlayerGame::build_game_type_filter_array();
   $game_type_filter_array[T_('Shape-Game#shape')] = "ShapeID>0";
   $game_type_filter_array[T_('No Shapes#shape')] = "ShapeID=0";

   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $idinfo = (int)@$_GET['info'];
   if( $idinfo < 0)
      $idinfo = 0;
   else
   {
      $gid = (int)get_request_arg('gid');
      if( $gid > 0 )
         $idinfo = load_waitingroom_info( $gid );
   }

   $page = "waiting_room.php?";
   if( $idinfo )
      $page.= 'info='.$idinfo . URI_AMP;

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_WAITINGROOM );
   $wrfilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'good' ); // named-filters FC_FNAME
   $wrtable = new Table( 'waitingroom', $page, $cfg_tblcols, '', TABLE_ROWS_NAVI );
   $wrtable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $wrfilter->add_filter( 1, 'Text',      'WRP.Name', true);
   $wrfilter->add_filter( 2, 'Text',      'WRP.Handle', true);
   $wrfilter->add_filter( 3, 'Rating',    'WRP.Rating2', true);
   $wrfilter->add_filter( 5, 'Selection', $handi_filter_array, true);
   $wrfilter->add_filter( 6, 'Numeric',   'Komi', true, array( FC_SIZE => 4 ));
   $wrfilter->add_filter( 7, 'Numeric',   'Size', true, array( FC_SIZE => 4 ));
   $wrfilter->add_filter( 8, 'Boolean',
         new QuerySQL( SQLP_HAVING, 'goodrating', 'goodmingames', 'haverating', 'goodsameopp' ),
         true,
         array( FC_FNAME => 'good', FC_LABEL => T_('Only suitable'), FC_STATIC => 1, FC_DEFAULT => 1 ));
   $wrfilter->add_filter( 9, 'Selection',
         array( T_('All') => '',
                T_('Japanese') => sprintf( "Byotype='%s'", BYOTYPE_JAPANESE ),
                T_('Canadian') => sprintf( "Byotype='%s'", BYOTYPE_CANADIAN ),
                T_('Fischer')  => sprintf( "Byotype='%s'", BYOTYPE_FISCHER ), ),
         true);
   $wrfilter->add_filter(11, 'RatedSelect', 'Rated', true);
   $wrfilter->add_filter(12, 'BoolSelect', 'Weekendclock', true);
   if( ENABLE_STDHANDICAP )
      $wrfilter->add_filter(13, 'BoolSelect', 'StdHandicap', true);
   $wrfilter->add_filter(15, 'Country', 'WRP.Country', false,
         array( FC_HIDE => 1 ));
   $wrfilter->add_filter(19, 'Selection', build_ruleset_filter_array(), true);
   $wrfilter->add_filter(20, 'Selection', $game_type_filter_array, true);
   $wrfilter->init();
   $f_range =& $wrfilter->get_filter(8);
   $suitable = $f_range->get_value(); // !suitable == all


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
   $wrtable->add_tablehead(18, T_('Settings#headerwr'), 'GameSettings', TABLE_NO_SORT );
   $wrtable->add_tablehead(14, T_('Handicap#headerwr'), 'Number', 0, 'Handicap+');
   $wrtable->add_tablehead( 6, T_('Komi#header'), 'Number', 0, 'Komi-');
   $wrtable->add_tablehead( 8, T_('Restrictions#header'), '', TABLE_NO_HIDE|TABLE_NO_SORT);
   $wrtable->add_tablehead( 9, T_('Time limit#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead(11, T_('Rated#header'), '', 0, 'Rated-');
   $wrtable->add_tablehead(10, T_('#Games#header'), 'Number', 0, 'nrGames-');
   $wrtable->add_tablehead(12, T_('Weekend Clock#header'), '', 0, 'WeekendClock-');
   // NOTE: User can choose:
   // View "merged" "Handicap + StdPlacement (in Settings column),
   // but has separate column on StdPlacement for filtering on it:
   if( ENABLE_STDHANDICAP )
      $wrtable->add_tablehead(13, T_('Standard placement#header'), '', 0, 'StdHandicap-');

   $wrtable->set_default_sort( 3, 2); //on WRP_Rating2,WRP_Handle
   $order = $wrtable->current_order_string('ID+');
   $limit = $wrtable->current_limit_string();

   $baseURLMenu = "waiting_room.php?"
      . $wrtable->current_rows_string(1)
      . $wrtable->current_sort_string(1); //end sep
   $baseURL = $baseURLMenu
      . $wrtable->current_filter_string(1)
      . $wrtable->current_from_string()
      . SPURI_ARGS . @$_REQUEST[SP_OVERWRITE_ARGS] . URI_AMP; //end sep

   $qsql = WaitingroomControl::build_waiting_room_query( 0, /*with-player*/true, $suitable );

   $qsql->merge( $wrtable->get_query() );
   $query = $qsql->get_select() . "$order$limit";
   $result = db_query( 'waiting_room.find_waiters', $query );

   $show_rows = $wrtable->compute_show_rows(mysql_num_rows($result));
   $wrtable->set_found_rows( mysql_found_rows('waiting_room.found_rows') );


   if( $suitable )
      $title = T_('Suitable waiting games');
   else
      $title = T_('All waiting games');

   start_page($title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);
   echo "<h3 class=Header>". $title . "</h3>\n";

   $maxGamesCheck = new MaxGamesCheck();
   if( !$idinfo )
      echo $maxGamesCheck->get_warn_text();

   $info_row = $info_joinable = NULL;
   if( $show_rows > 0 || $wrfilter->has_query() )
   {
      while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $WRP_Rating2 = NULL;
         $wro = new WaitingroomOffer( $row );
         $wro->calculate_offer_settings();
         $is_fairkomi = $wro->is_fairkomi();
         extract($row); //including $calculated, $haverating, $goodrating, $goodmingames, $goodmaxgames, $goodsameopp, $X_Time

         list( $restrictions, $joinable ) = WaitingroomControl::get_waitingroom_restrictions( $row, $suitable );

         if( $GameType != GAMETYPE_GO )
            $Handicaptype = HTYPEMP_MANUAL;
         if( $idinfo == (int)$ID )
         {
            $info_row = $row;
            $info_joinable = $joinable;
         }

         $wrow_strings = array();
         if( $wrtable->Is_Column_Displayed[17] )
            $wrow_strings[17] = button_TD_anchor( $baseURL."info=$ID#joingameForm", T_('Info'));
         if( $wrtable->Is_Column_Displayed[ 1] )
            $wrow_strings[ 1] = user_reference( REF_LINK, 1, '', $WRP_ID, $WRP_Name, '');
         if( $wrtable->Is_Column_Displayed[ 2] )
            $wrow_strings[ 2] = user_reference( REF_LINK, 1, '', $WRP_ID, $WRP_Handle, '');
         if( $wrtable->Is_Column_Displayed[15] )
            $wrow_strings[15] = getCountryFlagImage( @$row['WRP_Country'] );
         if( $wrtable->Is_Column_Displayed[ 3] )
            $wrow_strings[ 3] = echo_rating($WRP_Rating2, true, $WRP_ID);
         if( $wrtable->Is_Column_Displayed[ 4] )
            $wrow_strings[ 4] = make_html_safe($Comment, INFO_HTML);
         if( $wrtable->Is_Column_Displayed[ 5] ) // Handicaptype
         {
            $wrow_strings[ 5] = array('text' => $handi_array[$Handicaptype]);
            if( !$haverating )
               $wrow_strings[ 5]['attbs'] = warning_cell_attb( T_('No initial rating'), true);
         }
         if( $wrtable->Is_Column_Displayed[14] ) // Handicap
         {
            $h_str = ( $calculated )
               ? build_adjust_handicap( $AdjHandicap, $MinHandicap, $MaxHandicap )
               : $Handicap;
            $wrow_strings[14] = ( (string)$h_str != '' ) ? $h_str : NO_VALUE;
         }
         if( $wrtable->Is_Column_Displayed[ 6] ) // Komi
         {
            if( $calculated )
               $k_str = build_adjust_komi( $AdjKomi, $JigoMode, true );
            elseif( $is_fairkomi )
               $k_str = '';
            else
               $k_str = (float)$Komi;
            $wrow_strings[ 6] = ( (string)$k_str != '' ) ? $k_str : NO_VALUE;
         }
         if( $wrtable->Is_Column_Displayed[ 7] )
            $wrow_strings[ 7] = $Size;
         if( $wrtable->Is_Column_Displayed[ 8] )
         {
            $wrow_strings[ 8] = array( 'text' => $restrictions );
            if( !$joinable )
               $wrow_strings[ 8]['attbs']= warning_cell_attb( T_('Out of range'), true);
         }
         if( $wrtable->Is_Column_Displayed[ 9] )
            $wrow_strings[ 9] = TimeFormat::echo_time_limit(
                  $Maintime, $Byotype, $Byotime, $Byoperiods, TIMEFMT_SHORT|TIMEFMT_ADDTYPE);
         if( $wrtable->Is_Column_Displayed[10] )
            $wrow_strings[10] = ($wro->mp_player_count) ? 1 : $nrGames;
         if( $wrtable->Is_Column_Displayed[11] )
            $wrow_strings[11] = yesno( $Rated);
         if( $wrtable->Is_Column_Displayed[12] )
            $wrow_strings[12] = yesno( $WeekendClock);
         if( ENABLE_STDHANDICAP )
         {
            if( $wrtable->Is_Column_Displayed[13] )
               $wrow_strings[13] = yesno( $StdHandicap);
         }
         if( $wrtable->Is_Column_Displayed[16] )
            $wrow_strings[16] = build_usertype_text($WRP_Type, ARG_USERTYPE_NO_TEXT, true, '');
         if( $wrtable->Is_Column_Displayed[18] ) // Settings (resulting Color + Handi + Komi)
            $wrow_strings[18] = $wro->calculate_offer_settings();
         if( $wrtable->Is_Column_Displayed[19] )
            $wrow_strings[19] = getRulesetText($Ruleset);
         if( $wrtable->Is_Column_Displayed[20] )
         {
            $wrow_strings[20] =
               ( $ShapeID > 0 ? echo_image_shapeinfo($ShapeID, $Size, $ShapeSnapshot) . ' ' : '' ) .
               GameTexts::format_game_type($GameType, $GamePlayers) .
               ( $is_fairkomi ? GameTexts::build_fairkomi_gametype(GAME_STATUS_KOMI) : '' );
         }

         $wrtable->add_row( $wrow_strings );
      }//while

      // print table
      echo $wrtable->make_table();

      $show_info = ( $idinfo && is_array($info_row) );
      if( !$show_info )
      {
         $restrictions = array(
               T_('Handicap-type (conventional and proper handicap-type need a rating for calculations)#wroom'),
               T_('Rating range (user rating must be within the requested rating range), e.g. "25k-2d"#wroom'),
               T_('Number of rated finished games, e.g. "RG[2]"#wroom'),
               T_('Max. number of opponents started games must not exceed limits, e.g. "MXG"#wroom'),
               T_('Acceptance mode for challenges from same opponent, e.g. "SOT[1]" (total) or "SO[1x]" or "SO[&gt;7d]"#wroom'),
               sprintf( T_('Contact-option \'Hide waiting room games\', marked by "%s"'),
                        sprintf('[%s]', T_('Hidden#wroom')) ),
            );
         $notes = array();
         $notes[] = T_('Column \'Settings\' shows the probably game-color, handicap and komi.')
               . sprintf( '<br>%s = %s', T_('(Free Handicap)#handicap_tablewr'), T_('indicator of free handicap stone placement') );
         $notes[] = T_('A waiting game is <b>suitable</b> when a player matches the requested game restrictions on:')
               . "\n* " . implode(",\n* ", $restrictions);
         echo_notes( 'waitingroomnotes', T_('Waiting room notes'), $notes );
      }
   }
   else
      echo '<p></p>&nbsp;<p></p>' . T_('Seems to be empty at the moment.');
   mysql_free_result($result);


   if( @$show_info )
      add_old_game_form( 'joingame', $info_row, $iamrated, $info_joinable );


   $menu_array = array();
   $menu_array[T_('New Game')] = 'new_game.php';
   $menu_array[T_('Show all waiting games')] = $baseURLMenu.'good=0'.SPURI_ARGS.'good';
   $menu_array[T_('Show suitable games only')] = $baseURLMenu.'good=1'.SPURI_ARGS.'good';

   end_page(@$menu_array);
}


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
   foreach( $ARR_COPY_FIELDS as $src => $trg )
      $game_row[$trg]  = $game_row[$src];
   game_info_table( GSET_WAITINGROOM, $game_row, $player_row, $iamrated );

   $game_form->add_hidden( 'id', $game_row['ID'] ); // wroom-id
   if( $is_my_game )
   {
      $game_form->add_row( array(
            'HIDDEN', 'delete', 't',
            'SUBMITBUTTON', 'deletebut', T_('Delete'),
         ));
   }
   elseif( $can_join && $joinable )
   {
      $game_form->add_row( array(
            'SUBMITBUTTONX', 'join', T_('Join'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
         ));
   }

   $game_form->echo_string(1);
   echo $html_out;
}//add_old_game_form

// find waiting-room-id for given game-id
function load_waitingroom_info( $gid )
{
   $row = mysql_single_fetch( "waiting_room.load_wroom($gid)",
      "SELECT ID FROM Waitingroom WHERE gid=$gid LIMIT 1" );
   return ($row) ? $row['ID'] : 0;
}//load_wroom

?>

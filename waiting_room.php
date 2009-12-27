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

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_WAITINGROOM );

   //short descriptions for table
   $handi_array = array(
      HTYPE_CONV   => T_('Conventional'),
      HTYPE_PROPER => T_('Proper'),
      HTYPE_NIGIRI => T_('Nigiri'),
      HTYPE_DOUBLE => T_('Double game'),
      HTYPE_BLACK  => T_('Color Black'),
      HTYPE_WHITE  => T_('Color White'),
   );

   $ruleset_array = array(
      RULESET_TERRITORY => T_('Territory'),
      RULESET_AREA      => T_('Area'),
   );

   // config for handicap-filter
   $handi_filter_array = array(
      T_('All')            => '',
      T_('Conventional')   => "Handicaptype='conv'",
      T_('Proper')         => "Handicaptype='proper'",
      T_('Nigiri')         => "Handicaptype='nigiri'",
      T_('Double')         => "Handicaptype='double'",
      T_('Fix color')      => "Handicaptype IN ('double','black','white')",
      T_('Manual')         => "Handicaptype IN ('nigiri','double','black','white')",
   );

   $my_rating = $player_row['Rating2'];
   $my_rated_games = (int)$player_row['RatedGames'];
   $iamrated = ( $player_row['RatingStatus'] != RATING_NONE
         && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $idinfo = (int)@$_GET['info'];
   if( $idinfo < 0)
      $idinfo = 0;

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
   $wrfilter->add_filter( 1, 'Text',      'Players.Name', true);
   $wrfilter->add_filter( 2, 'Text',      'Players.Handle', true);
   $wrfilter->add_filter( 3, 'Rating',    'Players.Rating2', true);
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
   if( ENA_STDHANDICAP )
      $wrfilter->add_filter(13, 'BoolSelect', 'StdHandicap', true);
   $wrfilter->add_filter(15, 'Country', 'Players.Country', false,
         array( FC_HIDE => 1 ));
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
   $wrtable->add_tablehead(16, T_('UserType#headerwr'), 'User', 0, 'other_type+');
   $wrtable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'other_name+');
   $wrtable->add_tablehead( 2, T_('Userid#header'), 'User', 0, 'other_handle+');
   $wrtable->add_tablehead(15, T_('Country#header'), 'Image', 0, 'other_country+');
   $wrtable->add_tablehead( 3, T_('Rating#header'), 'Rating', 0, 'other_rating-');
   $wrtable->add_tablehead( 4, T_('Comment#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead(19, T_('Ruleset#header'), '', 0, 'Ruleset+');
   $wrtable->add_tablehead( 7, T_('Size#header'), 'Number', 0, 'Size-');
   $wrtable->add_tablehead( 5, T_('Type#headerwr'), '', 0, 'Handicaptype+');
   $wrtable->add_tablehead(18, T_('Settings#headerwr'), 'GameSettings', TABLE_NO_SORT );
   $wrtable->add_tablehead(14, T_('Handicap#headerwr'), 'Number', 0, 'Handicap+');
   $wrtable->add_tablehead( 6, T_('Komi#header'), 'Number', 0, 'Komi-');
   $wrtable->add_tablehead( 8, T_('Restrictions#header'), '', TABLE_NO_HIDE|TABLE_NO_SORT);
   $wrtable->add_tablehead( 9, T_('Time limit#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead(10, T_('#Games#header'), 'Number', 0, 'nrGames-');
   $wrtable->add_tablehead(11, T_('Rated#header'), '', 0, 'Rated-');
   $wrtable->add_tablehead(12, T_('Weekend Clock#header'), '', 0, 'WeekendClock-');
   // NOTE: User can choose:
   // View "merged" "Handicap + StdPlacement (in Settings column),
   // but has separate column on StdPlacement for filtering on it:
   if( ENA_STDHANDICAP )
      $wrtable->add_tablehead(13, T_('Standard placement#header'), '', 0, 'StdHandicap-');

   $wrtable->set_default_sort( 3, 2); //on other_rating,other_handle
   $order = $wrtable->current_order_string('ID+');
   $limit = $wrtable->current_limit_string();

   $baseURLMenu = "waiting_room.php?"
      . $wrtable->current_rows_string(1)
      . $wrtable->current_sort_string(1); //end sep
   $baseURL = $baseURLMenu
      . $wrtable->current_filter_string(1)
      . $wrtable->current_from_string(1); //end sep

   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'W.*',
      'Players.ID AS other_id',
      'Players.Handle AS other_handle',
      'Players.Name AS other_name',
      'Players.Type AS other_type',
      'Players.Country AS other_country',
      'Players.Rating2 AS other_rating',
      'Players.RatingStatus AS other_ratingstatus',
      'WRJ.JoinedCount',
      'UNIX_TIMESTAMP(WRJ.ExpireDate) AS X_ExpireDate' );

// $calculated = ( $Handicaptype == 'conv' || $Handicaptype == 'proper' );
// $haverating = ( !$calculated || is_numeric($my_rating) );
// if( $MustBeRated != 'Y' )         $goodrating = true;
// else if( is_numeric($my_rating) ) $goodrating = ( $my_rating>=$Ratingmin && $my_rating<=$Ratingmax );
// else                              $goodrating = false;
// $goodmingames = ( $MinRatedGames > 0 ? ($my_rated_games >= $MinRatedGames) : true );

   $calculated = "(W.Handicaptype='conv' OR W.Handicaptype='proper')";
   if( $iamrated )
   {
      $haverating = "1";
      $goodrating = "IF(W.MustBeRated='Y' AND"
                  . " ($my_rating<W.Ratingmin OR $my_rating>W.Ratingmax)"
                  . ",0,1)";
   }
   else
   {
      $haverating = "NOT $calculated";
      $goodrating = "IF(W.MustBeRated='Y',0,1)";
   }
   $sql_goodmingames = "IF(W.MinRatedGames>0,($my_rated_games >= W.MinRatedGames),1)";

   $qsql->add_part( SQLP_FIELDS,
      "$calculated AS calculated",
      "$haverating AS haverating",
      "$goodrating AS goodrating",
      "$sql_goodmingames AS goodmingames",
      'IF(W.SameOpponent=0 OR ISNULL(WRJ.wroom_id), 1, '
         . 'IF(W.SameOpponent<0, '
            . '(WRJ.JoinedCount < -W.SameOpponent), '
            . "(WRJ.ExpireDate <= FROM_UNIXTIME($NOW)) )) AS goodsameopp"
      );
   $qsql->add_part( SQLP_FROM,
      'Waitingroom AS W',
      'INNER JOIN Players ON W.uid=Players.ID',
      "LEFT JOIN WaitingroomJoined AS WRJ ON WRJ.opp_id=$my_id AND WRJ.wroom_id=W.ID" );

   // Contacts: make the protected waitingroom games invisible
   $qsql->add_part( SQLP_FIELDS,
      "IF(ISNULL(C.uid),0,C.SystemFlags & ".CSYSFLAG_WAITINGROOM.") AS C_denied" );
   $qsql->add_part( SQLP_FROM,
      "LEFT JOIN Contacts AS C ON C.uid=W.uid AND C.cid=$my_id" );
   $qsql->add_part( SQLP_WHERE,
      'W.nrGames>0' );
   $qsql->add_part( SQLP_HAVING,
      'C_denied=0' );

   // Contacts: hide unwanted user-offers
   $qsql->add_part( SQLP_FIELDS,
      "IF(ISNULL(CH.uid),0,CH.SystemFlags & ".CSYSFLAG_WR_HIDE_GAMES.") AS CH_hidden" );
   $qsql->add_part( SQLP_FROM,
      "LEFT JOIN Contacts AS CH ON CH.uid=$my_id AND CH.cid=W.uid" );
   if( $suitable )
      $qsql->add_part( SQLP_HAVING, 'CH_hidden=0' );

   $qsql->merge( $wrtable->get_query() );
   $query = $qsql->get_select() . "$order$limit";
   $result = db_query( 'waiting_room.find_waiters', $query );


   if( $suitable )
      $title = T_('Suitable waiting games');
   else
      $title = T_('All waiting games');

   start_page($title, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);
   echo "<h3 class=Header>". $title . "</h3>\n";

   $show_rows = $wrtable->compute_show_rows(mysql_num_rows($result));
   $wrtable->set_found_rows( mysql_found_rows('waiting_room.found_rows') );

   $info_row = NULL;
   if( $show_rows > 0 || $wrfilter->has_query() )
   {
      while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $other_rating = NULL;
         extract($row); //including $calculated, $haverating, $goodrating, $goodmingames, $goodsameopp
         $is_my_game = ( $uid == $player_row['ID'] );

         if( $idinfo == (int)$ID )
            $info_row = $row;

         // probable game-settings without adjustments
         $infoHandi = $Handicap;
         $infoKomi  = $Komi;
         $iamblack = '';
         if( $iamrated && !$is_my_game )
         {
            $other_is_rated = ( $other_ratingstatus != RATING_NONE
                  && is_numeric($other_rating) && $other_rating >= MIN_RATING );
            if( $other_is_rated )
            {
               if( $Handicaptype == HTYPE_CONV )
                  list( $infoHandi, $infoKomi, $iamblack ) =
                     suggest_conventional( @$player_row['Rating2'], $other_rating, $Size );
               elseif( $Handicaptype == HTYPE_PROPER )
                  list( $infoHandi, $infoKomi, $iamblack ) =
                     suggest_proper( @$player_row['Rating2'], $other_rating, $Size );
            }
         }

         $wrow_strings = array();
         if( $wrtable->Is_Column_Displayed[17] )
            $wrow_strings[17] = button_TD_anchor( $baseURL."info=$ID#joingameForm", T_('Info'));
         if( $wrtable->Is_Column_Displayed[ 1] )
            $wrow_strings[ 1] = user_reference( REF_LINK, 1, '', $other_id, $other_name, '');
         if( $wrtable->Is_Column_Displayed[ 2] )
            $wrow_strings[ 2] = user_reference( REF_LINK, 1, '', $other_id, $other_handle, '');
         if( $wrtable->Is_Column_Displayed[15] )
            $wrow_strings[15] = getCountryFlagImage( @$row['other_country'] );
         if( $wrtable->Is_Column_Displayed[ 3] )
            $wrow_strings[ 3] = echo_rating($other_rating,true,$other_id);
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
            $k_str = ( $calculated ) ? build_adjust_komi( $AdjKomi, $JigoMode, true ) : (float)$Komi;
            $wrow_strings[ 6] = ( (string)$k_str != '' ) ? $k_str : NO_VALUE;
         }
         if( $wrtable->Is_Column_Displayed[19] )
            $wrow_strings[ 19] = $ruleset_array[$Ruleset];
         if( $wrtable->Is_Column_Displayed[ 7] )
            $wrow_strings[ 7] = $Size;
         if( $wrtable->Is_Column_Displayed[ 8] )
         {
            $wrow_strings[ 8] = array( 'text' =>
               echo_game_restrictions($MustBeRated, $Ratingmin, $Ratingmax,
                  $MinRatedGames, $SameOpponent, (!$suitable && @$CH_hidden), true) );
            if( !$goodrating || !$goodmingames || !$goodsameopp || @$CH_hidden )
               $wrow_strings[ 8]['attbs']= warning_cell_attb( T_('Out of range'), true);
         }
         if( $wrtable->Is_Column_Displayed[ 9] )
            $wrow_strings[ 9] = TimeFormat::echo_time_limit(
                  $Maintime, $Byotype, $Byotime, $Byoperiods, TIMEFMT_SHORT|TIMEFMT_ADDTYPE);
         if( $wrtable->Is_Column_Displayed[10] )
            $wrow_strings[10] = $nrGames;
         if( $wrtable->Is_Column_Displayed[11] )
            $wrow_strings[11] = yesno( $Rated);
         if( $wrtable->Is_Column_Displayed[12] )
            $wrow_strings[12] = yesno( $WeekendClock);
         if( ENA_STDHANDICAP )
         {
            if( $wrtable->Is_Column_Displayed[13] )
               $wrow_strings[13] = yesno( $StdHandicap);
         }
         if( $wrtable->Is_Column_Displayed[16] )
            $wrow_strings[16] = build_usertype_text($other_type, ARG_USERTYPE_NO_TEXT, true, '');
         if( $wrtable->Is_Column_Displayed[18] ) // Settings (resulting Color + Handi + Komi)
         {
            if( !$is_my_game )
            {
               $colstr = determine_color( $Handicaptype, $is_my_game, $iamblack );
               $resultHandicap = adjust_handicap( $infoHandi, $AdjHandicap, $MinHandicap, $MaxHandicap );
               $resultKomi = adjust_komi( $infoKomi, $AdjKomi, $JigoMode );
               $settings_str = ($resultHandicap > 0)
                  ? sprintf( T_('%s H%s K%s#wrsettings'), $colstr, (int)$resultHandicap, $resultKomi )
                  : sprintf( T_('%s Even K%s#wrsettings'), $colstr, $resultKomi );
            }
            else
               $settings_str = '';
            if( ENA_STDHANDICAP && ($StdHandicap !== 'Y') )
               $settings_str .= ($settings_str ? ' ' : '') . T_('(Free Handicap)#handicap_tablewr');
            $wrow_strings[18] = $settings_str;
         }

         $wrtable->add_row( $wrow_strings );
      }

      // print table
      echo $wrtable->make_table();

      if( !( $idinfo && is_array($info_row) ))
      {
         $restrictions = array(
               T_('Handicap-type (conventional and proper handicap-type need a rating for calculations)#wroom'),
               T_('Rating range (user rating must be between the requested rating range), e.g. "25k-2d"#wroom'),
               T_('Number of rated finished games, e.g. "RG[2]"#wroom'),
               T_('Acceptance mode for challenges from same opponent, e.g. "SO[1x]" or "SO[&gt;7d]"#wroom'),
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


   if( $idinfo && is_array($info_row) )
      add_old_game_form( 'joingame', $info_row, $iamrated);


   $menu_array = array();
   $menu_array[T_('Add new game')] = 'new_game.php';
   $menu_array[T_('Show all waiting games')] = $baseURLMenu.'good=0';
   $menu_array[T_('Show suitable games only')] = $baseURLMenu.'good=1';

   end_page(@$menu_array);
}


function add_old_game_form( $form_id, $game_row, $iamrated)
{
   $game_form = new Form($form_id, 'join_waitingroom_game.php', FORM_POST, true);

   global $player_row;
   game_info_table( GSET_WAITINGROOM, $game_row, $player_row, $iamrated);

   $is_my_game = ($game_row['other_id'] == $player_row['ID']);

   $game_form->add_hidden( 'id', $game_row['ID']);
   if( $is_my_game )
   {
      $game_form->add_row( array(
            'HIDDEN', 'delete', 't',
            'SUBMITBUTTON', 'deletebut', T_('Delete'),
         ) );
   }
   else if( $game_row['haverating'] && $game_row['goodrating']
         && $game_row['goodmingames'] && $game_row['goodsameopp'] )
   {
      $game_form->add_row( array(
            'SUBMITBUTTONX', 'join', T_('Join'),
                        array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
         ) );
   }
   $game_form->echo_string(1);
}

function determine_color( $Handicaptype, $is_my_game, $iamblack )
{
   global $base_path;

   if( $Handicaptype == HTYPE_NIGIRI )
      $colstr = image( $base_path.'17/y.gif', T_('Nigiri#color'), T_('Nigiri (You randomly play Black or White)#color') );
   elseif( $Handicaptype == HTYPE_DOUBLE )
      $colstr = image( $base_path.'17/w_b.gif', T_('B+W#color'), T_('You play Black and White#color') );
   elseif( $Handicaptype == HTYPE_BLACK )
   {
      if( $is_my_game )
         $colstr = image( $base_path.'17/b.gif', T_('B#color'), T_('I play Black#color') );
      else
         $colstr = image( $base_path.'17/w.gif', T_('W#color'), T_('You play White#color') );
   }
   elseif( $Handicaptype == HTYPE_WHITE )
   {
      if( $is_my_game )
         $colstr = image( $base_path.'17/w.gif', T_('W#color'), T_('I play White#color') );
      else
         $colstr = image( $base_path.'17/b.gif', T_('B#color'), T_('You play Black#color') );
   }
   elseif( (string)$iamblack != '' ) // $iamrated && !$is_my_game && HTYPE_CONV/PROPER
   {
      if( $iamblack )
         $colstr = image( $base_path.'17/b.gif', T_('B#color'), T_('You probably play Black#color') );
      else
         $colstr = image( $base_path.'17/w.gif', T_('W#color'), T_('You probably play White#color') );
   }
   else // HTYPE_CONV/PROPER (unrated|my-game-offer) or otherwise calculated
      $colstr = '';

   if( $colstr && $Handicaptype != HTYPE_DOUBLE )
      $colstr = insert_width(5) . $colstr;
   return $colstr;
}

?>

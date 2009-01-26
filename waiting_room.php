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
require_once( "include/std_classes.php" );
require_once( "include/countries.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/contacts.php" );
require_once( "include/filter.php" );
require_once( "include/filterlib_country.php" );
require_once( "include/classlib_profile.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   //short descriptions for table
   $handi_array = array( 'conv'   => T_('Conventional'),
                         'proper' => T_('Proper'),
                         'nigiri' => T_('Even game'),
                         'double' => T_('Double game') );

   // config for handicap-filter
   $handi_filter_array = array(
         T_('All') => '',
         T_('Suitable') => new QuerySQL(SQLP_HAVING, 'haverating') );
   foreach( $handi_array as $fval => $fkey )
      $handi_filter_array[$fkey] = "Handicaptype='$fval'";

   $my_id = $player_row['ID'];
   $my_rating = $player_row['Rating2'];
   $iamrated = ( $player_row['RatingStatus'] && is_numeric($my_rating) && $my_rating >= MIN_RATING );

   $idinfo = (int)@$_GET['info'];
   if( $idinfo < 0)
      $idinfo = 0;

   $page = "waiting_room.php?";
   if( $idinfo )
      $page.= 'info='.$idinfo . URI_AMP;

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_WAITINGROOM );
   $wrfilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'handi|good' ); // named-filters FC_FNAME
   $wrtable = new Table( 'waitingroom', $page, "WaitingroomColumns" );
   $wrtable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $wrfilter->add_filter( 1, 'Text',      'Players.Name', true);
   $wrfilter->add_filter( 2, 'Text',      'Players.Handle', true);
   $wrfilter->add_filter( 3, 'Rating',    'Players.Rating2', true);
   $wrfilter->add_filter( 5, 'Selection', $handi_filter_array, true,
         array( FC_FNAME => 'handi', FC_STATIC => 1, FC_DEFAULT => 1 ) );
   $wrfilter->add_filter( 6, 'Numeric',   'Komi', true, array( FC_SIZE => 4 ));
   $wrfilter->add_filter( 7, 'Numeric',   'Size', true, array( FC_SIZE => 4 ));
   $wrfilter->add_filter( 8, 'Boolean',
         new QuerySQL( SQLP_HAVING, 'goodrating' ),
         true,
         array( FC_FNAME => 'good', FC_LABEL => T_('Only suitable'), FC_STATIC => 1, FC_DEFAULT => 1 ));
   $wrfilter->add_filter( 9, 'Selection',
         array( T_('All') => '',
                T_('Japanese') => "Byotype='JAP'",
                T_('Canadian') => "Byotype='CAN'",
                T_('Fischer')  => "Byotype='FIS'" ),
         true);
   $wrfilter->add_filter(11, 'RatedSelect', 'Rated', true);
   $wrfilter->add_filter(12, 'BoolSelect', 'Weekendclock', true);
   if( ENA_STDHANDICAP )
      $wrfilter->add_filter(13, 'BoolSelect', 'StdHandicap', true);
   $wrfilter->add_filter(15, 'Country', 'Players.Country', false,
         array( FC_HIDE => 1 ));
   $wrfilter->init();
   $f_handi =& $wrfilter->get_filter(5);
   $f_range =& $wrfilter->get_filter(8);


   // init table
   $wrtable->register_filter( $wrfilter );
   $wrtable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $wrtable->add_tablehead(33, T_('Info#header'), 'Button', TABLE_NO_HIDE|TABLE_NO_SORT);
   $wrtable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'other_name+');
   $wrtable->add_tablehead( 2, T_('Userid#header'), 'User', 0, 'other_handle+');
   $wrtable->add_tablehead(15, T_('Country#header'), 'Image', 0, 'other_country+');
   $wrtable->add_tablehead( 3, T_('Rating#header'), 'Rating', 0, 'other_rating-');
   $wrtable->add_tablehead( 4, T_('Comment#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead( 7, T_('Size#header'), 'Number', 0, 'Size-');
   /**
    * keep the Type#headerwr and Handicap#headerwr to allow the
    * translators to solve a local language ambiguity.
    **/
   $wrtable->add_tablehead( 5, T_('Type#headerwr'), '', TABLE_NO_HIDE, 'Handicaptype+');
   $wrtable->add_tablehead(14, T_('Handicap#headerwr'), 'Number', 0, 'Handicap+');
   /** TODO: the handicap stones info could be merged in the Komi column,
    * [ BUT better to keep them separate to be able to filter on them
    *   AND being more flexible for later handicap-enhancements !! ]
    * with the standard placement... something like: "%d H + %d K (S)"
    * where:
    *   H=Tr$['Handicap stones#short']
    *   K=Tr$['Komi#short']
    *   S=Tr$['Standard placement#short']
    **/
   $wrtable->add_tablehead( 6, T_('Komi#header'), 'Number', 0, 'Komi-');
   $wrtable->add_tablehead( 8, T_('Rating range#header'), '', TABLE_NO_HIDE, 'Ratingmin-Ratingmax-');
   $wrtable->add_tablehead( 9, T_('Time limit#header'), null, TABLE_NO_SORT );
   $wrtable->add_tablehead(10, T_('#Games#header'), 'Number', 0, 'nrGames+');
   $wrtable->add_tablehead(11, T_('Rated#header'), '', 0, 'Rated-');
   $wrtable->add_tablehead(12, T_('Weekend Clock#header'), '', 0, 'WeekendClock-');
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
      'Players.Country AS other_country',
      'Players.Rating2 AS other_rating',
      'Players.RatingStatus AS other_ratingstatus' );

// $calculated = ( $Handicaptype == 'conv' || $Handicaptype == 'proper' );
// $haverating = ( !$calculated || is_numeric($my_rating) );
// if( $MustBeRated != 'Y' )         $goodrating = true;
// else if( is_numeric($my_rating) ) $goodrating = ( $my_rating>=$Ratingmin && $my_rating<=$Ratingmax );
// else                              $goodrating = false;

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

   $qsql->add_part( SQLP_FIELDS,
      "$calculated AS calculated",
      "$haverating AS haverating",
      "$goodrating AS goodrating" );
   $qsql->add_part( SQLP_FROM,
      'Waitingroom AS W',
      'INNER JOIN Players ON W.uid=Players.ID' );

   // Contacts: make invisible the protected waitingroom games
   $tmp= CSYSFLAG_WAITINGROOM;
   $qsql->add_part( SQLP_FIELDS,
      "IF(ISNULL(C.uid),0,C.SystemFlags & $tmp) AS C_denied" );
   $qsql->add_part( SQLP_FROM,
      "LEFT JOIN Contacts AS C ON C.uid=W.uid AND C.cid=$my_id" );
   $qsql->add_part( SQLP_WHERE,
      'W.nrGames>0' );
   $qsql->add_part( SQLP_HAVING,
      'C_denied=0' );

   $qsql->merge( $wrtable->get_query() );
   $query = $qsql->get_select() . "$order$limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'waiting_room.find_waiters');


   $arr_suitable = array();
   if( $f_handi->get_value() == 1 )
      $arr_suitable[]= T_('Handicap-Type');
   if( $f_range->get_value() )
      $arr_suitable[]= T_('Rating range');
   if( count($arr_suitable) > 0 )
      $title = T_("Suitable waiting games") . ' (' . implode(', ', $arr_suitable) . ')';
   else
      $title = T_("All waiting games");

   start_page($title, true, $logged_in, $player_row,
               $wrtable->button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);
   echo "<h3 class=Header>". $title . "</h3>\n";


   $show_rows = $wrtable->compute_show_rows(mysql_num_rows($result));
   $info_row = NULL;
   if( $show_rows > 0 || $wrfilter->has_query() )
   {
      while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $other_rating = NULL;
         extract($row); //including $calculated, $haverating and $goodrating

         if( $idinfo == (int)$ID )
            $info_row = $row;

         $Comment = make_html_safe($Comment, INFO_HTML);

         $wrow_strings = array();
         if( $wrtable->Is_Column_Displayed[33] )
            $wrow_strings[33] = $wrtable->button_TD_anchor(
                                 $baseURL."info=$ID#joingameForm", T_('Info'));
         if( $wrtable->Is_Column_Displayed[ 1] )
            $wrow_strings[ 1] = user_reference( REF_LINK, 1, '', $other_id, $other_name, '');
         if( $wrtable->Is_Column_Displayed[ 2] )
            $wrow_strings[ 2] = user_reference( REF_LINK, 1, '', $other_id, $other_handle, '');
         if( $wrtable->Is_Column_Displayed[15] )
         {
            $cntr = @$row['other_country'];
            $cntrn = basic_safe(@$COUNTRIES[$cntr]);
            $cntrn = empty($cntr) ? '' :
               "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">";
            $wrow_strings[15] = $cntrn;
         }
         if( $wrtable->Is_Column_Displayed[ 3] )
            $wrow_strings[ 3] = echo_rating($other_rating,true,$other_id);
         if( $wrtable->Is_Column_Displayed[ 4] )
            $wrow_strings[ 4] = $Comment;
         if( $wrtable->Is_Column_Displayed[ 5] )
         {
            $wrow_strings[ 5] = array('text' => $handi_array[$Handicaptype]);
            if( !$haverating )
               $wrow_strings[ 5]['attbs']=
                  $wrtable->warning_cell_attb( T_('No initial rating'), true);
         }
         if( $wrtable->Is_Column_Displayed[14] )
            $wrow_strings[14] = ($calculated ? NO_VALUE : $Handicap);
         if( $wrtable->Is_Column_Displayed[ 6] )
            $wrow_strings[ 6] = ($calculated ? NO_VALUE : $Komi);
         if( $wrtable->Is_Column_Displayed[ 7] )
            $wrow_strings[ 7] = $Size;
         if( $wrtable->Is_Column_Displayed[ 8] )
         {
            $wrow_strings[ 8] = array('text' =>
               echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax) );
            if( !$goodrating )
               $wrow_strings[ 8]['attbs']=
                  $wrtable->warning_cell_attb( T_('Out of range'), true);
         }
         if( $wrtable->Is_Column_Displayed[ 9] )
            $wrow_strings[ 9] =
               echo_time_limit( $Maintime, $Byotype, $Byotime, $Byoperiods, 0, 1);
         if( $wrtable->Is_Column_Displayed[10] )
            $wrow_strings[10] = $nrGames;
         if( $wrtable->Is_Column_Displayed[11] )
            $wrow_strings[11] = yesno( $Rated);
         if( $wrtable->Is_Column_Displayed[12] )
            $wrow_strings[12] = yesno( $WeekendClock);
         if( ENA_STDHANDICAP )
            if( $wrtable->Is_Column_Displayed[13] )
               $wrow_strings[13] = yesno( $StdHandicap);

         $wrtable->add_row( $wrow_strings );
      }

      // print table
      echo $wrtable->make_table();
   }
   else
      echo '<p></p>&nbsp;<p></p>' . T_('Seems to be empty at the moment.');
   mysql_free_result($result);


   $form_id = 'addgame'; //==> ID='addgameForm'
   if( $idinfo && is_array($info_row) )
   {
      add_old_game_form( 'joingame', $info_row, $iamrated); //==> ID='joingameForm'

      $menu_array[T_('Add new game')] = clean_url($baseURL) . '#'.$form_id.'Form' ;
   }
   else
      add_new_game_form( $form_id, $iamrated); //==> ID='addgameForm'


   $menu_array[T_('Show all games')] =
      $baseURLMenu.'handi=0'.URI_AMP.'good=0';
   $menu_array[T_('Show all suitable games')] =
      $baseURLMenu.'handi=1'.URI_AMP.'good=1';

   end_page(@$menu_array);
}


function echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax)
{
   if( $MustBeRated != 'Y' )
      return NO_VALUE;

   // +/-50 reverse the inflation from add_to_waitingroom.php
   $r1 = echo_rating($Ratingmin+50,false);
   $r2 = echo_rating($Ratingmax-50,false);
   if( $r1 == $r2 )
      $Ratinglimit = sprintf( T_('%s only'), $r1);
   else
      $Ratinglimit = $r1 . ' - ' . $r2;
   return $Ratinglimit;
}


function add_new_game_form( $form_id, $iamrated)
{
   $addgame_form = new Form( $form_id, 'add_to_waitingroom.php', FORM_POST );

   $addgame_form->add_row( array( 'HEADER', T_('Add new game') ) );

   $vals = array();

   for($i=1; $i<=10; $i++)
      $vals["$i"] = "$i";

   $addgame_form->add_row( array( 'DESCRIPTION', T_('Number of games to add'),
                                  'SELECTBOX', 'nrGames', 1, $vals, '1', false ) );

   game_settings_form($addgame_form, 'waitingroom', $iamrated);

   $rating_array = array();

   $s = ' ' . T_('dan');
   for($i=9; $i>0; $i--)
      $rating_array["$i dan"] = $i . $s;

   $s = ' ' . T_('kyu');
   for($i=1; $i<=30; $i++) //30 = (2100-MIN_RATING)/100
      $rating_array["$i kyu"] = $i . $s;


   $addgame_form->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                                  'CHECKBOX', 'must_be_rated', 'Y', "", false,
                                  'TEXT', sptext(T_('If yes, rating between'),1),
                                  'SELECTBOX', 'rating1', 1, $rating_array, '30 kyu', false,
                                  'TEXT', sptext(T_('and')),
                                  'SELECTBOX', 'rating2', 1, $rating_array, '9 dan', false ) );


   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'DESCRIPTION', T_('Comment'),
                                  'TEXTINPUT', 'comment', 40, 40, "" ) );

   $addgame_form->add_row( array( 'SPACE' ) );
   $addgame_form->add_row( array( 'SUBMITBUTTON', 'add_game', T_('Add Game') ) );

   $addgame_form->echo_string(1);
}

function add_old_game_form( $form_id, $game_row, $iamrated)
{
   $game_form = new Form($form_id, 'join_waitingroom_game.php', FORM_POST, true);

   global $player_row;
   game_info_table( 'waitingroom', $game_row, $player_row, $iamrated);

   $mygame= $game_row['other_id'] == $player_row['ID'];

   $game_form->add_hidden( 'id', $game_row['ID']);
   if( $mygame )
   {
      $game_form->add_row( array(
            'HIDDEN', 'delete', 't',
            'SUBMITBUTTON', 'deletebut', T_('Delete'),
         ) );
   }
   else if( $game_row['haverating'] && $game_row['goodrating'] )
   {
      $game_form->add_row( array(
            'DESCRIPTION', T_('Reply'),
            'TEXTAREA', 'reply', 60, 8, '',
         ) );
      $game_form->add_row( array(
            'SUBMITBUTTONX', 'join', T_('Join'),
                        array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
         ) );
   }
   $game_form->echo_string(1);
}

?>

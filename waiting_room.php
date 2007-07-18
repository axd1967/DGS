<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Game";

require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/countries.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

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

   // table filters
   $wrfilter = new SearchFilter();
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
                T_('Fisher') => "Byotype='FIS'" ),
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


   $wrtable = new Table( 'waitingroom', $page, "WaitingroomColumns" );
   $wrtable->register_filter( $wrfilter );
   $wrtable->add_or_del_column();
   $wrtable->set_default_sort( 'other_rating', 1, 'other_handle', 0);

   // add_tablehead($nr, $descr, $sort=NULL, $desc_def=false, $undeletable=false, $attbs=NULL)
   $wrtable->add_tablehead( 0, T_('Info'), NULL, false, true, array( 'class' => 'Button') );
   $wrtable->add_tablehead( 1, T_('Name'), 'other_name', false);
   $wrtable->add_tablehead( 2, T_('Userid'), 'other_handle', false);
   $wrtable->add_tablehead(15, T_('Country'), 'other_country', false);
   $wrtable->add_tablehead( 3, T_('Rating'), 'other_rating', true);
   $wrtable->add_tablehead( 4, T_('Comment'));
   $wrtable->add_tablehead( 7, T_('Size'), 'Size', true);
   $wrtable->add_tablehead( 5, T_('Colors'), 'Handicaptype', false, true);
   /** TODO: the handicap stones info may be merged in the Komi column
    * with the standard placement... something like: "%d H + %d K (S)"
    * where:
    *   H=Tr$['Handicap stones#short']
    *   K=Tr$['Komi#short']
    *   S=Tr$['Standard placement#short']
    **/
   $wrtable->add_tablehead(14, T_('Handicap'), 'Handicap');
   $wrtable->add_tablehead( 6, T_('Komi'), 'Komi', true);
   $wrtable->add_tablehead( 8, T_('Rating range'), "Ratingmin".URI_ORDER_CHAR."Ratingmax", true, true);
   $wrtable->add_tablehead( 9, T_('Time limit'));
   $wrtable->add_tablehead(10, T_('#Games'), 'nrGames', true);
   $wrtable->add_tablehead(11, T_('Rated'), 'Rated', true);
   $wrtable->add_tablehead(12, T_('Weekend Clock'), 'WeekendClock', true);
   if( ENA_STDHANDICAP )
      $wrtable->add_tablehead(13, T_('Standard placement'), 'StdHandicap', true);

   $order = $wrtable->current_order_string();
   $limit = $wrtable->current_limit_string();

   $baseURLMenu = "waiting_room.php?"
      . $wrtable->current_rows_string(1)
      . $wrtable->current_sort_string();
   $baseURL = $baseURLMenu . URI_AMP
      . $wrtable->current_from_string();

   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'Waitingroom.*',
      'Players.ID AS other_id',
      'Players.Handle AS other_handle',
      'Players.Name AS other_name',
      'Players.Country AS other_country',
      'Players.Rating2 AS other_rating',
      'Players.RatingStatus AS other_ratingstatus' );

// $calculated = ( $Handicaptype == 'conv' or $Handicaptype == 'proper' );
// $haverating = ( !$calculated or is_numeric($my_rating) );
// if( $MustBeRated != 'Y' )         $goodrating = true;
// else if( is_numeric($my_rating) ) $goodrating = ( $my_rating>=$Ratingmin && $my_rating<=$Ratingmax );
// else                              $goodrating = false;

   $calculated = "(Handicaptype='conv' OR Handicaptype='proper')";
   if( $iamrated )
   {
      $haverating = "1";
      $goodrating = "IF(MustBeRated='Y' AND"
                  . " ($my_rating<Waitingroom.Ratingmin OR $my_rating>Waitingroom.Ratingmax)"
                  . ",0,1)";
   }
   else
   {
      $haverating = "NOT $calculated";
      $goodrating = "IF(MustBeRated='Y',0,1)";
   }

   $qsql->add_part( SQLP_FIELDS,
      "$calculated AS calculated",
      "$haverating AS haverating",
      "$goodrating AS goodrating" );
   $qsql->add_part( SQLP_FROM,
      'Waitingroom', 'Players' );

   $qsql->add_part( SQLP_WHERE, 'Players.ID=Waitingroom.uid' );
   $qsql->add_part( SQLP_ORDER, $order, 'ID' );
   $qsql->merge( $wrtable->get_query() );
   $query = $qsql->get_select() . " $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'waiting_room.find_waiters');


   $arr_suitable = array();
   if ( $f_handi->get_value() == 1 )
      array_push( $arr_suitable, T_('Handicap') );
   if ( $f_range->get_value() )
      array_push( $arr_suitable, T_('Rating range') );
   if ( count($arr_suitable) > 0 )
      $title = T_("Suitable waiting games") . ' (' . implode(', ', $arr_suitable) . ')';
   else
      $title = T_("All waiting games");

   start_page($title, true, $logged_in, $player_row,
               $wrtable->button_style($player_row['Button']) );

   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);
   echo "<h3 class=Header>". $title . "</h3>\n";


   $show_rows = $wrtable->compute_show_rows(mysql_num_rows($result));
   $info_row = NULL;
   if ( $show_rows > 0 or $wrfilter->has_query() )
   {
      while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
      {
         $other_rating = NULL;
         extract($row); //including $calculated, $haverating and $goodrating

         if( $idinfo == (int)$ID )
            $info_row = $row;

         $Comment = make_html_safe($Comment, INFO_HTML);

         $wrow_strings = array();
         if( $wrtable->Is_Column_Displayed[0] )
            $wrow_strings[0] = $wrtable->button_TD_anchor( $baseURL.URI_AMP."info=$ID#roomInfos", T_('Info'));
         if( $wrtable->Is_Column_Displayed[1] )
            $wrow_strings[1] = '<td>'.
               user_reference( REF_LINK, 1, 'black', $other_id, $other_name, '') . "</td>";
         if( $wrtable->Is_Column_Displayed[2] )
            $wrow_strings[2] = "<td>" .
               user_reference( REF_LINK, 1, 'black', $other_id, $other_handle, '') . "</td>";
         if( $wrtable->Is_Column_Displayed[15] )
         {
            $cntr = @$row['other_country'];
            $cntrn = T_(@$COUNTRIES[$cntr]);
            $cntrn = (empty($cntr) ? '' :
               "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");
            $wrow_strings[15] = "<td>" . $cntrn . "</td>";
         }
         if( $wrtable->Is_Column_Displayed[3] )
            $wrow_strings[3] = "<td>" . echo_rating($other_rating,true,$other_id) . "&nbsp;</td>";
         if( $wrtable->Is_Column_Displayed[4] )
            $wrow_strings[4] = "<td>" . $Comment . "</td>";
         if( $wrtable->Is_Column_Displayed[5] )
         {
            $wrow_strings[5] = '<td' .
               ( $haverating ? '' : $wrtable->warning_cell_attb(  T_('No initial rating') ) )
               . '>' . $handi_array[$Handicaptype] . "</td>";
         }
         if( $wrtable->Is_Column_Displayed[14] )
            $wrow_strings[14] = '<td>' . ($calculated ? '-' : $Handicap) . "</td>";
         if( $wrtable->Is_Column_Displayed[6] )
            $wrow_strings[6] = '<td>' . ($calculated ? '-' : $Komi) . "</td>";
         if( $wrtable->Is_Column_Displayed[7] )
            $wrow_strings[7] = "<td>$Size</td>";
         if( $wrtable->Is_Column_Displayed[8] )
         {
            $Ratinglimit= echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax);
            $wrow_strings[8] = '<td' .
               ( $goodrating ? '' : $wrtable->warning_cell_attb(  T_('Out of range') ) )
               . '>' . $Ratinglimit . "</td>";
         }
         if( $wrtable->Is_Column_Displayed[9] )
            $wrow_strings[9] = '<td>' .
               echo_time_limit( $Maintime, $Byotype, $Byotime, $Byoperiods, 0, 1) .
               "</td>";
         if( $wrtable->Is_Column_Displayed[10] )
            $wrow_strings[10] = "<td>$nrGames</td>";
         if( $wrtable->Is_Column_Displayed[11] )
            $wrow_strings[11] = "<td>".yesno( $Rated)."</td>";
         if( $wrtable->Is_Column_Displayed[12] )
            $wrow_strings[12] = "<td>".yesno( $WeekendClock)."</td>";
         if( ENA_STDHANDICAP )
            if( $wrtable->Is_Column_Displayed[13] )
               $wrow_strings[13] = "<td>".yesno( $StdHandicap)."</td>";

         $wrtable->add_row( $wrow_strings );
      }

      // print form with table
      echo $wrtable->make_table();
   }
   else
      echo '<p></p>&nbsp;<p></p>' . T_('Seems to be empty at the moment.');


   $form_id = 'addgame'; //==> ID='addgameForm'
   if( $idinfo and is_array($info_row) )
   {
      add_old_game_form( 'joingame', $info_row, $iamrated);

      $menu_array[T_('Add new game')] = $baseURL . '#'.$form_id.'Form' ;
   }
   else
      add_new_game_form( $form_id, $iamrated); //==> ID='addgameForm'


   $menu_array[T_('Show all games')] =
      $baseURLMenu.URI_AMP.'handi=0'.URI_AMP.'good=0';
   $menu_array[T_('Show all suitable games')] =
      $baseURLMenu.URI_AMP.'handi=1'.URI_AMP.'good=1';

   end_page(@$menu_array);
}


function echo_rating_limit($MustBeRated, $Ratingmin, $Ratingmax)
{
   if( $MustBeRated != 'Y' )
      return '-';

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
   for($i=1; $i<=30; $i++)
      $rating_array["$i kyu"] = $i . $s;


   $addgame_form->add_row( array( 'DESCRIPTION', T_('Require rated opponent'),
                                  'CHECKBOX', 'must_be_rated', 'Y', "", false,
                                  'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('If yes, rating between'),
                                  'SELECTBOX', 'rating1', 1, $rating_array, '30 kyu', false,
                                  'TEXT', T_('and'),
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
   $game_form->set_tabindex(1);

   global $player_row;
   game_info_table( 'waitingroom', $game_row, $player_row, $iamrated);

   $mygame= $game_row['other_id'] == $player_row['ID'];

   $game_form->add_hidden( 'id', $game_row['ID']);
   if( $mygame )
   {
      $game_form->add_hidden( 'delete', 't');
      echo $game_form->print_insert_submit_button(
               'deletebut', T_('Delete'));
   }
   else if( $game_row['haverating'] && $game_row['goodrating'] )
   {
      echo T_('Reply');
      echo $game_form->print_insert_textarea( 'reply', 50, 4, '');
      echo $game_form->print_insert_submit_buttonx(
               'join', T_('Join'), array('accesskey'=>'x'));
   }
   echo $game_form->print_end();
}

?>

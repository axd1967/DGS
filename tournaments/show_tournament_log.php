<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/**
 * PURPOSE:
 * Show tournament-log for actions on tournaments to admins,
 * - needs ADMIN_DEVELOPER or ADMIN_TOURNAMENT rights to see all entries,
 * - needs to be tournament-owner or tournament-director to see entries of THEIR tournament
 */

$TranslateGroups[] = "Tournament";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/table_columns.php';
require_once 'include/filter.php';
require_once 'include/rating.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log.php';


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row );
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.show_tlog');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.show_tlog');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.show_tlog');

   $tid = (int)@$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   // check view-rights
   $is_admin = ( @$player_row['admin_level'] & (ADMIN_DEVELOPER|ADMIN_TOURNAMENT) );
   $tourney = null;
   $allow_view = false;
   if ( $tid > 0 )
   {
      $tourney = TournamentCache::load_cache_tournament( 'Tournament.show_tlog', $tid, /*check*/false );
      if ( !is_null($tourney) ) // deleted tournament perhaps (so no error)
         $allow_view = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   }
   if ( !($is_admin || $allow_view) )
      error('adminlevel_too_low', 'Tournament.show_tlog');


   // init
   $page = 'show_tournament_log.php';

   $types_arr = array(
         T_('All') => '',
         'CR' => "TLOG.Type='".TLOG_TYPE_CRON."'",
         'TA' => "TLOG.Type='".TLOG_TYPE_ADMIN."'",
         'TO' => "TLOG.Type='".TLOG_TYPE_OWNER."'",
         'TD' => "TLOG.Type='".TLOG_TYPE_DIRECTOR."'",
         'U'  => "TLOG.Type='".TLOG_TYPE_USER."'",
      );

   // table filters
   $tlogfilter = new SearchFilter();
   $tlogfilter->add_filter( 1, 'Numeric', 'TLOG.ID', true);
   $tlogfilter->add_filter( 4, 'RelativeDate', 'TLOG.Date', true,
      array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8 ));
   $tlogfilter->add_filter( 5, 'Selection', $types_arr, true );
   $tlogfilter->add_filter( 9, 'Text', 'TLOG.Message', true,
      array( FC_SIZE => 50, FC_SUBSTRING => 1, FC_START_WILD => 2 ));
   $tlogfilter->init(); // parse current value from _GET
   $filter_msg =& $tlogfilter->get_filter(9);
   $rx_terms_msg = implode('|', $filter_msg->get_rx_terms() );

   $table = new Table( 'tournamentlog', $page );
   $table->register_filter( $tlogfilter );
   $table->add_or_del_column();

   // attach external URL-parameters to table
   $extparam = new RequestParameters();
   if ( $tid > 0 )
      $extparam->add_entry( 'tid', $tid );
   $table->add_external_parameters( $extparam, true ); // also for hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('ID#header'), 'ID', TABLE_NO_HIDE, 'ID-');
   $table->add_tablehead( 3, T_('Userid#header'), 'User', TABLE_NO_HIDE, 'uid+');
   $table->add_tablehead( 2, T_('tid#header'), 'Number', TABLE_NO_HIDE|TABLE_NO_SORT, 'tid+');
   $table->add_tablehead( 5, T_('Type#header'), 'Center', TABLE_NO_HIDE|TABLE_NO_SORT, 'Type+');
   $table->add_tablehead( 6, T_('Object#header'), 'Enum', TABLE_NO_HIDE|TABLE_NO_SORT, 'Object+');
   $table->add_tablehead( 7, T_('Action#header'), 'Action', TABLE_NO_HIDE|TABLE_NO_SORT, 'Action+');
   $table->add_tablehead( 9, T_('Message#header'), 'Text');
   $table->add_tablehead( 4, T_('Action Date#header'), 'Date', TABLE_NO_SORT, 'Date-');
   $table->add_tablehead( 8, T_('Action User#header'), 'User', TABLE_NO_SORT, 'actuid+');

   $table->set_default_sort( 1); // on ID

   // build SQL-query (for Tournamentlog-table)
   $qsql = $table->get_query(); // clause-parts for filter
   $qsql->merge( new QuerySQL(
      SQLP_FIELDS,
         'P.Handle AS P_Handle', 'P.Rating2 AS P_Rating',
         'AP.Handle AS AP_Handle', 'AP.Rating2 AS AP_Rating',
      SQLP_FROM,
         'LEFT JOIN Players AS P ON P.ID=TLOG.uid', // left-join for cron-user with uid 0
         'LEFT JOIN Players AS AP ON AP.ID=TLOG.actuid' ));
   if ( $tid > 0 )
      $qsql->add_part( SQLP_WHERE, "TLOG.tid=$tid" );

   $iterator = new ListIterator( 'Tournamentlog',
         $qsql,
         $table->current_order_string('ID-'),
         $table->current_limit_string() );
   $iterator = Tournamentlog::load_tournament_logs( $iterator );

   $show_rows = $table->compute_show_rows( $iterator->getResultRows() );
   //$table->set_found_rows( mysql_found_rows('Tournament.show_tlog.found_rows') );

   while ( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $tlog, $orow ) = $arr_item;
      $row_str = array();

      if ( $table->Is_Column_Displayed[1] )
         $row_str[1] = $tlog->ID;
      if ( $table->Is_Column_Displayed[2] )
         $row_str[2] = anchor($base_path."tournaments/view_tournament.php?tid=".$tlog->tid, $tlog->tid);
      if ( $table->Is_Column_Displayed[3] )
      {
         if ( $tlog->uid > 0 )
            $row_str[3] = user_reference( REF_LINK, 1, '', $tlog->uid, $orow['P_Handle'], '' ) . ', ' .
               echo_rating($orow['P_Rating'], /*show%*/false, $tlog->uid, /*engl*/false, /*short*/true);
         else
            $row_str[3] = 'CRON';
      }
      if ( $table->Is_Column_Displayed[4] )
         $row_str[4] = ( $tlog->Date > 0 ) ? date(DATE_FMT3, $tlog->Date) : '';
      if ( $table->Is_Column_Displayed[5] )
         $row_str[5] = $tlog->Type;
      if ( $table->Is_Column_Displayed[6] )
         $row_str[6] = $tlog->Object;
      if ( $table->Is_Column_Displayed[7] )
         $row_str[7] = $tlog->Action;
      if ( $table->Is_Column_Displayed[8] )
      {
         $row_str[8] = ( $tlog->actuid > 0 )
            ? user_reference( REF_LINK, 1, '', $tlog->actuid, $orow['AP_Handle'], '' ) . ', ' .
                  echo_rating($orow['AP_Rating'], /*show%*/false, $tlog->actuid, /*engl*/false, /*short*/true)
            : NO_VALUE;
      }
      if ( $table->Is_Column_Displayed[9] )
         $row_str[9] = format_tlog_message( $tlog, $rx_terms_msg );

      $table->add_row( $row_str );
   }


   start_page(T_('Show Tournament Log'), true, $logged_in, $player_row);
   section( 'tournamentlog', T_('Tournament Log') );

   if ( !is_null($tourney) )
      echo $tourney->build_info(2), "<br><br>\n";

   $table->echo_table();

   $notes = array();
   $notes[] = array( 'Types:',
      '<b>CR</b> = CRON, <b>TA</b> = tournament admin, <b>TO</b> = tournament owner, <b>TD</b> = tournament director, <b>U</b> = user',
      );
   $notes[] = array( 'Object types:',
      '<b>T</b> = tournament, <b>TD</b> = tournament director, <b>TG</b> = tournament game',
      '<b>TL</b> = tournament ladder, <b>TLP</b> = tournament ladder properties',
      '<b>TN</b> = tournament news, <b>TP</b> = tournament participant, <b>TPOOL</b> = tournament pool',
      '<b>TPR</b> = tournament registration properties, <b>TRES</b> = tournament result',
      '<b>TRR</b> = tournament round-robin, <b>TRND</b> = tournament round, <b>TRULE</b> = tournament rule',
      );
   $notes[] = array( 'Object subtypes:',
      'Data, Game, Lock, News, NextRound, Pool, Props (=Properties), Rank, Reg (=Registration), Round, Status, User',
      );
   $notes[] = array( 'Actions:',
      'Add, Change, Clear, Create, Remove, Seed, Set, Start',
      );
   echo_notes( 'tournamentlog', T_('Tournament Log notes'), $notes );

   $menu = array();
   if ( $tid )
   {
      $menu[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
      $menu[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu);
}//main


function format_tlog_message( $tlog, $rx_terms )
{
   global $base_path;
   $msg = make_html_safe( $tlog->Message, false, $rx_terms );
   $msg = wordwrap( str_replace( "\n", "<br>\n", $msg ), 80, "<br>\n", true );

   $msg = preg_replace("/TG#(\\d+)/", anchor($base_path."tournaments/game_admin.php?tid={$tlog->tid}".URI_AMP.'gid=$1', 'TGame #$1'), $msg );
   $msg = preg_replace("/GID#(\\d+)/", anchor($base_path."game.php?gid=\$1", 'Game #$1'), $msg );
   $msg = preg_replace("/UID#(\\d+)/", anchor($base_path."userinfo.php?uid=\$1", 'User #$1'), $msg );

   return $msg;
}//format_tlog_message

?>

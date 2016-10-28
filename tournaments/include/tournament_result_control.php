<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Tournament";

require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/countries.php';
require_once 'include/filter.php';
require_once 'include/filterlib_country.php';
require_once 'include/rating.php';
require_once 'include/table_columns.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_result.php';


 /*!
  * \class TournamentResultControl
  *
  * \brief Controller-Class to handle tournament-results-list.
  */
class TournamentResultControl
{
   private $show_all; // full-mode for T-result-page or part-mode for view-T-page
   private $page;
   private $tourney;
   private $allow_edit_tourney;
   private $limit;

   private $my_id;
   private $table = null;
   private $iterator = null;
   private $show_rows = 0;
   private $total_found_rows = 0;

   public function __construct( $show_all, $page, $tourney, $allow_edit_tourney, $limit )
   {
      if ( $limit == 0 )
         error('invalid_args', "TournamentResultControl.construct.check.limit($show_all,$page,".(@$tourney->ID).",$limit)");
      global $player_row;
      $this->show_all = (bool)$show_all;
      $this->page = $page;
      $this->tourney = $tourney;
      $this->allow_edit_tourney = $allow_edit_tourney;
      $this->limit = (int)$limit;
      $this->my_id = (int)$player_row['ID'];
   }

   public function get_show_rows()
   {
      return $this->show_rows;
   }

   /*! \brief Creates Table, Filters and execute Query for tournament-results. */
   public function build_tournament_result_table( $dbgmsg )
   {
      $tid = $this->tourney->ID;

      // create table
      $cfg_tblcols = ConfigTableColumns::load_config( $this->my_id, CFGCOLS_TOURNAMENT_RESULTS );
      if ( !$cfg_tblcols )
         error('user_init_error', "$dbgmsg.TournamentResultControl.build_tournament_result_table.config_table_cols($tid)");

      if ( $this->show_all )
      {
         // table filters
         $trfilter = new SearchFilter();
         $trfilter->add_filter( 1, 'Text', 'TRP.Name', true);
         $trfilter->add_filter( 2, 'Text', 'TRP.Handle', true,
               array( FC_FNAME => 'user' ));
         $trfilter->add_filter( 3, 'Country', 'TRP.Country', false,
               array( FC_HIDE => 1 ));
         $trfilter->add_filter( 6, 'Numeric', 'TRS.Rank', true,
               array( FC_SIZE => 4 ));
         $trfilter->add_filter( 9, 'Numeric', 'TRS.Result', true,
               array( FC_SIZE => 4 ));
         $trfilter->init();
      }

      // init table
      $trtable = new Table( 'tournament_results', $this->page, $cfg_tblcols, '',
         TABLE_ROWS_NAVI | ( $this->show_all ? 0 : TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE ) );
      if ( $this->show_all && @$trfilter )
         $trtable->register_filter( $trfilter );
      if ( !$this->show_all )
         $trtable->use_show_rows(false);
      $trtable->add_or_del_column();

      // page vars
      $page_vars = new RequestParameters();
      $page_vars->add_entry( 'tid', $tid );
      $trtable->add_external_parameters( $page_vars, true ); // add as hiddens

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      if ( $this->allow_edit_tourney )
         $trtable->add_tablehead(11, T_('Actions#header'), 'Image', TABLE_NO_HIDE, '');
      $trtable->add_tablehead(12, T_('Type#header'), '', 0, 'Type+');
      if ( $this->tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         $trtable->add_tablehead(13, T_('Round#tresheader'), 'NumberC', 0, 'Round-');
      if ( $this->tourney->Type == TOURNEY_TYPE_LADDER )
         $trtable->add_tablehead( 9, T_('Result#tresult'), 'Number', TABLE_NO_HIDE, 'Result-');
      $trtable->add_tablehead( 6, T_('Rank#tourney_result'), 'Number', TABLE_NO_HIDE, 'Rank+');
      $trtable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'TRP_Name+');
      $trtable->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE, 'TRP_Handle+');
      $trtable->add_tablehead( 3, T_('Country#header'), 'Image', 0, 'TRP_Count+');
      $trtable->add_tablehead( 4, T_('Current Rating#header'), 'Rating', 0, 'TRP_Rating2-');
      $trtable->add_tablehead( 5, T_('Result Rating#header'), 'Rating', 0, 'Rating-');
      if ( $this->tourney->Type == TOURNEY_TYPE_LADDER )
         $trtable->add_tablehead( 7, T_('Result Span#header'), '', TABLE_NO_SORT, ''); // calculated
      $trtable->add_tablehead( 8, T_('Result Date#header'), '', 0, 'EndTime+');
      $trtable->add_tablehead(10, T_('Comment#header'), '', 0, 'Comment+');

      if ( $this->tourney->Type == TOURNEY_TYPE_LADDER )
         $trtable->set_default_sort( 9, 6 ); // [Type ASC,] Result DESC, Rank DESC [, EndTime ASC]
      elseif ( $this->tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         $trtable->set_default_sort( 6, 8 ); // [Type ASC, Round DESC,] Rank DESC, EndTime ASC

      // load tournament-results
      $iterator = new ListIterator( "$dbgmsg.TRC.build_tournament_result_table.find_tresults",
            $trtable->get_query(),
            $trtable->current_order_string(),
            ( $this->limit < 0 ? $trtable->current_limit_string() : "LIMIT {$this->limit}" ) );
      if ( $this->show_all || $this->limit < 0 )
      {
         $iterator = TournamentResult::load_tournament_results( $iterator, $tid, /*player-info*/true );
         $this->total_found_rows = mysql_found_rows("$dbgmsg.TRC.build_tournament_result_table.found_rows");
      }
      else
         list( $iterator, $this->total_found_rows ) = TournamentCache::load_cache_tournament_results(
            "$dbgmsg.TRC.build_tournament_result_table", $tid, $iterator, /*player-info*/true );

      $this->show_rows = $trtable->compute_show_rows( $iterator->getResultRows() );
      $trtable->set_found_rows( $this->show_rows );

      $this->iterator = $iterator;
      $this->table = $trtable;
   }//build_tournament_result_table

   /*! \brief Fills tournament-result-Table with data and returns table-string. */
   public function make_table_tournament_results( $show_footer=false )
   {
      global $base_path;
      $tid = $this->tourney->ID;
      $show_rows = $this->show_rows;
      $total_found_rows = $this->total_found_rows;

      while ( ($show_rows-- > 0) && list(,$arr_item) = $this->iterator->getListIterator() )
      {
         list( $tresult, $orow ) = $arr_item;
         $uid = $tresult->uid;
         $user = User::new_from_row($orow, 'TRP_');
         $is_mine = ( $this->my_id == $uid );

         $row_str = array();

         if ( $this->allow_edit_tourney && @$this->table->Is_Column_Displayed[11] )
         {
            $links = array();
            $links[] = anchor( $base_path."tournaments/edit_results.php?tid=$tid".URI_AMP."trid={$tresult->ID}",
                  image( $base_path.'images/edit.gif', 'E', '', 'class="Action"' ), T_('Edit tournament result#tourney'));
            $links[] = anchor( $base_path."tournaments/edit_results.php?tid=$tid".URI_AMP."trid={$tresult->ID}".URI_AMP."tr_del=1",
                  image( $base_path.'images/trashcan.gif', 'E', '', 'class="Action"' ), T_('Delete tournament result#tourney'));
            $row_str[11] = implode(' ', $links);
         }

         if ( $this->table->Is_Column_Displayed[ 1] )
            $row_str[ 1] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
         if ( $this->table->Is_Column_Displayed[ 2] )
            $row_str[ 2] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
         if ( $this->table->Is_Column_Displayed[ 3] )
            $row_str[ 3] = getCountryFlagImage( $user->Country );
         if ( $this->table->Is_Column_Displayed[ 4] )
            $row_str[ 4] = echo_rating( $user->Rating, true, $uid);
         if ( $this->table->Is_Column_Displayed[ 5] )
            $row_str[ 5] = echo_rating( $tresult->Rating, true, 0);
         if ( $this->table->Is_Column_Displayed[ 6] )
            $row_str[ 6] = $tresult->Rank . '.';
         if ( @$this->table->Is_Column_Displayed[ 7] )
            $row_str[ 7] = TimeFormat::echo_time_diff( $tresult->EndTime, $tresult->StartTime, 24, TIMEFMT_SHORT, '' );
         if ( $this->table->Is_Column_Displayed[ 8] )
            $row_str[ 8] = ($tresult->EndTime > 0) ? date(DATE_FMT, $tresult->EndTime) : '';
         if ( @$this->table->Is_Column_Displayed[ 9] )
         {
            if ( $tresult->Type == TRESULTTYPE_TL_SEQWINS )
               $row_str[ 9] = $tresult->Result;
            else
               $row_str[ 9] = NO_VALUE;
         }
         if ( $this->table->Is_Column_Displayed[10] )
            $row_str[10] = $tresult->Comment;
         if ( $this->table->Is_Column_Displayed[12] )
            $row_str[12] = TournamentResult::getTypeText($tresult->Type);
         if ( @$this->table->Is_Column_Displayed[13] )
            $row_str[13] = $tresult->Round;

         if ( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $this->table->add_row( $row_str );
      }

      if ( $show_footer && $total_found_rows > 0 && $total_found_rows > $this->show_rows )
      {
         $this->table->add_row_one_col(
            sprintf(T_('There are %s more for a total of %s entries ...'),
               $total_found_rows - $this->show_rows, $total_found_rows ),
            array( 'extra_class' => 'Footer' ) );
      }

      return $this->table->make_table();
   }//make_table_tournament_results


   // ------------ static functions ----------------------------

   /* \brief Creates TournamentResult-entries from round-robins pool-winners for given round. */
   public static function create_tournament_result_pool_winners( $tid, $round, $tlog_type )
   {
      $tround = TournamentCache::load_cache_tournament_round( 'TRC.create_tresult_poolwinners', $tid, $round );

      $iterator = new ListIterator( "TRC:create_tresult_pool_winners.TournamentPool",
         new QuerySQL( SQLP_WHERE, 'TPOOL.Rank > 0' ), // find pool-winners
         'ORDER BY Rank ASC, Pool ASC' ); // order by highest rank first
      $iterator = TournamentPool::load_tournament_pools( $iterator, $tid, $round, 0, 0,
         TPOOL_LOADOPT_USER | TPOOL_LOADOPT_TP_ID | TPOOL_LOADOPT_TRATING | TPOOL_LOADOPT_ONLY_RATING );

      $cnt = 0;
      ta_begin();
      {//HOT-section to create tournament-results
         $arr_tresult = array();
         while ( list(,$arr_item) = $iterator->getListIterator() )
         {
            list( $tpool, $orow ) = $arr_item;
            $tresult = self::build_tournament_result_pool_winner( $tround, $tpool );
            if ( $tresult->persist() )
               $arr_tresult[] = $tresult;
         }

         $cnt = count($arr_tresult);
         if ( $cnt > 0 )
            TournamentLogHelper::log_create_tournament_result_pool_winners( $tid, $tlog_type, $round,
               $iterator->getItemCount(), $cnt, $arr_tresult );
      }
      ta_end();

      return $cnt;
   }//create_tournament_result_pool_winners

   /*!
    * \brief Builds TournamentResult-object from round-robins pool-winners for given round.
    * \param $tpool TournamentPool with set User-object and urow with TP_ID/TP_Rating set
    * \return null if no valid rating available or Pool-user is not a pool-winner (Rank>0); otherwise TournamentResult.
    * \note keep in sync with fill_tournament_info()-function in 'edit_results.php'
    */
   private static function build_tournament_result_pool_winner( $tround, $tpool )
   {
      global $NOW, $player_row;

      $rating = $tpool->User->urow['TP_Rating'];
      if ( !is_valid_rating($rating) )
         $rating = $tpool->User->Rating;
      if ( $tpool->Rank <= 0 || !is_valid_rating($rating) )
         return null;

      $tresult = new TournamentResult( 0, $tround->tid, $tpool->uid, $tpool->User->urow['TP_ID'], $rating,
         TRESULTTYPE_TRR_POOL_WINNER, $tround->Round, $tround->Lastchanged, $NOW, 0, $tpool->Rank,
         'Pool Winner', sprintf( 'auto-filled by [%s]', $player_row['Handle'] ) ); // no translation
      return $tresult;
   }//build_tournament_result_pool_winner


   /*
    * \brief Creates TournamentResult-entries for max-consecutive-wins from ladder-tournament.
    * \param $tid should be 0 if $tgame specified and must be given if $tgame is null
    * \param $tgame TournamentGame-object = process tournament-game for game-end-processing;
    *       null = process all tournament-ladder-entries for fix-script.
    * \return added/updated tournament-result-entries
    */
   public static function create_tournament_result_best_seq_wins( $tid, $tgame, $seq_wins_threshold )
   {
      if ( !is_null($tgame) )
         $tid = $tgame->tid;
      if ( $seq_wins_threshold <= 0 || $tid <= 0 )
         return;

      // find ladder-entries for updated users with crossed seq-wins-threshold
      $qsql = new QuerySQL(
            SQLP_FIELDS, 'P.Rating2',
            SQLP_FROM,   'Players AS P ON P.ID=TL.uid',
            SQLP_WHERE,  "TL.SeqWinsBest >= $seq_wins_threshold" );
      if ( !is_null($tgame) )
      {
         $qsql->add_part( SQLP_WHERE, "TL.rid IN ({$tgame->Challenger_rid},{$tgame->Defender_rid})" );
         $qsql->add_part( SQLP_LIMIT, '2' );
      }
      $iterator = new ListIterator( 'TRC:create_tresult_best_seq_wins.TL', $qsql );
      $iterator = TournamentLadder::load_tournament_ladder( $iterator, $tid );

      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;

         // check for existing T-result; should be 0 or only 1 entry, if more then something wrong, so then take 1st
         $arr_tresults = TournamentResult::load_tournament_results_by_rid( "TLH:create_tresult_best_seq_wins($tid)",
            $tladder->rid, TRESULTTYPE_TL_SEQWINS );
         $tresult = ( count($arr_tresults) > 0 ) ? $arr_tresults[0] : null;

         if ( is_null($tresult) ) // add T-result
            $tresult = self::build_tournament_result_seq_wins( $tladder, $orow['Rating2'] );
         else if ( $tladder->SeqWinsBest > $tresult->Result ) // update T-result if BestSeqWins increased
            $tresult = self::build_tournament_result_seq_wins( $tladder, $orow['Rating2'], $tresult );
         $tresult->persist();
      }

      return $iterator->getItemCount();
   }//create_tournament_result_best_seq_wins

   /*!
    * \brief Builds TournamentResult-object from tournament-ladder.
    * \param $tresult null = create new TournamentResult-object; otherwise expects object to be modified for update
    */
   private static function build_tournament_result_seq_wins( $tladder, $user_rating, $tresult=null )
   {
      global $NOW;

      if ( is_null($tresult) ) // new T-result
      {
         $tresult = new TournamentResult( 0, $tladder->tid, $tladder->uid, $tladder->rid, $user_rating,
            TRESULTTYPE_TL_SEQWINS, /*round*/1, /*start*/$tladder->Created, /*end*/$NOW,
            /*result*/$tladder->SeqWinsBest, $tladder->Rank, 'Consecutive Wins', 'set by CRON' );
      }
      else // update T-result
      {
         $tresult->Rating = $user_rating;
         $tresult->EndTime = $NOW;
         $tresult->Result = $tladder->SeqWinsBest;
         $tresult->Rank = $tladder->Rank;
      }

      return $tresult;
   }//build_tournament_result_seq_wins

} // end of 'TournamentResultControl'

?>

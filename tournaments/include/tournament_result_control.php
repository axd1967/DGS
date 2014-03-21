<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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
      if ( $this->tourney->Type == TOURNEY_TYPE_LADDER )
         $trtable->add_tablehead( 9, T_('Result#tourney_result'), 'Number', TABLE_NO_HIDE, 'Result+');
      $trtable->add_tablehead( 6, T_('Rank#tourney_result'), 'Number', TABLE_NO_HIDE, 'Rank+');
      $trtable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'TRP_Name+');
      $trtable->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE, 'TRP_Handle+');
      $trtable->add_tablehead( 3, T_('Country#header'), 'Image', 0, 'TRP_Count+');
      $trtable->add_tablehead( 4, T_('Current Rating#header'), 'Rating', 0, 'TRP_Rating2-');
      $trtable->add_tablehead( 5, T_('Result Rating#header'), 'Rating', 0, 'Rating-');
      if ( $this->tourney->Type == TOURNEY_TYPE_LADDER )
         $trtable->add_tablehead( 7, T_('Rank Kept#header'), '', 0, 'RankKept-');
      $trtable->add_tablehead( 8, T_('Result Date#header'), '', 0, 'EndTime+');
      $trtable->add_tablehead(10, T_('Comment#header'), '', 0, 'Comment+');

      if ( $this->tourney->Type == TOURNEY_TYPE_LADDER )
         $trtable->set_default_sort( 6, 7, 8 );
      elseif ( $this->tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
         $trtable->set_default_sort( 6, 8 );

      // load tournament-results
      $iterator = new ListIterator( "$dbgmsg.TRC.build_tournament_result_table.find_tresults",
            $trtable->get_query(),
            $trtable->current_order_string(),
            ( $this->limit < 0 ? $trtable->current_limit_string() : "LIMIT {$this->limit}" ) );
      if ( $this->show_all || $this->limit < 0 )
         $iterator = TournamentResult::load_tournament_results( $iterator, $tid, /*player-info*/true );
      else
         $iterator = TournamentCache::load_cache_tournament_results( "$dbgmsg.TRC.build_tournament_result_table",
            $tid, $iterator, /*player-info*/true );

      $this->show_rows = $trtable->compute_show_rows( $iterator->getResultRows() );
      $trtable->set_found_rows( ( $this->limit < 0 )
          ? mysql_found_rows("$dbgmsg.TRC.build_tournament_result_table.found_rows")
          : $this->show_rows );

      $this->iterator = $iterator;
      $this->table = $trtable;
   }//build_tournament_result_table

   /*! \brief Fills tournament-result-Table with data and returns table-string. */
   public function make_table_tournament_results()
   {
      global $base_path;
      $tid = $this->tourney->ID;

      while ( ($this->show_rows-- > 0) && list(,$arr_item) = $this->iterator->getListIterator() )
      {
         list( $tresult, $orow ) = $arr_item;
         $uid = $tresult->uid;
         $user = User::new_from_row($orow, 'TRP_');
         $is_mine = ( $this->my_id == $uid );

         $row_str = array();

         if ( $this->allow_edit_tourney && $this->table->Is_Column_Displayed[11] )
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
            $row_str[ 8] = ($tresult->EndTime > 0) ? date(DATE_FMT2, $tresult->EndTime) : '';
         if ( @$this->table->Is_Column_Displayed[ 9] )
         {
            if ( $tresult->Type == TRESULTTYPE_TL_SEQWINS )
               $row_str[ 9] = $tresult->Result;
            else
               $row_str[ 9] = '';
         }
         if ( $this->table->Is_Column_Displayed[10] )
            $row_str[10] = $tresult->Comment;
         if ( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $this->table->add_row( $row_str );
      }

      return $this->table->make_table();
   }//make_table_tournament_results

} // end of 'TournamentResultControl'

?>

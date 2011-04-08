<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/table_columns.php';
require_once 'include/classlib_user.php';
require_once 'include/std_functions.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_userconfig.php';
require_once 'tournaments/include/tournament_ladder.php';

 /*!
  * \file tournament_gui_helper.php
  *
  * \brief General functions to support GUI for tournaments.
  */


 /*!
  * \class TournamentGuiHelper
  *
  * \brief Helper-class with mostly static functions to support tournament GUI.
  */
class TournamentGuiHelper
{
   // ------------ static functions ----------------------------

   /*! \brief Returns tournament-standings of ladder, or null for none. */
   function build_tournament_ladder_standings( $page, $tid, $limit=0 )
   {
      global $player_row;
      $my_id = $player_row['ID'];

      // NOTE: see also tournaments/ladder/view.php

      $ltable = new Table( 'tournament_ladder', $page, null, '', 0 );
      $ltable->use_show_rows(false);

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $ltable->add_tablehead( 1, T_('Rank#T_ladder'), 'Number', TABLE_NO_HIDE );
      $ltable->add_tablehead( 3, T_('Name#T_ladder'), 'User', 0 );
      $ltable->add_tablehead( 4, T_('Userid#T_ladder'), 'User', TABLE_NO_HIDE );
      $ltable->add_tablehead( 5, T_('Country#T_ladder'), 'Image', 0 );
      $ltable->add_tablehead( 6, T_('Rating#T_ladder'), 'Rating', 0 );
      $ltable->add_tablehead(10, T_('Rank Kept#T_ladder'), '', 0 );
      $ltable->add_tablehead(13, T_('Last access#T_ladder'), '', 0 );

      $iterator = TournamentLadder::build_tournament_ladder_iterator( $tid, $ltable->get_query(), $limit );

      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         $uid = $tladder->uid;
         $user = User::new_from_row($orow, 'TLP_');
         $is_mine = ( $my_id == $uid );

         $row_str = array();

         if( $ltable->Is_Column_Displayed[ 1] )
            $row_str[ 1] = $tladder->Rank . '.';
         if( $ltable->Is_Column_Displayed[ 3] )
            $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
         if( $ltable->Is_Column_Displayed[ 4] )
            $row_str[ 4] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
         if( $ltable->Is_Column_Displayed[ 5] )
            $row_str[ 5] = getCountryFlagImage( $user->Country );
         if( $ltable->Is_Column_Displayed[ 6] )
            $row_str[ 6] = echo_rating( $user->Rating, true, $uid);
         if( $ltable->Is_Column_Displayed[10] )
            $row_str[10] = $tladder->build_rank_kept();
         if( $ltable->Is_Column_Displayed[13] )
            $row_str[13] = TimeFormat::echo_time_diff( $GLOBALS['NOW'], $user->Lastaccess, 24, TIMEFMT_SHORT, '' );
         if( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $ltable->add_row( $row_str );
      }

      return $ltable->make_table();
   }//build_tournament_ladder_standings

   function build_tournament_results( $page, $tourney )
   {
      global $player_row;
      $my_id = $player_row['ID'];
      $tid = $tourney->ID;

      // check + load tournament-results
      $iterator = new ListIterator( 'TournamentGuiHelper.build_tournament_results.load_tresult',
         null, 'ORDER BY Rank ASC, ID ASC' );
      $iterator->addQuerySQLMerge( new QuerySQL(
            SQLP_FIELDS, 'TRP.Name AS TRP_Name', 'TRP.Handle AS TRP_Handle',
                         'TRP.Country AS TRP_Country', 'TRP.Rating2 AS TRP_Rating2',
            SQLP_FROM,   'INNER JOIN Players AS TRP ON TRP.ID=TRS.uid'
         ));
      $iterator = TournamentResult::load_tournament_results( $iterator, $tid );

      if( $iterator->getItemCount() == 0 )
         return T_('No tournament results yet.#T_result');

      // create table
      $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_TOURNAMENT_RESULTS );

      $table = new Table( 'tournament_results', $page, $cfg_tblcols, '',
         TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE|TABLE_ROWS_NAVI );
      $table->use_show_rows(false);
      $table->add_or_del_column();

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $table->add_tablehead( 6, T_('Rank#tres_header'), 'Number', TABLE_NO_HIDE );
      $table->add_tablehead( 1, T_('Name#header'), 'User', TABLE_NO_HIDE);
      $table->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE);
      $table->add_tablehead( 3, T_('Country#header'), 'Image', 0);
      $table->add_tablehead( 4, T_('Current Rating#tres_header'), 'Rating', 0);
      $table->add_tablehead( 5, T_('Result Rating#tres_header'), 'Rating', 0);
      if( $tourney->Type == TOURNEY_TYPE_LADDER )
         $table->add_tablehead( 7, T_('Rank Kept#T_ladder'), '', 0);
      $table->add_tablehead( 8, T_('Result Date#tres_header'), '', 0);

      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tresult, $orow ) = $arr_item;
         $uid = $tresult->uid;
         $user = User::new_from_row($orow, 'TRP_');
         $is_mine = ( $my_id == $uid );

         $row_str = array();

         if( $table->Is_Column_Displayed[ 1] )
            $row_str[ 1] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
         if( $table->Is_Column_Displayed[ 2] )
            $row_str[ 2] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
         if( $table->Is_Column_Displayed[ 3] )
            $row_str[ 3] = getCountryFlagImage( $user->Country );
         if( $table->Is_Column_Displayed[ 4] )
            $row_str[ 4] = echo_rating( $user->Rating, true, $uid);
         if( $table->Is_Column_Displayed[ 5] )
            $row_str[ 5] = echo_rating( $tresult->Rating, true, 0);
         if( $table->Is_Column_Displayed[ 6] )
            $row_str[ 6] = $tresult->Rank . '.';
         if( @$table->Is_Column_Displayed[ 7] )
            $row_str[ 7] = TimeFormat::echo_time_diff( $tresult->EndTime, $tresult->StartTime, 24, TIMEFMT_SHORT, '' );
         if( $table->Is_Column_Displayed[ 8] )
            $row_str[ 8] = ($tresult->EndTime > 0) ? date(DATE_FMT2, $tresult->EndTime) : '';
         if( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $table->add_row( $row_str );
      }

      return $table->make_table();
   }//build_tournament_results

} // end of 'TournamentGuiHelper'

?>

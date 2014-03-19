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

require_once 'include/table_columns.php';
require_once 'include/classlib_user.php';
require_once 'include/std_functions.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/time_functions.php';
require_once 'include/classlib_userconfig.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_utils.php';


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
   public static function build_tournament_ladder_standings( $iterator, $page, $need_tp_rating )
   {
      global $player_row;
      $my_id = $player_row['ID'];

      // NOTE: see also tournaments/ladder/view.php

      $ltable = new Table( 'tournament_ladder', $page, null, '', 0 );
      $ltable->use_show_rows(false);

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $ltable->add_tablehead( 1, T_('Rank#T_ladder'), 'Number', TABLE_NO_HIDE );
      $ltable->add_tablehead( 3, T_('Name#header'), 'User', 0 );
      $ltable->add_tablehead( 4, T_('Userid#header'), 'User', TABLE_NO_HIDE );
      $ltable->add_tablehead( 5, T_('Country#header'), 'Image', 0 );
      $ltable->add_tablehead( 6, T_('Rating#header'), 'Rating', 0 );
      if ( $need_tp_rating )
         $ltable->add_tablehead(14, T_('Tournament Rating#header'), 'Rating', 0 );
      $ltable->add_tablehead(10, T_('Rank Kept#header'), '', 0 );
      $ltable->add_tablehead(13, T_('Last access#header'), '', 0 );

      while ( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         $uid = $tladder->uid;
         $user = User::new_from_row($orow, 'TLP_');
         $is_mine = ( $my_id == $uid );

         $row_str = array();

         if ( $ltable->Is_Column_Displayed[ 1] )
            $row_str[ 1] = $tladder->Rank . '.';
         if ( $ltable->Is_Column_Displayed[ 3] )
            $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
         if ( $ltable->Is_Column_Displayed[ 4] )
            $row_str[ 4] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
         if ( $ltable->Is_Column_Displayed[ 5] )
            $row_str[ 5] = getCountryFlagImage( $user->Country );
         if ( $ltable->Is_Column_Displayed[ 6] )
            $row_str[ 6] = echo_rating( $user->Rating, true, $uid);
         if ( $ltable->Is_Column_Displayed[10] )
            $row_str[10] = $tladder->build_rank_kept();
         if ( $ltable->Is_Column_Displayed[13] )
            $row_str[13] = TimeFormat::echo_time_diff( $GLOBALS['NOW'], $user->Lastaccess, 24, TIMEFMT_SHORT, '' );
         if ( $need_tp_rating && $ltable->Is_Column_Displayed[14] )
            $row_str[14] = echo_rating( @$orow['TP_Rating'], true, $uid);
         if ( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $ltable->add_row( $row_str );
      }

      return $ltable->make_table();
   }//build_tournament_ladder_standings

   /*! \brief Returns roles (text) of user in tournament: owner, tournament-director, tournament-admin. */
   public static function getTournamentRoleText( $tourney, $uid )
   {
      $arr = array();

      if ( $tourney->Owner_ID == $uid )
         $arr[] = T_('Owner#tourney');

      $td = TournamentCache::is_cache_tournament_director('TournamentGuiHelper.getTournamentRoleText', $tourney->ID, $uid, 0xffff);
      if ( !is_null($td) )
         $arr[] = sprintf( T_('Tournament Director [%s]'), $td->formatFlags() );

      if ( TournamentUtils::isAdmin() )
         $arr[] = T_('Tournament Admin');

      return (count($arr)) ? implode(', ', $arr) : NO_VALUE;
   }//getTournamentRoleText

   /*! \brief Returns (cached) registration-link-text for given user; return empty string if user-registration is denied. */
   public static function getLinkTextRegistration( $tid, $reg_user_status=null )
   {
      global $player_row;

      if ( is_null($reg_user_status) )
         $reg_user_status = TournamentCache::is_cache_tournament_participant( 'TGuiHelper.getLinkTextRegistration',
            $tid, $player_row['ID'] );
      if ( $reg_user_status != TP_STATUS_REGISTER )
      {
         if ( @$player_row['AdminOptions'] & ADMOPT_DENY_TOURNEY_REGISTER )
            return '';
      }

      return ($reg_user_status) ? T_('Edit my registration#tourney') : T_('Registration#tourney');
   }//getLinkTextRegistration

} // end of 'TournamentGuiHelper'

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/countries.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/gui_functions.php';
require_once 'include/rating.php';
require_once 'include/std_functions.php';
require_once 'include/table_columns.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_points.php';
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


   /*! \brief Returns array with notes about tournament pools. */
   function build_tournament_pool_notes( $tpoints, $pool_view )
   {
      $notes = array();

      $mfmt = MINI_SPACING . '%s' . MINI_SPACING;
      $sep = ', ' . MED_SPACING;
      $img_pool_winner = echo_image_tourney_pool_winner();
      $points_type_text = TournamentPoints::getPointsTypeText($tpoints->PointsType);

      if ( $pool_view )
      {
         $notes[] = sprintf( T_('Pools are ranked by Tie-breakers: %s'),
            implode(', ', array( T_('Points#tourney'), T_('SODOS#tourney') ) ));
      }

      if ( $pool_view )
      {
         $notes[] = array( T_('Pool matrix entries & colors (with link to game)#tpool_table') . ':',
            sprintf( T_("'%s' = running game, '%s' = no game#tpool"), '#', '-' )
            . $sep .
            span('MatrixSelf', T_('self#tpool_table'), $mfmt),
            span('MatrixWon', T_('game won#tpool_table'), $mfmt)
            . $sep .
            span('MatrixWon MatrixForfeit', T_('game won by forfeit#tpool_table'), $mfmt),
            span('MatrixLost', T_('game lost#tpool_table'), $mfmt)
            . $sep .
            span('MatrixLost MatrixForfeit', T_('game lost by forfeit#tpool_table'), $mfmt),
            span('MatrixDraw', T_('game draw#tpool_table'), $mfmt)
            . $sep .
            span('MatrixAnnulled', T_('game annulled#tpool_table'), $mfmt)
            . $sep .
            span('MatrixNoResult', T_('game no-result#tpool_table'), $mfmt)
            );

         $notes[] = sprintf( T_('[%s] in format "wins : losses" = number of wins and losses for user'), T_('#Wins#tourney') );
         $notes[] = sprintf( T_('[%s] = sum of points calculated from game-results of player'), T_('Points#header') );
      }

      $arr = array( T_('Points configuration type#tpoints') . ': ' . span('bold', $points_type_text ) );
      if ( $tpoints->PointsType == TPOINTSTYPE_SIMPLE )
      {
         $arr[] = sprintf( T_('game ended by score (>0), resignation or timeout: %s points for winner, %s points for loser#tpool_table'),
            $tpoints->PointsWon, $tpoints->PointsLost );
         $arr[] = sprintf( T_('game ended by forfeit: %s points for winner, %s points for loser#tpool_table'),
            $tpoints->PointsForfeit, $tpoints->PointsLost );
         $arr[] = sprintf( T_('game ended by draw: %s points for both players#tpool_table'), $tpoints->PointsDraw );
         $arr[] = sprintf( T_('game ended by no-result: %s points for both players#tpool_table'), $tpoints->PointsNoResult );
         $arr[] = sprintf( T_('game annulled: %s points#tpool_table'), 0 );
      }
      else //TPOINTSTYPE_HAHN
      {
         $arr[] = sprintf( T_('game ended by score or draw: %s points for every block of %s score-points#tpool_table'),
            1, $tpoints->ScoreBlock );
         $arr[] = sprintf( T_('game ended by resignation (%s points), timeout (%s points), forfeit (%s points), no-result (%s points)#tpool_table'),
            $tpoints->PointsResignation, $tpoints->PointsTimeout, $tpoints->PointsForfeit, $tpoints->PointsNoResult );
         $arr[] = sprintf( T_('max. points per game: %s points#tpool_table'), $tpoints->MaxPoints )
            . ( ($tpoints->Flags & TPOINTS_FLAGS_SHARE_MAX_POINTS) ? $sep . T_('share points for game ended by score or draw#tpool_table') : '' )
            . ( ($tpoints->Flags & TPOINTS_FLAGS_NEGATIVE_POINTS) ? $sep . T_('negative points allowed#tpool_table') : '' );
         $arr[] = sprintf( T_('game annulled: %s points#tpool_table'), 0 );
      }
      $notes[] = $arr;

      if ( $pool_view )
      {
         $notes[] = sprintf( T_('[%s] = Tie-Breaker SODOS = Sum of Defeated Opponents Score'), T_('SODOS#tourney') );
         $notes[] = array(
            sprintf( T_('[%s] = Rank of user within one pool (1=Highest rank); Format "R (CR) %s"#tpool'),
                     T_('Rank#tpool'), $img_pool_winner ),
            T_('R = (optional) rank set by tournament director, really final only at end of tournament round#tpool'),
            sprintf( T_('R = \'%s\' = user withdrawing from next round#tpool'), span('bold', NO_VALUE) ),
            T_('CR = preliminary calculated rank, omitted when it can\'t be calculated or identical to rank R#tpool'),
            sprintf( T_('%s = marks user as pool winner (to advance to next round, or mark for final result)#tpool'),
               $img_pool_winner ),
         );
      }

      return $notes;
   }//build_tournament_pool_notes

} // end of 'TournamentGuiHelper'

?>

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

require_once 'include/db_classes.php';
require_once 'include/dgs_cache.php';
require_once 'include/gui_functions.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_globals.php';
require_once 'tournaments/include/tournament_utils.php';

 /*!
  * \file tournament_points.php
  *
  * \brief Functions for handling tournament points-config: tables TournamentPoints
  */


define('TPOINTSTYPE_SIMPLE', 'SIMPLE');
define('TPOINTSTYPE_HAHN',   'HAHN');
define('CHECK_TPOINTS_TYPE', 'SIMPLE|HAHN');

define('TPOINTS_FLAGS_SHARE_MAX_POINTS', 0x01);
define('TPOINTS_FLAGS_NEGATIVE_POINTS',  0x02);

 /*!
  * \class TournamentPoints
  *
  * \brief Class to manage TournamentPoints-table to store points-config.
  */

global $ENTITY_TOURNAMENT_POINTS; //PHP5
$ENTITY_TOURNAMENT_POINTS = new Entity( 'TournamentPoints',
      FTYPE_PKEY, 'tid',
      FTYPE_CHBY,
      FTYPE_INT,  'tid', 'Flags', 'PointsWon', 'PointsLost', 'PointsDraw', 'PointsBye', 'PointsNoResult',
                  'ScoreBlock', 'MaxPoints', 'PointsResignation', 'PointsTimeout',
      FTYPE_DATE, 'Lastchanged',
      FTYPE_ENUM, 'PointsType'
   );

class TournamentPoints
{
   public $tid;
   public $PointsType;
   public $Flags;
   public $PointsWon;
   public $PointsLost;
   public $PointsDraw;
   public $PointsBye;
   public $PointsNoResult;
   public $ScoreBlock;
   public $MaxPoints;
   public $PointsResignation;
   public $PointsTimeout;
   public $Lastchanged;
   public $ChangedBy;

   /*! \brief Constructs TournamentPoints-object with specified arguments. */
   public function __construct( $tid=0, $points_type=TPOINTSTYPE_SIMPLE, $flags=TPOINTS_FLAGS_SHARE_MAX_POINTS,
         $points_won=2, $points_lost=0, $points_draw=1, $points_bye=2, $points_no_result=1,
         $score_block=10, $max_points=10, $points_resignation=10, $points_timeout=10,
         $lastchanged=0, $changed_by='' )
   {
      $this->tid = (int)$tid;
      $this->setPointsType( $points_type );
      $this->Flags = (int)$flags;
      $this->PointsWon = (int)$points_won;
      $this->PointsLost = (int)$points_lost;
      $this->PointsDraw = (int)$points_draw;
      $this->PointsBye = (int)$points_bye;
      $this->PointsNoResult = (int)$points_no_result;
      $this->ScoreBlock = (int)$score_block;
      $this->MaxPoints = (int)$max_points;
      $this->PointsResignation = (int)$points_resignation;
      $this->PointsTimeout = (int)$points_timeout;
      $this->Lastchanged = (int)$lastchanged;
      $this->ChangedBy = $changed_by;
   }

   public function setPointsType( $points_type )
   {
      if ( !preg_match( "/^(".CHECK_TPOINTS_TYPE.")$/", $points_type ) )
         error('invalid_args', "TournamentPoints.setPointsType($points_type)");
      $this->PointsType = $points_type;
   }

   public function getPointsLimit( $points_type=null )
   {
      $chk_ptype = (is_null($points_type)) ? $this->PointsType : $points_type;
      if ( $chk_ptype == TPOINTSTYPE_SIMPLE )
         return 100;
      else //if ( $chk_ptype == TPOINTSTYPE_HAHN )
         return 1000;
   }

   /*! \brief Inserts or updates tournament-points in database. */
   public function persist()
   {
      if ( self::isTournamentPoints($tid) )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->insert( "TournamentPoints.insert(%s)" );
   }

   public function update()
   {
      $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->update( "TournamentPoints.update(%s)" );
      self::delete_cache_tournament_points( 'TournamentPoints.update', $this->tid );
      return $result;
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->delete( "TournamentPoints.delete(%s)" );
      self::delete_cache_tournament_points( 'TournamentPoints.delete', $this->tid );
      return $result;
   }

   public function fillEntityData()
   {
      $data = $GLOBALS['ENTITY_TOURNAMENT_POINTS']->newEntityData();
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'PointsType', $this->PointsType );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'PointsWon', $this->PointsWon );
      $data->set_value( 'PointsLost', $this->PointsLost );
      $data->set_value( 'PointsDraw', $this->PointsDraw );
      $data->set_value( 'PointsBye', $this->PointsBye );
      $data->set_value( 'PointsNoResult', $this->PointsNoResult );
      $data->set_value( 'ScoreBlock', $this->ScoreBlock );
      $data->set_value( 'MaxPoints', $this->MaxPoints );
      $data->set_value( 'PointsResignation', $this->PointsResignation );
      $data->set_value( 'PointsTimeout', $this->PointsTimeout );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'ChangedBy', $this->ChangedBy );
      return $data;
   }

   public function setDefaults( $points_type )
   {
      if ( !preg_match( "/^(".CHECK_TPOINTS_TYPE.")$/", $points_type ) )
         return;

      $this->PointsType = $points_type;
      $this->Flags = 0;

      // SIMPLE only
      $this->PointsWon = 2;
      $this->PointsLost = 0;
      $this->PointsDraw = 1;

      // HAHN only
      $this->Flags |= TPOINTS_FLAGS_SHARE_MAX_POINTS;
      $this->ScoreBlock = 10;
      $this->MaxPoints = 10;
      $this->PointsResignation = 10;
      $this->PointsTimeout = 10;

      // SIMPLE | HAHN
      if ( $points_type == TPOINTSTYPE_SIMPLE )
      {
         $this->PointsBye = 2;
         $this->PointsNoResult = 1;
      }
      else //=TPOINTSTYPE_HAHN
      {
         $this->PointsBye = 10;
         $this->PointsNoResult = 0;
      }
   }//setDefaults

   /*! \brief Calculates points for given game-score (<0 = win, >0 = loss, 0=jigo). */
   public function calculate_points( $game_score )
   {
      if ( $this->PointsType == TPOINTSTYPE_SIMPLE )
      {
         if ( $game_score < 0 )
            return $this->PointsWon;
         elseif ( $game_score > 0 )
            return $this->PointsLost;
         else //=0
            return $this->PointsDraw;
      }
      else //=TPOINTSTYPE_HAHN
      {
         $share_points = true;
         $score_diff = abs($game_score);
         if ( $score_diff == SCORE_RESIGN )
         {
            $share_points = false;
            $points = $this->PointsResignation;
         }
         elseif ( $score_diff == SCORE_TIME )
         {
            $share_points = false;
            $points = $this->PointsTimeout;
         }
         elseif ( $score_diff == 0 )
            $points = 0;
         else // point-diff
         {
            if ( 2*floor($score_diff) != 2*$score_diff ) // +0.5 for fractional-score x.5
               $score_diff = round($score_diff);
            if ( $this->ScoreBlock > 1 )
               $points = floor( ($score_diff + $this->ScoreBlock - 1) / $this->ScoreBlock );
            else
               $points = $score_diff;
         }

         if ( $this->Flags & TPOINTS_FLAGS_SHARE_MAX_POINTS )
         {
            if ( $share_points )
               $points = max( 0, floor($this->MaxPoints / 2) - signum($game_score) * $points );
            else if ( $game_score > 0 ) // lost
               $points = 0;
         }
         elseif ( $this->Flags & TPOINTS_FLAGS_NEGATIVE_POINTS )
         {
            if ( $game_score > 0 )
               $points = -$points;
         }
         else
         {
            if ( $game_score > 0 )
               $points = 0;
         }

         if ( abs($points) > $this->MaxPoints )
            $points = signum($points) * abs($this->MaxPoints);

         return $points;
      }//HAHN
   }//calculate_points


   // ------------ static functions ----------------------------

   /*! \brief Checks, if tournament-points existing for given tournament. */
   public static function isTournamentPoints( $tid )
   {
      return (bool)mysql_single_fetch( "TournamentPoints:isTournamentPoints($tid)",
         "SELECT 1 FROM TournamentPoints WHERE tid='$tid' LIMIT 1" );
   }

   /*! \brief Returns db-fields to be used for query of single TournamentPoints-object for given tournament-id. */
   public static function build_query_sql( $tid )
   {
      $qsql = $GLOBALS['ENTITY_TOURNAMENT_POINTS']->newQuerySQL('TPOINT');
      $qsql->add_part( SQLP_WHERE, "TPOINT.tid='$tid'" );
      $qsql->add_part( SQLP_LIMIT, '1' );
      return $qsql;
   }

   /*! \brief Returns TournamentPoints-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $tpoints = new TournamentPoints(
            // from TournamentPoints
            @$row['tid'],
            @$row['PointsType'],
            @$row['Flags'],
            @$row['PointsWon'],
            @$row['PointsLost'],
            @$row['PointsDraw'],
            @$row['PointsBye'],
            @$row['PointsNoResult'],
            @$row['ScoreBlock'],
            @$row['MaxPoints'],
            @$row['PointsResignation'],
            @$row['PointsTimeout'],
            @$row['X_Lastchanged'],
            @$row['ChangedBy']
         );
      return $tpoints;
   }

   /*! \brief Loads and returns TournamentPoints-object for given tournament-ID. */
   public static function load_tournament_points( $tid )
   {
      $result = NULL;
      if ( $tid > 0 )
      {
         $qsql = self::build_query_sql( $tid );
         $row = mysql_single_fetch( "TournamentPoints:load_tournament_points($tid)",
            $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Returns points-type-text or all points-type-texts (if arg=null). */
   public static function getPointsTypeText( $points_type=null )
   {
      static $ARR_POINTS_TYPES = null; // [points-type] => text

      // lazy-init of texts
      if ( is_null($ARR_POINTS_TYPES) )
      {
         $arr = array();
         $arr[TPOINTSTYPE_SIMPLE] = T_('Simple#TPOINTS_type');
         $arr[TPOINTSTYPE_HAHN]   = T_('Hahn#TPOINTS_type');
         $ARR_POINTS_TYPES = $arr;
      }

      if ( is_null($points_type) )
         return $ARR_POINTS_TYPES;
      if ( !isset($ARR_POINTS_TYPES[$points_type]) )
         error('invalid_args', "TournamentPoints:getPointsTypeText($points_type)");
      return $ARR_POINTS_TYPES[$points_type];
   }//getPointsTypeText

   public static function get_edit_tournament_status()
   {
      static $statuslist = array( TOURNEY_STATUS_NEW );
      return $statuslist;
   }

   public static function delete_cache_tournament_points( $dbgmsg, $tid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_TPOINTS, "TPoints.$tid" );
   }

} // end of 'TournamentPoints'
?>

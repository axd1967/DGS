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

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/classlib_user.php';

 /*!
  * \file waitingroom.php
  *
  * \brief Functions for managing waitingroom: tables Waitingroom
  * \see specs/db/table-Waitingroom.txt
  */


 /*!
  * \class Waitingroom
  *
  * \brief Class to manage Waitingroom-table
  */

global $ENTITY_WROOM; //PHP5
$ENTITY_WROOM = new Entity( 'Waitingroom',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'uid', 'gid', 'ShapeID', 'nrGames', 'Size', 'Handicap',
                  'AdjHandicap', 'MinHandicap', 'MaxHandicap', 'Maintime', 'Byotime', 'Byoperiods',
                  'RatingMin', 'RatingMax', 'MinRatedGames', 'MinHeroRatio', 'SameOpponent',
      FTYPE_FLOAT, 'Komi', 'AdjKomi',
      FTYPE_ENUM, 'GameType', 'Handicaptype', 'JigoMode', 'Rated', 'StdHandicap', 'WeekendClock', 'MustBeRated',
      FTYPE_TEXT, 'GamePlayers', 'Ruleset', 'Handicaptype', 'Byotype', 'ShapeSnapshot', 'Comment',
      FTYPE_DATE, 'Time'
   );

class Waitingroom
{
   public $ID;
   public $uid;
   public $gid;
   public $CountOffers;
   public $Created;
   public $GameType;
   public $GamePlayers;
   public $Ruleset;
   public $Size;
   public $Handicaptype;
   public $Komi;
   public $Handicap;
   public $AdjKomi;
   public $JigoMode;
   public $AdjHandicap;
   public $MinHandicap;
   public $MaxHandicap;
   public $Maintime;
   public $Byotype;
   public $Byotime;
   public $Byoperiods;
   public $WeekendClock;
   public $Rated;
   public $StdHandicap;
   public $MustBeRated;
   public $RatingMin;
   public $RatingMax;
   public $MinRatedGames;
   public $MinHeroRatio;
   public $SameOpponent;
   public $ShapeID;
   public $ShapeSnapshot;
   public $Comment;

   // non-DB fields

   public $User; // User-object
   public $wrow = null; // remaining fields from larger query

   /*! \brief Constructs Waitingroom-object with specified arguments. */
   public function __construct( $id=0, $uid=0, $user=null, $gid=0, $count_offers=0, $created=0,
         $game_type=GAMETYPE_GO, $game_players='1:1', $ruleset=RULESET_JAPANESE, $size=19, $htype=HTYPE_NIGIRI, $komi=0,
         $handicap=0, $adj_komi=0, $jigo_mode=JIGOMODE_KEEP_KOMI, $adj_handicap=0, $min_handicap=0, $max_handicap=0,
         $maintime=0, $byotype=BYOTYPE_FISCHER, $byotime=0, $byoperiods=0, $weekendclock=true,
         $rated=true, $std_handicap=true, $must_be_rated=false, $rating_min=0, $rating_max=0,
         $min_rated_games=0, $min_hero_ratio=0, $same_opponent=0, $shape_id=0,
         $shape_snaphost='', $comment='' )
   {
      $this->ID = (int)$id;
      $this->uid = (int)$uid;
      $this->gid = (int)$gid;
      $this->CountOffers = (int)$count_offers;
      $this->Created = (int)$created;
      $this->GameType = $game_type;
      $this->GamePlayers = $game_players;
      $this->setRuleset( $ruleset );
      $this->Size = (int)$size;
      $this->Handicaptype = $htype;
      $this->Komi = (float)$komi;
      $this->Handicap = (int)$handicap;
      $this->AdjKomi = (float)$adj_komi;
      $this->JigoMode = $jigo_mode;
      $this->AdjHandicap = (int)$adj_handicap;
      $this->MinHandicap = (int)$min_handicap;
      $this->MaxHandicap = (int)$max_handicap;
      $this->Maintime = (int)$maintime;
      $this->Byotype = $byotype;
      $this->Byotime = (int)$byotime;
      $this->Byoperiods = (int)$byoperiods;
      $this->WeekendClock = (bool)$weekendclock;
      $this->Rated = (bool)$rated;
      $this->StdHandicap = (bool)$std_handicap;
      $this->MustBeRated = (bool)$must_be_rated;
      $this->RatingMin = (int)$rating_min;
      $this->RatingMax = (int)$rating_max;
      $this->MinRatedGames = (int)$min_rated_games;
      $this->MinHeroRatio = (int)$min_hero_ratio;
      $this->SameOpponent = (int)$same_opponent;
      $this->ShapeID = (int)$shape_id;
      $this->ShapeSnapshot = $shape_snaphost;
      $this->Comment = $comment;
      // non-DB fields
      $this->User = ($user instanceof User) ? $user : new User( $this->uid );
   }//__construct

   public function setRuleset( $ruleset )
   {
      if ( !preg_match( "/^(".CHECK_RULESETS.")$/", $ruleset ) )
         error('invalid_args', "Waitingroom.setRuleset($ruleset)");
      if ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $ruleset ) )
         error('feature_disabled', "Waitingroom.setRuleset($ruleset)");
      $this->Ruleset = $ruleset;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Waitingroom-entry in database. */
   public function persist()
   {
      if ( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   public function insert()
   {
      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Waitingroom.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "Waitingroom.update(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_WROOM']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'CountOffers', $this->CountOffers );
      $data->set_value( 'Created', $this->Created );
      $data->set_value( 'GameType', $this->GameType );
      $data->set_value( 'GamePlayers', $this->GamePlayers );
      $data->set_value( 'Ruleset', $this->Ruleset );
      $data->set_value( 'Size', $this->Size );
      $data->set_value( 'Handicaptype', $this->Handicaptype );
      $data->set_value( 'Komi', $this->Komi );
      $data->set_value( 'Handicap', $this->Handicap );
      $data->set_value( 'AdjKomi', $this->AdjKomi );
      $data->set_value( 'JigoMode', $this->JigoMode );
      $data->set_value( 'AdjHandicap', $this->AdjHandicap );
      $data->set_value( 'MinHandicap', $this->MinHandicap );
      $data->set_value( 'MaxHandicap', $this->MaxHandicap );
      $data->set_value( 'Maintime', $this->Maintime );
      $data->set_value( 'Byotype', $this->Byotype );
      $data->set_value( 'Byotime', $this->Byotime );
      $data->set_value( 'Byoperiods', $this->Byoperiods );
      $data->set_value( 'WeekendClock', ($this->WeekendClock ? 'Y' : 'N') );
      $data->set_value( 'Rated', ($this->Rated ? 'Y' : 'N') );
      $data->set_value( 'StdHandicap', ($this->StdHandicap ? 'Y' : 'N') );
      $data->set_value( 'MustBeRated', ($this->MustBeRated ? 'Y' : 'N') );
      $data->set_value( 'RatingMin', $this->RatingMin );
      $data->set_value( 'RatingMax', $this->RatingMax );
      $data->set_value( 'MinRatedGames', $this->MinRatedGames );
      $data->set_value( 'MinHeroRatio', $this->MinHeroRatio );
      $data->set_value( 'SameOpponent', $this->SameOpponent );
      $data->set_value( 'ShapeID', $this->ShapeID );
      $data->set_value( 'ShapeSnapshot', $this->ShapeSnapshot );
      $data->set_value( 'Comment', $this->Comment );
      return $data;
   }//fillEntityData


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of Waitingroom-objects for given wroom-id. */
   public static function build_query_sql( $wroom_id=0, $with_player=true )
   {
      $qsql = $GLOBALS['ENTITY_WROOM']->newQuerySQL('WR');
      if ( $with_player )
      {
         $qsql->add_part( SQLP_FIELDS,
            'WR.uid AS WRP_ID',
            'WRP.Name AS WRP_Name',
            'WRP.Handle AS WRP_Handle',
            'WRP.Type AS WRP_Type',
            'WRP.Rating2 AS WRP_Rating2',
            'WRP.RatingStatus AS WRP_RatingStatus',
            'WRP.Country AS WRP_Country',
            'UNIX_TIMESTAMP(WRP.Lastaccess) AS WRP_Lastaccess',
            'WRP.OnVacation AS WRP_OnVacation',
            'IF(WRP.Finished>='.MIN_FIN_GAMES_HERO_AWARD.',WRP.GamesWeaker/WRP.Finished,0) AS WRP_HeroRatio' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS WRP ON WRP.ID=WR.uid' );
      }
      if ( $wroom_id > 0 )
         $qsql->add_part( SQLP_WHERE, "WR.ID=$wroom_id" );
      return $qsql;
   }//build_query_sql

   /*! \brief Returns Waitingroom-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $wroom = new Waitingroom(
            // from Waitingroom
            @$row['ID'],
            @$row['uid'],
            User::new_from_row( $row, 'WRP_', true ), // from Players WRP
            @$row['gid'],
            @$row['nrGames'],
            @$row['X_Time'], //Created
            @$row['GameType'],
            @$row['GamePlayers'],
            @$row['Ruleset'],
            @$row['Size'],
            @$row['Handicaptype'],
            @$row['Komi'],
            @$row['Handicap'],
            @$row['AdjKomi'],
            @$row['JigoMode'],
            @$row['AdjHandicap'],
            @$row['MinHandicap'],
            @$row['MaxHandicap'],
            @$row['Maintime'],
            @$row['Byotype'],
            @$row['Byotime'],
            @$row['Byoperiods'],
            ( @$row['WeekendClock'] == 'Y' ),
            ( @$row['Rated'] == 'Y' ),
            ( @$row['StdHandicap'] == 'Y' ),
            ( @$row['MustBeRated'] == 'Y' ),
            @$row['RatingMin'],
            @$row['RatingMax'],
            @$row['MinRatedGames'],
            @$row['MinHeroRatio'],
            @$row['SameOpponent'],
            @$row['ShapeID'],
            @$row['ShapeSnapshot'],
            @$row['Comment']
         );
      $wroom->wrow = $row;
      return $wroom;
   }//new_from_row

   /*!
    * \brief Loads and returns Waitingroom-object for given waiting-room-id limited to 1 result-entry.
    * \param $wroom_id Waitingroom.ID
    * \return NULL if nothing found; Waitingroom-object otherwise
    */
   public static function load_waitingroom( $wroom_id, $with_player=true )
   {
      $qsql = self::build_query_sql( $wroom_id, $with_player );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Waitingroom:load_wroom.find_wroom($wroom_id)", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_waitingroom

   /*!
    * \brief Loads and returns Waitingroom-object for given QuerySQL limited to 1 result-entry.
    */
   public static function load_waitingroom_by_query( $qsql )
   {
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Waitingroom:load_wroom_by_query.find_wroom()", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_waitingroom_by_query

   /*! \brief Returns enhanced (passed) ListIterator with Waitingroom-objects. */
   public static function load_waitingroom_entries( $qsql, $iterator )
   {
      $iterator->setQuerySQL( $qsql );
      $query = $iterator->buildQuery();
      $result = db_query( "Waitingroom:load_waitingroom_entries", $query );
      $iterator->setResultRows( mysql_num_rows($result) );

      $iterator->clearItems();
      while ( $row = mysql_fetch_array( $result ) )
      {
         $wroom = self::new_from_row( $row );
         $iterator->addItem( $wroom, $row );
      }
      mysql_free_result($result);

      return $iterator;
   }//load_waitingroom_entries

} // end of 'Waitingroom'
?>

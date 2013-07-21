<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /*!
  * \file game_invitation.php
  *
  * \brief Functions for managing game-invitation properties: table GameInvitation
  * \see specs/db/table-Games.txt
  */


 /*!
  * \class GameInvitation
  *
  * \brief Class to manage GameInvitation-table
  */

global $ENTITY_GAMEINV; //PHP5
$ENTITY_GAMEINV = new Entity( 'GameInvitation',
      FTYPE_PKEY, 'gid', 'uid',
      FTYPE_INT,  'gid', 'uid', 'Size', 'Handicap', 'AdjHandicap', 'MinHandicap', 'MaxHandicap',
                  'Maintime', 'Byotime', 'Byoperiods',
      FTYPE_FLOAT, 'Komi', 'AdjKomi',
      FTYPE_ENUM, 'Handicaptype', 'JigoMode', 'Rated', 'StdHandicap', 'WeekendClock',
      FTYPE_TEXT, 'Ruleset', 'Handicaptype', 'Byotype',
      FTYPE_DATE, 'Lastchanged'
   );

class GameInvitation
{
   public $gid;
   public $uid;
   public $Lastchanged;
   public $Ruleset;
   public $Size;
   public $Komi;
   public $Handicap;
   public $Handicaptype;
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

   /*! \brief Constructs GameInvitation-object with specified arguments. */
   public function __construct( $gid=0, $uid=0, $last_changed=0,
         $ruleset=RULESET_JAPANESE, $size=19, $komi=0, $handicap=0, $htype=HTYPE_NIGIRI,
         $adj_komi=0, $jigo_mode=JIGOMODE_KEEP_KOMI, $adj_handicap=0, $min_handicap=0, $max_handicap=0,
         $maintime=0, $byotype=BYOTYPE_FISCHER, $byotime=0, $byoperiods=0, $weekendclock=true,
         $rated=true, $std_handicap=true )
   {
      $this->gid = (int)$gid;
      $this->uid = (int)$uid;
      $this->Lastchanged = (int)$last_changed;
      $this->setRuleset( $ruleset );
      $this->Size = (int)$size;
      $this->Komi = (float)$komi;
      $this->Handicap = (int)$handicap;
      $this->Handicaptype = $htype;
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
   }//__construct

   public function setRuleset( $ruleset )
   {
      if ( !preg_match( "/^(".CHECK_RULESETS.")$/", $ruleset ) )
         error('invalid_args', "GameInvitation.setRuleset($ruleset)");
      if ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $ruleset ) )
         error('feature_disabled', "GameInvitation.setRuleset($ruleset)");
      $this->Ruleset = $ruleset;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates GameInvitation-entry in database. */
   public function persist()
   {
      $entityData = $this->fillEntityData();
      $query = $entityData->build_sql_insert_values(true)
         . $entityData->build_sql_insert_values()
         . " ON DUPLICATE KEY UPDATE Lastchanged=VALUES(Lastchanged), "
            . "Ruleset=VALUES(Ruleset), Size=VALUES(Size), Komi=VALUES(Komi), Handicap=VALUES(Handicap), "
            . "Handicaptype=VALUES(Handicaptype), AdjKomi=VALUES(AdjKomi), JigoMode=VALUES(JigoMode), "
            . "AdjHandicap=VALUES(AdjHandicap), MinHandicap=VALUES(MinHandicap), MaxHandicap=VALUES(MaxHandicap), "
            . "Maintime=VALUES(Maintime), Byotype=VALUES(Byotype), Byotime=VALUES(Byotime), "
            . "Byoperiods=VALUES(Byoperiods), WeekendClock=VALUES(WeekendClock), "
            . "Rated=VALUES(Rated), StdHandicap=VALUES(StdHandicap)";
      return db_query( "GameInvitation.persist.on_dupl_key({$this->gid},{$this->uid})", $query );
   }

   public function insert()
   {
      $entityData = $this->fillEntityData();
      return $entityData->insert( "GameInvitation.insert(%s)" );
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "GameInvitation.update(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_GAMEINV']->newEntityData();
      $data->set_value( 'gid', $this->gid );
      $data->set_value( 'uid', $this->uid );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'Ruleset', $this->Ruleset );
      $data->set_value( 'Size', $this->Size );
      $data->set_value( 'Komi', $this->Komi );
      $data->set_value( 'Handicap', $this->Handicap );
      $data->set_value( 'Handicaptype', $this->Handicaptype );
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
      return $data;
   }//fillEntityData


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of GameInvitation-objects for given game-id and optional uid. */
   public static function build_query_sql( $gid, $uid=0 )
   {
      $gid = (int)$gid;
      $uid = (int)$uid;
      $qsql = $GLOBALS['ENTITY_GAMEINV']->newQuerySQL('GI');
      $qsql->add_part( SQLP_WHERE, "GI.gid=$gid" );
      if ( $uid > 0 )
         $qsql->add_part( SQLP_WHERE, "GI.uid=$uid" );
      return $qsql;
   }//build_query_sql

   /*! \brief Returns GameInvitation-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $game_inv = new GameInvitation(
            // from GameInvitation
            @$row['gid'],
            @$row['uid'],
            @$row['X_Lastchanged'], //Lastchanged
            @$row['Ruleset'],
            @$row['Size'],
            @$row['Komi'],
            @$row['Handicap'],
            @$row['Handicaptype'],
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
            ( @$row['StdHandicap'] == 'Y' )
         );
      return $game_inv;
   }//new_from_row

   /*!
    * \brief Loads and returns GameInvitation-object for given game-id and user-id.
    * \return GameInvitation-object, or NULL if nothing found
    */
   public static function load_game_invitation( $gid, $uid )
   {
      if ( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "GameInvitation:load_game_invitation.check_gid($gid)");
      if ( !is_numeric($uid) || $uid <= 0 )
         error('invalid_args', "GameInvitation:load_game_invitation.check_uid($uid)");

      $qsql = self::build_query_sql( $gid, $uid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "GameInvitation:load_game_invitation.find($gid,$uid)", $qsql->get_select() );
      return ($row) ? self::new_from_row($row) : NULL;
   }//load_game_invitation

   /*!
    * \brief Loads and returns GameInvitation-objects for given game-id for both players.
    * \return non-null array( GameInvitation-object, ..); per gid there should always be exactly two entries
    */
   public static function load_game_invitations( $gid )
   {
      $qsql = self::build_query_sql( $gid, 0 );
      $result = db_query( "GameInvitation:load_game_invitations($gid)", $qsql->get_select() );

      $out = array();
      while ( $row = mysql_fetch_array($result) )
         $out[] = self::new_from_row( $row );
      mysql_free_result($result);

      return $out;
   }//load_game_invitations

   /*! \brief Inserts multiple game-invitations given as array of GameInvitation-objects. */
   public static function insert_game_invitations( $arr_ginv )
   {
      if ( count($arr_ginv) == 0 )
         return 0;
      $gid = $arr_ginv[0]->gid;

      $qparts = array();
      $uids = array();
      foreach ( $arr_ginv as $game_inv )
      {
         $entityData = $game_inv->fillEntityData();
         $qparts[] = $entityData->build_sql_insert_values();
         $uids[] = $game_inv->uid;
      }

      db_query( "GameInvitation.insert_game_invitations.insert($gid;".implode(',',$uids).")",
         $entityData->build_sql_insert_values(true) . implode(', ', $qparts) );
      return mysql_affected_rows();
   }//insert_game_invitations

   /*! \brief Deletes (two) game-invitations for given game-id. */
   public static function delete_game_invitations( $gid )
   {
      $gid = (int)$gid;
      return db_query( "GameInvitation:delete_game_invitations($gid)",
         "DELETE FROM GameInvitation WHERE gid=$gid LIMIT 2" );
   }

} // end of 'GameInvitation'

?>

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

$TranslateGroups[] = "Game";

require_once 'include/globals.php';
require_once 'include/db_classes.php';
require_once 'include/std_classes.php';
require_once 'include/time_functions.php';

 /*!
  * \file games.php
  *
  * \brief Functions for managing games: tables Games
  */


 /*!
  * \class Games
  *
  * \brief Class to manage normal games
  */

global $ENTITY_GAMES; //PHP5
$ENTITY_GAMES = new Entity( 'Games',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'tid', 'ShapeID', 'mid', 'DoubleGame_ID', 'Black_ID', 'White_ID', 'ToMove_ID', 'Size',
                  'Handicap', 'Moves', 'Black_Prisoners', 'White_Prisoners', 'Last_X', 'Last_Y', 'Flags',
                  'Maintime', 'Byotime', 'Byoperiods', 'Black_Maintime', 'White_Maintime',
                  'Black_Byotime', 'White_Byotime', 'Black_Byoperiods', 'White_Byoperiods', 'LastTicks',
                  'ClockUsed', 'TimeOutDate',
      FTYPE_FLOAT, 'Komi', 'Score', 'Black_Start_Rating', 'White_Start_Rating', 'Black_End_Rating',
                   'White_End_Rating',
      FTYPE_TEXT, 'GamePlayers', 'Last_Move', 'Snapshot', 'ShapeSnapshot', 'GameSetup',
      FTYPE_DATE, 'Starttime', 'Lastchanged',
      FTYPE_ENUM, 'Status', 'GameType', 'Ruleset', 'Byotype', 'Rated', 'StdHandicap', 'WeekendClock'
   );

class Games
{
   public $ID;
   public $tid;
   public $ShapeID;
   public $Starttime; // not the typical entity-created, but start-time of game (after setup)
   public $Lastchanged; // not the typical entity-lastchanged, but "LastMoved"
   public $mid;
   public $DoubleGame_ID;
   public $Black_ID;
   public $White_ID;
   public $ToMove_ID;
   public $GameType;
   public $GamePlayers;
   public $Ruleset;
   public $Size;
   public $Komi;
   public $Handicap;
   public $Status;
   public $Moves;
   public $Black_Prisoners;
   public $White_Prisoners;
   public $Last_X;
   public $Last_Y;
   public $Last_Move;
   public $Flags;
   public $Score;
   public $Maintime;
   public $Byotype;
   public $Byotime;
   public $Byoperiods;
   public $Black_Maintime;
   public $White_Maintime;
   public $Black_Byotime;
   public $White_Byotime;
   public $Black_Byoperiods;
   public $White_Byoperiods;
   public $LastTicks;
   public $ClockUsed;
   public $TimeOutDate;
   public $Rated;
   public $StdHandicap;
   public $WeekendClock;
   public $Black_Start_Rating;
   public $White_Start_Rating;
   public $Black_End_Rating;
   public $White_End_Rating;
   public $Snapshot;
   public $ShapeSnapshot;
   public $GameSetup;

   // other DB-fields

   public $grow = null;

   /*! \brief Constructs Games-object with specified arguments. */
   public function __construct( $id=0, $tid=0, $shape_id=0, $starttime=0, $lastchanged=0, $mid=0, $double_gid=0,
         $black_id=0, $white_id=0, $tomove_id=0, $game_type=GAMETYPE_GO, $game_players='',
         $ruleset=RULESET_JAPANESE, $size=19, $komi=6.5, $handicap=0,
         $status=GAME_STATUS_INVITED, $moves=0, $black_prisoners=0, $white_prisoners=0,
         $last_x=-1, $last_y=-1, $last_move='', $flags=0, $score=0.0, $maintime=0,
         $byotype=BYOTYPE_JAPANESE, $byotime=0, $byoperiods=0, $black_maintime=0,
         $white_maintime=0, $black_byotime=0, $white_byotime=0, $black_byoperiods=0,
         $white_byoperiods=0, $lastticks=0, $clockused=0, $timeoutdate=0, $rated='N',
         $stdhandicap=true, $weekendclock=true, $black_start_rating=NO_RATING,
         $white_start_rating=NO_RATING, $black_end_rating=NO_RATING,
         $white_end_rating=NO_RATING, $snapshot='', $shape_snapshot='', $game_setup='' )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->ShapeID = (int)$shape_id;
      $this->Starttime = (int)$starttime;
      $this->Lastchanged = (int)$lastchanged;
      $this->mid = (int)$mid;
      $this->DoubleGame_ID = (int)$double_gid;
      $this->Black_ID = (int)$black_id;
      $this->White_ID = (int)$white_id;
      $this->ToMove_ID = (int)$tomove_id;
      $this->setGameType( $game_type );
      $this->GamePlayers = $game_players;
      $this->setRuleset( $ruleset );
      $this->Size = (int)$size;
      $this->Komi = (float)$komi;
      $this->Handicap = (int)$handicap;
      $this->setStatus( $status );
      $this->Moves = (int)$moves;
      $this->Black_Prisoners = (int)$black_prisoners;
      $this->White_Prisoners = (int)$white_prisoners;
      $this->Last_X = (int)$last_x;
      $this->Last_Y = (int)$last_y;
      $this->Last_Move = (int)$last_move;
      $this->Flags = (int)$flags;
      $this->Score = (float)$score;
      $this->Maintime = (int)$maintime;
      $this->setByotype( $byotype );
      $this->Byotime = (int)$byotime;
      $this->Byoperiods = (int)$byoperiods;
      $this->Black_Maintime = (int)$black_maintime;
      $this->White_Maintime = (int)$white_maintime;
      $this->Black_Byotime = (int)$black_byotime;
      $this->White_Byotime = (int)$white_byotime;
      $this->Black_Byoperiods = (int)$black_byoperiods;
      $this->White_Byoperiods = (int)$white_byoperiods;
      $this->LastTicks = (int)$lastticks;
      $this->ClockUsed = (int)$clockused;
      $this->TimeOutDate = (int)$timeoutdate;
      $this->setRated( $rated );
      $this->StdHandicap = (bool)$stdhandicap;
      $this->WeekendClock = (bool)$weekendclock;
      $this->Black_Start_Rating = (float)$black_start_rating;
      $this->White_Start_Rating = (float)$white_start_rating;
      $this->Black_End_Rating = (float)$black_end_rating;
      $this->White_End_Rating = (float)$white_end_rating;
      $this->Snapshot = $snapshot;
      $this->ShapeSnapshot = $shape_snapshot;
      $this->GameSetup = $game_setup;
   }//__construct

   public function setStatus( $status )
   {
      if ( !preg_match( "/^(".CHECK_GAME_STATUS.")$/", $status ) )
         error('invalid_args', "Games.setStatus($status)");
      $this->Status = $status;
   }

   public function setGameType( $game_type )
   {
      if ( !preg_match( "/^(".CHECK_GAMETYPE.")$/", $game_type ) )
         error('invalid_args', "Games.setGameType($game_type)");
      $this->GameType = $game_type;
   }

   public function setRuleset( $ruleset )
   {
      if ( !preg_match( "/^(".CHECK_RULESETS.")$/", $ruleset ) )
         error('invalid_args', "Games.setRuleset($ruleset)");
      if ( !preg_match( "/^(".ALLOWED_RULESETS.")$/", $ruleset ) )
         error('feature_disabled', "Games.setRuleset($ruleset)");
      $this->Ruleset = $ruleset;
   }

   public function setByotype( $byotype )
   {
      if ( !preg_match( "/^".REGEX_BYOTYPES."$/", $byotype ) )
         error('invalid_args', "Games.setByotype($byotype)");
      $this->Byotype = $byotype;
   }

   public function setRated( $rated )
   {
      if ( !preg_match( "/^(Y|N|Done)$/", $rated ) )
         error('invalid_args', "Games.setRated($rated)");
      $this->Rated = $rated;
   }

   public function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Games-entry in database. */
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
      $result = $entityData->insert( "Games.insert(%s)" );
      if ( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   public function update()
   {
      $entityData = $this->fillEntityData();
      return $entityData->update( "Games.update(%s)" );
   }

   public function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "Games.delete(%s)" );
   }

   public function fillEntityData( $data=null )
   {
      if ( is_null($data) )
         $data = $GLOBALS['ENTITY_GAMES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'ShapeID', $this->ShapeID );
      $data->set_value( 'Starttime', $this->Starttime );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'mid', $this->mid );
      $data->set_value( 'DoubleGame_ID', $this->DoubleGame_ID );
      $data->set_value( 'Black_ID', $this->Black_ID );
      $data->set_value( 'White_ID', $this->White_ID );
      $data->set_value( 'ToMove_ID', $this->ToMove_ID );
      $data->set_value( 'GameType', $this->GameType );
      $data->set_value( 'GamePlayers', $this->GamePlayers );
      $data->set_value( 'Ruleset', $this->Ruleset );
      $data->set_value( 'Size', $this->Size );
      $data->set_value( 'Komi', $this->Komi );
      $data->set_value( 'Handicap', $this->Handicap );
      $data->set_value( 'Status', $this->Status );
      $data->set_value( 'Moves', $this->Moves );
      $data->set_value( 'Black_Prisoners', $this->Black_Prisoners );
      $data->set_value( 'White_Prisoners', $this->White_Prisoners );
      $data->set_value( 'Last_X', $this->Last_X );
      $data->set_value( 'Last_Y', $this->Last_Y );
      $data->set_value( 'Last_Move', $this->Last_Move );
      $data->set_value( 'Flags', $this->Flags );
      $data->set_value( 'Score', $this->Score );
      $data->set_value( 'Maintime', $this->Maintime );
      $data->set_value( 'Byotype', $this->Byotype );
      $data->set_value( 'Byotime', $this->Byotime );
      $data->set_value( 'Byoperiods', $this->Byoperiods );
      $data->set_value( 'Black_Maintime', $this->Black_Maintime );
      $data->set_value( 'White_Maintime', $this->White_Maintime );
      $data->set_value( 'Black_Byotime', $this->Black_Byotime );
      $data->set_value( 'White_Byotime', $this->White_Byotime );
      $data->set_value( 'Black_Byoperiods', $this->Black_Byoperiods );
      $data->set_value( 'White_Byoperiods', $this->White_Byoperiods );
      $data->set_value( 'LastTicks', $this->LastTicks );
      $data->set_value( 'ClockUsed', $this->ClockUsed );
      $data->set_value( 'TimeOutDate', $this->TimeOutDate );
      $data->set_value( 'Rated', $this->Rated );
      $data->set_value( 'StdHandicap', ($this->StdHandicap ? 'Y' : 'N') );
      $data->set_value( 'WeekendClock', ($this->WeekendClock ? 'Y' : 'N') );
      $data->set_value( 'Black_Start_Rating', $this->Black_Start_Rating );
      $data->set_value( 'White_Start_Rating', $this->White_Start_Rating );
      $data->set_value( 'Black_End_Rating', $this->Black_End_Rating );
      $data->set_value( 'White_End_Rating', $this->White_End_Rating );
      $data->set_value( 'Snapshot', $this->Snapshot );
      $data->set_value( 'ShapeSnapshot', $this->ShapeSnapshot );
      $data->set_value( 'GameSetup', $this->GameSetup );
      return $data;
   }//fillEntityData


   // ------------ static functions ----------------------------

   public static function buildFlags( $flags )
   {
      $arr = array();
      if ( $flags & GAMEFLAGS_KO )
         $arr[] = 'Ko';
      if ( $flags & GAMEFLAGS_HIDDEN_MSG )
         $arr[] = 'HiddenMsg';
      if ( $flags & GAMEFLAGS_ADMIN_RESULT )
         $arr[] = 'AdmResult';
      if ( $flags & GAMEFLAGS_TG_DETACHED )
         $arr[] = 'TGDetached';
      if ( $flags & GAMEFLAGS_ATTACHED_SGF )
         $arr[] = 'AttachedSgf';
      if ( $flags & GAMEFLAGS_NO_RESULT )
         $arr[] = 'NoResult';
      return implode(',', $arr);
   }//buildFlags

   /*! \brief Returns db-fields to be used for query of Games-objects for given game-id. */
   public static function build_query_sql( $gid=0 )
   {
      $qsql = $GLOBALS['ENTITY_GAMES']->newQuerySQL('G');
      if ( $gid > 0 )
         $qsql->add_part( SQLP_WHERE, "G.ID='$gid'" );
      return $qsql;
   }

   /*! \brief Returns Games-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $g = new Games(
            // from Games
            @$row['ID'],
            @$row['tid'],
            @$row['ShapeID'],
            @$row['X_Starttime'],
            @$row['X_Lastchanged'],
            @$row['mid'],
            @$row['DoubleGame_ID'],
            @$row['Black_ID'],
            @$row['White_ID'],
            @$row['ToMove_ID'],
            @$row['GameType'],
            @$row['GamePlayers'],
            @$row['Ruleset'],
            @$row['Size'],
            @$row['Komi'],
            @$row['Handicap'],
            @$row['Status'],
            @$row['Moves'],
            @$row['Black_Prisoners'],
            @$row['White_Prisoners'],
            @$row['Last_X'],
            @$row['Last_Y'],
            @$row['Last_Move'],
            @$row['Flags'],
            @$row['Score'],
            @$row['Maintime'],
            @$row['Byotype'],
            @$row['Byotime'],
            @$row['Byoperiods'],
            @$row['Black_Maintime'],
            @$row['White_Maintime'],
            @$row['Black_Byotime'],
            @$row['White_Byotime'],
            @$row['Black_Byoperiods'],
            @$row['White_Byoperiods'],
            @$row['LastTicks'],
            @$row['ClockUsed'],
            @$row['TimeOutDate'],
            @$row['Rated'], // Y|N|Done
            ( @$row['StdHandicap'] == 'Y' ),
            ( @$row['WeekendClock'] == 'Y' ),
            @$row['Black_Start_Rating'],
            @$row['White_Start_Rating'],
            @$row['Black_End_Rating'],
            @$row['White_End_Rating'],
            @$row['Snapshot'],
            @$row['ShapeSnapshot'],
            @$row['GameSetup']
         );
      $g->grow = $row;
      return $g;
   }//new_from_row

   /*!
    * \brief Loads and returns Games-object for given games-ID.
    * \return NULL if nothing found; Games otherwise
    */
   public static function load_game( $gid, $return_row=false )
   {
      if ( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "Games:load_game.check_gid($gid)");

      $qsql = self::build_query_sql( $gid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Games:load_game.find_game($gid)",
         $qsql->get_select() );
      return ($row) ? ( $return_row ? $row : self::new_from_row($row) ) : NULL;
   }//load_game

   /*! \brief Updates Games.Flags with $set_flags and $clear_flags. */
   public static function update_game_flags( $dbgmsg, $gid, $set_flags=0, $clear_flags=0 )
   {
      if ( $set_flags || $clear_flags )
      {
         $qparts = array();
         if ( $set_flags )
            $qparts[] = " | ".(int)$set_flags;
         if ( $clear_flags )
            $qparts[] = " & ~".(int)$clear_flags;

         db_query( "Games:update_game_flags($gid)",
            "UPDATE Games SET Flags=Flags " . implode('', $qparts) . " WHERE ID=$gid LIMIT 1" );
      }
   }//update_game_flags

   /*! \brief Updates Games.Rated with $new_rated if game not finished yet and optionally still matching old-Rated state. */
   public static function update_game_rated( $dbgmsg, $gid, $new_rated, $old_rated=null )
   {
      $gid = (int)$gid;
      if ( $new_rated != 'Y' && $new_rated != 'N' )
         error('invalid_args', "Games:update_game_rated.check.new_rated($gid,$new_rated)");
      if ( !is_null($old_rated) && !preg_match( "/^(Y|N|Done)$/", $old_rated ) )
         error('invalid_args', "Games:update_game_rated.check.old_rated($gid,$old_rated)");

      $chk_qpart = (is_null($old_rated)) ? '' : "AND Rated='$old_rated'";

      // NOTE: keep Rated-state if game already finished or rated-calculation done
      db_query( "Games:update_game_rated($gid,$new_rated,$old_rated)",
         "UPDATE Games SET Rated='" . mysql_addslashes($new_rated) . "' " .
         "WHERE ID=$gid $chk_qpart AND Status<>'".GAME_STATUS_FINISHED."' AND Rated<>'Done' LIMIT 1" );
      return mysql_affected_rows();
   }//update_game_rated

   /*! \brief Detach given games from tournament by setting detached-game-flags (only for tournament-games). */
   public static function detach_games( $dbgmsg, $arr_gid )
   {
      if ( count($arr_gid) == 0 )
         return 0;
      $gids_str = implode(',', $arr_gid);

      db_query( "$dbgmsg.Games:detach_games.upd_games($gids_str)",
         "UPDATE Games SET Flags=Flags | ".GAMEFLAGS_TG_DETACHED." " .
         "WHERE ID IN ($gids_str) AND tid > 0" ); // only for tournament-games
      return mysql_affected_rows();
   }//detach_games

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   public static function getStatusText( $status=null )
   {
      static $ARR_GAMESTATUS = null; // status => text

      // lazy-init of texts
      if ( is_null($ARR_GAMESTATUS) )
      {
         $arr = array();
         $arr[GAME_STATUS_KOMI] = T_('Fair Komi Negotiation#G_status');
         $arr[GAME_STATUS_SETUP] = T_('MPG-Setup#G_status');
         $arr[GAME_STATUS_INVITED] = T_('Invited#G_status');
         $arr[GAME_STATUS_PLAY] = T_('Play#G_status');
         $arr[GAME_STATUS_PASS] = T_('Pass#G_status');
         $arr[GAME_STATUS_SCORE] = T_('Score#G_status');
         $arr[GAME_STATUS_SCORE2] = T_('Score#G_status'); //=Score-text
         $arr[GAME_STATUS_FINISHED] = T_('Finished#G_status');
         $ARR_GAMESTATUS = $arr;
      }

      if ( is_null($status) )
         return $ARR_GAMESTATUS;
      if ( !isset($ARR_GAMESTATUS[$status]) )
         error('invalid_args', "Games:getStatusText($status)");
      return $ARR_GAMESTATUS[$status];
   }//getStatusText

} // end of 'Games'
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

// lazy-init in Games::get..Text()-funcs
global $ARR_GLOBALS_GAMES; //PHP5
$ARR_GLOBALS_GAMES = array();

global $ENTITY_GAMES; //PHP5
$ENTITY_GAMES = new Entity( 'Games',
      FTYPE_PKEY, 'ID',
      FTYPE_AUTO, 'ID',
      FTYPE_INT,  'ID', 'tid', 'mid', 'DoubleGame_ID', 'Black_ID', 'White_ID', 'ToMove_ID', 'Size',
                  'Handicap', 'Moves', 'Black_Prisoners', 'White_Prisoners', 'Last_X', 'Last_Y',
                  'Maintime', 'Byotime', 'Byoperiods', 'Black_Maintime', 'White_Maintime',
                  'Black_Byotime', 'White_Byotime', 'Black_Byoperiods', 'White_Byoperiods', 'LastTicks',
                  'ClockUsed', 'TimeOutDate',
      FTYPE_FLOAT, 'Komi', 'Score', 'Black_Start_Rating', 'White_Start_Rating', 'Black_End_Rating',
                   'White_End_Rating',
      FTYPE_TEXT, 'Last_Move',
      FTYPE_DATE, 'Starttime', 'Lastchanged',
      FTYPE_ENUM, 'Status', 'Flags', 'Byotype', 'Rated', 'StdHandicap', 'WeekendClock'
   );

class Games
{
   var $ID;
   var $tid;
   var $Starttime;
   var $Lastchanged;
   var $mid;
   var $DoubleGame_ID;
   var $Black_ID;
   var $White_ID;
   var $ToMove_ID;
   var $Size;
   var $Komi;
   var $Handicap;
   var $Status;
   var $Moves;
   var $Black_Prisoners;
   var $White_Prisoners;
   var $Last_X;
   var $Last_Y;
   var $Last_Move;
   var $Flags;
   var $Score;
   var $Maintime;
   var $Byotype;
   var $Byotime;
   var $Byoperiods;
   var $Black_Maintime;
   var $White_Maintime;
   var $Black_Byotime;
   var $White_Byotime;
   var $Black_Byoperiods;
   var $White_Byoperiods;
   var $LastTicks;
   var $ClockUsed;
   var $TimeOutDate;
   var $Rated;
   var $StdHandicap;
   var $WeekendClock;
   var $Black_Start_Rating;
   var $White_Start_Rating;
   var $Black_End_Rating;
   var $White_End_Rating;

   /*! \brief Constructs Games-object with specified arguments. */
   function Games( $id=0, $tid=0, $starttime=0, $lastchanged=0, $mid=0, $double_gid=0,
                   $black_id=0, $white_id=0, $tomove_id=0, $size=19, $komi=6.5, $handicap=0,
                   $status=GAME_STATUS_INVITED, $moves=0, $black_prisoners=0, $white_prisoners=0,
                   $last_x=-1, $last_y=-1, $last_move='', $flags=0, $score=0.0, $maintime=0,
                   $byotype=BYOTYPE_JAPANESE, $byotime=0, $byoperiods=0, $black_maintime=0,
                   $white_maintime=0, $black_byotime=0, $white_byotime=0, $black_byoperiods=0,
                   $white_byoperiods=0, $lastticks=0, $clockused=0, $timeoutdate=0, $rated='N',
                   $stdhandicap=true, $weekendclock=true, $black_start_rating=NO_RATING,
                   $white_start_rating=NO_RATING, $black_end_rating=NO_RATING,
                   $white_end_rating=NO_RATING )
   {
      $this->ID = (int)$id;
      $this->tid = (int)$tid;
      $this->Starttime = (int)$starttime;
      $this->Lastchanged = (int)$lastchanged;
      $this->mid = (int)$mid;
      $this->DoubleGame_ID = (int)$double_gid;
      $this->Black_ID = (int)$black_id;
      $this->White_ID = (int)$white_id;
      $this->ToMove_ID = (int)$tomove_id;
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
   }

   function setStatus( $status )
   {
      if( !preg_match( "/^(".CHECK_GAME_STATUS.")$/", $status ) )
         error('invalid_args', "Games.setStatus($status)");
      $this->Status = $status;
   }

   function setByotype( $byotype )
   {
      if( !preg_match( "/^".REGEX_BYOTYPES."$/", $byotype ) )
         error('invalid_args', "Games.setByotype($byotype)");
      $this->Byotype = $byotype;
   }

   function setRated( $rated )
   {
      if( !preg_match( "/^(Y|N|Done)$/", $rated ) )
         error('invalid_args', "Games.setRated($rated)");
      $this->Rated = $rated;
   }

   function is_status_running()
   {
      return ($this->Status == GAME_STATUS_PLAY || $this->Status == GAME_STATUS_PASS
         || $this->Status == GAME_STATUS_SCORE || $this->Status == GAME_STATUS_SCORE2 );
   }

   function to_string()
   {
      return print_r($this, true);
   }

   /*! \brief Inserts or updates Games-entry in database. */
   function persist()
   {
      if( $this->ID > 0 )
         $success = $this->update();
      else
         $success = $this->insert();
      return $success;
   }

   function insert()
   {
      $this->Starttime = $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      $result = $entityData->insert( "Games.insert(%s)" );
      if( $result )
         $this->ID = mysql_insert_id();
      return $result;
   }

   function update( $upd_lastchanged=true )
   {
      if( $upd_lastchanged )
         $this->Lastchanged = $GLOBALS['NOW'];

      $entityData = $this->fillEntityData();
      return $entityData->update( "Games.update(%s)" );
   }

   function delete()
   {
      $entityData = $this->fillEntityData();
      return $entityData->delete( "Games.delete(%s)" );
   }

   function fillEntityData( &$data=null )
   {
      if( is_null($data) )
         $data = $GLOBALS['ENTITY_GAMES']->newEntityData();
      $data->set_value( 'ID', $this->ID );
      $data->set_value( 'tid', $this->tid );
      $data->set_value( 'Starttime', $this->Starttime );
      $data->set_value( 'Lastchanged', $this->Lastchanged );
      $data->set_value( 'mid', $this->mid );
      $data->set_value( 'DoubleGame_ID', $this->DoubleGame_ID );
      $data->set_value( 'Black_ID', $this->Black_ID );
      $data->set_value( 'White_ID', $this->White_ID );
      $data->set_value( 'ToMove_ID', $this->ToMove_ID );
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
      $data->set_value( 'Flags', Games::buildFlags($this->Flags) );
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
      return $data;
   }


   // ------------ static functions ----------------------------

   function parseFlags( $flags_str )
   {
      static $map_flags = array(
         'KO'        => GAMEFLAGS_KO,
         'HIDDENMSG' => GAMEFLAGS_HIDDEN_MSG,
      );

      $flags = 0;
      if( $flags_str )
      {
         $arr = explode(',', $flags_str );
         foreach( $arr as $flagkey )
            $flags |= $map_flags[strtoupper($flagkey)];
      }
      return $flags;
   }

   function buildFlags( $flags )
   {
      $arr = array();
      if( $flags & GAMEFLAGS_KO )
         $arr[] = 'Ko';
      if( $flags & GAMEFLAGS_HIDDEN_MSG )
         $arr[] = 'HiddenMsg';
      return implode(',', $arr);
   }

   /*! \brief Returns db-fields to be used for query of Games-objects for given game-id. */
   function build_query_sql( $gid=0 )
   {
      $qsql = $GLOBALS['ENTITY_GAMES']->newQuerySQL('G');
      if( $gid > 0 )
         $qsql->add_part( SQLP_WHERE, "G.ID='$gid'" );
      return $qsql;
   }

   /*! \brief Returns Games-object created from specified (db-)row. */
   function new_from_row( $row )
   {
      $g = new Games(
            // from Games
            @$row['ID'],
            @$row['tid'],
            @$row['Starttime'],
            @$row['Lastchanged'],
            @$row['mid'],
            @$row['DoubleGame_ID'],
            @$row['Black_ID'],
            @$row['White_ID'],
            @$row['ToMove_ID'],
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
            Games::parseFlags( @$row['Flags'] ),
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
            @$row['White_End_Rating']
         );
      return $g;
   }

   /*!
    * \brief Loads and returns Games-object for given tournament-ID, challenger- and defender-uid.
    * \return NULL if nothing found; Games otherwise
    */
   function load_game( $gid )
   {
      if( !is_numeric($gid) || $gid <= 0 )
         error('invalid_args', "Games.load_game.check_gid($gid)");

      $qsql = Games::build_query_sql( $gid );
      $qsql->add_part( SQLP_LIMIT, '1' );

      $row = mysql_single_fetch( "Games::load_game.find_game($gid)",
         $qsql->get_select() );
      return ($row) ? Games::new_from_row($row) : NULL;
   }

   /*! \brief Returns status-text or all status-texts (if arg=null). */
   function getStatusText( $status=null )
   {
      global $ARR_GLOBALS_GAMES;

      // lazy-init of texts
      $key = 'STATUS';
      if( !isset($ARR_GLOBALS_GAMES[$key]) )
      {
         $arr = array();
         $arr[GAME_STATUS_INVITED] = T_('Invited#G_status');
         $arr[GAME_STATUS_PLAY] = T_('Play#G_status');
         $arr[GAME_STATUS_PASS] = T_('Pass#G_status');
         $arr[GAME_STATUS_SCORE] = T_('Score#G_status');
         $arr[GAME_STATUS_SCORE2] = T_('Score#G_status'); //=Score-text
         $arr[GAME_STATUS_FINISHED] = T_('Finished#G_status');
         $ARR_GLOBALS_GAMES[$key] = $arr;
      }

      if( is_null($status) )
         return $ARR_GLOBALS_GAMES[$key];
      if( !isset($ARR_GLOBALS_GAMES[$key][$status]) )
         error('invalid_args', "Games.getStatusText($status)");
      return $ARR_GLOBALS_GAMES[$key][$status];
   }

} // end of 'Games'
?>

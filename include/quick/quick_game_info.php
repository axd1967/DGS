<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once 'include/quick/quick_handler.php';
require_once 'include/std_functions.php';
require_once 'include/classlib_user.php';
require_once 'include/time_functions.php';
require_once 'include/rating.php';
require_once 'include/game_functions.php';


 /*!
  * \file quick_game_info.php
  *
  * \brief QuickHandler for returning info for game-object.
  * \see specs/quick_suite.txt (3a)
  */

// see specs/quick_suite.txt (3a)
define('GAMEINFO_OPT_GID', 'gid');

define('GAMECMD_GET_NOTES', 'get_notes');
define('GAMEINFO_COMMANDS', 'info|get_notes');


 /*!
  * \class QuickHandlerGameInfo
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerGameInfo extends QuickHandler
{
   var $gid;

   var $game_row;
   var $gamenotes;
   var $user_rows;

   function QuickHandlerGameInfo( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->gid = 0;

      $this->game_row = null;
      $this->gamenotes = null;
      $this->user_rows = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAMEINFO_COMMANDS, $cmd);
   }

   function parseURL()
   {
      parent::checkArgsUnknown(GAMEINFO_OPT_GID);
      $this->gid = (int)get_request_arg(GAMEINFO_OPT_GID);
   }

   function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGameInfo.prepare($uid,{$this->gid})";
      $this->checkCommand( $dbgmsg, GAMEINFO_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check gid
      QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_GID, $this->gid );
      if( !is_numeric($this->gid) || $this->gid <= 0 )
         error('unknown_game', "QuickHandlerGameInfo.check({$this->gid})");
      $gid = $this->gid;

      // prepare command: info, get_notes

      if( $cmd == QCMD_INFO )
      {
         $this->game_row = mysql_single_fetch( "QuickHandlerGameInfo.prepare.find_game3($gid)",
                    "SELECT G.*, "
                    ."G.Flags+0 AS X_Flags "
                    .",UNIX_TIMESTAMP(G.Starttime) AS X_Starttime "
                    .",UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged "
                    .",COALESCE(Clock.Ticks,0) AS X_Ticks "
                    ."FROM Games AS G LEFT JOIN Clock ON Clock.ID=G.ClockUsed "
                    ."WHERE G.ID=$gid LIMIT 1" )
               or error('unknown_game', "QuickHandlerGameInfo.prepare.find_game4($gid)");

         if( $this->is_with_option(QWITH_USER_ID) )
            $this->user_rows = User::load_quick_userinfo( array(
               (int)$this->game_row['Black_ID'], (int)$this->game_row['White_ID'] ));
      }
      elseif( $cmd == GAMECMD_GET_NOTES )
      {
         $gn_row = mysql_single_fetch( "QuickHandlerGameInfo.prepare.find_gamenotes($gid,$uid)",
                  "SELECT Notes FROM GamesNotes WHERE gid={$gid} AND uid='$uid' LIMIT 1" );
         if( is_array($gn_row) )
            $this->gamenotes = @$gn_row['Notes'];
      }
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $cmd = $this->quick_object->cmd;
      if( $cmd == QCMD_INFO )
         $this->process_cmd_info();
      elseif( $cmd == GAMECMD_GET_NOTES )
         $this->addResultKey( 'notes', (is_null($this->gamenotes) ? "" : $this->gamenotes) );
   }

   function process_cmd_info()
   {
      $row = $this->game_row;
      $color = ($row['ToMove_ID'] == $row['Black_ID']) ? BLACK : WHITE;

      $this->addResultKey( 'id', (int)$row['ID'] );
      $this->addResultKey( 'double_id', (int)$row['DoubleGame_ID'] );
      $this->addResultKey( 'tournament_id', (int)$row['tid'] );
      $this->addResultKey( 'game_action',
         GameHelper::get_quick_game_action($row['Status'], (int)$row['Handicap'], (int)$row['Moves'],
            new FairKomiNegotiation( GameSetup::new_from_game_setup($row['GameSetup']), $row ) ) );
      $this->addResultKey( 'status', strtoupper($row['Status']) );
      $this->addResultKey( 'flags', QuickHandlerGameInfo::convertGameFlags($row['X_Flags']) );
      $this->addResultKey( 'score', ( $row['Status'] == GAME_STATUS_FINISHED )
            ? score2text($row['Score'], /*verbose*/false, /*engl*/true, /*quick*/true)
            : "" );
      $this->addResultKey( 'rated', ($row['Rated'] == 'N') ? 0 : 1 );
      $this->addResultKey( 'game_type', GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true) );
      $this->addResultKey( 'ruleset', strtoupper($row['Ruleset']) );
      $this->addResultKey( 'size', (int)$row['Size'] );
      $this->addResultKey( 'komi', (float)$row['Komi'] );
      $this->addResultKey( 'handicap', (int)$row['Handicap'] );
      $this->addResultKey( 'handicap_mode', ($row['StdHandicap'] == 'Y') ? 'STD' : 'FREE' );

      $this->addResultKey( 'shape_id', (int)$row['ShapeID'] );
      $this->addResultKey( 'shape_snapshot', $row['ShapeSnapshot'] );

      $this->addResultKey( 'time_started', QuickHandler::formatDate(@$row['X_Starttime']) );
      $this->addResultKey( 'time_lastmove', QuickHandler::formatDate(@$row['X_Lastchanged']) );
      $this->addResultKey( 'time_weekend_clock', ($row['WeekendClock'] == 'Y') ? 1 : 0 );
      $this->addResultKey( 'time_mode', strtoupper($row['Byotype']) );
      $this->addResultKey( 'time_limit',
         TimeFormat::echo_time_limit(
            $row['Maintime'], $row['Byotype'], $row['Byotime'], $row['Byoperiods'],
            TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE) );

      $this->addResultKey( 'move_id', (int)$row['Moves'] );
      $this->addResultKey( 'move_color', ($color == BLACK) ? 'B' : 'W' );
      $this->addResultKey( 'move_uid', (int)$row['ToMove_ID'] );
      $this->addResultKey( 'move_last', strtolower($row['Last_Move']) );
      $this->addResultKey( 'move_ko', ($row['X_Flags'] & GAMEFLAGS_KO) ? 1 : 0 );

      foreach( array( BLACK, WHITE ) as $col )
      {
         $icol = ($col == BLACK) ? 'Black' : 'White';
         $prefix = strtolower($icol);
         $uid = (int)$row[$icol.'_ID'];
         $time_remaining = build_time_remaining( $row, $col,
               /*is_to_move*/ ( $uid == $row['ToMove_ID'] ),
               TIMEFMT_QUICK|TIMEFMT_ADDTYPE|TIMEFMT_ZERO );

         $this->addResultKey( $prefix.'_user', $this->build_obj_user($uid, $this->user_rows, 'rating') );
         $this->addResultKey( $prefix.'_gameinfo', array(
            'prisoners'        => (int)$row[$icol.'_Prisoners'],
            'remtime'          => $time_remaining['text'],
            'rating_start'     => echo_rating($row[$icol.'_Start_Rating'], /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1),
            'rating_start_elo' => echo_rating_elo($row[$icol.'_Start_Rating']),
            'rating_end'       => echo_rating($row[$icol.'_End_Rating'], /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1),
            'rating_end_elo'   => echo_rating_elo($row[$icol.'_End_Rating']),
         ));
      }
   }//process_cmd_info


   // ------------ static functions ----------------------------

   function convertGameFlags( $flags )
   {
      $out = array();
      if( $flags & GAMEFLAGS_HIDDEN_MSG )
         $out[] = 'HIDDENMSG';
      if( $flags & GAMEFLAGS_ADMIN_RESULT )
         $out[] = 'ADMRESULT';
      return implode(',', $out);
   }

} // end of 'QuickHandlerGameInfo'

?>

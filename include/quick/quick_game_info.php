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

define('GAMEINFO_COMMANDS', 'info');


 /*!
  * \class QuickHandlerGameInfo
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerGameInfo extends QuickHandler
{
   private $gid = 0;

   private $glc = null; // GameListControl
   private $game_row = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAMEINFO_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown(GAMEINFO_OPT_GID);
      $this->gid = (int)get_request_arg(GAMEINFO_OPT_GID);
   }

   public function prepare()
   {
      global $player_row;
      $uid = (int)@$player_row['ID'];

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGameInfo.prepare($uid,{$this->gid})";
      $this->checkCommand( $dbgmsg, GAMEINFO_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check gid
      QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_GID, $this->gid );
      if ( !is_numeric($this->gid) || $this->gid <= 0 )
         error('unknown_game', "$dbgmsg.check.gid");
      $gid = $this->gid;

      // prepare command: info

      if ( $cmd == QCMD_INFO )
      {
         $glc = new GameListControl(/*quick*/true);
         $glc->setView( GAMEVIEW_INFO, 'all' );
         $this->glc = $glc;

         $qsql = $glc->build_games_query( $this->is_with_option(QWITH_RATINGDIFF), /*remtime*/true, $this->is_with_option(QWITH_PRIO) );
         $qsql->add_part( SQLP_WHERE, "G.ID=$gid" );
         $qsql->add_part( SQLP_LIMIT, 1 );

         if ( $this->is_with_option(QWITH_NOTES) )
            GameHelper::extend_query_with_game_notes( $qsql, $uid, 'G' );

         $this->game_row = mysql_single_fetch( "QuickHandlerGameInfo.prepare.find_game3($gid)", $qsql->get_select() )
               or error('unknown_game', "QuickHandlerGameInfo.prepare.find_game4($gid)");
      }
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      if ( $cmd == QCMD_INFO )
         self::fill_game_info($this, $this->glc, $this->quick_object->result, $this->game_row);
   }


   // ------------ static functions ----------------------------

   private static function convertGameFlags( $flags )
   {
      $out = array();
      if ( $flags & GAMEFLAGS_HIDDEN_MSG )
         $out[] = 'HIDDENMSG';
      if ( $flags & GAMEFLAGS_ADMIN_RESULT )
         $out[] = 'ADMRESULT';
      if ( $flags & GAMEFLAGS_TG_DETACHED )
         $out[] = 'TGDETACHED';
      if ( $flags & GAMEFLAGS_ATTACHED_SGF )
         $out[] = 'ATTACHEDSGF';
      if ( $flags & GAMEFLAGS_NO_RESULT )
         $out[] = 'NO_RESULT';
      return implode(',', $out);
   }//convertGameFlags

   public static function fill_game_info( $quick_handler, $glc, &$out, $row )
   {
      //$FU = $glc->is_finished();
      //$all = $glc->is_all();

      // init init fields
      $color = ($row['ToMove_ID'] == $row['Black_ID']) ? BLACK : WHITE;
      $is_my_game = ( $row['Black_ID'] == $glc->my_id ) || ( $row['White_ID'] == $glc->my_id );
      $row['Blackhandle'] = @$row['BlackHandle']; // for FK-info
      $row['Whitehandle'] = @$row['WhiteHandle']; // for FK-info
      $game_setup = GameSetup::new_from_game_setup($row['GameSetup']);
      $game_finished = ($row['Status'] == GAME_STATUS_FINISHED);
      $game_started = isStartedGame($row['Status']);


      // output for views: info (INFO), status (ST), observe_mine (OU), observe_all (OA), running-user (RU), finished-user (FU)

      $out['id'] = (int)$row['ID'];
      $out['double_id'] = (int)$row['DoubleGame_ID'];
      $out['tournament_id'] = (int)$row['tid'];
      $out['game_action'] =
         GameHelper::get_quick_game_action($row['Status'], (int)$row['Handicap'], (int)$row['Moves'],
            new FairKomiNegotiation( $game_setup, $row ) );
      $out['status'] = strtoupper($row['Status']);
      $out['flags'] = self::convertGameFlags($row['Flags']);
      $out['score'] = ( $row['Status'] == GAME_STATUS_FINISHED )
            ? score2text( $row['Score'], $row['Flags'], /*verbose*/false, /*engl*/true, /*quick*/true )
            : "";

      $out['game_type'] = GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true);
      $out['rated'] = ($row['Rated'] == 'N') ? 0 : 1;
      $out['ruleset'] = strtoupper($row['Ruleset']);
      $out['size'] = (int)$row['Size'];
      $out['komi'] = (float)$row['Komi'];
      $out['jigo_mode'] = $game_setup->JigoMode;
      $out['handicap'] = (int)$row['Handicap'];
      $out['handicap_mode'] = ($row['StdHandicap'] == 'Y') ? 'STD' : 'FREE';
      $out['shape_id'] = (int)$row['ShapeID'];

      $out['time_started'] = QuickHandler::formatDate(@$row['X_Starttime']);
      $out['time_lastmove'] = QuickHandler::formatDate(@$row['X_Lastchanged']);
      $out['time_weekend_clock'] = ($row['WeekendClock'] == 'Y') ? 1 : 0;
      $out['time_mode'] = strtoupper($row['Byotype']);
      $out['time_limit'] =
         TimeFormat::echo_time_limit(
            $row['Maintime'], $row['Byotype'], $row['Byotime'], $row['Byoperiods'],
            TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE);

      $out['my_id'] = $glc->my_id;
      $out['move_id'] = (int)$row['Moves'];
      $out['move_count'] = (int)$row['Moves'];
      if ( !$game_finished )
      {
         $out['move_color'] = ($color == BLACK) ? 'B' : 'W';
         $out['move_uid'] = (int)$row['ToMove_ID'];
         $out['move_opp'] = ($color == BLACK) ? (int)$row['White_ID'] : (int)$row['Black_ID'];
         $out['move_last'] = strtolower($row['Last_Move']);
         //$out['move_ko'] = ($row['Flags'] & GAMEFLAGS_KO) ? 1 : 0;

         $out['prio'] = (int)@$row['X_Priority'];
         if ( $quick_handler->is_with_option(QWITH_NOTES) )
            $out['notes'] = @$row['X_Note'];
      }

      foreach ( array( BLACK, WHITE ) as $col )
      {
         $icol = ($col == BLACK) ? 'Black' : 'White';
         $prefix = strtolower($icol);
         $uid = (int)$row[$icol.'_ID'];
         if ( $game_started && $is_my_game )
         {
            $time_remaining = build_time_remaining( $row, $col,
                  /*is_to_move*/ ( $uid == $row['ToMove_ID'] ),
                  TIMEFMT_QUICK|TIMEFMT_ADDTYPE|TIMEFMT_ZERO );
            $remtime = $time_remaining['text'];
         }
         else
            $remtime = '';

         // user-info
         $out[$prefix.'_user'] = $quick_handler->build_obj_user($uid, $row, $prefix, 'country,rating,lastacc');

         // game-info
         $ginfo = array();
         $ginfo['prisoners'] = (int)$row[$icol.'_Prisoners'];
         $ginfo['remtime'] = ($remtime) ? $remtime : '';
         $ginfo['rating_start'] = echo_rating($row[$icol.'_Start_Rating'], /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1);
         $ginfo['rating_start_elo'] = echo_rating_elo($row[$icol.'_Start_Rating'], false, '');

         if ( $game_finished )
         {
            $ginfo['rating_end']     = echo_rating($row[$icol.'_End_Rating'], /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1);
            $ginfo['rating_end_elo'] = echo_rating_elo($row[$icol.'_End_Rating'], false, '');

            if ( $quick_handler->is_with_option(QWITH_RATINGDIFF) )
            {
               if ( isset($row[$prefix.'Diff']) )
               {
                  $rat_diff = $row[$prefix.'Diff'];
                  $ginfo['rating_diff'] = ( $rat_diff > 0 ? '+' : '' ) . sprintf( "%0.2f", $rat_diff / 100 );
               }
               else
                  $ginfo['rating_diff'] = '';
            }
         }

         $out[$prefix.'_gameinfo'] = $ginfo;
      }

      if ( $glc->is_observe_all() )
      {
         $out['obs_count'] = (int)@$row['X_ObsCount'];
         $out['obs_mine'] = ( @$row['X_MeObserved'] == 'Y' ) ? 1 : 0;
      }

      return $out;
   }//fill_game_info

} // end of 'QuickHandlerGameInfo'

?>

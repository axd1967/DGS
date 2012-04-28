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
require_once 'include/std_classes.php';
require_once 'include/classlib_user.php';
require_once 'include/time_functions.php';
require_once 'include/rating.php';
require_once 'include/classlib_game.php';


 /*!
  * \file quick_game_list.php
  *
  * \brief QuickHandler for returning list of game-objects.
  * \see specs/quick_suite.txt (3a)
  */

// see specs/quick_suite.txt (3a)
define('GAMELIST_COMMANDS', 'list');

define('GAMELIST_OPT_VIEW', 'view');
define('GAMELIST_OPT_UID', 'uid');
define('QGAMELIST_OPTIONS', 'view|uid');

define('GAMELIST_OPTVAL_VIEW_STATUS', 'status');
define('GAMELIST_OPTVAL_VIEW_RUNNING', 'running');
define('GAMELIST_OPTVAL_VIEW_FINISHED', 'finished');
define('GAMELIST_OPTVAL_VIEW_OBSERVING', 'observing');
define('GAMELIST_OPTVAL_VIEW_OBSERVED', 'observed');
define('CHECK_GAMELIST_OPTVAL_VIEW', 'status|running|finished|observing|observed');


 /*!
  * \class QuickHandlerGameList
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerGameList extends QuickHandler
{
   var $view;
   var $opt_uid; // original option: int | 'mine' | 'all'
   var $uid; // 0 | int>0

   var $games;

   function QuickHandlerGameList( $quick_object )
   {
      parent::QuickHandler( $quick_object );
      $this->view = '';
      $this->opt_uid = '';
      $this->uid = 0;

      $this->games = null;
   }


   // ---------- Interface ----------------------------------------

   function canHandle( $obj, $cmd ) // static
   {
      return false; // TODO: not implemented yet
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAMELIST_COMMANDS, $cmd);
   }

   function parseURL()
   {
      parent::checkArgsUnknown(QGAMELIST_OPTIONS);
      $this->view = get_request_arg(GAMELIST_OPT_VIEW);
      $this->opt_uid = get_request_arg(GAMELIST_OPT_UID);
   }

   function prepare()
   {
      global $player_row;
      $my_id = $player_row['ID'];

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGameList.prepare()";
      $this->checkCommand( $dbgmsg, GAMELIST_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check args
      QuickHandler::checkArgMandatory( $dbgmsg, GAMELIST_OPT_VIEW, $this->view );
      if( !QuickHandler::matchRegex(CHECK_GAMELIST_OPTVAL_VIEW, $this->view) )
         error('invalid_args', "$dbgmsg.check.opt.view({$this->view})");
      $view = $this->view;

      if( is_numeric($this->opt_uid) && $this->opt_uid > 0 )
         $this->uid = (int)$this->opt_uid;
      elseif( (string)$this->opt_uid == '' || $this->opt_uid == 'mine' )
         $this->uid = $my_id;
      //elseif( $this->opt_uid == 'all' ) //TODO see below
         //$this->uid = 'all';
      else
         error('invalid_args', "$dbgmsg.check.opt.uid({$this->opt_uid})");
      $uid = $this->uid; // int>0 (user-id) | 'all'

      $dbgmsg = "QuickHandlerGameList.prepare($view,$uid)";

      if( !is_numeric($uid) )
         error('invalid_args', "$dbgmsg.check.view.uid");

      //TODO handle offset + limit on lists before implementing uid=all; status=no-limit
      //TODO handle uid=all

      // prepare command: list

      $qsql = new QuerySQL(
         SQLP_FIELDS,
            'G.*',
            'G.Flags+0 AS X_Flags',
            'UNIX_TIMESTAMP(G.Starttime) AS X_Starttime',
            'UNIX_TIMESTAMP(G.Lastchanged) AS X_Lastchanged',
            'COALESCE(Clock.Ticks,0) AS X_Ticks' );

      if( $view == GAMELIST_OPTVAL_VIEW_STATUS )
      {
         $qsql->add_part( SQLP_FROM, 'Games AS G', 'LEFT JOIN Clock ON Clock.ID=G.ClockUsed' );
         $qsql->add_part( SQLP_WHERE, "G.ToMove_ID=$uid", 'Status' . IS_STARTED_GAME );

         // handle next-game-order
         $next_game_order = ($uid == $my_id)
            ? $player_row['NextGameOrder']
            : NextGameOrder::load_user_next_game_order( $uid );
         if( $next_game_order == NGO_PRIO )
         {
            $qsql->add_part( SQLP_FIELDS, 'COALESCE(GP.Priority,0) AS X_Priority' );
            $qsql->add_part( SQLP_FROM, "LEFT JOIN GamesPriority AS GP ON GP.gid=G.ID AND GP.uid=$uid" );
         }
         $qsql->add_part( SQLP_ORDER,
            NextGameOrder::get_next_game_order( $next_game_order, 'G', false ) );
      }
      elseif( $view == GAMELIST_OPTVAL_VIEW_RUNNING )
      {
         $qsql->add_part( SQLP_FROM, 'Games AS G', 'LEFT JOIN Clock ON Clock.ID=G.ClockUsed' );
         $qsql->add_part( SQLP_WHERE, 'G.Status' . IS_STARTED_GAME );
         $qsql->add_part( SQLP_UNION_WHERE, "G.White_ID=$uid", "G.Black_ID=$uid" );
         $qsql->useUnionAll();
      }
      elseif( $view == GAMELIST_OPTVAL_VIEW_FINISHED )
      {
         $qsql->add_part( SQLP_FROM, 'Games AS G', 'LEFT JOIN Clock ON Clock.ID=G.ClockUsed' );
         $qsql->add_part( SQLP_WHERE, "G.Status='".GAME_STATUS_FINISHED."'" );
         $qsql->add_part( SQLP_UNION_WHERE, "G.White_ID=$uid", "G.Black_ID=$uid" );
         $qsql->useUnionAll();
      }
      elseif( $view == GAMELIST_OPTVAL_VIEW_OBSERVING || $view == GAMELIST_OPTVAL_VIEW_OBSERVED )
      {
         if( (string)$this->opt_uid != '' && $uid != $my_id )
            error('invalid_args', "$dbgmsg.check.view.uid.only_mine");

         $qsql->add_part( SQLP_FROM,
               'Observers AS Obs',
               'INNER JOIN Games AS G ON G.ID=Obs.gid',
               'LEFT JOIN Clock ON Clock.ID=G.ClockUsed' );

         if( $view == GAMELIST_OPTVAL_VIEW_OBSERVING )
            $qsql->add_part( SQLP_WHERE, "Obs.uid=$my_id" );
         else
         {
            $qsql->add_part( SQLP_FIELDS, 'COUNT(Obs.uid) AS X_ObsCount' );
            $qsql->add_part( SQLP_GROUP, 'Obs.gid' );
         }
      }

      if( !$qsql->has_part(SQLP_ORDER) )
         $qsql->add_part( SQLP_ORDER, 'Lastchanged ASC', 'ID' );

      // add user-info
      if( $this->is_with_option(QWITH_USER_ID) )
      {
         $qsql->add_part( SQLP_FIELDS,
            'black.Handle AS Black_Handle',
            'black.Name AS Black_Name',
            'white.Handle AS White_Handle',
            'white.Name AS White_Name' );
         $qsql->add_part( SQLP_FROM,
            'INNER JOIN Players AS black ON black.ID=G.Black_ID',
            'INNER JOIN Players AS white ON white.ID=G.White_ID' );
      }


      // load games
      $arr = array();
      $result = db_query( "$dbgmsg.find_games", $qsql->get_select() );
      while( $row = mysql_fetch_assoc($result) )
         $arr[] = $row;
      mysql_free_result($result);
      $this->games = $arr;
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   function process()
   {
      $out = array();
      if( is_array($this->games) )
      {
         foreach( $this->games as $game_row )
            $out[] = $this->build_game_info($game_row);
      }

      //TODO set list-order
      $this->add_list( QOBJ_GAME, $out );
   }//process

   function build_game_info( $row )
   {
      $out = array();
      $color = ($row['ToMove_ID'] == $row['Black_ID']) ? BLACK : WHITE;

      $out['id'] = (int)$row['ID'];
      //$out['double_id', (int)$row['DoubleGame_ID'];
      $out['tournament_id'] = (int)$row['tid'];
      $out['game_type'] = GameTexts::format_game_type($row['GameType'], $row['GamePlayers'], true);
      $out['status'] = strtoupper($row['Status']);
      //$out['flags'] = QuickHandlerGameInfo::convertGameFlags($row['X_Flags']);
      //$out['score'] = ( $row['Status'] == GAME_STATUS_FINISHED )
            //? score2text($row['Score'], /*verbose*/false, /*engl*/true, /*quick*/true)
            //: "";
      //$out['rated'] = ($row['Rated'] == 'N') ? 0 : 1;
      //$out['ruleset'] = strtoupper($row['Ruleset']);
      //$out['size'] = (int)$row['Size'];
      //$out['komi'] = (float)$row['Komi'];
      //$out['handicap'] = (int)$row['Handicap'];
      //$out['handicap_mode'] = ($row['StdHandicap'] == 'Y') ? 'STD' : 'FREE';

      //$out['time_started'] = QuickHandler::formatDate(@$row['X_Starttime']);
      $out['time_lastmove'] = QuickHandler::formatDate(@$row['X_Lastchanged']);
      //$out['time_weekend_clock'] = ($row['WeekendClock'] == 'Y') ? 1 : 0;
      //$out['time_mode'] = strtoupper($row['Byotype']);
      //$out['time_limit'] =
         //TimeFormat::echo_time_limit(
            //$row['Maintime'], $row['Byotype'], $row['Byotime'], $row['Byoperiods'],
            //TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT|TIMEFMT_ADDTYPE);

      $out['move_id'] = (int)$row['Moves'];
      $out['move_color'] = ($color == BLACK) ? 'B' : 'W';
      //$out['move_uid'] = (int)$row['ToMove_ID'];
      //$out['move_last'] = strtolower($row['Last_Move']);
      //$out['move_ko'] = ($row['X_Flags'] & GAMEFLAGS_KO) ? 1 : 0;

      foreach( array( BLACK, WHITE ) as $col )
      {
         $icol = ($col == BLACK) ? 'Black' : 'White';
         $prefix = strtolower($icol);
         $uid = (int)$row[$icol.'_ID'];
         $time_remaining = build_time_remaining( $row, $col,
               /*is_to_move*/ ( $uid == $row['ToMove_ID'] ),
               TIMEFMT_QUICK|TIMEFMT_ADDTYPE|TIMEFMT_ADDEXTRA|TIMEFMT_ZERO );

         $out[$prefix.'_user'] = $this->build_obj_user2($uid, $row, $icol.'_');
         $out[$prefix.'_gameinfo'] = array(
            //'prisoners'        => (int)$row[$icol.'_Prisoners'],
            'remtime'          => $time_remaining['text'],
            //'rating_start'     => echo_rating($row[$icol.'_Start_Rating'], /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1),
            //'rating_start_elo' => echo_rating_elo($row[$icol.'_Start_Rating']),
            //'rating_end'       => echo_rating($row[$icol.'_End_Rating'], /*perc*/1, /*uid*/0, /*engl*/true, /*short*/1),
            //'rating_end_elo'   => echo_rating_elo($row[$icol.'_End_Rating']),
         );
      }
      return $out;
   }//build_game_info

} // end of 'QuickHandlerGameList'

?>

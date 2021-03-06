<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /*!
  * \file quick_user.php
  *
  * \brief QuickHandler for user-object.
  * \see specs/quick_suite.txt (3b)
  */

// see specs/quick_suite.txt (3b)
// uid|user=<USER_ID|HANDLE>
define('USEROPT_UID',  'uid');
define('USEROPT_USER', 'user');
define('QUSER_OPTIONS', 'uid|user');

define('USERCMD_INFO', 'info');
define('USER_COMMANDS', 'info');


 /*!
  * \class QuickHandlerUser
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerUser extends QuickHandler
{
   private $uid = 0;
   private $handle = '';

   private $user = null;
   private $game_stats = null;
   private $diff_opps = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_USER ) && QuickHandler::matchRegex(USER_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown(QUSER_OPTIONS);
      $this->uid = (int)get_request_arg(USEROPT_UID);
      $this->handle = get_request_arg(USEROPT_USER);

      $this->user = $this->game_stats = $this->diff_opps = null;
   }

   public function prepare()
   {
      global $player_row;

      // see specs/quick_suite.txt (3b)
      $my_id = (int)@$player_row['ID'];
      $dbgmsg = "QuickHandlerUser.prepare($my_id,{$this->uid},{$this->handle})";
      $this->checkCommand( $dbgmsg, USER_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check uid | user
      if ( ($this->uid == 0 || (string)$this->uid == '') && (string)$this->handle == '' )
      {
         // use logged-in user as default
         $this->uid = $my_id;
         if ( $this->uid <= 0 )
            error('invalid_args', "$dbgmsg.miss_user");
      }
      if ( (string)$this->uid != '' && is_numeric($this->uid) && $this->uid > 0 )
         $this->user = User::load_user( $this->uid );
      elseif ( (string)$this->handle != '' )
         $this->user = User::load_user_by_handle( $this->handle );
      else
         error('invalid_args', "$dbgmsg.bad_user");

      // prepare command: info

      if ( is_null($this->user) )
         error('unknown_user', "$dbgmsg.find_user");
      else
      {
         $this->game_stats = User::load_game_stats_for_users( $dbgmsg, $my_id, $this->user->ID );
         $this->diff_opps = User::load_different_opponents_for_all_games( $dbgmsg, $this->user->ID );
      }

      // check for invalid-action
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $urow = $this->user->urow;

      $my_info = ($this->user->ID == $this->my_id );
      $hero_ratio = User::calculate_hero_ratio( $this->user->urow['GamesWeaker'], $this->user->GamesFinished,
         $this->user->Rating, $this->user->RatingStatus );
      $hero_badge = User::determine_hero_badge($hero_ratio);
      $games_next_herolevel = User::determine_games_next_hero_level( $hero_ratio,
         $this->user->GamesFinished, $this->user->urow['GamesWeaker'], $this->user->RatingStatus );

      $this->addResultKey( 'id', $this->user->ID );
      $this->addResultKey( 'handle', $this->user->Handle );
      $this->addResultKey( 'type', self::convertUserType($this->user->Type) );
      $this->addResultKey( 'name', $this->user->Name );
      $this->addResultKey( 'country', $this->user->Country );
      $this->addResultKey( 'picture', $this->user->urow['UserPicture'] );
      $this->addResultKey( 'vacation_left', floor(@$urow['VacationDays']) );
      $this->addResultKey( 'vacation_on',
         TimeFormat::echo_onvacation( @$urow['OnVacation'], TIMEFMT_QUICK|TIMEFMT_ENGL|TIMEFMT_SHORT, '' ) );
      $this->addResultKey( 'register_date', QuickHandler::formatDate(@$urow['X_Registerdate'], /*long*/false) );
      $this->addResultKey( 'last_access', QuickHandler::formatDate(@$urow['X_Lastaccess']) );
      $this->addResultKey( 'last_quick_access', QuickHandler::formatDate(@$urow['X_LastQuickAccess']) );
      $this->addResultKey( 'last_move', QuickHandler::formatDate(@$urow['X_LastMove']) );
      $this->addResultKey( 'rating_status', strtoupper($this->user->RatingStatus) );
      $this->addResultKey( 'rating', echo_rating($this->user->Rating, 1, 0, true, 1) );
      $this->addResultKey( 'rating_elo', echo_rating_elo($this->user->Rating, false, '') );
      $this->addResultKey( 'rank', @$urow['Rank'] );
      $this->addResultKey( 'open_match', @$urow['Open'] );
      if ( $my_info )
      {
         $this->addResultKey( 'hits', (int)$this->user->urow['Hits'] );
         $this->addResultKey( 'moves', (int)$this->user->urow['Moves'] );
      }
      $this->addResultKey( 'games_running', (int)$this->user->urow['Running'] );
      $this->addResultKey( 'games_finished', (int)$this->user->GamesFinished );
      $this->addResultKey( 'games_rated', (int)$this->user->GamesRated );
      $this->addResultKey( 'games_won', (int)$this->user->urow['Won'] );
      $this->addResultKey( 'games_lost', (int)$this->user->urow['Lost'] );
      $this->addResultKey( 'games_mpg', (int)$this->user->urow['GamesMPG'] );
      if ( $my_info || $hero_badge > 0 )
         $this->addResultKey( 'hero_ratio', $hero_ratio );
      $this->addResultKey( 'hero_badge', User::determine_hero_badge($hero_ratio) );
      if ( $my_info )
         $this->addResultKey( 'hero_games_next',
            ( $games_next_herolevel < 0 ? -MIN_FIN_GAMES_HERO_AWARD : $games_next_herolevel ) );

      if ( !$my_info )
      {
         $this->addResultKey( 'opp_games_running', (int)$this->game_stats['Running'] );
         $this->addResultKey( 'opp_games_finished', (int)$this->game_stats['Finished'] );
      }
      if ( is_array($this->diff_opps) )
      {
         $this->addResultKey( 'diff_opps_games_all', (int)$this->diff_opps['count_diff_opps_all_games'] );
         $this->addResultKey( 'diff_opps_ratio_all', (float)$this->diff_opps['ratio_all_games'] );
      }
   }//process


   // ------------ static functions ----------------------------

   private static function convertUserType( $usertype )
   {
      $out = array();
      if ( $usertype & USERTYPE_PRO )
         $out[] = 'pro';
      if ( $usertype & USERTYPE_TEACHER )
         $out[] = 'teacher';
      if ( $usertype & USERTYPE_ROBOT )
         $out[] = 'bot';
      if ( $usertype & USERTYPE_TEAM )
         $out[] = 'team';
      return implode(',', $out);
   }

} // end of 'QuickHandlerUser'

?>

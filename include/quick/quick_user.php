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

   private $user;


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

      $this->user = null;
   }

   public function prepare()
   {
      global $player_row;

      // see specs/quick_suite.txt (3b)
      $dbgmsg = "QuickHandlerUser.prepare({$this->uid},{$this->handle})";
      $this->checkCommand( $dbgmsg, USER_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check uid | user
      if ( ($this->uid == 0 || (string)$this->uid == '') && (string)$this->handle == '' )
      {
         // use logged-in user as default
         $this->uid = (int)@$player_row['ID'];
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

      // check for invalid-action
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $urow = $this->user->urow;
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
      $this->addResultKey( 'rating_elo', echo_rating_elo($this->user->Rating) );
      $this->addResultKey( 'rank', @$urow['Rank'] );
      $this->addResultKey( 'open_match', @$urow['Open'] );
      $this->addResultKey( 'games_running', (int)$this->user->urow['Running'] );
      $this->addResultKey( 'games_finished', (int)$this->user->GamesFinished );
      $this->addResultKey( 'games_rated', (int)$this->user->GamesRated );
      $this->addResultKey( 'games_won', (int)$this->user->urow['Won'] );
      $this->addResultKey( 'games_lost', (int)$this->user->urow['Lost'] );
      $this->addResultKey( 'games_mpg', (int)$this->user->urow['GamesMPG'] );
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

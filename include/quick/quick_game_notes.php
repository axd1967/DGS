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
require_once 'include/game_functions.php';


 /*!
  * \file quick_game_notes.php
  *
  * \brief QuickHandler for managing private game-notes for game-object.
  * \see specs/quick_suite.txt (3a)
  */

// see specs/quick_suite.txt (3a)
define('GAMENOTES_OPT_GID', 'gid');
define('GAMENOTES_OPT_NOTES', 'notes');
define('QGAMENOTES_OPTIONS', 'gid|notes');

define('GAMECMD_GET_NOTES', 'get_notes');
define('GAMECMD_SAVE_NOTES', 'save_notes');
define('GAMECMD_HIDE_NOTES', 'hide_notes');
define('GAMECMD_SHOW_NOTES', 'show_notes');
define('GAMENOTES_COMMANDS', 'get_notes|save_notes|hide_notes|show_notes');


 /*!
  * \class QuickHandlerGameNotes
  *
  * \brief Quick-handler class for handling game-object.
  */
class QuickHandlerGameNotes extends QuickHandler
{
   private $gid = 0;
   private $arg_notes = null;

   private $hidden = null; // Y|N
   private $notes = null;


   // ---------- Interface ----------------------------------------

   public static function canHandle( $obj, $cmd ) // static
   {
      return ( $obj == QOBJ_GAME ) && QuickHandler::matchRegex(GAMENOTES_COMMANDS, $cmd);
   }

   public function parseURL()
   {
      parent::checkArgsUnknown(QGAMENOTES_OPTIONS);
      $this->gid = (int)get_request_arg(GAMENOTES_OPT_GID);
      $this->arg_notes = rtrim( get_request_arg(GAMENOTES_OPT_NOTES) );
   }

   public function prepare()
   {
      global $player_row;
      $uid = $this->my_id;

      // see specs/quick_suite.txt (3a)
      $dbgmsg = "QuickHandlerGameNotes.prepare($uid,{$this->gid})";
      $this->checkCommand( $dbgmsg, GAMENOTES_COMMANDS );
      $cmd = $this->quick_object->cmd;

      // check gid
      QuickHandler::checkArgMandatory( $dbgmsg, GAMEOPT_GID, $this->gid );
      if( !is_numeric($this->gid) || $this->gid <= 0 )
         error('unknown_game', "$dbgmsg.check.gid");
      $gid = $this->gid;

      // prepare command: get_notes, save_notes, hide_notes, show_notes

      if( $cmd == GAMECMD_GET_NOTES || $cmd == GAMECMD_SAVE_NOTES || $cmd == GAMECMD_HIDE_NOTES || $cmd == GAMECMD_SHOW_NOTES )
      {
         $gn_row = GameHelper::load_cache_game_notes( $dbgmsg, $gid, $uid );
         if( is_array($gn_row) )
         {
            $this->hidden = @$gn_row['Hidden'];
            $this->notes = @$gn_row['Notes'];
         }
         else
            $this->hidden = 'N'; // default for new game-notes
      }
   }//prepare

   /*! \brief Processes command for object; may fire error(..) and perform db-operations. */
   public function process()
   {
      $cmd = $this->quick_object->cmd;
      $dbgmsg = "QuickHandlerGameNotes.process($cmd)";

      if( $cmd == GAMECMD_GET_NOTES )
      {
         $this->addResultKey( 'hidden', ( $this->hidden == 'Y' ? 1 : 0 ) );
         $this->addResultKey( 'notes', (is_null($this->notes) ? "" : $this->notes) );
      }
      elseif( $cmd == GAMECMD_SAVE_NOTES )
         GameHelper::update_game_notes( $dbgmsg, $this->gid, $this->my_id, $this->hidden, $this->arg_notes );
      elseif( $cmd == GAMECMD_HIDE_NOTES )
         GameHelper::update_game_notes( $dbgmsg, $this->gid, $this->my_id, 'Y', $this->notes );
      elseif( $cmd == GAMECMD_SHOW_NOTES )
         GameHelper::update_game_notes( $dbgmsg, $this->gid, $this->my_id, 'N', $this->notes );
   }//process

} // end of 'QuickHandlerGameNotes'

?>

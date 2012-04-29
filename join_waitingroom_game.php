<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/wroom_control.php';


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'join_waitingroom_game');

/* Actual REQUEST calls used:  id=wroom-id (mandatory)
     delete=t&gid=      : delete waiting-room game
     join&gid=          : join waiting-room game
*/

   $wr_id = (int)@$_REQUEST['id'];
   if( @$_REQUEST['delete'] == 't' ) // delete
   {
      $gid = WaitingroomControl::delete_waitingroom_game( $wr_id );

      $msg = urlencode(T_('Game deleted!'));
      if( $gid )
         jump_to("game_players.php?gid=$gid".URI_AMP."sysmsg=$msg");
      else
         jump_to("waiting_room.php?sysmsg=$msg");
   }
   else // join
   {
      WaitingroomControl::join_waitingroom_game( $wr_id );

      $msg = urlencode(T_('Game joined!'));
      jump_to("status.php?sysmsg=$msg");
   }
}
?>

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/db/game_sgf.php';


 /*!
  * \class GameSgfControl
  *
  * \brief Controller-Class to handle SGF-attachment for finished games.
  */

class GameSgfControl
{
   // ------------ static functions ----------------------------

   // deletes own GameSgf
   function delete_game_sgf( $gid )
   {
      global $player_row;
      $uid = $player_row['ID'];

      $game_sgf = GameSgf::load_game_sgf( $gid, $uid );
      if( is_null($game_sgf) )
         return true; // nothing to delete
      else
         return $game_sgf->delete();
   }//delete_game_sgf

   function save_game_sgf( $gid, $uid, $path_file )
   {
      global $NOW;

      // read SGF from uploaded file
      $sgf_data = read_from_file( $path_file );
      if( $sgf_data !== false )
      {
         $game_sgf = new GameSgf( $gid, $uid, $NOW, $sgf_data );
         return $game_sgf->persist();
      }
      else // shouldn't happen as read-from-file quits on error
         return false;
   }//save_game_sgf

   // $disposition_type : inline | attachment
   function download_game_sgf( $dbgmsg, $gid, $uid, $disposition_type='attachment' )
   {
      global $NOW;

      $game_sgf = GameSgf::load_game_sgf( $gid, $uid );
      if( is_null($game_sgf) )
         error('invalid_args', $dbgmsg.".download_game_sgf.no_sgf($gid,$uid)");

      if( $disposition_type != 'inline' && $disposition_type != 'attachment' )
         $disposition_type = 'attachment';

      $filename = "dgs-{$game_sgf->gid}-{$game_sgf->uid}-" . date('Ymd', $game_sgf->Lastchanged);

      // output HTTP-header
      header( 'Content-Type: application/x-go-sgf' );
      // default for content-disposition is "attachment" because of "inline"-problems for some mobile-devices
      header( "Content-Disposition: $disposition_type; filename=\"$filename.sgf\"" );
      header( "Content-Description: PHP Generated Data" );

      /*
      if( $use_cache )
      {
         header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*SECS_PER_MIN));
         header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));
      }
      */

      echo $game_sgf->SgfData;
   }//download_game_sgf

} // end of 'GameSgfControl'
?>

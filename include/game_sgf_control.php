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

require_once 'include/std_functions.php';
require_once 'include/db/game_sgf.php';
require_once 'include/db/games.php';
require_once 'include/sgf_parser.php';
require_once 'include/board.php';
require_once 'include/coords.php';


 /*!
  * \class GameSgfControl
  *
  * \brief Controller-Class to handle SGF-attachment for running and finished games.
  */
class GameSgfControl
{

   /*!
    * \brief Deletes own GameSgf-entry.
    * \param $gid game-id
    * \return success; true is also returned if there's no entry to delete
    */
   public static function delete_game_sgf( $gid )
   {
      global $player_row;
      $uid = $player_row['ID'];

      $game_sgf = GameSgf::load_game_sgf( $gid, $uid );
      if ( is_null($game_sgf) )
         return true; // nothing to delete
      else
      {
         ta_begin();
         {//HOT-section to delete SGF and update game-flags
            $result = $game_sgf->delete();
            if ( $result && GameSgf::count_game_sgfs($gid) == 0 )
            {
               Games::update_game_flags( "GameSgfControl.delete_game_sgf.upd_flags($gid,$uid)",
                  $gid, 0, GAMEFLAGS_ATTACHED_SGF );

               self::delete_cache_game_sgfs_count( "GameSgfControl.delete_game_sgf($gid)", $gid );
            }
         }
         ta_end();

         return $result;
      }
   }//delete_game_sgf

   /*!
    * \brief Checks syntax, verifies and save uploaded SGF for given game and user.
    * \param $game pre-loaded Games-object
    * \param $uid author of SGF that uploaded SGF
    * \param $path_file server-local uploaded tmp-file to read SGF data from
    * \return non-null errors-array; empty on success
    */
   public static function save_game_sgf( $game, $uid, $path_file )
   {
      global $NOW;
      $gid = $game->ID;
      $errors = array( );

      // read SGF from uploaded file
      $sgf_data = read_from_file( $path_file );
      if ( $sgf_data !== false )
      {
         $errors = self::verify_game_sgf( $game, $sgf_data );
         if ( count($errors) == 0 )
         {
            $game_sgf = new GameSgf( $gid, $uid, $NOW, $sgf_data );

            ta_begin();
            {//HOT-section to save SGF and update game-flags
               if ( $game_sgf->persist() )
               {
                  if ( !($game->Flags & GAMEFLAGS_ATTACHED_SGF) )
                  {
                     Games::update_game_flags( "GameSgfControl.save_game_sgf.upd_flags($gid,$uid)",
                        $gid, GAMEFLAGS_ATTACHED_SGF );

                     self::delete_cache_game_sgfs_count( "GameSgfControl.save_game_sgf($gid)", $gid );
                  }
               }
               else
                  $errors[] = T_('Saving the SGF failed!');
            }
            ta_end();
         }
      }//else: shouldn't happen as read-from-file quits on error

      return $errors;
   }//save_game_sgf

   /*!
    * \brief Parses, checks SGF and verifies that game from SGF matches game on DGS.
    * \param $game pre-loaded Games-object
    * \param $sgf_data SGF-string
    * \return non-null errors-array; empty on success; Errors are returned on the following failures:
    *     - error, if file is not in SGF-format
    *     - error on SGF-parsing-error
    *     - error, if the SGFs board-size, handicap or komi does not match the DGS-game attributes
    *     - error, if the SGFs shape-setup and 1st 25 moves does not match the shape-setup and moves of the DGS-game
    */
   public static function verify_game_sgf( $game, $sgf_data )
   {
      if ( !GameSgfParser::might_be_sgf($sgf_data) )
         return array( T_('File has no SGF-format!') );

      $game_sgf_parser = GameSgfParser::parse_sgf_game( $sgf_data );
      $parse_err = $game_sgf_parser->get_error();
      if ( $parse_err )
         $errors = array( sprintf( T_('SGF-Parse error found: %s'), $parse_err ) );
      else
         $errors = $game_sgf_parser->verify_game_attributes( $game->Size, $game->Handicap, $game->Komi );

      if ( count($errors) == 0 )
      {
         // load some of the current game-moves and shape-setup from DB to compare with SGF
         static $skip_pass = true;
         list( $chk_cnt_moves, $db_shape_setup, $db_sgf_moves ) =
            Board::prepare_verify_game_sgf( $game, /*Board*/null, 25, $skip_pass );

         $errors = array_merge(
            $game_sgf_parser->verify_game_shape_setup( $db_shape_setup, $game->Size ),
            $game_sgf_parser->verify_game_moves( $chk_cnt_moves, $db_sgf_moves, $skip_pass ) );
      }

      return $errors;
   }//verify_game_sgf

   /*!
    * \brief Downloads (non-cached) stored SGF for given game-id and user-id.
    * \param $disposition_type inline | attachment for HTTP-header "Content-Disposition"
    */
   public static function download_game_sgf( $dbgmsg, $gid, $uid, $disposition_type='attachment' )
   {
      global $NOW;

      $game_sgf = GameSgf::load_game_sgf( $gid, $uid );
      if ( is_null($game_sgf) )
         error('invalid_args', $dbgmsg.".download_game_sgf.no_sgf($gid,$uid)");

      if ( $disposition_type != 'inline' && $disposition_type != 'attachment' )
         $disposition_type = 'attachment';

      $filename = "dgs-{$game_sgf->gid}-{$game_sgf->uid}-" . date('Ymd', $game_sgf->Lastchanged);

      // output HTTP-header
      header( 'Content-Type: application/x-go-sgf' );
      // default for content-disposition is "attachment" because of "inline"-problems for some mobile-devices
      header( "Content-Disposition: $disposition_type; filename=\"$filename.sgf\"" );
      header( "Content-Description: PHP Generated Data" );

      /*
      if ( $use_cache )
      {
         header('Expires: ' . gmdate(GMDATE_FMT, $NOW+5*SECS_PER_MIN));
         header('Last-Modified: ' . gmdate(GMDATE_FMT, $NOW));
      }
      */

      echo $game_sgf->SgfData;
   }//download_game_sgf

   public static function count_cache_game_sgfs( $gid )
   {
      $dbgmsg = "GameSgfControl:count_cache_game_sgfs($gid)";
      $key = "GameSgfCount.$gid";

      $count = DgsCache::fetch( $dbgmsg, CACHE_GRP_GAMESGF_COUNT, $key );
      if ( is_null($count) )
      {
         $count = GameSgf::count_game_sgfs( $gid );
         DgsCache::store( $dbgmsg, CACHE_GRP_GAMESGF_COUNT, $key, $count, 30*SECS_PER_MIN );
      }

      return $count;
   }//count_cache_game_sgfs

   private static function delete_cache_game_sgfs_count( $dbgmsg, $gid )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_GAMESGF_COUNT, "GameSgfCount.$gid" );
   }

} // end of 'GameSgfControl'
?>

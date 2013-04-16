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
      if( is_null($game_sgf) )
         return true; // nothing to delete
      else
      {
         ta_begin();
         {//HOT-section to delete SGF and update game-flags
            $result = $game_sgf->delete();
            if( $result && GameSgf::count_game_sgfs($gid) == 0 )
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
      if( $sgf_data !== false )
      {
         $errors = self::verify_game_sgf( $game, $sgf_data );
         if( count($errors) == 0 )
         {
            $game_sgf = new GameSgf( $gid, $uid, $NOW, $sgf_data );

            ta_begin();
            {//HOT-section to save SGF and update game-flags
               if( $game_sgf->persist() )
               {
                  if( !($game->Flags & GAMEFLAGS_ATTACHED_SGF) )
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
      $errors = array();
      $gsize = $game->Size;

      if( !SgfParser::might_be_sgf($sgf_data) )
         return array( T_('File has no SGF-format!') );

      $sgf_parser = SgfParser::parse_sgf( $sgf_data );
      if( $sgf_parser->error )
         $errors[] = sprintf( T_('SGF-Parse error found: %s'), $sgf_parser->error );
      else
      {
         if( $sgf_parser->Size != $gsize )
            $errors[] = sprintf( T_('Board size mismatch: expected %s but found %s#sgf'), $gsize, $sgf_parser->Size );
         if( $sgf_parser->Handicap != $game->Handicap )
            $errors[] = sprintf( T_('Handicap mismatch: expected %s but found %s#sgf'), $game->Handicap, $sgf_parser->Handicap );
         if( (float)$sgf_parser->Komi != (float)$game->Komi )
            $errors[] = sprintf( T_('Komi mismatch: expected %s but found %s#sgf'), $game->Komi, $sgf_parser->Komi );
      }

      if( count($errors) == 0 )
      {
         // load some of the current game-moves and shape-setup from DB to compare with SGF
         list( $chk_cnt_moves, $db_shape_setup, $db_sgf_moves ) = self::prepare_verify_game_sgf( $game );

         // compare shape-setup from DB with B/W-stone-setup parsed from SGF
         foreach( array( BLACK, WHITE ) as $stone )
         {
            $arr_coords = ( $stone == BLACK ) ? $sgf_parser->SetBlack : $sgf_parser->SetWhite;
            foreach( $arr_coords as $sgf_coord )
            {
               if( !isset($db_shape_setup[$sgf_coord]) || $db_shape_setup[$sgf_coord] != $stone )
               {
                  $coord = sgf2board_coords( $sgf_coord, $gsize );
                  $errors[] = sprintf( T_('Shape-Setup mismatch: found discrepancy at coord [%s]#sgf'), $coord );
               }
               unset($db_shape_setup[$sgf_coord]);
            }
         }
         if( count($db_shape_setup) > 0 )
         {
            $coords = array();
            foreach( $db_shape_setup as $sgf_coord => $stone )
               $coords[] = sgf2board_coords( $sgf_coord, $gsize );
            $errors[] = sprintf( T_('Shape-Setup mismatch: missing setup stones in SGF [%s]#sgf'), implode(',', $coords) );
         }

         // compare some db-moves with moves parsed from SGF
         $move_nr = 0;
         foreach( $sgf_parser->Moves as $move ) // move = B|W sgf-coord, e.g. "Baa", "Wbb"
         {
            if( $move_nr >= $chk_cnt_moves )
               break;
            if( strlen($move) != 3 ) // skip PASS-move
               continue;
            if( $move != $db_sgf_moves[$move_nr++] )
            {
               $errors[] = sprintf( T_('Moves mismatch: found discrepancy at move #%s#sgf'), $move_nr + 1 );
               break;
            }
         }
      }

      return $errors;
   }//verify_game_sgf

   /*!
    * \brief Loads game-, shape-setup- and moves-data from DGS-database to compare with SGF-parsed-data in verify_game_sgf()-function.
    * \param $game pre-loaded Games-object
    * \return arr( count-moves-to-check, db-shape-setup-arr, db-sgf-moves-arr )
    *     - db-shape-setup-arr = arr( SGF-coord => BLACK|WHITE, ... )
    *     - db-sgf-moves-arr   = arr( 'Baa', 'Wbb', ... );   // <COLOR><SGF_COORD>; only B/W-moves; captured stones and PASS-moves are not returned
    */
   private static function prepare_verify_game_sgf( $game )
   {
      $gid = $game->ID;
      $size = $game->Size;

      // load some of the current game-moves and shape-setup from DB to compare with SGF
      $chk_cnt_moves = min( 25, $game->Moves );
      $game_row = array(
         'ID' => $gid,
         'Size' => $size,
         'Moves' => $game->Moves,
         'ShapeSnapshot' => $game->ShapeSnapshot
      );
      $board = new Board();
      if( !$board->load_from_db( $game_row, $chk_cnt_moves, BOARDOPT_USE_CACHE ) )
         error('internal_error', "GameSgfControl:prepare_verify_game_sgf.check.moves($gid,$chk_cnt_moves)");

      // prepare shape-setup from db
      $db_shape_setup = array(); // arr( SGF-coord => BLACK|WHITE, ... )
      foreach( $board->shape_arr_xy as $arr )
      {
         list( $Stone, $PosX, $PosY) = $arr;
         $sgf_coord = number2sgf_coords( $PosX, $PosY, $size, $size );
         $db_shape_setup[$sgf_coord] = $Stone;
      }

      // prepare to verify some moves: read some db-moves
      $db_sgf_moves = array(); // Baa, Wbb, ...
      $count_handicap = $game->Handicap;
      foreach( $board->moves as $arr ) // arr: Stone,PosX,PosY
      {
         list( $Stone, $PosX, $PosY) = $arr;
         if( $PosX < 0 ) // check for valid move
            continue;
         if( $Stone == BLACK )
            $color = 'B';
         elseif( $Stone == WHITE )
            $color = 'W';
         else
            continue;

         if( $count_handicap-- > 0 ) // put handicap-stones into shape-setup-arr
         {
            $sgf_coord = number2sgf_coords( $PosX, $PosY, $size, $size );
            $db_shape_setup[$sgf_coord] = $Stone;
         }
         else
            $db_sgf_moves[] = $color . number2sgf_coords( $arr[1], $arr[2], $size, $size );
      }

      return array( $chk_cnt_moves, $db_shape_setup, $db_sgf_moves );
   }//prepare_verify_game_sgf

   /*!
    * \brief Downloads (non-cached) stored SGF for given game-id and user-id.
    * \param $disposition_type inline | attachment for HTTP-header "Content-Disposition"
    */
   public static function download_game_sgf( $dbgmsg, $gid, $uid, $disposition_type='attachment' )
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

   public static function count_cache_game_sgfs( $gid )
   {
      $dbgmsg = "GameSgfControl:count_cache_game_sgfs($gid)";
      $key = "GameSgfCount.$gid";

      $count = DgsCache::fetch( $dbgmsg, CACHE_GRP_GAMESGF_COUNT, $key );
      if( is_null($count) )
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

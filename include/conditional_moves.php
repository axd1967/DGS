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

$TranslateGroups[] = "Game";

require_once 'include/classlib_upload.php';
require_once 'include/sgf_parser.php';
require_once 'include/std_functions.php';


/*!
 * \class ConditionalMoves
 *
 * \brief Helper class to handle conditional-moves for game.
 */
class ConditionalMoves
{

   //TODO comments
   public static function load_cond_moves_from_sgf( $file_sgf_arr )
   {
      // upload SGF and parse
      list( $errors, $sgf_data ) = FileUpload::load_data_from_file( $file_sgf_arr, SGF_MAXSIZE_UPLOAD );
      if ( is_null($errors) )
      {
         $sgf_parser = new SgfParser();
         if ( $sgf_parser->parse_sgf($sgf_data) )
         {
            //TODO check parsed SGF game-tree:
            //TODO - check: only 1 SGF-game
            //TODO - check: same board-size, handicap, komi like curr-game
            //TODO - check: same shape-setup and moves like curr-game
            //TODO - last same move is starting point for cond-moves -> extract cond-moves-part

            //TODO from here also used for manually entered cond-moves:
            //TODO check cond-moves-part:
            //TODO   - check: each node need B|W-prop
            //TODO   - check: alternating colors in all vars B-W
            //TODO   - check: all vars ending with own move
            //TODO   - check: check if COs (SGF- or board-format) are valid (syntax); moves not played out
            //TODO   - replace: tt=PASS to '' (=empty)
            //TODO   - check: not more than 2 passes per var
            //TODO   - check: max-size for rebuilt cond-moves is 2048
            //TODO   - LATER: check that all vars contain valid moves, that can be played rule-conform (empty-points, ko, etc); see create_igoban_from_parsed_sgf()

            //TODO $game_sgf_parser = GameSgfParser::parse_sgf_game( $sgf_data );
            //TODO see verify_game_sgf()
         }
         else
            $errors = array( $sgf_parser->error_loc );
      }

      return array( $errors, $sgf_data, $sgf_parser );
   }//load_cond_moves_from_sgf


   /*!
    * \brief Converts and re-formats human-entered conditional-moves with SGF- & board-coordinates
    *       into SGF-like-syntax expected.
    *
    * \note already present SGF-properties are not touched
    * \note trim string, add missing ';'-node-char, add missing move-color, add property-format for B/W-moves,
    *       add missing surrounding braces for root-var, replace 'tt' (=SGF-pass) with ''
    * \note still can contain empty nodes and has no root-node properties
    * \note no syntax or semantical checks done (e.g. consecutive moves with same color)
    *
    * \param $str string to parse and convert
    * \param $size board-size to recognize coordinates
    * \param $is_blacks_turn to determine if coord is for Black or White
    * \return converted SGF-like string
    */
   public static function reformat_to_sgf( $str, $size, $is_blacks_turn )
   {
      static $ARR = array( 'B', 'W' );
      $color = ($is_blacks_turn) ? 0 : 1;
      $size = (int)$size;
      $str = trim($str);

      // regex for board- & SGF-coordinate depending on board-size
      $rxc_s = 'a-' . chr( ord('a') + $size - 1 ); // max-coord for SGF-coords
      $rxc_b = ( $size > 8 ) ? 'a-hj-' . chr( ord('a') + $size ) : $rxc_s; // max-coord for board-coords
      $rxc_bn = ( $size <= 9 ) ? "1-$size" : '1[0-' . ( $size <= 19 ? ($size-10) : '9]|2[0-'.($size-20) ) . ']|[1-9]';
      $rxc = "([BW])?([$rxc_b](?:$rxc_bn)|[$rxc_s][$rxc_s]|tt)"; // tt=PASS (SGF), s19=board-coord, aa=sgf-coord

      $str_len = strlen($str);
      $arg_start = -1;
      $esc = 0;
      $i = 0;
      $new_str = '';

      while ( $i < $str_len )
      {
         $c = $str[$i++];
         if ( $esc )
            $esc = 0;
         elseif ( $c == '\\' ) // escape-char
            $esc = 1;
         elseif ( $arg_start < 0 && $c == '[' ) // SGF-property-start
         {
            $arg_start = $i - 1;
            continue;
         }
         elseif ( $c == ']' ) // SGF-property-end
         {
            $new_str .= substr( $str, $arg_start, $i - $arg_start );
            $arg_start = -1;
            continue;
         }

         if ( $arg_start < 0 )
         {
            if ( preg_match("/^$rxc(\\s|;|$)/i", substr($str, $i-1), $matches) )
            {
               // replace with colored SGF-property
               $c_col = strtoupper($matches[1]);
               $coord = strtolower($matches[2]);
               if ( !$c_col )
                  $c_col = $ARR[$color];
               $color = 1 - $color;
               $new_str .= " ;{$c_col}[" . ( $coord != 'tt' ? $coord : '' ) . ']';
               $i += strlen($matches[0]) - 1;
            }
            else
               $new_str .= $c;
         }
      }
      if ( $arg_start >= 0 )
         $new_str .= substr( $str, $arg_start, $i - $arg_start );

      $new_str = preg_replace("/\\s+/", ' ', $new_str); // keep LFs, but replace simple white-spaces

      // surround with "(; ... )"
      if ( preg_match("/^(\\()?\\s*(;)\\s*?(.*)$/", $new_str, $matches) )
         $new_str = '(;' . $matches[3];
      if ( substr($str, -1) != ')' )
         $new_str .= ' )';

      return $new_str;
   }//reformat_to_sgf

} //end 'ConditionalMoves'

?>

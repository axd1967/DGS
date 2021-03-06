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

$TranslateGroups[] = "Game";

require_once 'include/board.php';
require_once 'include/classlib_upload.php';
require_once 'include/move.php';
require_once 'include/sgf_parser.php';
require_once 'include/std_functions.php';


/*!
 * \class ConditionalMoves
 *
 * \brief Helper class to handle conditional-moves for game.
 */
class ConditionalMoves
{
   public static $TXT_CM_START = 'CONDITIONAL_MOVES';

   /*!
    * \bried Loads uploaded SGF-file, checks that played moves are identical and finds start of conditional-moves.
    * \param $file_sgf_arr files-arr taken from global $_FILES[key]
    * \param $grow Games-row with at least fields (ID, Size, Moves, Handicap, Komi, ShapeSnapshot)
    * \param $board pre-loaded Board-object to avoid re-loading; null = reload Board with shape/moves
    * \return arr( errors, sgf-data, game-sgf-parser ):
    *       errors = empty-arr (success), otherwise error-list;
    *       sgf-data = raw SGF-data from uploaded file;
    *       game-sgf-parser = GameSgfParser with parsed SGF-data
    */
   public static function load_cond_moves_from_sgf( $file_sgf_arr, $grow, $board )
   {
      $errors = array();
      $game_sgf_parser = null;

      // read SGF from uploaded file
      list( $upload_errors, $sgf_data ) = FileUpload::load_data_from_file( $file_sgf_arr, SGF_MAXSIZE_UPLOAD );
      if ( !is_null($upload_errors) && count($upload_errors) > 0 )
         $errors = $upload_errors;
      else
      {
         // parse SGF and identify start of cond-moves
         $game_sgf_parser = GameSgfParser::parse_sgf_game( $sgf_data, 0, $grow['Moves'] );
         $parse_err = $game_sgf_parser->get_error();
         if ( $parse_err )
            $errors[] = sprintf( T_('SGF-Parse error found: %s'), $parse_err );
         else
         {
            // check parsed SGF game-tree
            if ( count($game_sgf_parser->sgf_parser->games) > 1 ) // check for only 1 SGF-gametree
               $errors[] = T_('SGF contains more than one game-tree.');
            else
            {
               // check for same board-size, handicap, komi
               $errors = $game_sgf_parser->verify_game_attributes( $grow['Size'], $grow['Handicap'], $grow['Komi'] );

               if ( count($errors) == 0 )
               {
                  // load some of the current game-moves and shape-setup from DB to compare with SGF
                  static $skip_pass = false;
                  list( $moves, $db_shape_setup, $db_sgf_moves ) =
                     Board::prepare_verify_game_sgf( $grow, $board, -1, $skip_pass );

                  // check: same shape-setup and moves
                  $errors = array_merge(
                     $game_sgf_parser->verify_game_shape_setup( $db_shape_setup, $grow['Size'] ),
                     $game_sgf_parser->verify_game_moves( $moves, $db_sgf_moves, $skip_pass ) );
               }
            }
         }
      }

      return array( $errors, $sgf_data, $game_sgf_parser );
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
      if ( (string)$str == '' )
         return '';

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
            if ( preg_match("/^$rxc(\\s|;|$)/Si", substr($str, $i-1), $matches) )
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

      $new_str = preg_replace("/ +/", ' ', $new_str); // remove double-spaces

      // surround with "(; ... )"
      if ( preg_match("/^(\\()?\\s*(;)?\\s*?(.*)$/", $new_str, $matches) )
         $new_str = '(;' . $matches[3];
      if ( substr($str, -1) != ')' )
         $new_str .= ' )';

      return $new_str;
   }//reformat_to_sgf


   /*!
    * \brief Checks syntax and replaces PASS-moves of conditional-moves-part (read from uploaded-SGF or manually entered).
    * \param $sgf_game_tree SgfGameTree with parsed nodes (sub-tree) with only conditional-moves; as parsed from SgfParser
    * \param $gchkmove GameCheckMove initialized with shape-setup and moves up to but excluding first move of cond-moves
    * \param $gsize board-size
    * \param $player_color BLACK | WHITE
    * \return arr( errors, variation-name-references, cond-moves-sgf-coords )
    */
   public static function check_nodes_cond_moves( &$sgf_game_tree, $gchkmove, $gsize, $player_color )
   {
      $errors = array();
      $own_color = ($player_color == BLACK) ? 'B' : 'W';
      $opp_color = ($player_color == BLACK) ? 'W' : 'B';

      $sgf_cond_moves = SgfParser::sgf_builder( array( $sgf_game_tree ), '', '', '',
         'SgfParser::sgf_convert_move_to_sgf_coords', $gsize );
      $var_names = array(); // varname => 1
      $cnt_total_nodes = 0;
      $last_pos = 0;

      // traverse game-tree
      $vars = array(); // stack for variations for traversal of game-tree
      SgfParser::push_var_stack( $vars, $sgf_game_tree, array(
            'level'        => 0, // level=0 (root)
            'varname'      => '1',
            'last_color'   => ( ( $gchkmove->get_replay_last_color() == BLACK ) ? 'B' : 'W' ),
            'last_move'    => $gchkmove->get_replay_last_sgf_move(), // SGF- or board-coord, ''=PASS, -1 = unset
            'cnt_pass'     => 0,
            'gchkmove'     => clone $gchkmove,
         ));

      while ( list($data, $game_tree) = array_pop($vars) ) // process variations-stack
      {
         $varname = $data['varname'];
         $last_color = $data['last_color'];
         $last_move = $data['last_move'];
         $cnt_pass = $data['cnt_pass'];
         $gchkmove = $data['gchkmove'];

         $cnt_nodes = count($game_tree->nodes);
         $cnt_total_nodes += $cnt_nodes;
         if ( $cnt_nodes == 0 )
            $errors[] = sprintf( T_('Variation [%s] at position %s is missing nodes.#sgf'), $varname, $last_pos );

         $var_has_bad_move = false;
         foreach ( $game_tree->nodes as $node_idx => $node ) // $node is a SgfNode
         {
            // start all variations with opponents move (not own move); except for root-node, which can contain 1st move
            if ( $node_idx == 0 && isset($node->props[$own_color]) && $data['level'] > 0 )
               $errors[] = sprintf( T_('Variation [%s] in node [%s] at position %s must start with opponents move.#condmoves'),
                  $varname, $node->get_props_text(), $node->pos );

            // check move
            $has_prop_B = isset($node->props['B']);
            $has_prop_W = isset($node->props['W']);
            if ( $has_prop_B && $has_prop_W )
               $errors[] = sprintf( T_('Node [%s] at position %s in variation [%s] has a Black and White move.#condmoves'),
                  $node->get_props_text(), $node->pos, $varname );
            elseif ( $has_prop_B || $has_prop_W ) // each node needs B|W-property with move
            {
               // moves must have alternating colors (relative to last-move)
               $col_key = ( $has_prop_B ) ? 'B' : 'W';
               if ( $col_key == $last_color )
                  $errors[] = sprintf( T_('Node [%s] at position %s in variation [%s] has same color as previous move.#condmoves'),
                     $node->get_props_text(), $node->pos, $varname );
               $last_color = $col_key;

               $coord = @$node->props[$col_key][0]; // SGF-coord or board-coord
               if ( $gsize <= 19 && $coord == 'tt' ) // replace PASS-notation of 'tt' -> ''
                  $node->prop[$col_key][0] = $coord = '';

               // check if coords (SGF- or board-format) are valid (syntax); moves not played out here
               $is_pass = ( (string)$coord == '' );
               if ( !$is_pass )
               {
                  if ( is_valid_sgf_coords($coord, $gsize) )
                     $sgf_coord = $coord;
                  elseif ( is_valid_board_coords($coord, $gsize) )
                  {
                     list( $x, $y ) = board2number_coords($coord, $gsize);
                     $sgf_coord = number2sgf_coords( $x, $y, $gsize );
                  }
                  else
                     $errors[] = sprintf( T_('Node [%s] at position %s in variation [%s] has invalid coordinates [%s].#condmoves'),
                        $node->get_props_text(), $node->pos, $varname, $coord );

                  //  do not allow move after 2 consecutive PASS-moves
                  if ( $cnt_pass >= 2 )
                     $errors[] = sprintf( T_('Move in node [%s] at position %s in variation [%s] is not allowed after 2 PASS-moves.#condmoves'),
                        $node->get_props_text(), $node->pos, $varname );

                  $cnt_pass = 0;
               }
               else //PASS
               {
                  $sgf_coord = '';
                  $cnt_pass = ( (string)$last_move == '' ) ? $cnt_pass + 1 : 1;
               }

               // check if move can be played rule-conform (empty-points, ko, suicide, invalid coord)
               if ( !$var_has_bad_move ) // stop move-play-checking if former move on same var-path was bad
               {
                  $move_err = $gchkmove->replay_move( $col_key . $sgf_coord );
                  $mseq_err = MoveSequence::get_check_move_error_code( $move_err );
                  if ( $mseq_err > 0 )
                  {
                     $var_has_bad_move = true;
                     $errors[] = sprintf( T_('Move in node [%s] at position %s in variation [%s] is invalid (%s).#condmoves'),
                        $node->get_props_text(), $node->pos, $varname, MoveSequence::getErrorCodeText($mseq_err) );
                  }
               }

               $last_move = $coord;
            }
            else
               $errors[] = sprintf( T_('Found node [%s] at position %s in variation [%s] without Black or White move.#condmoves'),
                  $node->get_props_text(), $node->pos, $varname );

            $last_pos = $node->pos;
         }//tree-nodes end

         if ( $game_tree->has_vars() )
         {
            $first_moves = array();
            foreach ( $game_tree->vars as $varnum => $sub_tree )
            {
               $sub_varname = $varname . '.'. ($varnum + 1);
               SgfParser::push_var_stack( $vars, $sub_tree, array(
                     'level'        => $data['level'] + 1,
                     'varname'      => $sub_varname,
                     'last_color'   => $last_color,
                     'last_move'    => $last_move,
                     'cnt_pass'     => $cnt_pass,
                     'gchkmove'     => clone $gchkmove,
                  ));

               // check that all variations start with unique move
               $first_node = $sub_tree->get_first_node();
               $node_mv = self::get_conditional_move_format_from_sgf_node($first_node, $gsize, /*die-err*/false );
               if ( !is_null($node_mv) )
               {
                  if ( isset($first_moves[$node_mv]) )
                     $errors[] = sprintf( T_('Variation [%s] at position %s started with move [%s], but it must be different from other variations.#condmoves'),
                        $sub_varname, $last_pos, self::convert_conditional_move_format_to_board_coords($node_mv, $gsize) );
                  $first_moves[$node_mv] = 1;
               }
            }//end-foreach tree-vars

            // variations must end with own move
            if ( count($game_tree->vars) <= 1 && $last_color != $own_color )
               $errors[] = sprintf( T_('Variation [%s] ending at position %s must end with players color.#condmoves'),
                  $varname, $last_pos );
         }
         else // game-tree has no vars
         {
            $var_names[$varname] = 1; // collect variation-"structure" for var-preview

            // each game-tree (without sub-vars) must have at least 2 moves (opponent + own move)
            if ( $cnt_nodes < 2 )
               $errors[] = sprintf( T_('Variation [%s] ending at position %s must have at least two moves.#condmoves'),
                  $varname, $last_pos );
         }
      }//game-tree end


      // check total number of nodes
      static $max_cm_nodes = 100;
      if ( $cnt_total_nodes > $max_cm_nodes )
         $errors[] = sprintf( T_('Conditional moves sequence contains %s nodes, but only %s are allowed.'),
            $cnt_total_nodes, $max_cm_nodes );

      // check max-size for rebuilt cond-moves (to be stored)
      static $max_cm_len = 2048;
      $sgf_length = strlen($sgf_cond_moves);
      if ( $sgf_length > $max_cm_len )
         $errors[] = sprintf( T_('Conditional moves sequence is %s bytes long, but only %s are allowed.'),
            $sgf_length - $max_cm_len, $max_cm_len );

      return array( array_reverse( array_unique($errors) ), array_reverse( array_keys($var_names) ), $sgf_cond_moves );
   }//check_nodes_cond_moves

   /*!
    * \brief Extracts specified variation-reference from given nodes-tree.
    * \return error-text, or else arr( move, ... ); move = arr( BLACK|WHITE, x-pos, y-pos ) with x-pos can be POSX_PASS
    */
   public static function extract_variation( $sgf_game_tree, $variation, $board_size )
   {
      $moves = array();
      $varpart_ref = explode('.', $variation);

      if ( array_shift($varpart_ref) != '1' ) // variation-ref must start with '1'
         return sprintf( T_('Variation reference [%s] must start with \'1\'.#condmoves'), $variation );

      // traverse game-tree
      $vars = array(); // stack for variations for traversal of game-tree
      SgfParser::push_var_stack( $vars, $sgf_game_tree );

      $var_visit = array( 1 );
      while ( list($data, $game_tree) = array_pop($vars) ) // process variations-stack
      {
         foreach ( $game_tree->nodes as $node ) // $node is a SgfNode
         {
            if ( isset($node->props['B']) )
            {
               $coord = $node->props['B'][0];
               $stone_col = BLACK;
            }
            elseif ( isset($node->props['W']) )
            {
               $coord = $node->props['W'][0];
               $stone_col = WHITE;
            }
            else
               continue;

            if ( (string)$coord == '' || ( $board_size <= 19 && $coord == 'tt' ) )
            {
               $x = POSX_PASS;
               $y = 0;
            }
            elseif ( is_valid_sgf_coords($coord, $board_size) )
               list( $x, $y ) = sgf2number_coords($coord, $board_size);
            elseif ( is_valid_board_coords($coord, $board_size) )
               list( $x, $y ) = board2number_coords($coord, $board_size);
            else
               return sprintf( T_('Illegal coordinate found [%s] in sub-variation [%s].#condmoves'),
                  $coord, implode('.', $var_visit));

            $moves[] = array( $stone_col, $x, $y );
         }

         if ( $game_tree->has_vars() )
         {
            $var_idx = ( count($varpart_ref) ) ? array_shift($varpart_ref) - 1 : 0;
            $var_visit[] = $var_idx + 1;
            if ( !isset($game_tree->vars[$var_idx]) )
               return sprintf( T_('Sub-variation [%s] can not be found.#condmoves'), implode('.', $var_visit));
            SgfParser::push_var_stack( $vars, $game_tree->vars[$var_idx] );
         }
      }

      return $moves;
   }//extract_variation

   /*!
    * \brief Plays conditional moves on board for showing all of them for preview.
    * \return ''=success; else illegal-move-error
    */
   public static function add_played_conditional_moves_on_board( &$board, $gchkmove, $moves )
   {
      $error = '';
      $movenum = 0;
      foreach ( $moves as $move ) // move = ( stone=BLACK|WHITE, posx, posy ); posx can be POSX_PASS
      {
         $movenum++;
         $err = $gchkmove->replay_move( $move );
         if ( $err )
         {
            list( $to_move, $x, $y ) = $move;
            $board_pos = number2board_coords( $x, $y, $board->size );
            $board->set_conditional_moves_errpos( $movenum - 1 );
            $error = sprintf( T_('Playing conditional moves stopped: Error [%s] at move #%s [%s] found!'),
               $err, $movenum, ($to_move==BLACK ? 'B' : 'W') . $board_pos );
            break;
         }
      }

      $gchkmove->assign_board_array( $board );

      return $error;
   }//add_played_conditional_moves_on_board

   /*!
    * \brief Returns start-move in parsed conditional-moves.
    * \param $sgf_game_tree SgfGameTree with SgfNode-entries (and without parsing-errors)
    * \return 'W' (=white-PASS), or else move-color + sgf-coord of 1st move in conditional-moves, e.g. 'Bef'
    */
   public static function get_nodes_start_move_sgf_coords( $sgf_game_tree, $size )
   {
      if ( !$sgf_game_tree->has_nodes() )
         error('invalid_args', "CM.get_nodes_start_move_sgf_coords.check.miss_cond_moves($size)");

      return self::get_conditional_move_format_from_sgf_node( $sgf_game_tree->nodes[0], $size );
   }//get_nodes_start_move_sgf_coords

   /*!
    * \brief Returns conditional-move in format COLOR . COORD.
    * \param $sgf_node SgfNode to get B/W-property with move
    * \return 'W' (=white-PASS), or else move-color + sgf-coord, e.g. 'Bef'; null = error if !$die_on_error
    */
   public static function get_conditional_move_format_from_sgf_node( $sgf_node, $size, $die_on_error=true )
   {
      if ( !( $sgf_node instanceof SgfNode ) )
      {
         if ( $die_on_error )
            error('invalid_args', "CM.get_cm_move_fmt_from_sgf_node.check.bad_node($size)");
         return null;
      }

      if ( isset($sgf_node->props['B']) )
      {
         $color = 'B';
         $coord = $sgf_node->props['B'][0];
      }
      elseif ( isset($sgf_node->props['W']) )
      {
         $color = 'W';
         $coord = $sgf_node->props['W'][0];
      }
      else
      {
         if ( $die_on_error )
            error('invalid_args', "CM.get_cm_move_fmt_from_sgf_node.check.miss_BW_move($size,".$sgf_node->get_props_text().")");
         return null;
      }

      if ( (string)$coord == '' || ($size <= 19 && $coord == 'tt') )
         $move = $color; // PASS
      elseif ( is_valid_sgf_coords($coord, $size) )
         $move = $color . $coord;
      elseif ( is_valid_board_coords($coord, $size) )
      {
         list( $x, $y ) = board2number_coords( $coord, $size );
         $move = $color . number2sgf_coords( $x, $y, $size );
      }
      else
      {
         if ( $die_on_error )
            error('invalid_args', "CM.get_cm_move_fmt_from_sgf_node.conv_move($size,$coord)");
         return null;
      }

      return $move;
   }//get_conditional_move_format_from_sgf_node

   private static function convert_conditional_move_format_to_board_coords( $cm_move, $size )
   {
      $board_move = $cm_move[0];
      if ( strlen($cm_move) == 3 )
         $board_move .= sgf2board_coords( substr($cm_move, 1), $size );
      return $board_move;
   }//convert_conditional_move_format_to_board_coords

   /*!
    * \brief callback-function for SgfParser::sgf_builder() to strip out C-sgf-nodes with text starting conditional-moves.
    * \return modified SgfNode-object
    */
   public static function sgf_strip_cond_moves_notes( $sgf_node )
   {
      if ( isset($sgf_node->props[$prop = 'C']) )
      {
         $values = $sgf_node->props[$prop];
         $text = trim( str_replace( self::$TXT_CM_START, '', $values[0] ) ); // remove CM-indicator-text
         if ( (string)$text == '' )
            unset($sgf_node->props[$prop]);
         else
            $sgf_node->props[$prop][0] = $text;
      }
      return $sgf_node;
   }//sgf_strip_cond_moves_notes

   /*!
    * \brief Sets SgfNode-attributes: node->move_nr starting with $start_move_nr
    *       and set node->sgf_move=Baa|Wbb|B(=pass) for all SgfNodes.
    * \return modified SgfGameTree
    * \note also modifies passed $sgf_game_tree (as it's an object)
    */
   public static function fill_conditional_moves_attributes( $sgf_game_tree, $start_move_nr )
   {
      // traverse game-tree
      $vars = array(); // stack for variations for traversal of game-tree
      SgfParser::push_var_stack( $vars, $sgf_game_tree, $start_move_nr );

      while ( list($move_nr, $game_tree) = array_pop($vars) ) // process variations-stack
      {
         foreach ( $game_tree->nodes as $node ) // $node is a SgfNode
         {
            // set SgfNode->sgf_move for easier move-comparing/merging
            if ( isset($node->props['B']) )
               $node->sgf_move = 'B' . $node->props['B'][0];
            if ( isset($node->props['W']) )
               $node->sgf_move = 'W' . $node->props['W'][0];

            $node->move_nr = $move_nr++; // add move-nr
         }//var-nodes end

         foreach ( $game_tree->vars as $sub_tree )
            SgfParser::push_var_stack( $vars, $sub_tree, $move_nr );
      }//game-tree end

      return $sgf_game_tree;
   }//fill_conditional_moves_attributes

} //end 'ConditionalMoves'

?>

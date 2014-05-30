<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Rod Ival

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

require_once 'include/coords.php';
require_once 'include/utilities.php';


/*
Basic (EBNF) Definition - see http://www.red-bean.com/sgf/sgf4.html#2

  "..." : terminal symbols
  [...] : option: occurs at most once
  {...} : repetition: any number of times, including zero
  (...) : grouping
    |   : exclusive or
 italics: parameter explained at some other place


SGF EBNF Definition

  Collection = GameTree { GameTree }
  GameTree   = "(" Sequence { GameTree } ")"
  Sequence   = Node { Node }
  Node       = ";" { Property }
  Property   = PropIdent PropValue { PropValue }
  PropIdent  = UcLetter { UcLetter }
  PropValue  = "[" CValueType "]"
  CValueType = (ValueType | Compose)
  ValueType  = (None | Number | Real | Double | Color | SimpleText | Text | Point  | Move | Stone)

White space (space, tab, carriage return, line feed, vertical tab and so on)
may appear anywhere between PropValues, Properties, Nodes, Sequences and GameTrees.

There are two types of property lists: 'list of' and 'elist of'.
'list of':    PropValue { PropValue }
'elist of':   ((PropValue { PropValue }) | None)
              In other words elist is list or "[]".


Property Value Types

  UcLetter   = "A".."Z"
  Digit      = "0".."9"
  None       = ""

  Number     = [("+"|"-")] Digit { Digit }
  Real       = Number ["." Digit { Digit }]

  Double     = ("1" | "2")
  Color      = ("B" | "W")

  SimpleText = { any character (handling see below) }
  Text       = { any character (handling see below) }

  Point      = game-specific
  Move       = game-specific
  Stone      = game-specific

  Compose    = ValueType ":" ValueType


*/

define('SGF_VAR_BEG', '(');
define('SGF_VAR_END', ')');
define('SGF_ARG_BEG', '[');
define('SGF_ARG_END', ']');
define('SGF_NOD_BEG', ';');

define('SGFP_OPT_SKIP_ROOT_NODE', 0x01); // don't read root-node and simplify game-tree if possible


/*! \brief Helper-class to parse SGF-data. */
class SgfParser
{
   private $options = '';
   private $sgf = '';
   private $sgf_len = 0;
   private $idx = 0; // string-position while parsing

   public $games = array();
   public $error_msg = '';
   public $error_loc = '';

   private static $ARR_ROOT_DEFAULT_VALUES = array(
         'FF' => 4,
         'GM' => 1,
         'SZ' => 19,
         'RU' => 'Japanese',
      );

   public function __construct( $options=0 )
   {
      $this->options = $options;
   }

   private function init_parser( $sgf )
   {
      $this->sgf = $sgf;
      $this->sgf_len = strlen($sgf);
      $this->idx = 0;

      $this->games = array();
      $this->error_msg = '';
      $this->error_loc = '';
   }

   /*!
    * \brief Parses specified SGF-data into $this->games-array; return true on success, false on error.
    * \note $this->error_msg contains error (or ''=no-error), $this->error_loc contains error-context in SGF-data
    */
   public function parse_sgf( $sgf )
   {
      $this->init_parser( $sgf );

      $this->error_msg = $this->parse_sgf_game_tree();

      if ( $this->error_msg )
      {
         $tmp = max(0, $this->idx-20);
         $this->error_loc = "SGF: {$this->error_msg}, near: "
            . ltrim(substr( $this->sgf, $tmp, $this->idx-$tmp))
            . '<' . substr( $this->sgf, $this->idx, 1) . '>'
            . rtrim(substr( $this->sgf, $this->idx+1, 20));
      }

      return ( (string)$this->error_msg == '' );
   }//parse_sgf

   /*!
    * \brief Parses list of "GameTree" from SGF-data; returning '' on success or else error-msg.
    * \note Sets $this->games with array of SgfGameTree-objects.
    */
   private function parse_sgf_game_tree()
   {
      $skip_root_node = ( $this->options & SGFP_OPT_SKIP_ROOT_NODE );

      $vars = array();
      while ( false !== ($this->idx = strpos( $this->sgf, SGF_VAR_BEG, $this->idx)) )
      {
         // $vars holds parsed variations (SgfGameTree), 1st elem is one complete EBNF-GameTree
         $idx_var = 0;
         $vars[$idx_var] = new SgfGameTree();

         $this->idx++;
         if ( !$skip_root_node )
         {
            if ( !$this->sgf_skip_space() )
               return T_('Bad end of file#sgf');

            if ( $this->sgf[$this->idx] != SGF_NOD_BEG )
               return T_('Bad root node#sgf');

            $this->idx++;
            $err = $this->sgf_parse_node( $node );
            if ( $err )
               return $err;

            if ( !isset($node->props['GM']) || @$node->props['GM'][0] != 1 )
               return T_('Not a Go game (GM[1])#sgf');

            foreach ( self::$ARR_ROOT_DEFAULT_VALUES as $key => $arg ) // set defaults
            {
               if ( !isset($node->props[$key]) )
                  $node->props[$key] = array($arg);
            }

            $vars[$idx_var]->nodes[] = $node;
         }//skip-root-node

         while ( $idx_var >= 0 && ($c = $this->sgf_skip_space()) )
         {
            switch ( (string)$c )
            {
               case SGF_VAR_END:
                  $idx_var--;
                  $this->idx++;
                  $vars[$idx_var]->vars[] = $vars[$idx_var + 1];
                  break;

               case SGF_VAR_BEG:
                  $this->idx++;
                  if ( $this->sgf_skip_space() != SGF_NOD_BEG )
                     return T_('Bad node start#sgf');

                  $idx_var++;
                  $vars[$idx_var] = new SgfGameTree();
                  // NOTE: running through (no break here)

               case SGF_NOD_BEG:
                  $this->idx++;
                  if ( $vars[$idx_var]->has_vars() )
                     return T_('Bad node position outside variation#sgf');
                  $err = $this->sgf_parse_node( $node );
                  $vars[$idx_var]->nodes[] = $node;
                  if ( $err )
                     return $err;
                  break;

               default:
                  return T_('Game-Tree syntax error#sgf');
            }
         }

         if ( $idx_var >= ($skip_root_node ? 1 : 0) )
            return T_('Missing right parenthesis#sgf');

         // simplify if only one variation with empty root-node
         if ( $skip_root_node )
         {
            if ( !$vars[0]->has_nodes() && count($vars[0]->vars) == 1 )
               $vars[0] = $vars[0]->vars[0];
         }

         $this->games[] = $vars[0];
      }
      unset($vars);

      return ''; // no-error
   }//parse_sgf_game_tree


   /*!
    * Skips white-space in SGF-data.
    * \return character after skipped white-spaces; should be non-number
    */
   private function sgf_skip_space()
   {
      while ( $this->idx < $this->sgf_len )
      {
         if ( $this->sgf[$this->idx] > ' ' )
            return $this->sgf[$this->idx];
         $this->idx++;
      }
      return 0;
   }//sgf_skip_space

   /*!
    * \brief Parses "Node" in SGF-data.
    * \note Example: 'MN[0][1]C[note]' into $node = SgfNote( props: arr( 'MN' => arr( 0, 1 ), 'C' => arr( 'note' )) )
    * \note node from sgf-data must end with one of: "; ( )"
    *
    * \param $node SgfNode-object passed via back-reference with parsed nodes
    * \return '' = no-error; else error-msg with parsing-error
    */
   private function sgf_parse_node( &$node )
   {
      $node = new SgfNode( $this->idx - 1 );
      while ( $key = $this->sgf_parse_key() )
      {
         $err = $this->sgf_parse_args( $args );
         if ( $err )
            return $err;
         if ( isset($node->props[$key]) )
            return T_('Property not unique#sgf');
         $node->props[$key] = $args;
      }

      $c = $this->sgf[$this->idx]; //sgf_skip_space()
      return ( $c != SGF_NOD_BEG && $c != SGF_VAR_BEG && $c != SGF_VAR_END ) ? T_('Node syntax error#sgf') : '';
   }//sgf_parse_args

   /*!
    * \brief Parses "PropValue" in SGF-data, e.g. '0' from 'MN[0]'.
    * \param $args array passed via back-reference with parsed arguments, e.g. '[0][1]' -> ( 0, 1 )
    * \return '' = no-error; else error-msg with parsing-error
    */
   private function sgf_parse_args( &$args )
   {
      $args = array();
      while ( $this->sgf_skip_space() === SGF_ARG_BEG )
      {
         $j = $this->idx;
         $found_arg = false;
         while ( false !== ($j = strpos( $this->sgf, SGF_ARG_END, $j + 1 )) )
         {
            if ( $this->sgf[$j-1] != '\\' )
            {
               $arg = substr( $this->sgf, $this->idx + 1, $j - $this->idx - 1 );
               $args[] = $arg;
               $this->idx = $j + 1;
               $found_arg = true;
               break;
            }
         }
         if ( !$found_arg && false === $j )
            return T_('Missing right bracket#sgf');
      }
      return '';
   }//sgf_parse_args

   /*!
    * \brief Parses upper-case "Property" from SGF-data, e.g. 'MN' from 'MN[0]'; skipping lower-case and white-spaces.
    * \return property-key
    */
   private function sgf_parse_key()
   {
      $key = '';
      while ( $this->idx < $this->sgf_len )
      {
         $c = $this->sgf[$this->idx];
         if ( $c >= 'A' && $c <= 'Z' )
            $key .= $c;
         elseif ( ($c > ' ') && ($c < 'a' || $c > 'z') )
            break;
         $this->idx++;
      }
      return $key;
   }//sgf_parse_key


   // ------------ static functions ----------------------------

   /*! \brief Explodes a SGF-string ($sgf, from file) into an array of games stored in SgfParser-object. */
   public static function sgf_parser( $sgf_data, $parse_options=0 )
   {
      $sgf_parser = new SgfParser( $parse_options );
      $sgf_parser->parse_sgf( $sgf_data );
      return $sgf_parser;
   }

   /*! \brief Pushes variation $var (mostly SgfGameTree) on variation-stack $vars with some varying data-payload. */
   public static function push_var_stack( &$vars, &$entry, $data=0 )
   {
      $vars[] = array( $data, $entry );
   }

   // In short, it does the opposite of sgf_parser()
   // \param $props_lf_sep comma-separated list of props with preceding LF
   // \return built sgf-data
   public static function sgf_builder( $games, $var_start_sep="\n", $node_start_sep="", $props_lf_sep='',
         $convert_node_func=null, $convert_extra_arg=null )
   {
      $sgf = '';
      $map_props_with_lf = array_value_to_key_and_value( explode(',', $props_lf_sep) ); // props with preceding LF

      $vars = array();
      foreach ( array_reverse($games) as $tree ) //$games is array of SgfGameTree
         array_push( $vars, SGF_VAR_END, $tree );

      while ( $tree = array_pop($vars) )
      {
         if ( $tree === SGF_VAR_END )
            $sgf .= SGF_VAR_END;
         else
         {
            $sgf .= $var_start_sep . SGF_VAR_BEG;
            foreach ( $tree->nodes as $node ) //$node is a SgfNode with an array of properties
            {
               $sgf .= $node_start_sep . SGF_NOD_BEG;
               $conv_node = (is_null($convert_node_func))
                  ? $node
                  : call_user_func( $convert_node_func, $node, $convert_extra_arg );
               foreach ( $conv_node->props as $key => $args )
               {
                  if ( isset($map_props_with_lf[$key]) )
                     $sgf .= "\n";
                  $sgf .= $key;
                  foreach ( $args as $arg )
                     $sgf .= SGF_ARG_BEG.$arg.SGF_ARG_END;
               }
            }

            foreach ( array_reverse($tree->vars) as $sub_tree )
               array_push( $vars, SGF_VAR_END, $sub_tree );
         }
      }

      return trim($sgf);
   }//sgf_builder

   /*! \brief Normalizes given coordinate (in SGF- or board-format) to SGF-coord, use '' for PASS-move. */
   public static function normalize_move_coords( $coord, $size )
   {
      if ( (string)$coord == '' || ( $size <= 19 && $coord == 'tt' ) ) // normalize PASS-move
         return '';
      elseif ( is_valid_board_coords($coord, $size) )
      {
         list( $x, $y ) = board2number_coords($coord, $size);
         return number2sgf_coords($x, $y, $size);
      }
      else //if ( is_valid_sgf_coords($coord, $size) )
         return $coord;
   }//normalize_move_coords

   /*!
    * \brief callback-function for sgf_builder() to convert B/W-moves in SgfNode-object to board-coordinates (+ use '' for PASS-move).
    * \return modified SgfNode-object
    * \note $sgf_node is modified as well, because objects are passed by reference
    */
   public static function sgf_convert_move_to_board_coords( $sgf_node, $size )
   {
      foreach ( $sgf_node->props as $prop => $values )
      {
         if ( $prop == 'B' || $prop == 'W' )
         {
            $coord = $values[0];
            if ( (string)$coord == '' || ( $size <= 19 && $coord == 'tt' ) ) // normalize PASS-move
               $sgf_node->props[$prop][0] = '';
            elseif ( (string)$coord != '' && is_valid_sgf_coords($coord, $size) )
               $sgf_node->props[$prop][0] = sgf2board_coords($coord, $size);
         }
      }
      return $sgf_node;
   }//sgf_convert_move_to_board_coords

   /*!
    * \brief callback-function for sgf_builder() to convert B/W-moves in SgfNode-object to sgf-coordinates (+ use '' for PASS-move).
    * \return modified SgfNode-object
    * \note $sgf_node is modified as well, because objects are passed by reference
    */
   public static function sgf_convert_move_to_sgf_coords( $sgf_node, $size )
   {
      foreach ( $sgf_node->props as $prop => $values )
      {
         if ( $prop == 'B' || $prop == 'W' )
         {
            $coord = $values[0];
            $sgf_node->props[$prop][0] = self::normalize_move_coords( $coord, $size );
         }
      }
      return $sgf_node;
   }//sgf_convert_move_to_sgf_coords

} //end 'SgfParser'



/*! \brief Class used to store game-tree with nodes parsed from SGF. */
class SgfGameTree
{
   public $nodes = array(); // SgfNode, ...
   public $vars = array(); // SgfGameTree, ...

   public function has_nodes()
   {
      return count($this->nodes);
   }

   public function has_vars()
   {
      return count($this->vars);
   }

   public function get_first_node()
   {
      return ( count($this->nodes) ) ? $this->nodes[0] : null;
   }

   public function to_string( $conv_func=null, $conv_size=19 )
   {
      return SgfParser::sgf_builder( array( clone $this ), "\n", '', '', $conv_func, $conv_size );
   }

   public function debug( $conv_size=19 )
   {
      return SgfParser::sgf_builder( array( clone $this ), ' ', '', '' );
      //return SgfParser::sgf_builder( array( clone $this ), ' ', '', '', 'SgfParser::sgf_convert_move_to_board_coords', $conv_size ); //TODO TODO
   }

} //end 'SgfGameTree'


/*! \brief Class used to store node parsed from SGF. */
class SgfNode
{
   public $props = array(); // propkey => arr( value, ... )
   public $pos; // parsing pos of node-start ';'
   public $move_nr = 0;

   public function __construct( $pos )
   {
      $this->pos = $pos;
   }

   public function get_props_text()
   {
      $out = array();
      foreach ( $this->props as $prop => $value )
         $out[] = "{$prop}[" . implode('][', $value) . "]";
      return implode(' ', $out);
   }

   public function to_string()
   {
      $out = array();
      foreach ( $this as $k => $v )
         $out[] = ( $k == 'props' ) ? 'props=' . $this->get_props_text() : "$k=[$v]";
      return '{' . implode(', ', $out) . '}';
   }

} //end 'SgfNode'




/*! \brief Class containing parsed properties from SGF for further processing. */
class GameSgfParser
{
   public $sgf_parser; // SgfParser
   public $sgf_game_tree = null;

   public $Size = 0;
   public $Handicap = 0; // number of handicap-stones
   public $Komi = 0;
   public $SetWhite = array(); // [ sgf-coord, ... ]
   public $SetBlack = array();
   public $Moves = array(); // [ 'B|W'.sgf-coord, ... ], for example: [ 'Baa', ... ]

   public function __construct( $sgf_parser )
   {
      $this->sgf_parser = $sgf_parser;
   }

   /*! \brief Returns error from SGF-parser; error-message | '' (=success). */
   public function get_error()
   {
      return $this->sgf_parser->error_loc;
   }

   /*!
    * \brief Verifies basic attributes of game to match those parsed from SGF: size, handicap, komi.
    * \return empty-array = success; errors otherwise
    */
   public function verify_game_attributes( $size, $handicap, $komi )
   {
      $errors = array();

      if ( $this->Size != $size )
         $errors[] = sprintf( T_('Board size mismatch: expected %s but found %s#sgf'), $size, $this->Size );
      if ( $this->Handicap != $handicap )
         $errors[] = sprintf( T_('Handicap mismatch: expected %s but found %s#sgf'), $handicap, $this->Handicap );
      if ( (float)$this->Komi != (float)$komi )
         $errors[] = sprintf( T_('Komi mismatch: expected %s but found %s#sgf'), $komi, $this->Komi );

      return $errors;
   }//verify_game_attributes

   /*!
    * \brief Verifies game shape-setup to match those parsed from SGF.
    * \param $db_shape_setup array( sgf-coord => BLACK | WHITE, ... )
    * \return empty-array = success; errors otherwise
    */
   public function verify_game_shape_setup( $db_shape_setup, $gsize )
   {
      $errors = array();

      // compare shape-setup from DB with B/W-stone-setup parsed from SGF
      foreach ( array( BLACK, WHITE ) as $stone )
      {
         $arr_coords = ( $stone == BLACK ) ? $this->SetBlack : $this->SetWhite;
         foreach ( $arr_coords as $sgf_coord )
         {
            if ( !isset($db_shape_setup[$sgf_coord]) || $db_shape_setup[$sgf_coord] != $stone )
            {
               $coord = sgf2board_coords( $sgf_coord, $gsize );
               $errors[] = sprintf( T_('Shape-Setup mismatch: found discrepancy at coord [%s]#sgf'), $coord );
            }
            unset($db_shape_setup[$sgf_coord]);
         }
      }
      if ( count($db_shape_setup) > 0 )
      {
         $coords = array();
         foreach ( $db_shape_setup as $sgf_coord => $stone )
            $coords[] = sgf2board_coords( $sgf_coord, $gsize );
         $errors[] = sprintf( T_('Shape-Setup mismatch: missing setup stones in SGF [%s]#sgf'), implode(',', $coords) );
      }

      return $errors;
   }//verify_game_shape_setup

   /*!
    * \brief Verifies count of $chk_cnt_moves game-moves to match those parsed from SGF.
    * \param $chk_cnt_moves how many moves to check starting at first
    * \param $db_sgf_moves array with moves from db-game in format <B|W sgf-coord|''>, e.g. "Baa", "W"
    * \param $skip_pass true = skip PASS-moves in this parsed SGF; false = also match PASS-moves
    * \return empty-array = success; errors otherwise
    */
   public function verify_game_moves( $chk_cnt_moves, $db_sgf_moves, $skip_pass )
   {
      $errors = array();

      // compare some db-moves with moves parsed from SGF
      $move_nr = 0;
      foreach ( $this->Moves as $move ) // move = B|W sgf-coord, e.g. "Baa", "Wbb"
      {
         if ( $move_nr >= $chk_cnt_moves )
            break;
         if ( $skip_pass && strlen($move) != 3 ) // skip PASS-move
            continue;
         if ( $move != $db_sgf_moves[$move_nr++] )
         {
            $errors[] = sprintf( T_('Moves mismatch: found discrepancy at move #%s#sgf'), $move_nr );
            break;
         }
      }

      return $errors;
   }//verify_game_moves


   // ------------ static functions ----------------------------

   /*! Returns true, if passed SGF-data does rudimentary looking like a SGF. */
   public static function might_be_sgf( $sgf_data )
   {
      return preg_match("/^\\s*\\(\\s*;\\s*[a-z]/si", $sgf_data);
   }

   /*!
    * \brief Parses SGF-data into resulting-array (used to load SGF and flatten into Goban-objects for Shape-game).
    * \param $last_move_nr >=0 to collect extra-nodes in this->sgf_game_tree starting from that move-nr;
    *       <0 = no collecting of game-tree
    * \return GameSgfParser-instance with filled properties; parsing-error in SgfParser->Error or '' if ok
    */
   public static function parse_sgf_game( $sgf_data, $last_move_nr=-1 )
   {
      $sgf_parser = SgfParser::sgf_parser( $sgf_data );
      $game_sgf_parser = new GameSgfParser( $sgf_parser );
      if ( $sgf_parser->error_msg )
         return $game_sgf_parser;

      $movenum = 0; // current move-number
      $vars = array(); // variations
      $parsed_HA = $parsed_KM = null;
      $game_sgf_parser->sgf_game_tree = new SgfGameTree();
      $collect_gametree = 0; // for game-tree: 0|-1 = no collecting, 1 = collect nodes, -1 = collecting stopped

      SgfParser::push_var_stack( $vars, $sgf_parser->games[0], $movenum ); // check 1st game only

      while ( list($movenum, $game_tree) = array_pop($vars) ) // process variations-stack
      {
         foreach ( $game_tree->nodes as $node ) // $node is a SgfNode
         {
            if ( isset($node->props['B']) || isset($node->props['W']) )
            {
               $key = ( isset($node->props['B']) ) ? 'B' : 'W';
               $sgf_coord = @$node->props[$key][0];
               if ( $game_sgf_parser->Size <= 19 && $sgf_coord == 'tt' ) // tt=>'' (=PASS)
                  $sgf_coord = '';
               $game_sgf_parser->Moves[] = $key . $sgf_coord;
               $movenum++;

               // start collecting additional nodes after last curr-move
               if ( $last_move_nr >= 0 && $collect_gametree == 0 && $movenum >= $last_move_nr )
                  $collect_gametree = 1; // start collecting cond-moves after last move
            }
            if ( $movenum == 0 ) // parse certain props only from root-node (before 1st move starts)
            {
               if ( isset($node->props['AB']) )
               {
                  foreach ( @$node->props['AB'] as $sgf_coord )
                     $game_sgf_parser->SetBlack[] = $sgf_coord;
               }
               if ( isset($node->props['AW']) )
               {
                  foreach ( @$node->props['AW'] as $sgf_coord )
                     $game_sgf_parser->SetWhite[] = $sgf_coord;
               }
               if ( isset($node->props['SZ']) && !$game_sgf_parser->Size )
                  $game_sgf_parser->Size = (int)$node->props['SZ'][0];
               if ( isset($node->props['HA']) && is_null($parsed_HA) )
                  $game_sgf_parser->Handicap = $parsed_HA = (int)$node->props['HA'][0];
               if ( isset($node->props['KM']) && is_null($parsed_KM) )
                  $game_sgf_parser->Komi = $parsed_KM = (float)$node->props['KM'][0];
            }

            if ( $collect_gametree > 0 )
               $game_sgf_parser->sgf_game_tree->nodes[] = $node;
         }//end-nodes

         if ( $game_tree->has_vars() )
         {
            $sub_tree = $game_tree->vars[0]; // only take first var (main-branch)
            if ( $collect_gametree > 0 )
            {
               $game_sgf_parser->sgf_game_tree->vars[] = $sub_tree;
               $collect_gametree = -1; // stop collecting, because var already contains the rest
            }
            SgfParser::push_var_stack( $vars, $sub_tree, $movenum );
         }
      }

      return $game_sgf_parser;
   }//parse_sgf_game

}//end 'GameSgfParser'




// Read the standard handicap pattern file
// and convert it to a stonestring.
function get_handicap_pattern( $size, $handicap, &$err)
{
   $stonestring ='';
   if ( $handicap < 2 )
      return $stonestring;

   $filename = "pattern/standard_handicap_$size.sgf";
   $sgf_data = @read_from_file( $filename, 0);
   if ( is_string($sgf_data) )
   {
      $sgf_parser = SgfParser::sgf_parser( $sgf_data );
      $game_tree = $sgf_parser->games[0]; //keep the first game only
      $err = $sgf_parser->error_loc;
   }
   else
      $err = T_('File not found');
   if ( $err )
   {
      //Simply returning the error message will allow the player to manually add his handicap stones.
      $err = sprintf( T_('Bad handicap pattern for %s'), "size=$size h=$handicap err=[$err]" );
      return $stonestring;
   }

   $nb = 0;
   $vars = array();
   SgfParser::push_var_stack( $vars, $game_tree, $nb);

   while ( list($nb, $game_tree) = array_pop($vars) )
   {
      $stonestring = substr( $stonestring, 0, 2*$nb);

      //a variation is an array of nodes
      foreach ( $game_tree->nodes as $node ) // $node is a SgfNode
      {
         if ( isset($node->props['B']) || isset($node->props['W']) )
         {
            $co = @$node->props['B'][0];
            if ( !$co )
               $co = @$node->props['W'][0];
            if ( strlen($co) != 2 )
               return $stonestring;
            $stonestring .= $co;
            $nb++;
            if ( $nb >= $handicap )
               return $stonestring;
         }
      }

      foreach ( array_reverse($game_tree->vars) as $sub_tree )
         SgfParser::push_var_stack( $vars, $sub_tree, $nb );
   }
   //See previous error comment
   $err = sprintf( T_('Insufficient handicap pattern for %s'), "size=$size h=$handicap n=$nb" );
   return $stonestring;
}//get_handicap_pattern

?>

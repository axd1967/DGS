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


/*
Basic (EBNF) Definition

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
define('SGF_VAR_KEY', '++');



/*! \brief Helper-class to parse SGF-data. */
class Sgf
{
   public $games;
   public $error_msg;
   public $error_loc;

   private static $ARR_ROOT_DEFAULT_VALUES = array(
         'FF' => 4,
         'GM' => 1,
         'SZ' => 19,
         'RU' => 'Japanese',
      );

   private function __construct( $games, $error_msg, $error_loc )
   {
      $this->games = $games;
      $this->error_msg = $error_msg;
      $this->error_loc = $error_loc;
   }


   // ------------ static functions ----------------------------

   /*! \brief Explodes a SGF-string ($sgf, from file) into an array of games ($games). */
   public static function sgf_parser( $sgf )
   {
      $err = '';
      $games = array();

      $sgf_len = strlen($sgf);
      $i = 0;
      while ( !$err && false !== ($i = strpos( $sgf, SGF_VAR_BEG, $i )) )
      {
         $ivar = 0;
         $vars[$ivar] = array();

         $i++;
         if ( !self::sgf_skip_space( $sgf, $i, $sgf_len) )
         {
            $err = T_('Bad end of file#sgf');
            break;
         }

         if ( $sgf[$i] != SGF_NOD_BEG )
         {
            $err = T_('Bad root node#sgf');
            break;
         }

         $i++;
         $err = self::sgf_parse_node( $sgf, $i, $sgf_len, $node);
         if ( $err )
            break;

         if ( !isset($node['GM']) || @$node['GM'][0] != 1 )
         {
            $err = T_('Not a Go game (GM[1])#sgf');
            break;
         }

         foreach ( self::$ARR_ROOT_DEFAULT_VALUES as $key => $arg )
         {
            if ( !isset($node[$key]) )
               $node[$key] = array($arg);
         }

         $vars[$node_ivar = $ivar][] = $node;

         while ( !$err && $ivar >= 0 && ($c = self::sgf_skip_space( $sgf, $i, $sgf_len)) )
         {
            switch ( (string)$c )
            {
               case SGF_VAR_END:
                  $ivar--; $i++;
                  $vars[$ivar][SGF_VAR_KEY][] = $vars[$ivar + 1];
                  break;

               case SGF_VAR_BEG:
                  $i++;
                  if ( SGF_NOD_BEG != self::sgf_skip_space( $sgf, $i, $sgf_len) )
                  {
                     $err = T_('Bad node start#sgf');
                     break;
                  }
                  if ( $node_ivar <= $ivar )
                     $vars[$ivar][SGF_VAR_KEY] = array();
                  $ivar++;
                  $vars[$ivar] = array();
               case SGF_NOD_BEG :
                  $i++;
                  $err = self::sgf_parse_node( $sgf, $i, $sgf_len, $node);
                  $vars[$node_ivar=$ivar][] = $node;
                  break;

               default :
                  $err = T_('Syntax error#sgf');
                  break;
            }
         }
         if ( $err )
            break;

         if ( $ivar >= 0 )
         {
            $err = T_('Missing right parenthesis#sgf');
            break;
         }

         $games[] = $vars[0];
      }

      $err_msg = $err;
      if ( $err )
      {
         $tmp = max(0, $i-20);
         $err = "SGF: $err\nnear: "
            . ltrim(substr( $sgf, $tmp, $i-$tmp))
            . '<' . substr( $sgf, $i, 1) . '>'
            . rtrim(substr( $sgf, $i+1, 20));
      }

      $sgf_parser = new Sgf( $games, $err_msg, $err );
      return $sgf_parser;
   }//sgf_parser


   // In short, it does the opposite of sgf_parser()
   // \return error or else '' (=ok)
   // NOTE: not used, but keep it for later
   private static function sgf_builder( $games, &$sgf )
   {
      $sgf = '';

      $vars = array();
      //$games is an array of games (i.e. variations)
      for ( $i=count($games)-1; $i >= 0; $i-- )
         self::sgf_var_push( $vars, $games[$i] );

      while ( $var = array_pop($vars) )
      {
         if ( $var === SGF_VAR_END ) //see Sgf::sgf_var_push()
         {
            $sgf .= SGF_VAR_END."\r\n";
            continue;
         }

         $sgf .= SGF_VAR_BEG."\r\n";
         //a variation is an array of nodes
         foreach ( $var as $id => $node )
         {
            if ( $id === SGF_VAR_KEY )
            {
               //this perticular node is an array of variations
               for ( $i=count($node)-1; $i >= 0; $i-- )
                  self::sgf_var_push( $vars, $node[$i] );
               continue;
            }

            $sgf .= SGF_NOD_BEG."\r\n";
            //a node is an array of properties
            foreach ( $node as $key => $args )
            {
               $sgf .= "$key";
               foreach ( $args as $arg )
                  $sgf .= SGF_ARG_BEG.$arg.SGF_ARG_END;
               $sgf .= "\r\n";
            }
         }
      }

      return '';
   }//sgf_builder

   /*! \brief Pushes variation $var on variation-stack $vars. */
   private static function sgf_var_push( &$vars, &$var )
   {
      if ( is_array($var) )
      {
         $vars[] = SGF_VAR_END;
         $vars[] = &$var;
      }
   }

   /*!
    * Skips white-space in SGF-data.
    * \param $sgf string with length $l to parse
    * \param $i index to start in $sgf; modified to next position after white-spaces
    * \return character after skipped white-spaces; should be non-number
    */
   private static function sgf_skip_space( $sgf, &$i, $l )
   {
      while ( $i < $l )
      {
         if ( $sgf[$i] > ' ' )
            return $sgf[$i];
         $i++;
      }
      return 0;
   }//sgf_skip_space

   /*!
    * \brief Parses "Node" in SGF-data, e.g. 'MN[0][1]C[note]' into $node = arr( 'MN' => arr( 0, 1 ), 'C' => arr( 'note' )).
    * \note node-data must end with one of: "; ( )"
    * \param $sgf string with length $l to parse
    * \param $i index to start in $sgf; modified to next position after node
    * \param $node array passed via back-reference with parsed nodes
    * \return '' = no-error; else error-msg with parsing-error
    */
   private static function sgf_parse_node( $sgf, &$i, $l, &$node )
   {
      $node = array();
      while ( $key = self::sgf_parse_key( $sgf, $i, $l ) )
      {
         $err = self::sgf_parse_args( $sgf, $i, $l, $args );
         if ( $err )
            return $err;
         $node[$key] = $args;
      }
      $c = $sgf[$i]; //sgf_skip_space( $sgf, $i, $l)
      return ( $c != SGF_NOD_BEG && $c != SGF_VAR_BEG && $c != SGF_VAR_END ) ? T_('Syntax error#sgf') : '';
   }//sgf_parse_args

   /*!
    * \brief Parses "PropValue" in SGF-data, e.g. '0' from 'MN[0]'.
    * \param $sgf string with length $l to parse
    * \param $i index to start in $sgf; modified to next position after arguments
    * \param $args array passed via back-reference with parsed arguments, e.g. '[0][1]' -> ( 0, 1 )
    * \return '' = no-error; else error-msg with parsing-error
    */
   private static function sgf_parse_args( $sgf, &$i, $l, &$args )
   {
      $args = array();
      while ( self::sgf_skip_space( $sgf, $i, $l) == SGF_ARG_BEG )
      {
         $j = $i;
         while ( false !== ($j = strpos( $sgf, SGF_ARG_END, $j + 1 )) )
         {
            if ( $sgf[$j-1] != '\\' )
            {
               $arg = substr( $sgf, $i + 1, $j - $i - 1 );
               $args[] = $arg;
               $i = $j + 1;
               break;
            }
         }
         if ( false === $j )
            return T_('Missing right bracket#sgf');
      }
      return '';
   }//sgf_parse_args

   /*!
    * \brief Parses upper-case "Property" from SGF-data, e.g. 'MN' from 'MN[0]'; skipping lower-case and white-spaces.
    * \param $sgf string with length $l to parse
    * \param $i index to start in $sgf; modified to next position after property-key
    * \return property-key
    */
   private static function sgf_parse_key( $sgf, &$i, $l )
   {
      $key = '';
      while ( $i < $l )
      {
         $c = $sgf[$i];
         if ( $c >= 'A' && $c <= 'Z' )
            $key .= $c;
         elseif ( ($c > ' ') && ($c < 'a' || $c > 'z') )
            break;
         $i++;
      }
      return $key;
   }//sgf_parse_key


   /*! \brief Pushes variation $var on variation-stack $vars with move-num $nb. */
   public static function push_var_stack( &$vars, &$var, $nb )
   {
      if ( is_array($var) )
         $vars[] = array( $nb, &$var );
   }

} //end 'Sgf'




/*! \brief Class containing parsed properties from SGF for further processing. */
class GameSgfParser
{
   public $error; // error-message | '' (=success)
   public $Size = 0;
   public $Handicap = 0; // number of handicap-stones
   public $Komi = 0;
   public $SetWhite = array(); // [ sgf-coord, ... ]
   public $SetBlack = array();
   public $Moves = array(); // [ 'B|W'.sgf-coord, ... ], for example: [ 'Baa', ... ]

   public function __construct( $error )
   {
      $this->error = $error;
   }


   // ------------ static functions ----------------------------

   /*! Returns false if passed SGF-data does not rudimentary looking like a SGF. */
   public static function might_be_sgf( $sgf_data )
   {
      return preg_match("/^\\s*\\(\\s*;\\s*[a-z]/si", $sgf_data);
   }

   /*!
    * \brief Parses SGF-data into resulting-array (used to load SGF and flatten into Goban-objects for Shape-game).
    * \return GameSgfParser-instance with filled properties; parsing-error in SgfParser->Error or '' if ok
    */
   public static function parse_sgf( $sgf_data )
   {
      $sgf_parser = Sgf::sgf_parser( $sgf_data );
      $game_sgf_parser = new GameSgfParser( $sgf_parser->error_loc );
      if ( $sgf_parser->error_msg )
         return $game_sgf_parser;

      $game = $sgf_parser->games[0]; // check 1st game only
      $movenum = 0; // current move-number
      $vars = array(); // variations
      Sgf::push_var_stack( $vars, $game, $movenum );

      $parsed_HA = $parsed_KM = null;
      while ( list($movenum, $var) = array_pop($vars) ) // process variations-stack
      {
         // a variation is an array of nodes
         foreach ( $var as $id => $node )
         {
            if ( $id === SGF_VAR_KEY )
            {
               // this particular node is an array of variations, but only take first var (main-branch)
               Sgf::push_var_stack( $vars, $node[0], $movenum );
               continue;
            }

            // a node is an array of properties
            if ( isset($node['B']) || isset($node['W']) )
            {
               $key = ( isset($node['B']) ) ? 'B' : 'W';
               $sgf_coord = @$node[$key][0];
               $game_sgf_parser->Moves[] = $key . $sgf_coord;
               $movenum++;
            }
            if ( isset($node['AB']) )
            {
               foreach ( @$node['AB'] as $sgf_coord )
                  $game_sgf_parser->SetBlack[] = $sgf_coord;
            }
            if ( isset($node['AW']) )
            {
               foreach ( @$node['AW'] as $sgf_coord )
                  $game_sgf_parser->SetWhite[] = $sgf_coord;
            }
            if ( isset($node['SZ']) && !$game_sgf_parser->Size )
               $game_sgf_parser->Size = (int)$node['SZ'][0];
            if ( isset($node['HA']) && is_null($parsed_HA) )
               $game_sgf_parser->Handicap = $parsed_HA = (int)$node['HA'][0];
            if ( isset($node['KM']) && is_null($parsed_KM) )
               $game_sgf_parser->Komi = $parsed_KM = (float)$node['KM'][0];
         }
      }

      return $game_sgf_parser;
   }//parse_sgf

}//end 'GameSgfParser'



if ( defined('ENABLE_STDHANDICAP') && ENABLE_STDHANDICAP ) {

// Read the standard handicap pattern file
// and convert it to a stonestring.
function get_handicap_pattern( $size, $handicap, &$err)
{
   $stonestring ='';
   if ( $handicap < 2 )
      return $stonestring;

   $game = array();

   $filename = "pattern/standard_handicap_$size.sgf";
   $sgf_data = @read_from_file( $filename, 0);
   if ( is_string($sgf_data) )
   {
      $sgf_parser = Sgf::sgf_parser( $sgf_data );
      $game = $sgf_parser->games;
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
   $game = $game[0]; //keep the first game only

   $nb = 0;
   $vars = array();
   Sgf::push_var_stack( $vars, $game, $nb);

   while ( list($nb,$var) = array_pop($vars) )
   {
      $stonestring = substr( $stonestring, 0, 2*$nb);

      //a variation is an array of nodes
      foreach ( $var as $id => $node )
      {
         if ( $id === SGF_VAR_KEY )
         {
            //this particular node is an array of variations
            for ( $i=count($node)-1; $i >= 0; $i-- )
               Sgf::push_var_stack( $vars, $node[$i], $nb);
            continue;
         }

         //a node is an array of properties
         if ( isset($node['B']) || isset($node['W']) )
         {
            $co = @$node['B'][0];
            if ( !$co )
               $co = @$node['W'][0];
            if ( strlen($co) != 2 )
               return $stonestring;
            $stonestring .= $co;
            $nb++;
            if ( $nb >= $handicap )
               return $stonestring;
         }
      }
   }
   //See previous error comment
   $err = sprintf( T_('Insufficient handicap pattern for %s'), "size=$size h=$handicap n=$nb" );
   return $stonestring;
}//get_handicap_pattern

}//ENABLE_STDHANDICAP


?>

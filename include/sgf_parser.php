<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/



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
  ValueType  = (None | Number | Real | Double | Color | SimpleText |
		Text | Point  | Move | Stone)
 

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


// Explode a sgf string ($sgf, from file) into an array of games ($games)
function sgf_parser( $sgf, &$games)
{
   $err= '';
   $games= array();

   $sgf_len= strlen($sgf);
   $i= 0;
   while( !$err && false !== ($i= strpos($sgf,SGF_VAR_BEG,$i)) )
   {
      $ivar= 0;
      $vars[$ivar]= array();

      $i++;
      if( !sgf_skip_space( $sgf, $i, $sgf_len) )
      {
         $err= 'bad end of file';
         break;
      }

      if( $sgf{$i} != SGF_NOD_BEG )
      {
         $err= 'bad root node';
         break;
      }

      $i++;
      $err= sgf_parse_node( $sgf, $i, $sgf_len, $node);
      if( $err )
         break;

      if( !isset($node['GM']) || @$node['GM'][0] != 1 )
      {
         $err= 'not a Go game (GM[1])';
         break;
      }

      foreach( array( //root default values
           'FF' => 4,
           'GM' => 1,
           'SZ' => 19,
           'RU' => 'Japanese',
         ) as $key => $arg)
      {
         if( !isset($node[$key]) )
            $node[$key]= array($arg);
      }

      $vars[$node_ivar=$ivar][]= $node;

      while( !$err && $ivar>=0 && $c=sgf_skip_space( $sgf, $i, $sgf_len) )
      {
         switch( $c )
         {
            case SGF_VAR_END:
               $ivar--; $i++;
               $vars[$ivar][SGF_VAR_KEY][]= $vars[$ivar+1];
               break;
            case SGF_VAR_BEG:
               $i++;
               if( SGF_NOD_BEG != sgf_skip_space( $sgf, $i, $sgf_len) )
               {
                  $err= 'bad node start';
                  break;
               }
               if( $node_ivar <= $ivar )
                  $vars[$ivar][SGF_VAR_KEY]= array();
               $ivar++;
               $vars[$ivar]= array();
            case SGF_NOD_BEG :
               $i++;
               $err= sgf_parse_node( $sgf, $i, $sgf_len, $node);
               $vars[$node_ivar=$ivar][]= $node;
               break;
            default :
               $err= 'syntax error';
               break;
         }
      }
      if( $err )
         break;

      if( $ivar>=0 )
      {
         $err= 'missing right parenthesis';
         break;
      }

      $games[]= $vars[0];
   }

   if( $err )
   {
      $tmp= max(0,$i-20);
      $err= "SGF: ".$err
         . "\nnear: "
         . ltrim(substr( $sgf, $tmp, $i-$tmp))
         . '<'
         . substr( $sgf, $i, 1)
         . '>'
         . rtrim(substr( $sgf, $i+1, 20))
         ; 
   }
   return $err;
}


// In short, it does the opposite of sgf_parser()
function sgf_builder( $games, &$sgf)
{
   $err= '';
   $sgf= '';

   $vars= array();
   //$games is an array of games (i.e. variations)
   for( $i=count($games)-1; $i>=0; $i-- )
   {
      sgf_var_push( $vars, $games[$i]);
   }

   while( $var=array_pop($vars) )
   {
      if( $var === SGF_VAR_END ) //see sgf_var_push()
      {
         $sgf.= SGF_VAR_END."\r\n";
         continue;
      }

      $sgf.= SGF_VAR_BEG."\r\n";
      //a variation is an array of nodes
      foreach( $var as $id => $node )
      {
         if( $id === SGF_VAR_KEY )
         {
            //this perticular node is an array of variations
            for( $i=count($node)-1; $i>=0; $i-- )
            {
               sgf_var_push( $vars, $node[$i]);
            }
            continue;
         }

         $sgf.= SGF_NOD_BEG."\r\n";
         //a node is an array of properties
         foreach( $node as $key => $args )
         {
            $sgf.= "$key";
            foreach( $args as $arg )
            {
               $sgf.= SGF_ARG_BEG.$arg.SGF_ARG_END;
            }
            $sgf.= "\r\n";
         }
      }
   }

   return $err;
}


function sgf_var_push( &$vars, &$var)
{
   if( is_array($var) )
   {
      $vars[]= SGF_VAR_END;
      $vars[]= &$var;
   }
}


function sgf_skip_space( $sgf, &$i, $l)
{
   while( $i<$l )
   {
      if( $sgf{$i} > ' ' )
         return $sgf{$i};
      $i++;
   }
   return 0;
}


function sgf_parse_node( $sgf, &$i, $l, &$node)
{
   $node= array();
   while( $key= sgf_parse_key( $sgf, $i, $l) )
   {
      $err= sgf_parse_args( $sgf, $i, $l, $args, $key);
      if( $err )
         return $err;
      $node[$key]= $args;
   }
   $c= $sgf{$i}; //sgf_skip_space( $sgf, $i, $l)
   if( $c != SGF_NOD_BEG &&  $c != SGF_VAR_BEG && $c != SGF_VAR_END )
   {
      return 'syntax error';
   }
   return '';
}


function sgf_parse_args( $sgf, &$i, $l, &$args, $key)
{
   $args= array();
   while( sgf_skip_space( $sgf, $i, $l) == SGF_ARG_BEG )
   {
      $j= $i;
      while( false !== ($j=strpos($sgf,SGF_ARG_END,$j+1)) )
      {
         if( $sgf{$j-1} != '\\' )
         {
            $arg= substr($sgf, $i+1, $j-$i-1);
            $args[]= $arg;
            $i= $j+1;
            break;
         }
      }
      if( false === $j )
         return 'missing right bracket';
   }
   return '';
}


function sgf_parse_key( $sgf, &$i, $l)
{
   $key= '';
   while( $i<$l )
   {
      $c= $sgf{$i};
      if( $c>='A' && $c<='Z' )
         $key.= $c;
      else if( ($c>' ') && ($c<'a' || $c>'z') )
         break;
      $i++;
   }
   return $key;
}

if( defined('ENA_STDHANDICAP') && ENA_STDHANDICAP ) {

function handicap_push( &$vars, &$var, $nb)
{
   if( is_array($var) )
   {
      $vars[]= array($nb, &$var);
   }
}

// Read the standard handicap pattern file
// and convert it to a stonestring.
function get_handicap_pattern( $size, $handicap, &$err)
{
   $stonestring='';
   $game= array();

   $filename = "pattern/standard_handicap_$size.sgf";
   if( file_exists( $filename ) )
   {
      $handle = fopen($filename, "r");
      $sgf = fread($handle, filesize($filename));
      fclose($handle);

      $err = sgf_parser( $sgf, $game);
   }
   else
   {
      $err = 'File not found';
   }
   if( $err ) {
      //error('handicap_pattern',"s=$size h=$handicap err=$err");
      //Simply returning the error message will allow
      //the player to manually add his handicap stones.
      $err= "Bad handicap pattern for size=$size h=$handicap err=$err";
      return $stonestring;
   }
   $game= $game[0]; //keep the first game only

   $nb= 0;
   $vars= array();
   handicap_push( $vars, $game, $nb);

   while( list($nb,$var)=array_pop($vars) )
   {
      $stonestring = substr( $stonestring, 0, 2*$nb);

      //a variation is an array of nodes
      foreach( $var as $id => $node )
      {
         if( $id === SGF_VAR_KEY )
         {
            //this perticular node is an array of variations
            for( $i=count($node)-1; $i>=0; $i-- )
            {
               handicap_push( $vars, $node[$i], $nb);
            }
            continue;
         }

         //a node is an array of properties
         if( isset($node['B']) || isset($node['W']) )
         {
            $co= @$node['B'][0];
            if( !$co )
               $co= @$node['W'][0];
            if( strlen($co) != 2 )
               return $stonestring;
            $stonestring.= $co;
            $nb++;
            if( $nb >= $handicap )
               return $stonestring;
         }
      }
   }
   //error('handicap_pattern',"s=$size h=$handicap n=$nb");
   //See previous error comment
   $err= "Insufficient handicap pattern for size=$size h=$handicap n=$nb";
   return $stonestring;
}

} // ENA_STDHANDICAP

?>

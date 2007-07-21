<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

require_once( "include/translation_functions.php" );

 /* Author: Jens-Uwe Gaspar */

 /*!
  * \file tokenizer.php
  *
  * \brief Classes and Functions for breaking up texts into tokens according to some syntax.
  *
  * \see Token
  * \see XmlTag
  *
  * \see BasicTokenizer
  * \see   StringTokenizer
  * \see   XmlTokenizer
  */

define('TOK_TEXT',      1); // literal-token
define('TOK_SEPARATOR', 2); // separator-token
// for XML
define('TOK_XMLTAG',    3); // xml-tag, token-value is a XmlTag-object
define('TOK_XMLENTITY', 4); // xml-entity (&xyz;)

$TOKEN_TYPES = array(
   TOK_TEXT       => 'TEXT',
   TOK_SEPARATOR  => 'SEP',
   TOK_XMLTAG     => 'XMLTAG',
   TOK_XMLENTITY  => 'XMLENT',
);

 /*!
  * \class Token
  *
  * \brief Representation of a token built within the Tokenizing-process.
  *
  * A Token-object is containing a type, the start- and end-position
  * the token has been found in the orig-text, the actual value of the
  * token (which can be a string or an object) and an optional
  * error-message assigned to the token.
  */
class Token
{
   /*! \brief type of token: TOK_TEXT | SEPARATOR | XMLTAG | XMLENTITY. */
   var $type;
   /*! \brief Object of type string | XmlTag, containing the parsed Token. */
   var $token;
   /*! \brief absolute starting pos of token in orig-text. */
   var $spos;
   /*! \brief absolute end pos of token in orig-text; -1=not-set, then get_endpos() is calculated by spos + len of token. */
   var $epos;
   /*! \brief optional error detected while parsing token */
   var $error;

   /*! \brief Constructing Token( TOK_... type, int spos, string|object token) with specified $type, starting pos and token-value. */
   function Token( $type, $spos, $token = '' )
   {
      $this->type  = $type;
      $this->token = $token;
      $this->spos  = $spos;
      $this->epos  = -1;
      $this->error = '';
   }

   /*!
    * \brief Returning type of token.
    * signature: string|int get_type( [ bool as_text ] );
    */
   function get_type( $as_text = false )
   {
      global $TOKEN_TYPES;
      return ($as_text) ? $TOKEN_TYPES[$this->type] : $this->type;
   }

   /*!
    * \brief Return token (as ref).
    * signature: Token get_token();
    */
   function &get_token()
   {
      return $this->token;
   }

   /*! \brief Return error-string associated with this token. */
   function get_error()
   {
      return $this->error;
   }

   /*! \brief Returns true, if token has an assigned error. */
   function has_error()
   {
      return (bool)( $this->error != '' );
   }

   /*!
    * \brief Sets token-value.
    * signature: set_token( Token token );
    */
   function set_token( $token )
   {
      $this->token = $token;
   }

   /*! \brief Sets int end-position where token was found in orig-text. */
   function set_endpos( $endpos )
   {
      $this->epos = $endpos;
   }

   /*! \brief Returns int end-position where token was found in orig-text. */
   function get_endpos()
   {
      if ( $this->epos < 0 )
         return $this->spos + strlen($this->token) - 1;
      else
         return $this->epos;
   }

   /*! \brief Sets error-string for this token. */
   function set_error( $error )
   {
      $this->error = $error;
   }

   /*! \brief Returns string-representation of this token for debugging. */
   function to_string()
   {
      $v = (method_exists($this->token, 'to_string')) ? $this->token->to_string() : $this->token;
      return "{" . $this->get_type(true)
         . ": pos=[{$this->spos}" . (($this->epos >= 0) ? "..{$this->epos}" : "], endpos=[".$this->get_endpos()."]" ) . "], "
         . "[$v] "
         . ($this->error != '' ? ":" . T_('Error#filter') . " {$this->error}" : "") . "} ";
   }
} // end of 'Token'



 /*!
  * \class BasicTokenizer
  *
  * \brief Abstract base-class for Tokenizer.
  *
  * Basic principle of a Tokenizer:
  *   A string input-value $value is parsed and broken into several
  *   tokens stored in array $tokens.
  *
  * Example:
  *   $tokenizer = new XYZTokenizer(tok-config, other args);
  *   $success = $tokenizer->parse( value );
  *   if ( !$success )
  *      handle_errors( $tokenizer->errors );
  *   $token_arr = $tokenizer->tokens(); // array of Token-objects
  *
  * \see code_examples/tokenizer_example.php
  */
class BasicTokenizer
{
   /*! \brief Additional config-array: ( key => val ). */
   var $config;

   /*! \brief orig-text-value to parse and tokenize. */
   var $value;
   /*! \brief Token[] */
   var $tokens;
   /*! \brief string[] */
   var $errors;

   function BasicTokenizer()
   {
      $this->config = array();
      $this->init_parse("");
   }

   /*! \brief Re-inits parsing process with specified value-string. */
   function init_parse( $value )
   {
      $this->value = $value;
      $this->errors = array();
      $this->tokens = array();
   }

   /*!
    * \brief Adds config as key-value-pair.
    * signature: add_config( string key, string val)
    */
   function add_config( $key, $val )
   {
      $this->config[$key] = $val;
   }

   /*!
    * \brief Returns configuration for key; if not set, return empty string.
    * signature: mixed get_config( string key, [mixed defval=''] )
    */
   function get_config( $key, $defval = '' )
   {
      return (isset($this->config[$key])) ? $this->config[$key] : $defval;
   }

   /*!
    * \brief Returns Token-array stored within this Tokenizer.
    * signature: Token[] tokens();
    */
   function tokens()
   {
      return $this->tokens;
   }

   /*! \brief Returns number-count of parsed token-entries. */
   function size()
   {
      return count($this->tokens);
   }

   /*! \brief Returns string-array with error-message if parse() failed. */
   function errors()
   {
      return $this->errors;
   }

   /*!
    * \brief abstract method to append Tokenizer-specifics for to_string()
    * \internal
    */
   function to_string_local()
   {
      return '';
   }

   /*!
    * \brief Returns string-representation showing internal-vars for Tokenizer.
    * \see to_string_local()
    */
   function to_string()
   {
      $arrtok = array();
      foreach( $this->tokens as $tok )
         array_push( $arrtok, $tok->to_string() );
      return get_class($this) .
         $this->to_string_local() . " " .
         "value=[{$this->value}], errors=[" . implode('; ', $this->errors) . "],\n" .
         "tokens=[" . implode(', ', $arrtok) . "]";
   }

   // interfaces

   /*!
    * \brief Main function to parse specified $value into \see $tokens and $errors.
    * signature: interface abstract bool success = parse(string value);
    */
   function parse( $value )
   {
      error("ERROR: BasicTokenizer-class is abstract");
   }
} // end of 'BasicTokenizer'



 /*!
  * \class StringTokenizer
  *
  * \brief Tokenizer to parse and tokenize standard texts with (three) standard quoting-mechanisms.
  * <p>Optional config:
  * - add_config( STRTOK_CONF_RX_NO_SEP, 'regex' ) to overrule separator-char by matching regex
  *
  * <p>Parsing and handling of quoting and/or escaping of strings:
  * <pre>
  *   Allowed Patterns (examples):
  *      Text: a-b, a-, -a, abc, a*b;     valid-chars: a-zA-Z0-9-_
  *      Num:  a-b, a-, -a, abc;          valid-chars: 0-9-
  *      Rate: a-b, a-, -a, abc;          valid-chars: 0-9k
  *      Date: a-b, a-, -a, abc;          valid-chars: 0-9 :-
  *
  *   Quoting needed:
  *      chars:   a-z A-Z 0-9 `~!@#$%^&*(-)_=+[{]};'\:"|,./<>?
  *      invalid:             `~! #$ ^ *( ) = [{]}; \  |,./  ?
  *
  *      Text: '-' im Namen ->
  *         a--a-b   => sep = '-', type=double
  *         "a-a"-b  => sep = '-', type=quote
  *         a\-a-b   => sep = '-', type=escape
  *
  * NOTE: Tokenizer and Parser parses in one pass without stopping (not on the fly using iterator).
  *
  * Examples:
  *    $tokenizer = new StringTokenizer(quote_type, split_chars, spec_chars, quote_chars='""', escape_chars='\\\\')
  *
  *    // using split_chars, spec_chars, escape_char, ignore quote_chars
  *    $tokenizer = new StringTokenizer(QUOTETYPE_DOUBLE, '-', '-*');
  *
  *    // using split_chars, spec_chars, quote_chars, escape_char
  *    $tokenizer = new StringTokenizer(QUOTETYPE_QUOTE,  '-', '*');
  *
  *    // using split_chars, spec_chars, quote_chars, escape_char
  *    $tokenizer = new StringTokenizer(QUOTETYPE_ESCAPE, '-', '*', '\\');
  * </pre>
  *
  * \see code_examples/tokenizer_example.php
  */

// defining ways to escape special-chars:
define('QUOTETYPE_DOUBLE', 1); // escape by doubling char, e.g. **
define('QUOTETYPE_QUOTE',  2); // escape by enclosing in quotes, e.g. "a-"-c
define('QUOTETYPE_ESCAPE', 3); // escape by prefixing with escape-char, e.g. a\-c
define('TOKENIZE_TYPEMASK', 0x7); // bitmask for storing quote-types

define('TOKENIZE_WORD_RX', 0x08); // flag to use word-regex tokenizing (can be mixed with quote-types)

define('STRTOK_CONF_RX_NO_SEP', 'strtokconf_rx_no_sep'); // regex that overrules match for range-separator

class StringTokenizer extends BasicTokenizer
{
   /*! \brief Escape-type used to quote special-chars. */
   var $quote_type;
   /*! \brief additional parser-flags. */
   var $flags;
   /*! \brief string with split-chars, splitting parsed-value into separate tokens; splitting-char parsed into TOK_SEP-token. */
   var $split_chars;
   /*! \brief string with special-chars, included into parsed string, but need quoting of some kind. */
   var $spec_chars;
   /*! \brief string with start/end-quote-char, used to escape/quote values (if only one char, use same as end-quote). */
   var $quote_chars;
   /*! \brief two chars representing from- and to-escape-char to escape special/split-chars with. */
   var $escape_chars;

   /*! \brief regex for not-matching char of 'word' (considered as separator). */
   var $rxsep;

   /*!
    * \brief Constructs StringTokenizer with specified args.
    * signature: StringTokenizer( QUOTETYPE_..., string split_chars, string spec_chars, [string escape_chars], [string quote_type] )
    * param $quote_type
    *    for QUOTE_DOUBLE: quote_chars ignored \n
    *    for QUOTE_QUOTE: \n
    *    for QUOTE_ESCAPE: spec_chars are escaped, quote_chars contain prefix-char indicating need to escape
    *    optional flag ORed with quote_type: TOKENIZE_WORD_RX (split_chars contains char-regex forming a word, separator is negating those chars)
    */
   function StringTokenizer( $quote_type, $split_chars, $spec_chars, $escape_chars = '\\\\', $quote_chars = '""' )
   {
      parent::BasicTokenizer();
      $this->quote_type   = ( $quote_type & TOKENIZE_TYPEMASK );
      $this->flags        = $quote_type & ~TOKENIZE_TYPEMASK;
      $this->split_chars  = $split_chars;
      $this->spec_chars   = $spec_chars;
      $this->escape_chars = $escape_chars;
      $this->quote_chars  = $quote_chars;
      $this->rxsep        = '';

      if ( $this->flags & TOKENIZE_WORD_RX )
      {
         $this->rxsep = ($split_chars) ? "/[^{$split_chars}]/i" : '';
         $this->split_chars = '';
      }
   }

   /*!
    * \brief Returns true, if parsing specified value into tokens was successful.
    * signature: interface bool success = parse(value);
    */
   function parse( $value )
   {
      $this->init_parse($value);

      $success = false;
      if ( $this->quote_type == QUOTETYPE_DOUBLE )
         $success = $this->parse_quote_double();
      elseif ( $this->quote_type == QUOTETYPE_QUOTE )
         $success = $this->parse_quote_quote();
      elseif ( $this->quote_type == QUOTETYPE_ESCAPE )
         $success = $this->parse_quote_escape();
      else
         error("Unknown quote-type [{$this->quote_type}]");

      return $success;
   }

   function to_string_local()
   {
      return "(type={$this->quote_type}, flags=[{$this->flags}], split[{$this->split_chars}], special[{$this->spec_chars}], " .
         "quote[{$this->quote_chars}], esc[{$this->escape_chars}], rxsep=[{$this->rxsep}])\n";
   }


   // ------------  quote-parser  ---------------

   /*!
    * \brief returns true, if passed char is a splitting-char and optional
    *        no-sep-regex doesn't match on substring (must start at same
    *        pos as passed char).
    */
   function is_split_char( $char, $substr )
   {
      if ( (string)$this->rxsep != '')
         $is_splitter = preg_match($this->rxsep, $char);
      else
         $is_splitter = !(strpos($this->split_chars, $char) === false);
      return (bool) ( $is_splitter and !$this->match_rx_no_sep( $substr ) );
   }

   /*!
    * \brief Returns true, if passed string matches optionally given
    *        case-insensitive regex-pattern to allow separator-char.
    * <p>Pattern specified with this->add_config(STRTOK_CONF_RX_NO_SEP, 'regex')
    */
   function match_rx_no_sep( $str )
   {
      $rx = $this->get_config(STRTOK_CONF_RX_NO_SEP);
      return (bool) ( (string)$rx != '' and preg_match( "/^($rx)/i", $str ) );
   }

   /*!
    * \brief Escapes a string using double-quoting.
    * \internal
    *
    * Principle: quoting by doubling special-char
    * signature: bool success = parse_quote_double()
    * note: using spec_chars, split_chars, escape_chars
    * Example:  a--a-b  (sep='-')  -> tokens = ( TOK_TEXT(a-a), TOK_SEP(-), TOK_TEXT(b) )
    */
   function parse_quote_double()
   {
      $spos = 0; // orig-start-pos
      $tok = ''; // current token
      $len = strlen($this->value);

      for( $pos=0; $pos < $len; $pos++)
      {
         // note: $this->value{pos} works only with const-pos
         $char = substr( $this->value, $pos, 1 );

         // check for separator
         if ( $this->is_split_char( $char, substr( $this->value, $pos ) ) ) // separator found
         {
            if ( ($pos + 1 < $len) and (substr($this->value,$pos+1,1) == $char) ) // need escape
            {
               $tok .= $char;
               $pos++;
               continue;
            }

            if ( (string)$tok != '' )
               array_push( $this->tokens, new Token(TOK_TEXT, $spos, $tok) );
            $spos = $pos;
            $tok = '';
            array_push( $this->tokens, new Token(TOK_SEPARATOR, $spos, $char) );
            continue;
         }

         // check for double special-char
         if ( !(strpos($this->spec_chars, $char) === false) )
         {
            if ( ($pos + 1 < $len) and (substr($this->value,$pos+1,1) == $char) ) // need escape
            {
               $tok .= $this->escape_chars{1} . $char;
               $pos++;
               continue;
            }
         }

         $tok .= $char;
      }

      if ( (string)$tok != '' )
         array_push( $this->tokens, new Token(TOK_TEXT, $spos, $tok) );
      return true;
   }

   /*!
    * \brief Escapes a string using quote-chars.
    * \internal
    *
    * Principle: quoting by quoting chars (using start/end-quote-char)
    * signature: bool success = parse_quote_quote();
    * note: using spec_chars, split_chars, quote_chars, escape_chars
    * note: double quote_start can be used to escape quote
    * Example:  "a-a"-b  (sep='-')  -> tokens = ( TOK_TEXT(a-a), TOK_SEP(-), TOK_TEXT(b) )
    */
   function parse_quote_quote()
   {
      $quote_start = $this->quote_chars{0};
      $quote_end = (strlen($this->quote_chars) > 1) ? $this->quote_chars{1} : $quote_start;
      $spos = 0; // orig-start-pos
      $tok = ''; // current token
      $len = strlen($this->value);
      $quote_begin = 0;

      for( $pos=0; $pos < $len; $pos++)
      {
         // note: $this->value{pos} works only with const-pos
         $char = substr( $this->value, $pos, 1 );

         if ( $char == $quote_start and ($pos+1 < $len) and substr($this->value,$pos+1,1) == $quote_start ) // double-quote
         {
            $tok .= $quote_start;
            $pos++;
            continue;
         }

         if ( $quote_begin == 0 and $char == $quote_start )
         {
            $quote_begin++;
            continue;
         }
         elseif ($quote_begin == 1 and $char == $quote_end )
         {
            $quote_begin--;
            continue;
         }

         if ( strpos($this->spec_chars, $char) !== false ) // special-char found
         {
            if ( $quote_begin > 0 ) // quoted
               $tok .= $this->escape_chars{1};
            $tok .= $char;
            continue;
         }

         if ( $this->is_split_char( $char, substr( $this->value, $pos ) ) ) // separator found
         {
            if ( $quote_begin > 0 )
            { // quoted
               $tok .= $char;
            }
            else
            { // unquoted
               if ( (string)$tok != '' )
                  array_push( $this->tokens, new Token(TOK_TEXT, $spos, $tok) );
               $spos = $pos;
               $tok = '';
               array_push( $this->tokens, new Token(TOK_SEPARATOR, $spos, $char) );
            }
         } else {
            $tok .= $char;
         }
      }
      if ( (string)$tok != '' )
         array_push( $this->tokens, new Token(TOK_TEXT, $spos, $tok) );

      if ($quote_begin != 0)
      {
         array_push( $this->errors, "[{$this->value}] " . T_('using bad quoting') );
         return false;
      }

      return true;
   }

   /*!
    * \brief Escapes a string using escape-char.
    * \internal
    *
    * Principle: quoting by using escape-char
    * signature: bool success = parse_quote_double();
    * note: using spec_chars, split_chars, quote_chars, escape_chars
    * Example:  a\-a-b   (sep='-')  -> tokens = ( TOK_TEXT(a-a), TOK_SEP(-), TOK_TEXT(b) )
    */
   function parse_quote_escape()
   {
      $esc_char = $this->escape_chars{0};
      $spos = 0; // orig-start-pos
      $tok = ''; // current token
      $len = strlen($this->value);
      for( $pos=0; $pos < $len; $pos++)
      {
         // note: $this->value{pos} works only with const-pos
         $char = substr( $this->value, $pos, 1 );

         if ( $char == $esc_char ) // found escape-char
         {
            if ( $pos + 1 < $len ) // need escape
            {
               $next_char = substr($this->value, $pos+1, 1);
               if ( $next_char == $esc_char or ( strpos($this->spec_chars, $next_char) !== false ) )
                  $tok .= $this->escape_chars{1}; // special-char
               $tok .= $next_char;
               $pos++;
               continue;
            }
         }

         if ( $this->is_split_char( $char, substr( $this->value, $pos ) ) ) // separator found
         {
            if ( (string)$tok != '' )
               array_push( $this->tokens, new Token(TOK_TEXT, $spos, $tok) );
            $spos = $pos;
            $tok = '';
            array_push( $this->tokens, new Token(TOK_SEPARATOR, $spos, $char) );
         }
         else
            $tok .= $char;
      }

      if ( (string)$tok != '' )
         array_push( $this->tokens, new Token(TOK_TEXT, $spos, $tok) );
      return true;
   }
} // end of 'StringTokenizer'



// ----------------------------------------------------------------------
//            XML - Tokenizer
// ----------------------------------------------------------------------

 /*!
  * \class XmlTag
  *
  * \brief Representation of a XML-tag with name, attributes and if it's a start or end-tag.
  * note: Used as Token-value in resulting tokens-array for XmlTokenizer
  *
  * Example:
  *    '<tag a1=1>' -> XmlTag { name='tag', attributes=( a1 => 1), is_start=1, is_end=0 }
  */
class XmlTag
{
   /*! \brief tag-name. */
   var $name;
   /*! \brief array with only unique attributes allowed: ( attrname => attrvalue ). */
   var $attributes;
   /*! \brief true, if is a start-tag */ # for: <tag>  or <tag/>
   var $is_start;
   /*! \brief true, if is an end-tag  */ # for: </tag> or <tag/>
   var $is_end;

   function XmlTag()
   {
      $this->name = '';
      $this->is_start = false;
      $this->is_end   = false;
      $this->attributes = array();
   }

   // signature: add_attribute( string attrname, string attrvalue );
   function add_attribute( $attrname, $attrvalue )
   {
      $this->attributes[$attrname] = $attrvalue;
   }

   function to_string()
   {
      $arr_attr = array();
      foreach( $this->attributes as $k => $v )
         array_push( $arr_attr, "$k=[$v]" );
      return "XmlTag(name={$this->name}, is_start/end={$this->is_start}/{$this->is_end}, attributes=\{" . implode(", ", $arr_attr). "}) ";
   }
} // end of 'XmlTag'



 /*!
  * \class XmlTokenizer
  *
  * \brief Tokenizer to parse and tokenize XML-texts into Tokens.
  *
  * Example:
  * <pre>
  *    $tokenizer = new XmlTokenizer();
  *    $success = $tokenizer->parse( "some <xml> ..." );
  *    if ($sucess)
  *       echo $tokenizer->to_string();
  *    else
  *       echo implode( "\n", $tokenizer->errors() );
  *
  * Parsing-Example:
  *    $tokenizer->parse( "some <xml>&quot;</xml> here" );
  *    $tokenizer->tokens()  -> gives an array with Token-objects:
  *       Token( TOK_TEXT, 'some ' ),
  *       Token( TOK_XMLTAG, XmlTag( 'xml', is_start=1, is_end=0 ) ),
  *       Token( TOK_XMLENTITY, '&quot;' ),
  *       Token( TOK_XMLTAG, XmlTag( 'xml', is_start=0, is_end=1 ) ),
  *       Token( TOK_TEXT, ' here' )
  *
  * </pre>
  *
  * \see code_examples/tokenizer_example.php
  */
class XmlTokenizer extends BasicTokenizer
{
   /*! \brief origingla start-pos for parsing within value. */
   var $spos;
   /*! \brief current parsing-pos within value. */
   var $pos;
   /*! \brief length of value. */
   var $len;
   /*! \brief current token-string. */
   var $tok;

   /*! \brief Constructs XmlTokenizer. */
   function XmlTokenizer()
   {
      parent::BasicTokenizer();
   }

   function to_string_local()
   {
      return " pos={$this->pos}, ";
   }

   /*!
    * \brief Returns true, if parsing specified value into tokens was successful.
    * signature: interface bool success = parse(string value);
    */
   function parse( $value )
   {
      $this->init_parse($value);
      $this->spos = 0; // orig-start-pos
      $this->pos = 0;
      $this->len = strlen($this->value);
      $this->tok = '';

      while ( $this->pos < $this->len )
      {
         // note: $this->value{pos} works only with const-pos
         $char = substr( $this->value, $this->pos, 1);

         if ( $char == '<' ) // xml-tag
         {
            $oldpos = $this->pos;
            $token_xml_tag = $this->eat_tag();
            if ( $token_xml_tag->has_error() )
               $this->pos = $oldpos; // if parse-error eat as normal-char
            else
            {
               if ( (string)$this->tok != '' )
                  array_push( $this->tokens, new Token(TOK_TEXT, $this->spos, $this->tok) );
               $this->spos = $this->pos;
               $this->tok = '';
               array_push( $this->tokens, $token_xml_tag );
               continue;
            }
         }

         if ( $char == '&' ) // entity
         {
            $oldpos = $this->pos;
            $token_xml_ent = $this->eat_entity();
            if ( $token_xml_ent->has_error() )
               $this->pos = $oldpos; // if parse-error eat as normal-char
            else
            {
               if ( (string)$this->tok != '' )
                  array_push( $this->tokens, new Token(TOK_TEXT, $this->spos, $this->tok) );
               $this->spos = $this->pos;
               $this->tok = '';
               array_push( $this->tokens, $token_xml_ent );
               continue;
            }
         }

         // eat normal char
         $this->tok .= $char;
         $this->pos++;
      }
      if ( (string)$this->tok != '' )
         array_push( $this->tokens, new Token(TOK_TEXT, $this->spos, $this->tok) );

      // encountered errors
      if ( count($this->errors) > 0 )
         return false;

      return true;
   }

   // parsing XML-entity: "&xyz;", eat-up entity from current pos in parsing value
   // signature: Token eat_entity();
   function eat_entity()
   {
      static $rx_entity = '/^(\&[a-z0-9-_]*;)/i';
      $val = substr( $this->value, $this->pos );
      $token = new Token(TOK_XMLENTITY, $this->pos);

      // parse entity
      $out = array();
      if ( preg_match($rx_entity, $val, $out) == 0)
      {
         $token->set_error("[" . substr($val, 0, 10) . "]: " .
            T_('invalid XML-entity at position #') . ($this->pos+1) );
         array_push( $this->errors, $token->get_error() );
         $token->set_token( $val );
         $this->pos++; // just skip '&'
      }
      else
      {
         $token->set_token( $out[1] );
         $this->pos += strlen($out[1]);
      }
      return $token;
   }

   // eat-up tag from current pos in parsing value, returning null on fatal-error.
   //   parsing XML-tag: <tag [ attr=val|"val"|'val' ...]>  <tag/>
   // signature: Token(tagname,XmlTag)|null eat_tag();
   // note: also sets token-endpos
   function eat_tag()
   {
      $spos = $this->pos; // start-pos
      $token  = new Token(TOK_XMLTAG, $this->pos);
      $xmltag = new XmlTag();

      $xmltag->is_start = 1;
      $xmltag->is_end   = 0;
      $this->pos++; // skip '<'
      if ( $this->pos >= $this->len )
      {
         $token->set_error( "[<]: " . T_('invalid XML-tag at position #') . ($spos+1) );
         array_push( $this->errors, $token->get_error() );
         $token->set_token($xmltag);
         return $token;
      }

      /*
      // non-XML(!): check for special-tag <http://..>
      //   parsing XML-http-tag: <http://www/abc>
      if ( substr($this->value, $this->pos, 7) === "http://" )
      {
         $epos = strpos( $this->value, '>', $this->pos );
         if ( $epos === false )
         {
            $token->set_error( "[" . substr($this->value, $spos, $this->pos - $spos + 15) . "]: "
               . T_('invalid http-XML-tag at position #') . ($spos+1) );
            array_push( $this->errors, $token->get_error() );
         }
         else
         {
            $xmltag->is_end = 1;
            $xmltag->name = substr( $this->value, $this->pos, $epos - $this->pos );
            $token->set_endpos( $epos );
            $this->pos = $epos + 1;
         }

         $token->set_token($xmltag);
         return $token;
      }
      */

      // check for: </endtag>
      if ( substr($this->value,$this->pos,1) == '/' )
      {
         $xmltag->is_end   = 1;
         $xmltag->is_start = 0;
         $this->pos++; // skip '/'
         if ( $this->pos >= $this->len )
         {
            $token->set_error( "[</]: " . T_('invalid XML-tag at position #') . ($spos+1) );
            array_push( $this->errors, $token->get_error() );
            $token->set_token($xmltag);
            return $token;
         }
      }

      // parse tagname (may be empty)
      $out = array();
      preg_match('/^([^\s\/>]*)/', substr($this->value, $this->pos), $out); // always matches
      $xmltag->name = $out[1];
      $this->pos += strlen($out[0]);

      // check for: / > attr
      $valid = false;
      while ( $this->pos < $this->len )
      {
         if ( ctype_space(substr($this->value,$this->pos,1)) ) // skip spaces
         {
            $this->pos++;
            continue;
         }

         // check for: > (tag ended)
         if ( substr($this->value,$this->pos,1) == '>' )
         {
            $this->pos++;
            $valid = true;
            break;
         }

         // check for: /
         if ( substr($this->value,$this->pos,1) == '/' )
         {
            if ($xmltag->is_end)
            {
               $token->set_error(
                  "[" . substr($this->value, $spos, $this->pos - $spos + 10) . "]: " .
                  T_('invalid XML-end-tag at position #') . ($spos+1) );
               array_push( $this->errors, $token->get_error() );
               $token->set_token($xmltag);
               return $token;
            }

            $xmltag->is_end = 1;
            $this->pos++;
            continue;
         }

         // parse attribute
         if ( !$this->eat_attribute( $token, $xmltag, $spos) )
         {
            $token->set_token($xmltag);
            return $token;
         }
      }

      if ( !$valid )
      {
         $token->set_error(
            "[" . substr($this->value, $spos, $this->pos - $spos + 10) . "]: " .
            T_('invalid XML-tag at position #') . ($spos+1) );
         array_push( $this->errors, $token->get_error() );
      }

      $token->set_endpos( $this->pos - 1 );
      $token->set_token($xmltag);
      return $token;
   }

   // parsing XML-tag-attribute: attr | attr=[val] | attr='val' | attr="val"
   //    eat-up attribute from current pos in parsing value
   // signature: bool success = eat_attribute(xmltag_token, $xmltag, tagstartpos);
   // note: no check for unique attributes
   function eat_attribute( &$token, &$xmltag, $spos )
   {
      $out = array();
      if ( preg_match( '/^([^\s=\/>]*)?="([^"]*)"/', substr($this->value, $this->pos), $out ) != 0 ) // 1. key = "val"
         $xmltag->add_attribute( $out[1], $out[2] );
      elseif ( preg_match( '/^([^\s=\/>]*)?=\'([^\']*)\'/', substr($this->value, $this->pos), $out ) != 0 ) // 2. key = 'val'
         $xmltag->add_attribute( $out[1], $out[2] );
      elseif ( preg_match( '/^([^\s=\/>]*)?=([^\s\/>]*)/', substr($this->value, $this->pos), $out ) != 0 ) // 3. key = val
         $xmltag->add_attribute( $out[1], $out[2] );
      elseif ( preg_match( '/^([^\s=\/>]+)?/', substr($this->value, $this->pos), $out ) != 0 ) // 4. key
         $xmltag->add_attribute( $out[1], 1 );
      else
      {
         $errormsg =
            "[" . substr($this->value, $spos, $this->pos - $spos + 10) . "]: " .
            T_('invalid XML-tag-attribute at position #') . ($spos+1);
         $token->set_error( (($token->get_error() != '') ? $token->get_error() . "; " : "") . $errormsg); // append error
         array_push( $this->errors, $errormsg );
         return false;
      }

      $this->pos += strlen($out[0]);

      return true;
   }
} // end of 'XmlTokenizer'

?>

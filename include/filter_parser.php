<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

 /* Author: Jens-Uwe Gaspar */

require_once( "include/quick_common.php" );
require_once( "include/filter_functions.php" );
require_once( "include/tokenizer.php" );

 /*!
  * \file filter_parser.php
  *
  * \brief Classes and special functions to help in parsing texts and numerics; mainly used for filters.
  *
  * \see TokenizerConfig
  * \see BasicParser
  * \see   NumericParser
  * \see   TextParser
  * \see DateParser
  */

// constants for parsers

// the following consts use the same bitmask-scope (general scope 'PARSER_'):
//    express flags, so that omitting them is the default
define('PARSER_NOSWAP_REVERSE',       0x00000001); // if set, don't swap start/end if start > end
define('TEXTPARSER_FORBID_RANGE',     0x00001000); // for numeric/text/date-parser: if set, forbid range-syntax
define('TEXTPARSER_FORBID_WILD',      0x00002000); // for text-parser: if set, forbid wildcards
define('TEXTPARSER_ALLOW_START_WILD', 0x00004000); // for text-parser: if set, allow search-string to start with wildcard
define('TEXTPARSER_END_INCL',         0x00008000); // for text-parser: if not set, make end-range exclusive in the search to build '>' <-> '>='
define('TEXTPARSER_IMPLICIT_WILD',    0x00010000); // for text-parser: if set, implicit wildcards are added to start and end 'foo' -> '*foo*' (forces TEXTPARSER_FORBID_RANGE and TEXTPARSER_ALLOW_START_WILD)

define('PFLAG_WILDCARD',   0x00000001); // if set, indicates that parsed value contained wildcard
define('PFLAG_EXCL_START', 0x00000002); // if set, indicates that parsed start-value should use exclusive SQL-comparator
define('PFLAG_EXCL_END',   0x00000004); // if set, indicates that parsed end-value should use exclusive SQL-comparator



/*!
 * \brief returns token-array with range: val1-val2, -val2, val1-
 * signature: ( Token1, Token2 ) = extract_range(token_array)
 */
function extract_range( $arr )
{
   if( count($arr) == 3 )
      return array( $arr[0]->get_token(), $arr[2]->get_token() );

   if($arr[0]->get_type() == TOK_SEPARATOR)
      return array( '', $arr[1]->get_token() );
   else
      return array( $arr[0]->get_token(), '' );
}

/*!
 * \brief constructs StringTokenizer
 * signature: StringTokenizer createStringTokenizer( TokenizerConfig tok_config, string special_chars, int flags)
 * param flags: TEXTPARSER_FORBID_RANGE
 */
function create_StringTokenizer( $tc, $spec_chars, $flags = 0 )
{
   $sep = ( $flags & TEXTPARSER_FORBID_RANGE ) ? '' : $tc->sep;
   $tokenizer = new StringTokenizer($tc->quotetype, $sep, $spec_chars, $tc->escape_chars, $tc->quote_chars);
   return $tokenizer;
}



 /*!
  * \class TokenizerConfig
  *
  * \brief Configuration used for StringTokenizer.
  */
class TokenizerConfig
{
   /*! \brief quote-type. */
   var $quotetype;
   /*! \brief separator, default is '-' */
   var $sep;
   /*! \brief wildcard-char, default is '*' */
   var $wild;
   /*! \brief chars used to quote; default are "" */
   var $quote_chars;
   /*! \brief chars to escape; default are \\ */
   var $escape_chars;

   /*! \brief Additional config-array: ( key => val ). */
   var $config;

   /*!
    * \brief Constructs TokenizerConfig( QUOTETYPE_..., [string separator-char='-'], [quote_chars="''"], [esc_chars='\\\\'] )
    * \param $quotetype QUOTETYPE_..-const
    * \param $sep separator-char to represent range-syntax; if null, default is '-'
    * \param $wild wildcard-char; if null, default is '*'
    * \param $quote_chars start- and end-quote-char; if null, default is "''" (single-quotes)
    * \param $esc_chars escape-from- and escape-to-char; if null, default is \\ (backslash)
    */
   function TokenizerConfig( $quotetype, $sep = null, $wild = null, $quote_chars = null, $esc_chars = null )
   {
      $this->quotetype = $quotetype;
      $this->sep       = (is_null($sep)) ? '-' : $sep;
      $this->wild      = (is_null($wild)) ? TEXT_WILD_M : $wild;
      $this->quote_chars  = (is_null($quote_chars)) ? "''" : $quote_chars;
      $this->escape_chars = (is_null($esc_chars)) ? '\\\\' : $esc_chars;
      $this->config = array();
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
} // end of 'TokenizerConfig'



 /*!
  * \class BasicParser
  *
  * \brief abstract Basic-Parser providing some basic vars and functions used to support syntax for Filters.
  * Provides an interface-method 'parse(value,flags)' to parse a value follow some basic syntax.
  * note: next layer of functions based on Tokenizers.
  *
  * Basic principle of Parser:
  *    Parser is constructed with configuration needed for parsing.
  *    $parser = new XYZParser( value, TokenizerConfig, int flags);
  *    $success = parse( input_string, parser_flags );
  *    if( !$success )
  *       handle_errors( $parser->errormsg() );
  */
class BasicParser
{
   /*! \brief orig-value to parse. */
   var $value;
   /*! \brief TokenizerConfig */
   var $tokconf;
   /*! \brief Flags for parsing, e.g.: PARSER_NOSWAP_REVERSE, TEXTPARSER_... */
   var $flags;

   // parsed values and flags

   /*! \brief error-message if parsing-error occured; if non-empty, parsing-output is invalid. */
   var $errormsg;
   /*! \brief start-range (>=) */
   var $p_start;
   /*! \brief end-range (<=) */
   var $p_end;
   /*! \brief exact-value or wildcard-value */
   var $p_value;
   /*! \brief flags about parsed value: PFLAG_WILDCARD */
   var $p_flags;
   /*! \brief non-null array with search rx_terms. */
   var $p_terms;

   /*! \brief Constructs BasicParser for specified value, TokenizerConfig and flags. */
   function BasicParser( $value, $tokconf, $flags = 0 )
   {
      $this->init_parse( $value, $flags );
      $this->tokconf = $tokconf;

      // abstract $this->parse($value);
   }

   /*!
    * \brief reset values for parsing; value is trimmed.
    * signature: init_parse(string val)
    */
   function init_parse( $_value, $_flags = 0 )
   {
      $this->value = trim($_value);
      $this->flags = $_flags;
      $this->errormsg = '';

      $this->p_start = '';
      $this->p_end = '';
      $this->p_value = '';
      $this->p_flags = 0;
      $this->p_terms = array();
   }

   /*!
    * \brief returns non-empty string, if parsing-error occured.
    * signature: string errormsg()
    */
   function errormsg()
   {
      return $this->errormsg;
   }

   /*!
    * \brief Returns true, if specified flag in parsing-flags set.
    * signature: bool is_flags_set()
    */
   function is_flags_set( $flag ) {
      return (bool)( $this->flags & $flag );
   }

   /*!
    * \brief Returns null on error; otherwise bool indicating if specified flag in parsed-flags ($p_flags)
    * signature: bool|null = is_parsed_flags_set()
    */
   function is_parsed_flags_set( $flag ) {
      if( $this->errormsg != '' )
         return null;
      return (bool)( $this->p_flags & $flag );
   }


   // interfaces

   /*!
    * \brief parses value with specified flags, updates: value, flags, errormsg, p_start/p_end/p_value/p_flags.
    * signature: interface bool success = parse(string value, int flags)
    */
   function parse( $value, $flags = 0) {
      // concrete parser needs to implement this abstract method
      error('invalid_filter', "filter_parser.parse.miss_implementation(".get_class($this).")");
   }


   // help-functions

   /*!
    * \brief Swaps p_start and p_end if range reversed and flag PARSER_NOSWAP_REVERSE set
    *        and returns true, if swapped; false otherwise.
    * signature: void handle_reverse_range([bool force = false])
    */
   function handle_reverse_range( $force = false ) {
      $swapped = false;
      if( !$this->is_flags_set(PARSER_NOSWAP_REVERSE) || $force )
      {
         if( $this->is_reverse_range() )
         {
            $this->swap_range_start_end();
            $swapped = true;
         }
      }
      return $swapped;
   }

   /*!
    * \brief returns true, if p_start > p_end
    * signature: bool is_reverse_range()
    */
   function is_reverse_range() {
      if( (string)$this->p_start != '' && (string)$this->p_end != '' )
         return ( $this->p_start > $this->p_end );
      else
         return false;
   }

   /*!
    * \brief swaps p_start and p_end
    * signature: void swap_range_start_end()
    */
   function swap_range_start_end() {
      $tmp = $this->p_start;
      $this->p_start = $this->p_end;
      $this->p_end = $tmp;
   }
} // end of 'BasicParser'



 /*!
  * \class NumericParser
  *
  * \brief Parser to parse numeric values.
  *
  * Supported Flags: PARSER_NOSWAP_REVERSE
  * NOTE: parse numeric values only (at this moment)
  *
  * Parse-Syntax: 7, -7, 7-, 7-9
  */
class NumericParser extends BasicParser
{
   /*! \brief Constructs NumericParser( string value, TokenizerConfig tok_config, int flags ). */
   function NumericParser( $value, $tok_config, $flags = 0 ) {
      parent::BasicParser( $value, $tok_config, $flags );
      $this->parse($value, $flags);
   }

   /*!
    * \brief Parses value with specified $flags
    * signature: interface bool success = parse(string value, int flags)
    */
   function parse($value, $flags) {
      $this->init_parse($value, $flags);
      if( $this->value == '' )
         return true; // empty (no-error)

      // tokenize
      $tokenizer = create_StringTokenizer( $this->tokconf, '', $this->flags);
      if( !$tokenizer->parse($value) )
      {
         $this->errormsg = implode('; ', $tokenizer->errors());
         return false;
      }
      $arr = $tokenizer->tokens();
      $cnt = count($arr);
      // expected: 0 entries (''), 1 entry (val), 2 entries (sep val | val sep), 3 entries (start sep end)

      if( $cnt == 0 )
         return true;

      if( $cnt > 3 )
      {
         $this->errormsg = "[$value] " . T_('too many separators#filter') . " [{$this->tokconf->sep}]";
         return false;
      }

      if( $cnt == 1 )
      { // exact syntax
         $v = $arr[0]->get_token();
         if( empty($v) || is_numeric($v) )
            $this->p_value = $v;
         else
         {
            $this->errormsg = "[$v] " . T_('not numeric');
            return false;
         }
      }
      else
      { // range syntax
         list( $v1, $v2 ) = extract_range( $arr );
         if( !empty($v1) && !is_numeric($v1) )
         {
            $this->errormsg = "[$v1] " . T_('not numeric');
            return false;
         }
         elseif( !empty($v2) && !is_numeric($v2) )
         {
            $this->errormsg = "[$v2] " . T_('not numeric');
            return false;
         }
         else
         {
            $this->p_start = $v1;
            $this->p_end   = $v2;
         }
      }

      $this->handle_reverse_range();

      return true;
   }
} // end of 'NumericParser'



 /*!
  * \class TextParser
  *
  * \brief Parser to parse text values.
  *
  * Supported Flags: PARSER_NOSWAP_REVERSE | TEXTPARSER_FORBID_RANGE |
  *                  TEXTPARSER_FORBID_WILD | TEXTPARSER_ALLOW_START_WILD |
  *                  TEXTPARSER_END_INCL | TEXTPARSER_IMPLICIT_WILD
  * Supported TokenizerConfig:
  *                  TEXTPARSER_CONF_STARTWILD_MINCHARS = 1..
  *                  TEXTPARSER_CONF_RX_NO_SEP = regex
  *                  TEXTPARSER_CONF_PRECEDENCE_SEP = 0|1
  *
  * note: wildcard supported: '*' (multi-char)
  * note: performs additional check of consecutive chars when value starts with wildcard
  *
  * result: var $p_terms contains array with search rx_terms when wildcard or exact-syntax.
  *
  * Parse-Syntax: x, -x, x-, x-y, x*, *x
  */

define('TEXT_WILD_M', '*'); // special char for wildcard (multi-char)

define('TEXTPARSER_CONF_STARTWILD_MINCHARS', 'startwild_minchars');
define('TEXTPARSER_CONF_RX_NO_SEP',      'txtpconf_rx_no_sep' ); // regex that overrules match for range-separator
define('TEXTPARSER_CONF_PRECEDENCE_SEP', 'precedence_sep'); // flag to indicate, that separator has higher precedence than other special-chars (wildcard)

define('STARTWILD_OPTMINCHARS', 4); // value for filter-config FC_START_WILD or above define (=no of chars to force when pattern starts with wildcard)

class TextParser extends BasicParser
{
   /*! \brief Constructs TextParser( string value, TokenizerConfig tok_config, int flags ). */
   function TextParser( $value, $tok_config, $flags = 0 ) {
      if( $flags & TEXTPARSER_IMPLICIT_WILD )
         $flags |= TEXTPARSER_FORBID_RANGE | TEXTPARSER_ALLOW_START_WILD;
      parent::BasicParser( $value, $tok_config, $flags );
      $this->parse($value, $this->flags);
   }

   /*!
    * \brief Parses value with specified $flags
    * signature: interface bool success = parse(string value, int flags)
    */
   function parse($value, $flags) {
      $this->init_parse($value, $flags);
      if( $this->value == '' )
         return true; // empty (no-error)

      // init tokenizer
      $forbid_wild = $this->is_flags_set(TEXTPARSER_FORBID_WILD);
      $wild_char = $this->tokconf->wild;
      if( $forbid_wild )
         $wild_char = '';
      $tokenizer = create_StringTokenizer( $this->tokconf, $wild_char, $this->flags);
      $rx_no_sep = $this->tokconf->get_config( TEXTPARSER_CONF_RX_NO_SEP );
      if( $rx_no_sep != '' )
         $tokenizer->add_config( STRTOK_CONF_RX_NO_SEP, $rx_no_sep );

      // tokenize
      if( !$tokenizer->parse($value) )
      {
         $this->errormsg = implode('; ', $tokenizer->errors());
         return false;
      }
      $arr = $tokenizer->tokens();
      $cnt = count($arr);
      // expected: 0 entries (''), 1 entry (val), 2 entries (sep val | val sep), 3 entries (start sep end)

      if( $cnt == 0 )
         return true;

      if( $cnt > 3 )
      {
         $this->errormsg = "[$value] " . T_('too many separators#filter') . " [{$this->tokconf->sep}]";
         return false;
      }

      // assure higher precedence of wildcard (over separator)
      $arr_wild_replace = array( $this->tokconf->wild => '%' );
      if( $cnt != 1 && !$forbid_wild && !$this->is_flags_set(TEXTPARSER_CONF_PRECEDENCE_SEP) )
      {
         list( $v1, $v2 ) = extract_range( $arr );
         list( $sql, $cnt_wild1 ) = sql_replace_wildcards( $v1, $arr_wild_replace );
         list( $sql, $cnt_wild2 ) = sql_replace_wildcards( $v2, $arr_wild_replace );
         if( $cnt_wild1 + $cnt_wild2 > 0 )
         {
            $merged_token = new Token(TOK_TEXT, 0, $v1 . $arr[1]->get_token() . $v2 );
            $arr = array( $merged_token );
            $cnt = 1;
         }
      }

      if( $cnt == 1 )
      { // exact syntax or wildcard
         $v = $arr[0]->get_token();
         if($v == '')
            return true;

         if( $forbid_wild )
         {
            // wild can be treated as normal char, same goes for special-chars
            $this->p_value = $v;
            $term = preg_replace( "/[^\\w'\\s]+/", '', $v );
            $this->p_terms[]= $term;
         }
         else // allow-wild
         {
            // add wild as prefix and suffix to build substring-search
            if( $this->is_flags_set(TEXTPARSER_IMPLICIT_WILD) )
            {
               if( $v[0] != $wild_char )
                  $v = $wild_char . $v;
               if( substr($v, -1) != $wild_char )
                  $v .= $wild_char;
            }

            if( (string)$wild_char != '' )
               if(substr_count($v, $wild_char) == strlen($v)) // wildcards only (multi-char '*')
                  return true; // matching all (return '')

            // started with wildcard?
            if( $v[0] == $wild_char )
            {
               if( $this->is_flags_set(TEXTPARSER_ALLOW_START_WILD) ) // check min-chars
               {
                  $minchars = $this->tokconf->get_config(TEXTPARSER_CONF_STARTWILD_MINCHARS, STARTWILD_OPTMINCHARS);
                  $quote_wild = preg_quote( $wild_char, '/' );
                  if( $minchars > 1 && !(preg_match("/^[{$quote_wild}]+([^{$quote_wild}]{".$minchars.",})/", $v)) )
                  {
                     $this->errormsg =
                        sprintf( T_('need at least %1$s characters when using text with starting wildcard [%2$s]'),
                           $minchars, $wild_char );
                     return false;
                  }
               }
               else
               { // forbid to start with wildcard
                  $this->errormsg = "[$wild_char] " . T_('not allowed as prefix#filter');
                  return false;
               }
            }

            // safely replace wildcards with SQL-wildcards
            list( $valsql, $cnt_wild ) = sql_replace_wildcards( $v, $arr_wild_replace );

            $this->p_value = $valsql;
            if( $cnt_wild > 0 )
               $this->p_flags |= PFLAG_WILDCARD;
            $arr_terms = sql_extract_terms( $valsql );
            if( count($arr_terms) > 0 )
               $this->p_terms = array_merge( $this->p_terms, $arr_terms );
         }
      }
      else
      { // range syntax (handle wildcard as normal chars)
         list( $v1, $v2 ) = extract_range( $arr );
         $this->p_start = $v1;
         $this->p_end   = $v2;
         $this->handle_reverse_range();

         if( (string)$this->p_end != '' && !$this->is_flags_set(TEXTPARSER_END_INCL) )
            $this->p_end = make_text_end_exclusive( $this->p_end );
      }

      return true;
   }
} // end of 'TextParser'



 /*!
  * \class DateParser
  *
  * \brief Parser to parse single date values without tokenizing.
  *
  * NOTE: tokenizing is done in FilterDate-class; \see FilterDate
  * NOTE: not extending from BasicParser
  *
  * Parse-Syntax: date = [ YYYY [ MM [ DD [ ' '? hh [ mm [ ss ] ] ] ] ] ]
  * Notes:
  * - if year omitted, use current year
  * - Completes missing date-parts for supporting searching start/end-ranges
  * - ':'-char is skipped while parsing date
  */

define('RANGE_START', 0); // indicating range-start for DateParser
define('RANGE_END',   1); // indicating range-end for DateParser

class DateParser
{
   /*! \brief original date passed to constructor. */
   var $origdate;
   /*! \brief original range-type passed to constructor. */
   var $rangetype;
   /*! \brief first error encountered while parsing. */
   var $errormsg;
   /*! \brief =getdate() on constructor-time */
   var $now;

   // for parsing

   /*! \brief Working copy of date for parsing. */
   var $datestr;
   /*! \brief array with error-datepart. */
   var $checkarr;
   /*! \brief resulting date, completed in format 'YYYY-MM-DD hh:mm:ss', only valid if no errormsg */
   var $dateval;
   /*! \brief unix-timestamp of completed-date without rangetype-processing (used for reverse-range-handling) */
   var $rawdate;

   // respective values during parsing
   var $year;
   var $month;
   var $day;
   var $hour;
   var $min;
   var $sec;

   /*!
    * \brief Constructs DateParser( string datestr, int rangetype ).
    * param $rangetype: RANGE_START | RANGE_END
    */
   function DateParser( $datestr, $rangetype )
   {
      // check args
      if( $rangetype != RANGE_START && $rangetype != RANGE_END )
         error('invalid_filter', "filter_parser.DateParser.invalid_arg.rangetype($rangetype)");

      $this->origdate = $datestr;
      $this->rangetype = $rangetype;
      $this->errormsg = '';
      global $NOW;
      $this->now = getdate($NOW);

      if( $this->parse($this->origdate) )
         $this->complete_date();
   }

   /*!
    * \brief Returns error-message; '' if no error.
    * signature: string errormsg()
    */
   function errormsg()
   {
      return $this->errormsg;
   }

   /*!
    * \brief Returns resulting date in format 'YYYY-MM-DD hh:mm:ss' if no error occured, '' otherwise.
    * signature: string get_completed_date()
    * NOTE: only valid if no errormsg
    */
   function get_completed_date()
   {
      return ( $this->errormsg ) ? '' : $this->dateval;
   }

   // for debugging
   function to_string()
   {
      return "DateParser('{$this->origdate}',{$this->rangetype})={ err=[{$this->errormsg}], "
         . "date=[{$this->datestr}], val=[{$this->dateval}], rawdate=[{$this->rawdate}]"
         . "y-m-d=[{$this->year}-{$this->month}-{$this->day}] "
         . "H:M:S=[{$this->hour}:{$this->min}:{$this->sec}] }";
   }

   /*!
    * \brief Creates unix-timestamp-snapshot of current year/month/day/hour/min/sec.
    * signature: void make_ts_snapshot()
    */
   function make_ts_snapshot()
   {
      return mktime($this->hour, $this->min, $this->sec, $this->month, $this->day, $this->year);
   }


   /*!
    * \brief Checks passed value against specified range; if error -> add typepart in checkarr.
    * signature: bool error = check_datepart(string typepart, string datepart, int start, int end)
    */
   function check_datepart($typepart, $dpart, $start, $end)
   {
      $error = 0;
      if( $dpart == '' )
         return false; // ok, if empty
      if( $dpart < $start )
         $error++;
      if( $dpart > $end )
         $error++;
      if( $error )
         $this->checkarr[]= $typepart;
      return (boolean)$error;
   }

   /*!
    * \brief Parses date.
    * signature: bool success = parse(string date)
    */
   function parse( $date )
   {
      static $rxdate = '/^(\d{4})(\d\d)?(\d\d)?\s*(\d\d)?(\d\d)?(\d\d)?$/';

      // init parse
      $this->origdate = $date;
      $this->datestr  = str_replace( ':', '', $date ); // working copy (strip ';'-char)
      $this->dateval  = '';
      $this->errormsg = '';

      $this->checkarr = array(); // reset errors

      // parse into date-parts: YMD hms
      $out = array();
      if( preg_match($rxdate, $this->datestr, $out) == 0)
      {
         $this->errormsg = "[$date] " . T_('invalid date-format#filter');
         return false;
      }

      // some basic range-checks (date-parts may be optional)
      @list(, $this->year, $this->month, $this->day, $this->hour, $this->min, $this->sec) = $out;
      $this->check_datepart('Y', $this->year,  1900, 2100);
      $this->check_datepart('M', $this->month, 1, 12);
      $this->check_datepart('D', $this->day,   1, 31);
      $this->check_datepart('h', $this->hour,  0, 23);
      $this->check_datepart('m', $this->min,   0, 59);
      $this->check_datepart('s', $this->sec,   0, 59);
      if( count($this->checkarr) > 0)
      {
         $err_datepart = implode( '', $this->checkarr);
         $this->errormsg = "[$date] " . T_('has invalid date-part#filter') . " [".$err_datepart."]";
         return false;
      }

      // check year/month/day-combination
      $chk_day   = ( (string)$this->day   != '' ) ? $this->day   : 1; // maybe empty
      $chk_month = ( (string)$this->month != '' ) ? $this->month : 1; // maybe empty
      if( !checkdate( $chk_month, $chk_day, $this->year ) )
      {
         $this->errormsg = "[$date] " . T_('invalid day#filter');
         return false;
      }

      return true;
   }

   /*!
    * \brief Completes date according to rangetype, store in dateval; update of year/month/day/hour/min/sec if needed.
    * signature: void complete_date()
    * NOTE: rangetype only RANGE_START|END
    */
   function complete_date()
   {
      // missing year + remaining ('YMDhms') -> use current year
      if( $this->year == '' )
         $this->year = $this->now['year'];

      // missing month + remaining ('MDhms')
      if( $this->month == '' )
      {
         $this->month = 1;
         $this->day = 1;
         $this->hour = $this->min = $this->sec = 0;
         $this->rawdate = $this->make_ts_snapshot();
         if( $this->rangetype == RANGE_END)
            $this->year++;
      }
      // missing day + remaining ('Dhms')
      elseif( $this->day == '' )
      {
         $this->day = 1;
         $this->hour = $this->min = $this->sec = 0;
         $this->rawdate = $this->make_ts_snapshot();
         if( $this->rangetype == RANGE_END)
            $this->month++;
      }
      // missing hour + remaining ('hms')
      elseif( $this->hour == '' )
      {
         $this->hour = $this->min = $this->sec = 0;
         $this->rawdate = $this->make_ts_snapshot();
         if( $this->rangetype == RANGE_END)
            $this->day++;
      }
      // missing minute + remaining ('ms')
      elseif( $this->min == '' )
      {
         $this->min = $this->sec = 0;
         $this->rawdate = $this->make_ts_snapshot();
         if( $this->rangetype == RANGE_END)
            $this->hour++;
      }
      // missing sec + remaining ('s')
      elseif( $this->sec == '' )
      {
         $this->sec = 0;
         $this->rawdate = $this->make_ts_snapshot();
         if( $this->rangetype == RANGE_END)
            $this->min++;
      }

      // handle exceeding of limits by increasing of date-parts
      $unixtime = $this->make_ts_snapshot();
      $this->dateval = date("Y-m-d H:i:s", $unixtime);
   }
} // end of 'DateParser'

?>

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

require_once( "include/tokenizer.php" );

 /* Author: Jens-Uwe Gaspar */

 /*!
  * \file filter_functions.php
  *
  * \brief Helping-functions to manage filters.
  */

// layout prefix/suffix for filter-errors in static-forms
$FERR1 = '<strong class=ErrMsg>';
$FERR2 = '</strong>';
$FWRN1 = '<strong class=WarnMsg>';
$FWRN2 = '</strong>';


/*!
 * \brief Returns bitmask for specified filter_id
 * signature: int filter_id2mask(int filter_id)
 * \internal
 */
function filter_id2mask( $id )
{
   return (1 << ($id - 1));
}

/*!
 * \brief Creates default TokenizerConfig taking global consts into account
 * \param $quotetype if null, default is used
 * note: defaults are: quote-type=QUOTETYPE_QUOTE, separator=-, quote-chars='', escape-chars=\\
 * \internal
 */
function createTokenizerConfig( $quotetype = null )
{
   if( is_null($quotetype) )
      $quotetype = QUOTETYPE_QUOTE;
   return new TokenizerConfig( $quotetype, '-', TEXT_WILD_M, "''", '\\\\' );
}

/*!
 * \brief ordinally increases last char of passed value: 'uks' -> 'ukt', used to do TEXTPARSER_END_INCL
 * signature: string make_text_end_exclusive(string v)
 */
function make_text_end_exclusive( $v ) {
   if( $v == '' )
      return '';
   $lastch = substr($v, -1, 1);
   return substr($v, 0, strlen($v) - 1) . chr( ord($lastch) + 1);
}

 /*!
  * \brief escape SQL-special chars and replace DGS-wildcards with SQL-wildcards
  * signature: (string sql, int cnt_wild) = replace_wildcards(
  *             string val, array( src_char => dest_char ),
  *             array( allow_char-despite-being-forbidden => 1 ))
  * note: incompatible (doing more) than mysql_escape_string()-func because of special-chars
  * note: because of SQL-injection, dest_char must never be one of: ' " \
  * note: use with caution!! allow-char-array allows forbidden dest_chars
  * note: also handle, if wildcar_char is '%' using correct escaping
  */
function sql_replace_wildcards( $valsql, $arr_repl, $arr_allow = array() )
{
   static $sql_spec = "%_'\\"; // SQL-special-chars
   $sql = '';
   $esc = 0;
   $cnt_wild = 0;
   for( $pos = 0; $pos < strlen($valsql); $pos++)
   {
      $char = $valsql{$pos};
      $repl = $char;

      if( $esc )
      {
         $esc = 0;
         // non-DGS-wildcard needs no escaping, so escape previous '\', e.g. \a -> \\a
         // note: DGS-wildcard don't need '\' (so omit it to insert)
         if( !isset($arr_repl[$char]) )
            $sql .= '\\\\';

         // SQL-escape with '\' only needed if SQL-wildcard (if DGS-wild or not), e.g. \' -> \\\'
         if( !(strpos($sql_spec, $char) === false) )
            $sql .= '\\';
      }
      elseif( $char == '\\' )
      {
         $esc = 1; // next char may needs escaping (but no '\')
         continue;
      }
      else // no char to escape
      {
         if( isset($arr_repl[$char]) )
         {
            $repl = $arr_repl[$char];
            if($repl == '%' || $repl == '_')
               $cnt_wild++;

            // SQL-injection-error: dest_char must never be one of: ' " \ -> so catch it by escaping
            if( !isset($arr_allow[$char]) ) // allow forbidden (BUT use with caution)
               if( $repl == "'" || $repl == '"' || $repl == '\\' )
                  $sql .= '\\';
         }
         elseif( !(strpos($sql_spec, $char) === false) ) // SQL-special -> escape
            $sql .= '\\';
      }

      $sql .= $repl;
   }
   if( $esc )
      $sql .= '\\\\'; // ending with '\' (is SQL-special, so needs escaping)

   return array( $sql, $cnt_wild );
}

/*!
 * \brief Extracts regex-terms from SQL-pattern/value and return as terms-array.
 * \param $rx_delimiter see include/std_functions.php (parse_html_safe) for default ('/')
 */
function sql_extract_terms( $sql, $rx_delimiter='/' )
{
   $sql = preg_replace( "/^%+/", '', $sql ); //remove heading "%"
   $len = strlen($sql);

   // find potential endpos to skip trailing '%' to handle e.g. a\\%% a\%% a\%
   $arr_match = array();
   $endpos = (preg_match( "/^(.*?[^%])%+$/", $sql, $arr_match))
      ? strlen($arr_match[1]) : $len;

   // \\ -> \\, \% -> %, \_ -> _, % -> .*?, _ -> ., others -> copy and preg-escape
   $rx_term = '';
   for( $pos = 0; $pos < $len; $pos++)
   {
      if( $sql{$pos} == '\\' )
      {
         $pos++;
         if( $pos < $len )
            $rx_term .= preg_quote($sql{$pos}, $rx_delimiter);
      }
      elseif( $sql{$pos} == '%' )
      {
         if( $pos >= $endpos ) // skip trailing '%'
            break;
         while( $pos <= $len && substr($sql, $pos+1, 1) == '%' ) // skip double '%'
            $pos++;
         $rx_term .= '.*?';
      }
      elseif( $sql{$pos} == '_' )
         $rx_term .= '.';
      else
         $rx_term .= preg_quote($sql{$pos}, $rx_delimiter);
   }

   if( $rx_term )
      return array( $rx_term );
   else
      return array();
}

?>

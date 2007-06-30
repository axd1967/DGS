<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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

require_once( "include/tokenizer.php" );

 /* Author: Jens-Uwe Gaspar */

 /*!
  * \file filter_functions.php
  *
  * \brief Helping-functions to manage filters.
  */

// layout prefix/suffix for filter-errors in static-forms
$FERR1 = "<font color=darkred>";
$FERR2 = "</font>";


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
 * note: defaults are: quote-type=QUOTETYPE_ESCAPE, separator=-, quote-chars="", escape-chars=\\
 * \internal
 */
function createTokenizerConfig( $quotetype = null )
{
   #global $player_row; \\! \todo may later read from player-settings
   if ( is_null($quotetype) )
      $quotetype = QUOTETYPE_ESCAPE;
   return new TokenizerConfig( $quotetype, '-', TEXT_WILD_M, '""', '\\\\' );
}

/*!
 * \brief ordinally increases last char of passed value: 'uks' -> 'ukt', used to do TEXTPARSER_END_INCL
 * signature: string make_text_end_exclusive(string v)
 */
function make_text_end_exclusive( $v ) {
   if ( $v == '' )
      return '';
   $lastch = substr($v, -1, 1);
   return substr($v, 0, strlen($v) - 1) . chr( ord($lastch) + 1);
}

 /*!
  * \brief escape SQL-special chars and replace DGS-wildcards with SQL-wildcards
  * signature: (string sql, int cnt_wild) = replace_wildcards(
  *             string val, array( src_char => dest_char ),
  *             array( allow_char-despite-being-forbidden => 1 ))
  * note: imcompatible (doing more) than mysql_escape_string()-func because of special-chars
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

      if ( $esc )
      {
         $esc = 0;
         // non-DGS-wildcard needs no escaping, so escape previous '\', e.g. \a -> \\a
         // note: DGS-wildcard don't need '\' (so omit it to insert)
         if ( !isset($arr_repl[$char]) )
            $sql .= '\\\\';

         // SQL-escape with '\' only needed if SQL-wildcard (if DGS-wild or not), e.g. \' -> \\\'
         if ( !(strpos($sql_spec, $char) === false) )
            $sql .= '\\';
      }
      elseif ( $char == '\\' )
      {
         $esc = 1; // next char may needs escaping (but no '\')
         continue;
      }
      else // no char to escape
      {
         if ( isset($arr_repl[$char]) )
         {
            $repl = $arr_repl[$char];
            if ($repl == '%' or $repl == '_')
               $cnt_wild++;

            // SQL-injection-error: dest_char must never be one of: ' " \ -> so catch it by escaping
            if ( !isset($arr_allow[$char]) ) // allow forbidden (BUT use with caution)
               if ( $repl == "'" or $repl == '"' or $repl == '\\' )
                  $sql .= '\\';
         }
         elseif ( !(strpos($sql_spec, $char) === false) ) // SQL-special -> escape
            $sql .= '\\';
      }

      $sql .= $repl;
   }
   if ( $esc )
      $sql .= '\\\\'; // ending with '\' (is SQL-special, so needs escaping)

   return array( $sql, $cnt_wild );
}

?>

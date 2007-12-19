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

/*
 * Code example using StringTokenizer and XmlTokenizer (Tokenizes value and output tokens).
 *
 * Usage: php code_examples/tokenizer_example.php type value [sep]
 * Usage: php code_examples/tokenizer_example.php     -> using type = 'xml', value = some text
 *
 * Options:
 *    type  : xml - expecting XML as value using XmlTokenizer
 *            D | Q | E - using StringTokenizer with quote-type 'D' (double), 'Q' (quoting), 'E' (escape)
 *    value : text to tokenize
 *    sep   : additional separator-char used ('-' default)
 */

require_once( "include/tokenizer.php" );

function fnop() {}

{
   // some xml-example-value
   $value = <<<END_DATA
<c>comment<c
<dies a> <bla a1="val1" a2='val2' a3=val3 a4= a5>
<dies a> ist <das/> Haus <vom> Nikolaus &amp; dem Wolf </vom> jetzt </dies> geht es aber <erst/> richtig <los/>
END_DATA;

   // parse args
   $type = 'xml';
   if ( $argc > 2 )
   {
      $type = $argv[1];
      $value = $argv[2];
   }
   $sep = ( $argc > 3 ) ? $argv[3] : '-';

   // choose Tokenizer
   if ($type == 'xml') {
      $t = new XmlTokenizer();

   } elseif ($type == 'D') {
      // using split_chars/spec_chars/escape_char, ignore quote_chars
      $t = new StringTokenizer(QUOTETYPE_DOUBLE, $sep, '*?');

   } elseif ($type == 'Q') {
      // using split_chars/spec_chars/quote_chars/escape_char
      $t = new StringTokenizer(QUOTETYPE_QUOTE,  $sep, '*?', "''");

   } elseif ($type == 'E') {
      // using split_chars/spec_chars/quote_chars/escape_char
      $t = new StringTokenizer(QUOTETYPE_ESCAPE, $sep, '*?', '.');

   } else {
      user_error("Bad type [$type]\n");
   }

   // parse value-text
   $success = $t->parse($value);
   $arr = $t->tokens();

   // print tokenized result and/or errors
   echo get_class($t) . ": success=$success, value=[$value]\n";
   echo "Errors:\n" . implode("\n  ", $t->errors)."\n";
   echo "\n";
   $cnt = 0;
   foreach( $arr as $token ) {
      $cnt++;
      echo "$cnt. " . $token->to_string() . "\n";
   }

   echo "\n";
}
?>

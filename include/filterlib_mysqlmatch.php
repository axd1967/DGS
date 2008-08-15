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

require_once( "include/filter.php" );


/*!
 * \brief for MysqlMatch-Filter: control usage of boolean-mode (checkbox and/or functionality).
 * values: MATCH_BOOLMODE_OFF don't show checkbox and don't use boolean-mode
 *         MATCH_BOOLMODE_SET don't show checkbox but use boolean-mode; since mysql 4.0.1(!)
 *         MATCH_QUERY_EXPANSION use query-extension (mutually exclusive to boolean-mode); since mysql 4.1.1(!)
 *         '' (empty) -> show checkbox to control use of boolean-mode
 * default is to show boolean-mode-checkbox (like using ''-value for config)
 */
define('FC_MATCH_MODE', 'match_mode');


define('MATCH_BOOLMODE_OFF',    'bool_mode_off'); // mysql match: boolean-mode isn't used (ctrl not shown)
define('MATCH_BOOLMODE_SET',    'bool_mode_set'); // mysql match: boolean-mode is used implicitly (ctrl not shown)
define('MATCH_QUERY_EXPANSION', 'query_exp');     // mysql match: using query-expansion (exclusive to bool-mode)

// MySQL (regex for english stopwords): see http://dev.mysql.com/doc/refman/4.1/en/fulltext-stopwords.html
// will be marked as error for mysql-match-filter
define('MYSQL_MATCH_STOPWORDS', "a's|able|about|above|according|accordingly|across|actually|after|afterwards|again|against|ain't|all|allow|allows|almost|alone|along|already|also|although|always|am|among|amongst|an|and|another|any|anybody|anyhow|anyone|anything|anyway|anyways|anywhere|apart|appear|appreciate|appropriate|are|aren't|around|as|aside|ask|asking|associated|at|available|away|awfully|be|became|because|become|becomes|becoming|been|before|beforehand|behind|being|believe|below|beside|besides|best|better|between|beyond|both|brief|but|by|c'mon|c's|came|can|can't|cannot|cant|cause|causes|certain|certainly|changes|clearly|co|com|come|comes|concerning|consequently|consider|considering|contain|containing|contains|corresponding|could|couldn't|course|currently|definitely|described|despite|did|didn't|different|do|does|doesn't|doing|don't|done|down|downwards|during|each|edu|eg|eight|either|else|elsewhere|enough|entirely|especially|et|etc|even|ever|every|everybody|everyone|everything|everywhere|ex|exactly|example|except|far|few|fifth|first|five|followed|following|follows|for|former|formerly|forth|four|from|further|furthermore|get|gets|getting|given|gives|go|goes|going|gone|got|gotten|greetings|had|hadn't|happens|hardly|has|hasn't|have|haven't|having|he|he's|hello|help|hence|her|here|here's|hereafter|hereby|herein|hereupon|hers|herself|hi|him|himself|his|hither|hopefully|how|howbeit|however|i'd|i'll|i'm|i've|ie|if|ignored|immediate|in|inasmuch|inc|indeed|indicate|indicated|indicates|inner|insofar|instead|into|inward|is|isn't|it|it'd|it'll|it's|its|itself|just|keep|keeps|kept|know|knows|known|last|lately|later|latter|latterly|least|less|lest|let|let's|like|liked|likely|little|look|looking|looks|ltd|mainly|many|may|maybe|me|mean|meanwhile|merely|might|more|moreover|most|mostly|much|must|my|myself|name|namely|nd|near|nearly|necessary|need|needs|neither|never|nevertheless|new|next|nine|no|nobody|non|none|noone|nor|normally|not|nothing|novel|now|nowhere|obviously|of|off|often|oh|ok|okay|old|on|once|one|ones|only|onto|or|other|others|otherwise|ought|our|ours|ourselves|out|outside|over|overall|own|particular|particularly|per|perhaps|placed|please|plus|possible|presumably|probably|provides|que|quite|qv|rather|rd|re|really|reasonably|regarding|regardless|regards|relatively|respectively|right|said|same|saw|say|saying|says|second|secondly|see|seeing|seem|seemed|seeming|seems|seen|self|selves|sensible|sent|serious|seriously|seven|several|shall|she|should|shouldn't|since|six|so|some|somebody|somehow|someone|something|sometime|sometimes|somewhat|somewhere|soon|sorry|specified|specify|specifying|still|sub|such|sup|sure|t's|take|taken|tell|tends|th|than|thank|thanks|thanx|that|that's|thats|the|their|theirs|them|themselves|then|thence|there|there's|thereafter|thereby|therefore|therein|theres|thereupon|these|they|they'd|they'll|they're|they've|think|third|this|thorough|thoroughly|those|though|three|through|throughout|thru|thus|to|together|too|took|toward|towards|tried|tries|truly|try|trying|twice|two|un|under|unfortunately|unless|unlikely|until|unto|up|upon|us|use|used|useful|uses|using|usually|value|various|very|via|viz|vs|want|wants|was|wasn't|way|we|we'd|we'll|we're|we've|welcome|well|went|were|weren't|what|what's|whatever|when|whence|whenever|where|where's|whereafter|whereas|whereby|wherein|whereupon|wherever|whether|which|while|whither|who|who's|whoever|whole|whom|whose|why|will|willing|wish|with|within|without|won't|wonder|would|would|wouldn't|yes|yet|you|you'd|you'll|you're|you've|your|yours|yourself|yourselves|zero");


 /*!
  * \class FilterMysqlMatch
  * \brief Filter for using the MySQL 'match'-command with all available options;
  *        SearchFilter-Type: MysqlMatch.
  * <p>GUI: text input-box + optional checkbox for boolean-mode-selection
  * <p>Additional interface functions:
  * - \see get_match_query_part() to return match-SQL-query-part to be used
  *   as relevance-value in list of SQL-select fields.
  * - \see get_rx_terms() to return array with search-terms as regex.
  *
  * note: special quoting used according to MATCH-syntax of mysql,
  *       i.e. no quoting used as for the Text-/Numeric-based-Filters is used
  *
  * note: stopwords are reported as filter->warnmsg()
  *
  * <p>Allowed Syntax:
  *    general fulltext-search for mysql: \see http://dev.mysql.com/doc/refman/4.1/en/fulltext-search.html
  *    boolean-mode syntax: \see http://dev.mysql.com/doc/refman/4.1/en/fulltext-boolean.html
  *
  *    Syntax-Description:
  *    - A word (search-term) consists of the chars  a-z0-9'_  with a min-length of 4 chars,
  *    - stopwords are ignored, and
  *    - text is only found when below a 50%-threshold.
  *      +word  -> word must appear in searched text (though not always ;)
  *      -word  -> word should not appear
  *      word   -> word might appear
  *      >word  -> word is more important
  *      <word  -> word is less important
  *      (grouping of terms) -> grouping, e.g. +( <word1 >word2 -word3 "word4 word5" )
  *      ~word  -> negate relevance
  *      word*  -> truncate-search, finds terms beginning with word (wildcard)
  *      "word word" -> literal phrase to be found
  *
  * <p>supported common config (restrictions or defaults):
  *    FC_FNAME, FC_STATIC, FC_GROUP_SQL_OR, FC_SIZE, FC_SYNTAX_HINT,
  *    FC_SYNTAX_HELP, FC_HIDE,
  *    FC_DEFAULT - match-term-value if scalar, or
  *                 array( '' => match-term-value, 'b' => 1=boolean-mode-ON or 0=OFF )
  *
  * <p>supported filter-specific config:
  *    FC_MATCH_MODE ='' (default),
  *                  =MATCH_BOOLMODE_OFF (nothing special),
  *                  =MATCH_BOOLMODE_SET (use bool-mode but no checkbox),
  *                  =MATCH_QUERY_EXPANSION (using query-expansion)
  */
class FilterMysqlMatch extends Filter
{
   /*! \brief element-name for boolean-mode-checkbox. */
   var $elem_boolmode;
   /*! \brief clause-part containing MATCH-command. */
   var $match_query_part;

   /*! \brief Constructs MysqlMatch-Filter. */
   function FilterMysqlMatch($name, $dbfield, $config)
   {
      static $_default_config = array( FC_SIZE => 30 );
      parent::Filter($name, $dbfield, $_default_config, $config);
      $this->type = 'MysqlMatch';
      $this->syntax_help = T_('MATCHINDEX#filterhelp');

      $arr_syntax = array();
      $arr_syntax[]= 'word word';
      $match_mode = $this->get_config(FC_MATCH_MODE);
      if ( empty($match_mode) || $match_mode === MATCH_BOOLMODE_SET )
         $arr_syntax[]= '+w -w <w >w ~w (wgroup) w* "literals"';
      $this->syntax_descr = implode(', ', $arr_syntax);

      // setup bool-mode (check-box)
      $this->elem_boolmode = "{$this->name}b";
      $this->add_element_name( $this->elem_boolmode );
      $this->values[$this->elem_boolmode] = ''; // default (unchecked)

      $this->build_sql_option(); // check match-mode
      $this->match_query_part = '';
   }

   /*!
    * \brief Parses match-term-value and handle checkbox for boolean-mode (if used).
    * <p>Also updates filter var: match_terms
    */
   function parse_value( $name, $val )
   {
      $val = $this->handle_default( $name, $val );
      $this->init_parse($val, $name); // if elem, value for elem_boolmode saved

      if ( $name === $this->name )
      { // parse terms
         list( $arr_terms, $arr_stopwords ) =
            $this->extract_match_terms( $this->value );
         if ( is_null($arr_terms) )
            return false; // no terms or error

         // check for stopwords (but continue without error)
         if ( count($arr_stopwords) > 0 )
         {
            $this->warnmsg =
               sprintf( T_('Can\'t search for stopwords [%s]!'),
                        implode(', ', $arr_stopwords) );
         }

         $this->match_terms = $arr_terms;
         $this->p_value = $this->value;
      }
      elseif ( $name === $this->elem_boolmode )
         ; // only var set in values[elem_boolmode]
      else
         error('invalid_filter', "ERROR: MysqlMatch-Filter parse_value($name,$val) called with unknown key");

      return true;
   }

   /*!
    * \brief Builds query for multi-element filter.
    * expecting: values[elem_boolmode] set, p_value set, handling FC_MATCH_MODE-config
    * <p>Also updates local var: match_query_part
    */
   function build_query()
   {
      // check
      if ( $this->p_value == '' )
         return;

      // build SQL
      $sql_option = $this->build_sql_option();
      list( $valsql, $cnt_wild ) =
         sql_replace_wildcards( $this->p_value, array( '"' => '"' ), array( '"' => 1 ) );
      $query = $this->build_base_query( $this->dbfield, false, false );
      $fields = $query->get_part(SQLP_FNAMES);

      // note: to get RELEVANCE use query without bool-mode-option,
      //       see http://dev.mysql.com/doc/refman/4.1/en/fulltext-search.html#c1502
      $this->match_query_part = "MATCH($fields) AGAINST ( '$valsql' )";

      $parttype = ($this->get_config(FC_ADD_HAVING)) ? SQLP_HAVING : SQLP_WHERE;
      $query->add_part( $parttype, "MATCH($fields) AGAINST ( '$valsql' $sql_option )" );
      $this->query = $query;
   }

   /*! \brief Returns input-text and optional checkbox form-element below (for handling of boolean-mode). */
   function get_input_element($prefix, $attr = array() )
   {
      // input-text for terms
      $r = $this->build_input_text_elem(
         $prefix, $attr, @$this->config[FC_MAXLEN], @$this->config[FC_SIZE] );

      // check-box for boolean-mode (only if no match-mode set (=default))
      $match_mode = $this->get_config(FC_MATCH_MODE);
      if ( empty($match_mode) )
      {
         $r .= "<BR>";
         $r .= $this->build_generic_checkbox_elem(
            $prefix, $this->elem_boolmode, $this->values[$this->elem_boolmode],
            T_('Expert mode#filter') );
      }

      return $r;
   }

   /*!
    * \brief Returns SQL-part for mysql-match according to FC_MATCH_MODE;
    * throws error for unknown match-mode.
    * \internal
    */
   function build_sql_option()
   {
      // handle boolean-mode
      $match_mode = $this->get_config(FC_MATCH_MODE);
      if ( empty($match_mode) )
         $sql_option = ((bool)$this->values[$this->elem_boolmode]) ? 'IN BOOLEAN MODE' : '';
      elseif ( $match_mode === MATCH_BOOLMODE_SET )
         $sql_option = 'IN BOOLEAN MODE';
      elseif ( $match_mode === MATCH_BOOLMODE_OFF )
         $sql_option = '';
      elseif ( $match_mode === MATCH_QUERY_EXPANSION )
         $sql_option = 'WITH QUERY EXPANSION';
      else
         error('invalid_filter', "ERROR: FilterMysqlMatch.build_query(): unknown FC_MATCH_MODE [$match_mode] for filter [{$this->id}]");

      return $sql_option;
   }

   /*!
    * \brief Returns arrays with words to search for in mysql-match search-terms,
    *        and another array with potential stopwords;
    * return null on error (errormsg set).
    * elements are well-formed for a (p)reg_exp using '/' as delimiter
    * \internal
    */
   function extract_match_terms( $terms )
   {
      // extract words, especially "quoted literals" using StringTokenizer
      // Syntax: word: [a-z0-9'_]+   +must -mustnot opt >more-important <less-important (grouping) ~negate-relevance trunc* "literal phrase"
      // note: treat following chars as word-separators to be removed in terms: +-<>~()
      $tokenizer = new StringTokenizer( QUOTETYPE_QUOTE | TOKENIZE_WORD_RX,
            '\\w\'\\*', '', '\\\\', '""');

      if ( !$tokenizer->parse($terms) )
      {
         $this->errormsg = implode(", ", $tokenizer->errors());
         return null;
      }

      // remove the regex special chars (including the '/' delimiter)
      // note (regex-chars): . \ + * ? [ ^ ] $ ( ) { } = ! < > | :
      $wordchars = "\\w'"; // chars building a mysql-match-word
      $arr = array();
      $stopwords = array();
      $n = 0;
      foreach( $tokenizer->tokens() as $token )
      {
         if ( $token->get_type() == TOK_TEXT )
         {
            if ( $token->get_flags() & TOKFLAG_QUOTED ) // quoted-text "text"
            {
               $val = preg_quote( $token->get_token(), '/' ); //regex delimiter
               $val = preg_replace( "/\\s+/", "\\s+", $val );
            }
            else // unquoted-text
            {
               $val = preg_replace(
                  array( "/[^$wordchars\*\\s]+/i", "/\*/", "/\\s+/" ), // match-rx
                  array( " ",                      ".*?",  "\\s+" ),   // corresponding replacement
                  $token->get_token() );
            }

            $val = trim( $val );
            if ( (string)$val != '' ) // need ''<>0, so don't use empty(val)
               $arr[]= $val;

            // check for stopwords
            if ( preg_match( "/\\b(".MYSQL_MATCH_STOPWORDS.")\\b/i", $val, $out ) )
               $stopwords[] = $out[1];
         }
      }

      return array( $arr, $stopwords );
   }

   /*!
    * \brief Returns the query-part used for the MATCH, which can be added as
    *        SQL-select-field for the score (term-relevance).
    * <p>According to \see http://dev.mysql.com/doc/refman/4.1/en/fulltext-search.html#c1502
    * this doesn't contain the boolean-option even if used for the where-clause.
    */
   function get_match_query_part()
   {
      return $this->match_query_part;
   }
} // end of 'FilterMysqlMatch'

?>

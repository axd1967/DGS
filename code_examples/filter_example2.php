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
 * Code example demonstrating use of Filter-Framework (Filter-config):
 *
 * Usage: open its URL in browser
 */

chdir("../");
require_once("include/std_functions.php");
require_once("include/std_classes.php");
require_once("include/form_functions.php");
require_once("include/filter.php");
require_once("include/filterlib_country.php");
require_once("include/filterlib_mysqlmatch.php");
chdir("code_examples/");

/* init vars */

// Filter-Demos with types and config
$arr_filter_demos = array(
   1 => 'Numeric-Filter',
   2 => 'Text-Filter',
   3 => 'Rating-Filter',
   4 => 'Country-Filter',
   5 => 'Date-Filter',
   6 => 'RelativeDate-Filter',
   7 => 'Selection-Filter',
   8 => 'BoolSelect-Filter',
   9 => 'RatedSelect-Filter',
  10 => 'Boolean-Filter',
  11 => 'MysqlMatch-Filter',
  12 => 'Score-Filter',
  13 => 'RatingDiff-Filter',
  14 => 'CheckboxArray-Filter',

  // some special Filter-Configs:
   0 => '--- Other Filter-Config:',
  16 => 'Conditionals', # FC_IF
  17 => 'Quoting', # FC_QUOTETYPE
  15 => 'Defaults (with defaults)', # FC_DEFAULT
  19 => 'Defaults (without defaults)',
  18 => 'Miscellaneous', # more FC_...
);

$fdemo = get_request_arg('fdemo');
if ( !isset($arr_filter_demos[$fdemo]) )
   $fdemo = 1;

/* control-form to select filter-demo */

echo "<html><body>\n";

# select demo
$actform = new Form( "myname", "filter_example2.php", FORM_GET );
$actform->add_row( array(
      'DESCRIPTION',  'Filter-DemoType',
      'SELECTBOX',    'fdemo', 1, $arr_filter_demos, $fdemo, false,
      'SUBMITBUTTON', 'action', 'Choose Filter-DemoType',
      ));
$actform->echo_string();

/* -------------------- init vars for filters ------------------------------------  */

$DEBUG_SQL = true;

# using prefix to allow separate search-filters and to demonstrate FC_FNAME (not using a prefix)
$filter = new SearchFilter('p');

# init base sql-query
$query = new QuerySQL(
   SQLP_FIELDS,
      '*',
   SQLP_FROM,
      'TableName'
   );

// form for static filters
$page = 'filter_example2.php';
$form = new Form( 'table', $page, FORM_GET );
//$form->set_config( FEC_TR_ATTR, 'valign=top' ); // not needed if CSS contains: td { vertical-align: top }
$form->set_attr_form_element( 'Description', FEA_ATTBS, array('align'=>'left') );

$area_layout = '';
$arr_layout = array(); # filter-id => arr( label|descr|noerr => val )
$title = ''; # string filter-title | array( filter-title, filter-info-lines, ... )

/* -------------------- helper funcs ---------------------------------------------  */

/*!
 * \brief returns array with FC_DEFAULT-key if with_def is true;
 *        otherwise according keys are omitted in resulting array.
 */
function def_array( $with_def, $arr )
{
   if ( !$with_def )
      unset( $arr[FC_DEFAULT] );
   return $arr;
}


/* -------------------- (1) Numeric ----------------------------------------------  */
if ( $fdemo == 1 )
{
   $title = array(
      'Numeric Filter',
      'allowed syntax: exact value, range-syntax (swap reverse-values, e.g. 4-1 -> 1-4)',
      "escape special chars with single-quotes, .e.g  '-'1  or  '-1'  for -1",
      'hover-text with a short syntax-help',
      'error is shown if invalid syntax entered',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Numeric #1', 'descr' => 'standard filter allowing exact and range-syntax' ),
      2 => array( 'label' => 'Numeric #2', 'descr' => 'OR\'grouping with filter #3 [FC_GROUP_SQL_OR], [FC_SYNTAX_HINT] for additional info in syntax-hover-text-description' ),
      3 => array( 'label' => 'Numeric #3', 'descr' => 'OR\'grouping with filter #2 [FC_GROUP_SQL_OR], [FC_SYNTAX_HELP] for special help-id in syntax-hover-text-description' ),
      4 => array( 'label' => 'Numeric #4', 'descr' => 'use fix URL-fieldname NUM4 [FC_FNAME] (check in URL), using SQL-template as dbfield [FC_SQL_TEMPLATE]' ),
      5 => array( 'label' => 'Numeric #5', 'descr' => 'use complex query with QuerySQL (with template)' ),
      6 => array( 'label' => 'Numeric #6', 'descr' => 'use complex query with QuerySQL (no template)' ),
      7 => array( 'label' => 'Numeric #7', 'descr' => 'use of [FC_NUM_FACTOR=10]' ),
      8 => array( 'label' => 'Numeric #8', 'descr' => 'use of [FC_ADD_HAVING], no range-syntax allowed [FC_NO_RANGE], adjust size/maxlen of input [FC_SIZE=3, FC_MAXLEN=5]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Numeric', 'num1', true);

   # OR'grouping filter 2+3
   $filter->add_filter( 2, 'Numeric', 'num2', true,
         array( FC_GROUP_SQL_OR => '2,3',
                FC_SYNTAX_HINT => array( FCV_SYNHINT_ADDINFO => 'additional info' ) ));

   # OR'grouping filter 2+3, using QuerySQL with simple-fieldname just like simple dbfield (see filter #2)
   $filter->add_filter( 3, 'Numeric',
         new QuerySQL( SQLP_FNAMES, 'num3'),
         true,
         array( FC_GROUP_SQL_OR => '2,3',
                FC_SYNTAX_HELP => 'SpecialHelp' ));

   # using SQL-template, using fix URL-name
   $filter->add_filter( 4, 'Numeric', '(num3a #OP #VAL OR num3b #OP #VAL)', true,
         array( FC_FNAME => 'NUM4',
                FC_SQL_TEMPLATE => 1 ));

   # using SQL-template with QuerySQL for more complex query-part
   $filter->add_filter( 5, 'Numeric',
         new QuerySQL(
               SQLP_FROM,      'Table5 T5', 'Table6 T6',
               SQLP_WHERETMPL, '(T5.num5 #OP #VAL OR T6.num5 #OP #VAL)' ),
         true);

   # using QuerySQL as dbfield for more complex query-part to be included when filter-value set
   $filter->add_filter( 6, 'Numeric',
         new QuerySQL(
               SQLP_OPTS,   'DISTINCT',
               SQLP_FNAMES, 'N.num6',
               SQLP_FROM,   'NumTable N',
               SQLP_ORDER,  'N.num6 DESC' ),
         true);

   # filter-specific: num-factor
   $filter->add_filter( 7, 'Numeric', 'num7', true,
         array( FC_NUM_FACTOR => 10 ));

   # using where-clause in Having, no range-syntax allowed, size of input-field, maxlen of input-field
   $filter->add_filter( 8, 'Numeric', 'num8', true,
         array( FC_ADD_HAVING => 1,
                FC_NO_RANGE => 1,
                FC_SIZE => 3,
                FC_MAXLEN => 5 ));
}

/* -------------------- (2) Text -------------------------------------------------  */
elseif ( $fdemo == 2 )
{
   $title = array(
      'Text Filter',
      'allowed syntax: exact value, wildcard (\'*\'), substring-search, range-syntax (swap reverse-values, e.g. e-a -> a-e)',
      "escape special chars with single-quotes, .e.g  a'-'  or  'a-'  for exact-value-text \'a-\' (not range)",
      'hover-text with a short syntax-help',
      'error is shown if invalid syntax entered',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Text #1', 'descr' => 'standard filter allowing exact, wildcard (not starting) and range-syntax' ),
      2 => array( 'label' => 'Text #2', 'descr' => 'no wildcard allowed (is normal char) [FC_NO_WILD]' ),
      3 => array( 'label' => 'Text #3', 'descr' => 'no range allowed [FC_NO_RANGE] (\'-\'-char is no special char any more)' ),
      4 => array( 'label' => 'Text #4', 'descr' => 'starting wildcard allowed [FC_START_WILD=1] without restrictions' ),
      5 => array( 'label' => 'Text #5', 'descr' => 'starting wildcard allowed [FC_START_WILD=STARTWILD_OPTMINCHARS] with min-char restriction of 4 chars' ),
      6 => array( 'label' => 'Text #6', 'descr' => 'substring-search using implicit wildcards at start and end of string, needs to set FC_START_WILD too [FC_SUBSTRING,FC_START_WILD=1]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Text', 'text1', true);

   # no wildcard allowed (wild-char is normal char)
   $filter->add_filter( 2, 'Text', 'text2', true,
         array( FC_NO_WILD => 1 ));

   # no range allowed
   $filter->add_filter( 3, 'Text', 'text3', true,
         array( FC_NO_RANGE => 1 ));

   # start-wildcard allowed (at least 1 char)
   $filter->add_filter( 4, 'Text', 'text4', true,
         array( FC_START_WILD => 1 ));

   # start-wildcard allowed (with optimization)
   $filter->add_filter( 5, 'Text', 'text5', true,
         array( FC_START_WILD => STARTWILD_OPTMINCHARS ));

   # substring-search (using implicit wildcards at start and end of string), FC_START_WILD mandatory
   $filter->add_filter( 6, 'Text', 'text6', true,
         array( FC_SUBSTRING => 1, FC_START_WILD => 1 ));
}

/* -------------------- (3) Rating -----------------------------------------------  */
elseif ( $fdemo == 3 )
{
   $title = array(
      'Rating Filter',
      'allowed syntax: range value, exact value, range-syntax (swap reverse-values, e.g. 7k-3d -> 3d-7k)',
      'syntax for rank: 7k = 7 kyu = 7 k = 7gup = 7 gup, 2d = 2 dan = 2dan',
      'syntax for specific rank: 5k (+45), 5k (45%), 5k+45%, 5k-13%, 5k -13%, 5k (-13%); spaces can be omitted',
      'syntax for range-value: 7k is representing a rating between 7k (-50%) .. 7k (+49%)',
      '<b>quoting:</b> no quoting for the \'-\'-range-char is needed',
      'hover-text with a short syntax-help',
      'error is shown if invalid syntax entered',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Rating #1', 'descr' => 'standard filter allowing exact and range-syntax, [FC_SIZE=20]' ),
      2 => array( 'label' => 'Rating #2', 'descr' => 'no range allowed [FC_NO_RANGE], [FC_SIZE=20]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Rating', 'rank1', true, array( FC_SIZE => 20 ));

   # no range allowed
   $filter->add_filter( 2, 'Rating', 'rank2', true,
         array( FC_NO_RANGE => 1,
                FC_SIZE => 20 ));
}

/* -------------------- (4) Country ----------------------------------------------  */
elseif ( $fdemo == 4 )
{
   $title = array(
      'Country Filter',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Country #1', 'descr' => 'standard filter allowing to select all or specific country' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Country', 'cc1', true);
}

/* -------------------- (5) Date -------------------------------------------------  */
elseif ( $fdemo == 5 )
{
   $title = array(
      'Date Filter (absolute dates)',
      'absolute date-syntax: YYYY, YYYYMM, YYYYMMDD, YYYYMMDD hh, YYYYMMDD hhmm; \':\' allowed at any place',
      'allowed syntax: exact value, range-syntax (swap reverse-values)',
      'hover-text with a short syntax-help',
      'error is shown if invalid syntax entered',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Date #1', 'descr' => 'standard filter allowing exact and range-syntax' ),
      2 => array( 'label' => 'Date #2', 'descr' => 'no range allowed [FC_NO_RANGE]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Date', 'date1', true);

   # no range allowed
   $filter->add_filter( 2, 'Date', 'date2', true,
         array( FC_NO_RANGE => 1 ));
}

/* -------------------- (6) RelativeDate -----------------------------------------  */
elseif ( $fdemo == 6 )
{
   $title = array(
      'RelativeDate Filter (relative with optional absolute date)',
      'relative date-syntax: 30 or <30 (30x time-period ago until now), >30 (from ancient times ending at 30x time-periods ago)',
      'absolute date-syntax: YYYY, YYYYMM, YYYYMMDD, YYYYMMDD hh, YYYYMMDD hhmm; \':\' allowed at any place',
      'allowed syntax for absolute date: exact value, range-syntax (swap reverse-values) - using FilterDate to parse',
      'hover-text with a short syntax-help',
      'error is shown if invalid syntax entered',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Rel-Date #1', 'descr' => 'standard filter allowing to select time-unit (for relative-date)' ),
      2 => array( 'label' => 'Rel-Date #2', 'descr' => 'filter with specific time-units [FC_TIME_UNITS=FRDTU_YMWD]: years, months, weeks, days' ),
      3 => array( 'label' => 'Rel-Date #3', 'descr' => 'filter with specific time-units [FC_TIME_UNITS]: years, weeks, hours' ),
      4 => array( 'label' => 'Rel-Date #4', 'descr' => 'filter with only one static time-unit [FC_TIME_UNITS=FRDTU_DAY]' ),
      5 => array( 'label' => 'Rel-Date #5', 'descr' => 'filter with additional absolute-date [FC_TIME_UNITS=FRDTU_ABS|FRDTU_ALL], [FC_SIZE=12]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'RelativeDate', 'reldate1', true);

   # filter with specific time-units (years, months, weeks, days)
   $filter->add_filter( 2, 'RelativeDate', 'reldate2', true,
         array( FC_TIME_UNITS => FRDTU_YMWD ));

   # filter with specific time-units (weeks, days, hours)
   $filter->add_filter( 3, 'RelativeDate', 'reldate3', true,
         array( FC_TIME_UNITS => FRDTU_YEAR | FRDTU_WEEK | FRDTU_HOUR ));

   # filter with only one time-unit (other appearance, no selectbox)
   $filter->add_filter( 4, 'RelativeDate', 'reldate4', true,
         array( FC_TIME_UNITS => FRDTU_DAY ));

   # filter with additional absolute-date syntax
   $filter->add_filter( 5, 'RelativeDate', 'reldate5', true,
         array( FC_TIME_UNITS => FRDTU_ABS | FRDTU_ALL,
                FC_SIZE => 12 ));
}

/* -------------------- (7) Selection --------------------------------------------  */
elseif ( $fdemo == 7 )
{
   $title = array(
      'Selection Filter',
      'Supporting single-item selection',
      'Supporting multi-value selection (Hold Ctrl-key to select and/or deselect items)',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Selection #1 (Single)', 'descr' => 'standard filter (select one item)' ),
      2 => array( 'label' => 'Selection #2 (Single)', 'descr' => 'filter using complexer queries (select one item)' ),
      3 => array( 'label' => 'Selection #3 (Single)', 'descr' => 'filter using multi-line (select one item) [FC_SIZE=3]' ),
      4 => array( 'label' => 'Selection #4 (Multi)', 'descr' => 'filter allowing multi-value-selection (select none, one or more items) [FC_MULTIPLE] using scalar dbfield, default FC_SIZE for multi-values' ),
      5 => array( 'label' => 'Selection #5 (Multi)', 'descr' => 'filter with multi-value-selection [FC_MULTIPLE] using complex query, [FC_SIZE=4]' ),
      6 => array( 'label' => 'Selection #6 (Multi)', 'descr' => 'filter with multi-value-selection [FC_MULTIPLE] using complex query using HAVING-clause [FC_SIZE=4,FC_ADD_HAVING]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Selection',
      array( 'All' => '', 'item #1' => 'sel1a=23', 'item #2' => 'sel1b IN (7,8)' ),
      true);

   # filter using complex queries (QuerySQL)
   $filter->add_filter( 2, 'Selection',
      array( 'All elements' => '',
             'Element #1' =>
                  new QuerySQL( SQLP_FIELDS, 'RAND() AS sel2_order',
                                SQLP_FROM, 'Table2 T2',
                                SQLP_WHERE, "T2.sel2='abc'",
                                SQLP_ORDER, 'T2.sel2_order' ),
             'Element #2' =>
                  new QuerySQL( SQLP_FIELDS, 'RAND() AS sel2_random',
                                SQLP_HAVING, 'sql2_random < 0.5' ),
             'Element #3' => 'sel2a < sel2b' ),
      true);

   # multi-line selection
   $filter->add_filter( 3, 'Selection',
      array( 'All' => '', 'Line #1' => 'sel3=10', 'Line #2' => 'sel3=20',
             'Line #3' => 'sel3=30', 'Line #4' => 'sel3=40', 'Line #5' => 'sel3=50' ),
      true,
      array( FC_SIZE => 3 ));

   # multi-value selection on one db-fieldname (scalar)
   $filter->add_filter( 4, 'Selection', 'sel4', true,
      array( FC_MULTIPLE => array( 'Beer' => 'beer', 'Whiskey' => 'whis',
                                   'Whine' => 'whin', 'Wodka' => 'wodk' ) ));

   # multi-value selection on one db-fieldname (complex query)
   $filter->add_filter( 5, 'Selection',
      new QuerySQL( SQLP_FNAMES, 'sel5a',
                    SQLP_FROM, 'Table5 T5',
                    SQLP_WHERE, 'sel5b < 20', 'T5.sel5c IS NOT NULL' ),
      true,
      array( FC_SIZE => 4,
             FC_MULTIPLE => array( 'Steak' => 'stea', 'Noodles' => 'nood', 'Rice' => 'rice' ) ));

   # multi-value selection on one db-fieldname (complex query adding field in HAVING-clause)
   $filter->add_filter( 6, 'Selection',
      new QuerySQL( SQLP_FNAMES, 'sel6a',
                    SQLP_ORDER, 'sel6_sports DESC',
                    SQLP_LIMIT, '3,7' ),
      true,
      array( FC_SIZE => 4,
             FC_MULTIPLE => array( 'Golfing' => 'golf', 'Swimming' => 'swim', 'Diving' => 'dive',
                                   'Climbing' => 'climb', 'Walking' => 'walk' ),
             FC_ADD_HAVING => 1 ));
}

/* -------------------- (8) BoolSelect -------------------------------------------  */
elseif ( $fdemo == 8 )
{
   $title = array(
      'BoolSelect Filter',
      'used on db-field with values [Y|N]',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Bool-Select #1', 'descr' => 'standard filter allowing to All, Yes or No' ),
   );

   # standard filter
   $filter->add_filter( 1, 'BoolSelect', 'boolsel1', true);
}

/* -------------------- (9) RatedSelect ------------------------------------------  */
elseif ( $fdemo == 9 )
{
   $title = array(
      'RatedSelect Filter',
      'used on db-field with values [Y|Done|N] like Games.Rated',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Rated-Select #1', 'descr' => 'standard filter allowing to All, Yes or No' ),
   );

   # standard filter
   $filter->add_filter( 1, 'RatedSelect', 'ratedsel1', true);
}

/* -------------------- (10) Boolean ---------------------------------------------  */
elseif ( $fdemo == 10 )
{
   $title = array(
      'Boolean Filter',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Boolean #1', 'descr' => 'standard filter allowing to switch checkbox on or off (without label)' ),
      2 => array( 'label' => 'Boolean #2', 'descr' => 'filter allowing to switch checkbox on or off (with label) [FC_LABEL]' ),
      3 => array( 'label' => 'Boolean #3', 'descr' => 'filter with label and queries for both boolean-values [FC_LABEL]' ),
      4 => array( 'label' => 'Boolean #4', 'descr' => 'filter with label and complex query for one boolean-value (false) [FC_LABEL]' ),
      5 => array( 'label' => 'Boolean #5', 'descr' => 'filter with label and complex having query for default boolean-value (true) [FC_LABEL]' ),
   );

   # standard filter (without label), which makes not much sense
   $filter->add_filter( 1, 'Boolean', 'bool1=4711', true);

   # filter with label right to checkbox
   $filter->add_filter( 2, 'Boolean', 'bool2 > 42', true,
         array( FC_LABEL => 'Label-Text' ));

   # filter with label and query for both values
   $filter->add_filter( 3, 'Boolean',
         array( false => "bool3='bird'", true => "bool3 IN ('worm','insect')" ),
         true,
         array( FC_LABEL => 'Prey' ));

   # filter with label and query for one of the boolean-values using QuerySQL
   $filter->add_filter( 4, 'Boolean',
         array( false => new QuerySQL( SQLP_FROM, 'Table4 T4',
                                       SQLP_WHERE, "T4.bool4a=1 OR T4.bool4b='other'" )),
         true,
         array( FC_LABEL => 'Where-Clause' ));

   # filter with label and query as QuerySQL for the boolean-value 'true'
   $filter->add_filter( 5, 'Boolean',
         new QuerySQL( SQLP_FIELDS, 'IF(T5.bool5>10,-1,1) AS T5_bool5',
                       SQLP_FROM,   'Table5 T5',
                       SQLP_HAVING, 'T5_bool5 < 0' ),
         true,
         array( FC_LABEL => 'Having-Clause' ));
}

/* -------------------- (11) MysqlMatch ------------------------------------------  */
elseif ( $fdemo == 11 )
{
   $title = array(
      'MysqlMatch Filter',
      'syntax for fulltext-search boolean-mode: word +word -word &lt;word &gt;word ~word (word-group) word* "literal"',
      'note: uses double-quotes in boolean-mode to embrace literals, because it\'s a special syntax',
      'a word (search-term) consists of the chars  a-z0-9\'_  with a min-length of 4 chars; stopwords are ignored',
      'hover-text with a short syntax-help',
      'error is shown if invalid syntax entered',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Mysql-Match #1', 'descr' => 'standard filter to use mysql fulltext-search (in boolean-mode selectable)' ),
      2 => array( 'label' => 'Mysql-Match #2', 'descr' => 'filter to use mysql fulltext-search (using boolean-mode without checkbox) [FC_MATCH_MODE=MATCH_BOOLMODE_SET]' ),
      3 => array( 'label' => 'Mysql-Match #3', 'descr' => 'filter to use mysql fulltext-search (no boolean-mode without checkbox) [FC_MATCH_MODE=MATCH_BOOLMODE_OFF]' ),
      4 => array( 'label' => 'Mysql-Match #4', 'descr' => 'filter to use mysql fulltext-search (with query expansion) [FC_MATCH_MODE=MATCH_QUERY_EXPANSION]' ),
      5 => array( 'label' => 'Mysql-Match #5', 'descr' => 'filter to use mysql fulltext-search on several column-fields' ),
   );

   # standard filter (bool-mode checkbox)
   $filter->add_filter( 1, 'MysqlMatch', 'match1', true);

   # filter (bool-mode used, but without checkbox)
   $filter->add_filter( 2, 'MysqlMatch', 'match2', true,
         array( FC_MATCH_MODE => MATCH_BOOLMODE_SET ));

   # filter (bool-mode used, but without checkbox)
   $filter->add_filter( 3, 'MysqlMatch', 'match3', true,
         array( FC_MATCH_MODE => MATCH_BOOLMODE_OFF ));

   # filter (bool-mode used, but without checkbox)
   $filter->add_filter( 4, 'MysqlMatch', 'match4', true,
         array( FC_MATCH_MODE => MATCH_QUERY_EXPANSION ));

   # filter on several column-fields
   $filter->add_filter( 5, 'MysqlMatch', 'match5a,match5b,match5c', true,
         array( FC_MATCH_MODE => MATCH_BOOLMODE_OFF )); # <-- not needed (reducing web-output)
}

/* -------------------- (12) Score -----------------------------------------------  */
elseif ( $fdemo == 12 )
{
   $title = array(
      'Score Filter',
      'selectbox to choose from win-mode and input-form-element to enter score (float or int with exact or range-syntax) for \'X+?\'-choices',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Score #1', 'descr' => 'standard filter allowing to select win-mode '
            . '(by B/W, by Resignation/Time/Score or Jigo) and/or enter score (float or int, exact or range-syntax) for \'?+?, B+?, W+?\'-choice' ),
      2 => array( 'label' => 'Score #2', 'descr' => 'filter with forbidden range-syntax for score-input-field [FC_NO_RANGE]' ),
   );

   # standard filter
   $filter->add_filter( 1, 'Score', 'score1', true);

   # no range-allowed for score
   $filter->add_filter( 2, 'Score', 'score2', true,
         array( FC_NO_RANGE => 1 ));
}

/* -------------------- (13) RatingDiff ------------------------------------------  */
elseif ( $fdemo == 13 )
{
   $title = array(
      'RatingDiff Filter',
      'syntax: exact or range as integer or float-value, e.g. \'0.4-\'',
      'uses FilterNumeric with fix NUM-factor of 100 (therefore inheriting all configs from FilterNumeric)',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Rating-Diff #1', 'descr' => 'standard filter allowing exact and range-syntax for float and integer-values' ),
   );

   # standard filter
   $filter->add_filter( 1, 'RatingDiff', 'ratdiff1', true);
}

/* -------------------- (14) CheckboxArray ---------------------------------------  */
elseif ( $fdemo == 14 )
{
   $title = array(
      'CheckboxArray Filter',
      'if selecting none: often meaning is same as checking all entries (though not always the same)',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Checkbox-Array #1', 'descr' => 'standard filter allowing to select none, one or more choices [FC_MULTIPLE]' ),
      2 => array( 'label' => 'Checkbox-Array #2', 'descr' => 'filter allowing to select none, one or more choices showed in 3 columns [FC_MULTIPLE,FC_SIZE=3]' ),
      3 => array( 'label' => 'Checkbox-Array #3', 'descr' => 'filter supporting bitmask-searching [FC_MULTIPLE,FC_BITMASK]' ),
   );

   # standard filter with mandatory FC_MULTIPLE
   $filter->add_filter( 1, 'CheckboxArray', 'chkbox1', true,
         array( FC_MULTIPLE => array(
                  '<td>%s&nbsp;Row1-Col1</td>' => 'val1',
                  '<td>%s&nbsp;Row2-Col1</td>' => 'val2',
                )));

   # filter with 3 columns
   $filter->add_filter( 2, 'CheckboxArray', 'chkbox2', true,
         array( FC_SIZE => 3,
                FC_MULTIPLE => array(
                   '<td>%s&nbsp;Row1-Col1</td>' => 'val1',
                   '<td>%s&nbsp;Row1-Col2</td>' => 'val2',
                   '<td>%s&nbsp;Row1-Col3</td>' => 'val3',
                   '<td>%s&nbsp;Row2-Col1</td>' => 'val4',
                )));

   # filter with bitmask FC_BITMASK
   $filter->add_filter( 3, 'CheckboxArray', 'chkbox3', true,
         array( FC_BITMASK => 1,
                FC_MULTIPLE => array(
                  '<td>%s&nbsp;Row1-Col1 0x07</td>' => 0x07,
                  '<td>%s&nbsp;Row2-Col1 0x20</td>' => 0x20,
                )));
}

/* -------------------- (15) Defaults / (19) Without Defaults --------------------  */
elseif ( $fdemo == 15 || $fdemo == 19 )
{
   $with_def = ( $fdemo == 15 ); # show defaults
   $title = array(
      'Handling Defaults for Filters' . ( $with_def ? '' : '(show without defaults)' ),
      'Filter-specific default using [FC_DEFAULT]-config (see according filter-doc) for single- or multi-element filters',
      'Another way to use a default would be to set the values using URL-vars',
   );
   $area_layout = '1|2';
   $arr_layout = array(
       1 => array( 'area' => 1, 'label' => 'Default #1',  'descr' => 'Numeric-filter' ),
       2 => array( 'area' => 1, 'label' => 'Default #2',  'descr' => 'Text-filter' ),
       3 => array( 'area' => 1, 'label' => 'Default #3',  'descr' => 'Rating-filter' ),
       4 => array( 'area' => 1, 'label' => 'Default #4',  'descr' => 'Country-filter' ),
       5 => array( 'area' => 1, 'label' => 'Default #5',  'descr' => 'Date-filter' ),
       7 => array( 'area' => 1, 'label' => 'Default #7',  'descr' => 'single-value Selection-filter' ),
       8 => array( 'area' => 1, 'label' => 'Default #8',  'descr' => 'multi-value Selection-filter' ),
       9 => array( 'area' => 1, 'label' => 'Default #9',  'descr' => 'BoolSelect-filter' ),
      10 => array( 'area' => 1, 'label' => 'Default #10', 'descr' => 'RatedSelect-filter' ),
      11 => array( 'area' => 1, 'label' => 'Default #11', 'descr' => 'Boolean-filter' ),
      14 => array( 'area' => 1, 'label' => 'Default #14', 'descr' => 'RatingDiff-filter' ),
      // multi-elements:
       6 => array( 'area' => 2, 'label' => 'Default #6 (multi)',  'descr' => 'RelativeDate-filter (multi-element)' ),
      12 => array( 'area' => 2, 'label' => 'Default #12 (multi)', 'descr' => 'MysqlMatch-filter (multi-element)' ),
      13 => array( 'area' => 2, 'label' => 'Default #13 (multi)', 'descr' => 'Score-filter (multi-element)' ),
      15 => array( 'area' => 2, 'label' => 'Default #15 (multi)', 'descr' => 'CheckboxArray-filter (multi-element)' ),
   );

   # default for numeric-filter
   $filter->add_filter( 1, 'Numeric', 'def_num1', true,
         def_array( $with_def, array( FC_DEFAULT => 42 )));

   # default for text-filter
   $filter->add_filter( 2, 'Text', 'def_text2', true,
         def_array( $with_def, array( FC_DEFAULT => 'ape' )));

   # default for rating-filter
   $filter->add_filter( 3, 'Rating', 'def_rating3', true,
         def_array( $with_def, array( FC_DEFAULT => '7k-1d (-25%)', FC_SIZE => 10 )));

   # default for country-filter
   $filter->add_filter( 4, 'Country', 'def_ccode4', true,
         def_array( $with_def, array( FC_DEFAULT => 'za' )));

   # default for date-filter
   $filter->add_filter( 5, 'Date', 'def_date5', true,
         def_array( $with_def, array( FC_DEFAULT => '200502-' )));

   # default for multi-element relative-date-filter
   $filter->add_filter( 6, 'RelativeDate', 'def_reldate6', true,
         def_array( $with_def, array(
            FC_TIME_UNITS => FRDTU_ABS | FRDTU_ALL,
            FC_DEFAULT => array( '' => '2007', 'tu' => FRDTU_ABS ))));

   # default for selection-filter
   $filter->add_filter( 7, 'Selection',
         array( 'All' => '', 'Choice #1' => 'def_sel7=10', 'Choice #2' => 'def_sel7>61' ),
         true,
         def_array( $with_def, array( FC_DEFAULT => 2 )));

   # default for multi-selection-filter
   $filter->add_filter( 8, 'Selection', 'def_sel8', true,
         def_array( $with_def, array(
            FC_SIZE => 3,
            FC_MULTIPLE => array( 'Beer'  => 'beer', 'Whiskey' => 'whis',
                                  'Whine' => 'whin', 'Wodka'   => 'wodk' ),
            FC_DEFAULT  => array( 1, 2 ) )));

   # default for bool-select-filter
   $filter->add_filter( 9, 'BoolSelect', 'def_boolsel9', true,
         def_array( $with_def, array( FC_DEFAULT => 2 )));

   # default for rated-select-filter
   $filter->add_filter(10, 'RatedSelect', 'def_ratedsel10', true,
         def_array( $with_def, array( FC_DEFAULT => 1 )));

   # default for boolean-filter
   $filter->add_filter(11, 'Boolean', 'def_bool11', true,
         def_array( $with_def, array(
            FC_DEFAULT => true, FC_LABEL => 'Set by default' )));

   # default for multi-element mysqlmatch-filter
   $filter->add_filter(12, 'MysqlMatch', 'def_term12', true,
         def_array( $with_def, array(
            FC_SIZE => 20,
            FC_DEFAULT => array( '' => 'tree +leaf -root', 'b' => true ))));

   # default for multi-element score-filter
   $filter->add_filter(13, 'Score', 'def_score13', true,
         def_array( $with_def, array(
            FC_DEFAULT => array( '' => '0.5-2', 'r' => FSCORE_B_SCORE ))));

   # default for ratingdiff-filter
   $filter->add_filter(14, 'RatingDiff', 'def_ratdiff14', true,
         def_array( $with_def, array( FC_DEFAULT => '0.5-1.2' )));

   # default for multi-element checkbox-array-filter
   $filter->add_filter(15, 'CheckboxArray', 'def_chkbox15', true,
         def_array( $with_def, array(
            FC_SIZE => 2,
            FC_MULTIPLE => array(
               '<td>%s&nbsp;Row1-Col1</td>' => array( 'val1' ),
               '<td>%s&nbsp;Row1-Col2</td>' => array( 'val2' ),
               '<td>%s&nbsp;Row1-Col3</td>' => array( 'val3' )),
            FC_DEFAULT => array( 2, 3 ) )));
            #FC_DEFAULT => array( '_2' => 1, '_3' => 1 ) )));
}

/* -------------------- (16) Conditionals ----------------------------------------  */
elseif ( $fdemo == 16 )
{
   $title = array(
      'Conditional Filters',
      '[FC_SQL_SKIP]: Support to skip filter when building query from SearchFilter-object; '
         . 'can also be used to manually combine filters into more complex queries',
      '[FC_IF]: Support for conditions (expression) on filter-values and queries; '
         . 'see doc of eval_condition-func in include/filters.php',
      '[FC_IF]: If condition is fulfilled, action on filters can be performed: '
         . 'set-value, set-active, set-visible; '
         . 'see doc of perform_conditional_action-func in include/filters.php',
   );
   $arr_layout = array(
      1 => array( 'label' => 'Cond-Control #1', 'descr' => 'Please enter values \'1st\', \'2nd\', \'3rd\'; Filter without influence to query [FC_SQL_SKIP]' ),
      2 => array( 'label' => 'Conditional #2', 'descr' => 'Filter dependent on #1: if value == \'1st\' -> use value 0815 [FC_IF]' ),
      3 => array( 'label' => 'Conditional #3', 'descr' => 'Filter dependent on #1: if value == \'2nd\' -> use value 4711 and clear filter #2 [FC_IF]' ),
      4 => array( 'label' => 'Conditional #4', 'descr' => 'Filter dependent on #2 and #3: if both values set -> activate [FC_IF]' ),
      5 => array( 'label' => 'Conditional #5', 'descr' => 'Filter dependent on #1: if value == \'3rd\' -> setup multi-element filter: set value of 30 hours for RelativeDate [FC_IF]' ),
      6 => array( 'label' => 'Complex-Query #6', 'descr' => 'Filter to manually build complex query [FC_SQL_SKIP]' ),
   );

   # text-filter (skip query) used for conditional-control without influence to query [FC_SQL_SKIP=1]
   $filter->add_filter( 1, 'Text', 'cond1', true,
         array( FC_SQL_SKIP => 1 ));

   # conditional filter (depend on filter #1): if val='2nd' -> set my value
   $filter->add_filter( 2, 'Numeric', 'cond3', true,
         array( FC_IF => array( "V1=='1st'", 'SET_VAL F,N,0815' )));

   # conditional filter (depend on filter #1): if val='1st' -> set my value and clear filter #2
   $filter->add_filter( 3, 'Numeric', 'cond2', true,
         array( FC_IF => array( "V1=='2nd'", 'SET_VAL F,N,4711', 'SET_VAL F2,N1,' )));

   # conditional filter (depend on filter #2+3): if both has value <> empty|0 -> activate
   $filter->add_filter( 4, 'Boolean', 'cond4', true,
         array( FC_LABEL => 'Conditional Activate',
                FC_IF => array( "V2 and V3", 'SET_VAL F,N,1' )));

   # conditional filter (depend on filter #1): if val='3rd'-> set rel-date of 30 hours (multi-element)
   $filter->add_filter( 5, 'RelativeDate', 'cond5', true,
         array( FC_TIME_UNITS => FRDTU_ALL,
                FC_IF => array( "V1=='3rd'", 'SET_VAL F,N,'.FRDTU_HOUR, 'SET_VAL F,N2,30' )));

   # filter (skip query) to manually build complex query
   # note: for building query see below
   $filter->add_filter( 6, 'Boolean', 'cond6 IS NOT NULL', true,
         array( FC_LABEL => 'Build complex query', FC_SQL_SKIP => 1 ));
}

/* -------------------- (17) Quoting / Escaping ----------------------------------  */
elseif ( $fdemo == 17 )
{
   $tc = createTokenizerConfig();
   $s = $tc->sep;
   $w = $tc->wild;

   $title = array(
      'Quoting / Escaping',
      'All filters use the same quote-type. There are some few exceptions, that are documented in the docs of filters.php!',
      'Support for three different quote-types: ESCAPE, QUOTE, DOUBLE to escape '
         . "special-chars in filter-syntax like range-char [$s] or wildcard-char [$w]",
      "Syntax (ESCAPE): need escape-char, double to get escape-char; default is [\\]; Examples: no\\{$w}wild\\{$w}  fish\\{$s}head",
      "Syntax (QUOTE):  need start- and end-quote, double to get escape-char; default ['']; Examples: 'no{$w}wild$w'  no'$w'wild'$w'  'fish{$s}head'  fish'$s'head",
      "Syntax (DOUBLE): double char to quote; Examples: no$w{$w}wild$w$w  fish$s{$s}head",
   );
   $arr_layout = array(
      1 => array( 'label' => 'Quote #1', 'descr' => 'text-filter using ESCAPE-quoting [FC_QUOTETYPE=QUOTETYPE_ESCAPE]: prefix quote-char with backslash \'\\\'' ),
      2 => array( 'label' => 'Quote #2', 'descr' => "text-filter using QUOTE-quoting [FC_QUOTETYPE=QUOTETYPE_QUOTE]: enclose quoted chars with single-quotes '..'" ),
      3 => array( 'label' => 'Quote #3', 'descr' => 'text-filter using DOUBLE-quoting [FC_QUOTETYPE=QUOTETYPE_DOUBLE]: double quote-char to quote' ),
   );

   # text-filter with ESCAPE-quoting
   $filter->add_filter( 1, 'Text', 'quote1', true,
      array( FC_QUOTETYPE => QUOTETYPE_ESCAPE ));

   # text-filter with QUOTE-quoting
   $filter->add_filter( 2, 'Text', 'quote2', true,
      array( FC_QUOTETYPE => QUOTETYPE_QUOTE ));

   # text-filter with DOUBLE-quoting
   $filter->add_filter( 3, 'Text', 'quote3', true,
      array( FC_QUOTETYPE => QUOTETYPE_DOUBLE ));
}

/* -------------------- (18) Miscellaneous----------------------------------------  */
elseif ( $fdemo == 18 )
{
   $title = array(
      'Miscellaneous Filter-Config',
      'for [FC_STATIC], see <a href="filter_example.php">code_examples/filter_example.php</a> - allows to make a filter-element static (with no hide-toggle), which makes only sense with FILTER_CONF_FORCE_STATIC = false',
      'for [FC_HIDE], see <a href="filter_example.php">code_examples/filter_example.php</a> - allows to show a hide-toggle for a filter-element even if FILTER_CONF_FORCE_STATIC = true',
      'use [FC_SYNTAX_HINT] to extend (text-based) filters default syntax-description with more text, for example see Numeric-Filter #2',
   );
}


/* -------------------- Show Form & Filters --------------------------------------  */

# parse filters from URL
$filter->init();

// attach filter-manage-vars only
$form->attach_table( $filter->get_req_params(GETFILTER_NONE) );

# add hidden for common fdemo into filter-form
$rq = new RequestParameters();
$rq->add_entry( 'fdemo', $fdemo );
$form->attach_table( $rq );

# form-area-layout
if ( $area_layout )
{
   $form->set_layout( FLAYOUT_GLOBAL, $area_layout );
   $form->set_layout( FLAYOUT_AREACONF, FAREA_ALL,
      array( FAC_ENVTABLE => 'align=center' ) );
}

# build form with filters from arr_layout:
#    filter-id => arr( label|descr|noerr => val )
foreach( $arr_layout as $fid => $felem )
{
   $arr = array();
   array_push( $arr, 'DESCRIPTION', $felem['label'] );
   array_push( $arr, 'FILTER', $filter, $fid );
   array_push( $arr, 'TD', 'TEXT', "&nbsp;<font size=-1>" . $felem['descr'] . "</font>" );

   $area = @$felem['area'];
   if ( $area )
      $form->set_area( $area );

   $form->add_row( $arr );
   if ( !isset($felem['noerr']) )
   {
      $arr = array(
         'TAB', 'CELL', 2, 'align=left',
         'FILTERWARN',  $filter, $fid, $FWRN1, $FWRN2."<BR>", true );
         'FILTERERROR', $filter, $fid, $FERR1, $FERR2."<BR>", true );
      $form->add_row( $arr );
   }
}

# add submits to form
$form->add_row( array(
      'TAB',
      'CELL',    2, 'align=left',
      'OWNHTML', implode( '', $filter->get_submit_elements() ) ));

# build (integrate filters) and print query
$query->merge( $filter->get_query() );
$qstr = $query->get_select();


/* ---------- demo-type specifics on query - START ---------- */
if ( $fdemo == 16 ) // Conditionals
{
   $cquery = new QuerySQL( SQLP_FIELDS, '*', SQLP_FROM, 'TableXYZ', SQLP_WHERE, 'conditionXYZ' );
   $f6 = $filter->get_filter(6);
   if ( $f6->get_value() )
   {
      $qf6 = $f6->get_query();
      if ( !is_null($qf6) )
      {
         $cquery->merge( $qf6 );
         $qstr = '(' . $cquery->get_select() . ") UNION ($qstr)";
      }
   }
}
/* ---------- demo-type specifics on query - END ------------ */


echo "<hr>\n";
if ( $DEBUG_SQL ) echo "<br>QUERY: " . make_html_safe($qstr) . "<br>\n";


/* ---------- demo-type specifics extras - START ---------- */
# some extra info
if ( $fdemo == 11 ) // mysql-match
{
   echo "<font size=-1>\n";
   foreach( $filter->get_filter_keys(GETFILTER_ALL) as $fid )
   {
      $fref =& $filter->get_filter($fid);
      echo "<br>Filter #{$fid}: get_match_query_part() = [" . $fref->get_match_query_part() . "]\n"
         . "<br>Filter #{$fid}: get_rx_term = [" . implode('|', $fref->get_rx_terms() ) . "]<br>\n";
   }
   echo "</font>\n";
}
/* ---------- demo-type specifics extras - END ------------ */


# print filter-info + form
echo "<hr>\n";
if ( is_array($title) )
{
   $title0 = array_shift( $title );
   echo "<h4>$title0</h4>\n";
   if ( count($title) > 0 )
   {
      echo "<ul>\n";
      foreach( $title as $item )
         echo "  <li>$item</li>\n";
      echo "</ul>\n";
   }
}
elseif ( $title != '' )
   echo "<h4>$title</h4>\n";
$form->echo_string();

echo "</body></html>\n";

?>

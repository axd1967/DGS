<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
 * Code example demonstrating use of Filter-Framework (Table + Form):
 *   1. with Form + Filters
 *   2. with External-Form + Table + Filters
 *   3. Table + Filters
 *
 * Usage: open its URL in browser
 */

chdir("../");
require_once("include/std_functions.php");
require_once("include/std_classes.php");
require_once("include/form_functions.php");
require_once("include/table_columns.php");
require_once("include/filter.php");
chdir("code_examples/");

/* init vars */

$page = 'filter_example.php';

$DEBUG_SQL = true;

echo "<html><body>\n";

// Filter-Demos with types and config
$arr_filter_demos = array(
   1 => 'Form with Filters + Show-Rows',
   2 => 'Table + External-Form with Filters',
   3 => 'Table with Filters',
);

$fdemo = get_request_arg('fdemo');
if( !isset($arr_filter_demos[$fdemo]) )
   $fdemo = 1;

/* control-form to select filter-demo */

# select demo
$actform = new Form( "myname", $page, FORM_GET );
$actform->add_row( array(
      'DESCRIPTION',  'Filter-DemoType',
      'SELECTBOX',    'fdemo', 1, $arr_filter_demos, $fdemo, false,
      'SUBMITBUTTON', 'action', 'Choose Filter-DemoType',
      ));
$actform->echo_string();

# prep hidden for adding common fdemo into filter-forms
$rq = new RequestParameters();
$rq->add_entry( 'fdemo', $fdemo );


/* init vars for filters */

$ARR_ANIMALS = array( 1 => 'monkey', 2 => 'lion', 3 => 'dog' );

$qsql = new QuerySQL(
   SQLP_FIELDS, '*',
   SQLP_FROM, 'TableName' );


/* -------------------- (1) Form + Filters + Show-Rows ---------------------------  */
// other example: see forum/search.php

if( $fdemo == 1 )
{
   echo "<h3>Example #1 - Form used with Filter (+ Show-Rows-selection)</h3>\n";

   // static filters
   $filter = new SearchFilter('t1');
   $f1 =& $filter->add_filter( 1, 'Text', 'textColA', true,
         array( FC_START_WILD => 1 ));
   $f2 =& $filter->add_filter( 2, 'Text', 'textColB', true,
         array( FC_NO_RANGE => 1, FC_START_WILD => STARTWILD_OPTMINCHARS ));
   $filter->init();

   // form for static filters
   $form = new Form( 'table1', $page, FORM_GET );
   $form->attach_table( $rq ); // add fdemo-var

   $form->add_row( array(
         'DESCRIPTION', 'Text-Column A',
         'FILTER',      $filter, 1,
         'TEXT',        $f1->get_syntax_description(),
         'BR',
         'FILTERERROR', $filter, 1, $FERR1, $FERR2."<BR>", true,
         ));
   $form->add_row( array(
         'DESCRIPTION', 'Text-Column B',
         'FILTER',      $filter, 2,
         'TEXT',        $f2->get_syntax_description(),
         'BR',
         'FILTERERROR', $filter, 2, $FERR1, $FERR2."<BR>", true,
         ));
   $form->add_row( array(
         'TAB',
         'CELL',        1, 'align=left',
         'OWNHTML',     implode( '', $filter->get_submit_elements( $form ) ) ));

   $maxrows = get_request_arg( 'maxrows' );
   $form->add_row( array(
         'DESCRIPTION', 'Show Rows',
         'SELECTBOX',   'maxrows', 1, build_maxrows_array($maxrows), $maxrows, false ));

   $q1 = $qsql->duplicate();
   $q1->merge( $filter->get_query() );
   $form->echo_string();
   if( $DEBUG_SQL ) echo "<br>QUERY: " . make_html_safe($q1->get_select()) . "<br>\n";
   if( $DEBUG_SQL ) echo "<br>Max Rows: " . $maxrows . "<br>\n";
}



/* -------------------- (2) External-Form + Table + Filters ----------------------  */
// other example: see search_messages.php, opponents.php

if( $fdemo == 2 )
{
   echo "<hr size=1>\n";
   echo "<h3>Example #2 - External-Form and Table with Filter</h3>\n";

   // static filters
   $filter = new SearchFilter('t2s');
   $filter->add_filter(1, 'Boolean', "boolColC='a_value'", true);
   $filter->add_filter(2, 'Boolean', array( true => 'condition1', false => 'condition2'), true,
         array( FC_LABEL => 'label #2' ));
   $filter->init();

   // table filters
   $tfilter = new SearchFilter('t2');
   $tfilter->add_filter( 1, 'Numeric', 'ID', true);
   $tfilter->add_filter( 2, 'Text',    'Animal', true,
         array( FC_NO_WILD => 1, FC_HIDE => 1 ));
   $tfilter->init();

   $table = new Table( 'table2', $page, '' );
   $table->register_filter( $tfilter );
   $table->add_or_del_column();
   $table->add_external_parameters( $rq, true ); // add fdemo-var

   // External-Form
   $form = new Form( 'table2', $page, FORM_GET, false );
   $form->set_config( FEC_EXTERNAL_FORM, true );
   $table->set_externalform( $form ); // also attach offset, sort, manage-filter as hidden (table) to ext-form
   $form->attach_table( $filter ); // attach manage-filter as hiddens (static) to ext-form
   $form->attach_table( $rq ); // add fdemo-var

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, 'ID', 'ID', TABLE_NO_HIDE, 'ID+');
   $table->add_tablehead( 2, 'Animal', '', 0, 'Animal+');

   // form for static filters
   $form->add_row( array(
         'DESCRIPTION', 'Bool-Column C',
         'FILTER',      $filter, 1,
         'BR',
         'FILTERERROR', $filter, 1, $FERR1, $FERR2."<BR>", true,
         ));
   $form->add_row( array(
         'DESCRIPTION', 'Bool-Column D',
         'FILTER',      $filter, 2,
         'BR',
         'FILTERERROR', $filter, 2, $FERR1, $FERR2."<BR>", true,
         ));
   $form->add_row( array(
         'TAB',
         'CELL',        1, 'align=left',
         'OWNHTML',     implode( '', $filter->get_submit_elements( $form ) ) ));

   // build SQL-query (for user-table)
   $q2 = $qsql->duplicate();
   $q2->merge( $filter->get_query(GETFILTER_ALL) );
   $q2->merge( $table->get_query() );

   // build table
   foreach( $ARR_ANIMALS as $id => $animal )
   {
      $arr_row = array();
      if( $table->Is_Column_Displayed[1] )
         $arr_row[1] = $id;
      if( $table->Is_Column_Displayed[2] )
         $arr_row[2] = $animal;
      $table->add_row( $arr_row );
   }

   // print static-filter, table
   echo $form->print_start_default()
      . $form->get_form_string() // static form
      . $table->make_table()
      . $form->print_end();
   if( $DEBUG_SQL ) echo "<br>QUERY: " . make_html_safe($q2->get_select()) . "<br>\n";
}



/* -------------------- (3) Table + Filters --------------------------------------  */
// other example: see waiting_room.php, users.php, show_games.php

if( $fdemo == 3 )
{
   echo "<hr size=1>\n";
   echo "<h3>Example #3 - Table with Filter</h3>\n";

   // table filters
   $tfilter = new SearchFilter('t3');
   $tfilter->add_filter( 1, 'RatedSelect', 'Rated', true,
         array( FC_STATIC => 1, FC_ADD_HAVING => 1, FC_HIDE => 1 ));
   $tfilter->add_filter( 2, 'Boolean', "ActiveLevel>$ActiveLevel1", true,
         array( FC_LABEL => 'Activity', FC_STATIC => 1 ));
   $tfilter->init();

   $table = new Table( 'table3', $page, '' );
   $table->use_show_rows( false );
   $table->register_filter( $tfilter );
   $table->add_or_del_column();
   $table->add_external_parameters( $rq, true ); // add fdemo-var

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, 'ID', 'ID', TABLE_NO_HIDE, 'ID+');
   $table->add_tablehead( 2, 'Animal', '', 0, 'Animal+');

   // build SQL-query (for user-table)
   $q3 = $qsql->duplicate();
   $q3->merge( $table->get_query() );

   // build table
   foreach( $ARR_ANIMALS as $id => $animal )
   {
      $arr_row = array();
      if( $table->Is_Column_Displayed[1] )
         $arr_row[1] = $id;
      if( $table->Is_Column_Displayed[2] )
         $arr_row[2] = $animal;
      $table->add_row( $arr_row );
   }

   $table->echo_table();
   if( $DEBUG_SQL ) echo "<br>QUERY: " . make_html_safe($q3->get_select()) . "<br>\n";
}

echo "</body></html>\n";

?>

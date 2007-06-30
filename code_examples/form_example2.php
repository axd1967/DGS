<?php

/*
 * Code example using area-layout of Form-class.
 *
 * Usage: open its URL in browser
 */

chdir("../");
require_once("include/form_functions.php");
chdir("code_examples/");

/* init vars */

$arr_layouts = array(
   0 => array( 'layout' => 'Standard' ),
   1 => array( 'layout' => '1,2,3,4,5' ),
   2 => array( 'layout' => '1|2|3|4|5' ),
   3 => array( 'layout' => '1,2|3|4,5' ),
   4 => array( 'layout' => '1,2|(3,4)|5' ),
   5 => array( 'layout' => '1|(2,(3)|(4,5))' ),
   6 => array( 'layout' => '5,4,3,2|1' ),
);
$form_layouts = array(); # for action-form
foreach( $arr_layouts as $idx => $arr )
   $form_layouts[$idx] = $arr['layout'] . "  ";

$col1 = 'red';
$col2 = 'blue';
$col3 = 'green';
$col4 = 'grey';
$col5 = 'magenta';
function ff( $col, $msg ) {
   return "<font color=\"$col\">$msg</font>";
}

$layout = get_request_arg('layout');
if ( !isset($arr_layouts[$layout]) )
   $layout = 1;
$center = (bool) get_request_arg('center');
$border = (bool) get_request_arg('border');

/* control-form to select layout for area-layouted form */

# select layout
$actform = new Form( "myname", "form_example2.php", FORM_POST );
$actform->add_row( array(
      'DESCRIPTION',  'Layout',
      'SELECTBOX',    'layout', 1, $form_layouts, $layout, false,
      'SUBMITBUTTON', 'action', 'Choose Layout',
      'CHECKBOX',     'center', '1', 'Display centered areas', (bool) $center,
      'CHECKBOX',     'border', '1', 'Display table-borders', (bool) $border, ));
$actform->echo_string();



/* build area-layouted form */

$the_form = new Form( "myname", "form_example2.php", FORM_POST );

// set layout
if ( $layout )
   $the_form->set_layout( FLAYOUT_GLOBAL, $arr_layouts[$layout]['layout'] );

// set config for layout
$the_form->set_layout( FLAYOUT_AREACONF, FAREA_ALL,
   array(
      FAC_TABLE    => ($border) ? 'border=1' : '',
      FAC_ENVTABLE => ($center) ? 'align=center' : '',
   ) );

// setup form with areas
#$the_form->set_area( 1 );  # default-area is 1
$the_form->add_row( array(
   'TEXT', ff($col1, 'Area 1 - Row 1 - Col 1'),
   'TD',
   'TEXT', ff($col1, 'Area 1 - Row 1 - Col 2'), ) );
$the_form->add_row( array(
   'TEXT', ff($col1, 'Area 1 - Row 2 - Col 1'),
   'TD',
   'TEXT', ff($col1, 'Area 1 - Row 2 - Col 2'), ) );

if ( $layout ) $the_form->set_area( 2 );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 2') ) );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 3') ) );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 4') ) );

if ( $layout ) $the_form->set_area( 3 );
$the_form->add_row( array( 'TEXT', ff($col3, 'Area 3 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col3, 'Area 3 - Row 2') ) );
$the_form->add_row( array( 'TEXT', ff($col3, 'Area 3 - Row 3') ) );

if ( $layout ) $the_form->set_area( 4 );
$the_form->add_row( array( 'TEXT', ff($col4, 'Area 4 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col4, 'Area 4 - Row 2') ) );

if ( $layout ) $the_form->set_area( 5 );
$the_form->add_row( array( 'TEXT', ff($col5, 'Area 5 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col5, 'Area 5 - Row 2') ) );
$the_form->add_row( array( 'TEXT', ff($col5, 'Area 5 - Row 3') ) );

$the_form->echo_string();


// present some internal vars from Form
if ( isset($the_form->layout[FLAYOUT_GLOBAL]) )
{
   echo "<br><br>\nvar_export:\n<font size=+2><pre>"
      . "global-layout: " . var_export( $the_form->orig_layout, true ) ."\n\n"
      . "layout-array (for debugging):\n" .
         var_export( $the_form->layout[FLAYOUT_GLOBAL], true ) . "\n"
      . "</pre></font>\n";
}

?>

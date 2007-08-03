<?php

/*
 * Code example using area-layout of Form-class.
 *
 * Usage: open its URL in browser
 */

chdir("../");
//require_once("include/quick_common.php");
require_once( "include/std_functions.php" );
require_once("include/form_functions.php");
chdir("code_examples/");

/* init vars */

$arr_layouts = array(
   array( 'layout' => 'Standard' ),
   array( 'layout' => '1,2,3,4,5' ),
   array( 'layout' => '1|2|3|4|5' ),
   array( 'layout' => '1,2,(3),4,5' ),
   array( 'layout' => '1|2|(3)|4|5' ),
   array( 'layout' => '1,2,(((3))),4,5' ),
   array( 'layout' => '1,2|3|4,5' ),
   array( 'layout' => '1|2,3,4|5' ),
   array( 'layout' => '1,(2|3|4),5' ),
   array( 'layout' => '1|(2,3,4)|5' ),
   array( 'layout' => '1,2|(3,4)|5' ),
   array( 'layout' => '1|(2,(3)|(4,5))' ),
   array( 'layout' => '5,4,3,2|1' ),
   array( 'layout' => '4|5,3|5,2|5,1|5' ),
   array( 'layout' => '(4,5)|(3,5)|(2,5)|(1,5)' ),
);
$form_layouts = array(); # for action-form
foreach( $arr_layouts as $idx => $arr )
   $form_layouts[$idx] = $arr['layout'] . "  ";

$col1 = 'grey';
$col2 = 'green';
$col3 = 'blue';
$col4 = 'red';
$col5 = 'magenta';
function ff( $col, $msg ) {
   return "<font color=\"$col\">$msg</font>";
}

$layout = get_request_arg('layout');
if ( !isset($arr_layouts[$layout]) )
   $layout = 1;
$align = (int)@$_REQUEST['align'];
$center = (bool)@$_REQUEST['center'];
if( $center ) $align= 0;
$border = (bool)@$_REQUEST['border'];
$title = (bool)@$_REQUEST['title'];



/* define local style */

$page_style ='
table.FormClass {border: 1px solid red;}
td.FormClassV,
table.FormClassV {border: 1px solid blue;}
td.FormClassH,
table.FormClassH {border: 1px solid orange;}
td.FormClassC,
table.FormClassC {border: 1px solid green;}

td.FormClassV,
td.FormClassH,
td.FormClassC {border-style: dashed; margin: 0px; padding: 6px; vertical-align: top;}

td.FormClassC {padding: 6px 30px;}

td.FormClassC span.Rubric {font-weight: bold;}

#tblWarn1 {background: #c0ffc0;}
table.TblWarn2 {background: #c0c0ff;}
';
if( !$border )
   $page_style.='
table.FormClass,
td.FormClassV,
table.FormClassV,
td.FormClassH,
table.FormClassH,
td.FormClassC,
table.FormClassC {border-width: 0px;}
';
if( $align > 0 )
{ //right align
   $page_style.='
table.FormClass,
table.FormClassV,
table.FormClassH,
table.FormClassC {margin: 0px 0px 0px auto;}

td.FormClassV,
td.FormClassH,
td.FormClassC {text-align: right;}
';
}
else if( $align < 0 )
{ //left align
   $page_style.='
table.FormClass,
table.FormClassV,
table.FormClassH,
table.FormClassC {margin: 0px auto 0px 0px;}

td.FormClassV,
td.FormClassH,
td.FormClassC {text-align: left;}
';
}
else
{ //center align
   $page_style.='
table.FormClass,
table.FormClassV,
table.FormClassH,
table.FormClassC {margin: 0px auto 0px auto;}

td.FormClassV,
td.FormClassH,
td.FormClassC {text-align: center;}
';
}

loc_start_html( $page_style);



/* control-form to select layout for area-layouted form */

# select layout
$actform = new Form( "myname", "form_example2.php", FORM_POST );
$actform->add_row( array(
      'DESCRIPTION',  'Layout',
      'SELECTBOX',    'layout', 1, $form_layouts, $layout, false,
      'SUBMITBUTTON', 'action', 'Choose Layout',
      'RADIOBUTTONS', 'align', array(-1=>'left', 0=>'center', 1=>'right'), (int)$align,
      'CHECKBOX',     'border', '1', 'table-borders', (bool)$border,
      'CHECKBOX',     'title', '1', 'table-titles', (bool)$title,
      ));
$actform->echo_string();



/* build area-layouted form */

$the_form = new Form( "myname", "form_example2.php", FORM_POST );

// set layout
if ( $layout )
   $the_form->set_layout( FLAYOUT_GLOBAL, $arr_layouts[$layout]['layout'] );

// set config for layout
/*
$the_form->set_layout( FLAYOUT_AREACONF, FAREA_ALL,
   array(
      FAC_TABLE    => ($border) ? 'border=1' : '',
      FAC_ENVTABLE => ($center) ? 'align=center' : '',
   ) );
*/

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
if( $title )
$the_form->set_layout( FLAYOUT_AREACONF, 1,
   array(
      'title' => 'Title 1',
   ) );

if ( $layout ) $the_form->set_area( 2 );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 2') ) );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 3') ) );
$the_form->add_row( array( 'TEXT', ff($col2, 'Area 2 - Row 4') ) );
$the_form->set_layout( FLAYOUT_AREACONF, 2,
   array(
      'title' => ( $title ?'Title 2' :'' ),
      FAC_TABLE => 'id=tblWarn1',
   ) );

if ( $layout ) $the_form->set_area( 3 );
$the_form->add_row( array( 'TEXT', ff($col3, 'Area 3 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col3, 'Area 3 - Row 2') ) );
$the_form->add_row( array( 'TEXT', ff($col3, 'Area 3 - Row 3') ) );
$the_form->set_layout( FLAYOUT_AREACONF, 3,
   array(
      ( $title ?'title' :'dummy' )=> 'Title 3',
      FAC_TABLE => 'class=TblWarn2',
   ) );

if ( $layout ) $the_form->set_area( 4 );
$the_form->add_row( array( 'TEXT', ff($col4, 'Area 4 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col4, 'Area 4 - Row 2') ) );
$the_form->set_layout( FLAYOUT_AREACONF, 4,
   array(
      'title' => ( $title ?'Title 4' :'' ),
      FAC_TABLE => 'bgcolor="#ffc0c0"',
   ) );

if ( $layout ) $the_form->set_area( 5 );
$the_form->add_row( array( 'TEXT', ff($col5, 'Area 5 - Row 1') ) );
$the_form->add_row( array( 'TEXT', ff($col5, 'Area 5 - Row 2') ) );
$the_form->add_row( array( 'TEXT', ff($col5, 'Area 5 - Row 3') ) );
if( $title )
$the_form->set_layout( FLAYOUT_AREACONF, 5,
   array(
      'title' => 'Title 5',
   ) );

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

loc_end_html();
exit;

//-----------

function loc_start_html( $style_string='')
{
   ob_start("ob_gzhandler");

   $encoding_used = 'utf-8';

   header('Content-Type: text/html;charset='.$encoding_used);

   echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"'
         .' "http://www.w3.org/TR/html4/loose.dtd">';

   echo "\n<HTML>\n<HEAD>";

   if( $style_string )
      echo "\n <STYLE TYPE=\"text/css\">\n" .$style_string . "\n </STYLE>";

   echo "\n</HEAD>\n<BODY>\n";
}

function loc_end_html()
{
   echo "\n</BODY>\n</HTML>";
   ob_end_flush();
}

?>

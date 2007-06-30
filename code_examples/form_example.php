<?php

/*
 * Code example with simple form using Form-class.
 *
 * Usage: open its URL in browser
 */

chdir("../");
require_once("include/form_functions.php");
chdir("code_examples/");

echo "<html><body>\n";

$the_form = new Form( "myname", "myactionpage.php", FORM_POST );
$the_form->add_row( array( 'DESCRIPTION', 'Description',
                           'TEXTINPUT', 'descr', 40, 80, "default description" ) );
$the_form->add_row( array( 'DESCRIPTION', 'Checkboxes',
                           'CHECKBOX', 'box1', 1, 'box1', true,
                            'CHECKBOX', 'box2', 1, 'box2', false,
                            'CHECKBOX', 'box3', 1, 'box3', false,
                            'BR',
                            'CHECKBOX', 'box4', 1, 'box4', true,
                            'CHECKBOX', 'box5', 1, 'box5', false,
                            'CHECKBOX', 'box6', 1, 'box6', false,
                            'BR',
                            'CHECKBOX', 'box7', 1, 'box7', true,
                            'CHECKBOX', 'box8', 1, 'box8', false,
                            'CHECKBOX', 'box9', 1, 'box9', true ) );
$the_form->add_row( array( 'SUBMITBUTTON', 'submit', 'Go Ahead' ) );
$the_form->echo_string();

echo "</body></html>\n";

?>

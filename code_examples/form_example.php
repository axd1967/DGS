<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
 * Code example with simple form using Form-class.
 *
 * Usage: open its URL in browser
 */

chdir("../");
require_once 'include/form_functions.php';
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

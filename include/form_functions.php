<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

/* The code in this file is written by Ragnar Ouchterlony */

/*
 * --------------------------------------------------------------------------
 *                         form_functions.php
 * --------------------------------------------------------------------------
 *
 * This is for creating a standard sort of form.
 *
 *** Form function usage:
 *
 * Start with a form_start().
 * End with a form_end().
 * In between add a number of rows. Arguments to the different
 * form types is seen in their respective functions.
 *
 *** current types:
 *
 * * Description
 * * Textinput
 * * Textarea
 * * Selectbox
 * * Radiobuttons
 * * Checkbox
 * * Submitbutton 
 *
 *** Other things you could have in a row.
 *
 * * BR -- Forces a linebreak within the row.
 *
 *** Example:
 *
 * echo form_start( "myname", "myactionpage.php", "POST" );
 * echo form_insert_row( 'DESCRIPTION', 'Description',
 *                       'TEXTINPUT', 'descr', 40, 80, "default description" );
 * echo form_insert_row( 'DESCRIPTION', 'Checkboxes',
 *                       'CHECKBOX', 'box1', 1, 'box1', true,
 *                       'CHECKBOX', 'box2', 1, 'box2', false,
 *                       'CHECKBOX', 'box3', 1, 'box3', false,
 *                       'BR',
 *                       'CHECKBOX', 'box4', 1, 'box4', true,
 *                       'CHECKBOX', 'box5', 1, 'box5', false,
 *                       'CHECKBOX', 'box6', 1, 'box6', false,
 *                       'BR',
 *                       'CHECKBOX', 'box7', 1, 'box7', true,
 *                       'CHECKBOX', 'box8', 1, 'box8', false,
 *                       'CHECKBOX', 'box9', 1, 'box9', true,
 * echo form_insert_row( 'SUBMITBUTTON', 'Go Ahead' );
 * echo form_end();
 *
 *** Will look somewhat like this:
 *
 * Description: default_description_____________________
 *
 *              [X] box1  [ ] box2  [ ] box3
 *  Checkboxes: [X] box4  [ ] box5  [ ] box6
 *              [X] box7  [ ] box8  [X] box9
 *
 *                    [ Go Ahead ]
 *
 *** TODO
 *
 * * Add more types (if necessary).
 * * To be able to use the type functions separately
 *   (probably possible already but not tested).
 * * Breaks within radiobuttons.
 * * New columns.
 *
 */

/* If there is a form started yet (so we don't handle multiple forms at a time).*/
$form_started = false;
/* The maximum number of columns in a form. */
$max_nr_columns = 0;

/*
 * --------------------------------------------------------------------------
 *                             form_start
 * --------------------------------------------------------------------------
 *
 * This will start a standard form.
 *
 * $name          --- The name of the form, might be useful for scripting.
 * $action_page   --- The page to access when submitting.
 * $method        --- GET or POST (GET means in url and POST hidden).
 *
 */
function form_start( $name, $action_page, $method )
{
  global $form_started;
  $form_started = true;
  $result =
    "<FORM name=\"".$name."\" action=\"".$action_page."\" method=\"".$method."\">\n".
    "  <TABLE>\n";
  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                                form_end
 * --------------------------------------------------------------------------
 *
 * This will end a standard form.
 */
function form_end()
{
  global $form_started;
  $form_started = false;
  $result =
    "  </TABLE>\n".
    "</FORM>";
  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                          form_td_start
 * --------------------------------------------------------------------------
 * Internal function.
 */
function form_td_start( $alignment = 'left', $colspan = 1 )
{
  return "      <TD align=\"".$alignment."\" colspan=\"".$colspan."\">";
}

/*
 * --------------------------------------------------------------------------
 *                          form_td_end
 * --------------------------------------------------------------------------
 * Internal function.
 */
function form_td_end( $nospace = false )
{
  $result = "";
  if( !$nospace )
    $result .= "      ";
  return $result."</TD>\n";
}


/*
 * --------------------------------------------------------------------------
 *                        form_add_description
 * --------------------------------------------------------------------------
 */
function form_add_description( $description )
{
  return "      <TD align=\"right\">".$description.":</TD>\n";
}

/*
 * --------------------------------------------------------------------------
 *                        form_insert_text_input
 * --------------------------------------------------------------------------
 *
 * This will insert a text input box in a standard form.
 *
 * $name          --- the field name that will be used as the variable name
 *                    in the GET or POST.
 * $size          --- the size of the text input box.
 * $maxlength     --- How many characters it is allowed to enter.
 * $initial_value --- Text that appears initially in the input box.
 */
function form_insert_text_input( $name, $size, $maxlength, $initial_value )
{
  $result =
    "<INPUT type=\"text\" name=\"".$name."\" value=\"".$initial_value."\" ".
    "size=\"".$size."\" maxlength=\"".$maxlength."\">";
  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                          form_insert_textarea
 * --------------------------------------------------------------------------
 *
 * This will insert a text inputarea in a standard form.
 *
 * $name         --- the field name that will be used as the variable name
 *                   in the GET or POST.
 * $columns      --- the number of columns in the textarea.
 * $rows         --- the number of rows in the textarea.
 * $initial_text --- Text that appears initially in the textarea.
 */
function form_insert_textarea( $name, $columns, $rows, $initial_text )
{
  $result =
    "<TEXTAREA name=\"".$name."\" cols=\"".$columns."\" ".
    "rows=\"".$rows."\">".$initial_text."</TEXTAREA>";
  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                         form_insert_select_box
 * --------------------------------------------------------------------------
 *
 * This will insert a select box in a standard form.
 *
 * $name        --- the field name that will be used as the variable name
 *                  in the GET or POST.
 * $size        --- the size of the box.
 * $value_array --- The array defining all values and their descriptions.
 *                  the key will be used as value and the entry will
 *                  be used as description, i.e.:
 *                  'value' => 'description'.
 * $selected    --- If multiple this should be an array of all values
 *                  that should be selected from start else the value
 *                  of the selected value.
 * $multiple    --- If it should be possible to select more than one
 *                  value.
 */
function form_insert_select_box( $name, $size, $value_array, $selected, $multiple )
{
  $result =
    "        <SELECT name=\"".$name."\" size=\"".$size."\" ";
  if( $multiple )
    $result .= "multiple";
  $result .= ">\n";

  foreach( $value_array as $value => $info )
    {
      $result .= "          <OPTION value=\"".$value."\"";
      if( (! $multiple and $value == $selected) or
          ($multiple and array_key_exists($value,$selected)) )
        $result .= " selected";

      $result .= ">".$info."</OPTION>\n";
    }
  $result .=
    "        </SELECT>\n";

  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                       form_insert_radio_buttons
 * --------------------------------------------------------------------------
 *
 * This will insert some connected radio buttons in a standard form.
 *
 * $name        --- the field name that will be used as the variable name
 *                  in the GET or POST.
 * $size        --- the size of the box.
 * $value_array --- The array defining all values and their descriptions.
 *                  the key will be used as value and the entry will
 *                  be used as description, i.e.:
 *                  'value' => 'description'.
 * $selected    --- If multiple this should be an array of all values
 *                  that should be selected from start else the value
 *                  of the selected value.
 */
function form_insert_radio_buttons( $name, $size, $value_array, $selected )
{
  foreach( $value_array as $value => $info )
    {
      $result .= "        <INPUT type=\"radio\" name=\"".$name."\" value=\"".$value."\" ";
      if($value == $selected)
        $result .= " checked";

      $result .= ">".$info."\n";
    }

  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                       form_insert_checkbox
 * --------------------------------------------------------------------------
 *
 * This will insert some checkboxes.
 *
 * $name        --- the field name that will be used as the variable name
 *                  in the GET or POST.
 * $value       --- The value of the variable if checked.
 * $description --- A description of the checkbox.
 * $selected    --- True if checked at beginning.
 */
function form_insert_checkbox( $name, $value, $description, $selected )
{
  $result = "<INPUT type=\"checkbox\" name=\"".$name."\" value=\"".$value."\" ";
  if($selected)
    $result .= " checked";

  $result .= ">".$info;

  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                        form_insert_submit_button
 * --------------------------------------------------------------------------
 *
 * This will insert a text input box in a standard form.
 *
 * $text          --- the text on the submit button.
 */
function form_insert_submit_button( $text )
{
  $result = "<INPUT type=\"submit\" value=\"".$text."\">";
  return $result;
}

/*
 * --------------------------------------------------------------------------
 *                        form_insert_submit_button
 * --------------------------------------------------------------------------
 */
function form_insert_row()
{
  global $form_started, $max_nr_columns;
  if( !$form_started )
    return;

  $result = "";
  $current_arg = 0;
  $nr_columns = 0;
  $column_started = false;
  $result .= "    <TR>\n";

  while( $current_arg < func_num_args() )
    {
      switch( func_get_arg( $current_arg ) )
        {
        case 'DESCRIPTION':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 1 )
              {
                $description = func_get_arg( $current_arg );
                $current_arg++;

                if( $column_started )
                  $result .= form_td_end();

                $result .= form_td_start( 'right' ).$description.":".form_td_end( true );
                $nr_columns++;
                $column_started = false;
              }

          }
          break;

        case 'TEXTINPUT':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 4 )
              {
                $name = func_get_arg( $current_arg );
                $size = func_get_arg( $current_arg + 1 );
                $maxlength = func_get_arg( $current_arg + 2 );
                $initial_value = func_get_arg( $current_arg + 3);
                $textinput =
                  form_insert_text_input( $name, $size, $maxlength, $initial_value );
                $current_arg += 4;

                if( !$column_started )
                  {
                    $result .= form_td_start()."\n";
                    $column_started = true;
                    $nr_columns++;
                  }
                $result .= "        ".$textinput."\n";
              }
          }
          break;

        case 'TEXTAREA':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 4 )
              {
                $name = func_get_arg( $current_arg );
                $columns = func_get_arg( $current_arg + 1 );
                $rows = func_get_arg( $current_arg + 2 );
                $initial_text = func_get_arg( $current_arg + 3);
                $textarea = form_insert_textarea( $name, $columns, $rows, $initial_text );
                $current_arg += 4;

                if( !$column_started )
                  {
                    $result .= form_td_start()."\n";
                    $column_started = true;
                    $nr_columns++;
                  }
                $result .= "        ".$textarea."\n";
              }
          }
          break;

        case 'SELECTBOX':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 5 )
              {
                $name = func_get_arg( $current_arg );
                $size = func_get_arg( $current_arg + 1 );
                $value_array = func_get_arg( $current_arg + 2 );
                $selected = func_get_arg( $current_arg + 3 );
                $multiple = func_get_arg( $current_arg + 4 );
                $selectbox = form_insert_textarea( $name, $size, $value_array,
                                                   $selected, $multiple );
                $current_arg += 5;

                if( !$column_started )
                  {
                    $result .= form_td_start()."\n";
                    $column_started = true;
                    $nr_columns++;
                  }
                $result .= "        ".$selectbox."\n";
              }
          }
          break;

        case 'RADIOBUTTONS':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 4 )
              {
                $name = func_get_arg( $current_arg );
                $size = func_get_arg( $current_arg + 1 );
                $value_array = func_get_arg( $current_arg + 2 );
                $selected = func_get_arg( $current_arg + 3 );
                $radiobuttons = form_insert_radio_buttons( $name, $size,
                                                           $value_array, $selected );
                $current_arg += 4;
              }

            if( !$column_started )
              {
                $result .= form_td_start()."\n";
                $column_started = true;
                $nr_columns++;
              }
            $result .= $radiobuttons;
          }
          break;

        case 'CHECKBOX':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 4 )
              {
                $name = func_get_arg( $current_arg );
                $value = func_get_arg( $current_arg + 1 );
                $description = func_get_arg( $current_arg + 2 );
                $selected = func_get_arg( $current_arg + 3 );
                $checkbox = form_insert_checkbox( $name, $value,
                                                  $description, $selected );
                $current_arg += 4;

                if( !$column_started )
                  {
                    $result .= form_td_start()."\n";
                    $column_started = true;
                    $nr_columns++;
                  }
                $result .= "        ".$checkbox."\n";
              }
          }
          break;

        case 'SUBMITBUTTON':
          {
            $current_arg++;
            if( func_num_args() - $current_arg >= 1 )
              {
                $submit = form_insert_submit_button( func_get_arg( $current_arg ) );
                $current_arg++;

                if( $column_started )
                  $result .= form_td_end();

                $result .=
                  form_td_start( 'center', $max_nr_columns - $nr_columns ).
                  $submit.
                  form_td_end( true );
              }
          }
          break;

          /* Special commands */
        case 'BR':
          {
            $current_arg++;
            $result .= "        <BR>\n";
          }
          break;

        default:
          {
            $current_arg++;
          }
          break;
        }

      if( $nr_columns > $max_nr_columns )
        $max_nr_columns = $nr_columns;
    }

  if( $column_started )
    $result .= form_td_end();

  $result .= "    </TR>\n";
  return $result;
}

?>

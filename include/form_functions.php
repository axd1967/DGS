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
  * * Password
  * * Hidden
  * * Textarea
  * * Selectbox
  * * Radiobuttons
  * * Checkbox
  * * Submitbutton
  * * Text
  * * Header  --- Creates a header line.
  * * Ownhtml --- Does not produce td:s and such things, it will only add the code
  * *             specified by the user.
  *
  *** Other things you could have in a row.
  *
  * * SPACE     -- Makes some additional space, should be used on it's own row.
  * * BR        -- Forces a linebreak within the row.
  * * TD        -- Forces a column change.
  *
  *** Example:
  *
  * $the_form = new Form( "myname", "myactionpage.php", FORM_POST );
  * $the_form->add_row( array( 'DESCRIPTION', 'Description',
  *                            'TEXTINPUT', 'descr', 40, 80, "default description" ) );
  * $the_form->add_row( array( 'DESCRIPTION', 'Checkboxes',
  *                            'CHECKBOX', 'box1', 1, 'box1', true,
  *                            'CHECKBOX', 'box2', 1, 'box2', false,
  *                            'CHECKBOX', 'box3', 1, 'box3', false,
  *                            'BR',
  *                            'CHECKBOX', 'box4', 1, 'box4', true,
  *                            'CHECKBOX', 'box5', 1, 'box5', false,
  *                            'CHECKBOX', 'box6', 1, 'box6', false,
  *                            'BR',
  *                            'CHECKBOX', 'box7', 1, 'box7', true,
  *                            'CHECKBOX', 'box8', 1, 'box8', false,
  *                            'CHECKBOX', 'box9', 1, 'box9', true ) );
  * $the_form->add_row( array( 'SUBMITBUTTON', 'submit', 'Go Ahead' ) );
  * $the_form->echo_string();
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
  * * Change to class.
  * * Add more types (if necessary).
  * * To be able to use the type functions separately
  *   (probably possible already but not tested).
  * * Breaks within radiobuttons.
  *
  */

 define( "FORM_GET", 0 );
define( "FORM_POST", 1 );

class Form
{
  /* The form name. */
  var $name;
  /* The page to go to when submitting. */
  var $action;
  /* The method to send. should be FORM_GET or FORM_POST. */
  var $method;

  /* Internal variable to contain all the information on the rows of the form. */
  var $rows;

  /* Holds the cached form of the form string. */
  var $form_string;

  /* If $rows has been changed since last time we updated $form_string. */
  var $updated;

  /* The number that the lin number will be increased with with each new row. */
  var $line_no_step;

  /* Constructor. Initializes various variables. */
  function Form( $name, $action_page, $method )
    {
      $this->name = $name;
      $this->action = $action_page;
      $this->method = $method;

      $this->rows = array();

      $this->form_string = "";

      $this->updated = false;

      $this->line_no_step = 10;
    }

  /* Get $form_string and update it if necessary. */
  function get_form_string()
    {
      $this->update_form_string();
      return $this->form_string;
    }

  /* Echo $form_string */
  function echo_string()
    {
      echo $this->get_form_string();
    }

  /*
   * Add a new row to the form.
   * returns the line_number of the new row or -1 if failed.
   */
  function add_row( $row_array, $line_no = -1 )
    {
      if( $line_no != -1 )
        {
          if( array_key_exists( $line_no, $this->rows ) )
            return -1;
        }
      else
        {
          if( empty($this->rows) )
            {
              $line_no = $this->line_no_step;
            }
          else
            {
              $line_no = max( array_keys( $this->rows ) ) + $this->line_no_step;
            }
        }

      $this->rows[ $line_no ] = $row_array;
      $this->updated = false;

      return $line_no;
    }

  /* Set $line_no_step */
  function set_line_no_step( $step_size )
    {
      $this->line_no_step = $step_size;
    }

  /* Update $form_string with new $rows data. */
  function update_form_string()
    {
      if( $this->updated )
        return;

      $this->form_string = $this->create_form_string();
      $this->updated = true;
    }

  /* Create a string from the rows */
  function create_form_string()
    {
      global $h3_color;

      $result = "";
      $max_nr_columns = 2;

      $result .= $this->print_start( $this->name, $this->action, $this->method );

      ksort($this->rows);
      foreach( $this->rows as $row_args )
        {
          $args = array_values( $row_args );

          $current_arg = 0;
          $nr_columns = 0;
          $column_started = false;
          $result .= "    <TR>\n";

          while( $current_arg < count($args) )
            {
              switch( $args[ $current_arg ] )
                {
                case 'DESCRIPTION':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 1 )
                      {
                        $description = $args[ $current_arg ];
                        $current_arg++;

                        if( $column_started )
                          $result .= $this->print_td_end();

                        $result .= $this->print_td_start( 'right' ) . $description .
                          ":" . $this->print_td_end( true );
                        $nr_columns++;
                        $column_started = false;
                      }

                  }
                  break;

                case 'OWNHTML':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 1 )
                      {
                        $ownhtml = $args[ $current_arg ];
                        $current_arg++;

                        $result .= $ownhtml . "\n";
                      }
                  }

                case 'HEADER':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 1 )
                      {
                        $description = $args[ $current_arg ];
                        $current_arg++;

                        if( $nr_columns == 0  and
                            $current_arg >= count($args) )
                          {
                            if( $column_started )
                              $result .= $this->print_td_end();

                            $result .=
                              $this->print_td_start( 'center', 
                                                     max( $max_nr_columns - $nr_columns, 1 ) ) .
                              "<B><h3><font color=$h3_color>".$description.":" .
                              "</font></h3></B>" .
                              $this->print_td_end( true );

                            $nr_columns++;
                            $column_started = false;
                          }
                        elseif( $nr_columns > 0 or $current_arg < count($args) )
                          {
                            if( !$column_started )
                              {
                                $result .= $this->print_td_start()."\n";
                                $column_started = true;
                                $nr_columns++;
                              }
                            $result .= "        " .
                              "<B><h3><font color=$h3_color>".$description.":" .
                              "</font></h3></B>\n";
                          }
                      }

                  }
                  break;

                case 'TEXT':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 1 )
                      {
                        $description = $args[ $current_arg ];
                        $current_arg++;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
                            $column_started = true;
                            $nr_columns++;
                          }
                        $result .= "        ".$description."\n";
                      }
                  }
                  break;

                case 'TEXTINPUT':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 4 )
                      {
                        $name = $args[ $current_arg ];
                        $size = $args[ $current_arg + 1 ];
                        $maxlength = $args[ $current_arg + 2 ];
                        $initial_value = $args[ $current_arg + 3];
                        $textinput =
                          $this->print_insert_text_input( $name, $size, $maxlength, $initial_value );
                        $current_arg += 4;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
                            $column_started = true;
                            $nr_columns++;
                          }
                        $result .= "        ".$textinput."\n";
                      }
                  }
                  break;

                case 'PASSWORD':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 3 )
                      {
                        $name = $args[ $current_arg ];
                        $size = $args[ $current_arg + 1 ];
                        $maxlength = $args[ $current_arg + 2 ];
                        $password_input =
                          $this->print_insert_password_input( $name, $size, $maxlength );
                        $current_arg += 3;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
                            $column_started = true;
                            $nr_columns++;
                          }
                        $result .= "        ".$password_input."\n";
                      }
                  }
                  break;

                case 'HIDDEN':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 2 )
                      {
                        $name = $args[ $current_arg ];
                        $value = $args[ $current_arg + 1 ];
                        $hidden_input =
                          $this->print_insert_hidden_input( $name, $value );
                        $current_arg += 2;

                        $result .= "        ".$hidden_input."\n";
                      }
                  }
                  break;

                case 'TEXTAREA':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 4 )
                      {
                        $name = $args[ $current_arg ];
                        $columns = $args[ $current_arg + 1 ];
                        $rows = $args[ $current_arg + 2 ];
                        $initial_text = $args[ $current_arg + 3 ];
                        $textarea = $this->print_insert_textarea( $name, $columns,
                                                                  $rows, $initial_text );
                        $current_arg += 4;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
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
                    if( count($args) - $current_arg >= 5 )
                      {
                        $name = $args[ $current_arg ];
                        $size = $args[ $current_arg + 1 ];
                        $value_array = $args[ $current_arg + 2 ];
                        $selected = $args[ $current_arg + 3 ];
                        $multiple = $args[ $current_arg + 4 ];
                        $selectbox = $this->print_insert_select_box( $name, $size, $value_array,
                                                                     $selected, $multiple );
                        $current_arg += 5;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
                            $column_started = true;
                            $nr_columns++;
                          }
                        $result .= $selectbox;
                      }
                  }
                  break;

                case 'RADIOBUTTONS':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 3 )
                      {
                        $name = $args[ $current_arg ];
                        $value_array = $args[ $current_arg + 1 ];
                        $selected = $args[ $current_arg + 2 ];
                        $radiobuttons = $this->print_insert_radio_buttons( $name,
                                                                           $value_array,
                                                                           $selected );
                        $current_arg += 3;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
                            $column_started = true;
                            $nr_columns++;
                          }
                        $result .= $radiobuttons;
                      }
                  }
                  break;

                case 'CHECKBOX':
                  {
                    $current_arg++;
                    if( count($args) - $current_arg >= 4 )
                      {
                        $name = $args[ $current_arg ];
                        $value = $args[ $current_arg + 1 ];
                        $description = $args[ $current_arg + 2 ];
                        $selected = $args[ $current_arg + 3 ];
                        $checkbox = $this->print_insert_checkbox( $name, $value,
                                                                  $description, $selected );
                        $current_arg += 4;

                        if( !$column_started )
                          {
                            $result .= $this->print_td_start()."\n";
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
                    if( count($args) - $current_arg >= 2 )
                      {
                        $name = $args[ $current_arg ];
                        $text = $args[ $current_arg + 1 ];
                        $submit = $this->print_insert_submit_button( $name, $text );
                        $current_arg += 2;

                        if( $nr_columns == 0  and
                            $current_arg >= count($args) )
                          {
                            if( $column_started )
                              $result .= $this->print_td_end();

                            $result .=
                              $this->print_td_start( 'center', max( $max_nr_columns -
                                                                    $nr_columns,
                                                                    1 ) ) .
                              $submit .
                              $this->print_td_end( true );
                            $nr_columns++;
                            $column_started = false;
                          }
                        elseif( $nr_columns > 0 or $current_arg < count($args) )
                          {
                            if( !$column_started )
                              {
                                $result .= $this->print_td_start()."\n";
                                $column_started = true;
                                $nr_columns++;
                              }
                            $result .= "        ".$submit."\n";
                          }
                      }
                  }
                  break;

                  /* Special commands */
                case 'SPACE':
                  {
                    $current_arg++;
                    $result .= "      <td height=\"20px\">&nbsp;</td>\n";
                    $column_started = false;
                  }
                  break;

                case 'BR':
                  {
                    $current_arg++;
                    $result .= "        <BR>\n";
                  }
                  break;

                case 'TD':
                  {
                    $current_arg++;
                    if( $column_started )
                      $result .= $this->print_td_end();
                    $column_started = false;
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
            $result .= $this->print_td_end();

          $result .= "    </TR>\n";

        }

      $result .= $this->print_end();

      return $result;
    }

  /*
   * --------------------------------------------------------------------------
   *                             print_start
   * --------------------------------------------------------------------------
   * This will start a standard form.
   *
   * $name          --- The name of the form, might be useful for scripting.
   * $action_page   --- The page to access when submitting.
   * $method        --- FORM_GET or FORM_POST (GET means in url and POST hidden).
   */
  function print_start( $name, $action_page, $method )
    {
      assert( $method == FORM_GET or $method == FORM_POST );
      $pg_arr = array( FORM_GET => "GET", FORM_POST => "POST" );

      return "<FORM name=\"$name\" action=\"$action_page\" method=\"" .
        $pg_arr[$method] . "\">\n" .
        "  <TABLE>\n";
    }

  /*
   * --------------------------------------------------------------------------
   *                              print_end
   * --------------------------------------------------------------------------
   * This will end a standard form.
   */
  function print_end()
    {
      return "  </TABLE>\n</FORM>\n";
    }

  /*
   * --------------------------------------------------------------------------
   *                          print_td_start
   * --------------------------------------------------------------------------
   * Internal function.
   */
  function print_td_start( $alignment = 'left', $colspan = 1 )
    {
      return "      <TD align=\"$alignment\" colspan=\"$colspan\">";
    }

  /*
   * --------------------------------------------------------------------------
   *                          print_td_end
   * --------------------------------------------------------------------------
   * Internal function.
   */
  function print_td_end( $nospace = false )
    {
      return ( $nospace ? "</TD>\n" : "      </TD>\n" );
    }

  /*
   * --------------------------------------------------------------------------
   *                        print_description
   * --------------------------------------------------------------------------
   */
  function print_description( $description )
    {
      return "      <TD align=\"right\">$description:</TD>\n";
    }

  /*
   * --------------------------------------------------------------------------
   *                        print_insert_text_input
   * --------------------------------------------------------------------------
   * This will insert a text input box in a standard form.
   *
   * $name          --- the field name that will be used as the variable name
   *                    in the GET or POST.
   * $size          --- the size of the text input box.
   * $maxlength     --- How many characters it is allowed to enter.
   * $initial_value --- Text that appears initially in the input box.
   */
  function print_insert_text_input( $name, $size, $maxlength, $initial_value )
    {
      return "<INPUT type=\"text\" name=\"$name\" value=\"$initial_value\"" .
        " size=\"$size\" maxlength=\"$maxlength\">";
    }

  /*
   * --------------------------------------------------------------------------
   *                        print_insert_password_input
   * --------------------------------------------------------------------------
   * This will insert a text input box for entering passwords in a standard form.
   * Note that there is no initial value on purpose since that would be
   * a security risk to send clear passwords to the user.
   *
   * $name          --- the field name that will be used as the variable name
   *                    in the GET or POST.
   * $size          --- the size of the text input box.
   * $maxlength     --- How many characters it is allowed to enter.
   */
  function print_insert_password_input( $name, $size, $maxlength )
    {
      return "<INPUT type=\"password\" name=\"$name\"" .
        " size=\"$size\" maxlength=\"$maxlength\">";
    }

  /*
   * --------------------------------------------------------------------------
   *                        print_insert_hidden_input
   * --------------------------------------------------------------------------
   * This will just insert a value to the form, invisible to the user.
   *
   * $name    --- the field name that will be used as the variable name
   *                    in the GET or POST.
   * $value   --- The value of the hidden variable.
   */
  function print_insert_hidden_input( $name, $value )
    {
      return "<INPUT type=\"hidden\" name=\"$name\" value=\"$value\">";
    }

  /*
   * --------------------------------------------------------------------------
   *                          print_insert_textarea
   * --------------------------------------------------------------------------
   * This will insert a text inputarea in a standard form.
   *
   * $name         --- the field name that will be used as the variable name
   *                   in the GET or POST.
   * $columns      --- the number of columns in the textarea.
   * $rows         --- the number of rows in the textarea.
   * $initial_text --- Text that appears initially in the textarea.
   */
  function print_insert_textarea( $name, $columns, $rows, $initial_text )
    {
      return "<TEXTAREA name=\"$name\" cols=\"$columns\" " .
        "rows=\"$rows\" wrap=\"virtual\">$initial_text</TEXTAREA>";
    }

  /*
   * --------------------------------------------------------------------------
   *                         print_insert_select_box
   * --------------------------------------------------------------------------
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
  function print_insert_select_box( $name, $size, $value_array, $selected, $multiple )
    {
      $result = "        <SELECT name=\"$name\" size=\"$size\" ";
      if( $multiple )
        $result .= "multiple";
      $result .= ">\n";

      foreach( $value_array as $value => $info )
        {
          $result .= "          <OPTION value=\"$value\"";
          if( (! $multiple and $value == $selected) or
              ($multiple and array_key_exists($value,$selected)) )
            $result .= " selected";

          $result .= ">".$info."</OPTION>\n";
        }
      $result .= "        </SELECT>\n";

      return $result;
    }

  /*
   * --------------------------------------------------------------------------
   *                       print_insert_radio_buttons
   * --------------------------------------------------------------------------
   * This will insert some connected radio buttons in a standard form.
   *
   * $name        --- the field name that will be used as the variable name
   *                  in the GET or POST.
   * $value_array --- The array defining all values and their descriptions.
   *                  the key will be used as value and the entry will
   *                  be used as description, i.e.:
   *                  'value' => 'description'.
   * $selected    --- If multiple this should be an array of all values
   *                  that should be selected from start else the value
   *                  of the selected value.
   */
  function print_insert_radio_buttons( $name, $value_array, $selected )
    {
      foreach( $value_array as $value => $info )
        {
          $result .= "        <INPUT type=\"radio\" name=\"$name\" value=\"$value\"";
          if($value == $selected)
            $result .= " checked";

          $result .= "> $info\n";
        }

      return $result;
    }

  /*
   * --------------------------------------------------------------------------
   *                       print_insert_checkbox
   * --------------------------------------------------------------------------
   * This will insert some checkboxes.
   *
   * $name        --- the field name that will be used as the variable name
   *                  in the GET or POST.
   * $value       --- The value of the variable if checked.
   * $description --- A description of the checkbox.
   * $selected    --- True if checked at beginning.
   */
  function print_insert_checkbox( $name, $value, $description, $selected )
    {
      $result = "<INPUT type=\"checkbox\" name=\"$name\" value=\"$value\"";
      if($selected)
        $result .= " checked";

      $result .= "> $description";

      return $result;
    }

  /*
   * --------------------------------------------------------------------------
   *                        print_insert_submit_button
   * --------------------------------------------------------------------------
   * This will insert a text input box in a standard form.
   *
   * $name        --- the field name that will be used as the variable name
   *                  in the GET or POST.
   * $text        --- the text on the submit button.
   */
  function print_insert_submit_button( $name, $text )
    {
      return "<INPUT type=\"submit\" name=\"$name\" value=\"$text\">";
    }

}

?>

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

 /*!
  * \file form_functions.php
  *
  * \brief Functions for creating a standard set of forms.
  */

 /*!
  * \class Form
  *
  * \brief Container and functions for creating standard sets of forms.
  *
  * <b>Usage</b>
  *
  * Start with a form_start().
  * End with a form_end().
  * In between add a number of rows. Arguments to the different
  * form types is seen in their respective functions.
  *
  * <b>Current types</b>
  *
  * <ul>
  * <li> Description
  * <li> Textinput
  * <li> Password
  * <li> Hidden
  * <li> Textarea
  * <li> Selectbox
  * <li> Radiobuttons
  * <li> Checkbox
  * <li> Submitbutton
  * <li> Text
  * <li> Header  --- Creates a header line.
  * <li> Ownhtml --- Does not produce td:s and such things, it will only add the code
  *                  specified by the user.
  * </ul>
  *
  * <b>Other things you could have in a row.</b>
  *
  * <ul>
  * <li> SPACE     -- Makes some additional space, should be used on it's own row.
  * <li> BR        -- Forces a linebreak within the row.
  * <li> TD        -- Forces a column change.
  * </ul>
  *
  * \todo Add more types (if necessary).
  * \todo To be able to use the type functions separately
  *       (probably possible already but not tested).
  * \todo Breaks within radiobuttons.
  *
  */

 /*!
  * \example form_example.php
  * Example of how to use the form functions.  Here is one textinput and
  * nine checkboxes added plus the submitbutton.
  *
  * <b>In a textbased browser, the example will look something like this:</b>
  *
  * <pre>
  * Description: default_description_____________________
  *
  *              [X] box1  [ ] box2  [ ] box3
  *  Checkboxes: [X] box4  [ ] box5  [ ] box6
  *              [X] box7  [ ] box8  [X] box9
  *
  *                    [ Go Ahead ]
  * </pre>
  */

define( "FORM_GET", 0 );
define( "FORM_POST", 1 );

class Form
{
   /*! \brief The form name. */
   var $name;
   /*! \brief The page to go to when submitting. */
   var $action;
   /*! \brief The method to send. should be FORM_GET or FORM_POST. */
   var $method;

   /*! \brief Internal variable to contain all the information on the rows of the form. */
   var $rows;

   /*! \brief Holds the cached form of the form string. */
   var $form_string;

   /*! \brief If $rows has been changed since last time we updated $form_string. */
   var $updated;

   /*! \brief The number that the lin number will be increased with with each new row. */
   var $line_no_step;

   /*! \brief Constructor. Initializes various variables. */
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

   /*! \brief Get $form_string and update it if necessary. */
   function get_form_string()
      {
         $this->update_form_string();
         return $this->form_string;
      }

   /*! \brief Echo $form_string */
   function echo_string()
      {
         echo $this->get_form_string();
      }

   /*!
    * \brief Add a new row to the form.
    *
    * \return The line_number of the new row or -1 if failed.
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

   /*!
    * \brief Add another form into this.
    *
    * \todo Needs to be done.
    */
   function add_form( $other_form, $line_no )
      {
      }

   /*! \brief Set $line_no_step */
   function set_line_no_step( $step_size )
      {
         $this->line_no_step = $step_size;
      }

   /*! \brief Update $form_string with new $rows data. */
   function update_form_string()
      {
         if( $this->updated )
            return;

         $this->form_string = $this->create_form_string();
         $this->updated = true;
      }

   /*! \brief Create a string from the rows */
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
                     break;

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

   /*!
    * \brief This will start a standard form.
    *
    * \param $name        The name of the form, might be useful for scripting.
    * \param $action_page The page to access when submitting.
    * \param $method      FORM_GET or FORM_POST (GET means in url and POST hidden).
    */
   function print_start( $name, $action_page, $method )
      {
         assert( $method == FORM_GET or $method == FORM_POST );
         $pg_arr = array( FORM_GET => "GET", FORM_POST => "POST" );

         return "<FORM name=\"$name\" action=\"$action_page\" method=\"" .
            $pg_arr[$method] . "\">\n" .
            "  <TABLE>\n";
      }

   /*!
    * \brief This will end a standard form.
    */
   function print_end()
      {
         return "  </TABLE>\n</FORM>\n";
      }

   /*!
    * \brief Prints out start of a table cell.
    *
    * \internal
    *
    * \param $alignment How the cell should be aligned.
    * \param $colspan   How many columns this table should span.
    */
   function print_td_start( $alignment = 'left', $colspan = 1 )
      {
         return "      <TD align=\"$alignment\" colspan=\"$colspan\">";
      }

   /*!
    * \brief Prints end start of a table cell.
    *
    * \internal
    *
    * \param $nospace True if it shouldn't print out extra space.
    */
   function print_td_end( $nospace = false )
      {
         return ( $nospace ? "</TD>\n" : "      </TD>\n" );
      }

   /*!
    * \brief Prints a description.
    * \param $description The description.
    */
   function print_description( $description )
      {
         return "      <TD align=\"right\">$description:</TD>\n";
      }

   /*!
    * \brief This will insert a text input box in a standard form.
    *
    * \param $name          The field name that will be used as the variable name
    *                       in the GET or POST.
    * \param $size          The size of the text input box.
    * \param $maxlength     How many characters it is allowed to enter.
    * \param $initial_value Text that appears initially in the input box.
    */
   function print_insert_text_input( $name, $size, $maxlength, $initial_value )
      {
         return "<INPUT type=\"text\" name=\"$name\" value=\"$initial_value\"" .
            " size=\"$size\" maxlength=\"$maxlength\">";
      }

   /*!
    * \brief This will insert a text input box for entering passwords in a standard form.
    *
    * \note There is no initial value on purpose since that would be a
    * security risk to send clear passwords to the user.
    *
    * \param $name      The field name that will be used as the variable name
    *                   in the GET or POST.
    * \param $size      The size of the text input box.
    * \param $maxlength How many characters it is allowed to enter.
    */
   function print_insert_password_input( $name, $size, $maxlength )
      {
         return "<INPUT type=\"password\" name=\"$name\"" .
            " size=\"$size\" maxlength=\"$maxlength\">";
      }

   /*!
    * \brief This will just insert a value to the form, invisible to the user.
    *
    * \param $name  The field name that will be used as the variable name
    *               in the GET or POST.
    * \param $value The value of the hidden variable.
    */
   function print_insert_hidden_input( $name, $value )
      {
         return "<INPUT type=\"hidden\" name=\"$name\" value=\"$value\">";
      }

   /*!
    * \brief This will insert a text inputarea in a standard form.
    *
    * \param $name         The field name that will be used as the variable name
    *                      in the GET or POST.
    * \param $columns      The number of columns in the textarea.
    * \param $rows         The number of rows in the textarea.
    * \param $initial_text Text that appears initially in the textarea.
    */
   function print_insert_textarea( $name, $columns, $rows, $initial_text )
      {
         return "<TEXTAREA name=\"$name\" cols=\"$columns\" " .
            "rows=\"$rows\" wrap=\"virtual\">$initial_text</TEXTAREA>";
      }

   /*!
    * \brief This will insert a select box in a standard form.
    *
    * \param $name        The field name that will be used as the variable name
    *                     in the GET or POST.
    * \param $size        the size of the box.
    * \param $value_array The array defining all values and their descriptions.
    *                     the key will be used as value and the entry will
    *                     be used as description, i.e.:
    *                     'value' => 'description'.
    * \param $selected    If multiple this should be an array of all values
    *                     that should be selected from start else the value
    *                     of the selected value.
    * \param $multiple    If it should be possible to select more than one
    *                     value.
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

   /*!
    * \brief This will insert some connected radio buttons in a standard form.
    *
    * \param $name        The field name that will be used as the variable name
    *                     in the GET or POST.
    * \param $value_array The array defining all values and their descriptions.
    *                     the key will be used as value and the entry will
    *                     be used as description, i.e.:
    *                     'value' => 'description'.
    * \param $selected    If multiple this should be an array of all values
    *                     that should be selected from start else the value
    *                     of the selected value.
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

   /*!
    * \brief This will insert some checkboxes.
    *
    * \param $name        The field name that will be used as the variable name
    *                     in the GET or POST.
    * \param $value       The value of the variable if checked.
    * \param $description A description of the checkbox.
    * \param $selected    True if checked at beginning.
    */
   function print_insert_checkbox( $name, $value, $description, $selected )
      {
         $result = "<INPUT type=\"checkbox\" name=\"$name\" value=\"$value\"";
         if($selected)
            $result .= " checked";

         $result .= "> $description";

         return $result;
      }

   /*!
    * \brief This will insert a text input box in a standard form.
    *
    * \param $name The field name that will be used as the variable name
    *              in the GET or POST.
    * \param $text The text on the submit button.
    */
   function print_insert_submit_button( $name, $text )
      {
         return "<INPUT type=\"submit\" name=\"$name\" value=\"$text\">";
      }

}

?>

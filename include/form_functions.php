<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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
  * <li> Chapter --- Creates a chapter line.
  * <li> Ownhtml --- Does not produce td:s and such things, it will only add the code
  *                  specified by the user.
  * </ul>
  *
  * <b>Other things you could have in a row.</b>
  *
  * <ul>
  * <li> SPACE     -- Vertical space, should be used on it's own row.
  * <li> HR        -- Vertical separator, should be used on it's own row.
  * <li> TAB       -- Horizontal space. Add an empty cell.
  * <li> BR        -- Forces a linebreak within the row.
  * <li> TD        -- Forces a column change (TD end).
  * <li> CELL      -- Forces a new cell start with colspan and attributs specified.
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

   /*! \brief A variable responsible for holding all different form elements. */
   var $form_elements;

   /*! \brief Echo the <from ...> element immediately. */
   var $echo_form_start_now;

   /*! \brief Construction variables. */
   var $column_started;
   var $nr_columns;
   var $tabindex;
   var $safe_text;

   /*! \brief Constructor. Initializes various variables. */
   function Form( $name, $action_page, $method, $echo_form_start_now=false )
      {
         $this->attached = array();
         $this->hiddens_echoed = false;
         $this->hiddens = array();
         $this->tabindex = 0;

         $this->name = $name;
         $this->action = $action_page;
         $this->method = $method;
         $this->echo_form_start_now = $echo_form_start_now;

         $this->rows = array();

         $this->form_string = "";

         $this->updated = false;

         $this->line_no_step = 10;

//'SpanAllColumns' cancel 'NewTD', 'StartTD' and 'EndTD'.
         $this->form_elements = array(
            'DESCRIPTION'  => array( 'NumArgs' => 1,
                                     'NewTD'   => true,
                                     'StartTD' => true,
                                     'EndTD'   => true,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'right' ),
            'OWNHTML'      => array( 'NumArgs' => 1,
                                     'NewTD'   => false,
                                     'StartTD' => false,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => '' ),
            'HEADER'       => array( 'NumArgs' => 1,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => true,
                                     'SpanAllColumns' => true,
                                     'Align'   => 'center' ),
            'CHAPTER'      => array( 'NumArgs' => 1,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => true,
                                     'SpanAllColumns' => true,
                                     'Align'   => 'left' ),
            'TEXT'         => array( 'NumArgs' => 1,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'TEXTINPUT'    => array( 'NumArgs' => 4,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'PASSWORD'     => array( 'NumArgs' => 3,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'HIDDEN'       => array( 'NumArgs' => 2,
                                     'NewTD'   => false,
                                     'StartTD' => false,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'TEXTAREA'     => array( 'NumArgs' => 4,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'SELECTBOX'    => array( 'NumArgs' => 5,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'RADIOBUTTONS' => array( 'NumArgs' => 3,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'CHECKBOX'     => array( 'NumArgs' => 4,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => 'left' ),
            'SUBMITBUTTON' => array( 'NumArgs' => 2,
                                     'NewTD'   => false,
                                     'StartTD' => true,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => true,
                                     'Align'   => 'center' ),
            'SPACE'        => array( 'NumArgs' => 0,
                                     'NewTD'   => false,
                                     'StartTD' => false,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => '' ),
            'HR'           => array( 'NumArgs' => 0,
                                     'NewTD'   => false,
                                     'StartTD' => false,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => true,
                                     'Align'   => '' ),
            'TAB'          => array( 'NumArgs' => 0,
                                     'NewTD'   => true,
                                     'StartTD' => true,
                                     'EndTD'   => true,
                                     'SpanAllColumns' => false,
                                     'Align'   => '' ),
            'BR'           => array( 'NumArgs' => 0,
                                     'NewTD'   => false,
                                     'StartTD' => false,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => '' ),
            'TD'           => array( 'NumArgs' => 0,
                                     'NewTD'   => false,
                                     'StartTD' => false,
                                     'EndTD'   => true,
                                     'SpanAllColumns' => false,
                                     'Align'   => '' ),
            'CELL'         => array( 'NumArgs' => 2,
                                     'NewTD'   => true,
                                     'StartTD' => false,
                                     'EndTD'   => false,
                                     'SpanAllColumns' => false,
                                     'Align'   => '' ),
         );

         if( $echo_form_start_now )
            echo $this->print_start( $this->name, $this->action, $this->method );

      }

   /*! \brief Get $form_string and update it if necessary. */
   function get_form_string( $tabindex=0 )
      {
         $this->tabindex= $tabindex;
         $this->update_form_string();
         return $this->form_string;
      }

   /*! \brief Echo $form_string */
   function echo_string( $tabindex=0 )
      {
         echo $this->get_form_string($tabindex);
      }

   /*!
    * \brief Add a new row to the form.
    *
    * \return The line_number of the new row or -1 if failed.
    */
   function add_row( $row_array, $line_no = -1, $safe = true )
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

         $this->rows[ $line_no ] = array($safe,$row_array);
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
         $formstr = "";
         $max_nr_columns = 99; //actually build on the fly, it is often inadequate for the top rows of the form

         if( !$this->echo_form_start_now )
            $formstr .= $this->print_start( $this->name, $this->action, $this->method );

         $formstr .= "  <TABLE border=0>\n"; //form table

         ksort($this->rows);

         foreach( $this->rows as $row_args )
            {
               list( $this->safe_text, $row_args ) = $row_args;
               $args = array_values( $row_args );

               $current_arg = 0;
               $this->nr_columns = 0;
               $this->column_started = false;

               $result = '';
               $element_counter = 0;

               while( $current_arg < count($args) )
               {
                  //40 allow 10*(TEXT,TD,TEXTAREA,TD) in the row
                  if( $element_counter >= 40 )
                     exit;

                  $element_name = $args[ $current_arg ];
                  $current_arg++;

                  if( !array_key_exists( $element_name, $this->form_elements ) )
                     continue;

                  $element_counter++;

                  $element_type = $this->form_elements[ $element_name ];

                  if( count($args) - $current_arg >= $element_type[ 'NumArgs' ] )
                  {
                     $element_args = array();

                     for( $i = 0; $i < $element_type[ 'NumArgs' ]; $i++ )
                        $element_args[] = $args[ $current_arg + $i ];

                     $func_name = "create_string_func_" . strtolower( $element_name );

                     $current_arg += $element_type[ 'NumArgs' ];

                     if( $element_name == 'HIDDEN' )
                     {
                        $this->$func_name( $result, $element_args );
                     }
                     else if( $element_type['SpanAllColumns'] )
                     {

                        if( !$this->column_started )
                        $result .= $this->print_td_start( $element_type['Align'],
                                                          max( $max_nr_columns -
                                                               $this->nr_columns,
                                                               1 ) )."\n";

                        $result .= "        ";
                        $this->$func_name( $result, $element_args );
                        $result .= "\n";

                        $this->nr_columns = $max_nr_columns;
                        $this->column_started = true;
                     }
                     else
                     {
                        if( $element_type['NewTD'] and $this->column_started )
                        {
                           $result .= $this->print_td_end();
                           $this->column_started = false;
                        }

                        if( $element_type['StartTD'] and !$this->column_started )
                        {
                           $result .= $this->print_td_start( $element_type['Align'] )."\n";
                           $this->column_started = true;
                           $this->nr_columns++;
                        }

                        $result .= "        ";
                        $this->$func_name( $result, $element_args );
                        $result .= "\n";

                        if( $element_type['EndTD'] and $this->column_started )
                        {
                           $result .= $this->print_td_end();
                           $this->column_started = false;
                        }
                     }
                  }

                  if( $this->nr_columns > $max_nr_columns )
                     $max_nr_columns = $this->nr_columns;
               }
               if( $this->column_started )
                  $result .= $this->print_td_end();

               if( $result )
                  $formstr .= "    <TR>\n$result\n    </TR>\n";
            }

         $formstr .= "  </TABLE>\n";

         if (!$this->hiddens_echoed)
            $formstr .= $this->echo_hiddens();

         $formstr .= $this->print_end();

         return $formstr;
       }

   /*!
    * \brief Function for making a description string in the standard form.
    * \internal
    */
   function create_string_func_description( &$result, $args )
      {
         $result .= $args[ 0 ] . ':';
      }

   /*!
    * \brief Function for making own html string in the standard form
    * \internal
    */
   function create_string_func_ownhtml( &$result, $args )
      {
         $result .= $args[0];
      }

   /*!
    * \brief Function for making header string in the standard form
    * \internal
    */
   function create_string_func_header( &$result, $args )
      {
         global $h3_color;
         $result .= "&nbsp;<h3><font color=$h3_color>" . $args[0] . ":" .
            "</font></h3>";
      }

   /*!
    * \brief Function for making chapter string in the standard form
    * \internal
    */
   function create_string_func_chapter( &$result, $args )
      {
         $result .= "<b>" . $args[0] . ":</b>";
      }

   /*!
    * \brief Function for making text string in the standard form
    * \internal
    */
   function create_string_func_text( &$result, $args )
      {
         $result .= $args[0];
      }

   /*!
    * \brief Function for making textinput string in the standard form
    * \internal
    */
   function create_string_func_textinput( &$result, $args )
      {
         $result .= $this->print_insert_text_input( $args[0], $args[1], $args[2], $args[3] );
      }

   /*!
    * \brief Function for making password string in the standard form
    * \internal
    */
   function create_string_func_password( &$result, $args )
      {
         $result .= $this->print_insert_password_input( $args[0], $args[1], $args[2] );
      }

   /*!
    * \brief Function for making hidden string in the standard form
    * \internal
    */
   function create_string_func_hidden( &$result, $args )
      {
         //$result .= $this->print_insert_hidden_input( $args[0], $args[1] );
         $this->add_hidden( $args[0], $args[1] );
      }

   /*!
    * \brief Function for making textarea string in the standard form
    * \internal
    */
   function create_string_func_textarea( &$result, $args )
      {
         $result .= $this->print_insert_textarea( $args[0], $args[1], $args[2], $args[3] );
      }

   /*!
    * \brief Function for making selectbox string in the standard form
    * \internal
    */
   function create_string_func_selectbox( &$result, $args )
      {
         $result .= $this->print_insert_select_box( $args[0], $args[1], $args[2],
                                                    $args[3], $args[4] );
      }

   /*!
    * \brief Function for making radiobuttons string in the standard form
    * \internal
    */
   function create_string_func_radiobuttons( &$result, $args )
      {
         $result .= $this->print_insert_radio_buttons( $args[0], $args[1], $args[2] );
      }

   /*!
    * \brief Function for making checkbox string in the standard form
    * \internal
    */
   function create_string_func_checkbox( &$result, $args )
      {
         $result .= $this->print_insert_checkbox( $args[0], $args[1], $args[2], $args[3] );
      }

   /*!
    * \brief Function for making submitbutton string in the standard form
    * \internal
    */
   function create_string_func_submitbutton( &$result, $args )
      {
         $result .= $this->print_insert_submit_button( $args[0], $args[1] );
      }

   /*!
    * \brief Function for making vertical space string in the standard form
    * \internal
    */
   function create_string_func_space( &$result, $args )
      {
         $result .= "<td colspan=99 height=20></td>";
         //$result .= "&nbsp;"; //if SPACE SpanAllColumns==true
      }

   /*!
    * \brief Function for making vertical separator string in the standard form
    * \internal
    */
   function create_string_func_hr( &$result, $args )
      {
         $result .= "<HR>";
      }

   /*!
    * \brief Function for making horizontal space string in the standard form
    * \internal
    */
   function create_string_func_tab( &$result, $args )
      {
      }

   /*!
    * \brief Function for making break line string in the standard form
    * \internal
    */
   function create_string_func_br( &$result, $args )
      {
         $result .= "<br>";
      }

   /*!
    * \brief Function for ending td string in the standard form
    * \internal
    */
   function create_string_func_td( &$result, $args )
      {
      }

   /*!
    * \brief Function for making new td string in the standard form
    * \internal
    */
   function create_string_func_cell( &$result, $args )
      {
         $colspan = $args[0]>1 ? $args[0] : 1 ;
         $attribs = trim($args[1]) ;
         $result .= "<TD" .
            ($attribs ? " $attribs" : '') .
            ($colspan>1 ? " colspan=\"$colspan\"" : '') .
            ">";
         $this->column_started = true;
         $this->nr_columns+= $colspan;
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

         return "\n<FORM name=\"$name\" action=\"$action_page\" method=\"" .
            $pg_arr[$method] . "\">\n";
      }

   /*!
    * \brief This will end a standard form.
    */
   function print_end()
      {
         return "</FORM>\n";
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
         return "      <TD" .
            ($alignment ? " align=\"$alignment\"" : '') .
            ($colspan > 1 ? " colspan=\"$colspan\"" : '') . ">";
      }

   /*!
    * \brief Prints out end of a table cell.
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
         if( $this->safe_text )
            $initial_value = textarea_safe($initial_value);
         return "<INPUT type=\"text\" name=\"$name\" value=\"$initial_value\"" .
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
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
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
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
         if( $this->safe_text )
            $initial_text = textarea_safe($initial_text);
         return "<TEXTAREA name=\"$name\" cols=\"$columns\"" .
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
            " rows=\"$rows\">$initial_text</TEXTAREA>";

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
         $result .= 
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
            ">\n";

         foreach( $value_array as $value => $info )
            {
               $result .= "          <OPTION value=\"$value\"";
               if( (! $multiple and $value == $selected) or
                   ($multiple and array_key_exists($value,$selected)) )
                  $result .= " selected";

               // Filter out HTML code
               $info = eregi_replace("<BR>"," ",$info); //allow 2 lines long headers
               $info = str_replace("<", "&lt;", $info);
               $info = str_replace(">", "&gt;", $info);

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
         $result = '';
         foreach( $value_array as $value => $info )
            {
               $result .= "        <INPUT type=\"radio\" name=\"$name\" value=\"$value\"";
               if($value == $selected)
                  $result .= " checked";

               $result .= 
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
                  "> $info\n";
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

         $result .= 
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
            "> $description";

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
         return "<INPUT type=\"submit\" name=\"$name\" value=\"$text\"" .
            ($this->tabindex ? " tabindex=\"".($this->tabindex++)."\"" : "") .
         ">";
      }

   /* ******************************************************************** */

   var $attached;
   var $hiddens;
   var $hiddens_echoed;

   /*!
    * \brief This will attach an object to the form. Then, the hiddens
    *        from the object are inserted when the form is closed
    *        via the object get_hiddens() function.
    *
    * \param &$table The object.
    */
   function attach_table( &$table)
   {
      //if (isset($table))
      array_push($this->attached, $table);
   }

   function add_hidden( $key, $val)
   {
      $this->hiddens[$key] = $val;
   }

   function echo_hiddens()
   {
      foreach ($this->attached as $attach)
      {
         $attach->get_hiddens( $this->hiddens);
      }
      $str = '';
      foreach ($this->hiddens as $key => $val)
      {
         $str.= "<input type=\"hidden\" name=\"$key\" value=\"$val\">\n";
      }
      $this->hiddens_echoed = true;
      return $str;
   }

}

?>
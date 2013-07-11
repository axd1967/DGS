<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Ragnar Ouchterlony

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

 /* The code in this file is written by Ragnar Ouchterlony */

$TranslateGroups[] = "Common";

require_once 'include/globals.php';
require_once 'include/std_functions.php';

 /*!
  * \file form_functions.php
  *
  * \brief Functions for creating a standard set of forms.
  */


 /*!
  * \example form_example.php
  *
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

/*!
  * \example form_example2.php
  *
  * Example of how to use the form functions with area-grouping layout.
  */


define('FORM_GET', 0);
define('FORM_POST', 1);

// form-layout-config
define('FLAYOUT_GLOBAL', 'global'); // global layout for whole form (default is to use none)
define('FLAYOUT_AREACONF', 'areaconf'); // area-config
define('FAREA_ALL', 'all'); // form-area get area-config for 'all' areas
define('FAC_TABLE', 'table'); // form-area-context for a TABLE
define('FAC_ENVTABLE', 'td_table'); // form-area-context for a TD before a group-TABLE

define('FAREA_ALLV', FAREA_ALL); // form-area get area-config for 'all' V-areas
define('FAREA_ALLH', FAREA_ALL); // form-area get area-config for 'all' H-areas

// form-element-config
define('FEC_TR_ATTR', 'tr_attr'); // additional attributes for <tr>-element
define('FEC_EXTERNAL_FORM', 'form_external_form'); // true, if form start/end externally printed
define('FEC_BLOCK_FORM', 'block_form'); // true, if print form-items in blocks, clearing rows after printing

// for overwriting form-element-attributes
define('FEA_NUMARGS',     'NumArgs');
define('FEA_NEWTD',       'NewTD');
define('FEA_ENDTD',       'EndTD');
define('FEA_STARTTD',     'StartTD');
define('FEA_SPANALLCOLS', 'SpanAllColumns');
define('FEA_ATTBS',       'Attbs');

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
  * <li> TextinputX     --- with attributes
  * <li> Password
  * <li> Hidden
  * <li> Textarea
  * <li> Selectbox
  * <li> SelectboxX
  * <li> Radiobuttons
  * <li> RadiobuttonsX
  * <li> Checkbox
  * <li> Checkboxx
  * <li> Submitbutton
  * <li> SubmitbuttonX
  * <li> File           --- File-Upload
  * <li> Text
  * <li> Header  --- Creates a header line.
  * <li> Chapter --- Creates a chapter line.
  * <li> Ownhtml --- Does not produce td:s and such things, it will only add the code
  *                  specified by the user.
  * <li> Filter  --- Include Filter-form-elements; args: SearchFilters, filter-id
  * <li> FilterError --- args: SearchFilters, filter-id, msg-prefix, msg-suffix, bool with_syntax
  * <li> FilterWarn  --- args: SearchFilters, filter-id, msg-prefix, msg-suffix, bool with_syntax
  * </ul>
  *
  * <b>Other things you could have in a row.</b>
  *
  * <ul>
  * <li> SPACE     -- Vertical space, should be used on it's own row. Or use add_empty_row().
  * <li> HR        -- Vertical separator, should be used on it's own row.
  * <li> TAB       -- Horizontal space. Add an empty cell.
  * <li> BR        -- Forces a linebreak within the row.
  * <li> TD        -- Forces a column change (TD end).
  * <li> CELL      -- Forces a new cell start with colspan and attributs specified.
  * <li> ROW       -- Force the class specified for the row.
  * </ul>
  */
class Form
{
   /*! \brief The form name (and ID prefix too). */
   private $name;
   /*! \brief The page to go to when submitting. */
   private $action;
   /*! \brief The method to send. should be FORM_GET or FORM_POST. */
   private $method;
   /*! \brief The form class, default 'FormClass'. */
   private $fclass;
   /*! \brief Additional form-attributes, default (). */
   private $form_attributes = array();
   /*! \brief Additional config for the form (with influence to layouting form-elements). */
   private $config = array();

   /*! \brief Internal variable to contain all the information on the rows of the form: rows[line] = ( safe, row-arr, area ). */
   private $rows = array();

   /*! \brief Layout for grouping areas, \see set_layout(): key => value */
   private $layout = array();
   private $orig_layout = null; // for debugging only
   /*! \brief Layout for specific areas: areas[area] = ( layout => ...) */
   private $areas = array( 1 => 1 );
   /*! \brief Current area (initial is 1). */
   private $area = 1;
   /*! \brief Config for group-layouting: ( area-num|0|FAREA_ALL => ( FAC_TABLE|... => attbs, )) */
   private $areaconf = array();

   /*! \brief Holds the cached form of the form string. */
   private $form_string = '';

   /*! \brief If $rows has been changed since last time we updated $form_string. */
   private $updated = false;

   /*! \brief The number that the lin number will be increased with with each new row. */
   private $line_no_step = 10;

   /*! \brief Echo the <form ...> element immediately. */
   private $echo_form_start_now;

   /*! \brief If set, the following input fields will have the 'disabled' attribut. */
   private $disabled;

   /*! \brief This handle the tabindex of input fields. Purely incremental. */
   private $tabindex = 0;

   /*! \brief Construction variables. */
   private $column_started;
   private $nr_columns;
   private $max_nr_columns = 2; //actually build on the fly, it is often inadequate for the top rows of the form
   /*!
    * \brief texts of the row are raw. They must be converted if used in HTML parts.
    * boolean, default is true.
    */
   private $make_texts_safe;

   private $attached = array();
   private $hiddens = array();
   private $hiddens_echoed = false;

   /*!
    * \brief A variable responsible for holding all different form elements.
    *    'SpanAllColumns' cancel 'NewTD', 'StartTD' and 'EndTD'.
    *    'Attbs' are the attributs of the <TD> if one is opened for the element
    */
   private $form_elements = array(
         'DESCRIPTION'  => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => true,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => true,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormDescription') ),
         'OWNHTML'      => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'HEADER'       => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => true,
                                  FEA_SPANALLCOLS => true,
                                  FEA_ATTBS   => array('class'=>'FormHeader') ),
         'CHAPTER'      => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => true,
                                  FEA_SPANALLCOLS => true,
                                  FEA_ATTBS   => array('class'=>'FormChapter') ),
         'TEXT'         => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormText') ),
         'TEXTINPUT'    => array( FEA_NUMARGS => 4,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormTextinput') ),
         'TEXTINPUTX'   => array( FEA_NUMARGS => 5,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormTextinput') ),
         'PASSWORD'     => array( FEA_NUMARGS => 4,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormPassword') ),
         'HIDDEN'       => array( FEA_NUMARGS => 2,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'ENABLE'       => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'TEXTAREA'     => array( FEA_NUMARGS => 4,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormTextarea') ),
         'SELECTBOX'    => array( FEA_NUMARGS => 5,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormSelectbox') ),
         'SELECTBOXX'   => array( FEA_NUMARGS => 6,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormSelectbox') ),
         'RADIOBUTTONS' => array( FEA_NUMARGS => 3,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormRadiobuttons') ),
         'RADIOBUTTONSX' => array( FEA_NUMARGS => 4,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormRadiobuttons') ),
         'CHECKBOX'     => array( FEA_NUMARGS => 4,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormCheckbox') ),
         'CHECKBOXX'    => array( FEA_NUMARGS => 5,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormCheckbox') ),
         'SUBMITBUTTON' => array( FEA_NUMARGS => 2,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => true,
                                  FEA_ATTBS   => array('class'=>'FormSubmitbutton') ),
         'SUBMITBUTTONX' => array( FEA_NUMARGS => 3,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => true,
                                  FEA_ATTBS   => array('class'=>'FormSubmitbutton') ),
         'FILE'         => array( FEA_NUMARGS => 5,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => true,
                                  FEA_ATTBS   => array('class'=>'FormFileUpload') ),
         'SPACE'        => array( FEA_NUMARGS => 0,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'HR'           => array( FEA_NUMARGS => 0,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => true,
                                  FEA_ATTBS   => '' ),
         'TAB'          => array( FEA_NUMARGS => 0,
                                  FEA_NEWTD   => true,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => true,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'BR'           => array( FEA_NUMARGS => 0,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'TD'           => array( FEA_NUMARGS => 0,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => true,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'CELL'         => array( FEA_NUMARGS => 2,
                                  FEA_NEWTD   => true,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'ROW'          => array( FEA_NUMARGS => 1,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => false,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => '' ),
         'FILTER'       => array( FEA_NUMARGS => 2,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormFilter') ),
         'FILTERERROR'  => array( FEA_NUMARGS => 5,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormFilterError') ),
         'FILTERWARN'   => array( FEA_NUMARGS => 5,
                                  FEA_NEWTD   => false,
                                  FEA_STARTTD => true,
                                  FEA_ENDTD   => false,
                                  FEA_SPANALLCOLS => false,
                                  FEA_ATTBS   => array('class'=>'FormFilterWarning') ),
      );


   /*! \brief Constructor. Initializes various variables. */
   public function __construct( $name, $action_page, $method, $echo_form_start_now=false, $class='FormClass' )
   {
      $this->name = $name;
      $this->action = $action_page;
      $this->method = $method;
      $this->fclass = $class;
      $this->echo_form_start_now = $echo_form_start_now;

      if ( $echo_form_start_now )
         echo $this->print_start_default();
   }//__construct


   /*! \brief Sets additional form-attributes. */
   public function set_form_attributes( $attributes )
   {
      assert( is_array($attributes) && !is_null($attributes) );
      $this->form_attributes = $attributes;
   }

   /*! \brief Adds additional form-attribute. */
   public function add_form_attribute( $key, $value )
   {
      $this->form_attributes[$key] = $value;
   }

   /*! \brief add additional form-config. */
   public function set_config( $name, $value )
   {
      $this->config[$name] = $value;
   }

   /*! \brief Returning additional non-null form-config, '' if unset. */
   public function get_config( $name )
   {
      return ( isset($this->config[$name]) ) ? $this->config[$name] : '';
   }

   public function set_max_nr_columns( $max_nr_columns )
   {
      $this->max_nr_columns = $max_nr_columns;
   }

   public function get_tabindex()
   {
      return $this->tabindex;
   }

   /*!
    * \brief Allows to overwrite (reconfigure) form-element-config (not for read-only)
    * \param $name     form-element name, e.g. DESCRIPTION
    * \param $attrname allowed attribute-names: FEA_STARTTD, FEA_NEWTD, FEA_ENDTD, FEA_SPANALLCOLS, FEA_ATTBS
    * \param $value    value for attribute
    */
   public function set_attr_form_element( $name, $attrname, $value )
   {
      // known attribute-names for form-elements and read-only-state
      static $ARR_FORMELEM_READONLY = array(
            FEA_NUMARGS     => 1,
            FEA_NEWTD       => 0,
            FEA_ENDTD       => 0,
            FEA_STARTTD     => 0,
            FEA_SPANALLCOLS => 0,
            FEA_ATTBS       => 0,
         );

      $rx_attrs = "/^(" . implode('|', array_keys($ARR_FORMELEM_READONLY) ) . ")$/";

      $name = strtoupper($name);
      if ( !preg_match( $rx_attrs, $attrname ) )
         error('internal_error', "Form.set_attr_form_element.bad_attr_name($name,$attrname)");
      if ( @$ARR_FORMELEM_READONLY[$attrname] )
         error('internal_error', "Form.set_attr_form_element.readonly_attr($name,$attrname)");
      if ( !isset($this->form_elements[$name]) )
         error('internal_error', "Form.set_attr_form_element.miss_form_element($name)");

      $this->form_elements[$name][$attrname] = $value;
   }//set_attr_form_element

   /*!
    * \brief Sets layout for areas
    * \param $key FLAYOUT_GLOBAL (expecting 1 arg: layout-syntax with num ',' '|' '(..)'; \see parse_layout_areaconf ) \n
    *             FLAYOUT_AREACONF (expecting 2 args: config-key, array)
    * \param $value direct value for chosen key
    * \param $arr additional array for configuration of layout (depends on $key)
    */
   public function set_layout( $key, $value, $arr=NULL )
   {
      if ( $key === FLAYOUT_GLOBAL )
         $this->parse_layout_global( $value );
      else if ( $key === FLAYOUT_AREACONF )
         $this->parse_layout_areaconf( $value, $arr );
      else
         error('internal_error', "Form.set_layout.unknown_key($key)");
   }//set_layout

   /*!
    * \brief Parses syntax for global-layout grouping areas.
    * Syntax is: num = area num (starting with 1..),
    *            ',' = vertical area-grouping,
    *            '|' = horizontal area-grouping,
    *            '(..)' = grouping because '(' has higher prio than '|' than ','
    * \internal
    */
   private function parse_layout_global( $layout ) // varargs: more args after value possible
   {
      $this->orig_layout = $layout;
      $this->layout = array();
      $this->areas  = array();

      // syntax-checks: allowed chars, non-empty braces
      if ( !preg_match( "/(^[\d,|\(\)]+$|\(\))/", $layout ) )
         error('internal_error', "Form.parse_layout_global.bad_syntax1($layout)");

      $groups = array();
      $grcnt = 0;
      $L = "($layout)";
      while ( true )
      {
         $epos = strpos( $L, ')' );
         if ( $epos === false )
            break;
         # found ')', search backwards to '(' -> group
         $spos = strrpos( substr($L, 0, $epos), '(' );
         if ( $spos === false )
            error('internal_error', "Form.parse_layout_global.bracing_mismatch1($layout)");

         $group = substr( $L, $spos + 1, $epos - $spos - 1 ); // no '()' in group, only x or x, or x|
         $arr = array();
         $arr_horiz = explode( ',', $group );
         foreach ( $arr_horiz as $hgr )
         {
            if ( strlen($hgr) == 0 )
               error('internal_error', "Form.parse_layout_global.missing_area-num.H($layout)"); // around a ','
            $arr_vert = explode( '|', $hgr );
            if ( count($arr_vert) == 1 )
            {
               if ( $hgr[0] == 'G' )
                  $hgr = $groups[ substr($hgr,1) ];
               elseif ( $hgr < 1 )
                  error('internal_error', "Form.parse_layout_global.area-num.H<1");
               else
                  $this->areas[$hgr] = 1;
               $arr[]= $hgr;
            }
            else
            {
               $arrv = array( 'H' );
               foreach ( $arr_vert as $vgr )
               {
                  if ( strlen($vgr) == 0 )
                     error('internal_error', "Form.parse_layout_global.missing_area-num.V($layout)"); // around a '|'
                  if ( $vgr[0] == 'G' )
                     $vgr = $groups[ substr($vgr,1) ];
                  elseif ( $vgr < 1 )
                     error('internal_error', "Form.parse_layout_global.area-num.V<1");
                  else
                     $this->areas[$vgr] = 1;
                  $arrv[]= $vgr;
               }
               $arr[]= $arrv;
            }
         }

         $groups[$grcnt] = $arr;
         $L = substr_replace( $L, "G$grcnt", $spos, $epos - $spos + 1);
         $grcnt++;
      }

      if ( !(strpos( $L, '(' ) === false) )
         error('internal_error', "Form.parse_layout_global.bracing_mismatch2($layout)");
      if ( preg_match( "/[\|,]/", $L ) )
         error('internal_error', "Form.parse_layout_global.bad_syntax2($layout)"); // with one of '|,'

      $result = $groups[$grcnt-1];
      while ( is_array($result) && count($result) == 1 )
         $result = array_shift($result);
      $this->layout[FLAYOUT_GLOBAL] = $result;
   }//parse_layout_global

   /*!
    * \brief Parses and sets area-config: area (=num|0|FAREA_ALL), config = array( context => ... )
    * \internal
    */
   private function parse_layout_areaconf( $area, $config )
   {
      if ( !preg_match( "/^(\d+|".FAREA_ALL.")$/", $area) )
         error('assert', "Form.parse_layout_areaconf.area-num($area)");
      if ( !is_array( $config ) )
         error('assert', "Form.parse_layout_areaconf.config-arg()");

      // don't overwrite existing config
      if ( !isset($this->areaconf[$area]) )
         $this->areaconf[$area] = array();
      foreach ( $config as $key => $val )
         $this->areaconf[$area][$key] = $val;
   }//parse_layout_areaconf


   /*! \brief Get $form_string and update it if necessary. */
   public function get_form_string( $tabindex=0 )
   {
      $this->tabindex= $tabindex;
      $this->update_form_string();
      return $this->form_string;
   }

   /*! \brief Echo $form_string */
   public function echo_string( $tabindex=0 )
   {
      echo $this->get_form_string($tabindex);
   }

   /*! \brief Changes current area (area-num must exist in specified layout \see set_layout ). */
   public function set_area( $area )
   {
      if ( !isset($this->layout[FLAYOUT_GLOBAL]) )
         error('assert', "Form.set_area.unset_layout");
      if ( !isset($this->areas[$area]) )
         error('assert', "Form.set_area.bad_area($area)");
      $this->area = $area;
   }//set_area

   /*!
    * \brief Add a new row to the form.
    *
    * \param $make_texts_safe if false, the texts of the row will be kept "as is"
    *    else they will be textarea_safe'ed
    *
    * \return The line_number of the new row or -1 if failed.
    */
   public function add_row( $row_array, $line_no = -1, $make_texts_safe = true )
   {
      if ( $line_no != -1 )
      {
         if ( array_key_exists( $line_no, $this->rows ) )
            return -1;
      }
      else
      {
         if ( empty($this->rows) )
            $line_no = $this->line_no_step;
         else
            $line_no = max( array_keys( $this->rows ) ) + $this->line_no_step;
      }

      $this->rows[ $line_no ] = array( $make_texts_safe, $row_array, $this->area );
      $this->updated = false;

      return $line_no;
   }//add_row

   /*! \brief wrapper to add empty-row. */
   public function add_empty_row()
   {
      //$this->add_row( array( 'TEXT', '&nbsp;' ));
      $this->add_row( array( 'SPACE' )); //better for colspan
   }

   /*! \brief Set $line_no_step */
   public function set_line_no_step( $step_size )
   {
      $this->line_no_step = $step_size;
   }

   /*! \brief Update $form_string with new $rows data. */
   private function update_form_string()
   {
      if ( $this->updated )
         return;

      $this->form_string = $this->create_form_string();
      $this->updated = true;
   }

   /*! \brief Create a string from the rows */
   public function create_form_string()
   {
      $has_layout = isset($this->layout[FLAYOUT_GLOBAL]);
      $rootformstr = "";

      if ( count($this->rows) <= 0 )
      {
         if ( !$this->get_config(FEC_EXTERNAL_FORM) && $this->echo_form_start_now )
            $rootformstr .= $this->print_end();
         return $rootformstr;
      }

      ksort($this->rows);

      // prepare area-grouping
      $area_rows = array();
      foreach ( $this->areas as $area => $tmp )
         $area_rows[$area] = '';

      foreach ( $this->rows as $row_args )
      {
         list( $tmp, $args, $curr_area ) = $row_args;
         $this->make_texts_safe = $tmp;
         $args = array_values( $args );
         $formstr = "";

         $current_arg = 0;
         $this->nr_columns = 0;
         $this->column_started = false;

         $rowclass = '';
         $result = '';
         $element_counter = 0;

         while ( $current_arg < count($args) )
         {
            //40 allow 10*(TEXT,TD,TEXTAREA,TD) in the row
            if ( $element_counter >= 40 )
               exit;

            $element_name = $args[ $current_arg ];
            $current_arg++;

            if ( !array_key_exists( $element_name, $this->form_elements ) )
               continue;

            $element_counter++;
            $element_type = $this->form_elements[ $element_name ];

            $num_args = $element_type[FEA_NUMARGS];
            if ( $current_arg + $num_args > count($args) )
               continue;

            $element_args = array();
            for ( $i = 0; $i < $num_args; $i++ )
               $element_args[] = $args[ $current_arg + $i ];

            $func_name = "create_string_func_" . strtolower( $element_name );

            $current_arg += $num_args;

            if ( $element_name == 'ROW' )
            {
               $rowclass = ' class='.$element_args[ 0 ];
            }
            else if ( $element_name == 'HIDDEN' || $element_name == 'ENABLE' )
            {
               $this->$func_name( $result, $element_args );
            }
            else if ( $element_type[FEA_SPANALLCOLS] )
            {

               if ( !$this->column_started )
               $result .= $this->print_td_start( $element_type[FEA_ATTBS],
                                                 max( $this->max_nr_columns -
                                                      $this->nr_columns,
                                                      1 ) );

               $this->$func_name( $result, $element_args );

               $this->nr_columns = $this->max_nr_columns;
               $this->column_started = true;
            }
            else
            {
               if ( $element_type[FEA_NEWTD] && $this->column_started )
               {
                  $result .= $this->print_td_end();
                  $this->column_started = false;
               }

               if ( $element_type[FEA_STARTTD] && !$this->column_started )
               {
                  $result .= $this->print_td_start( $element_type[FEA_ATTBS] );
                  $this->column_started = true;
                  $this->nr_columns++;
               }

               $this->$func_name( $result, $element_args );

               if ( $element_type[FEA_ENDTD] && $this->column_started )
               {
                  $result .= $this->print_td_end();
                  $this->column_started = false;
               }
            }
            if ( $this->nr_columns > $this->max_nr_columns )
               $this->max_nr_columns = $this->nr_columns;
         } //while args
         if ( $this->column_started )
            $result .= $this->print_td_end();

         if ( $result )
         {
            $tr_attrs = $this->get_config(FEC_TR_ATTR);
            $formstr .= '<TR';
            if ( $rowclass )
               $formstr .= $rowclass;
            else if ( $tr_attrs )
               $formstr .=  ' '.$tr_attrs;
            $formstr .= ">$result</TR>\n";
            $area_rows[$curr_area] .= $formstr;
         }
      }

      $is_block_form = $this->get_config(FEC_BLOCK_FORM);

      // start default-form here because above loop may add additional form-attributes
      if ( !$this->get_config(FEC_EXTERNAL_FORM) && !$this->echo_form_start_now && !$is_block_form )
         $rootformstr .= $this->print_start_default();

      $table_attbs = $this->get_areaconf( 0, FAC_TABLE );
      $table_attbs = $this->get_form_attbs( $table_attbs);
      $rootformstr .= "<TABLE $table_attbs>\n"; //form table

      // build area-groups
      if ( $has_layout )
         $rootformstr .= $this->build_areas( $this->layout[FLAYOUT_GLOBAL], $area_rows );
      else
         $rootformstr .= implode( "", $area_rows );

      $rootformstr .= "</TABLE>";

      if ( !$this->get_config(FEC_EXTERNAL_FORM) && !$is_block_form )
         $rootformstr .= $this->print_end();
      if ( $is_block_form )
         $this->rows = array();

      return $rootformstr;
   }//create_form_string

   /*!
    * \brief Return form pressed into areas grouped as specified by layout (called recursively).
    * \param $L layout-array: is-arr(vert|horiz) OR is-num(=area)
    * \param $AR AR[areanum] contains the tablerows as string for the different areas
    * \param $TRlevel if true, put area into table-tr-element
    * \internal
    */
   private function build_areas( &$L, &$AR, $TRlevel=true )
   {
      $areastr = '';

      if ( is_numeric($L) )
      {
         $areastr = @$AR[$L]; //always TRlevel
         if ( !$areastr )
            return '';
         $table_attbs = $this->get_areaconf( $L, FAC_TABLE );
         $tdtable_attbs = $this->get_areaconf( $L, FAC_ENVTABLE );
         $table_attbs = $this->get_form_attbs( $table_attbs, 'C');
         $tdtable_attbs = $this->get_form_attbs( $tdtable_attbs, 'C');
         $title = (string)@$this->get_areaconf( $L, 'title' );
         if ( $title )
            $title = "<span class=Rubric>$title</span>";
         $areastr = "<TD$tdtable_attbs><!-- Area #$L -->$title<TABLE$table_attbs>\n"
                  . $areastr
                  . "</TABLE></TD>\n";
         if ( $TRlevel )
            $areastr = "<TR>$areastr</TR>\n";
      }
      else
      {
         if ( !is_array($L) )
            error('assert', "Form.build_areas.bad-layout-type($L)");
         if ( count($L) == 0 )
            error('assert', "Form.build_areas.empty-layout-array");

         $table_attbs = $this->get_areaconf( FAREA_ALL, FAC_TABLE );
         $cnt = 0;
         if ( $L[0] == 'H' )
         { // horizontal grouping
            $cnt_L = count($L);
            for ( $i=1; $i < $cnt_L; $i++ )
            {
               $area = $L[$i];
               $str = $this->build_areas( $area, $AR, false);
               if ( !$str )
                  continue;
               $areastr.= $str;
               $cnt++;
            }
            if ( $cnt < 1 )
               return '';
            //if ( $cnt > 1 ) //removed because it can induce mis-alignment of siblings
            {
               $table_attbs = $this->get_areaconf( FAREA_ALLH, FAC_TABLE );
               $tdtable_attbs = $this->get_areaconf( FAREA_ALLH, FAC_ENVTABLE );
               $table_attbs = $this->get_form_attbs( $table_attbs, 'H');
               $tdtable_attbs = $this->get_form_attbs( $tdtable_attbs, 'H');
               $areastr = "<TD$tdtable_attbs><TABLE$table_attbs>\n"
                        . "<TR>$areastr</TR>\n"
                        . "</TABLE></TD>\n";
            }
            if ( $TRlevel )
               $areastr = "<TR>$areastr</TR>\n";
         }
         else
         { // vertical grouping
            foreach ( $L as $area )
            {
               $str = $this->build_areas( $area, $AR, false);
               if ( !$str )
                  continue;
               if ( $areastr )
                  $areastr .= "</TR>\n<TR>";
               $areastr .= $str;
               $cnt++;
            }
            if ( $cnt < 1 )
               return '';
            //if ( $cnt > 1 ) //removed because it can induce mis-alignment of siblings
            {
               $table_attbs = $this->get_areaconf( FAREA_ALLV, FAC_TABLE );
               $tdtable_attbs = $this->get_areaconf( FAREA_ALLV, FAC_ENVTABLE );
               $table_attbs = $this->get_form_attbs( $table_attbs, 'V');
               $tdtable_attbs = $this->get_form_attbs( $tdtable_attbs, 'V');
               $areastr = "<TD$tdtable_attbs><TABLE$table_attbs>\n<TR>"
                        . $areastr
                        . "</TR>\n</TABLE></TD>\n";
            }
            if ( $TRlevel )
               $areastr = "<TR>$areastr</TR>\n";
         }
      }

      return $areastr;
   }//build_areas

   /*!
    * \brief Returns area-config for specified area-num (num|0|FAREA_ALL) for context given.
    * \internal
    */
   private function get_areaconf( $area, $context )
   {
      if ( isset($this->areaconf[$area][$context]) )
         return $this->areaconf[$area][$context]; // special first
      if ( $area !== FAREA_ALL ) // all already checked
      {
         if ( isset($this->areaconf[FAREA_ALL][$context]) )
            return $this->areaconf[FAREA_ALL][$context]; // all
      }
      return '';
   }//get_areaconf

   /*!
    * \brief Function for making a description string in the standard form.
    * \internal
    */
   private function create_string_func_description( &$result, $args )
   {
      $result .= $args[ 0 ] . ':';
   }

   /*!
    * \brief Function for making own html string in the standard form
    * \internal
    */
   private function create_string_func_ownhtml( &$result, $args )
   {
      $result .= $args[0];
   }

   /*!
    * \brief Function for making header string in the standard form
    * \internal
    */
   private function create_string_func_header( &$result, $args )
   {
      $result .= "<h3 class=Header>" . $args[0] . ":</h3>";
   }

   /*!
    * \brief Function for making chapter string in the standard form
    * \internal
    */
   private function create_string_func_chapter( &$result, $args )
   {
      $result .= "<h4 class=Chapter>" . $args[0] . ":</h4>";
   }

   /*!
    * \brief Function for making text string in the standard form
    * \internal
    */
   private function create_string_func_text( &$result, $args )
   {
      $result .= $args[0];
   }

   /*!
    * \brief Function for making textinput string in the standard form
    * \internal
    */
   private function create_string_func_textinput( &$result, $args )
   {
      $result .= $this->print_insert_text_input( $args[0], $args[1], $args[2], $args[3], '' );
   }

   /*!
    * \brief Function for making textinput string in the standard form with additional attributes.
    * \internal
    */
   private function create_string_func_textinputx( &$result, $args )
   {
      $result .= $this->print_insert_text_input( $args[0], $args[1], $args[2], $args[3], $args[4] );
   }

   /*!
    * \brief Function for making password string in the standard form
    * \internal
    */
   private function create_string_func_password( &$result, $args )
   {
      $result .= $this->print_insert_password_input( $args[0], $args[1], $args[2], $args[3] );
   }

   /*!
    * \brief Function for making hidden string in the standard form
    * \internal
    */
   private function create_string_func_hidden( &$result, $args )
   {
      //hiddens are delayed to the end of form
      $this->add_hidden( $args[0], $args[1] );
   }

   /*!
    * \brief Function for enabling/disabling fields in the standard form
    * \internal
    */
   private function create_string_func_enable( &$result, $args )
   {
      $this->enable_input( $args[0] );
   }

   /*!
    * \brief Function for making textarea string in the standard form
    * \internal
    */
   private function create_string_func_textarea( &$result, $args )
   {
      $result .= $this->print_insert_textarea( $args[0], $args[1], $args[2], $args[3] );
   }

   /*!
    * \brief Function for making selectbox string in the standard form
    * \internal
    */
   private function create_string_func_selectbox( &$result, $args )
   {
      $result .= $this->print_insert_select_boxx( $args[0], $args[1], $args[2], $args[3], $args[4], '' );
   }

   /*!
    * \brief Function for making selectbox string extended with attributes in the standard form
    * \internal
    */
   private function create_string_func_selectboxx( &$result, $args )
   {
      $result .= $this->print_insert_select_boxx( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5] );
   }

   /*!
    * \brief Function for making radiobuttons string in the standard form
    * \internal
    */
   private function create_string_func_radiobuttons( &$result, $args )
   {
      $result .= $this->print_insert_radio_buttonsx( $args[0], $args[1], $args[2], '', true );
   }

   /*!
    * \brief Function for making radiobuttons string extended with attributes in the standard form
    * \internal
    */
   private function create_string_func_radiobuttonsx( &$result, $args )
   {
      $result .= $this->print_insert_radio_buttonsx( $args[0], $args[1], $args[2], $args[3], false );
   }

   /*!
    * \brief Function for making checkbox string in the standard form
    * \internal
    */
   private function create_string_func_checkbox( &$result, $args )
   {
      $result .= $this->print_insert_checkbox( $args[0], $args[1], $args[2], $args[3] );
   }

   /*!
    * \brief Function for making checkbox string extended with attributes in the standard form
    * \internal
    */
   private function create_string_func_checkboxx( &$result, $args )
   {
      $result .= $this->print_insert_checkbox( $args[0], $args[1], $args[2], $args[3], $args[4] );
   }

   /*!
    * \brief Function for making submitbutton string in the standard form
    * \internal
    */
   private function create_string_func_submitbutton( &$result, $args )
   {
      $result .= $this->print_insert_submit_button( $args[0], $args[1] );
   }

   /*!
    * \brief Function for making submitbuttonx string in the standard form
    * \internal
    */
   private function create_string_func_submitbuttonx( &$result, $args )
   {
      $result .= $this->print_insert_submit_buttonx( $args[0], $args[1], $args[2] );
   }

   /*!
    * \brief Function for making file-upload string in the standard form
    * \internal
    */
   private function create_string_func_file( &$result, $args )
   {
      $this->add_form_attribute( 'enctype', 'multipart/form-data' );
      $result .= $this->print_insert_file( $args[0], $args[1], $args[2], $args[3], $args[4] );
   }

   /*!
    * \brief Function for making vertical space string in the standard form
    * \internal
    */
   private function create_string_func_space( &$result, $args )
   {
      $result .= "<td colspan=" .$this->max_nr_columns . " height=\"20px\"></td>";
      //$result .= "&nbsp;"; //if SPACE SpanAllColumns==true
   }

   /*!
    * \brief Function for making vertical separator string in the standard form
    * \internal
    */
   private function create_string_func_hr( &$result, $args )
   {
      $result .= "<HR>";
   }

   /*!
    * \brief Function for making horizontal space string in the standard form
    * \internal
    */
   private function create_string_func_tab( &$result, $args )
   {
      //equal: $result .= "<td></td>";
   }

   /*!
    * \brief Function for making break line string in the standard form
    * \internal
    */
   private function create_string_func_br( &$result, $args )
   {
      $result .= "<br>\n";
   }

   /*!
    * \brief Function for ending td string in the standard form
    * \internal
    */
   private function create_string_func_td( &$result, $args )
   {
      // nothing to do
   }

   /*!
    * \brief Function for making new td string in the standard form
    * \internal
    */
   private function create_string_func_cell( &$result, $args )
   {
      $colspan = $args[0]>1 ? $args[0] : 1 ;
      $attribs = trim($args[1]) ;
      $result .= "<TD" .
         ($attribs ? " $attribs" : '') .
         ($colspan>1 ? " colspan=\"$colspan\"" : '') .
         ">";
      $this->column_started = true;
      $this->nr_columns+= $colspan;
   }//create_string_func_cell

   /*!
    * \brief Function for making new filter-element string in the standard form
    * \internal
    */
   private function create_string_func_filter( &$result, $args )
   {
      // args: SearchFilters, filter-id
      // note: filter-attr could be implemented with additional 'FILTERX'-element
      $result .= $this->print_insert_filter( $args[0], $args[1], array() );
   }

   /*!
    * \brief Function to include filter-error-string in the standard form if filter has error, return '' otherwise
    * \internal
    */
   private function create_string_func_filtererror( &$result, $args )
   {
      // args: SearchFilters, filter-id, prefix, suffix, with_syntax
      $result .= $this->print_insert_filtererror( $args[0], $args[1], $args[2], $args[3], $args[4] );
   }

   /*!
    * \brief Function to include filter-warn-string in the standard form if filter has warning, return '' otherwise
    * \internal
    */
   private function create_string_func_filterwarn( &$result, $args )
   {
      // args: SearchFilters, filter-id, prefix, suffix, with_syntax
      $result .= $this->print_insert_filterwarn( $args[0], $args[1], $args[2], $args[3], $args[4] );
   }



   /*!
    * \brief This will start a standard form.
    *
    * \param $name        The name of the form, might be useful for scripting.
    * \param $action_page The page to access when submitting.
    * \param $method      FORM_GET or FORM_POST (GET means in url and POST hidden).
    * \param $attributes  key-value array with additional form-attributes; or null
    * \param $class       CSS-class, default FormClass
    */
   public function print_start( $name, $action_page, $method, $attributes=null, $class='FormClass' )
   {
      assert( $method == FORM_GET || $method == FORM_POST );
      $pg_arr = array( FORM_GET => "GET", FORM_POST => "POST" );

      $attrib_str = '';
      if ( is_array($attributes) )
      {
         foreach ( $attributes as $key => $value )
            $attrib_str .= sprintf( ' %s="%s"', $key, $value );
      }

      return "\n<FORM id=\"{$name}Form\" name=\"$name\" class=\"$class\"" .
         " action=\"$action_page\" method=\"" . $pg_arr[$method] . "\"$attrib_str>";
   }//print_start

   public function print_start_default()
   {
      return $this->print_start( $this->name, $this->action, $this->method, $this->form_attributes, $this->fclass);
   }

   /*! \brief This will end a standard form. */
   public function print_end()
   {
      $formstr = '';
      if (!$this->hiddens_echoed)
         $formstr .= $this->get_hiddens_string();

      return $formstr."\n</FORM>";
   }//print_end


   /*!
    * \brief Prints out start of a table cell.
    *
    * \param $attbs    The attributs of the cell.
    * \param $colspan  How many columns this table should span.
    *
    * \internal
    */
   private function print_td_start( $attbs = 'class=left', $colspan = 1 )
   {
      $attbs= attb_merge( attb_parse($attbs), array('colspan'=>$colspan), false);
      return "<TD" . attb_build( $attbs) . ">";
   }

   /*!
    * \brief Prints out end of a table cell.
    * \internal
    */
   private function print_td_end()
   {
      return "</TD>";
   }

   /*!
    * \brief Prints a description.
    * \param $description The description.
    */
   public function print_description( $description )
   {
      //no safe-text needed here because it come from translators' strings
      return "<TD class=Description>$description:</TD>";
   }

   /*!
    * \brief This will insert a text input box in a standard form.
    *
    * \param $name          The field name that will be used as the variable name in the GET or POST.
    * \param $size          The size of the text input box.
    * \param $maxlength     How many characters it is allowed to enter; not set if <0.
    * \param $initial_value Text that appears initially in the input box.
    * \param $attbs         optional attributes
    */
   public function print_insert_text_input( $name, $size, $maxlength, $initial_value, $attbs='' )
   {
      if ( $this->make_texts_safe )
         $initial_value = textarea_safe($initial_value);

      $str = "<INPUT type=\"text\" name=\"$name\" value=\"$initial_value\""
         . $this->get_input_attbs() . " size=\"$size\"";
      if ( $maxlength >= 0 )
         $str .= " maxlength=\"$maxlength\"";
      $str .= self::parse_input_standard_attributes( $attbs, 'type|name|value|size|maxlength' );
      $str .= ">";
      return $str;
   }//print_insert_text_input

   /*!
    * \brief This will insert a text input box for entering passwords in a standard form.
    *
    * \note There is no initial value on purpose since that would be a
    * security risk to send clear passwords to the user.
    *
    * \param $name      The field name that will be used as the variable name in the GET or POST.
    * \param $size      The size of the text input box.
    * \param $maxlength How many characters it is allowed to enter.
    * \param $initial_value Text that appears initially in the input box.
    */
   public function print_insert_password_input( $name, $size, $maxlength, $initival_value )
   {
      return "<INPUT type=\"password\" name=\"$name\"" .
         $this->get_input_attbs() . " size=\"$size\" maxlength=\"$maxlength\" value=\"$initival_value\">";
   }

   /*!
    * \brief This will just insert a value to the form, invisible to the user.
    *
    * \param $name  The field name that will be used as the variable name in the GET or POST.
    * \param $value The value of the hidden variable.
    */
   public function print_insert_hidden_input( $name, $value )
   {
      return "<INPUT type=\"hidden\" name=\"$name\" value=\"$value\">";
   }

   /*!
    * \brief This will insert a text inputarea in a standard form.
    *
    * \param $name         The field name that will be used as the variable name in the GET or POST.
    * \param $columns      The number of columns in the textarea.
    * \param $rows         The number of rows in the textarea.
    * \param $initial_text Text that appears initially in the textarea.
    */
   public function print_insert_textarea( $name, $columns, $rows, $initial_text )
   {
      if ( $this->make_texts_safe )
         $initial_text = textarea_safe($initial_text);
      return "<TEXTAREA name=\"$name\" cols=\"$columns\"" .
         $this->get_input_attbs() . " rows=\"$rows\">$initial_text</TEXTAREA>";
   }

   public function print_insert_select_box( $name, $size, $value_array, $selected, $multiple=0 )
   {
      return $this->print_insert_select_boxx( $name, $size, $value_array, $selected, $multiple );
   }

   /*!
    * \brief This will insert a select box in a standard form.
    *
    * \param $name        The field name that will be used as the variable name in the GET or POST.
    * \param $size        the size of the box.
    * \param $value_array The array defining all values and their descriptions.
    *                     the key will be used as value and the entry will be used as description, i.e.:
    *                     'value' => 'description'.
    * \param $selected    If multiple this should be an array of all values that should be selected from start
    *                     else the value of the selected value.
    * \param $multiple    If it should be possible to select more than one
    *                     value. $name will be extended to "$name[]"
    */
   public function print_insert_select_boxx( $name, $size, $value_array, $selected, $multiple=0, $attbs='' )
   {
      $result = "<SELECT size=\"$size\" name=\"$name";
      //[] works for either GET or POST forms (else try %5b%5d ?)
      $result.= $multiple ? '[]" multiple' : '"';
      $result.= $this->get_input_attbs();
      $result .= self::parse_input_standard_attributes($attbs);
      $result .= ">\n";

      foreach ( $value_array as $value => $info )
      {
         $result .= "<OPTION value=\"$value\"";
         if ( ($multiple ? array_key_exists((string)$value,$selected)
                        : ((string)$value == $selected) ) )
            $result .= " selected";

         // Filter out HTML code
         $info = preg_replace("/\\s*<BR>\\s*/i",' ',$info); //allow 2 lines long headers
         $info = basic_safe($info); //basic_safe() because inside <option></option>
         $result .= ">".$info."</OPTION>\n";
      }
      $result .= "</SELECT>";

      return $result;
   }//print_insert_select_boxx

   /*!
    * \brief This will insert some connected radio buttons in a standard form.
    *
    * \param $name        The field name that will be used as the variable name in the GET or POST.
    * \param $value_array The array defining all values and their descriptions.
    *                     the key will be used as value and the entry will be used as description, i.e.:
    *                     'value' => 'description'.
    * \param $selected    If multiple this should be an array of all values that should be selected from start
    *                     else the value of the selected value.
    * \param $attbs Additionnal attributes.
    * \param $use_disable false to restrict 'disabled'-attribute to $attbs-argument;
    *                     otherwise this->disabled is used.
    */
   public function print_insert_radio_buttonsx( $name, $value_array, $selected, $attbs='', $use_disable=true )
   {
      $result = '';
      foreach ( $value_array as $value => $info )
      {
         $result .= "<INPUT type=\"radio\" name=\"$name\" value=\"$value\"";
         if ((string)$value == $selected)
            $result .= " checked";

         $result .= $this->get_input_attbs($use_disable);
         $result .= self::parse_input_standard_attributes($attbs);
         $result .= "> $info";
      }

      return $result;
   }//print_insert_radio_buttonsx


   /*!
    * \brief This will insert some checkboxes.
    *
    * \param $name        The field name that will be used as the variable name in the GET or POST.
    * \param $value       The value of the variable if checked.
    * \param $description A description of the checkbox.
    * \param $selected    True if checked at beginning.
    * \param $attbs       optional attributes.
    */
   public function print_insert_checkbox( $name, $value, $description, $selected, $attbs='' )
   {
      $result = "<INPUT type=\"checkbox\" name=\"$name\" value=\"$value\"";
      if ($selected)
         $result .= " checked";

      $result .= $this->get_input_attbs();
      $result .= self::parse_input_standard_attributes($attbs);
      $result .= ">$description";

      return $result;
   }//print_insert_checkbox

   /*!
    * \brief This will insert a submit button in a standard form.
    *
    * \param $name The field name that will be used as the variable name in the GET or POST.
    * \param $text The text on the submit button.
    */
   public function print_insert_submit_button( $name, $text )
   {
      return "<INPUT type=\"submit\" name=\"$name\" value=\"$text\"" .
         $this->get_input_attbs() . ">";
   }

   /*!
    * \brief This will insert a submit button in a standard form.
    *
    * \param $name The field name that will be used as the variable name in the GET or POST.
    * \param $text The text on the submit button.
    * \param $attbs Additionnal attributes.
    */
   public function print_insert_submit_buttonx( $name, $text, $attbs )
   {
      $str = self::parse_input_standard_attributes($attbs);
      return "<INPUT type=\"submit\" name=\"$name\" value=\"$text\"" .
         $this->get_input_attbs() . $str . ">";
   }

   /*!
    * \brief This will insert a file-upload input-box in a standard form.
    *
    * \note
    * - If you are build your own form using this function,
    *   you will need 'enctype="multipart/form-data"' as form-attribute
    *   to bring the file-upload correctly to work.
    * - This is done automatically if added to this Forum with add_row(..).
    * - Also this does not work automatically if Form is constructed
    *   with $echo_form_start_now=true.
    *
    * \param $name     The field name that will be used as the variable name in the GET or POST.
    * \param $size     field-box size
    * \param $maxlength maximum length of uploaded file (not implemented by most browser),
    *                  0 to use no maxlength (=default)
    * \param $accept   what MIME-types are accepted (not implemented by most browser),
    *                  e.g. 'image/*', 'text/*' (default is '')
    * \param $with_maxsize if true, add hidden with name MAX_FILE_SIZE directly before file-upload according to
    *                  http://de2.php.net/manual/en/features.file-upload.post-method.php
    */
   public function print_insert_file( $name, $size, $maxlength=0, $accept='', $with_maxsize=false )
   {
      $result = '';
      $str = '';
      if ( $maxlength > 0 )
      {
         $str .= sprintf( ' maxlength="%s"', $maxlength );
         if ( $with_maxsize )
            $result .= self::print_insert_hidden_input( 'MAX_FILE_SIZE', $maxlength );
      }
      if ( $accept != '' )
         $str .= sprintf( ' accept="%s"', $accept );
      $result .= "<INPUT type=\"file\" name=\"$name\" size=\"$size\"" . $str . ">";
      return $result;
   }//print_insert_file

   /*!
    * \brief This will insert filter-elements.
    *
    * \param $filters SearchFilters-object
    * \param $fid     filter-id
    * \param $attr    additional attributes to print filter-form-elements, default empty-array
    */
   public function print_insert_filter( $filters, $fid, $attr = array() )
   {
      $filter = $filters->get_filter($fid);
      if ( isset($filter) )
         return $filter->get_input_element( $filters->get_prefix(), $attr );
      else
         return '';
   }

   /*!
    * \brief This will insert filter-error if filter has an error.
    *
    * \param $filters   SearchFilters-object
    * \param $fid       filter-id
    * \param $prefix    error-msg prefixed with, default ''
    * \param $suffix    error-msg suffixed with, default ''
    * \param $with_syntax syntax-description appended to error-msg, default true
    */
   public function print_insert_filtererror( $filters, $fid, $prefix = '', $suffix = '', $with_syntax = true )
   {
      $filter = $filters->get_filter($fid);
      if ( !isset($filter) || !$filter->has_error() )
         return '';

      $msg = $filter->errormsg();
      if ( $with_syntax )
      {
         $syntax = $filter->get_syntax_description();
         if ( $syntax )
            $msg.= "; $syntax";
      }
      return $prefix . T_('Error#filter') . ': ' . make_html_safe( $msg ) . $suffix;
   }//print_insert_filtererror

   /*!
    * \brief This will insert filter-warning if filter has a warning.
    *
    * \param $filters   SearchFilters-object
    * \param $fid       filter-id
    * \param $prefix    warn-msg prefixed with, default ''
    * \param $suffix    warn-msg suffixed with, default ''
    * \param $with_syntax syntax-description appended to warn-msg, default false
    */
   public function print_insert_filterwarn( $filters, $fid, $prefix = '', $suffix = '', $with_syntax = false )
   {
      $filter = $filters->get_filter($fid);
      if ( !isset($filter) || !$filter->has_warn() )
         return '';

      $msg = $filter->warnmsg();
      if ( $with_syntax )
      {
         $syntax = $filter->get_syntax_description();
         if ( $syntax )
            $msg.= "; $syntax";
      }
      return $prefix . T_('Warning#filter') . ': ' . make_html_safe( $msg ) . $suffix;
   }//print_insert_filterwarn


   /*!
    * \brief This will add or not the 'disabled' attribut to all the following input fields.
    * \param $on if false, 'disabled' will be added.
    */
   public function enable_input( $on )
   {
      $this->disabled = ($on == false);
   }

   /*! \brief Set the global tabindex attribut of input fields. */
   public function set_tabindex( $tabindex )
   {
      $val = $this->tabindex;
      $this->tabindex = $tabindex;
      return $val;
   }

   /*! \brief Get the global attributs string of input fields. */
   private function get_input_attbs( $use_disable=true )
   {
      return ($use_disable && $this->disabled)
         ? ' disabled'
         : ( $this->tabindex ? ' tabindex="'.($this->tabindex++).'"' : '');
   }

   /*! \brief Get the merged attributs string of a form part. */
   private function get_form_attbs( $attbs='', $suffix='')
   {
      $attbs = attb_parse( $attbs);
      $attbs = attb_merge( array( 'class' => $this->fclass.$suffix ), $attbs, ' ');
      return attb_build( $attbs);
   }


   /* ******************************************************************** */

   /*!
    * \brief This will attach an object to the form. Then, the hiddens from the object are inserted when the form
    *        is closed via the object get_hiddens() function.
    *
    * \param $obj The object with the get_hiddens()-interface.
    */
   public function attach_table( &$obj )
   {
      $this->attached[]= $obj;
   }

   public function add_hidden( $key, $val)
   {
      $this->hiddens[$key] = $val;
   }

   /*! \brief return the array of hiddens merged with owneds and attachments' ones. */
   public function get_hiddens( &$hiddens)
   {
      if ( is_array($hiddens) )
         $hiddens = array_merge( $hiddens, $this->hiddens);
      else
         $hiddens = $this->hiddens;
      foreach ( $this->attached as $attach )
      {
         $attach->get_hiddens( $hiddens);
      }
   }//get_hiddens

   /*! \brief return the form-string of owned hiddens merged with attachments' ones. */
   public function get_hiddens_string()
   {
      $hiddens = $this->hiddens;
      foreach ( $this->attached as $attach )
      {
         $attach->get_hiddens( $hiddens);
      }
      $this->hiddens_echoed = true;
      return build_hidden( $hiddens);
   }//get_hiddens_string


   // ------------ static functions ----------------------------

   /*!
    * \brief (static) Parses standard attributes: title, accesskey and
    *        return all as attribute string ready to be used within form element.
    * \param $attbs attributes (string or array)
    */
   private static function parse_input_standard_attributes( $attbs, $rx_skipattr='' )
   {
      $str = '';
      $attbs = attb_parse($attbs);
      if ( is_array($attbs) )
      {
         if ( isset($attbs['title']) )
         {
            $title = trim($attbs['title']);
            unset($attbs['title']);
         }
         else
            $title = '';

         if ( isset($attbs['accesskey']) )
         {
            $xkey = trim($attbs['accesskey']);
            unset($attbs['accesskey']);
            if ( (string)$xkey != '' ) // can be '0'
            {
               $xkey = substr($xkey,0,1);
               $title .= " [&amp;$xkey]";
               $str .= ' accesskey='.attb_quote($xkey);
            }
         }
         if ( $title )
            $str .= ' title='.attb_quote($title);

         if ( isset($attbs['disabled']) )
         {
            if ( $attbs['disabled'] )
               $str .= ' disabled';
            unset($attbs['disabled']);
         }

         // skip some attributes
         if ( $rx_skipattr )
         {
            foreach ( $attbs as $akey => $aval )
            {
               if ( preg_match( "/^($rx_skipattr)$/i", $akey ) )
                  unset($attbs[$akey]);
            }
         }

         $str .= attb_build($attbs);
      }
      return $str;
   }//parse_input_standard_attributes

   // only used for debugging/code-examples
   public static function echo_internal_layout( $form )
   {
      if ( isset($form->layout[FLAYOUT_GLOBAL]) )
      {
         echo "<br><br>\nvar_export:\n<font size=+2><pre>"
            . "global-layout: " . var_export( $form->orig_layout, true ) ."\n\n"
            . "layout-array (for debugging):\n" .
               var_export( $form->layout[FLAYOUT_GLOBAL], true ) . "\n"
            . "</pre></font>\n";
      }
   }//echo_internal_layout

} // end of class 'Form'


//format a text to be placed before/between input boxes
//$seps: optionaly add separations before=1 or after=2
//&nbsp; are kept for text-only browsers
function sptext( $text, $seps=0 )
{
   return '<span class="BoxLabel'.($seps&3).'">'
      . ($seps&1 ?SMALL_SPACING :'')
      . $text
      . ($seps&2 ?SMALL_SPACING :'')
      . '</span>';
}//sptext

?>

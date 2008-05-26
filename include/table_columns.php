<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Common";

require_once( "include/form_functions.php" );
require_once( "include/filter.php" );

/*!
 * \file table_columns.php
 *
 * \brief Functions for creating standard tables.
 */

/*!
 * \class Table
 *
 * \brief Class to ease the creation of standard tables.
 */

//0x80000000 is negative in PHP. So the database field holding the Column_sets must be signed: INT(11) NOT NULL
define('ALL_COLUMNS', 0xffffffff); //=4294967295 = full 32 bits integer

//caution: provide enought "images/sort$i$c.gif" images $i:[1..n] $c:[a,d]
define('TABLE_MAX_SORT', 2); // 0 to disable the table sorts

define('TABLE_NO_SORT', 0x01); //disable the sort options (except set_default_sort)
define('TABLE_NO_HIDE', 0x02); //disable the add_or_del_column options
define('TABLE_NO_PAGE', 0x04); //disable next_prev_links options
define('TABLE_NO_SIZE', 0x08); //disable the re-sizing (static number of rows) (see use_show_rows)

define('CHAR_SHOWFILTER', '+');

define('FCONF_SHOW_TOGGLE_FILTER', 'ShowToggleFilter');    // true to show hide-toggle besides input-fields (default true)
define('FCONF_FILTER_TABLEHEAD',   'FilterTableHead');     // true to show filters above table-rows (default defined as LAYOUT_FILTER_IN_TABLEHEAD)
define('FCONF_EXTERNAL_SUBMITS',   'ExternalSubmits');     // false to include start/reset-submits into table-structure (default false), else omit

define('TFORM_VAL_SHOWROWS', 'maxrows'); // form-elementname for Show-Rows


class Table
{
   /*! \privatesection */

   /*! \brief Id to be used in <table id='...'>. */
   var $Id;
   /*! \brief Prefix to be used in _GET to avoid clashes with other tables and variables. */
   var $Prefix;
   /*! \brief already opened Form class to be used by add_column and filters */
   var $ExternalForm;

   /*! \brief Array of the columns to sort on
    *  $Sort= array( nr1 => +/-nr1, nr2 => +/-nr2, ...);
    *  => +nr : sort the column nr with its default sort order
    *  => -nr : sort the column nr with the reverse order of its default
    *  nr1 will be the main sort, nr2 the second one... up to TABLE_MAX_SORT
    */
   var $Sort;
   var $Sortimg;

   /*! \brief The page used in links within this table. */
   var $Page;
   /*! \brief Which columns in the set that is visible. */
   var $Column_set;
   /*! \brief The column of the Player table in the database to use as column_set.
    * \see $Column_set */
   var $Player_Column_set_name;
   /*! \brief The columns that has been removed. */
   var $Removed_Columns;
   /*! \brief The number of columns displayed, known after make_tablehead() */
   var $Shown_Columns;
   /*! \brief The ID of previous column displayed, of head row at begining */
   var $PrevColId;

   /*! \brief Boolean array used to check if the column should be display. */
   var $Is_Column_Displayed;

   /*!
    * \brief Array describing all tableheads:
    *        [ Nr, Description, Sort_String, Desc_Default, Undeletable, attbs(Width) ]
    * attbs is either:
    * - an array of (attribut_name => attribut_value) for the column
    * - a string supposed to be the class of the column
    */
   var $Tableheads;
   var $Head_closed;
   var $Mode; //optional features of the table
   /*! \brief a place to store some external infos (array) */
   var $ExtMode;

   /*! \brief Array of rows to be diplayed.
    * Each row should consist of an array like this:
    * array( $column_nr1 => $column_elem1,
    *        $column_nr2 => $column_elem2 );
    * Each $column_elem is either:
    * - a string which will be the inner text of the cell
    *   (this text will be enclosed by the appropriate <td...></td> tags)
    * - an array with one or more of those named values:
    *   'owntd' => the complete "<td...>text</td>" tag for the cell
    *      (if 'owntd' is present, the rest of the array is ignored)
    *   'text' => the inner text for the cell
    *   'attbs' => the local attributs for the <td...>text</td> tag
    */
   var $Tablerows;

   /*! \brief The colors to alternate between for the rows. */
   //N.B.: only used for folder transparency but CSS incompatible
   var $Row_Colors;

   /*! \brief The row number to start from.
    * \see make_next_prev_links() */
   var $From_Row;
   /*! \brief If we are on the last page.
    * \see make_next_prev_links() */
   var $Last_Page;
   /*! \brief The number of rows to be displayed (normally) on one page (read from player-row TableMaxRows).
    * \see make_next_prev_links() */
   var $Rows_Per_Page;
   /*! \brief true, if number of rows to show should be configurable (otherwise use Players.TableMaxRows). */
   var $Use_Show_Rows;

   /*!
    * \brief array of objects with external request-parameters,
    *        expecting interfaces: get_hiddens() and get_url_parts()
    * \see RequestParameters
    */
   var $ext_req_params;
   /*! \brief if false, don't use for get_hiddens-func */
   var $ext_req_params_use_hidden;
   /*! \brief cache for current_extparams_string() storing: add_sep => string */
   var $cache_curr_extparam;

   // filter-stuff

   /*! \brief non-null SearchFilter-instance containing attached filters */
   var $Filters;
   /*! \brief true to show filters within table */
   var $UseFilters;
   /*! \brief configuration-array for filters used with table: ( key => config-val )
    *         keys are defined by consts FCONF_... */
   var $ConfigFilters;
   /*! \brief The number of filter-columns displayed, known after make_table_filter-func */
   var $Shown_Filters;
   /*! \brief cache for current_filter_string-func storing: ( filter-choice => string ) */
   var $cache_curr_filter;


   /*! \publicsection */

   /*! \brief Constructor. Create a new table and initialize it. */
   function Table( $_tableid, $_page, $_player_column_set_name=''
                  , $_prefix='', $_mode=0)
   {
      global $player_row;

      $this->ExternalForm = NULL;
      $this->ExtMode = array();
      $this->Removed_Columns = NULL;
      $this->Shown_Columns = 0;
      $this->Tableheads = array();
      $this->Head_closed = 0;
      $this->Tablerows = array();
      $this->Sort = array();
      $this->Sortimg = array();

      $this->Id = (string)$_tableid;
      $this->Prefix = (string)$_prefix;
      $this->Mode = (int)$_mode;

      // prepare for appending URL-parts (ends with either '?' or URI_AMP)
      if( !is_numeric( strpos( $_page, '?')) )
         $this->Page = $_page . '?'; //end_sep
      else if( substr( $_page, -1 ) == '?'
            || substr( $_page, -strlen(URI_AMP) ) == URI_AMP )
         $this->Page = $_page; //end_sep
      else
         $this->Page = $_page . URI_AMP; //end_sep

      if( !empty($_player_column_set_name)
         && is_string($_player_column_set_name)
         && !($this->Mode&TABLE_NO_HIDE) )
      {
         $this->Player_Column_set_name = $_player_column_set_name;
         $this->Column_set = ALL_COLUMNS;
         if( isset($player_row[ $_player_column_set_name ]) )
            $this->Column_set &= $player_row[ $_player_column_set_name ];
      }
      else
      {
         $this->Mode|= TABLE_NO_HIDE;
         $this->Player_Column_set_name = '';
         $this->Column_set = ALL_COLUMNS;
      }

      //{ N.B.: only used for folder transparency but CSS incompatible
      global $table_row_color1, $table_row_color2;
      $this->Row_Colors = array( $table_row_color1, $table_row_color2 );
      //}

      $this->From_Row = (int)$this->get_arg('from_row');
      if( !is_numeric($this->From_Row) or $this->From_Row < 0 )
         $this->From_Row = 0;
      $this->Last_Page = true;
      $this->Use_Show_Rows = true;
      $this->Rows_Per_Page = $player_row['TableMaxRows'];

      // filter-stuff
      $this->ext_req_params = array();
      $this->ext_req_params_use_hidden = false;
      $this->cache_curr_extparam = array();

      $this->Filters = new SearchFilter();
      $this->UseFilters    = false;
      $this->Shown_Filters = 0;
      $this->ConfigFilters = array( // set defaults
         FCONF_SHOW_TOGGLE_FILTER => true,
         FCONF_FILTER_TABLEHEAD   => LAYOUT_FILTER_IN_TABLEHEAD,
         FCONF_EXTERNAL_SUBMITS   => false,
      );
      $this->cache_curr_filter = array();
   } //Table

   /*! \brief Sets external form for this table, $form is passed as reference */
   function set_externalform( &$form )
   {
      $this->ExternalForm = $form;
      $form->attach_table($this);
   }

   /*! \brief Overwrites standard rows-per-page for this table */
   function set_rows_per_page( $rows )
   {
      $this->Rows_Per_Page = $rows;
   }

   /*! \brief if false, rows-selection is not shown (table using static number of maxrows); default is true. */
   function use_show_rows( $use=true )
   {
      $this->Use_Show_Rows = (bool)$use;
   }

   /*! \brief Add a tablehead.
    * \param $nr must be >0 but if highter than 32, the column will be static
    * \param $attbs must be an array of attributs or a class-name for the column
    */
   function add_tablehead( $nr,
                           $description,
                           $sort_string = '',
                           $desc_default = false,
                           $undeletable = false,
                           $attbs = null )
   {
      if( $this->Head_closed )
         error('assert', "Table.add_tablehead.closed($nr)");
      if( $nr <= 0 )
         error('assert', "Table.add_tablehead.bad_col_nr($nr)");
      if( !is_array($attbs) )
      {
         if( is_string($attbs) )
            $attbs= array( 'class' => $attbs);
         else
            $attbs= null;
      }
      //adjust old style parameters to new ones
      $mode = ($undeletable ? TABLE_NO_HIDE : 0) | $this->Mode;
      $sort_extnd= trim($sort_string);
      if( !$sort_extnd )
         $mode|= TABLE_NO_SORT;
      else if( !is_numeric(strpos('+-',substr($sort_extnd,-1))) )
         $sort_extnd.= ($desc_default ? '-' : '+');
      $this->Tableheads[$nr] =
         array( 'Nr' => $nr,
                'Description' => $description,
                'Sort_String' => $sort_extnd,
                'Mode' => $mode,
                'attbs' => $attbs );

      $visible = $this->Is_Column_Displayed[$nr] = $this->is_column_displayed( $nr);
      if ( $this->UseFilters ) // fix filter-visibility (especially for static cols)
         $this->Filters->set_visible($nr, $visible);
   } //add_tablehead

   /*! \brief records the default order of the table
    *   by the way, close and compute the headers definitions
    *  \param $default_sorts are +/- $column_nbr
    *  works even if TABLE_NO_SORT or TABLE_MAX_SORT==0
    *    (allowing a current_order_string() default usage)
    */
   function set_default_sort( /* {$default_sort1 {,$default_sort2 [,...]}} */)
   {
      if( $this->Head_closed )
         error('assert', "Table.set_default_sort.closed({$this->Head_closed})");
      $this->Head_closed= 1;
      $s= array();
      //even if TABLE_NO_SORT or TABLE_MAX_SORT==0:
      for( $i=func_num_args(); $i>0; )
      {
         --$i;
         $sd= func_get_arg($i);
         if( is_string($sd) )
            error('assert', "Table.set_default_sort.old_way($sd)");
         if( $sd=(int)$sd )
            $s= array(abs($sd)=>$sd) + $s;
      }
      if( TABLE_MAX_SORT>0 && !($this->Mode&TABLE_NO_SORT) )
      {
         //get the sort parameters from URL
         for( $i=TABLE_MAX_SORT; $i>0; )
         {
            if( $sd=(int)$this->get_arg("sort$i") )
               $s= array(abs($sd)=>$sd) + $s;
            --$i;
         }
      }
      $this->Sort= $s;
   } //set_default_sort

   /*! \brief Check if column is displayed. */
   function is_column_displayed( $nr )
   {
      if( $nr < 1 ) return 0;
      if( $nr > 32 ) return 1;
      $mask = (1 << ($nr-1));
      if( !($mask&ALL_COLUMNS) ) return 1;
      if( (TABLE_NO_HIDE&@$this->Tableheads[$nr]['Mode']) ) return 1;
      return ($mask&$this->Column_set);
   }

   /*!
    * \brief Add a row to be displayed.
    * \see $Tablerows
    */
   function add_row( $row_array )
   {
      $this->Tablerows[]= $row_array;
   }

   /*! \brief Create a string of the table. */
   function make_table()
   {
      //if( !$this->Head_closed )
      //   error('assert', "Table.make_table.!closed({$this->Head_closed})");

      /* Make tablehead */

      $this->Shown_Columns = 0;
      $this->PrevColId = $this->Prefix.'TableHead';
      $head_row = "\n <tr id=\"{$this->PrevColId}\" class=Head>";
      foreach( $this->Tableheads as $thead )
      {
         $head_row .= $this->make_tablehead( $thead );
      }
      $head_row .= "\n </tr>";

      /* Make filter row */

      $filter_row = '';
      $need_form = $this->make_filter_row( $filter_row );

      /* Make table rows */

      $table_rows = '';
      if( count($this->Tablerows)>0 )
      {
         $c=0;
         foreach( $this->Tablerows as $trow )
         {
            $c=($c % LIST_ROWS_MODULO)+1;
            $table_rows .= $this->make_tablerow( $trow, "Row$c" );
         }
      }

      /* Build the table */

      $string = "\n<table id='{$this->Id}Table' class=Table>";
      $string .= $this->make_next_prev_links('T');
      $string .= $head_row;
      if ( $this->ConfigFilters[FCONF_FILTER_TABLEHEAD] )
      { // filter at table-header
         $string .= $filter_row; // maybe empty
         $string .= $table_rows;
      }
      else
      { // filter at table-bottom
         $string .= $table_rows;
         $string .= $filter_row; // maybe empty
      }
      if( $table_rows )
         $string .= $this->make_next_prev_links('B');
      $string .= "\n</table>\n";

      /* Add filter + column form, embedding main-table into table-form */

      // NOTE: avoid nested forms with other forms (because nested hiddens are not working)

      if( isset($this->ExternalForm) )
         $table_form = $this->ExternalForm; // read-only
      else if( $need_form || !($this->Mode&TABLE_NO_HIDE) ) // need form for filter or add-column
      {
         $table_form = new Form( $this->Prefix.'tableFAC', // Filter/AddColumn-table-form
            clean_url( $this->Page),
            FORM_GET, false, 'FormTableFAC');
         $table_form->attach_table($this);
      }
      else
         unset( $table_form);


      if( $need_form || !($this->Mode&TABLE_NO_HIDE) ) // add-col & filter-submits
      {
         $addcol_str = $this->make_add_column_form( $table_form);
         $string .= $addcol_str;
      }

      // build form for Filter + AddColumn
      if ( isset($table_form) && is_null($this->ExternalForm) )
      {
         $string = $table_form->print_start_default()
            . $string // embed table
            . $table_form->print_end();
      }
      unset($table_form);

      return $string;
   } //make_table

   /*! \brief Echo the string of the table. */
   function echo_table()
   {
      echo $this->make_table();
   } //echo_table

   /*!
    * \brief Passes back filter_row per ref and return true, if form needed because of filters.
    * signature: bool need_form = make_filter_row( &$filter_rows)
    */
   function make_filter_row( &$filter_rows )
   {
      // make filter-rows
      $this->Shown_Filters = 0;
      $need_form  = false;
      $filter_rows = '';

      if ( $this->UseFilters )
      {
         // make filters
         $row_cells['Filter'] = '';
         foreach( $this->Tableheads as $thead )
            $row_cells['Filter'] .= $this->make_table_filter( $thead );

         // add error-messages for filters
         if ( $this->Shown_Filters > 0 )
         {
            // build error-messages
            $arr = $this->Filters->get_filter_keys(GETFILTER_ERROR);
            $arr_err = array();
            foreach ($arr as $id) {
               $filter = $this->Filters->get_filter($id);
               $syntax = $filter->get_syntax_description();
               $arr_err[]=
                  "<strong>{$this->Tableheads[$id]['Description']}:</strong> "
                  . '<em>' . T_('Error#filter') . ': ' . $filter->errormsg() . '</em>'
                  . ( ($syntax != '') ? "; $syntax" : '');
            }
            $errormessages = implode( '<br>', $arr_err );

            if ( $errormessages != '' )
               $row_cells['Error'] =
                  "\n  <td class=ErrMsg colspan={$this->Shown_Columns}>$errormessages</td>";

            $need_form = true;
         }

         // include row only, if there are filters
         if ( $this->ConfigFilters[FCONF_SHOW_TOGGLE_FILTER] or $this->Shown_Filters > 0 )
         {
            $tr_attbs = " id=\"{$this->Prefix}TableFilter\""; // only for 1st entry
            foreach( $row_cells as $class => $cells )
            {
               if( !$cells )
                  continue;
               $filter_rows .= "\n <tr$tr_attbs class=\"$class\">";
               $filter_rows .= $cells;
               $filter_rows .= "\n </tr>";
               $tr_attbs = '';
            }
         }
      }

      return $need_form;
   } //make_filter_row

   /*! \brief Return the attributs of a warning cellule. */
   function warning_cell_attb( $title='', $array=false)
   {
      if( $array )
      {
         $str= array('class' => 'Warning');
         if( $title ) $str['title']= $title;
      }
      else
      {
         $str= ' class=Warning';
         if( $title ) $str.= ' title=' . attb_quote($title);
      }
      return $str;
   }

   /*! \brief Return the global style part of a table with buttons. */
   function button_style( $button_nr=0)
   {
      global $button_max, $buttoncolors, $buttonfiles;
      //global $button_width;

      if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
         $button_nr = 0;

      return
        "table.Table a.Button {" .
          " color: {$buttoncolors[$button_nr]};" .
        "}\n" .
        "table.Table td.Button {" .
          " background-image: url(images/{$buttonfiles[$button_nr]});" .
        "}";
   }

   /*!
    * \brief Return the cell part of a button with anchor.
    */
   function button_TD_anchor( $href, $text='')
   {
      //return "\n  <td class=Button><a class=Button href=\"$href\">$text</a></td>";
      return "<a class=Button href=\"$href\">$text</a>";
   }

   /*!
    * \brief Return a stratagem to force a minimal column width.
    * Must be inserted before a cell inner text at least one time for a column.
    */
   function button_TD_width_insert( $width=false)
   {
      //a stratagem to force a minimal column width.
      //must be changed when, a day, the CSS min-width
      // command will work fine with every browser.
      //dot.gif is a 1x1 transparent image.
      global $base_path, $button_width;
      if( !is_numeric($width) )
         $width = $button_width;
      //the following "height=0" is useful for Avant browser, others are managed with CSS
      return "<img class=MinWidth src='{$base_path}images/dot.gif' width=$width height=0 alt=''>";
   }

   /*!
    * \brief Returns (locally cached) URL-parts for all filters (even if not used).
    * signature: string querystring = current_filter_string ([ choice = GETFILTER_ALL ])
    */
   function current_filter_string( $end_sep=false, $filter_choice=GETFILTER_ALL ) {
      if( !isset($this->cache_curr_filter[$filter_choice]) )
      {
         $trash = null;
         $this->cache_curr_filter[$filter_choice] = $this->Filters->get_url_parts( $trash, $filter_choice );
      }
      $url = $this->cache_curr_filter[$filter_choice];
      if( $url && $end_sep )
         $url.= URI_AMP;
      return $url;
   }

   /*!
    * \brief Add or delete a column from the column set and apply it to the database;
    *        Also handle to show or hide filters and parsing max-rows from URL.
    */
   function add_or_del_column()
   {
      if( ($i=count($this->Tableheads)) )
         error('assert', "Table.add_or_del_column.past_head_start($i)");

      // handle filter-visibility
      $this->Filters->add_or_del_filter();

      // handle show-rows
      $this->handle_show_rows();

      // handle column-visibility (+ adjust filters)
      $adds = $this->get_arg('add');
      if( empty($adds) )
         $adds = array();
      else if( !is_array($adds) )
         $adds = array($adds);
      $dels = $this->get_arg('del');
      if( empty($dels) )
         $dels = array();
      else if( !is_array($dels) )
         $dels = array($dels);

      $newset = $this->Column_set;
      foreach( $adds as $add )
      {
         $add = (int)$add;
         if( $add > 0 && $add <= 32 ) // add col
         {
            $newset |= (1 << ($add-1));
            $this->Filters->reset_filter($add);
            $this->Filters->set_active($add, true);
         }
         else if( $add < 0 ) // add all cols
         {
            $newset = ALL_COLUMNS;
            $this->Filters->reset_filters( GETFILTER_ALL );
            $this->Filters->setall_active(true);
         }
      }
      foreach( $dels as $del )
      {
         $del = (int)$del;
         if( $del > 0 && $del <= 32 ) // del col
         {
            $newset &= ~(1 << ($del-1));
            $this->Filters->reset_filter($del);
            $this->Filters->set_active($del, false);
         }
         else if( $del < 0 ) // del all cols
         {
            $newset = 0;
            $this->Filters->reset_filters( GETFILTER_ALL );
            $this->Filters->setall_active(false);
         }
      }

      global $player_row;
      $uid = (int)@$player_row['ID'];
      /*
       * Here, depending of the previous calculus, either $newset or
       *  $this->Column_set can be signed (int) or signed (float)
       *  and so covering the whole [-0x80000000,0xffffffff] interval.
       * Cares are needed especially for the write in the database
       *  as a value>0x7fffffff can't be recorded in a SIGNED INT field
       *  while a value<0 can't be recorded in an UNSIGNED INT field
       */
      $newset &= ALL_COLUMNS; //and reset it to the (signed) integer type
      if( $uid > 0 && !empty($this->Player_Column_set_name)
         && (($newset ^ $this->Column_set) & ALL_COLUMNS ) )
      {
         $player_row[ $this->Player_Column_set_name ] =
         $this->Column_set = $newset;
         //note: the column field must be a SIGNED INT
         $query = "UPDATE Players SET " . $this->Player_Column_set_name
            ."=$newset WHERE ID=$uid LIMIT 1";

         mysql_query($query)
            or error('mysql_query_failed','Table.add_or_del_column');
      }
   } //add_or_del_column

   /*! \brief Sets Rows_Per_Page (according to changed Show-Rows-form-element or else cookie or players-row). */
   function handle_show_rows()
   {
      if ( !$this->Use_Show_Rows )
         return;

      $rows = (int)$this->get_arg(TFORM_VAL_SHOWROWS); // nr of rows
      if ( $rows > 0 )
         $this->Rows_Per_Page =
            get_maxrows( $rows, MAXROWS_PER_PAGE, MAXROWS_PER_PAGE_DEFAULT );
   }

   /*! \brief Compute the number of rows from mySQL result. */
   function compute_show_rows( $num_rows_result)
   {
      if( $this->Rows_Per_Page > 0 && $num_rows_result > $this->Rows_Per_Page )
      {
         $num_rows_result = $this->Rows_Per_Page;
         $this->Last_Page = false;
      }
      else
         $this->Last_Page = true;
      return $num_rows_result;
   } //compute_show_rows

   /*! \brief Retrieve MySQL ORDER BY part from table.
    *   $final_field is an extended-field-name
    *      (ended by a '-+' like in add_tablehead())
    *  works even if TABLE_NO_SORT or TABLE_MAX_SORT==0
    *    (computing the set_default_sort() datas)
    */
   function current_order_string( $final_field='')
   {
      if( !$this->Head_closed )
         error('assert', "Table.current_order_string.!closed({$this->Head_closed})");
      //if( TABLE_MAX_SORT<=0 || $this->Mode&TABLE_NO_SORT ) return;

      //$this->Sortimg = array();
      $str = ''; $i=0;
      foreach( $this->Sort as $sd )
      {
         if( $i >= TABLE_MAX_SORT )
            break;
         if( !$sd )
            continue;
         $sk= abs($sd);
         $field= (string)@$this->Tableheads[$sk]['Sort_String'];
         if( !$field )
            continue;
         ++$i;
         if( $sd<0 )
            $str.= strtr( $field, '+-', '-+');
         else
            $str.= $field;
         $c= (substr($str, -1) == '+' ? 'a' : 'd' );
         //alt-attb and part of "images/sort$i$c.gif" $i:[1..n] $c:[a,d]
         $this->Sortimg[$sk]= "$i$c";
      }
      if( $final_field )
      {
         if( $str )
         {
            $c= preg_replace( "/[-+]+/", "[-+]", trim( $final_field, "-+ ") );
            if( !preg_match( "/\\b{$c}[-+]/i", $str) )
               $str.= $final_field;
         }
         else
            $str= $final_field;
      }
      if( !$str )
         return '';
      $str= str_replace( array('-','+'), array(' DESC,',','), $str);
      return ' ORDER BY '.substr($str,0,-1);
   } //current_order_string

   /*! \brief Retrieve mySQL LIMIT part from table. */
   function current_limit_string()
   {
      if ( $this->Rows_Per_Page <= 0 )
         return '';
      return ' LIMIT ' . $this->From_Row . ',' . ($this->Rows_Per_Page+1) ;
   }

   /*! \brief Retrieve from part of url from table. */
   function current_from_string( $end_sep=false )
   {
      if( $this->From_Row > 0 )
      {
         $str = $this->Prefix . 'from_row=' . $this->From_Row;
         if( $end_sep )
            $str.= URI_AMP;
         return $str;
      }
      return '';
   }

   /*! \brief Retrieves maxrows URL-part. */
   function current_rows_string( $end_sep=false )
   {
      if( !$this->Use_Show_Rows )
         return '';

      $str = $this->Prefix . TFORM_VAL_SHOWROWS . '=' . $this->Rows_Per_Page;
      if( $end_sep )
         $str.= URI_AMP;
      return $str;
   }

   /*! \brief Retrieve sort part of url from table. */
   function current_sort_string( $end_sep=false)
   {
      return $this->make_sort_string( 0, $end_sep);
   }

   /*! \brief Retrieve hidden part from table as a form string. */
   function get_hiddens_string( $filter_choice = GETFILTER_ALL )
   {
      $hiddens= array();
      $this->get_hiddens( $hiddens, $filter_choice );
      return build_hidden( $hiddens);
   }

   /*! \brief Retrieve hidden part from table. */
   function get_hiddens( &$hiddens)
   {
      $i=0;
      if( TABLE_MAX_SORT>0 && !($this->Mode&TABLE_NO_SORT) )
      {
         foreach( $this->Sort as $sd )
         {
            if( $i >= TABLE_MAX_SORT )
               break;
            if( !$sd )
               continue;
            ++$i;
            $hiddens[$this->Prefix . "sort$i"] = $sd;
         }
      }

      // include from_row (if unchanged search), otherwise remove from_row !?
      //   => reset from_row somehow (checking using hashcode on filter-values)
      if ( $this->Rows_Per_Page > 0 && $this->From_Row > 0 )
         $hiddens[$this->Prefix . 'from_row'] = $this->From_Row;

      // NOTE: no active-fields needed, because they have form-values and
      //       inactive need not to be saved; but need general filter-attribs
      if ( $this->UseFilters )
         $this->Filters->get_filter_hiddens( $hiddens, GETFILTER_NONE );

      // include hiddens from external RequestParameters
      if ( $this->ext_req_params_use_hidden )
      {
         foreach( $this->ext_req_params as $rp )
            $rp->get_hiddens( $hiddens );
      }
   }

   /*!
    * \brief Returns array with Nr and description of displayed tableheads.
    * signature: array( Nr => Descr ) = get_displayed_tableheads()
    */
   function get_displayed_tableheads()
   {
      $arr_displayed = array(); // Nr => Description
      foreach( $this->Tableheads as $thead )
      {
         $nr = $thead['Nr'];
         if( $this->Is_Column_Displayed[$nr] ) $arr_displayed[$nr] = $thead['Description'];
      }
      return $arr_displayed;
   }


   /*! \privatesection */

   function make_tablehead( $tablehead )
   {
      //if( !$this->Head_closed )
      //   error('assert', "Table.make_tablehead.!closed({$this->Head_closed})");
      $nr = (int)@$tablehead['Nr'];
      if( $nr < 1 )
         return '';

      if( !$this->Is_Column_Displayed[$nr] )
      {
         $this->Removed_Columns[ $nr ] = $tablehead['Description'];
         return '';
      }

      $this->Shown_Columns++;
      $mode = $this->Mode | (int)@$tablehead['Mode'];

      $curColId = $this->Prefix.'Col'.$nr;
      $string = "\n  <th id=\"$curColId\" scope=col";

      $width = -1;
      if( isset($tablehead['attbs']) )
      {
         $attbs =& $tablehead['attbs'];
         if( is_array($attbs) )
         {
            $string .= attb_build($attbs);

            if( is_numeric( strpos((string)@$attbs['class'],'Button')) )
            {
               global $button_width;
               $width = max($width, $button_width);
            }
            if( isset($attbs['width']) )
            {
               $width = max($width, (int)$attbs['width']);
               unset( $attbs['width']);
            }
         }
      }
      $string .= '><div>'; //<th> end bracket

      if( $width >= 0 )
         $string .= $this->button_TD_width_insert($width);

      $common_url =
         $this->current_extparams_string(true)
         . $this->current_rows_string(true)
         . $this->current_filter_string(true)
         ; //end_sep

      // field-sort-link
      $title = (string)@$tablehead['Description'];
      $field = (string)@$tablehead['Sort_String'];
      $sortimg= (string)@$this->Sortimg[$nr];

      if( $field && !($mode&TABLE_NO_SORT) )
      {
         $hdr = '<a href="' . $this->Page; //end_sep
         $hdr .= $this->make_sort_string( $nr, true ); //end_sep
         $hdr .= $this->current_from_string( true); //end_sep
         $hdr .= $common_url; //end_sep
         $hdr = clean_url($hdr) . "#$curColId\" title="
            . attb_quote(T_('Sort')) . ">$title</a>";
      }
      else
      {
         $hdr = $title;
      }
      $string .= '<span class="Header">' . $hdr . '</span>';

      $query_del = !($mode&TABLE_NO_HIDE);
      if( $query_del )
      {
         $query_del = $this->Page //end_sep
            . $this->current_sort_string(true)
            . $common_url; //end_sep

         // add from_row, if filter-value is empty,
         //   or else has a value but has an error (then removing of filter or column doesn't change the page)
         if( $this->UseFilters )
         {
            $filter = $this->Filters->get_filter($nr);
            if( isset($filter) && ( $filter->is_empty() ^ $filter->has_error() ) )
               $query_del .= $this->current_from_string(true); //end_sep
         }
      }
      if( $query_del || $sortimg )
      {
         global $base_path;
         if( $query_del ) //end_sep
         {
            $tool1 = image( $base_path.'images/remove.gif', 'x', '', 'class=Hide');
            $tool1 = anchor(
                 $query_del . "{$this->Prefix}del=$nr#{$this->PrevColId}"
               , $tool1, T_('Hide'));
         }
         else
         {
            $tool1 = image( $base_path.'images/dot.gif', '', '', 'class=Hide');
            $tool1 = "<span>$tool1</span>";
         }

         if( $sortimg )
         {
            //$sortimg: "$i$c" $i:[1..n] $c:[a,d]
            $tool2 = image( $base_path."images/sort$sortimg.gif", $sortimg, '', 'class=Sort');
            $tool2 = "<span>$tool2</span>";
         }
         else
         {
            $tool2 = image( $base_path.'images/dot.gif', '', '', 'class=Sort');
            $tool2 = "<span>$tool2</span>";
         }

         $string .= '<span class=Tool>' . $tool1.$tool2 . "</span>";
      }

      $string .= "</div></th>";

      $this->PrevColId = $curColId;
      return $string;
   }

   /*!
    * \brief Returns html for filter of passed TableHead
    * \internal
    */
   function make_table_filter( $thead )
   {
      // get filter for column
      $fid = $thead['Nr']; // keep as sep var
      $filter =& $this->Filters->get_filter($fid); // need ref for update

      // check, if column displayed
      $nr = $thead['Nr']; // keep as sep var
      $col_displayed = $this->Is_Column_Displayed[$nr];
      if ( !$col_displayed )
         return '';
      if ( !$filter )
         return "\n  <td></td>";
      // now: $filter valid

      // prepare strings for toggle-filter (if filter existing for field)
      $togglestr_show = '';
      $togglestr_hide = '';
      if ( $this->ConfigFilters[FCONF_SHOW_TOGGLE_FILTER] )
      {
         $fprefix = $this->Filters->Prefix;
         $query =
            make_url( $this->Page, array(
                  $fprefix . FFORM_TOGGLE_ACTION => 1,
                  $fprefix . FFORM_TOGGLE_FID    => $fid ), true)
            . $this->current_extparams_string(true)
            . $this->current_sort_string(true)
            . $this->current_rows_string(true)
            . $this->current_filter_string(true)
            ; //end_sep

         // add from_row, if filter-value is empty, or else has a value but has an error
         //    (then removing of filter or column doesn't change the page)
         if ( $filter->is_empty() ^ $filter->has_error() )
            $query .= $this->current_from_string(true); //end_sep

         $query = clean_url( $query);
         $fcolor_hide = ( $filter->errormsg() ) ? 'white' : 'red';
         $togglestr_show =
            "<a href=\"$query\" title=" . attb_quote(T_('Show')) . '>'
            . CHAR_SHOWFILTER ."</a>";
         global $base_path;
         $togglestr_hide = image( $base_path.'images/remove.gif', 'x', '', 'class=Hide');
         $togglestr_hide = anchor( $query, $togglestr_hide, T_('Hide'));
         $togglestr_hide = '<span class=Tool>' . $togglestr_hide . "</span>";
      }

      // check, if filter-field shown or hidden
      if( !$filter->is_active() )
      {
         if( $togglestr_show )
            return "\n  <td class=ShowFilter>$togglestr_show</td>";
         return "\n  <td></td>";
      }

      $this->Shown_Filters++;

      // filter input-element
      $class= trim(@$thead['attbs']['class']
         . ( $filter->has_error() ? ' Error' : '' ));
      if( $class )
         $class= " class=\"$class\"";
      $result = "\n  <td$class><div>";
      $result .= $filter->get_input_element( $this->Filters->Prefix, $thead );
      if( !$filter->is_static() )
         $result .= $togglestr_hide;
      $result .= "</div></td>";

      return $result;
   } //make_table_filter


   //{ N.B.: only used for folder transparency but CSS incompatible
   function blend_next_row_color_hex( $col=false )
      {
         $rowcol = substr($this->Row_Colors[
                     count($this->Tablerows) % count($this->Row_Colors)
                   ], 2, 6);
         if( $col )
            return blend_alpha_hex( $col, $rowcol);
         else
            return $rowcol;
      }
   //}

   function make_tablerow( $tablerow, $rclass='Row1' )
   {
      if( isset($tablerow['class']) )
      {
         $rclass = $tablerow['class'];
      }

      $string = "\n <tr class=\"$rclass\"";
      if( ALLOW_JSCRIPT && !@$tablerow['noclick'] )
      { //onClick onmousedown ondblclick
         //$string.= " ondblclick=\"javascript:this.className=((this.className=='highlight')?'$rclass':'highlight');\"";
         $string.= " ondblclick=\"javascript:this.className=((this.className=='$rclass')?'Hil$rclass':'$rclass');\"";
      }
      $string.= ">";

      $colspan= 0;
      foreach( $this->Tableheads as $thead )
      {
         $nr = $thead['Nr'];
         if( $this->Is_Column_Displayed[$nr] && --$colspan<=0 )
         {
            $cell= @$tablerow[$nr];
            if( is_array($cell) )
            {
               $text= (string)@$cell['owntd'];
               if( $text )
               {
                  $string.= $text;
                  continue;
               }
               $text= (string)@$cell['text'];
               $attbs= @$cell['attbs'];
               if( !is_array($attbs) )
                  $attbs= array();
            }
            else
            {
               $text= (string)@$cell;
               $attbs= array();
            }
            $colspan= $attbs['colspan']= max(1,@$attbs['colspan']);
            $class= $attbs['class']= trim(@$thead['attbs']['class'].' '.@$attbs['class']);
            if( !$class ) unset($attbs['class']);
            $string.= "\n  <td".attb_build($attbs).">";
            $string.= $text."</td>";
         }
      }

      $string .= "\n </tr>";

      return $string;
   }


   /*! \brief Add next and prev links. */
   /*
      To show the page index: page number / number of pages
         mysql> SELECT SQL_CALC_FOUND_ROWS * FROM tbl_name
             -> WHERE id > 100 LIMIT 10;
         mysql> SELECT FOUND_ROWS();
      SQL_CALC_FOUND_ROWS must be at the front of any fields
       in the SELECT statement.
      Be aware that using SQL_CALC_FOUND_ROWS and FOUND_ROWS()
       disables ORDER BY ... LIMIT optimizations.
      The first query is longer than without the SQL_CALC_FOUND_ROWS
       but if the ORDER BY is on a not indexed column, this is small.
      MySQL >= 4.0.0. Refs at:
       http://dev.mysql.com/doc/refman/4.1/en/information-functions.html
   */
   function make_next_prev_links($id)
   {
      if ( $this->Rows_Per_Page <= 0 or $this->Shown_Columns <= 0
            or !( $this->From_Row > 0 or !$this->Last_Page ) )
         return '';

      $string = 'align=bottom'; //'align=middle'
      $button = '';

      $qstr = $this->Page //end_sep
         . $this->current_extparams_string(true)
         . $this->current_sort_string(true)
         . $this->current_rows_string(true)
         . $this->current_filter_string(true)
         ; //end_sep

      if( $this->From_Row > 0 )
         $button.= anchor(
              $qstr //end_sep
              . $this->Prefix . 'from_row=' . ($this->From_Row-$this->Rows_Per_Page)
            , image( 'images/prev.gif', '<=', '', $string)
            , T_("prev page")
            , array('accesskey' => '<')
            );

      $button.= '&nbsp;'.round($this->From_Row/$this->Rows_Per_Page+1).'&nbsp;';

      if( !$this->Last_Page )
         $button.= anchor(
              $qstr //end_sep
              . $this->Prefix . 'from_row=' . ($this->From_Row+$this->Rows_Per_Page)
            , image( 'images/next.gif', '=>', '', $string)
            , T_("next page")
            , array('accesskey' => '>')
            );


      $string = '';

      $span = floor($this->Shown_Columns/2);
      if( $span < 2 ) $span = $this->Shown_Columns;
      if( $span > 0 )
         $string.= "\n  <td class=PagingL"
           . ($span>1 ? " colspan=$span" : '') . ">$button</td>";

      $span = $this->Shown_Columns - $span;
      if( $span > 0 )
         $string.= "\n  <td class=PagingR"
           . ($span>1 ? " colspan=$span" : '') . ">$button</td>";

      if( $string )
         $string = "\n <tr class=Links$id>\n$string\n </tr>";

      return $string;
   } //make_next_prev_links

   /*! \brief Make sort part of URL */
   function make_sort_string( $add_sort=0, $end_sep=false )
   {
      if( TABLE_MAX_SORT<=0 || $this->Mode&TABLE_NO_SORT ) return '';
      $s= $this->Sort;
      if( $add_sort )
      {
         $key= abs($add_sort);
         if( isset($s[$key]) ) //if it is in list...
         {
            //reset($s);
            list($sk,$sd)= each($s);
            if( $key == $sk ) //if it is main sort...
               $add_sort= -$sd; //toggle sort order
            else
               $add_sort= $s[$key]; //move it on main place
         }
         //unset($s[$key]);
         $s= array($key=>$add_sort) + $s;
      }
      $str = ''; $i=0;
      foreach( $s as $sd )
      {
         if( $i >= TABLE_MAX_SORT )
            break;
         if( !$sd )
            continue;
         ++$i;
         if( $str )
            $str.= URI_AMP.$this->Prefix . "sort$i=$sd";
         else
            $str= $this->Prefix . "sort$i=$sd";
      }
      if( $str && $end_sep )
         $str.= URI_AMP;
      return $str;
   } //make_sort_string

   /*! \brief Adds a form for adding columns. */
   function make_add_column_form( &$ac_form)
   {
      // add-column-elements
      $ac_string = '';
      if( !($this->Mode&TABLE_NO_HIDE) && count($this->Removed_Columns) > 0 )
      {
         split_url($this->Page, $page, $args);
         foreach( $args as $key => $value ) {
            $ac_form->add_hidden( $key, $value);
         }
         //Note: asort on translated strings
         asort($this->Removed_Columns);
         $this->Removed_Columns[ 0 ] = '';
         $this->Removed_Columns[ -1 ] = T_('All columns');
         $ac_string = $ac_form->print_insert_select_box(
               $this->Prefix.'add', '1', $this->Removed_Columns, '', false);
         $ac_string.= $ac_form->print_insert_submit_button(
               $this->Prefix.'addcol', T_('Add Column'));
      }

      // add filter-submits in add-column-row if not in table-head and need form for filter
      $f_string = '';
      if( $this->UseFilters && !$this->ConfigFilters[FCONF_EXTERNAL_SUBMITS] )
         $f_string = implode( '', $this->Filters->get_submit_elements() );

      // add show-rows elements in add-column-row
      $r_string = $this->make_show_rows( $ac_form );

      $string = false;
      if ( $ac_string or $f_string or $r_string )
      {
         $arr = array();
         if ( $f_string )  $arr[]= $f_string;
         if ( $r_string )  $arr[]= $r_string;
         if ( $ac_string ) $arr[]= $ac_string;

         $string = "<div id='{$this->Prefix}tableFAC'>";
         $string .= implode( '&nbsp;&nbsp;&nbsp;', $arr );
         $string .= '</div>';
      }

      return $string;
   }

   /*! \brief Builds select-box and submit-button to be able to change 'show-max-rows' */
   function make_show_rows( &$form )
   {
      if ( !$this->Use_Show_Rows )
         return '';

      $rows = $this->Rows_Per_Page;
      $elems = $form->print_insert_select_box(
            $this->Prefix.TFORM_VAL_SHOWROWS, '1', build_maxrows_array($rows), $rows, false);
      $elems.= $form->print_insert_submit_button(
            $this->Prefix.'showrows', T_('Show Rows'));
      return $elems;
   }

   function get_arg( $name, $def='')
   {
      return get_request_arg( $this->Prefix.$name, $def);
      //return arg_stripslashes(@$_GET[ $this->Prefix.$name ]);
   }

/* Must do a reverse arg_stripslashes()
   function set_arg( $name, $value)
   {
      //$_REQUEST[ $this->Prefix.$name ] = $value;
      $_GET[ $this->Prefix.$name ] = $value;
   }
*/

   /*!
    * \brief Attaches SearchFilter to this table.
    * \param $searchfilter SearchFilter-object to attach to table.
    * \param $config array with configuration for table used with filters, \see $ConfigFilters
    */
   function register_filter( &$searchfilter, $config = null )
   {
      $this->Filters = $searchfilter;
      $this->UseFilters = true;
      if ( is_array($config) )
      {
         foreach( $config as $key => $value )
            $this->ConfigFilters[$key] = $value;
      }

      // reset from-row if filters have changed
      if ( $this->UseFilters and ($this->Filters->HashCode != $this->Filters->hashcode()) )
         $this->From_Row = 0;
   }

   /*!
    * \brief Returns non-null merged QuerySQL for all active filters (if $UseFilters is true).
    * signature: QuerySQL get_query()
    */
   function get_query()
   {
      return ( $this->UseFilters ) ? $this->Filters->get_query() : new QuerySQL();
   }

   /*!
    * \brief Adds external-parameters included into URL
    *        (expecting to have 2 interface-methods 'get_hiddens' and 'get_url_parts').
    * \param rp \see RequestParameters
    * \param use_hidden true, if parameters should be included into table-hiddens; default is false
    */
   function add_external_parameters( $rp, $use_hidden = false )
   {
      if( is_object($rp) && method_exists($rp, 'get_hiddens') && method_exists($rp, 'get_url_parts') )
         $this->ext_req_params[]= $rp;
      else
      {
         // expecting object-argument with interfaces get_hiddens() and get_url_parts()
         error('internal_error', 'Table.add_external_parameters.bad_object');
      }
      $this->ext_req_params_use_hidden = $use_hidden;
      $this->cache_curr_extparam = array();
   }

   /*! \brief Retrieves additional / external URL-part for table. */
   function current_extparams_string( $end_sep=false )
   {
      if( !isset($this->cache_curr_extparam[$end_sep]) )
      {
         $url_str = '';
         foreach( $this->ext_req_params as $rp )
         {
            $url_str .= $rp->get_url_parts();
            if ( $url_str != '' && $end_sep )
               $url_str.= URI_AMP;
         }
         $this->cache_curr_extparam[$end_sep] = $url_str;
      }
      return $this->cache_curr_extparam[$end_sep];
   }

} // end of 'Table'

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/classlib_bitset.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/globals.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/filter.php';


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

//caution: provide enough "images/sort$i$c.gif" images $i:[1..n] $c:[a,d]
define('TABLE_MAX_SORT', 2); // 0 to disable the table sorts

define('TABLE_NO_SORT', 0x01); //disable the sort options (except set_default_sort)
define('TABLE_NO_HIDE', 0x02); //disable the add_or_del_column options
define('TABLE_NO_PAGE', 0x04); //disable next_prev_links options
define('TABLE_NO_SIZE', 0x08); //disable the re-sizing (static number of rows) (see use_show_rows)
define('TABLE_ROW_NUM', 0x10); //show row-number on each line
define('TABLE_ROWS_NAVI', 0x20); //calculate found rows and show full page-navigation
define('TABLE_NO_THEAD', 0x40); //disable table-header, need explicitly adding of table-headers (see add_row_thead)

define('CHAR_SHOWFILTER', '+');

define('FCONF_SHOW_TOGGLE_FILTER', 'ShowToggleFilter');    // true to show hide-toggle besides input-fields (default true)
define('FCONF_FILTER_TABLEHEAD',   'FilterTableHead');     // true to show filters above table-rows (default defined as LAYOUT_FILTER_IN_TABLEHEAD)
define('FCONF_EXTERNAL_SUBMITS',   'ExternalSubmits');     // false to include filter-search/reset-submits into table-structure (default false), else omit

define('TFORM_VAL_SHOWROWS', 'maxrows'); // form-elementname for Show-Rows
define('TFORM_ACTION_SHOWROWS', 'showrows'); // form-submit for table-showrows-submit-action
define('TFORM_ACTION_ADDCOL',   'addcol');   // form-submit for table-add/remove-columns-submit-action

class Table
{
   /*! \brief Id to be used in <table id='...'>. */
   private $Id;
   /*! \brief Prefix to be used in _GET to avoid clashes with other tables and variables. */
   private $Prefix;
   /*! \brief Intermediate Form-object, can be created before make_table() to be used for external form-purposes. */
   public $TableForm = null;
   /*! \brief already opened Form class to be used by add_column and filters */
   private $ExternalForm = null;
   /*! \brief optional callback-function expecting two arguments: this Table and table-Form objects. */
   private $ExtendTableFormFunc = null;

   /*!
    * \brief Array of the columns to sort on
    *    $Sort= array( nr1 => +/-nr1, nr2 => +/-nr2, ...);
    *    => +nr : sort the column nr with its default sort order
    *    => -nr : sort the column nr with the reverse order of its default
    *    nr1 will be the main sort, nr2 the second one... up to TABLE_MAX_SORT
    */
   private $Sort = array();
   private $Sortimg = array();

   /*! \brief The page used in links within this table. */
   private $Page;
   /*!
    * \brief ConfigTableColumns-object with column-name and bitset of the
    *        ConfigPages-table in the database to use as column-set for this table.
    */
   private $CfgTableCols;
   /*! \brief The columns that has been removed. */
   private $Removed_Columns = null;
   /*! \brief The number of columns displayed, known after make_tablehead() */
   private $Shown_Columns = null;
   /*! \brief The ID of previous column displayed, of head row at begining */
   private $PrevColId;

   /*! \brief Boolean array used to check if the column should be display. */
   public $Is_Column_Displayed;

   /*!
    * \brief Array describing all tableheads:
    *        [ Nr, TableHead-obj, Sort_String, Desc_Default, Undeletable, attbs(Width) ]
    *    attbs is either:
    *    - an array of (attribut_name => attribut_value) for the column
    *    - a string supposed to be the class of the column
    */
   private $Tableheads = array();
   /*!
    * \brief var to make some asserts to clearly force the use of some functions
    *        before or after the table_head definitions.
    */
   private $Head_closed = 0;
   /*! \brief optional features of the table. */
   private $Mode;

   /*!
    * \brief Array of rows to be diplayed.
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
    *
    * \see make_tablerow()
    */
   private $Tablerows = array();

   /*!
    * \brief The colors to alternate between for the rows.
    * \note only used for folder transparency but CSS incompatible
    */
   private $Row_Colors;

   /*!
    * \brief The row number to start from.
    * \see make_next_prev_links()
    */
   private $From_Row;
   /*!
    * \brief If we are on the last page.
    * \see make_next_prev_links()
    */
   private $Last_Page = true;
   /*!
    * \brief The number of rows to be displayed (normally) on one page (read from player-row TableMaxRows).
    * \see make_next_prev_links()
    */
   private $Rows_Per_Page;
   /*! \brief true, if number of rows to show should be configurable (otherwise use Players.TableMaxRows). */
   private $Use_Show_Rows = true;
   /*! \brief number of FOUND_ROWS by MySQL SQL_CALC_FOUND_ROWS, activated by table-option TABLE_ROWS_NAVI; -1 = not used. */
   private $FoundRows = -1;

   /*! \brief add to row-num for showing TABLE_ROW_NUM; 0 = default, only (row_num + RowNumDiff) > 0 is shown. */
   private $RowNumDiff = 0;

   /*! \brief true to use JavaScript features. */
   private $JavaScript;

   /*!
    * \brief array of objects with external request-parameters, expecting interfaces: get_hiddens() and get_url_parts()
    * \see RequestParameters
    */
   private $ext_req_params = array();
   /*! \brief cache for current_extparams_string() storing: add_sep => string */
   private $cache_curr_extparam = array();

   /*! \brief Profile-handler */
   private $ProfileHandler;

   // filter-stuff

   /*! \brief non-null SearchFilter-instance containing attached filters */
   private $Filters;
   /*! \brief true to show filters within table */
   private $UseFilters = false;
   /*! \brief configuration-array for filters used with table: ( key => config-val ) keys are defined by consts FCONF_... */
   private $ConfigFilters = array( // set defaults
         FCONF_SHOW_TOGGLE_FILTER => true,
         FCONF_FILTER_TABLEHEAD   => LAYOUT_FILTER_IN_TABLEHEAD,
         FCONF_EXTERNAL_SUBMITS   => false,
      );
   /*! \brief The number of filter-columns displayed, known after make_table_filter-func */
   private $Shown_Filters = 0;
   /*! \brief cache for current_filter_string-func storing: ( filter-choice => string ) */
   private $cache_curr_filter = array();


   /*! \brief Constructor. Create a new table and initialize it. */
   public function __construct( $_tableid, $_page, $cfg_tblcols=null, $_prefix='', $_mode=0 )
   {
      global $player_row;

      $this->Id = (string)$_tableid;
      $this->Prefix = (string)$_prefix;
      $this->Mode = (int)$_mode;

      if ( !ALLOW_SQL_CALC_ROWS )
         $this->Mode &= ~TABLE_ROWS_NAVI;

      // prepare for appending URL-parts (ends with either '?' or URI_AMP)
      if ( !is_numeric( strpos( $_page, '?')) )
         $this->Page = $_page . '?'; //end_sep
      elseif ( substr( $_page, -1 ) == '?' || substr( $_page, -strlen(URI_AMP) ) == URI_AMP )
         $this->Page = $_page; //end_sep
      else
         $this->Page = $_page . URI_AMP; //end_sep

      if ( !is_null($cfg_tblcols) && ($cfg_tblcols instanceof ConfigTableColumns) && !($this->Mode & TABLE_NO_HIDE) )
         $this->CfgTableCols = $cfg_tblcols;
      else
      {
         $this->Mode|= TABLE_NO_HIDE;
         $bitset = new BitSet();
         $bitset->reset(1); // set all-bits
         $this->CfgTableCols = new ConfigTableColumns( $player_row['ID'], '', $bitset );
      }

      //{ N.B.: only used for folder transparency but CSS incompatible
      global $table_row_color1, $table_row_color2;
      $this->Row_Colors = array( $table_row_color1, $table_row_color2 );
      //}

      // Profile-handler
      $this->set_profile_handler( null );

      $this->From_Row = (int)$this->get_arg('from_row'); // not in search-profile
      if ( !is_numeric($this->From_Row) || $this->From_Row < 0 )
         $this->From_Row = 0;
      $this->Rows_Per_Page = $player_row['TableMaxRows'];
      $this->JavaScript = is_javascript_enabled();

      // filter-stuff
      $this->Filters = new SearchFilter();

      if ( $this->Mode & TABLE_ROW_NUM )
         $this->add_tablehead( 0, T_('##table'), 'Number', TABLE_ROW_NUM|TABLE_NO_SORT|TABLE_NO_HIDE );
   }//__construct

   public function get_prefix()
   {
      return $this->Prefix;
   }

   /*! \brief Disables certain mode in table-options. */
   public function disable_table_mode( $bitmask )
   {
      if ( $bitmask > 0 )
         $this->Mode &= ~$bitmask;
   }

   /*! \brief Sets external form for this table, $form is passed as reference */
   public function set_externalform( &$form )
   {
      $this->ExternalForm = $form;
      $form->attach_table($this);
   }

   /*!
    * \brief Sets callback function to extend table-form.
    * Function must expect two arguments (call by reference possible):
    * 1. this Table-instance
    * 2. Tables Form-instance
    */
   public function set_extend_table_form_function( $func )
   {
      $this->ExtendTableFormFunc = $func;
   }

   /*! \brief Overwrites standard rows-per-page for this table; copy found_rows if null given. */
   public function set_rows_per_page( $rows )
   {
      $this->Rows_Per_Page = (is_null($rows)) ? $this->FoundRows : $rows;
   }

   /*! \brief Overwrites standard from-rows offset for this table. */
   public function set_from_row( $from_row )
   {
      $this->From_Row = (int)$from_row;
   }

   /*! \brief if false, rows-selection is not shown (table using static number of maxrows); default is true. */
   public function use_show_rows( $use=true )
   {
      $this->Use_Show_Rows = (bool)$use;
   }

   /*! \brief Sets the calculated rows from SQL_CALC_FOUND_ROWS option. */
   public function set_found_rows( $found_rows )
   {
      // handle SQL_CALC_FOUND_ROWS
      $this->FoundRows = -1;
      if ( $this->Mode & TABLE_ROWS_NAVI )
         $this->FoundRows = $found_rows;
   }

   public function get_found_rows()
   {
      return $this->FoundRows;
   }

   public function set_row_num_diff( $row_num_diff )
   {
      $this->RowNumDiff = $row_num_diff;
   }

   /*!
    * \brief Adds a tablehead.
    * \param $nr must be >0 but if higher than BITSET_MAXSIZE or the maxsize of
    *    Table-given ConfigTableColumns, the column will be static.
    *    $nr=0 is reserved for row-number, but can be equipped with additional attributes (see also add_row)
    * \param $description either String with description or else TableHead-instance with more detail-info
    * \param $attbs must be an array of attributs or a class-name for the column (td-element)
    *    default: no attributs or class (i.e. class "Text" left-aligned)
    *    Other known classes defined in CSS, most used are:
    *      ID, User, Date, Enum, Number, NumberC, Image, Button, Mark, MsgDir,
    *      Folder, Rating, Sgf, '' (=default)
    *
    * \param $mode is a combination of TABLE_NO_HIDE and TABLE_NO_SORT
    *    default: TABLE_NO_HIDE|TABLE_NO_SORT
    * \param $sort_xtend is the alias used as default to sort the column
    *    ended by a '+' to sort it in the asc order or a '-' to sort it in the desc order
    *    default: no sort
    */
   public function add_tablehead( $nr, $description, $attbs = null, $mode = null, $sort_xtend = '' )
   {
      if ( $this->Head_closed )
         error('assert', "Table.add_tablehead.closed($nr)");
      if ( $nr < 0 || ( $nr == 0 && !($this->Mode & TABLE_ROW_NUM) ) )
         error('invalid_args', "Table.add_tablehead.bad_col_nr($nr)");
      if ( isset($this->Tableheads[$nr]) )
         error('invalid_args', "Table.add_tablehead.col_exists($nr)");
      if ( !is_array($attbs) )
         $attbs = ( is_string($attbs) && !empty($attbs) ) ? array( 'class' => $attbs) : null;
      if ( !isset($mode) )
         $mode= TABLE_NO_HIDE|TABLE_NO_SORT;
      $sort_xtend= trim($sort_xtend);
      if ( !$sort_xtend )
         $mode|= TABLE_NO_SORT;
      else
         $sort_xtend = $this->_parse_sort_extension( "add_tablehead", $sort_xtend );
      $tableHead = ($description instanceof TableHead) ? $description : new TableHead($description);
      $this->Tableheads[$nr] =
         array( 'Nr' => $nr,
                'Description' => $tableHead,
                'Sort_String' => $sort_xtend,
                'Mode' => $mode,
                'attbs' => $attbs );

      $visible = $this->Is_Column_Displayed[$nr] = $this->is_column_displayed( $nr);
      if ( $nr > 0 && $this->UseFilters ) // fix filter-visibility (especially for static cols)
         $this->Filters->set_visible($nr, $visible);
   }//add_tablehead

   public function get_max_tablehead_nr()
   {
      return max( array_keys( $this->Tableheads ) );
   }

   // \internal
   private function _parse_sort_extension( $dbgmsg, $sort_xtend )
   {
      if ( !preg_match('/^[a-z_][\\w\\.]+[+-]?$/i', $sort_xtend) ) // check db-field-syntax
         error('invalid_args', "Table.{$dbgmsg}._parse_sort_extension({$this->Id},$sort_xtend)");

      if ( !preg_match('/[+-]$/', $sort_xtend) ) // append (default) '+' if missing +/-
         $sort_xtend .= '+';

      return $sort_xtend;
   }//_parse_sort_extension

   /*!
    * \brief records the default order of the table.
    * \param $default_sorts are +/- $column_nbr
    * \note by the way, close and compute the headers definitions.
    * \note works even if TABLE_NO_SORT or TABLE_MAX_SORT==0
    *       (allowing a current_order_string() default usage)
    */
   public function set_default_sort( /* {$default_sort1 {,$default_sort2 [,...]}} */)
   {
      if ( $this->Head_closed )
         error('assert', "Table.set_default_sort.closed({$this->Head_closed})");
      $this->Head_closed = 1;
      $s = array();
      //even if TABLE_NO_SORT or TABLE_MAX_SORT == 0:
      $cnt_args = func_num_args();
      for ( $i=0; $i < $cnt_args; $i++ ) // read defaults from func-args
      {
         $sd = func_get_arg($i);
         if ( is_string($sd) )
            error('assert', "Table.set_default_sort.old_way($sd)");
         if ( $sd = (int)$sd )
            $s[abs($sd)] = $sd;
      }

      // overwrite defaults with URL-sort-args
      if ( TABLE_MAX_SORT > 0 && !($this->Mode & TABLE_NO_SORT) )
      {
         //get the sort parameters from URL
         for ( $i=TABLE_MAX_SORT; $i>0; $i--)
         {
            if ( $sd = (int)$this->get_saved_arg("sort$i") )
            {
               $sk = abs($sd);
               if ( @$this->Is_Column_Displayed[$sk] )
                  $s = array( $sk => $sd ) + $s; // put new key first in array
            }
         }
      }

      $this->Sort = $s;
   }//set_default_sort

   /*!
    * \brief Overwrites table-sort (only one can be called: set_default_sort or set_sort).
    * \see for params see set_default_sort()-func
    * \note IMPORTANT: table column to sort by MUST be visible!!
    */
   public function set_sort( /* {$sort1 {,$sort2 [,...]}} */ )
   {
      if ( $this->Head_closed )
         error('assert', "Table.set_sort.closed({$this->Head_closed})");
      $this->Head_closed = 1;

      $s = array();
      $cnt_args = func_num_args();
      for ( $i=0; $i < $cnt_args; $i++ )
      {
         $sd = func_get_arg($i);
         if ( is_string($sd) )
            error('assert', "Table.set_sort.old_way($sd)");
         if ( $sd = (int)$sd )
         {
            $sk = abs($sd);
            if ( @$this->Is_Column_Displayed[$sd] )
               $s[$sk] = $sd;
         }
      }
      $this->Sort = $s;
   }//set_sort

   /*! \brief Returns 1=true, if column is displayed; 0=false otherwise. */
   public function is_column_displayed( $nr )
   {
      if ( $nr == 0 && ($this->Mode & TABLE_ROW_NUM) )
         return 1;
      if ( $nr < 1 )
         return 0;
      if ( $nr > $this->CfgTableCols->get_maxsize() )
         return 1; // treated as static

      if ( (TABLE_NO_HIDE & @$this->Tableheads[$nr]['Mode']) )
         return 1; // column configured as static

      $bitset = $this->CfgTableCols->get_bitset();
      return $bitset->get_bit($nr);
   }//is_column_displayed

   /*! \brief Returns number of columns added so far to this Table. */
   public function get_column_count()
   {
      return count($this->Tableheads);
   }

   /*! \brief Static func to create row-cell with text (and optional CSS-class). */
   public static function build_row_cell( $text, $class=null )
   {
      if ( is_null($class) )
         return $text;
      else
         return array( 'text' => $text, 'attbs' => array( 'class' => $class ) );
   }

   /*!
    * \brief Add a row to be displayed.
    *        Texts for column #0 (with row-number TABLE_ROW_NUM-mode)
    *        can be formatted differently: %1$s is replaced with row-number.
    * \see $Tablerows
    */
   public function add_row( $row_array )
   {
      $this->Tablerows[]= $row_array;
   }

   /*!
    * \brief Adds one row with headline/title.
    * \see for params see: add_row_one_col()-func
    */
   public function add_row_title( $col_arr, $row_arr_attr=null )
   {
      $row_attr = ( is_null($row_arr_attr) ) ? array() : $row_arr_attr;
      if ( isset($row_arr['extra_class']) )
         $row_attr['extra_class'] .= ' Title';
      else
         $row_attr['extra_class'] = 'Title';
      $this->add_row_one_col( $col_arr, $row_attr );
   }

   /*!
    * \brief Adds table-header row.
    * \param $row_array optional array with attributes: mode1 = table-mode-to-set
    */
   public function add_row_thead( $row_arr_attr=null )
   {
      $row_attr = ( is_null($row_arr_attr) ) ? array() : $row_arr_attr;
      $row_attr['add_thead'] = 1;
      $this->Tablerows[] = $row_attr;
   }

   /*!
    * \brief Adds one row with one column.
    * \param $col_arr_text expect scalar-text or else array with col-attributes 'text' or 'owntd' (+ 'attbs')
    * \param $row_arr_attr optional array row-attributes: 'extra_class'
    */
   public function add_row_one_col( $col_arr_text, $row_arr_attr=null )
   {
      reset($this->Tableheads);
      $first_col = each($this->Tableheads);
      $first_col = $first_col['value']['Nr'];

      $col_arr = ( is_array($col_arr_text) ) ? $col_arr_text : array( 'text' => $col_arr_text );
      if ( !isset($col_arr['attbs']) )
         $col_arr['attbs'] = array();
      if ( !isset($col_arr['attbs']['colspan']) )
         $col_arr['attbs']['colspan'] = $this->get_column_count();

      $row_arr = ( is_null($row_arr_attr) ) ? array() : $row_arr_attr;
      $row_arr[$first_col] = $col_arr;
      $this->Tablerows[] = $row_arr;
   }//add_row_one_col

   public function set_row_extra( $row_index, $arr_extra )
   {
      foreach ( $arr_extra as $key => $value )
      {
         if ( $key == 'extra_class' && isset($this->Tablerows[$row_index][$key]) )
            $this->Tablerows[$row_index][$key] .= ' ' . $value;
         else
            $this->Tablerows[$row_index][$key] = $value;
      }
   }//set_row_extra

   public function make_table_form( $need_form=true, $overwrite=false )
   {
      if ( is_null($this->TableForm) || $overwrite )
      {
         if ( !is_null($this->ExternalForm) )
            $this->TableForm = $this->ExternalForm; // read-only
         elseif ( $need_form || !($this->Mode & TABLE_NO_HIDE) ) // need form for filter, show-rows or add-column
         {
            $table_form = new Form( $this->Prefix.'tableFAC', // Filter/AddColumn-table-form
               clean_url( $this->Page),
               FORM_GET, false, 'FormTableFAC');
            $table_form->attach_table($this);
            $this->TableForm = $table_form;
         }
         else
            $this->TableForm = NULL;
      }

      return $this->TableForm;
   }//make_table_form

   /*! \brief Create a string of the table. */
   public function make_table()
   {
      //if ( !$this->Head_closed )
      //   error('assert', "Table.make_table.!closed({$this->Head_closed})");

      /* Make tablehead */

      $this->Shown_Columns = 0;
      $this->PrevColId = $this->Prefix.'TableHead';
      $head_row = "\n <tr id=\"{$this->PrevColId}\" class=Head>";
      foreach ( $this->Tableheads as $thead )
         $head_row .= $this->make_tablehead( $thead ); //compute Shown_Columns
      $head_row .= "\n </tr>";

      /* Make filter row */

      $filter_row = '';
      $need_form = $this->make_filter_row( $filter_row );

      /* Make table rows */

      $table_rows = '';
      if ( count($this->Tablerows)>0 )
      {
         $c=0;
         $row_num = $this->From_Row; // current start-row-num
         foreach ( $this->Tablerows as $trow )
         {
            if ( isset($trow['add_thead']) )
            {
               if ( count($trow) > 1 )
               {
                  $table_rows .= "\n <tr class=\"Head\">";
                  foreach ( $this->Tableheads as $thead )
                     $table_rows .= $this->make_tablehead( $thead, $trow );
                  $table_rows .= "\n </tr>";
               }
               else
                  $table_rows .= $head_row;
               $c = 0;
            }
            else
            {
               $row_num++;
               $c=($c % LIST_ROWS_MODULO)+1;
               $table_rows .= $this->make_tablerow( $trow, $row_num, "Row$c" );
            }
         }
      }

      if ( $this->Use_Show_Rows && !($this->Mode & TABLE_NO_SIZE) )
         $need_form = true;

      /* Build the table */

      $string = "\n<table id='{$this->Id}Table' class=Table>";
      $string .= $this->make_next_prev_links('T');
      if ( !($this->Mode & TABLE_NO_THEAD) )
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
      if ( $table_rows )
         $string .= $this->make_next_prev_links('B');
      $string .= "\n</table>\n";

      /* Add filter + column form, embedding main-table into table-form */

      // NOTE: avoid nested forms with other forms (because nested hiddens are not working)

      $table_form = $this->make_table_form( $need_form );
      if ( is_null($table_form) )
         unset($table_form);

      if ( $need_form || !($this->Mode & TABLE_NO_HIDE) ) // add-col & filter-submits & show-rows
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
   }//make_table

   /*! \brief Echo the string of the table. */
   public function echo_table()
   {
      echo $this->make_table();
   }

   /*!
    * \brief Passes back filter_row per ref and return true, if form needed because of filters.
    * signature: bool need_form = make_filter_row( &$filter_rows)
    */
   private function make_filter_row( &$filter_rows )
   {
      // make filter-rows
      $this->Shown_Filters = 0;
      $need_form  = false;
      $filter_rows = '';

      if ( $this->UseFilters )
      {
         // make filters
         $row_cells['Filter'] = '';
         foreach ( $this->Tableheads as $thead )
            $row_cells['Filter'] .= $this->make_table_filter( $thead );

         // add error-messages for filters
         if ( $this->Shown_Filters > 0 )
         {
            // build error-messages
            $arr = $this->Filters->get_filter_keys(GETFILTER_ERROR);
            $arr_err = array();
            foreach ( $arr as $id ) {
               $filter = $this->Filters->get_filter($id);
               $syntax = $filter->get_syntax_description();
               $tableHead = $this->Tableheads[$id]['Description'];
               $arr_err[]=
                  "<strong>{$tableHead->description}:</strong> "
                  . '<em>' . T_('Error#filter') . ': ' . $filter->errormsg() . '</em>'
                  . ( ($syntax != '') ? "; $syntax" : '');
            }
            $errormessages = implode( '<br>', $arr_err );
            if ( $errormessages != '' )
               $row_cells['Error'] =
                  "\n  <td class=ErrMsg colspan={$this->Shown_Columns}>$errormessages</td>";

            // build warn-messages
            $arr = $this->Filters->get_filter_keys(GETFILTER_WARNING);
            $arr_warn = array();
            foreach ( $arr as $id ) {
               $filter = $this->Filters->get_filter($id);
               $tableHead = $this->Tableheads[$id]['Description'];
               $arr_warn[]=
                  "<strong>{$tableHead->description}:</strong> "
                  . '<em>' . T_('Warning#filter') . ': ' . $filter->warnmsg() . '</em>';
            }
            $warnmessages = implode( '<br>', $arr_warn );
            if ( $warnmessages != '' )
               $row_cells['Warning'] =
                  "\n  <td class=WarnMsg colspan={$this->Shown_Columns}>$warnmessages</td>";

            $need_form = true;
         }

         // include row only, if there are filters
         if ( $this->ConfigFilters[FCONF_SHOW_TOGGLE_FILTER] || $this->Shown_Filters > 0 )
         {
            $tr_attbs = " id=\"{$this->Prefix}TableFilter\""; // only for 1st entry
            foreach ( $row_cells as $class => $cells )
            {
               if ( $cells )
               {
                  $filter_rows .= "\n <tr$tr_attbs class=\"$class\">";
                  $filter_rows .= $cells;
                  $filter_rows .= "\n </tr>";
                  $tr_attbs = '';
               }
            }
         }
      }

      return $need_form;
   }//make_filter_row

   /*!
    * \brief Returns (locally cached) URL-parts for all filters (even if not used).
    * signature: string querystring = current_filter_string ([ choice = GETFILTER_ALL ])
    */
   public function current_filter_string( $end_sep=false, $filter_choice=GETFILTER_ALL )
   {
      if ( !$this->UseFilters )
         return '';

      if ( !isset($this->cache_curr_filter[$filter_choice]) )
      {
         $trash = null;
         $this->cache_curr_filter[$filter_choice] = $this->Filters->get_url_parts( $trash, $filter_choice );
      }
      $url = $this->cache_curr_filter[$filter_choice];
      if ( $url && $end_sep )
         $url.= URI_AMP;
      return $url;
   }//current_filter_string

   /*!
    * \brief Add or delete a column from the column set and apply it to the database;
    *        Also handle to show or hide filters and parsing max-rows from URL.
    */
   public function add_or_del_column()
   {
      if ( ($i = count($this->Tableheads)) )
      {
         if ( $i > 1 || !isset($this->Tableheads[0]) || !($this->Mode & TABLE_ROW_NUM) )
            error('assert', "Table.add_or_del_column.past_head_start($i)");
      }

      // handle filter-visibility
      $this->Filters->add_or_del_filter();
      $arr_required_cols = $this->Filters->get_required_ids();

      // handle show-rows
      $this->handle_show_rows();

      // handle column-visibility (+ adjust filters); add/del can be array
      $adds = $this->get_arg('add');
      if ( empty($adds) )
         $adds = array();
      elseif ( !is_array($adds) )
         $adds = array($adds);

      $dels = $this->get_arg('del');
      if ( empty($dels) )
         $dels = array();
      elseif ( !is_array($dels) )
         $dels = array($dels);

      $bitset =& $this->CfgTableCols->get_bitset();

      // add columns not visible yet but needed for filtering
      foreach ( $arr_required_cols as $col_nr )
      {
         if ( !$bitset->get_bit($col_nr) && !in_array($col_nr, $adds) )
            $adds[] = $col_nr;
      }

      // add and delete table-columns
      $max_bits = $this->CfgTableCols->get_maxsize();
      $old_bithex = $bitset->get_hex_format();
      foreach ( $adds as $add )
      {
         $add = (int)$add;
         if ( $add > 0 && $add <= $max_bits ) // add col
         {
            $bitset->set_bit( $add );
            $this->Filters->reset_filter($add);
            $this->Filters->set_active($add, true);
         }
         elseif ( $add < 0 ) // add all cols
         {
            $bitset->reset(1); // set all bits
            $this->Filters->reset_filters( GETFILTER_ALL );
            $this->Filters->setall_active(true);
         }
      }
      foreach ( $dels as $del )
      {
         $del = (int)$del;
         if ( $del > 0 && $del <= $max_bits ) // del col
         {
            $bitset->set_bit( $del, 0 );
            $this->Filters->reset_filter($del);
            $this->Filters->set_active($del, false);
         }
         elseif ( $del < 0 ) // del all cols
         {
            $bitset->reset(0);
            $this->Filters->reset_filters( GETFILTER_ALL );
            $this->Filters->setall_active(false);
         }
      }

      // save changes into database in ConfigPages-table
      if ( $this->CfgTableCols->has_col_name() )
      {
         $new_bithex = $bitset->get_hex_format();
         if ( $old_bithex != $new_bithex )
            $this->CfgTableCols->update_config();
      }
   }//add_or_del_column

   /*! \brief Sets Rows_Per_Page (according to changed Show-Rows-form-element or else cookie or players-row). */
   private function handle_show_rows()
   {
      if ( !$this->Use_Show_Rows )
         return;

      $rows = (int)$this->get_saved_arg(TFORM_VAL_SHOWROWS); // nr of rows
      if ( $rows > 0 )
         $this->Rows_Per_Page = get_maxrows( $rows, MAXROWS_PER_PAGE, MAXROWS_PER_PAGE_DEFAULT );
   }//handle_show_rows

   /*! \brief Compute the number of rows from mySQL result. */
   public function compute_show_rows( $num_rows_result)
   {
      if ( $this->Rows_Per_Page > 0 && $num_rows_result > $this->Rows_Per_Page )
      {
         $num_rows_result = $this->Rows_Per_Page;
         $this->Last_Page = false;
      }
      else
         $this->Last_Page = true;

      return $num_rows_result;
   }//compute_show_rows

   /*!
    * \brief Sets the sort-images in the table-header.
    * \note Should be called, if current_order_string() is not used
    * \note To make this work add_tablehead()-calls for table must have a field-sort-order.
    */
   public function make_sort_images()
   {
      if ( !$this->Head_closed )
         error('assert', "Table.make_sort_images.!closed({$this->Head_closed})");
      //if ( TABLE_MAX_SORT <= 0 || $this->Mode & TABLE_NO_SORT ) return;

      //$this->Sortimg = array();
      $str = '';
      $i=0;
      foreach ( $this->Sort as $sk => $sd )
      {
         if ( $i >= TABLE_MAX_SORT )
            break;
         if ( !$sd || !@$this->Is_Column_Displayed[$sk] ) // num && !removed-col
            continue;
         $field = (string)@$this->Tableheads[$sk]['Sort_String'];
         if ( !$field )
            continue;
         ++$i;
         if ( $sd < 0 )
            $str .= strtr( $field, '+-', '-+');
         else
            $str .= $field;
         $c = (substr($str, -1) == '+' ? 'a' : 'd' );
         //alt-attb and part of "images/sort$i$c.gif" $i:[1..n] $c:[a,d] (a=ascending, d=descending)
         $this->Sortimg[$sk] = "$i$c";
      }

      return $str;
   }//make_sort_images

   /*!
    * \brief Retrieve MySQL ORDER BY part from table and sets table-sort-images.
    * \param $sort_xtend is an extended-alias-name
    *        IMPORTANT NOTE:
    *           ensure, that the used db-fields/aliases are present in the overall db-query!!
    *        (ended by a '+' or '-' like in add_tablehead())
    *        added to the current sort list
    * works even if TABLE_NO_SORT or TABLE_MAX_SORT==0
    *   (computing the set_default_sort() datas)
    */
   public function current_order_string( $sort_xtend='')
   {
      if ( !$this->Head_closed )
         error('assert', "Table.current_order_string.!closed({$this->Head_closed})");
      //if ( TABLE_MAX_SORT <= 0 || $this->Mode & TABLE_NO_SORT ) return;

      $str = $this->make_sort_images();
      if ( $sort_xtend )
      {
         if ( $str )
         {
            $sort_xtend = $this->_parse_sort_extension( "current_order_string", $sort_xtend );
            $sort_field = trim($sort_xtend, "-+ ");
            if ( !preg_match( "/\\b{$sort_field}[-+]/i", $str) ) // already in current-order-string ?
               $str .= $sort_xtend;
         }
         else
            $str = $sort_xtend;
      }
      if ( !$str )
         return '';
      $str = str_replace( array('-','+'), array(' DESC,',' ASC,'), $str); // replace all occurences
      return ' ORDER BY '.substr($str,0,-1); // remove trailing ','
   }//current_order_string

   /*! \brief Retrieve mySQL LIMIT part from table. */
   public function current_limit_string()
   {
      if ( $this->Rows_Per_Page <= 0 )
         return '';
      return ' LIMIT ' . $this->From_Row . ',' . ($this->Rows_Per_Page+1) ;
   }

   /*! \brief Retrieve from part of url from table. */
   public function current_from_string( $end_sep=false )
   {
      if ( $this->From_Row > 0 )
      {
         $str = $this->Prefix . 'from_row=' . $this->From_Row;
         if ( $end_sep )
            $str.= URI_AMP;
         return $str;
      }
      return '';
   }//current_from_string

   /*! \brief Retrieves maxrows URL-part. */
   public function current_rows_string( $end_sep=false )
   {
      if ( !$this->Use_Show_Rows )
         return '';

      $str = $this->Prefix . TFORM_VAL_SHOWROWS . '=' . $this->Rows_Per_Page;
      if ( $end_sep )
         $str.= URI_AMP;
      return $str;
   }//current_rows_string

   /*! \brief Retrieve sort part of url from table. */
   public function current_sort_string( $end_sep=false)
   {
      return $this->make_sort_string( 0, $end_sep);
   }

   /*! \brief Retrieve hidden part from table as a form string. */
   public function get_hiddens_string( $filter_choice = GETFILTER_ALL )
   {
      $hiddens= array();
      $this->get_hiddens( $hiddens, $filter_choice );
      return build_hidden( $hiddens);
   }

   /*! \brief Retrieve hidden part from table. */
   public function get_hiddens( &$hiddens)
   {
      $i=0;
      if ( TABLE_MAX_SORT > 0 && !($this->Mode & TABLE_NO_SORT) )
      {
         foreach ( $this->Sort as $sd )
         {
            if ( $i >= TABLE_MAX_SORT )
               break;
            if ( $sd ) // num
            {
               ++$i;
               $hiddens[$this->Prefix . "sort$i"] = $sd;
            }
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

      // include hiddens from external RequestParameters (only on set use_hidden)
      foreach ( $this->ext_req_params as $rp )
         $rp->get_hiddens( $hiddens );
   }//get_hiddens


   /*!
    * \brief Create table-header.
    * \param $arr_opts optional array with additional layout-config for table-header:
    *        mode1 = mode-flags to set
    */
   private function make_tablehead( $tablehead, $arr_opts=null )
   {
      //if ( !$this->Head_closed )
      //   error('assert', "Table.make_tablehead.!closed({$this->Head_closed})");
      $nr = (int)@$tablehead['Nr'];
      if ( $nr < 0 || ($nr == 0 && !($this->Mode && TABLE_ROW_NUM)) )
         return '';
      $is_std = is_null($arr_opts); // std-call counting Shown_Columns

      if ( !$this->Is_Column_Displayed[$nr] )
      {
         if ( $is_std )
            $this->Removed_Columns[ $nr ] = $tablehead['Description']->getDescriptionAddCol();
         return '';
      }

      if ( $is_std )
         $this->Shown_Columns++;
      $mode = $this->Mode | (int)@$tablehead['Mode'];
      if ( is_array($arr_opts) )
         $mode |= (int)@$arr_opts['mode1'];
      if ( $nr == 0 )
         $mode = ($mode | TABLE_ROW_NUM | TABLE_NO_HIDE) & ~TABLE_NO_SORT;

      $curColId = $this->Prefix.'Col'.$nr;
      $string = "\n  <th id=\"$curColId\" scope=col";

      $width = -1;
      $string .= self::parse_table_attbs( $tablehead, $width );
      $string .= '><div>'; //<th> end bracket

      if ( $width >= 0 )
         $string .= button_TD_insert_width($width);

      $common_url =
         $this->current_extparams_string(true)
         . $this->current_rows_string(true)
         . $this->current_filter_string(true)
         ; //end_sep

      // field-sort-link
      $title = @$tablehead['Description']->getDescriptionHtml();
      $field = (string)@$tablehead['Sort_String'];
      $sortimg= (string)@$this->Sortimg[$nr];

      if ( $field && !($mode & TABLE_NO_SORT) )
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
      $string .= span('Header', $hdr);

      $query_del = !($mode & TABLE_NO_HIDE);
      if ( $query_del )
      {
         $query_del = $this->Page //end_sep
            . $this->current_sort_string(true)
            . $common_url; //end_sep

         // add from_row, if filter-value is empty,
         //   or else has a value but has an error (then removing of filter or column doesn't change the page)
         if ( $this->UseFilters && $nr > 0 )
         {
            $filter = $this->Filters->get_filter($nr);
            if ( isset($filter) && ( $filter->is_empty() ^ $filter->has_error() ) )
               $query_del .= $this->current_from_string(true); //end_sep
         }
      }
      if ( $query_del || $sortimg )
      {
         global $base_path;
         if ( $query_del ) //end_sep
         {
            $tool1 = anchor( $query_del . "{$this->Prefix}del=$nr#{$this->PrevColId}",
                  image( $base_path.'images/remove.gif', 'x', '', 'class=Hide'),
                  T_('Hide'));
         }
         else
         {
            $tool1 = image( $base_path.'images/dot.gif', '', '', 'class=Hide');
            $tool1 = "<span>$tool1</span>";
         }

         if ( $sortimg )
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

         $string .= span('Tool', $tool1.$tool2);
      }

      $string .= "</div></th>";

      $this->PrevColId = $curColId;
      return $string;
   }//make_tablehead

   private static function parse_table_attbs( &$arr, &$width )
   {
      $string = '';
      if ( isset($arr['attbs']) )
      {
         $attbs =& $arr['attbs'];
         if ( is_array($attbs) )
         {
            $string .= attb_build($attbs);

            if ( is_numeric( strpos((string)@$attbs['class'],'Button')) )
            {
               $width = max($width, BUTTON_WIDTH);
            }
            if ( isset($attbs['width']) )
            {
               $width = max($width, (int)$attbs['width']);
               unset( $attbs['width']);
            }
         }
      }
      return $string;
   }//parse_table_attbs

   /*!
    * \brief Returns html for filter of passed TableHead
    * \internal
    */
   private function make_table_filter( $thead )
   {
      global $base_path;

      // get filter for column
      $fid = $thead['Nr']; // keep as sep var
      if ( $fid == 0 ) // row-num
         return "\n  <td></td>";
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
         $fprefix = $this->Filters->get_prefix();
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
         $togglestr_show = "<a href=\"$query\" title=" . attb_quote(T_('Show')) . '>' . CHAR_SHOWFILTER ."</a>";
         $togglestr_hide = image( $base_path.'images/remove.gif', 'x', '', 'class=Hide');
         $togglestr_hide = anchor( $query, $togglestr_hide, T_('Hide'));
         $togglestr_hide = span('Tool', $togglestr_hide);
      }

      // check, if filter-field shown or hidden
      if ( !$filter->is_active() )
      {
         if ( $togglestr_show )
            return "\n  <td class=ShowFilter>$togglestr_show</td>";
         return "\n  <td></td>";
      }

      $this->Shown_Filters++;

      // filter input-element
      if ( $filter->has_error() )
         $subclass = ' Error';
      elseif ( $filter->has_warn() )
         $subclass = ' Warning';
      else
         $subclass = '';
      $class = trim( preg_replace("/\\bButton\\b/", '', @$thead['attbs']['class'] . $subclass ) );
      if ( $class )
         $class= " class=\"$class\"";
      $result = "\n  <td$class><div>";
      $result .= $filter->get_input_element( $this->Filters->get_prefix(), $thead );
      if ( !$filter->is_static() )
         $result .= $togglestr_hide;
      $result .= "</div></td>";

      return $result;
   }//make_table_filter


   //{ N.B.: only used for folder transparency but CSS incompatible
   public function blend_next_row_color_hex( $col=false )
   {
      $idx = count($this->Tablerows) % count($this->Row_Colors);
      $rowcol = substr($this->Row_Colors[$idx], 2, 6);
      if ( $col )
         return blend_alpha_hex( $col, $rowcol);
      else
         return $rowcol;
   }
   //}

   // tablerow additional keys:
   //    class (=solely row-class), extra_class (=additional row-class),
   //    extra_row (=row-html), extra_row_class (=class for extra-row)
   // cell of table-row additional keys:
   //    owntd (=own TD-format), text (=content), attbs (=td-attributes)
   private function make_tablerow( $tablerow, $row_num, $rclass='Row1' )
   {
      if ( isset($tablerow['class']) )
         $rclass = $tablerow['class'];
      $extra_class = ( isset($tablerow['extra_class']) ) ? ' '.$tablerow['extra_class'] : '';

      if ( $this->JavaScript )
         $row_start = "\n <tr class=\"$rclass$extra_class%s\" ondblclick=\"toggle_class(this,'$rclass$extra_class','Hil$rclass$extra_class')\">";
      else
         $row_start = "\n <tr class=\"$rclass$extra_class%s\">";

      $colspan= 0;
      $string = sprintf( $row_start, '' );
      foreach ( $this->Tableheads as $thead )
      {
         $nr = $thead['Nr'];
         if ( $this->Is_Column_Displayed[$nr] && --$colspan<=0 )
         {
            $cell = @$tablerow[$nr];
            if ( $nr == 0 ) // col with row-number
               $this->build_cell_rownum( $cell, $row_num );

            if ( is_array($cell) )
            {
               $text= (string)@$cell['owntd'];
               if ( $text )
               {
                  $string.= $text;
                  continue;
               }
               $text= (string)@$cell['text'];
               $attbs= @$cell['attbs'];
               if ( !is_array($attbs) )
                  $attbs= array();
            }
            else
            {
               $text= (string)@$cell;
               $attbs= array();
            }
            $colspan= $attbs['colspan']= max(1,@$attbs['colspan']);
            $class= $attbs['class']= trim(@$thead['attbs']['class'].' '.@$attbs['class']);
            if ( !$class ) unset($attbs['class']);
            $string.= "\n  <td".attb_build($attbs).">";
            $string.= $text."</td>";
         }
      }

      $string .= "\n </tr>";

      if ( isset($tablerow['extra_row']) )
      {
         $extra_class = ( isset($tablerow['extra_row_class']) ) ? ' '.$tablerow['extra_row_class'] : '';
         $string .= sprintf( $row_start, $extra_class );
         if ( $this->Mode & TABLE_ROW_NUM )
            $string .= '<td></td>'; // empty col for row-num
         $string .= $tablerow['extra_row'];
         $string .= "\n </tr>";
      }

      return $string;
   }//make_tablerow

   // build cell with table row-number, expecting
   // - array( 'owntd' => .., 'text' => .., 'attbs' => .. )
   // - text with '%s' or empty text
   private function build_cell_rownum( &$cell, $orig_row_num )
   {
      $row_num = $orig_row_num + $this->RowNumDiff;
      if ( $row_num > 0 )
      {
         if ( is_array($cell) )
         {
            $cellfmt = ( isset($cell['text']) ) ? $cell['text'] : '%s.';
            $cell['text'] = sprintf( $cellfmt, $row_num );
         }
         else
         {
            $cellfmt = ( isset($cell) ) ? $cell : '%s.';
            $cell = sprintf( $cellfmt, $row_num );
         }
      }
   }//build_cell_rownum

   /*!
    * \brief Add next and prev links.
    * \param $id T=top, B=bottom
    *
    * \note on SQL_CALC_FOUND_ROWS:
    * To show the page index: page number / number of pages
    *    mysql> SELECT SQL_CALC_FOUND_ROWS * FROM tbl_name WHERE ... LIMIT 10;
    *    mysql> SELECT FOUND_ROWS();
    *
    * Be aware that using SQL_CALC_FOUND_ROWS and FOUND_ROWS() "disables" LIMIT optimizations,
    * but is faster than doing the same query twice.
    *
    * \see Doc-Reference: http://dev.mysql.com/doc/refman/5.0/en/information-functions.html#function_found-rows
    */
   private function make_next_prev_links($id)
   {
      global $base_path;

      if ( $this->Rows_Per_Page <= 0 || $this->Shown_Columns <= 0 )
         return '';
      $is_onepage = ( $this->From_Row <= 0 && $this->Last_Page );
      if ( $is_onepage && $this->FoundRows < 0 )
         return '';

      // add found-rows only at top-left
      $navi_entries = '';
      if ( $id == 'T' && $this->FoundRows >= 0 )
      {
         $fmt_entries = ($this->FoundRows == 1 ) ? T_('(%s entry)') : T_('(%s entries)');
         $navi_entries = span('NaviInfo', sprintf( $fmt_entries, $this->FoundRows ));
      }

      if ( $is_onepage )
      {
         $paging_left  = $navi_entries;
         $paging_right = '';
      }
      else
      {
         $current_page = floor( $this->From_Row / $this->Rows_Per_Page ) + 1;
         if ( $this->From_Row > ($current_page - 1) * $this->Rows_Per_Page )
            $current_page++;
         if ( $this->FoundRows >= 0 )
         {
            $max_page = floor( ($this->FoundRows + $this->Rows_Per_Page - 1) / $this->Rows_Per_Page );
            if ( $this->From_Row > ($max_page - 1) * $this->Rows_Per_Page )
               $max_page++;
         }
         else
            $max_page = 0;

         $align = 'align=bottom'; //'align=middle'
         $navi_left = ''; // left from page-num
         $navi_right = '';

         $qstr = $this->Page //end_sep
            . $this->current_extparams_string(true)
            . $this->current_sort_string(true)
            . $this->current_rows_string(true)
            . $this->current_filter_string(true)
            ; //end_sep

         if ( $current_page > 2 ) // start-link
         {
            $navi_left .= anchor(
                 $qstr . $this->Prefix . 'from_row=0',
                 image( $base_path.'images/start.gif', '|<=', '', $align),
                 T_('first page') );
         }

         if ( $this->From_Row > 0 ) // prev-link
         {
            if ( $navi_left != '') $navi_left .= MINI_SPACING;
            $navi_left .= anchor(
                 $qstr //end_sep
                 . $this->Prefix . 'from_row=' . ($this->From_Row-$this->Rows_Per_Page)
               , image( $base_path.'images/prev.gif', '<=', '', $align)
               , T_('Prev Page')
               , array( 'accesskey' => ACCKEY_ACT_PREV )
               );
         }

         // current page
         $navi_left .= ' ';
         $navi_page = ( $max_page > 0 )
            ? sprintf( T_('Page %s of %s#tablenavi'), $current_page, $max_page )
            : sprintf( T_('Page %s#tablenavi'), $current_page );

         if ( !$this->Last_Page ) // next-link
         {
            $navi_right .= MINI_SPACING . anchor(
                 $qstr //end_sep
                 . $this->Prefix . 'from_row=' . ($this->From_Row+$this->Rows_Per_Page)
               , image( $base_path.'images/next.gif', '=>', '', $align)
               , T_('Next Page')
               , array( 'accesskey' => ACCKEY_ACT_NEXT )
               );
         }

         if ( $max_page > 0 && $current_page < $max_page - 1 ) // end-link
         {
            $last_page_from_row = floor( $this->FoundRows / $this->Rows_Per_Page) * $this->Rows_Per_Page;
            $navi_right .= MINI_SPACING . anchor(
                 $qstr . $this->Prefix . 'from_row=' . $last_page_from_row,
                 image( $base_path.'images/end.gif', '=>|', '', $align),
                 T_('last page') );
         }

         $paging_left  = $navi_left . $navi_page . $navi_right
            . ( $navi_entries ? SMALL_SPACING . $navi_entries : '');
         $paging_right = $navi_left . $current_page . $navi_right;
      }// !is_onepage

      $string = '';
      $span = floor($this->Shown_Columns/2);
      if ( $span < 2 ) $span = $this->Shown_Columns;
      if ( $span > 0 )
         $string .= "\n  <td class=PagingL" . ($span>1 ? " colspan=$span" : '') . ">$paging_left</td>";

      $span = $this->Shown_Columns - $span;
      if ( $span > 0 )
         $string .= "\n  <td class=PagingR" . ($span>1 ? " colspan=$span" : '') . ">$paging_right</td>";

      if ( $string )
         $string = "\n <tr class=Links$id>\n$string\n </tr>";

      return $string;
   }//make_next_prev_links

   /*!
    * \brief Make sort part of URL
    * \param $add_sort [+-]table-col-id giving sort-direction; or 0 (=don't add col-sort)
    * \note does not modify $this->Sort
    */
   private function make_sort_string( $add_sort=0, $end_sep=false )
   {
      if ( TABLE_MAX_SORT <= 0 || $this->Mode & TABLE_NO_SORT )
         return '';
      $s = $this->Sort;
      if ( $add_sort )
      {
         $key = abs($add_sort);
         if ( isset($s[$key]) ) //if it is in list...
         {
            //reset($s);
            list($sk,$sd) = each($s);
            if ( $key == $sk ) //if it is main sort...
               $add_sort = -$sd; //toggle sort order
            else
               $add_sort = $s[$key]; //move it on main place
         }
         //unset($s[$key]);
         $s = array($key=>$add_sort) + $s; // put new key first in array
      }

      $str = '';
      $i=0;
      foreach ( $s as $sd )
      {
         if ( $i >= TABLE_MAX_SORT )
            break;
         if ( $sd )
         {
            ++$i;
            if ( $str ) $str .= URI_AMP;
            $str .= $this->Prefix . "sort$i=$sd";
         }
      }
      if ( $str && $end_sep )
         $str .= URI_AMP;
      return $str;
   }//make_sort_string

   /*! \brief Adds a form for adding columns. */
   private function make_add_column_form( &$ac_form)
   {
      // add-column-elements
      $ac_string = '';
      if ( !($this->Mode & TABLE_NO_HIDE) && count($this->Removed_Columns) > 0 )
      {
         split_url($this->Page, $page, $args);
         foreach ( $args as $key => $value ) {
            $ac_form->add_hidden( $key, $value);
         }
         //Note: asort on translated strings
         asort($this->Removed_Columns);
         $this->Removed_Columns[ 0 ] = '';
         $this->Removed_Columns[ -1 ] = T_('All columns');
         $ac_string = $ac_form->print_insert_select_box(
               $this->Prefix.'add', '1', $this->Removed_Columns, 0, false);
         $ac_string.= $ac_form->print_insert_submit_button(
               $this->Prefix.TFORM_ACTION_ADDCOL, T_('Add Column'));
      }

      // add filter-submits in add-column-row if not in table-head and need form for filter
      $f_string = '';
      if ( $this->UseFilters && !$this->ConfigFilters[FCONF_EXTERNAL_SUBMITS] )
         $f_string = implode( '', $this->Filters->get_submit_elements( $ac_form ) );

      // add show-rows elements in add-column-row
      $r_string = $this->make_show_rows( $ac_form );

      $ex_string = '';
      if ( $this->ExtendTableFormFunc && function_exists($this->ExtendTableFormFunc) )
         $ex_string = call_user_func_array( $this->ExtendTableFormFunc, array( &$this, &$ac_form ) ); // call vars by ref

      $string = false;
      if ( $ac_string || $f_string || $r_string || $ex_string )
      {
         $arr = array();
         if ( $f_string )  $arr[]= $f_string;  // filter-submits
         if ( $r_string )  $arr[]= $r_string;  // show-rows
         if ( $ac_string ) $arr[]= $ac_string; // add-cols
         if ( $ex_string ) $arr[]= $ex_string; // custom-extension

         $string = "<div id='{$this->Prefix}tableFAC'>";
         $string .= implode( '&nbsp;&nbsp;&nbsp;', $arr );
         $string .= '</div>';
      }

      return $string;
   }//make_add_column_form

   /*! \brief Builds select-box and submit-button to be able to change 'show-max-rows' */
   private function make_show_rows( &$form )
   {
      if ( !$this->Use_Show_Rows )
         return '';

      $rows = $this->Rows_Per_Page;
      $elems = $form->print_insert_select_box(
            $this->Prefix.TFORM_VAL_SHOWROWS, '1', build_maxrows_array($rows), $rows, false);
      $elems.= $form->print_insert_submit_button(
            $this->Prefix.TFORM_ACTION_SHOWROWS, T_('Show Rows'));
      return $elems;
   }//make_show_rows

   /*! \brief Returns (potentially profile-saved) arg or from _REQUEST-var with optional default-value. */
   private function get_saved_arg( $name, $def='' )
   {
      $fname = $this->Prefix . $name;
      if ( $this->ProfileHandler )
      {
         if ( $this->ProfileHandler->need_reset() || $this->ProfileHandler->need_clear() )
            $value = ''; // reset = clear
         else
            $value = $this->ProfileHandler->get_arg( $fname ); // get from saved profile
      }
      else
         $value = NULL; // no profile-handler
      return (is_null($value)) ? get_request_arg( $fname, $def) : $value;
   }//get_saved_arg

   /*! \brief Returns arg from _REQUEST-var with optional default-value. */
   private function get_arg( $name, $def='')
   {
      return get_request_arg( $this->Prefix . $name, $def);
   }

   /*! \brief Returns true, if table-action show-rows or add/del-column has been chosen. */
   public function was_table_submit_action()
   {
      return $this->get_arg(TFORM_ACTION_SHOWROWS) || $this->get_arg(TFORM_ACTION_ADDCOL);
   }

   /*! \brief Sets ProfileHandler managing search-profiles; use NULL to clear it. */
   public function set_profile_handler( $prof_handler )
   {
      $this->ProfileHandler = $prof_handler;

      // register standard arg-names for Table
      if ( $this->ProfileHandler )
      {
         $args = array();
         $args[] = $this->Prefix . TFORM_VAL_SHOWROWS;  // max-rows
         for ( $i=1; $i <= TABLE_MAX_SORT; $i++ )
            $args[] = $this->Prefix . "sort$i";   // see set_default_sort-func

         $this->ProfileHandler->register_argnames( $args );
         $this->ProfileHandler->register_regex_save_args(
            sprintf( "%ssort\d+|%s", $this->Prefix, $args[0] ));

         // add link-vars
         $this->add_external_parameters( $this->ProfileHandler->get_request_params(), false );
      }
   }//set_profile_handler

   /*!
    * \brief Attaches SearchFilter to this table.
    * \param $searchfilter SearchFilter-object to attach to table.
    * \param $config array with configuration for table used with filters, \see $ConfigFilters
    */
   public function register_filter( &$searchfilter, $config = null )
   {
      $this->Filters = &$searchfilter;
      $this->UseFilters = true;
      if ( is_array($config) )
      {
         foreach ( $config as $key => $value )
            $this->ConfigFilters[$key] = $value;
      }

      // reset from-row if filters have changed
      if ( $this->UseFilters && $this->Filters->has_filters_changed() )
         $this->From_Row = 0;
   }//register_filter

   /*!
    * \brief Returns non-null merged QuerySQL for all active filters (if $UseFilters is true).
    * signature: QuerySQL get_query()
    */
   public function get_query()
   {
      $qsql = ( $this->UseFilters ) ? $this->Filters->get_query() : new QuerySQL();
      if ( $this->Mode & TABLE_ROWS_NAVI )
         $qsql->add_part( SQLP_OPTS, SQLOPT_CALC_ROWS );
      return $qsql;
   }//get_query

   /*!
    * \brief Adds external-parameters included into URL
    *        (expecting to have 3 interface-methods 'get_hiddens', 'get_url_parts' and 'use_hidden').
    * \param rp \see RequestParameters
    * \param use_hidden true, if parameters should be included into table-hiddens;
    *        state is stored in $rp-structure;
    *        giving no default is with intent!
    */
   public function add_external_parameters( $rp, $use_hidden )
   {
      if ( is_object($rp) && method_exists($rp, 'get_hiddens') && method_exists($rp, 'get_url_parts') )
      {
         $rp->use_hidden( $use_hidden );
         $this->ext_req_params[]= $rp;
      }
      else
      {
         // expecting object-argument with interfaces get_hiddens() and get_url_parts()
         error('internal_error', 'Table.add_external_parameters.bad_object');
      }
      $this->cache_curr_extparam = array();
   }//add_external_parameters

   /*! \brief Retrieves additional / external URL-part for table. */
   public function current_extparams_string( $end_sep=false )
   {
      if ( !isset($this->cache_curr_extparam[$end_sep]) )
      {
         $url_str = '';
         foreach ( $this->ext_req_params as $rp )
         {
            $url_str .= $rp->get_url_parts();
            if ( $url_str != '' && $end_sep )
               $url_str.= URI_AMP;
         }
         $this->cache_curr_extparam[$end_sep] = $url_str;
      }
      return $this->cache_curr_extparam[$end_sep];
   }//current_extparams_string

} // end of 'Table'



/*!
 * \class TableHead
 *
 * \brief Class to represent table-head (text or image).
 */
class TableHead
{
   public $description;
   private $image_url;
   private $image_alt;
   private $image_title;
   private $image_attbs;

   /*! \brief Constructs TableHead-instance. */
   public function __construct( $description, $image_url=null, $image_title=null, $image_attbs=null )
   {
      $this->description = $description;
      $this->image_url = $image_url;
      $this->image_alt = $description;
      $this->image_title = (is_null($image_title)) ? $description : $image_title;
      $this->image_attbs = $image_attbs;
   }

   public function isImage()
   {
      return !(is_null($this->image_url));
   }

   public function getDescriptionHtml()
   {
      global $base_path;
      return ($this->isImage())
         ? image( $base_path . $this->image_url, $this->image_alt, $this->image_title, $this->image_attbs )
         : $this->description;
   }

   public function getDescriptionAddCol()
   {
      return ( $this->isImage() ) ? "({$this->description})" : $this->description;
   }

} // end of 'TableHead'

?>

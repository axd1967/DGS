<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Common";

require_once( "include/form_functions.php" );

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

//~(0) is negative (PHP) and database field is unsigned: INT(10) unsigned NOT NULL
define('ALL_COLUMNS', 0x7fffffff); //=2147483647


class Table
{
   /*! \privatesection */

   /*! \brief Id to be used in <table id='...'>. */
   var $Id;
   /*! \brief Prefix to be used in _GET to avoid clashes with other tables and variables. */
   var $Prefix;
   /*! \brief already opened Form class to be used by add_column */
   var $ExternalForm;

   /*! \brief The primary column to sort on. */
   var $Sort1;
   /*! \brief Whether to search descending or ascending for the primary sort. */
   var $Desc1;
   /*! \brief The secondary column to sort on. */
   var $Sort2;
   /*! \brief Whether to search descending or ascending for the secondary sort. */
   var $Desc2;

   /*! \brief Which columns in the set that is visible. */
   var $Column_set;
   /*! \brief The page used in links within this table. */
   var $Page;
   /*! \brief The column of the Player table in the database to use as column_set.
    * \see $Column_set */
   var $Player_Column;
   /*! \brief If true, the table will not be able to add or delete columns. */
   var $Static_Columns;
   /*! \brief The columns that has been removed. */
   var $Removed_Columns;
   /*! \brief The number of columns displayed, known after make_tablehead() */
   var $Shown_Columns;
   /*! \brief The ID of previous column displayed, of head row at begining */
   var $PrevColId;

   /*! \brief Boolean array used to check if the column should be display. */
   var $Is_Column_Displayed;

   /*! \brief Array describing all tableheads. */
   var $Tableheads;

   /*! \brief Array of rows to be diplayed.
    * Each row should consist of an array like this:
    * array( $column_nr1 => "Rowstring1",
    *        $column_nr2 => "Rowstring2" );
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
   /*! \brief The number of rows to be displayed (normally) on one page.
    * \see make_next_prev_links() */
   var $Rows_Per_Page;

   /*! \publicsection */

   /*! \brief Constructor. Create a new table and initialize it. */
   function Table( $_tableid, $_page,
                   $_player_column = '',
                   $_prefix = ''
                 )
      {
         global $RowsPerPage, $player_row;

         $this->ExternalForm = NULL;
         $this->Removed_Columns = NULL;
         $this->Shown_Columns = 0;
         $this->Tableheads = array();
         $this->Tablerows = array();

         $this->Id = $_tableid;
         $this->Prefix = $_prefix;

         $this->Page = $_page;
         if( strstr( $this->Page, '?' ) )
         {
            if( !(substr( $this->Page, -1 ) == '?') and
                !(substr( $this->Page, -strlen(URI_AMP) ) == URI_AMP) )
            {
               $this->Page .= URI_AMP;
            }
         }
         else
         {
            $this->Page .= '?';
         }

         $this->Player_Column = $_player_column;
         if( empty($this->Player_Column) )
         {
            $this->Static_Columns = true;
            $this->Column_set = ALL_COLUMNS;
         }
         else
         {
            $this->Static_Columns = false;
            if( !isset($player_row[ $this->Player_Column ]) )
               $this->Column_set = ALL_COLUMNS;
            else
               $this->Column_set = (int)@$player_row[ $this->Player_Column ];
         }

         $this->Sort1 = (string) $this->get_arg('sort1');
         $this->Desc1 = (bool) $this->get_arg('desc1');
         $this->Sort2 = (string) $this->get_arg('sort2');
         $this->Desc2 = (bool) $this->get_arg('desc2');

         //Simply remove the mySQL disturbing chars (Sort? must be a column name)
         $tmp = array( '\\', '\'', '\"', ';');
         $this->Sort1 = str_replace( $tmp, '', $this->Sort1 );
         $this->Sort2 = str_replace( $tmp, '', $this->Sort2 );


         //{ N.B.: only used for folder transparency but CSS incompatible
         global $table_row_color1, $table_row_color2;
         $this->Row_Colors = array( $table_row_color1, $table_row_color2 );
         //}

         $this->From_Row = (int)$this->get_arg('from_row');
         if( !is_numeric($this->From_Row) or $this->From_Row < 0 )
            $this->From_Row = 0;
         $this->Last_Page = true;
         $this->Rows_Per_Page = $RowsPerPage;
      }

   /*! \brief Add a tablehead. */
   function add_tablehead( $nr,
                           $description,
                           $sort_string = NULL,
                           $desc_default = false,
                           $undeletable = false,
                           $attbs = NULL )
      {
         $this->Tableheads[$nr] =
            array( 'Nr' => $nr,
                   'Description' => $description,
                   'Sort_String' => $sort_string,
                   'Desc_Default' => $desc_default,
                   'Undeletable' => $undeletable,
                   'attbs' => $attbs );

         $this->Is_Column_Displayed[$nr] = $this->is_column_displayed( $nr);
      }

   /*! \brief Check if column is displayed. */
   function is_column_displayed( $nr )
   {
      return $this->Static_Columns or
             $this->Tableheads[$nr]['Undeletable'] or
             ( $nr < 1 ? 1 : (1 << ($nr-1)) & $this->Column_set );
   }

   /*! \brief Add a row to be displayed.
    * \see $Tablerows
    */
   function add_row( $row_array )
   {
      array_push( $this->Tablerows, $row_array );
   }

   /*! \brief Create a string of the table. */
   function make_table()
   {
      /* Make tablehead */

      $this->Shown_Columns = 0;
      $this->PrevColId = $this->Prefix.'TableHead';
      $head_row = " <tr id=\"{$this->PrevColId}\" class=Head>\n";
      foreach( $this->Tableheads as $thead )
      {
         $head_row .= $this->make_tablehead( $thead );
      }
      $head_row .= " </tr>\n";

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

      $next_prev_row= $this->make_next_prev_links();

      $string = "<table id='{$this->Id}Table' class=Table>\n";
      $string .= $next_prev_row;
      $string .= $head_row;
      $string .= $table_rows;
      if( $table_rows )
         $string .= $next_prev_row;
      $string .= "</table>\n";

      /* Add column form */

      if( !$this->Static_Columns )
      {
         if( $this->ExternalForm )
         {
            $ac_form = $this->ExternalForm;
            if( ($add_string=$this->make_add_column_form( $ac_form)) )
            {
               $ac_form->attach_table($this);
               $string .= $add_string;
            }
         }
         else
         {
            if( substr( $this->Page, -1 ) == '?' )
            {
               $page = substr( $this->Page, 0, -1);
            }
            else if( substr( $this->Page, -strlen(URI_AMP) ) == URI_AMP)
            {
               $page = substr( $this->Page, 0, -strlen(URI_AMP));
            }
            else
            {
               $page = $this->Page;
            }
            $ac_form = new Form( $this->Prefix.'addcol',
               $page."#{$this->Prefix}TableAC", FORM_GET, false, 'formTable');

            if( ($add_string=$this->make_add_column_form( $ac_form)) )
            {
               $ac_form->attach_table($this);
               $string =
                     // '<div class=addcolout>'.
                     // '<table><tr><td>' .
                       $ac_form->print_start_default()
                     //. '<div class=addcolin>'
                     . $string
                     //. '</div>'
                     . $add_string
                     . $ac_form->print_end()
                     //. '</td></tr></table>'
                     //. '</div>'
                     ;
            }
            unset($ac_form);
         }
      }

      return $string;
   }

   /*! \brief Echo the string of the table. */
   function echo_table()
   {
      echo $this->make_table();
   }

   /*! \brief Return the attributs of a warning cellule. */
   function warning_cell_attb( $title='')
   {
      $str= ' class=Warning';
      if( $title ) $str.= ' title=' . attb_quote($title);
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

   /*! \brief Return the cell part of a button with anchor. */
   function button_TD_anchor( $href, $text='')
   {
      //$text= (string)$text;
      return "<td class=Button>" .
         "<a class=Button href=\"$href\">" .
         $text . "</a></td>";
   }

   /*! \brief Return a stratagem to force a minimal column width.
    * Must be inserted before a cell inner text at least one time for a column.
    */
   function button_TD_width_insert()
   {
      global $base_path, $button_width;
      //a stratagem to force a minimal column width.
      //must be changed when, a day, the CSS min-width
      // command will work fine with every browser.
      //dot.gif is 1x1 transparent image.
      return "<img class=MinWidth src='{$base_path}images/dot.gif' width=$button_width height=1 alt=''><br>";
   }


   /*! \brief Add or delete a column from the column set and apply it to the database. */
   function add_or_del_column()
   {
      global $player_row;

      $del = (int)$this->get_arg('del');
      $add = (int)$this->get_arg('add');

      if( $del or $add )
      {
         if( $add > 0 )
         {
            $this->Column_set |= (1 << ($add-1));
         }
         else if( $add < 0 )
         {
            $this->Column_set = ALL_COLUMNS;
         }

         if( $del > 0 )
         {
            $this->Column_set &= ~(1 << ($del-1));
         }
         else if( $del < 0 )
         {
            $this->Column_set = 0;
         }

         if( !empty($this->Player_Column)
            && isset($player_row)
            && isset($player_row["ID"])
            && $player_row["ID"]>0
            )
         {
            $query = "UPDATE Players" .
               " SET " . $this->Player_Column . "=" . $this->Column_set .
               " WHERE ID=" . $player_row["ID"];

            mysql_query($query)
               or error('mysql_query_failed','table_columns.add_or_del_column');
         }
      }
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
   }

   /*! \brief Retrieve mySQL ORDER BY part from table. */
   function set_default_sort( $sort1, $desc1, $sort2='', $desc2=0)
   {
      if( !$this->Sort1 )
      {
         $this->Sort1 = (string) $sort1;
         $this->Desc1 = (bool) $desc1;
         $this->Sort2 = (string) $sort2;
         $this->Desc2 = (bool) $desc2;
         return;
      }

      if( !$this->Sort2 )
      {
         if( strcasecmp( $this->Sort1, $sort1) )
         {
            $this->Sort2 = (string) $sort1; //shifted
            $this->Desc2 = (bool) $desc1;
         }
         else
         {
            $this->Sort2 = (string) $sort2;
            $this->Desc2 = (bool) $desc2;
         }
      }
   }

   /*! \brief Retrieve mySQL ORDER BY part from table. */
   function current_order_string()
   {
      if(!$this->Sort1 )
         return '';
      $order = str_replace( URI_ORDER_CHAR
         , ( $this->Desc1 ? ' DESC,' : ',' )
         , $this->Sort1.URI_ORDER_CHAR);
      if( $this->Sort2 )
      {
         $order.= str_replace( URI_ORDER_CHAR
            , ( $this->Desc2 ? ' DESC,' : ',' )
            , $this->Sort2.URI_ORDER_CHAR);
      }
      $order= substr($order,0,-1);
      //could do also: $order= 'ORDER BY '.$order;
      return $order;
   }

   /*! \brief Retrieve mySQL LIMIT part from table. */
   function current_limit_string()
   {
      if ( $this->Rows_Per_Page <= 0 )
         return '';
      return "LIMIT " . $this->From_Row . "," . ($this->Rows_Per_Page+1) ;
   }

   /*! \brief Retrieve from part of url from table. */
   function current_from_string( $add_sep=false )
   {
      if( $this->From_Row>0 )
      {
         $str = $this->Prefix . 'from_row=' . $this->From_Row;
         if ($add_sep)
            $str .= URI_AMP ;
         return $str;
      }
      return '';
   }

   /*! \brief Retrieve sort part of url from table. */
   function current_sort_string( $add_sep=false )
   {
      return $this->make_sort_string( $this->Sort1,
                                      $this->Desc1,
                                      $this->Sort2,
                                      $this->Desc2,
                                      $add_sep );
   }

   /*! \brief Retrieve hidden part from table as a form string. */
   function get_hiddens_string()
   {
      $hiddens= array();
      $this->get_hiddens( $hiddens);
      $str = '';
      foreach( $hiddens as $key => $val )
      {
         $str.= "<input type=\"hidden\" name=\"$key\" value=\"$val\">\n";
      }
      return $str;
   }

   /*! \brief Retrieve hidden part from table. */
   function get_hiddens( &$hiddens)
   {
      if ($this->Sort1)
      {
         $hiddens[$this->Prefix . 'sort1'] = $this->Sort1;
         if ($this->Desc1)
            $hiddens[$this->Prefix . 'desc1'] = $this->Desc1;
         if ($this->Sort2) {
            $hiddens[$this->Prefix . 'sort2'] = $this->Sort2;
            if ($this->Desc1)
               $hiddens[$this->Prefix . 'desc2'] = $this->Desc2;
         }
      }
      if ( $this->Rows_Per_Page > 0 && $this->From_Row > 0 )
      {
         $hiddens[$this->Prefix . 'from_row'] = $this->From_Row;
      }
   }


   /*! \privatesection */

   function make_tablehead( $tablehead )
   {
      $nr = $tablehead['Nr'];

      if( !$this->Is_Column_Displayed[$nr] )
      {
         $this->Removed_Columns[ $nr ] = $tablehead['Description'];
         return '';
      }

      $this->Shown_Columns++;

      $curColId = $this->Prefix.'Col'.$nr;
      $string = "  <th id=\"$curColId\" scope=col";

      if( !is_null( $tablehead['attbs'] ) )
      {
         if( is_array($tablehead['attbs']) )
         {
            $string .= attb_build($tablehead['attbs']);
         }
         else
         {
            $string .= " width=\"{$tablehead['attbs']}\"";
         }
      }

      $string .= '>';

      $title = $tablehead['Description'];
      $csort = $tablehead['Sort_String'];

      if( $csort )
      {
         $string .= "<a href=\"" . $this->Page;

         if( $csort == $this->Sort1 )
         { //Click on main column: just toggle its order
            $string .= $this->make_sort_string( $this->Sort1,
                                                !$this->Desc1,
                                                $this->Sort2,
                                                $this->Desc2 );
         }
         else
         if( $csort == $this->Sort2 )
         { //Click on second column: just swap the columns
            $string .= $this->make_sort_string( $this->Sort2,
                                                $this->Desc2,
                                                $this->Sort1,
                                                $this->Desc1 );
         }
         else
         { //Click on a new column: just push it
            $string .= $this->make_sort_string( $csort,
                                                $tablehead['Desc_Default'],
                                                $this->Sort1,
                                                $this->Desc1);
         }

         $string .= "#$curColId\" title=" . attb_quote(T_('Sort')) . '>' .
            $title . '</a>';
      }
      else
      {
         $string .= $title;
      }

      if( !$tablehead['Undeletable'] && !$this->Static_Columns)
      {
         $string .=
            '<sup><a href="' .$this->Page.$this->current_sort_string( true) .
            "{$this->Prefix}del=$nr#{$this->PrevColId}\"" .
            " title=" . attb_quote(T_('Hide')) . '>' .
            "x</a></sup>";
      }

      $string .= "</th>\n";

      $this->PrevColId = $curColId;
      return $string;
   }

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

      $string = " <tr class=$rclass";
      if( ALLOW_JSCRIPT && !@$tablerow['noclick'] )
      { //onClick onmousedown ondblclick
         $string.= " ondblclick=\"javascript:this.className=((this.className=='highlight')?'$rclass':'highlight');\"";
      }
      $string.= ">";

      foreach( $this->Tableheads as $th )
      {
         if( $this->Is_Column_Displayed[ $th['Nr'] ] )
         {
            if( ($tmp=@$tablerow[ $th['Nr'] ]) )
               $string .= "\n  ".$tmp;
         }
      }

      $string .= "\n </tr>\n";

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
      The first query is longer that without the SQL_CALC_FOUND_ROWS
       but if the ORDER BY is on a not indexed column, this is small.
      MySQL >= 4.0.0. Refs at:
       http://dev.mysql.com/doc/refman/4.1/en/information-functions.html
   */
   function make_next_prev_links()
   {
      if ( $this->Rows_Per_Page <= 0 or $this->Shown_Columns <= 0
            or !( $this->From_Row > 0 or !$this->Last_Page ) )
         return '';

      $string = 'align=bottom'; //'align=middle'
      $button = '';

      if( $this->From_Row > 0 )
         $button.= anchor(
              $this->Page . $this->current_sort_string( true )
              . $this->Prefix . 'from_row=' . ($this->From_Row-$this->Rows_Per_Page)
            , image( 'images/prev.gif', '<=', '', $string)
            , T_("prev page")
            , array('accesskey' => '<')
            );

      $button.= '&nbsp;'.round($this->From_Row/$this->Rows_Per_Page+1).'&nbsp;';

      if( !$this->Last_Page )
         $button.= anchor(
              $this->Page . $this->current_sort_string( true )
              . $this->Prefix . 'from_row=' . ($this->From_Row+$this->Rows_Per_Page)
            , image( 'images/next.gif', '=>', '', $string)
            , T_("next page")
            , array('accesskey' => '>')
            );


      $string = '';

      $span = floor($this->Shown_Columns/2);
      if( $span < 2 ) $span = $this->Shown_Columns;
      if( $span > 0 )
         $string.= '  <td align=left'
           . ($span>1 ? " colspan=$span" : '') . ">$button</td>\n";

      $span = $this->Shown_Columns - $span;
      if( $span > 0 )
         $string.= '  <td align=right' 
           . ($span>1 ? " colspan=$span" : '') . ">$button</td>\n";

      if( $string )
         $string = " <tr>\n$string</tr>\n";

      return $string;
   }

   /*! \brief Make sort part of url. */
   function make_sort_string( $sortA, $descA, $sortB, $descB, $add_sep=false )
   {
      if( $sortA )
      {
         $sort_string = $this->Prefix . "sort1=$sortA" .
            ( $descA ? URI_AMP . $this->Prefix . 'desc1=1' : '' );
         if( $sortB )
         {
            $sort_string .= URI_AMP . $this->Prefix . "sort2=$sortB" .
               ( $descB ? URI_AMP . $this->Prefix . 'desc2=1' : '' );
         }
         if ($add_sep)
            $sort_string .= URI_AMP ;
         return $sort_string;
      }
      return '';
   }

   /*! \brief Adds a form for adding columns. */
   function make_add_column_form( &$ac_form)
   {
      if( $this->Static_Columns or
          count($this->Removed_Columns) < 1 )
      {
         return false;
      }

      split_url($this->Page,$page,$args);
      foreach( $args as $key => $value ) {
         $ac_form->add_hidden( $key, $value);
      }

      asort($this->Removed_Columns);
      $this->Removed_Columns[ 0 ] = '';
      $this->Removed_Columns[ -1 ] = T_('All columns');
      $string = $ac_form->print_insert_select_box(
               $this->Prefix.'add', '1', $this->Removed_Columns, '', false);
      $string.= $ac_form->print_insert_submit_button(
               $this->Prefix.'addcol', T_('Add Column'));

      $string = "<div id='{$this->Prefix}TableAC'>".$string.'</div>';
      return $string;
   }

   function get_arg( $name)
   {
      //return get_request_arg( $this->Prefix.$name);
      return arg_stripslashes(@$_GET[ $this->Prefix.$name ]);
   }

/*
   function set_arg( $name, $value)
   {
      $_GET[ $this->Prefix.$name ] = $value;
   }
*/
}
?>

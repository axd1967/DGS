<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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
   /*! \brief Prefix to be used in _GET to avoid clashes with other tables and variables. */
   var $Prefix;
   /*! \brief If true, the table will not be able to add or delete columns. */
   var $Static_Columns;
   /*! \brief The columns that has been removed. */
   var $Removed_Columns;

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
   function Table( $_page,
                   $_player_column = '',
                   $_prefix = ''
                 )
      {
         global $table_row_color1, $table_row_color2, $RowsPerPage, $player_row;

         $this->Removed_Columns = NULL;
         $this->Tableheads = array();
         $this->Tablerows = array();

         $this->Page = $_page;

         if( strstr( $this->Page, '?' ) )
         {
            if( !(substr( $this->Page, -1 ) == '?') and
                !(substr( $this->Page, -1 ) == '&') )
            {
               $this->Page .= '&';
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
            $this->Column_set = $player_row[ $this->Player_Column ];
         }

         $this->Prefix = $_prefix;

         $this->Sort1 = @$_GET[ $this->Prefix . 'sort1' ];
         $this->Desc1 = @$_GET[ $this->Prefix . 'desc1' ];
         $this->Sort2 = @$_GET[ $this->Prefix . 'sort2' ];
         $this->Desc2 = @$_GET[ $this->Prefix . 'desc2' ];

         $this->Row_Colors = array( $table_row_color1, $table_row_color2 );

         $this->From_Row = @$_GET[ $this->Prefix . 'from_row' ];
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
                           $width = NULL )
      {
         $this->Tableheads[$nr] =
            array( 'Nr' => $nr,
                   'Description' => $description,
                   'Sort_String' => $sort_string,
                   'Desc_Default' => $desc_default,
                   'Undeletable' => $undeletable,
                   'Width' => $width );

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
         global $table_head_color;

         $string = '';

         /* Start of the table */

         $string = '<a name="' . $this->Prefix . 'tbl">'
                 . "<table border=0 cellspacing=0 cellpadding=3 align=center>\n";
         $string.= $this->make_next_prev_links();

         /* Make tableheads */

         $string .= " <tr bgcolor=$table_head_color>\n";
         foreach( $this->Tableheads as $thead )
            {
               $string .= $this->make_tablehead( $thead );
            }
         $string .= " </tr>\n";

         /* Make table rows */

         foreach( $this->Tablerows as $trow )
            {
               $string .= $this->make_tablerow( $trow );
            }

         /* End of the table */

         $string .= $this->make_next_prev_links();
         $tmp = $this->make_add_column_form();
         if( !$tmp )
           $tmp = '&nbsp;';
         $string .= ' <tr><td colspan=99 align=right>'
              . '<a name="' . $this->Prefix . 'tblac">'. $tmp . '</a>'
              . "</td></tr>\n";

         $string .= "</table></a>\n"; //</a> close the name="tbl" one
         return $string;
      }

   /*! \brief Echo the string of the table. */
   function echo_table()
      {
         echo $this->make_table();
      }

   /*! \privatesection */

   function make_tablehead( $tablehead )
      {
         $nr = $tablehead['Nr'];

         if( !$this->Is_Column_Displayed[$nr] )
         {
            $this->Removed_Columns[ $nr ] = $tablehead['Description'];
            return "";
         }

         $string = "  <th nowrap valign=\"bottom\"";

         if( !is_null( $tablehead['Width'] ) )
         {
            $string .= " width=\"" . $tablehead['Width'] . "\"";
         }

         $string .= ">";

         if( $tablehead['Sort_String'] )
         {
            $string .= "<a href=\"" . $this->Page;

            if( $tablehead['Sort_String'] == $this->Sort1 )
            { //Click on main column: just toggle its order
               $string .= $this->make_sort_string( $this->Sort1,
                                                   !$this->Desc1,
                                                   $this->Sort2,
                                                   $this->Desc2 );
            }
            else
            if( $tablehead['Sort_String'] == $this->Sort2 )
            { //Click on second column: just swap the columns
               $string .= $this->make_sort_string( $this->Sort2,
                                                   $this->Desc2,
                                                   $this->Sort1,
                                                   $this->Desc1 );
            }
            else
            { //Click on a new column: just push it
               $string .= $this->make_sort_string( $tablehead['Sort_String'],
                                                   $tablehead['Desc_Default'],
                                                   $this->Sort1,
                                                   $this->Desc1);
            }

            $string .= '#' . $this->Prefix . 'tbl">' .
               "<font color=\"black\">" . $tablehead['Description'] .
               "</font></a>";
         }
         else
         {
            $string .= "<font color=\"black\">" . $tablehead['Description'] . "</font>";
         }

         if( !$tablehead['Undeletable'] && !$this->Static_Columns)
         {
            $string .=
               "<a href=\"" . $this->Page .
               $this->current_sort_string( true ) .
               $this->Prefix . "del=" . $nr . 
               '#' . $this->Prefix . 'tbl">' .
               "<sup><font size=\"-1\" color=\"red\">x</font></sup></a>";
         }

         $string .= "</th>\n";

         return $string;
      }

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

   function make_tablerow( $tablerow )
      {
         list(, $bgcolor) = each( $this->Row_Colors );
         if( !$bgcolor )
         {
            reset( $this->Row_Colors );
            list(, $bgcolor) = each( $this->Row_Colors );
         }

         if( isset($tablerow['BG_Color']) )
         {
            $string = " <tr bgcolor=" . $tablerow['BG_Color'] .">\n";
         }
         else
         {
            $string = " <tr bgcolor=$bgcolor>\n";
         }

         foreach( $this->Tableheads as $th )
            {
               if( $this->Is_Column_Displayed[ $th['Nr'] ] )
               {
                  $string .= @$tablerow[ $th['Nr'] ];
               }
            }

         $string .= " </tr>\n";

         return $string;
      }


   /*! \brief Add next and prev links. */
   function make_next_prev_links()
      {
         if ( $this->Rows_Per_Page <= 0 )
            return '';

         $string = "";

         if( $this->From_Row > 0 )
         {
            $string .= "  <td><a href=\"" . $this->Page .
               $this->current_sort_string( true ) .
               $this->Prefix . "from_row=" . ($this->From_Row-$this->Rows_Per_Page) .
               "\">" . "&lt;-- " . T_("prev page") . "</a></td>\n";
         }

         if( !$this->Last_Page )
         {
            $string .= "  <td align=\"right\" colspan=99><a href=\"" . $this->Page .
               $this->current_sort_string( true ) .
               $this->Prefix . "from_row=" . ($this->From_Row+$this->Rows_Per_Page) .
               "\">" . T_("next page") . " --&gt;" . "</a></td>\n";
         }

         if( !empty( $string ) )
         {
            $string = " <tr>\n $string </tr>\n";
         }

         return $string;
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

   /*! \brief Retrieve mySQL LIMIT part from table. */
   function current_limit_string()
      {
         if ( $this->Rows_Per_Page <= 0 )
            return '';
         return "LIMIT " . $this->From_Row . "," . ($this->Rows_Per_Page+1) ;
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

   /*! \brief Retrieve hidden part from table. */
   function echo_hiddens()
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

   /*! \brief Retrieve sort part of url from table. */
   function current_sort_string( $add_sep=false )
      {
         return $this->make_sort_string( $this->Sort1,
                                         $this->Desc1,
                                         $this->Sort2,
                                         $this->Desc2,
                                         $add_sep );
      }

   /*! \brief Make sort part of url. */
   function make_sort_string( $sortA, $descA, $sortB, $descB, $add_sep=false )
      {
         if( $sortA )
         {
            $sort_string = $this->Prefix . "sort1=$sortA" .
               ( $descA ? '&' . $this->Prefix . 'desc1=1' : '' );
            if( $sortB )
            {
               $sort_string .= '&' . $this->Prefix . "sort2=$sortB" .
                  ( $descB ? '&' . $this->Prefix . 'desc2=1' : '' );
            }
            if ($add_sep)
               $sort_string .= '&' ;
            return $sort_string;
         }
         return '';
      }

   /*! \brief Add or delete a column from the column set and apply it to the database. */
   function add_or_del_column()
      {
         global $player_row;

         $del = @$_GET[ $this->Prefix . 'del' ];
         $add = @$_GET[ $this->Prefix . 'add' ];

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

            if( !empty($this->Player_Column) )
            {
               $query = "UPDATE Players" .
                  " SET " . $this->Player_Column . "=" . $this->Column_set .
                  " WHERE ID=" . $player_row["ID"];

               mysql_query($query);
            }
         }
      }

   /*! \brief Adds a form for adding columns. */
   function make_add_column_form()
      {
         if( $this->Static_Columns or
             count($this->Removed_Columns) < 1 )
         {
            return "";
         }

         $page_split = split( '[?&]', $this->Page );
         list( , $page ) = each( $page_split );
         $form_array = array();
         while( list( , $query ) = each( $page_split ) )
         {
            if( !empty( $query ) )
            {
               list( $key, $value ) = explode( "=", $query );
               array_push( $form_array, 'HIDDEN', $key, $value );
            }
         }

         $this->Removed_Columns[ 0 ] = '';
         $this->Removed_Columns[ -1 ] = T_('All columns');
         asort($this->Removed_Columns);
         array_push( $form_array,
                     'SELECTBOX', $this->Prefix . 'add', 1,
                     $this->Removed_Columns, '', false,
                     'SUBMITBUTTON', 'action', T_('Add Column') );
         $ac_form = new Form( 'add_column_form', $page . '#' . $this->Prefix . 'tblac', FORM_GET );
         $ac_form->attach_table($this);
         $ac_form->add_row( $form_array );
         return $ac_form->get_form_string();
      }
}

?>

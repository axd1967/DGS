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

$TranslateGroups[] = "Common";

require_once("form_functions.php");

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

class Table
{
   /*! \privatesection */

   /*! \brief The primary column to sort on. */
   var $Sort1;
   /*! \brief Whether to search descending or ascending for the primary sort. */
   var $Desc1;
   /*! \brief The secondary column to sort on. */
   var $Dort2;
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

   /*! \brief Array describing all tableheads. */
   var $Tableheads;

   /*! \brief Array of rows to be diplayed.
    * Each row should consist of an array like this:
    * array( $column_nr1 => "Rowstring1",
    *        $column_nr2 => "Rowstring2" );
    *
    * If the string doesn't begin with "<td", then, "<td>" will be added to the output.
    * If the string doesn't end with "</td>", then, "</td>" will be added to the output.
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
                   $_prefix = '',
                   $_sc = false )
      {
         global $table_row_color1, $table_row_color2, $RowsPerPage, $player_row;

         $this->Removed_Columns = array('');
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
            $this->Column_set = 255;
         }
         else
         {
            $this->Column_set = $player_row[ $this->Player_Column ];
         }

         $this->Prefix = $_prefix;
         $this->Static_Columns = $_sc;

         $this->Sort1 = $_GET[ $this->Prefix . 'sort1' ];
         $this->Desc1 = $_GET[ $this->Prefix . 'desc1' ];
         $this->Sort2 = $_GET[ $this->Prefix . 'sort2' ];
         $this->Desc2 = $_GET[ $this->Prefix . 'desc2' ];

         $this->Row_Colors = array( $table_row_color1, $table_row_color2 );

         $this->From_Row = $_GET[ $this->Prefix . 'from_row' ];
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
         array_push( $this->Tableheads,
                     array( 'Nr' => $nr,
                            'Description' => $description,
                            'Sort_String' => $sort_string,
                            'Desc_Default' => $desc_default,
                            'Undeletable' => $undeletable,
                            'Width' => $width ) );
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

         $string = "<table border=0 cellspacing=0 cellpadding=3 align=center>\n";
         $string .= $this->make_next_prev_links();

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
         $string .= ' <tr><td colspan=20 align=right>' .
            $this->make_add_column_form() .
            "</td></tr>\n</table>\n";

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
         if( $tablehead['Nr'] > 0 )
         {
            $col_pos = 1 << ($tablehead['Nr'] - 1);
         }
         else
         {
            $col_pos = 0;
         }

         if( !$this->Static_Columns and
             !$tablehead['Undeletable'] and
             $tablehead['Nr'] > 0 and
             !($col_pos & $this->Column_set) )
         {
            $this->Removed_Columns[ $tablehead['Nr'] ] = $tablehead['Description'];
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
            {
               $string .= $this->make_sort_string( $this->Sort1,
                                                   !$this->Desc1,
                                                   $this->Sort2,
                                                   !$this->Desc2 );
            }
            else
            {
               $string .= $this->make_sort_string( $tablehead['Sort_String'],
                                                   $tablehead['Desc_Default'],
                                                   $this->Sort1,
                                                   $this->Desc1 xor $tablehead['Desc_Default'] );
            }

            $string .= "\"><font color=\"black\">" . $tablehead['Description'] .
               "</font></a>";
         }
         else
         {
            $string .= "<font color=\"black\">" . $tablehead['Description'] . "</font>";
         }

         if( !$tablehead['Undeletable'] )
         {
            $string .=
               "<a href=\"" . $this->Page .
               ($this->Sort1 ? $this->make_sort_string( $this->Sort1,
                                                        $this->Desc1,
                                                        $this->Sort2,
                                                        $this->Desc2 ) . '&' : '') .
               $this->Prefix . "del=" . $tablehead['Nr'] . "\">" .
               "<sup><font size=\"-1\" color=\"red\">x</font></sup></a>";
         }

         $string .= "</th>\n";

         return $string;
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
               if( !in_array( $th['Description'], $this->Removed_Columns ) )
               {
                  $string .= $this->make_tablecell( $tablerow[ $th['Nr'] ] );
               }
            }

         $string .= " </tr>\n";

         return $string;
      }

   /*! \brief Modify the cellstring to make sure it behaves well. */
   function make_tablecell( $cellstring )
      {
         if( empty( $cellstring ) )
         {
            return '';
         }

         $string = '  ';
         if( substr( $cellstring, 0, 3 ) != "<td" )
         {
            $string .= "<td>";
         }

         $string .= $cellstring;

         if( substr( $cellstring, -5 ) != "</td>" )
         {
            $string .= "</td>";
         }

         return $string . "\n";
      }

   /*! \brief Add next and prev links. */
   function make_next_prev_links()
      {
         $string = "";

         if( $$this->From_Row > 0 )
         {
            $string .= "  <td><a href=\"" . $this->Page .
               $this->Prefix . "from_row=" . ($from_row-$RowsPerPage) .
               $this->make_sort_string( $this->Sort1,
                                        $this->Desc1,
                                        $this->Sort2,
                                        $this->Desc2 ) . "\">" .
               "<-- " . T_("prev page") . "</a></td>\n";

         }

         if( !$this->Last_Page )
         {
            $string .= "  <td align=\"right\" colspan=20><a href=\"" . $this->Page .
               $this->Prefix . "from_row=" . ($from_row+$RowsPerPage) .
               $this->make_sort_string( $this->Sort1,
                                        $this->Desc1,
                                        $this->Sort2,
                                        $this->Desc2 ) . "\">" .
               T_("next page") . " -->" . "</a></td>\n";

         }

         if( !empty( $string ) )
         {
            $string = " <tr>\n $string </tr>\n";
         }

         return $string;
      }

   /*! \brief Make sort part of url. */
   function make_sort_string( $sortA, $descA, $sortB, $descB )
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
         }

         return $sort_string;
      }

   /*! \brief Add or delete a column from the column set and apply it to the database. */
   function add_or_del_column()
      {
         global $player_row;

         $del = $_GET[ $this->Prefix . 'del' ];
         $add = $_GET[ $this->Prefix . 'add' ];

         if( $del or $add )
         {
            if( $add )
            {
               $this->Column_set |= 1 << ($add-1);
            }
            if( $del )
            {
               $this->Column_set &= ~(1 << ($del-1));
            }

            $query = "UPDATE Players" .
               " SET " . $this->Player_Column . "=" . $this->Column_set .
               " WHERE ID=" . $player_row["ID"];

            mysql_query($query);
         }

      }

   /*! \brief Adds a form for adding columns. */
   function make_add_column_form()
      {
         if( $this->Static_Columns or
             count($this->Removed_Columns) <= 1 )
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

         array_push( $form_array,
                     'SELECTBOX', $this->Prefix . 'add', 1,
                     $this->Removed_Columns, '', false,
                     'SUBMITBUTTON', 'action', T_('Add Column') );
         $ac_form = new Form( 'add_column_form', $page, FORM_GET );
         $ac_form->add_row( $form_array );
         return $ac_form->get_form_string();
      }
}

?>

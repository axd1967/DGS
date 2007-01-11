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


/*!
 * \file table_infos.php
 *
 * \brief Functions for creating info tables.
 */

/*!
 * \class Table_info
 *
 * \brief Class to ease the creation of info tables.
 */


class Table_info
{
   /*! \privatesection */

   /*! \brief Id to be used in <table id='...'>. */
   var $Id;

   /*! \brief Array of rows to be diplayed.
    * Each row should consist of an array like this:
    * array( $column_nr1 => assoc( header, info, rattbs, hattbs, iattbs),
    *        $column_nr2 => assoc( rawheader, rawinfo, rattbs, hattbs, iattbs));
    * with (all optional):
    *    header    = the "HTML safe" left column text.
    *    info      = the "HTML safe" right column text.
    *    rawheader = the unsafe left column text (will be healed).
    *    rawinfo   = the unsafe right column text (will be healed).
    *    hattbs    = the attributs of left cellule.
    *    iattbs    = the attributs of right cellule.
    *    rattbs    = the attributs of the row.
    */
   var $Tablerows;

   /*! \publicsection */

   /*! \brief Constructor. Create a new table and initialize it. */
   function Table_info( $_tableid)
   {
      $this->Tablerows = array();

      $this->Id = $_tableid;
   }

   /*! \brief Add a row to be displayed.
    * \see $Tablerows
    */
   function add_row( $row_array )
   {
      array_push( $this->Tablerows, $row_array );
   }

   /*! \brief Create a string of the table. */
   function make_table( $tattbs='class=infos')
   {
      /* Start of the table */

      $tattbs = attb_build($tattbs);
      $string = "<table id='{$this->Id}_infos'$tattbs>\n";

      /* Make table rows */

      if( count($this->Tablerows)>0 )
      {
         ksort($this->Tablerows);
         $c=0;
         foreach( $this->Tablerows as $trow )
         {
            $c=($c%4)+1;
            $string .= $this->make_tablerow( $trow, "class=row$c" );
         }
      }

      /* End of the table */

      $string .= "</table>\n";
      return $string;
   }

   /*! \brief Echo the string of the table. */
   function echo_table()
   {
      echo $this->make_table();
   }

   /*! \privatesection */

   function warning_cell_attb( $title='')
   {
      $str= ' class=warning';
      if ($title) $str.= ' title="' . $title . '"';
      return $str;
   }

   function make_tablerow( $tablerow, $rattbs='class=row1')
   {
      if( isset($tablerow['rattbs']) )
         $rattbs = $tablerow['rattbs'];
      $rattbs = attb_build($rattbs);

      $string = " <tr$rattbs";
      /*
      if( ALLOW_JSCRIPT )
      {
         $string.= " ondblclick=\"javascript:this.className=((this.className=='highlight')?'$rclass':'highlight');\"";
      }
      */
      $string.= ">\n  ";

      if( isset($tablerow['hattbs']) )
         $rattbs = attb_build($tablerow['hattbs']);
      else
         $rattbs = ' class=header';

      $string.= "<td$rattbs>";

      if( isset($tablerow['rawheader']) )
         $rattbs = make_html_safe($tablerow['rawheader'],INFO_HTML);
      else
         $rattbs = @$tablerow['header'];

      $string.= $rattbs.'</td>';

      if( isset($tablerow['iattbs']) )
         $rattbs = attb_build($tablerow['iattbs']);
      else
         $rattbs = ' class=info';

      $string.= "<td$rattbs>";

      if( isset($tablerow['rawinfo']) )
         $rattbs = make_html_safe($tablerow['rawinfo'],INFO_HTML);
      else
         $rattbs = @$tablerow['info'];

      $string.= $rattbs."</td>\n </tr>\n";

      return $string;
   }

}

?>
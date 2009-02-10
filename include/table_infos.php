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

//$TranslateGroups[] = "Common";


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
    *   array( $column_nr1 => assoc( {s}name, {s}info, rattb, nattb, iattb),
    *          $column_nr2 => assoc( sname, info, rattb, iattb)),
    *          $column_nr3 => assoc( {s}caption, cattb, rattb));
    *
    * with:
    *      - all optional,
    *      - "unsafe" exclude "safe"
    *      - {s}caption exclude ({s}name, {s}info)):
    *
    *    rattbs    = the attributs of the row.
    *
    *    sname     = the "HTML safe" left cellule text.
    *    sinfo     = the "HTML safe" right cellule text.
    *                Specify an array of values for multiple columns.
    *    name      = the unsafe left cellule text (will be healed).
    *    info      = the unsafe right cellule text (will be healed).
    *                Specify an array of values for multiple columns.
    *    nattb     = the attributs of left cellule.
    *    iattb     = the attributs of right cellule.
    *
    *    scaption  = the "HTML safe" extended cellule text.
    *    caption   = the unsafe extended cellule text (will be healed).
    *    cattbs    = the attributs of extended cellule.
    */
   var $Tablerows;

   /*! \brief Number of columns (dependent on info/sinfo). */
   var $Columns;


   /*! \publicsection */

   /*! \brief Constructor. Create a new table and initialize it. */
   function Table_info( $_tableid)
   {
      $this->Tablerows = array();
      $this->Id = $_tableid;
      $this->Columns = 2; // default column-width
   }

   /*! \brief Add a row to be displayed.
    * \see $Tablerows
    */
   function add_row( $row_array )
   {
      $this->Tablerows[]= $row_array;

      $this->check_cols( @$row_array['sinfo'] );
      $this->check_cols( @$row_array['info'] );
   }

   /*! \brief Add an caption row to be displayed.
    *  assuming that the string is not yet "HTML safe"
    * \see $Tablerows
    */
   function add_caption( $capt)
   {
      $this->Tablerows[]= array(
            'caption' => $capt,
            'cattb' => 'class=Caption'
            );
   }

   /*! \brief Add an caption row to be displayed.
    *  assuming that the string IS "HTML safe"
    * \see $Tablerows
    */
   function add_scaption( $scapt='')
   {
      $this->Tablerows[]= array(
            'scaption' => $scapt,
            'cattb' => 'class=Caption'
            );
   }

   /*! \brief Add an info row to be displayed.
    *  assuming that the strings are not yet "HTML safe"
    * \see $Tablerows
    */
   function add_info( $name='', $info='', $iattb='', $nattb='' )
   {
      $this->Tablerows[]= array(
            'name' => $name,
            'info' => $info,
            'iattb' => $iattb,
            'nattb' => $nattb,
            );
      $this->check_cols( $info );
   }

   /*! \brief Add an info row to be displayed.
    *  assuming that the strings ARE "HTML safe"
    * \see $Tablerows
    */
   function add_sinfo( $sname='', $sinfo='', $iattb='', $nattb='' )
   {
      $this->Tablerows[]= array(
            'sname' => $sname,
            'sinfo' => $sinfo,
            'iattb' => $iattb,
            'nattb' => $nattb,
            );
      $this->check_cols( $sinfo );
   }

   /*! \brief Checks if passed arg is array, and adjust column-count accordingly if needed. */
   function check_cols( $arr )
   {
      if( is_array($arr) )
         $this->Columns = max( $this->Columns, count($arr) + 1 );
   }

   /*! \brief Create a string of the table. */
   function make_table( $tattbs='class=Infos')
   {
      /* Start of the table */

      $tattbs = attb_build($tattbs);
      $string = "<table id='{$this->Id}Infos'$tattbs>\n";
      $string.= "<colgroup><col class=ColRubric><col class=ColInfo></colgroup>\n";

      /* Make table rows */

      if( count($this->Tablerows)>0 )
      {
         ksort($this->Tablerows);
         $c=0;
         foreach( $this->Tablerows as $trow )
         {
            $c=($c % LIST_ROWS_MODULO)+1;
            $string .= $this->make_tablerow( $trow, "class=Row$c", "Row$c" );
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

   function make_tablerow( $tablerow, $rattbs='', $rclass='')
   {
      if( isset($tablerow['rattb']) )
      {
         //$rattbs = $tablerow['rattb']; //overwrite $rattbs
         $rattbs = attb_merge( attb_parse($rattbs), attb_parse($tablerow['rattb']), false);
         if( $rattbs['class'] != $rclass )
            $rclass = '';
      }
      $rattbs = attb_build($rattbs);

      $string = " <tr$rattbs";
/*
      if( ALLOW_JAVASCRIPT && $rclass )
      {
         //$string.= " ondblclick=\"javascript:this.className=((this.className=='highlight')?'$rclass':'highlight');\"";
         $string.= " ondblclick=\"javascript:this.className=((this.className=='$rclass')?'Hil$rclass':'$rclass');\"";
      }
*/
      $string.= ">\n  ";

      if( isset($tablerow['caption'])
         || isset($tablerow['scaption'])
         )
      {
         $string.= $this->add_cell( $tablerow,
            'caption', 'scaption', 'cattb', '<th colspan='.($this->Columns).'$>', '</th>');
      }
      else
      {
         $string.= $this->add_cell( $tablerow,
            'name', 'sname', 'nattb', '<td$>', '</td>', array('class'=>'Rubric'));
         $string.= $this->add_cell( $tablerow,
            'info', 'sinfo', 'iattb', '<td$>', '</td>');
      }

      $string.= "\n </tr>\n";

      return $string;
   }

   // Adds table-cell(s): one cell if $unsafe or $safe are non-arrays, multi-cells on arrays
   // param $unsafe: has prio over $safe
   // param $attbs: char '$' replaced with $start
   function add_cell( $tablerow, $unsafe, $safe, $attbs, $start, $stop, $arymrg=NULL)
   {
      $attbs = @$tablerow[$attbs];
      if( isset($arymrg) )
         $attbs = attb_merge( $arymrg, attb_parse($attbs));
      $attbs = attb_build( @$attbs);
      $start_str = str_replace('$',$attbs,$start);

      $out = array();
      if( isset($tablerow[$unsafe]) )
      {
         if( is_array($tablerow[$unsafe]) )
         {
            foreach( $tablerow[$unsafe] as $colval )
               $out[] = $start_str . make_html_safe($colval,INFO_HTML) . $stop;
         }
         else
            $out[] = $start_str . make_html_safe($tablerow[$unsafe],INFO_HTML) . $stop;
      }
      elseif( isset($tablerow[$safe]) )
      {
         if( is_array($tablerow[$safe]) )
         {
            foreach( $tablerow[$safe] as $colval )
               $out[] = $start_str . $colval . $stop;
         }
         else
            $out[] = $start_str . $tablerow[$safe] . $stop;
      }
      return implode('', $out);
   }

} //class Table_info

?>

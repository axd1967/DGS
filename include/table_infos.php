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
    *    name      = the unsafe left cellule text (will be healed).
    *    info      = the unsafe right cellule text (will be healed).
    *    nattbs    = the attributs of left cellule.
    *    iattbs    = the attributs of right cellule.
    *
    *    scaption  = the "HTML safe" extended cellule text.
    *    caption   = the unsafe extended cellule text (will be healed).
    *    cattbs    = the attributs of extended cellule.
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

   /*! \brief Add an caption row to be displayed.
    *  assuming that the string is not yet "HTML safe"
    * \see $Tablerows
    */
   function add_caption( $capt)
   {
      array_push( $this->Tablerows, array(
            'caption' => $capt,
            'cattb' => 'class=Caption'
            ) );
   }

   /*! \brief Add an caption row to be displayed.
    *  assuming that the string IS "HTML safe"
    * \see $Tablerows
    */
   function add_scaption( $scapt='')
   {
      array_push( $this->Tablerows, array(
            'scaption' => $scapt,
            'cattb' => 'class=Caption'
            ) );
   }

   /*! \brief Add an info row to be displayed.
    *  assuming that the strings are not yet "HTML safe"
    * \see $Tablerows
    */
   function add_info( $name='', $info='', $warningtitle='')
   {
      array_push( $this->Tablerows, array(
            'name' => $name,
            'info' => $info,
            'iattb' => ( !$warningtitle ? '' :
                  $this->warning_cell_attb( $warningtitle) )
            ) );
   }

   /*! \brief Add an info row to be displayed.
    *  assuming that the strings ARE "HTML safe"
    * \see $Tablerows
    */
   function add_sinfo( $sname='', $sinfo='', $warningtitle='')
   {
      array_push( $this->Tablerows, array(
            'sname' => $sname,
            'sinfo' => $sinfo,
            'iattb' => ( !$warningtitle ? '' :
                  $this->warning_cell_attb( $warningtitle) )
            ) );
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

   /*! \brief Return the attributs of a warning cellule. */
   function warning_cell_attb( $title='')
   {
      $str= ' class=Warning';
      if( $title ) $str.= ' title=' . attb_quote($title);
      return $str;
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
      if( ALLOW_JSCRIPT && $rclass )
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
            'caption', 'scaption', 'cattb', '<th colspan=2$>', '</th>');
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

   function add_cell( $tablerow, $unsafe, $safe, $attbs, $start, $stop, $arymrg=NULL)
   {
      $attbs = @$tablerow[$attbs];
      if( isset($arymrg) )
         $attbs = attb_merge( $arymrg, attb_parse($attbs));
      $attbs = attb_build( @$attbs);

      if( isset($tablerow[$unsafe]) )
         $str = make_html_safe( $tablerow[$unsafe], INFO_HTML);
      else
         $str = @$tablerow[$safe];

      return str_replace('$',$attbs,$start).$str.$stop;
   }

} //class Table_info

?>

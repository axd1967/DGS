<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( 'include/std_functions.php' );



/*!
 * \file gui_functions.php
 *
 * \brief Collection of GUI-related functions.
 */


/*!
 * \brief Return the attributes of a warning cellule with class and title-attributes:
 *        - return as map with keys 'class' and 'title', if return_array-arg is true
 *        - otherwise return as string.
 */
function warning_cell_attb( $title='', $return_array=false )
{
   if( $return_array )
   {
      $result = array('class' => 'Warning');
      if( $title ) $result['title'] = $title;
   }
   else
   {
      $result = ' class=Warning';
      if( $title ) $result .= ' title=' . attb_quote($title);
   }
   return $result;
}

/*! \brief Return the global style part of a table with buttons. */
function button_style( $button_nr=0)
{
   global $base_path, $button_max, $buttoncolors, $buttonfiles;

   if( !is_numeric($button_nr) || $button_nr < 0 || $button_nr > $button_max  )
      $button_nr = 0;

   return
     "table.Table a.Button {" .
       " color: {$buttoncolors[$button_nr]};" .
     "}\n" .
     "table.Table td.Button {" .
       " background-image: url({$base_path}images/{$buttonfiles[$button_nr]});" .
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
   if( !is_numeric($width) )
      $width = BUTTON_WIDTH;
   return insert_width( $width );
}

?>

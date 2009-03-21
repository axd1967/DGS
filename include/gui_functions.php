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


if( !defined('SMALL_SPACING') )
   define('SMALL_SPACING', '&nbsp;&nbsp;&nbsp;');
if( !defined('MINI_SPACING') )
   define('MINI_SPACING', '&nbsp;');


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
function button_TD_anchor( $href, $text='', $title='' )
{
   //return "\n  <td class=Button><a class=Button href=\"$href\">$text</a></td>";
   $titlestr = ($title != '') ? " title=\"$title\"" : '';
   return "<a class=Button href=\"$href\"$titlestr>$text</a>";
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

function TD_button( $title, $href, $isrc, $ialt)
{
   //image( $src, $alt, $title='', $attbs='', $height=-1, $width=-1)
   $str = image( $isrc, $ialt, $title);
   //anchor( $href, $text, $title='', $attbs='')
   $str = anchor( $href, $str);
   $str = "<td class=Button>$str</td>\n";
   return $str;
}

/*! \brief Prints notes in formatted table if there are notes. */
function echo_notes( $table_id, $title, $notes )
{
   if( !is_array($notes) || count($notes) == 0 )
      return;

   echo "<br><br>\n",
      "<table id=\"{$table_id}\">\n",
      "<tr><th>" . make_html_safe($title, 'line') . "</th></tr>\n",
      "<tr><td><ul>";
   foreach( $notes as $note )
   {
      if( is_null($note) || (string)$note === '' )
         echo "<p></p>\n";
      else
         echo "  <li>" . make_htmL_safe($note, 'line') . "\n";
   }
   echo "</ul>\n",
      "</td></tr></table>\n";
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

$TranslateGroups[] = "Games";

require_once 'include/std_classes.php';
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/db/shape.php';
require_once 'include/classlib_game.php';
require_once 'include/classlib_goban.php';
require_once 'include/goban_handler_gfx.php';


 /*!
  * \class ShapeControl
  *
  * \brief Controller-Class to handle shape-game-stuff.
  */

// lazy-init in ShapeControl::get..Text()-funcs
global $ARR_GLOBALS_SHAPE; //PHP5
$ARR_GLOBALS_SHAPE = array();

class ShapeControl
{
   // ------------ static functions ----------------------------

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   function getFlagsText( $flag=null )
   {
      global $ARR_GLOBALS_SHAPE;

      // lazy-init of texts
      $key = 'FLAGS';
      if( !isset($ARR_GLOBALS_SHAPE[$key]) )
      {
         $arr = array();
         $arr[SHAPE_FLAG_PLAYCOLOR_W] = T_('W-First#SHP_flag');
         $ARR_GLOBALS_SHAPE[$key] = $arr;
      }

      if( is_null($flag) )
         return $ARR_GLOBALS_SHAPE[$key];
      if( !isset($ARR_GLOBALS_SHAPE[$key][$flag]) )
         error('invalid_args', "ShapeControl::getFlagsText($flag,$short)");
      return $ARR_GLOBALS_SHAPE[$key][$flag];
   }//getFlagsText

   /*! \brief Returns text-representation of shape-flags. */
   function formatFlags( $flags, $zero_val='', $intersect_flags=0, $class=null )
   {
      $check_flags = ( $intersect_flags > 0 ) ? $flags & $intersect_flags : $flags;

      $arr = array();
      $arr_flags = ShapeControl::getFlagsText();
      foreach( $arr_flags as $flag => $flagtext )
      {
         if( $check_flags & $flag )
            $arr[] = ($class) ? span($class, $flagtext) : $flagtext;
      }
      return (count($arr)) ? implode(', ', $arr) : $zero_val;
   }//formatFlags

   /*! \brief Returns HTML for given Shape-object parsing Snapshot. */
   function build_view_shape( $shape, $stone_size=null )
   {
      $arr_xy = GameSnapshot::parse_stones_snapshot( $shape->Size, $shape->Snapshot, GOBS_BLACK, GOBS_WHITE );
      $goban = Goban::create_goban_from_stones_snapshot( $shape->Size, $arr_xy );

      $exporter = new GobanHandlerGfxBoard( '', $stone_size );
      return $exporter->write_goban( $goban );
   }//build_view_shape

} // end of 'ShapeControl'
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/game_functions.php';
require_once 'include/classlib_goban.php';
require_once 'include/goban_handler_gfx.php';


 /*!
  * \class ShapeControl
  *
  * \brief Controller-Class to handle shape-game-stuff.
  */
class ShapeControl
{
   // ------------ static functions ----------------------------

   /*! \brief Returns Flags-text or all Flags-texts (if arg=null). */
   public static function getFlagsText( $flag=null )
   {
      static $ARR_SHAPE_FLAGS = null; // flag => text

      // lazy-init of texts
      if ( is_null($ARR_SHAPE_FLAGS) )
      {
         $arr = array();
         $arr[SHAPE_FLAG_PLAYCOLOR_W] = T_('W-First#SHP_flag');
         $arr[SHAPE_FLAG_PUBLIC] = T_('Public#SHP_flag');
         $ARR_SHAPE_FLAGS = $arr;
      }

      if ( is_null($flag) )
         return $ARR_SHAPE_FLAGS;
      if ( !isset($ARR_SHAPE_FLAGS[$flag]) )
         error('invalid_args', "ShapeControl:getFlagsText($flag,$short)");
      return $ARR_SHAPE_FLAGS[$flag];
   }//getFlagsText

   /*! \brief Returns text-representation of shape-flags. */
   public static function formatFlags( $flags, $zero_val='', $intersect_flags=0, $class=null )
   {
      $check_flags = ( $intersect_flags > 0 ) ? $flags & $intersect_flags : $flags;

      $arr = array();
      $arr_flags = self::getFlagsText();
      foreach ( $arr_flags as $flag => $flagtext )
      {
         if ( $check_flags & $flag )
            $arr[] = ($class) ? span($class, $flagtext) : $flagtext;
      }
      return (count($arr)) ? implode(', ', $arr) : $zero_val;
   }//formatFlags

   public static function newShape( $snapshot, $size, $flags )
   {
      return new Shape( 0, 0, null, '', $size, $flags, $snapshot );
   }

   /*! \brief Returns HTML for given Shape-object parsing Snapshot. */
   public static function build_view_shape( $shape, $stone_size=null )
   {
      if ( is_null($shape) )
         error('invalid_args', "ShapeControl:build_view_shape.miss_shape()");

      $arr_xy = GameSnapshot::parse_stones_snapshot( $shape->Size, $shape->Snapshot, GOBS_BLACK, GOBS_WHITE );
      $goban = Goban::create_goban_from_stones_snapshot( $shape->Size, $arr_xy );

      $exporter = new GobanHandlerGfxBoard( '', $stone_size );
      return $exporter->write_goban( $goban );
   }//build_view_shape

   public static function is_shape_name_used( $name )
   {
      return !is_null( Shape::load_shape_by_name($name) );
   }//is_shape_name_used

   public static function load_shape_name( $shape_id )
   {
      if ( $shape_id > 0 )
      {
         $shape = Shape::load_shape($shape_id, false);
         if ( !is_null($shape) )
            return $shape->Name;
      }
      return '???';
   }//load_shape_name

   public static function build_snapshot_info( $shape_id, $size, $snapshot, $b_first, $incl_image=true )
   {
      $shape_name = self::load_shape_name($shape_id);
      $colfirst_text = (is_null($b_first) || $b_first) ? '' : ' (' . T_('W-First#SHP_flag') . ')';
      return ($incl_image ? echo_image_shapeinfo( $shape_id, $size, $snapshot ) . ' ' : '') .
         sprintf( '%s #%s%s: %s', T_('Shape'), $shape_id, $colfirst_text, $shape_name );
   }

} // end of 'ShapeControl'
?>

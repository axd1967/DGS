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

$TranslateGroups[] = "Games";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/form_functions.php';
require_once 'include/shape_control.php';

$GLOBALS['ThePage'] = new Page('ShapeView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board($my_id);

   $shape_id = (int)get_request_arg('shape');
   $snapshot = get_request_arg('snapshot'); // optional arg
   if( $shape_id < 0 )
      $shape_id = 0;

   $page = "view_shape.php";

   // init
   $stone_size = 17;
   $shape = Shape::load_shape( $shape_id );
   //TODO can have snapshot but no shape-id any more
   if( is_null($shape) )
      error('unknown_shape', "view_shape.find_shape($shape_id)");

   $title = sprintf( T_('Shape View #%d'), $shape_id );
   start_page($title, true, $logged_in, $player_row, GobanHandlerGfxBoard::style_string($stone_size) );
   echo "<h3 class=Header>$title</h3>\n";

   $form = new Form( 'viewShape', $page, FORM_GET );
   $form->add_row( array(
         'DESCRIPTION', T_('Author#shape'),
         'TEXT', $shape->User->user_reference(), ));
   $form->add_row( array(
         'DESCRIPTION', T_('Shape Name#shape'),
         'TEXT', make_html_safe( $shape->Name, false), ));
   $form->add_row( array(
         'DESCRIPTION', T_('Size#shape'),
         'TEXT', $shape->Size, ));
   if( $shape->Flags > 0 )
      $form->add_row( array(
            'DESCRIPTION', T_('Flags#shape'),
            'TEXT', ShapeControl::formatFlags($shape->Flags), ));
   $form->add_row( array(
         'DESCRIPTION', T_('Created#shape'),
         'TEXT', formatDate($shape->Created), ));
   $form->add_row( array(
         'DESCRIPTION', T_('Lastchanged#shape'),
         'TEXT', formatDate($shape->Lastchanged), ));
   $form->echo_string();

   echo
      "<table class=\"ViewShape\">\n",
         "<tr>",
            "<td class=Preview>", ShapeControl::build_view_shape($shape, $stone_size), "</td>\n",
            "<td class=Notes><h4>", T_('Notes#shape'), "</h4>\n", make_html_safe($shape->Notes, true), "</td>\n",
         "</tr>\n",
      "</table>\n";

   if( $snapshot != $shape->Snapshot ) //TODO check must take care of extended and unextended snapshot
   {
      //TODO show diff
   }


   $menu_array = array();
   $menu_array[T_('Edit Shape#shape')] = "edit_shape.php?shape=$shape_id".URI_AMP."snapshot=".urlencode($snapshot);
   $menu_array[T_('Show in Goban Editor#shape')] =
      "goban_editor.php?shape=$shape_id".URI_AMP."snapshot=".urlencode($snapshot);
   $menu_array[T_('Shapes#shape')] = "list_shapes.php";
   //TODO new game
   //TODO invite

   end_page(@$menu_array);
}

?>

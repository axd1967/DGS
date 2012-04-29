<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/table_infos.php';

$GLOBALS['ThePage'] = new Page('ShapeView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'view_shape');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board($my_id);

   $shape_id = (int)get_request_arg('shape');
   $raw_snapshot = get_request_arg('snapshot'); // optional arg
   if( $shape_id < 0 )
      $shape_id = 0;

   $page = "view_shape.php";

   // init
   $stone_size = 17;
   $url_snapshot = $url_invite_snapshot = '';

   // load shape for shape-id
   $shape = ($shape_id) ? Shape::load_shape($shape_id) : null;
   if( $shape_id && is_null($shape) )
      error('unknown_shape', "view_shape.find_shape($shape_id)");
   if( !is_null($shape) )
   {
      $shape1 = array(
            'src'       => sprintf( T_('Shape ID #%s#shape'), $shape->ID ),
            'author'    => $shape->User->user_reference(),
            'name'      => $shape->Name,
            'size'      => $shape->Size,
            'flags'     => ShapeControl::formatFlags($shape->Flags),
            'created'   => formatDate($shape->Created),
            'lc'        => formatDate($shape->Lastchanged),
            'snapshot'  => $shape->Snapshot,
            'shape'     => $shape,
            'notes'     => ((string)$shape->Notes != '') ? make_html_safe($shape->Notes, true) : NO_VALUE,
         );
      $url_invite_snapshot = GameSnapshot::build_extended_snapshot( $shape->Snapshot, $shape->Size, $shape->Flags );
   }
   else
      $shape1 = array();

   // parse shape from URL-args
   $parsed_snapshot = ($raw_snapshot)
      ? GameSnapshot::parse_extended_snapshot($raw_snapshot) // [ Snapshot/Size/PlayColorB ]
      : null;
   $shape2 = array();
   if( is_array($parsed_snapshot) )
   {
      $shape2['src'] = T_('Game Shape#shape');
      if( isset($parsed_snapshot['Size']) )
         $shape2['size'] = (int)$parsed_snapshot['Size'];
      $shape_flags = ( @$parsed_snapshot['PlayColorB'] ? 0 : SHAPE_FLAG_PLAYCOLOR_W );
      if( isset($parsed_snapshot['PlayColorB']) )
         $shape2['flags'] = ShapeControl::formatFlags($shape_flags);
      if( isset($parsed_snapshot['Snapshot']) )
         $shape2['snapshot'] = $parsed_snapshot['Snapshot'];
      if( @$shape2['size'] && isset($shape2['snapshot']) )
      {
         $shape2['shape'] = new Shape( 0, 0, null, '', $shape2['size'], $shape_flags, $shape2['snapshot']);
         $url_snapshot = urlencode($raw_snapshot);
      }
      $shape2['notes'] = '';
   }

   if( !@$shape1['src'] && !@$shape2['src'] )
      error('miss_args', "view_shape.miss_shape($shape_id,$raw_snapshot)");

   $itable = build_info_table( $shape1, $shape2 );


   $title = T_('View Shape#shape');
   start_page($title, true, $logged_in, $player_row, GobanHandlerGfxBoard::style_string($stone_size) );
   echo "<h3 class=Header>$title</h3>\n";

   // show snapshot-diff
   $view_shape2 = '';
   $title_row = sprintf( "<tr><th></th><th class=\"left\">%s</th><th></th></tr>\n", T_('Shape Notes#shape') );
   if( !@$shape1['shape'] ) // only parsed-shape
      $view_shape1 = ShapeControl::build_view_shape($shape2['shape'], $stone_size);
   elseif( !@$shape2['shape'] ) // only db-shape
      $view_shape1 = ShapeControl::build_view_shape($shape1['shape'], $stone_size);
   else // two shapes
   {
      $view_shape1 = ShapeControl::build_view_shape($shape1['shape'], $stone_size);
      if( $shape1['snapshot'] != $shape2['snapshot'] )
      {
         $view_shape2 = ShapeControl::build_view_shape($shape2['shape'], $stone_size);
         $title_row = sprintf( "<tr><th>%s</th><th>%s</th><th>%s</th></tr>\n",
            $shape1['src'], T_('Shape Notes#shape'), $shape2['src'] );
      }
   }

   echo $itable->make_table(), "<br>\n",
      "<table class=\"ViewShape\">\n",
         $title_row,
         "<tr>",
            "<td class=Preview>$view_shape1</td>\n",
            "<td class=Notes>", @$shape1['notes'], "</td>\n",
            "<td class=Preview>$view_shape2</td>\n",
         "</tr>\n",
      "</table>\n";


   $menu_array = array();
   $menu_array[T_('Edit Shape#shape')] = "edit_shape.php?shape=$shape_id".URI_AMP."snapshot=$url_snapshot";
   $menu_array[T_('Show in Goban Editor#shape')] =
      "goban_editor.php?shape=$shape_id".URI_AMP."snapshot=$url_snapshot";
   $menu_array[T_('Shapes#shape')] = "list_shapes.php";
   if( @$shape->ID && $url_invite_snapshot )
   {
      $menu_array[T_('Invite#shape')] =
         "message.php?mode=Invite".URI_AMP."shape={$shape->ID}".URI_AMP."snapshot=".urlencode($url_invite_snapshot);
      $menu_array[T_('New Shape-Game#shape')] =
         "new_game.php?shape={$shape->ID}".URI_AMP."snapshot=".urlencode($url_invite_snapshot);
   }

   end_page(@$menu_array);
}//main


function build_info_table( $shape1, $shape2 )
{
   $descr = array(
         'src'       => T_('Source#shape'),
         'author'    => T_('Author#shape'),
         'name'      => T_('Shape Name#shape'),
         'size'      => T_('Board Size#shape'),
         'flags'     => T_('Flags#shape'),
         'created'   => T_('Created#shape'),
         'lc'        => T_('Lastchanged#shape'),
      );

   if( !@$shape1['src'] ) // only parsed-shape
      $shape = $shape2;
   elseif( !@$shape2['src'] ) // only db-shape
      $shape = $shape1;
   else // 2 shapes
      $shape = ( has_shape_diff($shape1,$shape2) ? null : $shape1 );

   $itable = new Table_info('shape');
   if( is_null($shape) ) // two shapes
   {
      foreach( $descr as $key => $title )
      {
         $itable->add_sinfo( $title,
               array( @$shape1[$key],
                      ((string)@$shape2[$key] != '' && @$shape1[$key] != @$shape2[$key]
                          ? array( @$shape2[$key], 'class=Diff' )
                          : @$shape2[$key] ) ));
      }
   }
   else // single shape
   {
      foreach( $descr as $key => $title )
      {
         if( isset($shape[$key]) )
            $itable->add_sinfo( $title, $shape[$key] );
      }
   }

   return $itable;
}//build_info_table

function has_shape_diff( $shape1, $shape2 )
{
   foreach( array( 'size', 'flags', 'snapshot' ) as $key )
   {
      if( (string)@$shape1[$key] != (string)@$shape2[$key] )
         return true;
   }
   return false;
}//has_shape_diff

?>

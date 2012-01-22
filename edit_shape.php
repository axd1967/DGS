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

$GLOBALS['ThePage'] = new Page('ShapeEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');
   $cfg_board = ConfigBoard::load_config_board($my_id);

   $page = "edit_shape.php";

/* Actual REQUEST calls used:
     (no args)       : new shape (redirected from goban-editor), needs snapshot + size
     preview&...     : preview given snapshot and notes
     swcol&...       : switch Black/White colors in snapshot
     save&...        : save shape-game, redirect to edit-shape-page
*/

   // read URL-args
   $shape_id = (int)get_request_arg('shape');
   if( $shape_id < 0 )
      $shape_id = 0;

   // init new shape
   $copy_err = 0;
   if( $shape_id )
   {
      $shape = Shape::load_shape( $shape_id );
      if( $shape_id && is_null($shape) )
         error('unknown_shape', "edit_shape.find_shape($shape_id)");

      // check shape-author -> enforce NEW-shape if another user
      if( $shape->uid != $my_id )
      {
         $copy_err = sprintf( T_('Create new shape (with Preview), because [%s] is author of shape %s!#shape'),
            $shape->User->Handle, '#'.$shape_id );
         $shape->ID = $shape_id = 0;
         $shape->uid = $my_id;
         $shape->User = User::new_from_row($player_row);
      }
   }
   else
      $shape = new Shape( 0, $my_id, User::new_from_row($player_row) );

   $arr_sizes = build_num_range_map( MIN_BOARD_SIZE, MAX_BOARD_SIZE, false );
   $stone_size = 17;

   // check + parse edit-form
   list( $vars, $edits, $errors ) = parse_edit_form( $shape );
   if( $copy_err )
      array_unshift( $errors, $copy_err );

   // ---------- PROCESS ACTIONS -----------------------------------

   if( count($errors) == 0 )
   {
      if( @$_REQUEST['swcol'] ) // switch colors
      {
         $arr_xy = GameSnapshot::parse_stones_snapshot( $shape->Size, $shape->Snapshot, GOBS_BLACK, GOBS_WHITE );
         $goban = Goban::create_goban_from_stones_snapshot( $shape->Size, $arr_xy );
         $goban->switch_colors();
         $vars['snapshot'] = GameSnapshot::make_game_snapshot( $shape->Size, $goban, /*with-dead*/false );
         if( $shape->Snapshot != $vars['snapshot'] )
            $edits[] = T_('Snapshot#edits');
         $edits = array_unique($edits);
         $shape->Snapshot = $vars['snapshot'];
      }
      elseif( @$_REQUEST['save'] ) // save shape
      {
         if( $shape->persist() )
            jump_to("edit_shape.php?shape={$shape->ID}".URI_AMP."sysmsg=".urlencode(T_('Shape saved!#shape')));
      }
   }

   if( isset($arr_sizes[$shape->Size]) && $shape->Snapshot )
   {
      $view_shape = ShapeControl::build_view_shape($shape, $stone_size);
      $url_snapshot = urlencode( GameSnapshot::build_extended_snapshot(
            $vars['snapshot'], $vars['size'], $vars['flag_playcol']) );
   }
   else
   {
      $view_shape = '';
      $url_snapshot = urlencode($shape->Snapshot);
   }
   $preview_notes = make_html_safe($vars['notes'], true);

   // ---------- EDIT FORM -----------------------------------------

   $arr_flags_playcol = array(
         0 => T_('Black#shape') . MINI_SPACING,
         SHAPE_FLAG_PLAYCOLOR_W => T_('White#shape'),
      );

   $form = new Form( 'editShape', $page, FORM_POST );
   $form->add_hidden('snapshot', $shape->Snapshot );
   $form->add_hidden('shape', $shape_id);

   if( !is_null($errors) && count($errors) )
   {
      $form->add_row( array(
            'DESCRIPTION', T_('Errors'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $form->add_empty_row();
   }

   $form->add_row( array(
         'DESCRIPTION', T_('Shape ID#shape'),
         'TEXT', ($shape_id ? anchor("edit_shape.php?shape=$shape_id", '#'.$shape_id) : T_('NEW#shape') ), ));
   $form->add_row( array(
         'DESCRIPTION', T_('Author#shape'),
         'TEXT', $shape->User->user_reference(), ));
   if( $shape->Created )
      $form->add_row( array(
            'DESCRIPTION', T_('Created#shape'),
            'TEXT', formatDate($shape->Created), ));
   if( $shape->Lastchanged )
      $form->add_row( array(
            'DESCRIPTION', T_('Lastchanged#shape'),
            'TEXT', formatDate($shape->Lastchanged), ));
   $form->add_empty_row();
   $form->add_row( array(
         'DESCRIPTION', T_('Shape Name#shape'),
         'TEXTINPUT',   'name', 40, 50, $vars['name'], ));
   $form->add_row( array(
         'DESCRIPTION', T_('Size#shape'),
         'SELECTBOX',   'size', 1, $arr_sizes, $vars['size'], false, ));
   $form->add_row( array(
         'DESCRIPTION', T_('Play Start Color#shape'),
         'RADIOBUTTONS', 'flag_playcol', $arr_flags_playcol, $vars['flag_playcol'], ));
   $form->add_row( array(
         'DESCRIPTION', T_('Shape Notes#shape'),
         'TEXTAREA',    'notes', 60, 5, $vars['notes'], ));
   $form->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));
   $form->add_row( array(
         'TAB', 'CELL', 1, '',
         'SUBMITBUTTON', 'preview', T_('Preview'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'swcol', T_('Switch Colors#gobedit'),
         'TEXT', MINI_SPACING,
         'SUBMITBUTTON', 'save', T_('Save Shape#shape'),
         ));

   // ---------- END form ------------------------------------------

   $title = T_('Edit Shape#shape');
   start_page($title, true, $logged_in, $player_row, GobanHandlerGfxBoard::style_string($stone_size) );
   echo "<h3 class=Header>$title</h3>\n";

   echo $form->get_form_string(), "<br>\n",
      "<table class=\"ViewShape\">\n",
         sprintf( "<tr><th></th><th>%s</th></tr>\n", T_('Shape Notes#shape') ),
         "<tr>",
            "<td class=Preview>$view_shape</td>\n",
            "<td class=Notes>$preview_notes</td>\n",
         "</tr>\n",
      "</table>\n";


   $menu_array = array();
   $menu_array[T_('View Shape#shape')] = "view_shape.php?shape=$shape_id".URI_AMP."snapshot=$url_snapshot";
   $menu_array[T_('Show in Goban Editor#shape')] = "goban_editor.php?shape=$shape_id".URI_AMP."snapshot=$url_snapshot";
   $menu_array[T_('Shapes#shape')] = "list_shapes.php";

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$shape )
{
   global $arr_sizes, $player_row;

   $edits = array();
   $errors = array();

   // read from props or set defaults
   $vars = array(
      'name'         => $shape->Name,
      'size'         => $shape->Size,
      'flag_playcol' => ( $shape->Flags & SHAPE_FLAG_PLAYCOLOR_W ),
      'notes'        => $shape->Notes,
      'snapshot'     => $shape->Snapshot,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // special handling, parsing snapshot/size/flags from extended-snapshot
   if( !@$_REQUEST['size'] && @$_REQUEST['snapshot'] )
   {
      $arr = GameSnapshot::parse_extended_snapshot($_REQUEST['snapshot']);
      if( is_array($arr) && ( $arr['Snapshot'] != $_REQUEST['snapshot'] ) )
      {
         $vars['size'] = (int)$arr['Size'];
         $vars['snapshot'] = $arr['Snapshot'];
         $vars['flag_playcol'] = (@$arr['PlayColorB']) ? 0 : SHAPE_FLAG_PLAYCOLOR_W;
      }
   }

   // parse URL-vars
   //if( $is_posted )
   {
      $new_value = trim($vars['name']);
      if( strlen($new_value) < 3 )
         $errors[] = T_('Name is missing or too short#shape');
      elseif( strlen($new_value) > 40 )
         $errors[] = sprintf( T_('Name is too long (max. %s chars)#shape'), 40);
      elseif( (!$shape->ID || $shape->Name != $new_value) && ShapeControl::is_shape_name_used($new_value) )
         $errors[] = sprintf( T_('Name [%s] is already used, but must be unique.#shape'), $new_value);
      else
         $shape->Name = $new_value;

      $new_value = trim($vars['size']);
      if( (string)$new_value == '' )
         $errors[] = T_('Missing Size#shape');
      elseif( !isset($arr_sizes[$new_value]) )
         $errors[] = sprintf( T_('Invalid size [%s]#shape'), $new_value );
      else
         $shape->Size = (int)$new_value;

      $shape->Flags = (int)$vars['flag_playcol'];
      $shape->Notes = trim($vars['notes']);

      $new_value = $vars['snapshot'];
      if( (string)$new_value != '' )
      {
         if( (string)($bad_chars = GameSnapshot::check_snapshot($new_value)) != '' )
            $errors[] = sprintf( T_('Snapshot for shape contains invalid characters [%s].#shape'), $bad_chars );
         else
            $shape->Snapshot = $new_value;
      }
      if( (string)$shape->Snapshot == '' )
         $errors[] = T_('Missing Snapshot#shape');


      // determine edits
      if( $old_vals['name'] != $shape->Name ) $edits[] = T_('Name#edits');
      if( $old_vals['size'] != $shape->Size ) $edits[] = T_('Size#edits');
      if( $old_vals['flag_playcol'] != ($shape->Flags & SHAPE_FLAG_PLAYCOLOR_W)) $edits[] = T_('PlayColor#edits');
      if( $old_vals['notes'] != $shape->Notes ) $edits[] = T_('Notes#edits');
      if( $old_vals['snapshot'] != $shape->Snapshot ) $edits[] = T_('Snapshot#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

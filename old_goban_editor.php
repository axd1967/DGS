<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Goban";

require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/classlib_goban.php' );
require_once( 'include/goban_handler_sl.php' );
require_once( 'include/goban_handler_gfx.php' );
require_once( 'include/GoDiagram.php' ); // OLD go-editor

$ThePage = new Page('GobanEdit');

define('IMG_SPACING', MINI_SPACING.MINI_SPACING);

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_GOBAN_EDITOR )
      error('feature_disabled', 'goban_editor');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board($my_id);

/* Actual REQUEST calls used:
     (no args)                : new goban
     gob_new&size=            : make new goban of given size
     gob_preview&goban_text=  : preview given goban from goban_text
     gob_old&goban_text=      : show OLD JavaScript-goban-editor
     gob_save&...             : save (TODO)
*/

   // read args
   $board_size = get_request_arg( 'size', 13 ); //TODO later 19
   $goban_text = trim(get_request_arg( 'goban_text' ));

   // init vars
   $tool_size = 21;
   $goban_form = 'goban';
   if( $goban_text == '' && !@$_REQUEST['gob_new'] )
   {
      // <igoban> example
      $goban_text = <<<EOF_GOBAN
<igoban SL1>
\$\$c9 Board Markup
\$\$ +
\$\$ . 3 . . . . . . . . .
\$\$ 1 2 . a b c . x y z .
\$\$ . . . . . . - . . . .
\$\$ . X . B . # . Y . Z .
\$\$ . . . . . . . . . . .
\$\$ . O . W . @ . Q . P .
\$\$ . . . . . . . . , . .
\$\$ . . . C . S . T . M .
\$\$ . . . . . . . . . . -
\$\$ + --- +
</igoban>
EOF_GOBAN;
   }

   $arr_sizes =  array();
   for( $bs = MIN_BOARD_SIZE; $bs <= MAX_BOARD_SIZE; $bs++ )
      $arr_sizes[$bs] = $bs;

   // setup goban for board-editing
   $goban = new Goban();
   $goban->setOptionsCoords( GOBB_NORTH|GOBB_SOUTH|GOBB_WEST|GOBB_EAST );
   $goban->setSize( $board_size );
   $goban->makeBoard( $board_size, $board_size );
   $goban_writer = new GobanHandlerGfxBoard();
   $goban_writer->setImageAttribute( 'onClick="edit_click(%s,%s)"' ); // x,y
   $edit_title = T_('Edit board');
   $edit_goboard = $goban_writer->write_goban( $goban );

   // - parse NEW <igoban...>-tag (inline)
   $goban_preview = MarkupHandlerGoban::replace_igoban_tags( $goban_text );

   // - parse OLD <goban...>-tag using JavaScript [js/goeditor.js]
   $GoDiagrams = NULL;
   $go_diagrams_str = NULL;
   $arr_dump_diagrams = array();
   $goban_preview_old = '';
   if( ALLOW_GO_DIAGRAMS && is_javascript_enabled() && @$_REQUEST['gob_old'] )
   {
      // create new entries for <goban> (without ID) in GoDiagrams-table
      // and replace <goban> tag with <goban id=#>
      $GoDiagrams = create_godiagrams($goban_text, $cfg_board);
      if( !is_null($GoDiagrams) )
      {
         $goban_preview_old = replace_goban_tags_with_boards($goban_text, $GoDiagrams);
         $arr_dump_diagrams = array();
         $go_diagrams_str = draw_editors($GoDiagrams);
         if( !empty($go_diagrams_str) )
         {
            // needs to be added to "Save"-submits to summon edited data
            $arr_dump_diagrams = array( 'onClick' => "dump_all_data('{$goban_form}Form');" );
         }

         // show OLD editor in edit-area
         $edit_title = T_('(OLD) Edit board');
         $edit_goboard = $go_diagrams_str;
      }

      // pure preview (without editing): show go-boards for previewing
      //$PreviewGoDiagrams = find_godiagrams($goban_preview, $cfg_board);
      //$goban_preview_oldt= replace_goban_tags_with_boards($goban_preview, $PreviewGoDiagrams);
   }


   // check + parse edit-form
   $errorlist = NULL;

   // save goban with values from edit-form
   if( @$_REQUEST['gob_save'] && !@$_REQUEST['gob_preview'] && is_null($errorlist) )
   {
      // TODO insert or update
      if( !is_null($GoDiagrams) )
      {
         // save_diagrams($GoDiagrams); // <goban>-tags
      }
      //jump_to("goban_editor.php?sysmsg=". urlencode(T_('Goban saved!')) );
   }

   $page = "goban_editor.php";
   $title = T_('Goban editor');


   // ---------- Goban EDIT form -----------------------------------

   $gobform = new Form( $goban_form, $page, FORM_POST );
   $gobform->set_layout( FLAYOUT_GLOBAL, '(1|(2,3)),4' );

   $gobform->set_area(1);
   $gobform->set_layout( FLAYOUT_AREACONF, 1, array(
         'title' => $edit_title,
      ));
   $gobform->add_row( array(
        'TEXT',  $edit_goboard, ));

   $gobform->set_area(2);
   $gobform->set_layout( FLAYOUT_AREACONF, 2, array(
         'title'   => T_('Edit tools'),
         //FAC_ENVTABLE => 'class="EditTools"', //TODO not working yet to left-align edit-tools
      ));
   $gobform->add_row( array(
         'DESCRIPTION', T_('Board'),
         'TEXT',        T_('Size').MINI_SPACING,
         'SELECTBOX',   'size', 1, $arr_sizes, $board_size, false,
         'SUBMITBUTTON', 'gob_new', T_('New'),
      ));
   $tool_on  = 'class=ToolOn';
   $tool_off = 'class=ToolOff';
   $js_toggle = "toggle_class(this,'ToolOn','ToolOff');";
   $gobform->add_row( array(
         'DESCRIPTION', T_('History'),
         'TEXT', image( $base_path."images/backward.gif", '<<', T_('Undo'), "$tool_off onClick=\"do_undo();\"" )
               . IMG_SPACING
               . image( $base_path."images/forward.gif", '>>', T_('Redo'), "$tool_off onClick=\"do_redo();\"" ),
      ));
   $gobform->add_row( array(
         'DESCRIPTION', T_('Stone'),
         'TEXT', image( $base_path."$tool_size/b.gif", 'B', T_('Black Stone'), "$tool_off onClick=\"$js_toggle\"" ) // use_stone('B');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/w.gif", 'W', T_('White Stone'), "$tool_off onClick=\"use_stone('W');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/pb.gif", 'BW', T_('Black to move'), "$tool_on onClick=\"switch_stone();\"" )
      ));
   $gobform->add_row( array(
         'DESCRIPTION', T_('Marker'),
         'TEXT', image( $base_path."$tool_size/b.gif", 'plain', T_('Plain Stone Marker'), "$tool_on onClick=\"use_marker('plain');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/bc.gif", 'circle', T_('Circle Marker'), "$tool_off onClick=\"use_marker('circle');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/bs.gif", 'square', T_('Square Marker'), "$tool_off onClick=\"use_marker('square');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/bt.gif", 'triangle', T_('Triangle Marker'), "$tool_off onClick=\"use_marker('triangle');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/bx.gif", 'cross', T_('Cross Marker'), "$tool_off onClick=\"use_marker('cross');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/b1.gif", 'number', T_('Plain Number'), "$tool_off onClick=\"use_marker('number');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/b1.gif", 'number', T_('Numbered Stone'), "$tool_off onClick=\"use_marker('number_stone');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/ca.gif", 'letter', T_('Plain Letter'), "$tool_off onClick=\"use_marker('letter');\"" )
               . IMG_SPACING
               . image( $base_path."$tool_size/ca.gif", 'letter', T_('Letter Stone'), "$tool_off onClick=\"use_marker('letter_stone');\"" )
      ));
   $gobform->add_empty_row();

   $gobform->set_area(3);
   $gobform->set_layout( FLAYOUT_AREACONF, 3, array(
         'title' => T_('Manual Edit'),
      ));
   $gobform->add_row( array(
         'TEXTAREA', 'goban_text', 60, 15, $goban_text,
      ));
   $gobform->add_row( array(
         'SUBMITBUTTONX', 'gob_preview', T_('Preview'),
               array( 'accesskey' => ACCKEY_ACT_PREVIEW ),
         'SUBMITBUTTONX', 'gob_save', T_('Save'),
               array( 'accesskey' => ACCKEY_ACT_EXECUTE ) + $arr_dump_diagrams,
         'SUBMITBUTTONX', 'gob_old', T_('Show OLD Editor'),
               $arr_dump_diagrams,
      ));

   $gobform->set_area(4);
   $gobform->set_layout( FLAYOUT_AREACONF, 4, array(
         'title' => T_('Misc area ...'),
      ));
   $gobform->add_row( array(
         'TEXT',  "For showing the OLD editor, enter &lt;goban size=9&gt; in the manual-edit box and press 'Show OLD editor'<br>\n" .
                  "After that use &lt;goban id=...&gt; with the ID which is assigned to get hand on the saved go-diagram<br>\n",
      ));

   //$gobform->add_hidden( 'gobid', $gob_id );


   $style_str = (is_null($cfg_board))
      ? '' : GobanHandlerGfxBoard::style_string( $cfg_board->get_stone_size() );
   start_page( $title, true, $logged_in, $player_row, $style_str );
   echo "<h3 class=Header>$title</h3>\n";

   $gobform->echo_string();

   if( @$_REQUEST['gob_preview'] )
   {
      section( 'GobanPreview', T_('Preview area') );
      echo $goban_preview, "<br>\n";
   }

   if( @$_REQUEST['gob_old'] && $goban_preview_old != '' )
   {
      section( 'GobanPreviewOld', T_('Preview area (Old Goban Editor)') );
      echo $goban_preview_old, "<br>\n";
   }

   $menu_array = array();
   $menu_array[T_('New Goban Editor')] = "goban_editor.php";
   $menu_array[T_('Load Goban (SGF or DGS)')] = "goban_editor.php";

   end_page(@$menu_array);
}

?>

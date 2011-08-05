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

$TranslateGroups[] = "Goban";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/goban_handler_gfx.php';

$GLOBALS['ThePage'] = new Page('GameEditor');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_GAME_EDITOR || !is_javascript_enabled() )
      error('feature_disabled');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board($my_id);
   $imgtool_path = $base_path . '21/';

   $page = "game_editor.php";
   $title = T_('Game Editor#ged');

   // setup skeleton-goban for board-editing
   $board_size = 2;
   $goban = new Goban();
   $goban->setOptionsCoords( GOBB_NORTH|GOBB_SOUTH|GOBB_WEST|GOBB_EAST, true );
   $goban->setSize( $board_size, $board_size );
   $goban->makeBoard( $board_size, $board_size );
   $goban_writer = new GobanHandlerGfxBoard();
   $goban_writer->enable_id = true;
   $goboard_skeleton = $goban_writer->write_goban( $goban, /*skeleton*/true );


   // ---------- Game EDITOR ---------------------------------------

   $js = sprintf( "DGS.run.gameEditor = new DGS.GameEditor(%d,%d);\n",
      $cfg_board->get_stone_size(), $cfg_board->get_wood_color() );
   $js .= "DGS.game_editor.loadPage();\n";

   $style_str = (is_null($cfg_board))
      ? '' : GobanHandlerGfxBoard::style_string( $cfg_board->get_stone_size() );
   start_page( $title, true, $logged_in, $player_row, $style_str, null, $js );

   echo "<h3 class=Header>$title</h3>\n",
      "<table id=GameEditor class=GameEditor>\n",
         "<tr><td id=BoardArea>$goboard_skeleton</td>\n",
            "<td id=EditArea>",
               "<div id=tabs>\n",
                  "<ul>\n",
                     "<li>", anchor('#tab_Size', T_('Size#ged'), T_('Change board size#ged')), "</li>\n",
                     "<li>", anchor('#tab_Edit', T_('Edit#ged'), T_('Edit tools#ged')), "</li>\n",
                     "<li>", anchor('#tab_Play', T_('Play#ged'), T_('Play tools#ged')), "</li>\n",
                  "</ul>\n",
                  "<div id=tab_Size class=tab>\n", build_tab_Size(), "</div>\n",
                  "<div id=tab_Edit class=tab>\n", build_tab_Edit(), "</div>\n",
                  "<div id=tab_Play class=tab>\n", build_tab_Play(), "</div>\n",
               "</div>\n",
            "</td></tr>\n",
         "<tr><td id=NodeArea colspan=2>Node Area</td></tr>\n",
         "<tr><td colspan=2><pre id=D></pre></td></tr>", // for debug
      "</table>\n";

   $menu_array = array();

   end_page(@$menu_array);
}//main


function build_tab_Size()
{
   global $page;
   $form = new Form( 'gameEditorSize', $page, FORM_GET );
   $form->add_row( array(
      'DESCRIPTION', T_('Width#ged'),
      'TEXTINPUTX',  'size_w', 4, 4, '', 'id=size_w' ));
   $form->add_row( array(
      'DESCRIPTION', T_('Height#ged'),
      'TEXTINPUTX',  'size_h', 4, 4, '', 'id=size_h' ));
   $form->add_row( array(
      'CELL', 2, '',
      'SUBMITBUTTONX', 'size_upd', T_('New Board#ged'), 'id=size_upd' ));
   return $form->create_form_string();
}

function build_tab_Edit()
{
   global $page, $imgtool_path, $base_path;
   $form = new Form( 'gameEditorEdit', $page, FORM_GET );
   $form->add_row( array(
      'DESCRIPTION', T_('Stone#ged'),
      'TEXT', anchor('#', image($imgtool_path.'pb.gif', T_('Toggle Stone (Click=Black, Shift-Click=White)#ged'), null), '', 'id=edit_tool_toggle_stone class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'b.gif', T_('Set Black Stone#ged'), null), '', 'id=edit_tool_b_stone class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'w.gif', T_('Set White Stone#ged'), null), '', 'id=edit_tool_w_stone class=Tool'),
      'TEXT', anchor('#', image($base_path.'images/no.gif', T_('Clear Stone#ged'), null), '', 'id=edit_tool_clear_stone class=Tool'),
      //'TEXT', anchor('#', image($imgtool_path.'bm.gif', T_('Toggle Mark Marker#ged'), null), '', 'id=edit_tool_mark_marker class="Tool"'), // only in PLAY-mode
      ));
   $form->add_row( array(
      'DESCRIPTION', T_('Marker#ged'),
      'TEXT', anchor('#', image($imgtool_path.'c.gif', T_('Toggle Circle Marker#ged'), null), '', 'id=edit_tool_circle_marker class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'s.gif', T_('Toggle Square Marker#ged'), null), '', 'id=edit_tool_square_marker class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'t.gif', T_('Toggle Triangle Marker#ged'), null), '', 'id=edit_tool_triangle_marker class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'x.gif', T_('Toggle Cross Marker#ged'), null), '', 'id=edit_tool_cross_marker class="Tool"'),
      'TEXT', MED_SPACING,
      'TEXT', anchor('#', image($imgtool_path.'eb.gif', T_('Toggle Black Territory Marker#ged'), null), '', 'id=edit_tool_terr_b_marker class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'ew.gif', T_('Toggle White Territory Marker#ged'), null), '', 'id=edit_tool_terr_w_marker class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'ed.gif', T_('Toggle Dame Territory Marker#ged'), null), '', 'id=edit_tool_terr_dame_marker class="Tool"'),
      'TEXT', anchor('#', image($imgtool_path.'eg.gif', T_('Toggle Neutral Territory Marker#ged'), null), '', 'id=edit_tool_terr_neutral_marker class="Tool"'),
      ));
   $form->add_row( array(
      'DESCRIPTION', T_('Label#ged'),
      'TEXT', anchor('#', span('LabelTool', ''), T_('Toggle Stone Number Label#ged'), 'id=edit_tool_number_label class="Tool Label"'),
      'TEXT', anchor('#', span('LabelTool', ''), T_('Toggle Letter Label#ged'), 'id=edit_tool_letter_label class="Tool Label"'),
      ));
   $form->add_row( array(
      'DESCRIPTION', T_('History#ged'),
      'TEXT', anchor('#', span('LabelTool', 'undo'), '', 'id=edit_tool_undo class=UndoTool') . span('id=edit_tool_undo_hist class=UndoHist', '(0)'),
      'TEXT', anchor('#', span('LabelTool', 'redo'), '', 'id=edit_tool_redo class=UndoTool') . span('id=edit_tool_redo_hist class=UndoHist', '(0)'),
      ));
   return $form->create_form_string();
}//build_tab_Edit

function build_tab_Play()
{
   global $page, $imgtool_path, $base_path;
   $form = new Form( 'gameEditorPlay', $page, FORM_GET );
   $form->add_row( array(
      'DESCRIPTION', T_('Move#ged'),
      'TEXT', anchor('#', image($imgtool_path.'b.gif', T_('Play move#ged'), null), '', 'id=play_tool_move class="Tool"'),
      ));
   $form->add_row( array(
      'DESCRIPTION', T_('History#ged'),
      'TEXT', anchor('#', span('LabelTool', 'undo'), '', 'id=play_tool_undo class=UndoTool') . span('id=play_tool_undo_hist class=UndoHist', '(0)'),
      'TEXT', anchor('#', span('LabelTool', 'redo'), '', 'id=play_tool_redo class=UndoTool') . span('id=play_tool_redo_hist class=UndoHist', '(0)'),
      ));
   return $form->create_form_string();
}//build_tab_Play

?>

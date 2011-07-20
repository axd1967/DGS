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

require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/classlib_goban.php' );
require_once( 'include/goban_handler_sl.php' );
require_once( 'include/goban_handler_gfx.php' );

$GLOBALS['ThePage'] = new Page('GobanEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_GOBAN_EDITOR )
      error('feature_disabled');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board($my_id);

   $page = "goban_editor.php";

/* Actual REQUEST calls used:
     (no args)                : new goban
     gob_new&width=&height=   : make new goban of given size (width x height)
     gob_preview&board=       : preview given goban from 'board'-text
*/

   // read args
   $width  = (int)get_request_arg('width', 19);
   $height = (int)get_request_arg('height', 19);
   $board_text = trim(get_request_arg('board'));

   // setup goban for board-editing
   if( @$_REQUEST['gob_new'] )
   {
      if( $width < MIN_BOARD_SIZE || $width > MAX_BOARD_SIZE || $height < MIN_BOARD_SIZE || $height > MAX_BOARD_SIZE )
         jump_to("$page?width=$width".URI_AMP."height=$height");

      $board_text = create_new_igoban( $width, $height );
   }

   // parse <igoban...>-tag (inline)
   $goban_preview = NULL;
   if( (string)$board_text != '' || @$_REQUEST['gob_preview'] )
      $goban_preview = MarkupHandlerGoban::replace_igoban_tags( $board_text );


   // ---------- Goban form ----------------------------------------

   $gobform = new Form( 'goban', $page, FORM_POST );

   if( is_null($goban_preview) )
   {
      $arr_sizes = build_num_range_map( MIN_BOARD_SIZE, MAX_BOARD_SIZE, false );

      $gobform->add_row( array(
            'CHAPTER', T_('Create new board of given size#gobedit'), ));
      $gobform->add_empty_row();
      $gobform->add_row( array(
            'DESCRIPTION', T_('Width#gobedit'),
            'SELECTBOX',   'width', 1, $arr_sizes, $width, false, ));
      $gobform->add_row( array(
            'DESCRIPTION', T_('Height#gobedit'),
            'SELECTBOX',   'height', 1, $arr_sizes, $height, false, ));
      $gobform->add_row( array(
            'TAB', 'CELL', 1, '',
            'SUBMITBUTTON', 'gob_new', T_('Create Board#gobedit'), ));
   }
   else
   {
      $gobform->add_row( array(
            'CHAPTER', T_('Edit Area#gobedit'), ));
      $gobform->add_row( array(
            'TEXTAREA', 'board', 60, $height + 7, $board_text, ));
      $gobform->add_row( array(
            'SUBMITBUTTON', 'gob_preview', T_('Preview'), ));
      $gobform->add_row( array(
            'HIDDEN', 'width', $width,
            'HIDDEN', 'height', $height, ));
   }

   // ---------- END form ------------------------------------------


   $title = T_('Goban Editor');
   $style_str = (is_null($cfg_board))
      ? '' : GobanHandlerGfxBoard::style_string( $cfg_board->get_stone_size() );
   start_page( $title, true, $logged_in, $player_row, $style_str );
   echo "<h3 class=Header>$title</h3>\n";

   $gobform->echo_string();

   if( !is_null($goban_preview) )
   {
      section( 'GobanPreview', T_('Preview Area#gobedit') );
      echo $goban_preview, "<br>\n";
   }


   $menu_array = array();
   $menu_array[T_('New Goban')] = "goban_editor.php";

   end_page(@$menu_array);
}//main


function create_new_igoban( $width, $height )
{
   static $BORDER = "\$\$ ++\n";

   $size = ($width == $height) ? $width : '';
   $line = sprintf("\$\$%s\n", str_repeat(' .', $width) );
   $board = str_repeat( $line, $height );
   $igoban_text = sprintf("<igoban SL1>\n\$\$c%s\n{$BORDER}%s{$BORDER}</igoban>\n", $size, $board );
   return $igoban_text;
}//create_new_igoban

?>

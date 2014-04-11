<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/classlib_goban.php';
require_once 'include/classlib_upload.php';
require_once 'include/goban_handler_sl.php';
require_once 'include/goban_handler_gfx.php';
require_once 'include/goban_handler_dgsgame.php';
require_once 'include/sgf_parser.php';
require_once 'include/board.php';
require_once 'include/move.php';
require_once 'include/coords.php';
require_once 'include/db/shape.php';

$GLOBALS['ThePage'] = new Page('GobanEdit');

define('SGF_MAXSIZE_UPLOAD', 30*1024); // bytes


{
   // NOTE: using page: edit_shape.php

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'goban_editor');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board_or_default($my_id);

   $page = "goban_editor.php";

/* Actual REQUEST calls used:
     (no args)                : new goban
     gob_new&width=&height=   : make new goban of given size (width x height)
     gob_load&gid=&move=      : load DGS-game as goban for game-id and move (default last-move)
     gob_load_sgf&file_sgf=   : load SGF and flatten into Goban-object
     gob_preview&board=       : preview given goban from 'board'-text
     gob_swcol&...            : switch Black/White colors
     gob_flatten&...          : flatten Goban into B/W-stones alone for Shape-game
     gob_save_shape&...       : save shape-game, redirect to edit-shape-page
     shape=                   : load goban from shape-id
     snapshot=&shape=         : create goban from extended-snapshot (with size-info), memorize shape-id for save
*/

   // read args
   $width  = (int)get_request_arg('width', 9);
   $height = (int)get_request_arg('height', 9);
   $board_text = trim(get_request_arg('board'));
   $gid = get_request_arg('gid');
   $move = get_request_arg('move');
   $snapshot = get_request_arg('snapshot');
   $shape_id = (int)get_request_arg('shape');

   // check args
   $width = limit( $width, MIN_BOARD_SIZE, MAX_BOARD_SIZE, $width );
   $height = limit( $height, MIN_BOARD_SIZE, MAX_BOARD_SIZE, $height );
   if ( $gid < 0 )
      $gid = 0;
   if ( $move < 0 )
      $move = 0;
   if ( $shape_id < 0 )
      $shape_id = 0;

   // ---------- Process Commands ----------------------------------

   $goban_preview = $errors = NULL;
   $do_preview = @$_REQUEST['gob_preview'];

   if ( @$_REQUEST['gob_new'] )
   {
      $board_text = create_new_igoban( $width, $height );
   }
   elseif ( @$_REQUEST['gob_load_game'] && $gid )
   {
      list( $board_text, $width, $height, $do_preview ) = load_igoban_from_dgs_game( $gid, $move );
   }
   elseif ( @$_REQUEST['gob_load_sgf'] && isset($_FILES['file_sgf']) )
   {
      list( $errors, $board_text ) = load_igoban_from_sgf( $_FILES['file_sgf'] ); // GLOBALS $do_preview, $width, $height
   }
   elseif ( @$_REQUEST['gob_swcol'] ) // switch colors
   {
      list( $board_text, $do_preview ) = update_igoban( $board_text, 'switch_colors' );
   }
   elseif ( @$_REQUEST['gob_flatten'] ) // flatten to stones
   {
      list( $board_text, $do_preview ) = update_igoban( $board_text, 'flatten_for_shape_game' );
      $width = $height = max( $width, $height );
   }
   elseif ( @$_REQUEST['gob_save_shape'] && $width == $height ) // save shape-game
   {
      list( $tmp, $arr_goban ) = MarkupHandlerGoban::replace_igoban_tags_collect_goban( $board_text );
      if ( count($arr_goban) )
      {
         $goban = $arr_goban[0];
         $snapshot = GameSnapshot::make_game_snapshot( $goban->size_x, $goban, /*with-dead*/false );
         jump_to("edit_shape.php?shape=$shape_id".URI_AMP."size={$goban->size_x}".URI_AMP."snapshot=".urlencode($snapshot));
      }
   }
   elseif ( !$do_preview && $snapshot ) // other load-methods first
   {
      list( $board_text, $width, $height, $do_preview ) = create_goban_from_extended_snapshot( $snapshot );
   }
   elseif ( !$do_preview && $shape_id )
   {
      list( $board_text, $width, $height, $do_preview ) = load_igoban_from_shape_game( $shape_id );
   }

   // parse <igoban...>-tag (inline) for preview
   if ( (string)$board_text != '' || $do_preview )
      $goban_preview = MarkupHandlerGoban::replace_igoban_tags( $board_text );


   // ---------- Goban Form ----------------------------------------

   $gobform = new Form( 'goban', $page, FORM_POST );

   if ( is_null($goban_preview) )
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

      $gobform->add_empty_row();
      $gobform->add_row( array(
            'CHAPTER', T_('Load board from game#gobedit'), ));
      $gobform->add_empty_row();
      $gobform->add_row( array(
            'DESCRIPTION', T_('Game-ID#gobedit'),
            'TEXTINPUT',   'gid', 8, 16, $gid ));
      $gobform->add_row( array(
            'DESCRIPTION', T_('Move#gobedit'),
            'TEXTINPUT',   'move', 4, 8, $move ));
      $gobform->add_row( array(
            'TAB', 'CELL', 1, '',
            'SUBMITBUTTON', 'gob_load_game', T_('Load DGS-Game#gobedit'), ));

      $gobform->add_empty_row();
      $gobform->add_row( array(
            'CHAPTER', T_('Load board from SGF#gobedit'), ));
      $gobform->add_empty_row();
      $gobform->add_row( array(
            'DESCRIPTION', T_('SGF-file#gobedit'),
            'FILE',        'file_sgf', 40, SGF_MAXSIZE_UPLOAD, 'application/x-go-sgf', true ));
      $gobform->add_row( array(
            'TAB', 'CELL', 1, '',
            'SUBMITBUTTON', 'gob_load_sgf', T_('Upload SGF#gobedit'), ));
   }
   else
   {
      $gobform->add_row( array(
            'CHAPTER', T_('Edit Area#gobedit'), ));
      $gobform->add_row( array(
            'TEXTAREA', 'board', 10 + 2 * $width, $height + 7, $board_text, ));
      $gobform->add_row( array(
            'CELL', 1, '',
            'SUBMITBUTTON', 'gob_preview', T_('Preview'),
            'TEXT', SMALL_SPACING,
            'SUBMITBUTTON', 'gob_swcol', T_('Switch Colors#gobedit'),
            'TEXT', MINI_SPACING,
            'SUBMITBUTTON', 'gob_flatten', T_('Flatten#gobedit'),
            'TEXT', MINI_SPACING,
            'SUBMITBUTTONX', 'gob_save_shape', T_('Save Shape#gobedit'), array( 'disabled' => ($width != $height) ) ));
      $gobform->add_row( array(
            'HIDDEN', 'width', $width,
            'HIDDEN', 'height', $height,
            'HIDDEN', 'shape', $shape_id, ));
   }

   // ---------- END form ------------------------------------------


   $title = T_('Goban Editor');
   start_page( $title, true, $logged_in, $player_row,
               GobanHandlerGfxBoard::style_string( $cfg_board->get_stone_size() ) );
   echo "<h3 class=Header>$title</h3>\n";

   if ( !is_null($errors) && count($errors) )
      echo buildErrorListString( T_('There are some errors'), $errors ), "<p>\n";

   if ( is_null($goban_preview) )
      $gobform->echo_string();
   else
   {
      echo
         "<table id=GobanEditor class=GobanEditor>\n",
            "<tr>",
               "<td id=PreviewArea>", $goban_preview, "</td>\n",
               "<td id=EditArea>", $gobform->get_form_string(), "</td>",
            "</tr>\n",
         "</table>\n";
   }

   $notes = array();
   $notes[] = array( T_('<tt>&lt;igoban SL1>TITLE BOARD %%%% TEXT&lt;/igoban></tt> - Go-Diagram with &lt;igoban>-tag#gobedit'),
         T_('BOARD-lines start with (optional) "$$", a space " " has meaning#gobedit'),
         T_('TEXT-block is optional, initiated with empty line or "%%%%" below diagram#gobedit'),
         T_('TEXT-block is shown to right of diagram or below if "%%%%" is present#gobedit'),
      );
   $notes[] = array( T_('TITLE-format: <tt>$$[color][c][size][movenum][title]</tt>#gobedit'),
         T_('<tt>color "B|W"</tt> = color of first numbered stone#gobedit'),
         T_('<tt>"c"</tt> = enables board coordinates#gobedit'),
         T_('<tt>size</tt> = board-size#gobedit'),
         T_('<tt>movenum "m99"</tt> = moves-start-number (default: 1)#gobedit'),
         T_('<tt>title</tt> = text at board-bottom#gobedit'),
      );
   $notes[] = array( T_('BOARD-format for borders and intersections:#gobedit'),
         T_('<tt>"."</tt> = empty intersection, <tt>","</tt> = hoshi (auto-hoshi if none used on board)#gobedit'),
         T_('no spaces allowed in border-lines defining edges,<br><tt>"| + -"</tt> = forming edges in 2nd and board-line, <tt>"++ -+ +-"</tt> = short-format edges#gobedit'),
         T_('<tt>"-"</tt> = clears intersection-lines on board, <tt>"_"</tt> = like <tt>"-"</tt> but not at edges#gobedit'),
         T_('<tt>"."</tt> = empty intersection, <tt>","</tt> = hoshi (auto-hoshi if none used on board)#gobedit'),
      );
   $notes[] = array( T_('BOARD-format for diagram markup: "<tt>Diagram-Code - Textual-Code</tt> : <tt>Description</tt>":#gobedit'),
         T_('<tt>X O - BO WO</tt> : black stone, white stone#gobedit'),
         T_('<tt>B|W0..9 - B|W1.100</tt> : numbered black|white stones, W0/B0=W10/B10#gobedit'),
         T_('<tt>B W - BC WC</tt> : black|white stone with circle#gobedit'),
         T_('<tt># @ - BS WS</tt> : black|white stone with square#gobedit'),
         T_('<tt>Y Q - BT WT</tt> : black|white stone with triangle#gobedit'),
         T_('<tt>Z P - BX WX</tt> : black|white stone with cross#gobedit'),
         T_('<tt>C S T M - EC ES ET EM</tt> : circle square triangle cross#gobedit'),
         T_('<tt>a..z - a..z</tt> : letters on empty intersection#gobedit'),
         T_('<tt>A V - TA TV</tt> : black|white-territory#gobedit'),
         T_('<tt>~ = - T~ T=</tt> : neutral-undecided-territory, dame-territory#gobedit'),
      );
   $notes[] = array( T_('BOARD-format for specialties:#gobedit'),
         T_('<tt>$$ [ref|link]</tt> : add link to <tt>ref</tt>-label on board, e.g. <tt>$$ [a|NadareJoseki]</tt><br>'
            . 'link <tt>"dgs:faq.php"</tt> = link to DGS-page<br>'
            . 'link <tt>"#123"</tt> = link to DGS-thread-anchor in DGS-forums<br>'
            . 'link <tt>"http://senseis.xmp.net"</tt> = link to external page<br>'
            . 'link <tt>"NadareJoseki"</tt> = link to wiki-topic on Sensei\'s Library#gobedit'),
         T_('differences to original SL-format: no big support of irregular boards, easier borders,<br>territory-markup, no lines markup, no arrow markup, no inline-images#gobedit'),
      );
   $notes[] = T_('also see Sensei\'s Library: <http://senseis.xmp.net/?HowDiagramsWork>#gobedit');

   echo_notes( 'gobanEditNotes', T_('Syntax description#gobedit'), $notes );


   $menu_array = array();
   $menu_array[T_('Shapes')] = "list_shapes.php";
   $menu_array[T_('New Goban')] = $page . (is_null($goban_preview) ? '' : "?width=$width".URI_AMP."height=$height");

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

function load_igoban_from_dgs_game( $gid, $move )
{
   $reader = new GobanHandlerDgsGame();
   $goban = $reader->read_goban( sprintf("<game %s%s>", (int)$gid, (is_numeric($move) ? ",$move" : '') ));
   $exporter = new GobanHandlerSL1( MarkupHandlerGoban::attribute_split( 'SL1' ) );
   $board_text = $exporter->write_goban( $goban );

   return array( $board_text, $goban->max_y, $goban->max_y, true ); // text, wid/hei, do-preview
}//load_igoban_from_dgs_game

function load_igoban_from_shape_game( $shape_id )
{
   $shape = Shape::load_shape( $shape_id, /*with-user*/false );
   if ( !$shape )
      error('unknown_shape', "goban_editor.load_igoban_from_shape_game($shape_id)");

   return create_goban_from_extended_snapshot( $shape->Snapshot, $shape->Size );
}//load_igoban_from_shape_game

// switches-color or "flattens" goban and re-creates <igoban>-tag from manipulated Goban
function update_igoban( $board_text, $goban_operation )
{
   if ( $goban_operation != 'switch_colors' && $goban_operation != 'flatten_for_shape_game' )
      error('invalid_method', "goban_editor.update_igoban($goban_operation)");

   list( $tmp, $arr_goban ) = MarkupHandlerGoban::replace_igoban_tags_collect_goban( $board_text );
   if ( count($arr_goban) )
   {
      $goban = $arr_goban[0];
      $exporter = new GobanHandlerSL1( MarkupHandlerGoban::attribute_split( 'SL1' ) );
      call_user_func( array( $goban, $goban_operation ) );
      $board_text = $exporter->write_goban( $goban );
      $do_preview = true;
   }

   return array( $board_text, true ); // text, do-preview
}//update_igoban

function load_igoban_from_sgf( $file_sgf_arr )
{
   global $do_preview;

   // upload SGF and parse into Goban
   $errors = NULL;
   $board_text = NULL;
   $upload = new FileUpload( $file_sgf_arr, SGF_MAXSIZE_UPLOAD );
   if ( $upload->is_uploaded() && !$upload->has_error() )
   {
      $sgf_data = @read_from_file( $upload->get_file_src_tmpfile() );
      if ( $sgf_data !== false )
      {
         $do_preview = true;
         $sgf_parser = GameSgfParser::parse_sgf( $sgf_data );
         list( $board_text, $err ) = create_igoban_from_parsed_sgf( $sgf_parser );
         if ( $err )
            $errors = array( $err );
      }
   }
   if ( $upload->has_error() )
      $errors = $upload->get_errors();
   @$upload->cleanup();

   return array( $errors, $board_text );
}//load_igoban_from_sgf

// create <igoban>-tag from SGF parsed with Sgf::sgf_parser(), see also 'include/sgf_parser.php'
// return [ board_text, error|'' ]
function create_igoban_from_parsed_sgf( $sgf_parser )
{
   global $width, $height;

   $size = $sgf_parser->Size;
   if ( $size >= MIN_BOARD_SIZE && $size <= MAX_BOARD_SIZE )
      $width = $height = $size;

   $board = new Board( 0, $size ); // need board to really "move" (with capturing stones)
   $board->init_board();

   // handle setup B/W-stone
   foreach ( array( BLACK, WHITE ) as $stone )
   {
      $arr_coords = ( $stone == BLACK ) ? $sgf_parser->SetBlack : $sgf_parser->SetWhite;
      foreach ( $arr_coords as $sgf_coord )
      {
         list($x,$y) = sgf2number_coords($sgf_coord, $size);
         $board->array[$x][$y] = $stone;
      }
   }

   // handle B/W-moves on board handling captures
   $gchkmove = new GameCheckMove( $board );
   $Black_Prisoners = $White_Prisoners = 0;
   $Last_Move = '';
   $GameFlags = 0;
   $to_move = BLACK;
   $parse_error = '';
   foreach ( $sgf_parser->Moves as $move ) // move = B|W sgf-coord, e.g. "Baa", "Wbb"
   {
      if ( $move[0] == 'B' )
         $to_move = BLACK;
      elseif ( $move[0] == 'W' )
         $to_move = WHITE;
      else
         continue; // unknown value
      $sgf_move = substr($move, 1);

      $err = $gchkmove->check_move( $sgf_move, $to_move, $Last_Move, $GameFlags, /*exit*/false);
      if ( $err )
      {
         $board_pos = sgf2board_coords( $sgf_move, $size );
         $parse_error = sprintf( T_('Parsing SGF stopped: Error [%s] at position [%s] found!'), $err, $board_pos );
         break;
      }
      $gchkmove->update_prisoners( $Black_Prisoners, $White_Prisoners );

      if ( $gchkmove->nr_prisoners == 1 )
         $GameFlags |= GAMEFLAGS_KO;
      else
         $GameFlags &= ~GAMEFLAGS_KO;
      $Last_Move = $sgf_move;
   }

   // parse Board into Goban
   $goban = new Goban();
   $goban->setOptionsCoords( GOBB_MID, true );
   $goban->setSize( $size, $size );
   $goban->makeBoard( $size, $size, /*withHoshi*/true );
   foreach ( $board->array as $x => $arr_y )
   {
      foreach ( $arr_y as $y => $stone )
      {
         if ( $stone == BLACK )
            $goban_stone = GOBS_BLACK;
         elseif ( $stone == WHITE )
            $goban_stone = GOBS_WHITE;
         else
            continue;

         $goban->setStone( $x+1, $y+1, $goban_stone );
      }
   }

   $exporter = new GobanHandlerSL1( MarkupHandlerGoban::attribute_split( 'SL1' ) );
   $board_text = $exporter->write_goban( $goban );

   return array( $board_text, $parse_error );
}//create_igoban_from_parsed_sgf

function create_goban_from_extended_snapshot( $snapshot, $size=null )
{
   if ( is_null($size) && preg_match("/ S(\d+)/", $snapshot, $matches) )
      $size = (int)@$matches[1];
   if ( $size < MIN_BOARD_SIZE || $size > MAX_BOARD_SIZE )
      error('invalid_args', "goban_editor.create_goban_from_extended_snapshot.bad_size($size,$snapshot)");
   if ( is_null($size) )
      error('miss_snapshot_size', "goban_editor.create_goban_from_extended_snapshot($snapshot)");

   $arr_xy = GameSnapshot::parse_stones_snapshot( $size, $snapshot, GOBS_BLACK, GOBS_WHITE );
   $goban = Goban::create_goban_from_stones_snapshot( $size, $arr_xy );

   $exporter = new GobanHandlerSL1( MarkupHandlerGoban::attribute_split( 'SL1' ) );
   $board_text = $exporter->write_goban( $goban );

   return array( $board_text, $size, $size, true ); // text, wid/hei, do-preview
}//create_goban_from_extended_snapshot

?>

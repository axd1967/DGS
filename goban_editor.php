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
   $width  = (int)get_request_arg('width', 9);
   $height = (int)get_request_arg('height', 9);
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
            'TEXTAREA', 'board', 10 + 2 * $width, $height + 7, $board_text, ));
      $gobform->add_row( array(
            'CELL', 1, '',
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

   if( is_null($goban_preview) )
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
   $notes[] = array( T_('BOARD-format for diagram markup: <tt>Diagram-Code - Textual-Code</tt> : Description:#gobedit'),
         T_('<tt>X O - BO WO</tt> : black stone, white stone#gobedit'),
         T_('<tt>B|W0..9 - B|W1.100</tt> : numbered black|white stones, W0/B0=W10/B10#gobedit'),
         T_('<tt>B W - BC WC</tt> : black|white stone with circle#gobedit'),
         T_('<tt># @ - BS WS</tt> : black|white stone with square#gobedit'),
         T_('<tt>Y Q - BT WT</tt> : black|white stone with triangle#gobedit'),
         T_('<tt>Z P - BX WX</tt> : black|white stone with cross#gobedit'),
         T_('<tt>C S T M - EC ES ET EM</tt> : circle square triangle cross#gobedit'),
         T_('<tt>a..z - a..z</tt> : letters on empty intersection#gobedit'),
         T_('<tt>* ~ - T* T~</tt> : black|white-teritory#gobedit'),
         T_('<tt>? = - T? T=</tt> : neutral-undecided-teritory, dame-teritory#gobedit'),
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

?>

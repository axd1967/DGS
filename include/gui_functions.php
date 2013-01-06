<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Common";

require_once( 'include/globals.php' );
require_once( 'include/std_functions.php' );
require_once( 'include/time_functions.php' );


/*!
 * \file gui_functions.php
 *
 * \brief Collection of GUI-related functions.
 */

define('BUTTON_TEXT', -1); // Players.Button


/*!
 * \brief Return the attributes of a warning cellule with class and title-attributes:
 *        - return as map with keys 'class' and 'title', if return_array-arg is true
 *        - otherwise return as string.
 */
function warning_cell_attb( $title='', $return_array=false )
{
   if( $return_array )
   {
      $result = array('class' => 'Warning');
      if( $title ) $result['title'] = $title;
   }
   else
   {
      $result = ' class=Warning';
      if( $title ) $result .= ' title=' . attb_quote($title);
   }
   return $result;
}

function is_valid_button( $button_nr )
{
   return is_numeric($button_nr) && ($button_nr >= BUTTON_TEXT) && ($button_nr < BUTTON_MAX);
}

/*! \brief Return the global style part of a table with buttons. */
function button_style( $button_nr=0 )
{
   global $base_path, $buttoncolors, $buttonfiles;

   if( !is_valid_button($button_nr) )
      $button_nr = 0;

   if( $button_nr == BUTTON_TEXT )
      return '';

   return
      "table.Table a.Button { color: {$buttoncolors[$button_nr]}; }\n" .
      "table.Table td.Button { background-image: url({$base_path}images/{$buttonfiles[$button_nr]}); }";
}

function name_anchor( $name )
{
   return "<a name=\"$name\"></a>\n";
}

/*!
 * \brief Return the cell part of a button with anchor.
 * \note Needs button_style(..) in start_page()-func-call;
 */
function button_TD_anchor( $href, $text='', $title='' )
{
   global $player_row;
   $class2 = ( @$player_row['Button'] == BUTTON_TEXT ) ? ' ButtonText' : '';

   $titlestr = ($title != '') ? " title=\"$title\"" : '';
   return ($href)
      ? "<a class=\"Button$class2\" href=\"$href\"$titlestr>$text</a>"
      : "<a class=Button$titlestr>$text</a>";
}

/*!
 * \brief Return a stratagem to force a minimal column width.
 * Must be inserted before a cell inner text at least one time for a column.
 */
function button_TD_insert_width( $width=false )
{
   if( !is_numeric($width) )
      $width = BUTTON_WIDTH;
   return insert_width( $width, 0, true ); // with MinWidth-class
}

function TD_button( $title, $href, $isrc, $ialt)
{
   //image( $src, $alt, $title='', $attbs='', $height=-1, $width=-1)
   $str = image( $isrc, $ialt, $title);
   //anchor( $href, $text, $title='', $attbs='')
   $str = anchor( $href, $str);
   $str = "<td class=Button>$str</td>\n";
   return $str;
}

/*! \brief Prints notes in formatted table if there are notes. */
function echo_notes( $table_id, $title, $notes, $pre_sep=true, $html_safe=true )
{
   if( !is_array($notes) || count($notes) == 0 )
      return;

   echo ( $pre_sep ? "<br><br>\n" : '' ),
      "<table id=\"{$table_id}\">\n";
   if( $title != '' )
      echo "<tr><th>", make_html_safe($title, 'line'), "</th></tr>\n";
   echo "<tr><td><ul class=\"Notes\">\n";
   foreach( $notes as $note )
   {
      if( is_null($note) || (string)$note === '' )
         echo "<p></p>\n";
      elseif( is_array($note) && isset($note['text']) )
      {
         $safe = (bool)@$note['safe'];
         echo '  <li>', ( $safe ? make_html_safe($note['text'], 'line') : $note['text'] ), "\n";
      }
      elseif( is_array($note) )
      {
         echo '  <li>', array_shift( $note ), "\n<ul class=\"SubNotes\">\n"; // note-title
         foreach( $note as $note_item )
            echo "<li>$note_item\n";
         echo "</ul>\n";
      }
      else
         echo '  <li>', ( $html_safe ? make_html_safe($note, 'line') : $note ), "\n";
   }
   echo "</ul>\n", "</td></tr></table>\n";
}

/*! \brief Returns image-tag with vacation-image if on_vacation set; return '' otherwise. */
function echo_image_vacation( $on_vacation=true, $vacText='', $game_clock_stopped=false )
{
   if( (is_numeric($on_vacation) && $on_vacation > 0) || $on_vacation )
   {
      global $base_path;
      $title = T_('On vacation');
      if( $vacText != '' )
         $title .= " ($vacText)";
      if( $game_clock_stopped )
         $title .= ', ' . T_('Game clock stopped');
      $attbs = ($on_vacation === true ) ? '' : 'class="InTextImage"';
      return image( $base_path.'images/vacation.gif', $title, null, $attbs );
   }
   else
      return '';
}

/*! \brief Returns image-tag with night-time-image if $sleeping set; return '' otherwise. */
function echo_image_nighttime( $sleeping=true, $game_clock_stopped=false )
{
   if( $sleeping )
   {
      global $base_path;
      $title = T_('User in sleeping time') . ($game_clock_stopped ? ', ' . T_('Game clock stopped') : '');
      $attbs = ($sleeping === true ) ? '' : 'class="InTextImage"';
      return image( $base_path.'images/night.gif', $title, null, $attbs );
   }
   else
      return '';
}

/*! \brief Returns image-tag for weekend-clock. */
function echo_image_weekendclock( $weekend=true, $game_clock_stopped=true )
{
   if( $weekend )
   {
      global $base_path;
      $title = T_('Weekend (UTC)') . ($game_clock_stopped ? ', ' . T_('Game clock stopped') : '');
      return image( $base_path.'images/wclock_stop.gif', $title, null, 'class="InTextImage"' );
   }
   else
      return '';
}

/*! \brief Returns image-tag for admin with given admin-level. */
function echo_image_admin( $adminlevel, $withSep=true )
{
   if( $adminlevel & ADMINGROUP_EXECUTIVE )
   {
      global $base_path;
      $title = T_('Dragon executive');
      return ($withSep ? MINI_SPACING : '')
         . anchor( $base_path.'people.php#executives',
                   image( $base_path.'images/admin.gif', $title, null, 'class="InTextImage"' ) );
   }
   else
      return '';
}

/*! \brief Returns image-tag for being-online if online; return '' otherwise. */
function echo_image_online( $in_the_house=true, $last_access=0, $withSep=true )
{
   if( $in_the_house )
   {
      global $base_path, $NOW;
      $title = T_('Online');
      if( $last_access > 0 )
      {
         $mins_ago = round( ($NOW - $last_access + SECS_PER_MIN - 1) / SECS_PER_MIN );
         if( $mins_ago >= 0 )
            $title = sprintf( T_('Online &lt;%s mins ago'), $mins_ago );
      }
      return ($withSep ? MINI_SPACING : '')
         . image( $base_path.'images/online.gif', $title, null, 'class="InTextImage"' );
   }
   else
      return '';
}

/*!
 * \brief Returns off-time echo-string for certain player.
 * \param $on_vacation true|false | vacation-days
 * \param $player_clock_used null | int
 */
function echo_off_time( $player_to_move, $on_vacation, $player_clock_used )
{
   if( $on_vacation === true )
      $vac_str = echo_image_vacation();
   elseif( is_numeric($on_vacation) && $on_vacation > 0 )
      $vac_str = echo_image_vacation( $on_vacation,
         TimeFormat::echo_onvacation($on_vacation), $player_to_move );
   else
      $vac_str = '';

   $game_str = '';
   if( !is_null($player_clock_used) )
   {
      if( is_weekend_clock_stopped($player_clock_used) )
         $game_str .= MINI_SPACING . echo_image_weekendclock( true, $player_to_move );
      if( is_nighttime_clock($player_clock_used) )
         $game_str .= MINI_SPACING . echo_image_nighttime( 'in_text', $player_to_move );
   }

   $result = $vac_str . $game_str;
   return ($result) ? SMALL_SPACING . $result : '';
}

/*! \brief Returns image to game-info page for given game-id. */
function echo_image_gameinfo( $gid, $with_sep=false, $board_size=null, $snapshot=null )
{
   global $base_path;

   if( is_numeric($board_size) && !is_null($snapshot) && is_javascript_enabled() )
   {
      $img_str = image( $base_path.'images/info.gif', '', null, 'class="InTextImage"');
      $link = anchor( $base_path."gameinfo.php?gid=$gid", $img_str, '',
         array(
            'onmouseover' => sprintf( "showGameThumbnail(event,%s,'%s');", $board_size, $snapshot ),
            'onmouseout'  => 'hideInfo();' ));
   }
   else
   {
      $img_str = image( $base_path.'images/info.gif', T_('Game information'), null, 'class="InTextImage"');
      $link = anchor( $base_path."gameinfo.php?gid=$gid", $img_str );
   }

   return ($with_sep ? ' ' : '' ) . $link;
}

/*!
 * \brief Returns image to shape-info page for given shape-id.
 * \param $edit_goban false = link to view-shape-page, true = link to goban-editor
 */
function echo_image_shapeinfo( $shape_id, $board_size, $snapshot, $edit_goban=false, $with_sep=false )
{
   if( $shape_id == 0 || (string)$snapshot == '' )
      return '';

   global $base_path;
   $page_url = $base_path . ( ($edit_goban) ? 'goban_editor.php' : 'view_shape.php' ) . "?shape=$shape_id";

   if( is_numeric($board_size) && !is_null($snapshot) )
   {
      $ext_snapshot = (strpos($snapshot, ' ') !== false)
         ? $snapshot // already extended
         : "$snapshot S$board_size";
      $page_url .= URI_AMP."snapshot=".urlencode($ext_snapshot);

      if( is_javascript_enabled() )
      {
         $img_str = image( $base_path.'images/shape.gif', '', null, 'class="InTextImage"');
         $link = anchor( $page_url, $img_str, '',
            array(
               'onmouseover' => sprintf( "showGameThumbnail(event,%s,'%s');", $board_size, $snapshot ),
               'onmouseout'  => 'hideInfo();' ));
      }
      else
      {
         $img_str = image( $base_path.'images/shape.gif', T_('Shape'), null, 'class="InTextImage"');
         $link = anchor( $page_url, $img_str );
      }
   }
   else
   {
      $img_str = image( $base_path.'images/shape.gif', T_('Shape Information'), null, 'class="InTextImage"');
      $link = anchor( $page_url, $img_str );
   }

   return ($with_sep ? ' ' : '' ) . $link;
}

/*! \brief Returns image to tournament-info page for given tournament-id. */
function echo_image_tournament_info( $tid, $with_sep=false, $img_only=false )
{
   if( ALLOW_TOURNAMENTS && $tid > 0 )
   {
      global $base_path;
      $img_str = image( $base_path.'images/tourney.gif',
                        ($img_only ? T_('Tournaments') : T_('Tournament info') . ' #' . $tid ),
                        null, 'class="InTextImage"');
      $str_sep = ($with_sep ? ' ' : '' );
      if( $img_only )
         return $str_sep . $img_str;
      else
         return $str_sep . anchor( $base_path."tournaments/view_tournament.php?tid=$tid", $img_str );
   }
   else
      return '';
}

/*!
 * \brief Returns image indicating that game have hidden game-comments for given game-id.
 * \param $gid maybe 0 for shape-info
 * \param $hidden_comments true (for default text), or text to be used
 */
function echo_image_gamecomment( $gid, $hidden_comments=true )
{
   global $base_path;
   $arr = array();
   if( $hidden_comments )
      $arr[] = ($hidden_comments === true) ? T_('Game has hidden comments') : $hidden_comments;
   return image( $base_path.'images/game_comment.gif', implode(', ', $arr), null, 'class="InTextImage"');
}

/*! \brief Returns image indicating there's a note. */
function echo_image_note( $text, $withSep=true )
{
   return ($withSep ? ' ' : '')
      . image( $GLOBALS['base_path'].'images/note.gif', $text, null, 'class="InTextImage"');
}

/*! \brief Returns image-tag for table-list with link. */
function echo_image_table( $url, $title, $withSep=true )
{
   global $base_path;
   return ($withSep ? MINI_SPACING : '')
      . anchor( $url,
         image( $base_path.'images/table.gif', $title, null, 'class="InTextImage"' ),
         $title );
}

/*! \brief Returns image-tag for tournament-round (next-round). */
function echo_image_tourney_next_round()
{
   global $base_path;
   return image( $base_path.'images/next.gif', T_('Next Round#tourney'), null, 'class="InTextImage"' );
}

/*! \brief Returns image-tag for MP-game (linked to game-players-page if game-id > 0 given). */
function echo_image_game_players( $gid, $icon_text='' )
{
   global $base_path;
   if( !$icon_text )
      $icon_text = T_('Multi-Player-Game') . ': ' . T_('Show game-players');
   $text = ($gid <= 0) ? T_('Show multi-player-games') : $icon_text;
   $img = image( $base_path.'images/team.gif', $text, null, 'class="InTextImage"' );
   return ($gid > 0) ? anchor( $base_path."game_players.php?gid=$gid", $img ) : $img;
}

/*!
 * \brief Formats string: <SPACES><TAG_L>str<TAG_R><SPACES>
 * \note spacing('text', 1, 'b'); -> ' <b>text</b> '
 * \note spacing('text', 1, 'ts', '/te'); -> ' <ts>text</te> '
 * \note spacing('text', 1, 'ts', ''); -> ' <ts>text '
 */
function spacing( $str, $space_count=0, $tag_l='', $tag_r=null )
{
   $spc = str_repeat( MINI_SPACING, $space_count );
   if( is_null($tag_r) ) $tag_r = "/$tag_l";
   if( $tag_l ) $tag_l = "<$tag_l>";
   if( $tag_r ) $tag_r = "<$tag_r>";
   return "{$spc}{$tag_l}{$str}{$tag_r}{$spc}";
}

// \param $class 'id=id attr=...' or else 'classname'
function span( $class, $str='', $strfmt='%s', $title='' )
{
   if( $title )
      $title = " title=\"$title\"";
   if( strpos($class,'=') !== false )
      return sprintf( "<span " . attb_build($class) . $title . ">$strfmt</span>", $str );
   else
      return sprintf( "<span class=\"$class\"$title>$strfmt</span>", $str );
}

function formatDate( $date, $defval='', $datefmt=DATE_FMT )
{
   return ($date > 0) ? date($datefmt, $date) : $defval;
}

function formatNumber( $num )
{
   return ($num > 0) ? "+$num" : $num;
}

function build_range_text( $min, $max, $fmt='[%s..%s]', $generic_max=null )
{
   return sprintf( $fmt, $min, $max, $generic_max );
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

require( "include/std_functions.php" );
require( "include/timezones.php" );
require( "include/rating.php" );
require( "include/form_functions.php" );

connect2mysql();

$logged_in = is_logged_in($handle, $sessioncode, $player_row);

if( !$logged_in )
   error("not_logged_in");

$button_nr = $player_row["Button"];

if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
   $button_nr = 0;

start_page(T_("Edit profile"), true, $logged_in, $player_row );

echo "<CENTER>\n";

echo form_start( 'profileform', 'change_profile.php', 'POST' );

echo "    <tr><td><h3><font color=$h3_color>" .
  T_('Personal settings') . ":</font></h3></td></tr>";

echo form_insert_row( 'DESCRIPTION', T_('Userid'),
                      'TEXT', $player_row["Handle"] );
echo form_insert_row( 'DESCRIPTION', T_('Full name'),
                      'TEXTINPUT', 'name', 16, 40, $player_row["Name"] );
echo form_insert_row( 'DESCRIPTION', T_('Email'),
                      'TEXTINPUT', 'email', 16, 80, $player_row["Email"] );
echo form_insert_row( 'DESCRIPTION', T_('Open for matches'),
                      'TEXTINPUT', 'open', 16, 40, $player_row["Open"] );
echo form_insert_row( 'DESCRIPTION', T_('Rank info'),
                      'TEXTINPUT', 'rank', 16, 40, $player_row["Rank"] );

$vals = array( 'dragonrating' => 'dragonrating',
               'eurorank' => 'eurorank',
               'eurorating' => 'eurorating',
               'aga' => 'aga',
               'agarating' => 'agarating',
               'igs' => 'igs',
               'igsrating' => 'igsrating',
               'iytgg' => 'iytgg',
               'nngs' => 'nngs',
               'nngsrating' => 'nngsrating',
               'japan' => 'japan',
               'china' => 'china',
               'korea' => 'korea' );

if( $player_row["RatingStatus"] != 'RATED' )
{
  echo form_insert_row( 'DESCRIPTION', T_('Rating'),
                        'TEXTINPUT', 'rating', 16,16,echo_rating($player_row["Rating"],true),
                        'SELECTBOX', 'ratingtype', 1, $vals, 'dragonrating', false );
}
else
{
  echo form_insert_row( 'DESCRIPTION', T_('Rating'),
                        'TEXT', echo_rating( $player_row["Rating"] ) );
}

$s = 0;
if(!(strpos($player_row["SendEmail"], 'ON') === false) ) $s++;
if(!(strpos($player_row["SendEmail"], 'MOVE') === false) ) $s++;
if(!(strpos($player_row["SendEmail"], 'BOARD') === false) ) $s++;

$vals = array( 0 => T_('Off'),
               1 => T_('Notify only'),
               2 => T_('Moves and messages'),
               3 => T_('Full board and messages') );

echo form_insert_row( 'DESCRIPTION', T_('Email notifications'),
                      'SELECTBOX', 'emailnotify', 1, $vals, $s, false );

$s = $player_row['Lang'];//$the_translator->current_language;
if( strcmp( $s, 'C' ) == 0 ) $s = 'en';

echo form_insert_row( 'DESCRIPTION', T_('Language'),
                      'SELECTBOX', 'language', 1,
                      get_known_languages_with_full_names(), $s, false );

echo form_insert_row( 'DESCRIPTION', T_('Timezone'),
                      'SELECTBOX', 'timezone', 1,
                      get_timezone_array(), $player_row['Timezone'], false );

$vals = array();
for($i=0; $i<24; $i++)
{
  $vals[$i] = sprintf('%02d-%02d',$i,($i+9)%24);
}

echo form_insert_row( 'DESCRIPTION', T_('Nighttime'),
                      'SELECTBOX', 'nightstart', 1, $vals, $player_row["Nightstart"], false );

echo "    <tr><td height=20px>&nbsp;</td></tr>\n";
echo "    <tr><td><h3><font color=$h3_color>" .
  T_('Board graphics') . ":</font></h3></td></tr>\n";

$vals = array( 13 => 13, 17 => 17, 21 => 21, 25 => 25,
               29 => 29, 35 => 35, 42 => 42, 50 => 50 );

echo form_insert_row( 'DESCRIPTION', T_('Stone size'),
                      'SELECTBOX', 'stonesize', 1, $vals, $player_row["Stonesize"], false );

$vals = array();
for($i=1; $i<6; $i++ )
{
  $vals[$i] = '<img width=30 height=30 src="images/smallwood'.$i.'.gif">';
}

echo form_insert_row( 'DESCRIPTION', T_('Wood color'),
                      'RADIOBUTTONS', 'woodcolor', $vals,
                      $player_row["Woodcolor"], false );

$s = $player_row["Boardcoords"];
echo form_insert_row( 'DESCRIPTION', T_('Coordinate sides'),
                      'CHECKBOX', 'coordsleft', 1, 'Left', ($s & LEFT),
                      'CHECKBOX', 'coordsup', 1, 'Up', ($s & UP),
                      'CHECKBOX', 'coordsright', 1, 'Right', ($s & RIGHT),
                      'CHECKBOX', 'coordsdown', 1, 'Down', ($s & DOWN) );

echo form_insert_row( 'DESCRIPTION', T_('Smooth board edge'),
                      'CHECKBOX', 'smoothedge', 1, '', ($s & SMOOTH_EDGE) );

echo "    <TR>\n";
echo "      <TD align=right>" . T_('Game id button') . ":</TD>\n";
echo "      <TD align=left>\n";
echo "        <TABLE border=0 cellspacing=0 cellpadding=3>\n";
echo "          <TR>\n";

for($i=0; $i<=$button_max; $i++)
{
   $font_style = 'color : ' . $buttoncolors[$i] .
   ';  font : bold 100% sans-serif;  text-decoration : none;  width : 90px;';
   $button_style = 'background-image : url(images/' . $buttonfiles[$i] . ');' .
   'background-repeat : no-repeat;  background-position : center;';

   echo '<TD valign=middle><INPUT type="radio" name="button" value=' . $i .
   ( $i == $button_nr ? ' checked' : '') . '></TD>' . "\n" .
   '<td><table><tr><TD width=92 height=21 align=center STYLE="' . $button_style . $font_style .
   '">1348</TD><td width=10></td></tr></table></td>';

   if( $i % 4 == 3 )
      echo "</TR>\n<TR>\n";
}

echo "          </TR>\n";
echo "        </table>\n";
echo "      </TD>\n";
echo "    </TR>\n";

echo "    <TR><TD><BR></TD></TR>\n";
echo form_insert_row( 'SUBMITBUTTON', 'action', T_('Change profile') );
echo form_end();
echo "</CENTER>\n";

end_page();

?>

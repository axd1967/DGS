<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/timezones.php" );
require_once( "include/rating.php" );
require_once( "include/form_functions.php" );
require_once( "include/countries.php" );


{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $button_nr = $player_row["Button"];

   if ( !is_numeric($button_nr) or $button_nr < 0 or $button_nr > $button_max  )
      $button_nr = 0;

   $ratings = array( 'dragonrating' => 'dragonrating',
                     'eurorank' => 'eurorank',
                     'eurorating' => 'eurorating',
                     'aga' => 'aga',
                     'agarating' => 'agarating',
                     'igs' => 'igs',
//                  'igsrating' => 'igsrating',
                     'iytgg' => 'iytgg',
                     'nngs' => 'nngs',
                     'nngsrating' => 'nngsrating',
                     'japan' => 'japan',
                     'china' => 'china',
                     'korea' => 'korea' );

   $notify_mess = array( 0 => T_('Off'),
                         1 => T_('Notify only'),
                         2 => T_('Moves and messages'),
                         3 => T_('Full board and messages') );

   $menu_directions = array('VERTICAL' => T_('Vertical'), 'HORIZONTAL' => T_('Horizontal'));


   $nightstart = array();
   for($i=0; $i<24; $i++)
   {
      $nightstart[$i] = sprintf('%02d-%02d',$i,($i+9)%24);
   }

   $stonesizes = array( 13 => 13, 17 => 17, 21 => 21, 25 => 25,
                        29 => 29, 35 => 35, 42 => 42, 50 => 50 );

   $woodcolors = array();
   for($i=1; $i<16; $i++ )
   {
      if( $i==6 ) $i = 11;
      $woodcolors[$i] = '<img width=30 height=30 src="images/smallwood'.$i.'.gif">';
   }

   $countries[''] = '';
   foreach( $COUNTRIES as $code => $name_array )
      {
         if( $name_array[5] )
            $countries[$code] = $name_array[0];
      }

   asort($countries);

   start_page(T_("Edit profile"), true, $logged_in, $player_row );

   echo "<CENTER>\n";

   $profile_form = new Form( 'profileform', 'change_profile.php', FORM_GET );
   $profile_form->add_row( array( 'HEADER', T_('Personal settings') ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                                  'TEXT', $player_row["Handle"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                                  'TEXTINPUT', 'name', 16, 40,
                                  str_replace( "\"", "&quot;", $player_row["Name"] ) ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Country'),
                                  'SELECTBOX', 'country', 1, $countries,
                                  $player_row["Country"], false ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Email'),
                                  'TEXTINPUT', 'email', 16, 80,
                                  str_replace( "\"", "&quot;", $player_row["Email"] ) ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Open for matches'),
                                  'TEXTINPUT', 'open', 16, 40,
                                  str_replace( "\"", "&quot;", $player_row["Open"] ) ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Rank info'),
                                  'TEXTINPUT', 'rank', 16, 40,
                                  str_replace( "\"", "&quot;", $player_row["Rank"] ) ) );

   if( $player_row["RatingStatus"] != 'RATED' )
   {
      $profile_form->add_row( array( 'DESCRIPTION', T_('Rating'),
                                     'TEXTINPUT', 'rating', 16, 16,
                                     echo_rating($player_row["Rating2"],true),
                                     'SELECTBOX', 'ratingtype', 1, $ratings,
                                     'dragonrating', false ) );
   }
   else
   {
      $profile_form->add_row( array( 'DESCRIPTION', T_('Rating'),
                                     'TEXT', echo_rating( $player_row["Rating2"] ) ) );
   }

   $s = 0;
   if(!(strpos($player_row["SendEmail"], 'ON') === false) ) $s++;
   if(!(strpos($player_row["SendEmail"], 'MOVE') === false) ) $s++;
   if(!(strpos($player_row["SendEmail"], 'BOARD') === false) ) $s++;

   $profile_form->add_row( array( 'DESCRIPTION', T_('Email notifications'),
                                  'SELECTBOX', 'emailnotify', 1, $notify_mess, $s, false ) );

   $langs = get_language_descriptions_translated();
   arsort($langs);
   $langs['C'] = T_('Use browser settings');

   $profile_form->add_row( array( 'DESCRIPTION', T_('Language'),
                                  'SELECTBOX', 'language', 1,
                                  array_reverse($langs), $player_row['Lang'], false ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Timezone'),
                                  'SELECTBOX', 'timezone', 1,
                                  get_timezone_array(), $player_row['Timezone'], false ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Nighttime'),
                                  'SELECTBOX', 'nightstart', 1,
                                  $nightstart, $player_row["Nightstart"], false ) );

   $profile_form->add_row( array( 'HEADER', T_('Board graphics') ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Stone size'),
                                  'SELECTBOX', 'stonesize', 1, $stonesizes,
                                  $player_row["Stonesize"], false ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Wood color'),
                                  'RADIOBUTTONS', 'woodcolor', $woodcolors,
                                  $player_row["Woodcolor"] ) );

   $s = $player_row["Boardcoords"];
   $profile_form->add_row( array( 'DESCRIPTION', T_('Coordinate sides'),
                                  'CHECKBOX', 'coordsleft', 1, T_('Left'), ($s & LEFT),
                                  'CHECKBOX', 'coordsup', 1, T_('Up'), ($s & UP),
                                  'CHECKBOX', 'coordsright', 1, T_('Right'), ($s & RIGHT),
                                  'CHECKBOX', 'coordsdown', 1, T_('Down'), ($s & DOWN) ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Smooth board edge'),
                                  'CHECKBOX', 'smoothedge', 1, '', ($s & SMOOTH_EDGE) ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Menu direction'),
                                  'RADIOBUTTONS', 'menudir', $menu_directions,
                                  $player_row["MenuDirection"] ) );

   $button_code  = "      <TD align=right>" . T_('Game id button') . ":</TD>\n";
   $button_code .= "      <TD align=left>\n";
   $button_code .= "        <TABLE border=0 cellspacing=0 cellpadding=3>\n";
   $button_code .= "          <TR>\n";

   for($i=0; $i<=$button_max; $i++)
   {
      $font_style = 'color : ' . $buttoncolors[$i] .
         ';  font : bold 100% sans-serif;  text-decoration : none;  width : 90px;';
      $button_style = 'background-image : url(images/' . $buttonfiles[$i] . ');' .
         'background-repeat : no-repeat;  background-position : center;';

      $button_code .= '<TD valign=middle><INPUT type="radio" name="button" value=' . $i .
         ( $i == $button_nr ? ' checked' : '') . '></TD>' . "\n" .
         '<td><table><tr><TD width=92 height=21 align=center STYLE="' . $button_style . $font_style .
         '">1348</TD><td width=10></td></tr></table></td>';

      if( $i % 4 == 3 )
         $button_code .= "</TR>\n<TR>\n";
   }

   $button_code .= "          </TR>\n";
   $button_code .= "        </table>\n";
   $button_code .= "      </TD>\n";

   $profile_form->add_row( array( 'OWNHTML', $button_code ) );

   $profile_form->add_row( array( 'TD',
                                  'OWNHTML', '<TD>',
                                  'CHECKBOX', 'locally', 1,
                                  T_('Change board graphics for this browser only'),
                                  !empty($_COOKIE["prefs{$player_row['ID']}"]) ) );

   $profile_form->add_row( array( 'OWNHTML', '<TD>&nbsp;</TD>' ) );

   $profile_form->add_row( array( 'SUBMITBUTTON', 'action', T_('Change profile') ) );

   $profile_form->echo_string();
   echo "</CENTER>\n";

   end_page();
}

?>

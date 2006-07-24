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

// Reminder: to friendly reset the language:
// http://www.dragongoserver.net/edit_profile.php?language=C
// http://www.dragongoserver.net/edit_profile.php?language=en

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

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

   $stonesizes = array( 5 => 5, 7 => 7, 9 => 9, 11 => 11,
                        13 => 13, 17 => 17, 21 => 21, 25 => 25,
                        29 => 29, 35 => 35, 42 => 42, 50 => 50 );

   $woodcolors = array();
   for($i=1; $i<16; $i++ )
   {
      $woodcolors[$i] = "<img width=30 height=30 src=\"images/smallwood$i.gif\" alt=\"wood$i\">&nbsp;&nbsp;";
      if( $i==5 ) 
      {
         $woodcolors[$i].= '<BR>';
         $i = 10;
      }
   }

   foreach( $COUNTRIES as $code => $country )
      $COUNTRIES[$code] = T_($country);

   asort($COUNTRIES);
   array_unshift($COUNTRIES, '');
   
   $notesheights = array();
   for($i=5; $i<26; $i++ )
     {
     $notesheights[$i] = $i;
     }
   
   $noteswidths = array();
   for($i=3; $i<16; $i++ )
     {
     $noteswidths[$i*5] = $i * 5;
     }
   
   $notesmodes = array('OFF' => T_('Off'), 'RIGHT' => T_('Right'), 'BELOW' => T_('Below'));

   $notescutoffs = array();
   for($i=5; $i<26; $i++ )
     {
     $notescutoffs[$i] = $i;
     }


//------------

   start_page(T_("Edit profile"), true, $logged_in, $player_row );

   echo "<CENTER>\n";

   $profile_form = new Form( 'profileform', 'change_profile.php', FORM_GET );


   $profile_form->add_row( array( 'HEADER', T_('Personal settings') ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                                  'TEXT', $player_row["Handle"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                                  'TEXTINPUT', 'name', 32, 40,
                                  $player_row["Name"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Country'),
                                  'SELECTBOX', 'country', 1, $COUNTRIES,
                                  $player_row["Country"], false ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Email'),
                                  'TEXTINPUT', 'email', 32, 80,
                                  $player_row["Email"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Open for matches'),
                                  'TEXTINPUT', 'open', 32, 40,
                                  $player_row["Open"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Rank info'),
                                  'TEXTINPUT', 'rank', 32, 40,
                                  $player_row["Rank"] ) );

   if( $player_row["RatingStatus"] != 'RATED' )
   {
      $profile_form->add_row( array( 'DESCRIPTION', T_('Rating'),
                                     'TEXTINPUT', 'rating', 16, 16,
                                     echo_rating($player_row["Rating2"],2,0,1),
                                     'SELECTBOX', 'ratingtype', 1, $ratings,
                                     'dragonrating', false ) );
   }
   else
   {
      $profile_form->add_row( array( 'DESCRIPTION', T_('Rating'),
                                     'TEXT', echo_rating($player_row["Rating2"],2,0,0 ) ) );
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



   //--- Followings may be browser settings ---

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'HR' ) );

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
                                  'CHECKBOX', 'coordsdown', 1, T_('Down'), ($s & DOWN),
                                  'CHECKBOX', 'coordsover', 1, T_('Over'), ($s & OVER),
                                ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Smooth board edge'),
                                  'CHECKBOX', 'smoothedge', 1, '', ($s & SMOOTH_EDGE) ) );

   if( ENA_MOVENUMBERS )
   $profile_form->add_row( array( 'DESCRIPTION', T_('Move numbering'),
                                  'TEXTINPUT', 'movenumbers', 4, 4, $player_row['MoveNumbers'],
                                  //'CHECKBOX', 'movenumover', 1, '', ($s & MOVE_NUMBER),
                                ) );

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Menu direction'),
                                  'RADIOBUTTONS', 'menudir', $menu_directions,
                                  $player_row["MenuDirection"] ) );


   $button_code  = "        <TABLE border=0 cellspacing=0 cellpadding=2 width=520>\n";
   $button_code .= "          <TR>\n";

   for($i=0; $i<=$button_max; $i++)
   {
      $font_style = ' text-align: center; color: ' . $buttoncolors[$i] .
         '; font: bold 100% sans-serif; text-decoration: none; width: '.$button_width.'px;';
      $button_style = 'background-image: url(images/' . $buttonfiles[$i] .
         '); background-repeat: no-repeat; background-position: center;';

      $button_code .= '<td valign=middle><INPUT type="radio" name="button" value=' . $i .
         ( $i == $button_nr ? ' checked' : '') . '></td>' . "\n" .
         '<td STYLE="' . $font_style . $button_style .
         '">1348</td><td width=14></td>' . "\n";

      if( $i % 4 == 3 )
         $button_code .= "</TR>\n<TR>\n";
   }

   $button_code .= "          </TR>\n";
   $button_code .= "        </table>\n";

      $profile_form->add_row( array(
               'DESCRIPTION', T_('Game id button'),
               'TEXT', $button_code,
            ) );



   $profile_form->add_row( array( 'HEADER', T_('Private game notes') ) );

   foreach( array( 'Small', 'Large') as $typ )
   {
      $ltyp = strtolower($typ) ;
      if( $ltyp == 'small' )
         $profile_form->add_row( array(
               'TEXT', '<b>' . T_('Small boards') . ':</b>',
               'TAB',
            ) );
      else
         $profile_form->add_row( array(
               'TEXT', '<b>' . T_("Large boards from") . ':</b>',
               'TD', 'SELECTBOX', 'notescutoff', 1, $notescutoffs, $player_row["NotesCutoff"], false,
            ) );

      $profile_form->add_row( array(
               'DESCRIPTION', T_('Position'),
               'RADIOBUTTONS', "notes{$ltyp}mode", $notesmodes, $player_row["Notes{$typ}Mode"],
            ) );

      $profile_form->add_row( array(
               'DESCRIPTION', T_('Size'),
               'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('Height') . '&nbsp;',
               'SELECTBOX', "notes{$ltyp}height", 1, $notesheights, $player_row["Notes{$typ}Height"], false,
               'TEXT', '&nbsp;&nbsp;&nbsp;' . T_('Width') . '&nbsp;',
               'SELECTBOX', "notes{$ltyp}width", 1, $noteswidths, $player_row["Notes{$typ}Width"], false,
            ) );
   }

   //--- End of browser settings ---



   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'TAB',
                                  'CHECKBOX', 'locally', 1,
                                  T_('Change board graphics for this browser only'),
                                  !empty($_COOKIE[COOKIE_PREFIX."prefs{$player_row['ID']}"]) ) );

   $profile_form->add_row( array( 'HR' ) );

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'SUBMITBUTTON', 'action', T_('Change profile') ) );


   $profile_form->echo_string(1);
   echo "</CENTER>\n";

   end_page();
}

?>

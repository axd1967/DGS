<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/timezones.php" );
require_once( "include/countries.php" );
require_once( "include/rating.php" );
require_once( "include/form_functions.php" );

// Reminder: to friendly reset the language:
// {HOSTBASE}edit_profile.php?language=C
// {HOSTBASE}edit_profile.php?language=en

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   $button_nr = $player_row['Button'];

   if( !is_numeric($button_nr) || $button_nr < 0 || $button_nr > $button_max  )
      $button_nr = 0;

   $ratings = array( 'dragonrating' => 'dragonrating',
                     'eurorank' => 'eurorank',
                     'eurorating' => 'eurorating',
                     'aga' => 'aga',
                     'agarating' => 'agarating',
                     'igs' => 'igs',
//                  'igsrating' => 'igsrating',
                     'iytgg' => 'iytgg',
                     'japan' => 'japan',
                     'china' => 'china',
                     'korea' => 'korea' );

   $notify_mess = array( 0 => T_('Off'),
                         1 => T_('Notify only'),
                         2 => T_('Moves and messages'),
                         3 => T_('Full board and messages') );

   $menu_directions = array('VERTICAL' => sptext(T_('Vertical'),2),
                           'HORIZONTAL' => sptext(T_('Horizontal')) );


   $nightstart = array();
   for($i=0; $i<24; $i++)
   {
      $nightstart[$i] = sprintf('%02d-%02d',$i,($i+NIGHT_LEN)%24);
   }

   $stonesizes = array( 5 => 5, 7 => 7, 9 => 9, 11 => 11,
                        13 => 13, 17 => 17, 21 => 21, 25 => 25,
                        29 => 29, 35 => 35, 42 => 42, 50 => 50 );

   $woodcolors = array();
   for($i=1; $i<16; $i++ )
   {
      $tmp = "<img width=30 height=30 src=\"images/smallwood$i.gif\" alt=\"wood$i\">";
      if( $i==5 )
      {
         $woodcolors[$i] = sptext($tmp).'<BR>';
         $i = 10;
      }
      else
         $woodcolors[$i] = sptext($tmp,2);
   }

   asort($COUNTRIES);
   $countries = $COUNTRIES;
   array_unshift($countries, '');

   $langs = get_language_descriptions_translated();
   //it's not obvious that this sort on "translated" strings will always give a good result:
   arsort($langs); //will be reversed to place ahead the following:
   if( @$player_row['Translator'] )
      $langs['N'] = /**T_**/('Native texts');
   $langs['C'] = T_('Use browser settings');

   $notesheights = array();
   for($i=5; $i<26; $i++ )
   {
      $notesheights[$i] = $i;
   }

   $noteswidths = array();
   for($i=15; $i<105; $i+=5 )
   {
      $noteswidths[$i] = $i;
   }

   $notesmodes = array('RIGHT' => sptext(T_('Right'),2),
                     'BELOW' => sptext(T_('Below'),2));

   $notescutoffs = array();
   for($i=5; $i<26; $i++ )
   {
      $notescutoffs[$i] = $i;
   }

   include_once( 'skins/known_skins.php' );
//   $known_skins = array('dragon' => 'Dragon Go Server original');

   $tablemaxrows = build_maxrows_array(0, MAXROWS_PER_PAGE_PROFILE); // array( 10 => 10, ...)


//------------

   start_page(T_("Edit profile"), true, $logged_in, $player_row );

   $profile_form = new Form( 'profileform', 'change_profile.php', FORM_GET );


   $profile_form->add_row( array( 'HEADER', T_('Personal settings') ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                                  'TEXT', $player_row["Handle"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                                  'TEXTINPUT', 'name', 32, 40,
                                  $player_row["Name"] ) );

   if( $player_row['RatingStatus'] != 'RATED' )
   {
      $row= array('DESCRIPTION', T_('Rating'),
                  'TEXTINPUT', 'rating', 16, 16,
                  echo_rating($player_row['Rating2'],2,0,1),
                  'SELECTBOX', 'ratingtype', 1, $ratings,
                  'dragonrating', false,
                  );
      if( !@$player_row['RatingStatus'] )
         array_push( $row,
                  'TEXT', '<span class="FormWarning">'
                  .T_('Must be filled to start a rated game').'</span>'
                  );
      $profile_form->add_row( $row);
   }
   else
   {
      $profile_form->add_row( array( 'DESCRIPTION', T_('Rating'),
                                     'TEXT', echo_rating($player_row["Rating2"],2,0,0 ) ) );
   }
   $profile_form->add_row( array( 'DESCRIPTION', T_('Rank info'),
                                  'TEXTINPUT', 'rank', 32, 40,
                                  $player_row["Rank"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Open for matches?'),
                                  'TEXTINPUT', 'open', 32, 40,
                                  $player_row["Open"] ) );

   if( strpos($player_row["SendEmail"], 'BOARD') !== false ) $s= 3;
   elseif( strpos($player_row["SendEmail"], 'MOVE') !== false ) $s= 2;
   elseif( strpos($player_row["SendEmail"], 'ON') !== false ) $s= 1;
   else $s= 0;

   $profile_form->add_row( array( 'DESCRIPTION', T_('Email notifications'),
                                  'SELECTBOX', 'emailnotify', 1, $notify_mess, $s, false ) );
   $row= array('DESCRIPTION', T_('Email'),
               'TEXTINPUT', 'email', 32, 80,
               $player_row['Email'],
               );
   if( !trim($player_row['Email']) )
      array_push( $row,
               'TEXT', '<span class="FormWarning">'
                  .T_('Must be filled to receive a new password or a notification').'</span>'
               );
   $profile_form->add_row( $row);

   $profile_form->add_row( array( 'DESCRIPTION', T_('Country'),
                                  'SELECTBOX', 'country', 1, $COUNTRIES,
                                  $player_row["Country"], false ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Language'),
                                  'SELECTBOX', 'language', 1,
                                  array_reverse($langs), $player_row['Lang'], false ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Timezone'),
                                  'SELECTBOX', 'timezone', 1,
                                  get_timezone_array(), $player_row['Timezone'], false ) );
   if( NIGHT_LEN % 24 )
      $profile_form->add_row( array( 'DESCRIPTION', T_('Nighttime'),
                                     'SELECTBOX', 'nightstart', 1,
                                     $nightstart, $player_row['Nightstart'], false ) );
   else
      $profile_form->add_row( array( 'HIDDEN', 'nightstart', $player_row['Nightstart'] ) );

   $boardcoords = (int)$player_row['Boardcoords'];

   //--- Followings may be browser settings ---

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'HR' ) );



   $profile_form->add_row( array( 'HEADER', T_('Appearences') ) );

   if( (@$player_row['admin_level'] & ADMIN_SKINNER) )
   $profile_form->add_row( array( 'DESCRIPTION', T_('Skin'),
                                  'SELECTBOX', 'skinname', 1, $known_skins,
                                  $player_row['SkinName'], false ) );
   else
   $profile_form->add_row( array( 'HIDDEN', 'skinname', 'dragon'));

   $profile_form->add_row( array( 'DESCRIPTION', T_('Menu direction'),
                                  'RADIOBUTTONS', 'menudir', $menu_directions,
                                  $player_row["MenuDirection"] ) );


   $button_code  = "\n<table class=EditProfilButtons><tr>";
   for($i=0; $i<=$button_max; $i++)
   {
      if( $i % 4 == 0 )
      {
         if( $i > 0 )
            $button_code .= "</tr>\n<tr>";
      }
      else
      {
         $button_code .= "<td></td>";
      }
      $button_style = 'color:' . $buttoncolors[$i] . ';' .
                  'background-image:url(images/' . $buttonfiles[$i] . ');';
      $button_code .=
         "<td><input type='radio' name='button' value=$i" .
               ( $i == $button_nr ? ' checked' : '') . "></td>" .
         "<td class=button style='$button_style'>1348</td>";
   }
   $button_code .= "</tr></table>\n";

      $profile_form->add_row( array(
               'DESCRIPTION', T_('Game id button'),
               'TEXT', $button_code,
            ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Table max rows'),
                                  'SELECTBOX', 'tablemaxrows', 1, $tablemaxrows,
                                  $player_row['TableMaxRows'], false,
                                  'TEXT', sptext(T_('choosing a lower value helps the server (see also FAQ)')),
                                 ) );

   if( ALLOW_JSCRIPT )
   $profile_form->add_row( array( 'DESCRIPTION', T_('Allow JScript'),
                                  'CHECKBOX', 'jsenable', 1, '', ($boardcoords & JSCRIPT_ENABLED) ) );



   $profile_form->add_row( array( 'HEADER', T_('Board graphics') ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Stone size'),
                                  'SELECTBOX', 'stonesize', 1, $stonesizes,
                                    $player_row["Stonesize"], false ) );

   $profile_form->add_row( array( 'DESCRIPTION', T_('Wood color'),
                                  'RADIOBUTTONS', 'woodcolor', $woodcolors,
                                    $player_row["Woodcolor"] ) );

   $row= array( 'DESCRIPTION', T_('Coordinate sides'),
         'CHECKBOX', 'coordsleft', 1, sptext(T_('Left'),2), ($boardcoords & COORD_LEFT),
         'CHECKBOX', 'coordsup', 1, sptext(T_('Up'),2), ($boardcoords & COORD_UP),
         'CHECKBOX', 'coordsright', 1, sptext(T_('Right'),2), ($boardcoords & COORD_RIGHT),
         'CHECKBOX', 'coordsdown', 1, sptext(T_('Down'),2), ($boardcoords & COORD_DOWN),
         'CHECKBOX', 'coordsover', 1, sptext(T_('Hover'),2), ($boardcoords & COORD_OVER),
      );
   if( (@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      array_push( $row,
         'CHECKBOX', 'coordssgfover', 1, sptext(T_('Sgf over')), ($boardcoords & COORD_SGFOVER)
      );
   $profile_form->add_row( $row);

   if( ENA_MOVENUMBERS )
   {
   $profile_form->add_row( array( 'DESCRIPTION', T_('Move numbering'),
                                  'TEXTINPUT', 'movenumbers', 4, 4, $player_row['MoveNumbers'],
                                  'CHECKBOX', 'movemodulo', 100,
                                    sptext(T_('Don\'t use numbers above 100'),2),
                                    ($player_row['MoveModulo']>0 ?1 :0),
                                  'CHECKBOX', 'numbersover', 1, sptext(T_('Hover')), ($boardcoords & NUMBER_OVER),
                                ) );
   }

//   $profile_form->add_row( array( 'SPACE' ) );
//


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

      $notesmode = $player_row["Notes{$typ}Mode"];
      $noteshide = substr( $notesmode, -3) == 'OFF';
      if( $noteshide )
         $notesmode = substr( $notesmode, 0, -3);
      if( $notesmode != 'BELOW' )
         $notesmode = 'RIGHT';
      $profile_form->add_row( array(
               'DESCRIPTION', T_('Position'),
               'RADIOBUTTONS', "notes{$ltyp}mode", $notesmodes, $notesmode,
               'TEXT', sptext('',1),
               'CHECKBOX', "notes{$ltyp}hide", 1, sptext(T_('Hidden')), $noteshide,
            ) );

      $profile_form->add_row( array(
               'DESCRIPTION', T_('Size'),
               'TEXT', sptext(T_('Height')),
               'SELECTBOX', "notes{$ltyp}height", 1, $notesheights, $player_row["Notes{$typ}Height"], false,
               'TEXT', sptext(T_('Width'),1),
               'SELECTBOX', "notes{$ltyp}width", 1, $noteswidths, $player_row["Notes{$typ}Width"], false,
            ) );
   }


   //--- End of browser settings ---



   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array( 'TAB',
                                  'CHECKBOX', 'locally', 1,
                                  sptext(T_('Change appearences for this browser only')),
                                  safe_getcookie("prefs".$player_row['ID'])>'' ));

   $profile_form->add_row( array( 'HR' ) );

   $profile_form->add_row( array( 'SPACE' ) );

   $profile_form->add_row( array(
                     'SUBMITBUTTONX', 'action', T_('Change profile'),
                        array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
                     ) );


   $profile_form->echo_string(1);

   end_page();
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( 'include/classlib_userconfig.php' );
require_once( "include/timezones.php" );
require_once( "include/countries.php" );
require_once( "include/form_functions.php" );
require_once( 'include/utilities.php' );
require_once( 'include/db/bulletin.php' );
require_once( 'include/gui_bulletin.php' );

// Reminder: to friendly reset the language:
// {HOSTBASE}edit_profile.php?language=C
// {HOSTBASE}edit_profile.php?language=en

{
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   $cfg_board = ConfigBoard::load_config_board($my_id);

   $button_nr = $player_row['Button'];
   if( !is_numeric($button_nr) || $button_nr < 0 || $button_nr > $button_max  )
      $button_nr = 0;

   $notify_msg = array(
         0 => T_('Off'),
         1 => T_('Notify only'),
         2 => T_('Moves and messages'),
         3 => T_('Full board and messages'),
      );

   $menu_directions = array(
         'VERTICAL'   => sptext(T_('Vertical'),2),
         'HORIZONTAL' => sptext(T_('Horizontal')),
      );


   $nightstart = array();
   for($i=0; $i<24; $i++)
      $nightstart[$i] = sprintf('%02d-%02d',$i,($i+NIGHT_LEN)%24);

   $stonesizes = array_value_to_key_and_value(
      array( 5, 7, 9, 11, 13, 17, 21, 25, 29, 35, 42, 50 ) );

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

   $countries = getCountryText();
   asort($countries);
   array_unshift($countries, '');

   $langs = get_language_descriptions_translated();
   //it's not obvious that this sort on "translated" strings will always give a good result:
   arsort($langs); //will be reversed to place ahead the following:
   if( @$player_row['Translator'] )
      $langs['N'] = /**T_**/('Native texts'); // pseudo-language, that shows original texts
   $langs['C'] = T_('Use browser settings');

   $notesheights = array();
   for($i=5; $i<26; $i++ )
      $notesheights[$i] = $i;

   $noteswidths = array();
   for($i=15; $i<105; $i+=5 )
      $noteswidths[$i] = $i;

   $notesmodes = array('RIGHT' => sptext(T_('Right'),2),
                       'BELOW' => sptext(T_('Below'),2));

   $notescutoffs = array();
   for($i=5; $i<26; $i++ )
      $notescutoffs[$i] = $i;

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
                                  'TEXTINPUT', 'name', 32, 40, $player_row["Name"] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Open for matches?'),
                                  'TEXTINPUT', 'open', 32, 60, $player_row["Open"] ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Country'),
         'SELECTBOX', 'country', 1, $countries, $player_row["Country"], false ));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Language'),
         'SELECTBOX', 'language', 1, array_reverse($langs), $player_row['Lang'], false ) );
   $profile_form->add_row( array(
         'DESCRIPTION', T_('Timezone'),
         'SELECTBOX', 'timezone', 1, get_timezone_array(), $player_row['Timezone'], false ) );
   if( NIGHT_LEN % 24 )
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Nighttime'),
            'SELECTBOX', 'nightstart', 1, $nightstart, $player_row['Nightstart'], false ) );
   else
      $profile_form->add_row( array( 'HIDDEN', 'nightstart', $player_row['Nightstart'] ) );

   if( strpos($player_row["SendEmail"], 'BOARD') !== false )
      $notify_msg_idx = 3;
   elseif( strpos($player_row["SendEmail"], 'MOVE') !== false )
      $notify_msg_idx = 2;
   elseif( strpos($player_row["SendEmail"], 'ON') !== false )
      $notify_msg_idx = 1;
   else
      $notify_msg_idx = 0;

   $profile_form->add_empty_row();
   $profile_form->add_row( array(
         'DESCRIPTION', T_('Email notifications'),
         'SELECTBOX', 'emailnotify', 1, $notify_msg, $notify_msg_idx, false ) );
   $row = array(
         'DESCRIPTION', T_('Email'),
         'TEXTINPUT', 'email', 32, 80, $player_row['Email'] );
   if( !trim($player_row['Email']) )
      array_push( $row,
            'TEXT', span('FormWarning', T_('Must be filled to receive a new password or a notification')) );
   $profile_form->add_row( $row);

   $skipbull = (int)@$player_row['SkipBulletin'];
   $profile_form->add_empty_row();
   $profile_form->add_row( array(
         'DESCRIPTION', T_('Show Bulletin Categories'),
         'CHECKBOX',    'skipbull'.BULLETIN_SKIPCAT_TOURNAMENT, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_TOURNAMENT), !($skipbull & BULLETIN_SKIPCAT_TOURNAMENT) ));
   $profile_form->add_row( array(
         'TAB', 'CHECKBOX', 'skipbull'.BULLETIN_SKIPCAT_PRIVATE_MSG, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_PRIVATE_MSG), !($skipbull & BULLETIN_SKIPCAT_PRIVATE_MSG) ));
   $profile_form->add_row( array(
         'TAB', 'CHECKBOX', 'skipbull'.BULLETIN_SKIPCAT_SPAM, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_SPAM), !($skipbull & BULLETIN_SKIPCAT_SPAM) ));

   //--- Followings may be browser settings ---

   $profile_form->add_row( array( 'SPACE' ) );
   $profile_form->add_row( array( 'HR' ) );


   $profile_form->add_row( array( 'HEADER', T_('Appearance') ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Table max rows'),
         'SELECTBOX', 'tablemaxrows', 1, $tablemaxrows, $player_row['TableMaxRows'], false,
         'TEXT', sptext(T_('choosing a lower value helps the server (see also FAQ)')),
      ));

   if( ALLOW_JAVASCRIPT )
   {
      $userflags = (int)$player_row['UserFlags'];
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Enable JavaScript'),
            'CHECKBOX', 'jsenable', 1, '', ($userflags & USERFLAG_JAVASCRIPT_ENABLED) ) );
   }

   $profile_form->add_empty_row();
   if( (@$player_row['admin_level'] & ADMIN_SKINNER) )
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Skin'),
            'SELECTBOX', 'skinname', 1, $known_skins, $player_row['SkinName'], false ) );
   }
   else
      $profile_form->add_row( array( 'HIDDEN', 'skinname', 'dragon'));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Menu direction'),
         'RADIOBUTTONS', 'menudir', $menu_directions, $player_row["MenuDirection"] ) );


   $button_code  = "\n<table class=EditProfilButtons><tr>";
   for($i=0; $i<=$button_max; $i++)
   {
      if( $i % 4 == 0 )
      {
         if( $i > 0 )
            $button_code .= "</tr>\n<tr>";
      }
      else
         $button_code .= "<td></td>";
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
         'TEXT', $button_code, ));


   $profile_form->add_row( array( 'HEADER', T_('Board graphics') ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Stone size'),
         'SELECTBOX', 'stonesize', 1, $stonesizes, $cfg_board->get_stone_size(), false ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Wood color'),
         'RADIOBUTTONS', 'woodcolor', $woodcolors, $cfg_board->get_wood_color() ) );

   $boardcoords = $cfg_board->get_board_coords();

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Smooth board edge'),
         'CHECKBOX', 'smoothedge', 1, '', ($boardcoords & SMOOTH_EDGE),
         'TEXT', sptext(T_('(only for textured wood colors)')) ));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Coordinate sides'),
         'CHECKBOX', 'coordsleft', 1, sptext(T_('Left'),2), ($boardcoords & COORD_LEFT),
         'CHECKBOX', 'coordsup', 1, sptext(T_('Up'),2), ($boardcoords & COORD_UP),
         'CHECKBOX', 'coordsright', 1, sptext(T_('Right'),2), ($boardcoords & COORD_RIGHT),
         'CHECKBOX', 'coordsdown', 1, sptext(T_('Down'),2), ($boardcoords & COORD_DOWN),
      ));

   $row = array(
         'TAB',
         'CHECKBOX', 'coordsover', 1, sptext(T_('Hover (show coordinates)'),2),
               ($boardcoords & COORD_OVER),
      );
   if( (@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      array_push( $row,
         'CHECKBOX', 'coordssgfover', 1, sprintf( '<span class="AdminOption">%s</span>',
                  sptext(T_('SGF Hover (show SGF coordinates)'))),
               ($boardcoords & COORD_SGFOVER) );
   $profile_form->add_row( $row);

   if( ENA_MOVENUMBERS )
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Move numbering'),
            'TEXTINPUT', 'movenumbers', 4, 4, $cfg_board->get_move_numbers(),
            'CHECKBOX', 'movemodulo', 100, sptext(T_('Don\'t use numbers above 100'),2),
                        ( ($cfg_board->get_move_modulo() > 0) ? 1 :0),
            'CHECKBOX', 'numbersover', 1, sptext(T_('Show numbers only on Hover')),
                  ($boardcoords & NUMBER_OVER),
         ));

      $profile_form->add_row( array(
            'TAB',
            'CHECKBOX', 'coordsrelnum', 1, sptext(T_('Relative numbering'),2),
                  ($boardcoords & COORD_RELATIVE_MOVENUM),
            'CHECKBOX', 'coordsrevnum', 1, sptext(T_('Reverse numbering'),2),
                  ($boardcoords & COORD_REVERSE_MOVENUM),
         ));
   }


   $profile_form->add_row( array( 'HEADER', T_('Private game notes') ) );

   foreach( array( 'Small', 'Large') as $typ )
   {
      $ltyp = strtolower($typ) ;
      if( $ltyp == 'small' )
      {
         $cfg_type = CFGBOARD_NOTES_SMALL;
         $profile_form->add_row( array(
               'TEXT', '<b>' . T_('Small boards') . ':</b>',
               'TAB',
            ));
      }
      else
      {
         $cfg_type = CFGBOARD_NOTES_LARGE;
         $profile_form->add_row( array(
               'TEXT', '<b>' . T_("Large boards from") . ':</b>',
               'TD', 'SELECTBOX', 'notescutoff', 1, $notescutoffs, $cfg_board->get_notes_cutoff(), false,
            ));
      }

      $notesmode = $cfg_board->get_notes_mode( $cfg_type );
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
            ));

      $profile_form->add_row( array(
               'DESCRIPTION', T_('Size'),
               'TEXT', sptext(T_('Height')),
               'SELECTBOX', "notes{$ltyp}height", 1, $notesheights, $cfg_board->get_notes_height($cfg_type), false,
               'TEXT', sptext(T_('Width'),1),
               'SELECTBOX', "notes{$ltyp}width", 1, $noteswidths, $cfg_board->get_notes_width($cfg_type), false,
            ));
   }


   //--- End of browser settings ---


   $profile_form->add_empty_row();
   $profile_form->add_empty_row();

   $profile_form->add_row( array(
         'TAB',
         'CHECKBOX', 'locally', 1, sptext(T_('Change appearences for this browser only')),
               safe_getcookie("prefs".$my_id) > '' ));

   $profile_form->add_empty_row();

   $profile_form->add_row( array(
         'SUBMITBUTTONX', 'action', T_('Change profile'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ), ));
   $profile_form->add_empty_row();

   $profile_form->echo_string(1);


   $menu_array = array();
   $menu_array[T_('Change rating & rank')] = 'edit_rating.php';
   $menu_array[T_('Change password')] = 'edit_password.php';
   $menu_array[T_('Edit bio')] = 'edit_bio.php';
   if( USERPIC_FOLDER != '' )
      $menu_array[T_('Edit user picture')] = 'edit_picture.php';
   $menu_array[T_('Edit message folders')] = 'edit_folders.php';

   end_page(@$menu_array);
}
?>

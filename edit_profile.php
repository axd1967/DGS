<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/std_functions.php';
require_once 'include/error_codes.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/timezones.php';
require_once 'include/countries.php';
require_once 'include/form_functions.php';
require_once 'include/utilities.php';
require_once 'include/db/bulletin.php';
require_once 'include/gui_bulletin.php';

// Reminder: to friendly reset the language:
// {HOSTBASE}edit_profile.php?language=C
// {HOSTBASE}edit_profile.php?language=en


{
   disable_cache();
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'edit_profile');
   $my_id = $player_row['ID'];

   ConfigBoard::delete_cache_config_board($my_id); // force reload
   $cfg_board = ConfigBoard::load_config_board($my_id);
   if ( !$cfg_board )
      error('user_init_error', 'edit_profile.init.config_board');

/* Actual REQUEST calls used:
     save                  : save profile
*/


   // ----- init globals ---------------------------------

   include_once 'skins/known_skins.php';
   $arr_timezones = get_timezone_array();
   $stonesizes = array_value_to_key_and_value( array( 5, 7, 9, 11, 13, 17, 21, 25, 29, 35, 42, 50 ) );
   $thumbnailsizes = array_value_to_key_and_value( array( 7, 11 ) );

   $woodcolors = array();
   for ($i=1; $i<=16; $i++ )
   {
      $tmp = "<img width=30 height=30 src=\"images/smallwood$i.gif\" alt=\"wood$i\">";
      if ( $i==5 )
      {
         $woodcolors[$i] = sptext($tmp).'<BR>';
         $i = 10;
      }
      else
         $woodcolors[$i] = sptext($tmp,2);
   }

   $notescutoffs = array();
   for ( $i=5; $i<26; $i++ ) $notescutoffs[$i] = $i;

   $notesheights = array();
   for ( $i=5; $i<26; $i++ ) $notesheights[$i] = $i;

   $noteswidths = array();
   for ( $i=15; $i<105; $i+=5 ) $noteswidths[$i] = $i;


   // ----- actions (check & save) -----------------------

   // read defaults for vars, and parse & check posted values
   list( $vars, $errors ) = parse_edit_form( $cfg_board );

   if ( @$_REQUEST['save'] && count($errors) == 0 )
      handle_save_profile( $cfg_board, $vars );


   // ----- init form-data -------------------------------

   $menu_directions = array(
         'VERTICAL'   => sptext(T_('Vertical'),2),
         'HORIZONTAL' => sptext(T_('Horizontal')),
      );

   $nightstart = array();
   for ( $i=0; $i<24; $i++ ) $nightstart[$i] = sprintf('%02d-%02d',$i,($i+NIGHT_LEN)%24);

   $countries = getCountryText();
   asort($countries);
   array_unshift($countries, '');

   $langs = get_language_descriptions_translated();
   //it's not obvious that this sort on "translated" strings will always give a good result:
   arsort($langs); //will be reversed to place ahead the following:
   if ( @$player_row['Translator'] )
      $langs['N'] = /**T_**/('Native texts'); // pseudo-language, that shows original texts
   $langs['C'] = T_('Use browser settings');

   $gamemsg_positions = array(
      1 => sptext(T_('Above'),2),
      0 => sptext(T_('Below'),2));

   $notesmodes = array('RIGHT' => sptext(T_('Right'),2),
                       'BELOW' => sptext(T_('Below'),2));

   $tablemaxrows = build_maxrows_array(0, MAXROWS_PER_PAGE_PROFILE); // array( 10 => 10, ...)


   // ----- start-page -------------------------

   start_page(T_("Edit profile"), true, $logged_in, $player_row );

   $profile_form = new Form( 'profileform', 'edit_profile.php', FORM_POST );


   $profile_form->add_row( array( 'HEADER', T_('Personal settings') ) );

   if ( count($errors) )
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $profile_form->add_empty_row();
   }

   $profile_form->add_row( array( 'DESCRIPTION', T_('Userid'),
                                  'TEXT', $player_row['Handle'] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Full name'),
                                  'TEXTINPUT', 'name', 32, 40, $vars['name'] ) );
   $profile_form->add_row( array( 'DESCRIPTION', T_('Open for matches?'),
                                  'TEXTINPUT', 'open', 32, 60, $vars['open'] ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Country'),
         'SELECTBOX', 'country', 1, $countries, $vars['country'], false ));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Language'),
         'SELECTBOX', 'language', 1, array_reverse($langs), $vars['language'], false ) );
   $profile_form->add_row( array(
         'DESCRIPTION', T_('Timezone'),
         'SELECTBOX', 'timezone', 1, $arr_timezones, $vars['timezone'], false ) );
   if ( NIGHT_LEN % 24 )
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Nighttime'),
            'SELECTBOX', 'nightstart', 1, $nightstart, (int)$vars['night_start'], false ) );
   else
      $profile_form->add_row( array( 'HIDDEN', 'nightstart', (int)$vars['night_start'] ) );


   $skipbull = (int)$vars['skip_bulletin'];
   $profile_form->add_empty_row();
   $profile_form->add_row( array(
         'DESCRIPTION', T_('Show Bulletin Categories'),
         'CHECKBOX',    'skipbull'.BULLETIN_SKIPCAT_TOURNAMENT, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_TOURNAMENT), !($skipbull & BULLETIN_SKIPCAT_TOURNAMENT) ));
   $profile_form->add_row( array(
         'TAB', 'CHECKBOX', 'skipbull'.BULLETIN_SKIPCAT_FEATURE, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_FEATURE), !($skipbull & BULLETIN_SKIPCAT_FEATURE) ));
   $profile_form->add_row( array(
         'TAB', 'CHECKBOX', 'skipbull'.BULLETIN_SKIPCAT_PRIVATE_MSG, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_PRIVATE_MSG), !($skipbull & BULLETIN_SKIPCAT_PRIVATE_MSG) ));
   $profile_form->add_row( array(
         'TAB', 'CHECKBOX', 'skipbull'.BULLETIN_SKIPCAT_SPAM, 1,
            GuiBulletin::getCategoryText(BULLETIN_CAT_SPAM), !($skipbull & BULLETIN_SKIPCAT_SPAM) ));

   $reject_timeout = (int)@$vars['reject_timeout'];
   $profile_form->add_empty_row();
   $profile_form->add_row( array(
         'DESCRIPTION', T_('Reject win by timeout'),
         'CHECKBOX', 'rwt_enable', 1, T_('Activate check on rated (non-tournament) game won by timeout:#rwt'),
            ($reject_timeout >= 0), ));
   $profile_form->add_row( array(
         'TAB', 'TEXT', T_('The win of a game by timeout will be "rejected" (i.e. changed to unrated),<br>' .
                           'if the loser has not moved in any game for a certain amount of time.#rwt'), ));
   $profile_form->add_row( array(
         'TAB', 'TEXTINPUT', 'rwt_days', 5, 3, ($reject_timeout >= 0 ? $reject_timeout : ''),
         'TEXT', sprintf(' (0..%s) %s', MAX_REJECT_TIMEOUT, T_('days (minimum) the loser has not moved in any game#rwt')), ));


   //--- Followings may be browser settings ---

   $profile_form->add_row( array( 'SPACE' ) );
   $profile_form->add_row( array( 'HR' ) );


   $profile_form->add_row( array( 'HEADER', T_('Appearance') ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Table max rows'),
         'SELECTBOX', 'tablemaxrows', 1, $tablemaxrows, (int)$vars['table_maxrows'], false,
         'TEXT', sptext(T_('choosing a lower value helps the server (see also FAQ)')),
      ));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Thumbnail size'),
         'SELECTBOX', 'thumbnailsize', 1, $thumbnailsizes, (int)$vars['thumbnail_size'], false ) );

   $userflags = (int)$vars['user_flags'];
   if ( ALLOW_JAVASCRIPT )
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Enable JavaScript'),
            'CHECKBOX', 'ufl_jsenable', 1, '', ($userflags & USERFLAG_JAVASCRIPT_ENABLED) ) );
   }

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Show rating graph by games'),
         'CHECKBOX', 'ufl_ratgraph_by_games', 1, '', ($userflags & USERFLAG_RATINGGRAPH_BY_GAMES) ) );


   $profile_form->add_empty_row();
   if ( (@$player_row['admin_level'] & ADMIN_SKINNER) )
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Skin'),
            'SELECTBOX', 'skinname', 1, $known_skins, $vars['skin_name'], false ) );
   }
   else
      $profile_form->add_row( array( 'HIDDEN', 'skinname', /*default*/'dragon'));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Menu direction'),
         'RADIOBUTTONS', 'menudir', $menu_directions, $vars['menu_direction'] ) );


   $button_nr = (int)$vars['button'];
   $button_code  = "\n<table class=\"EditProfilButtons\"><tr>";
   for ( $i=0; $i < BUTTON_MAX; $i++ )
   {
      if ( $i % 4 == 0 )
      {
         if ( $i > 0 )
            $button_code .= "</tr>\n<tr>";
      }
      else
         $button_code .= "<td></td>";
      $button_style = 'width: 107px; color:' . $buttoncolors[$i] . ';' . 'background-image:url(images/' . $buttonfiles[$i] . ');';
      $button_code .=
         "<td><input type='radio' name='button' value=$i" . ( $i == $button_nr ? ' checked' : '') . "></td>" .
         "<td class=button style='$button_style'>1348</td>";
   }
   $button_code .= "</tr>\n<tr>" .
      '<td><input type="radio" name="button" value="'.BUTTON_TEXT.'"' .
      ( $button_nr == BUTTON_TEXT ? ' checked' : '') . '></td><td>' . T_('No button') . '</td>';
   $button_code .= "</tr>\n</table>\n";

   $profile_form->add_row( array(
         'DESCRIPTION', T_('ID button'),
         'TEXT', $button_code, ));


   $profile_form->add_row( array( 'HEADER', T_('Board graphics') ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Stone size'),
         'SELECTBOX', 'stonesize', 1, $stonesizes, (int)$vars['stone_size'], false ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Wood color'),
         'RADIOBUTTONS', 'woodcolor', $woodcolors, (int)$vars['wood_color'] ) );

   $boardcoords = (int)$vars['board_coords'];

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Smooth board edge'),
         'CHECKBOX', 'smoothedge', 1, '', ($boardcoords & SMOOTH_EDGE),
         'TEXT', sptext(T_('(only for textured wood colors)')) ));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Coordinate sides'),
         'CHECKBOX', 'coordsleft',  1, sptext(T_('Left'),2),  ($boardcoords & COORD_LEFT),
         'CHECKBOX', 'coordsup',    1, sptext(T_('Up'),2),    ($boardcoords & COORD_UP),
         'CHECKBOX', 'coordsright', 1, sptext(T_('Right'),2), ($boardcoords & COORD_RIGHT),
         'CHECKBOX', 'coordsdown',  1, sptext(T_('Down'),2),  ($boardcoords & COORD_DOWN),
      ));

   $row = array(
         'TAB',
         'CHECKBOX', 'coordsover', 1, sptext(T_('Hover (show coordinates)'),2), ($boardcoords & COORD_OVER),
      );
   if ( (@$player_row['admin_level'] & ADMIN_DEVELOPER) )
   {
      array_push( $row,
         'CHECKBOX', 'coordssgfover', 1,
               span('AdminOption', sptext(T_('SGF Hover (show SGF coordinates)'))),
               ($boardcoords & COORD_SGFOVER) );
   }
   $profile_form->add_row( $row);

   $board_flags = (int)$vars['board_flags'];
   if ( ENA_MOVENUMBERS )
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Move numbering'),
            'TEXTINPUT', 'movenumbers', 4, 4, (int)$vars['move_numbers'],
            'CHECKBOX', 'movemodulo', 100, sptext(T_('Don\'t use numbers above 100'),2), (bool)$vars['move_modulo'],
            'CHECKBOX', 'numbersover', 1, sptext(T_('Show numbers only on Hover')), ($boardcoords & NUMBER_OVER),
         ));

      $profile_form->add_row( array(
            'TAB',
            'CHECKBOX', 'coordsrelnum', 1, sptext(T_('Relative numbering'),2), ($boardcoords & COORD_RELATIVE_MOVENUM),
            'CHECKBOX', 'coordsrevnum', 1, sptext(T_('Reverse numbering'),2),  ($boardcoords & COORD_REVERSE_MOVENUM),
         ));

      $profile_form->add_row( array(
            'TAB',
            'CHECKBOX', 'bfl_mark_lc', 1, sptext(T_('Mark Last Move Capture'),2), ($board_flags & BOARDFLAG_MARK_LAST_CAPTURE),
         ));
   }
   else
   {
      $profile_form->add_row( array(
            'DESCRIPTION', T_('Move marker'),
            'CHECKBOX', 'bfl_mark_lc', 1, sptext(T_('Mark Last Move Capture'),2), ($board_flags & BOARDFLAG_MARK_LAST_CAPTURE),
         ));
   }


   $profile_form->add_row( array( 'HEADER', T_('Game page settings') ) );

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Game move message'),
         'TEXT', sptext(T_('Position relative to game board').':',2),
         'RADIOBUTTONS', 'bfl_gamemsg_pos', $gamemsg_positions, ($board_flags & BOARDFLAG_MOVEMSG_ABOVE_BOARD) ? 1 : 0,
      ));

   $profile_form->add_row( array(
         'DESCRIPTION', T_('Submit move button'),
         'CHECKBOX', 'bfl_submit_stay_game', 1, sptext(T_('Show button to submit move and stay on same game'),2),
            ($board_flags & BOARDFLAG_SUBMIT_MOVE_STAY_GAME),
      ));


   $profile_form->add_row( array( 'HEADER', T_('Private game notes') ) );

   foreach ( array( 'small', 'large') as $ltyp )
   {
      if ( $ltyp == 'small' )
      {
         $profile_form->add_row( array(
               'TEXT', '<b>' . T_('Small boards') . ':</b>',
               'TAB',
            ));
      }
      else
      {
         $profile_form->add_row( array(
               'TEXT', '<b>' . T_("Large boards from") . ':</b>',
               'TD', 'SELECTBOX', 'notescutoff', 1, $notescutoffs, (int)$vars['notes_cutoff'], false,
            ));
      }

      $notesmode = $vars['notes_mode_'.$ltyp];
      $noteshide = ( substr( $notesmode, -3) == 'OFF' );
      if ( $noteshide )
         $notesmode = substr( $notesmode, 0, -3);
      if ( $notesmode != 'BELOW' )
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
               'SELECTBOX', "notes{$ltyp}height", 1, $notesheights, (int)$vars['notes_height_'.$ltyp], false,
               'TEXT', sptext(T_('Width'),1),
               'SELECTBOX', "notes{$ltyp}width", 1, $noteswidths, (int)$vars['notes_width_'.$ltyp], false,
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
         'SUBMITBUTTONX', 'save', T_('Change profile'), array( 'accesskey' => ACCKEY_ACT_EXECUTE ), ));
   $profile_form->add_empty_row();

   $profile_form->echo_string(1);


   $menu_array = array();
   $menu_array[T_('Change rating & rank')] = 'edit_rating.php';
   $menu_array[T_('Change email & notifications')] = 'edit_email.php';
   $menu_array[T_('Change password')] = 'edit_password.php';
   $menu_array[T_('Edit bio')] = 'edit_bio.php';
   if ( USERPIC_FOLDER != '' )
      $menu_array[T_('Edit user picture')] = 'edit_picture.php';
   $menu_array[T_('Edit message folders')] = 'edit_folders.php';

   end_page(@$menu_array);
}//main



// return ( vars, error-list ) with $vars containing default or changed profile-values
function parse_edit_form( &$cfg_board )
{
   global $player_row, $arr_timezones, $known_skins, $stonesizes, $thumbnailsizes,
      $woodcolors, $notesheights, $notescutoffs, $noteswidths;

   // set defaults
   $vars = array(
      'name'               => $player_row['Name'],
      'open'               => $player_row['Open'],
      'country'            => $player_row['Country'],
      'language'           => $player_row['Lang'],
      'timezone'           => $player_row['Timezone'],
      'night_start'        => (int)$player_row['Nightstart'],
      'db:clock_changed'   => false,
      'skip_bulletin'      => (int)$player_row['SkipBulletin'],
      'reject_timeout'     => (int)$player_row['RejectTimeoutWin'],
      // appearance
      'thumbnail_size'     => (int)$player_row['ThumbnailSize'],
      'table_maxrows'      => (int)$player_row['TableMaxRows'],
      'user_flags'         => (int)$player_row['UserFlags'],
      'skin_name'          => $player_row['SkinName'],
      'menu_direction'     => $player_row['MenuDirection'],
      'button'             => $player_row['Button'],
      // board graphics
      'stone_size'         => $cfg_board->get_stone_size(),
      'wood_color'         => $cfg_board->get_wood_color(),
      'board_coords'       => $cfg_board->get_board_coords(),
      'move_numbers'       => $cfg_board->get_move_numbers(),
      'move_modulo'        => $cfg_board->get_move_modulo(),
      'board_flags'        => $cfg_board->get_board_flags(),
      // game notes
      'notes_cutoff'       => $cfg_board->get_notes_cutoff(),
      'notes_mode_small'   => $cfg_board->get_notes_mode( CFGBOARD_NOTES_SMALL ),
      'notes_mode_large'   => $cfg_board->get_notes_mode( CFGBOARD_NOTES_LARGE ),
      'notes_height_small' => $cfg_board->get_notes_height( CFGBOARD_NOTES_SMALL ),
      'notes_height_large' => $cfg_board->get_notes_height( CFGBOARD_NOTES_LARGE ),
      'notes_width_small'  => $cfg_board->get_notes_width( CFGBOARD_NOTES_SMALL ),
      'notes_width_large'  => $cfg_board->get_notes_width( CFGBOARD_NOTES_LARGE ),
   );

   // parse URL-vars from form-submit
   $errors = array();
   if ( @$_REQUEST['save'] )
   {
      $name = trim(get_request_arg('name')) ;
      if ( strlen($name) < 1 )
         $errors[] = ErrorCode::get_error_text('name_not_given');
      else
         $vars['name'] = $name;

      $vars['open'] = trim(get_request_arg('open'));

      $country = trim(get_request_arg('country')) ;
      if ( empty($country) )
         $vars['country'] = '';
      elseif ( getCountryText($country) )
         $vars['country'] = $country;

      /* Because $_REQUEST['language'] is chosen prior $player_row['Lang']
         by include_translate_group(), this page use it for its translations
         (see the sysmsg displayed in the next page)
         This allow sysmsg to be translated in the right *future* language ...
         ... and some debug with a temporary page translation via the URL.
      */
      $language = trim(get_request_arg('language'));
      if ( $language === 'C'
            || ( $language === 'N' && @$player_row['Translator'] )
            || ( $language !== $player_row['Lang'] && language_exists($language) ) )
      {
         $vars['language'] = $language;
      }

      $timezone = get_request_arg('timezone');
      if ( !isset($arr_timezones[$timezone]) )
         $errors[] = sprintf( T_('Invalid timezone [%s] selected#profile'), $timezone );
      else
         $vars['timezone'] = $timezone;

      $nightstart = mod( (int)@$_REQUEST['nightstart'], 24);
      if ( $nightstart != $player_row['Nightstart'] || $timezone != $player_row['Timezone'] )
      {
         $vars['db:clock_changed'] = true;
         // ClockUsed is updated only once a day to prevent eternal night...
         // setTZ( $timezone); //for get_clock_used()
         // $query .= "ClockUsed=" . get_clock_used($nightstart) . ", ";
      }
      $vars['night_start'] = $nightstart;

      $skipbulletin = 0;
      foreach ( array( BULLETIN_SKIPCAT_TOURNAMENT, BULLETIN_SKIPCAT_FEATURE, BULLETIN_SKIPCAT_PRIVATE_MSG, BULLETIN_SKIPCAT_SPAM ) as $mask )
         $skipbulletin |= ( !@$_REQUEST['skipbull'.$mask] ? $mask : 0 );
      $vars['skip_bulletin'] = $skipbulletin;

      if ( @$_REQUEST['rwt_enable'] )
      {
         $reject_timeout = trim(get_request_arg('rwt_days'));
         if ( !is_numeric($reject_timeout) )
         {
            $errors[] = sprintf( T_('Corrected invalid days [%s] for reject win by timeout.#profile'), $reject_timeout);
            $reject_timeout = -1;
         }
         elseif ( $reject_timeout < 0 )
            $reject_timeout = 0;
         elseif ( $reject_timeout > MAX_REJECT_TIMEOUT )
         {
            $errors[] = sprintf( T_('Corrected days [%s] (too large) for reject win by timeout.#profile'), $reject_timeout);
            $reject_timeout = MAX_REJECT_TIMEOUT;
         }
      }
      else
         $reject_timeout = -1;
      $vars['reject_timeout'] = $reject_timeout;


      // parse appearance prefs ---------------------------------------

      $vars['table_maxrows'] = get_maxrows(
         get_request_arg('tablemaxrows'),
         MAXROWS_PER_PAGE_PROFILE, MAXROWS_PER_PAGE_DEFAULT );

      $user_flags = $vars['user_flags']; // preserve other user-flags
      if ( ALLOW_JAVASCRIPT )
      {
         if ( @$_REQUEST['ufl_jsenable'] )
            $user_flags |= USERFLAG_JAVASCRIPT_ENABLED;
         else
            $user_flags &= ~USERFLAG_JAVASCRIPT_ENABLED;
      }
      if ( @$_REQUEST['ufl_ratgraph_by_games'] )
         $user_flags |= USERFLAG_RATINGGRAPH_BY_GAMES;
      else
         $user_flags &= ~USERFLAG_RATINGGRAPH_BY_GAMES;
      $vars['user_flags'] = $user_flags;

      $thumbnail_size = (int)@$_REQUEST['thumbnailsize'];
      if ( !isset($thumbnailsizes[$thumbnail_size]) )
      {
         $errors[] = sprintf( T_('Invalid thumbnail size [%s] selected (corrected to default).#profile'), $thumbnail_size );
         $thumbnail_size = 7;
      }
      $vars['thumbnail_size'] = $thumbnail_size;

      $skin_name = get_request_arg('skinname', 'dragon');
      if ( !isset($known_skins[$skin_name]) )
         $errors[] = sprintf( T_('Unknown skin [%s] selected#profile'), $skin_name );
      $vars['skin_name'] = $skin_name;

      $vars['menu_direction'] = ( @$_REQUEST['menudir'] == 'HORIZONTAL' ) ? 'HORIZONTAL' : 'VERTICAL';

      $button_nr = (int)@$_REQUEST['button'];
      if ( !is_valid_button($button_nr) )
         $button_nr = 0;
      $vars['button'] = $button_nr;


      // parse board prefs --------------------------------------------

      $stone_size = (int)@$_REQUEST['stonesize'];
      if ( !isset($stonesizes[$stone_size]) )
      {
         $errors[] = sprintf( T_('Invalid stone size [%s] selected (corrected to default).#profile'), $stone_size );
         $stone_size = 25;
      }
      $vars['stone_size'] = $stone_size;

      $wood_color = (int)@$_REQUEST['woodcolor'];
      if ( !isset($woodcolors[$wood_color]) )
      {
         $errors[] = T_('Invalid wood color selected (corrected to default).#profile');
         $wood_color = 1;
      }
      $vars['wood_color'] = $wood_color;

      $vars['board_coords'] =
             ( @$_REQUEST['coordsleft']  ? COORD_LEFT : 0 )
           | ( @$_REQUEST['coordsup']    ? COORD_UP : 0 )
           | ( @$_REQUEST['coordsright'] ? COORD_RIGHT : 0 )
           | ( @$_REQUEST['coordsdown']  ? COORD_DOWN : 0 )
           | ( @$_REQUEST['coordsover']    ? COORD_OVER : 0 )
           | ( @$_REQUEST['coordssgfover'] ? COORD_SGFOVER : 0 )
           | ( @$_REQUEST['numbersover']   ? NUMBER_OVER : 0 )
           | ( ( @$_REQUEST['smoothedge'] && ($wood_color < 10) ) ? SMOOTH_EDGE : 0 )
           | ( @$_REQUEST['coordsrelnum'] ? COORD_RELATIVE_MOVENUM : 0 )
           | ( @$_REQUEST['coordsrevnum'] ? COORD_REVERSE_MOVENUM : 0 )
           ;

      $movenumbers = (int)@$_REQUEST['movenumbers'];
      if ( $movenumbers < 0 || $movenumbers > 500 )
         $errors[] = sprintf( T_('Invalid value [%s] for move-numbering used (allowed range is %s..%s).#profile'),
            $movenumbers, 0, 500 );
      $vars['move_numbers'] = $movenumbers;

      $vars['move_modulo'] = ( (int)@$_REQUEST['movemodulo'] ) ? 100 : 0;

      $vars['board_flags'] = ( @$_REQUEST['bfl_mark_lc'] ? BOARDFLAG_MARK_LAST_CAPTURE : 0 )
         | ( @$_REQUEST['bfl_submit_stay_game'] ? BOARDFLAG_SUBMIT_MOVE_STAY_GAME : 0 )
         | ( @$_REQUEST['bfl_gamemsg_pos'] ? BOARDFLAG_MOVEMSG_ABOVE_BOARD : 0 ); // 0=below


      // parse private game notes -------------------------------------

      $notes_cutoff = (int)@$_REQUEST['notescutoff'];
      if ( !isset($notescutoffs[$notes_cutoff]) )
      {
         $errors[] = T_('Invalid notes cut-off size selected to separate "small" from "large" boards (corrected to default).#profile');
         $notes_cutoff = 13;
      }
      $vars['notes_cutoff'] = $notes_cutoff;

      foreach ( array( 'small', 'large') as $ltyp )
      {
         $mode = strtoupper(@$_REQUEST["notes{$ltyp}mode"]);
         if ( $mode != 'BELOW' )
            $mode = 'RIGHT';
         if ( @$_REQUEST["notes{$ltyp}hide"] )
            $mode .= 'OFF';
         $vars['notes_mode_'.$ltyp] = $mode;

         $height = (int)@$_REQUEST["notes{$ltyp}height"];
         if ( !isset($notesheights[$height]) )
         {
            $errors[] = ($ltyp == 'small')
                ? T_('Invalid notes-height for small boards selected (corrected to default).#profile')
                : T_('Invalid notes-height for large boards selected (corrected to default).#profile');
            $height = 25;
         }
         $vars['notes_height_'.$ltyp] = $height;

         $width = (int)@$_REQUEST["notes{$ltyp}width"];
         if ( !isset($noteswidths[$width]) )
         {
            $errors[] = ($ltyp == 'small')
                ? T_('Invalid notes-width for small boards selected (corrected to default).#profile')
                : T_('Invalid notes-width for large boards selected (corrected to default).#profile');
            $width = 40;
         }
         $vars['notes_width_'.$ltyp]  = $width;
      }
   }//is_save

   return array( $vars, $errors );
}//parse_edit_form


// save profile-data $nval into database and jumps to user-info-page
function handle_save_profile( &$cfg_board, $nval )
{
   global $player_row, $cookie_prefs;

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'edit_profile.handle_save_profile');

   $upd = new UpdateQuery('Players');
   $upd->upd_txt('Name', $nval['name'] );
   $upd->upd_txt('Open', $nval['open'] );
   $upd->upd_txt('Country', $nval['country'] );
   $upd->upd_txt('Lang', $nval['language'] );
   $upd->upd_txt('Timezone', $nval['timezone'] );
   $upd->upd_num('Nightstart', (int)$nval['night_start'] );
   if ( $nval['db:clock_changed'] )
      $upd->upd_bool('ClockChanged', true );
   $upd->upd_num('SkipBulletin', (int)$nval['skip_bulletin'] );
   if ( (int)@$player_row['Skipbulletin'] != $nval['skip_bulletin'] ) // reset bulletin-count
      $upd->upd_num('CountBulletinNew', -1 );
   $upd->upd_num('RejectTimeoutWin', (int)$nval['reject_timeout'] );

   if ( @$_REQUEST['locally'] == 1 ) // only set in browser
   {
      // adjust $cookie_pref_rows too
      $cookie_prefs['TableMaxRows'] = (int)$nval['table_maxrows'];
      $cookie_prefs['ThumbnailSize'] = (int)$nval['thumbnail_size'];
      $cookie_prefs['UserFlags'] = (int)$nval['user_flags'];
      $cookie_prefs['SkinName'] = $nval['skin_name'];
      $cookie_prefs['MenuDirection'] = $nval['menu_direction'];
      $cookie_prefs['Button'] = (int)$nval['button'];

      // board-prefs
      $cookie_prefs['Stonesize']   = (int)$nval['stone_size'];
      $cookie_prefs['Woodcolor']   = (int)$nval['wood_color'];
      $cookie_prefs['BoardFlags']  = (int)$nval['board_flags'];
      $cookie_prefs['Boardcoords'] = (int)$nval['board_coords'];
      $cookie_prefs['MoveNumbers'] = (int)$nval['move_numbers'];
      $cookie_prefs['MoveModulo']  = (int)$nval['move_modulo'];
      $cookie_prefs['NotesCutoff']      = (int)$nval['notes_cutoff'];
      $cookie_prefs['NotesSmallMode']   = $nval['notes_mode_small'];
      $cookie_prefs['NotesSmallHeight'] = (int)$nval['notes_height_small'];
      $cookie_prefs['NotesSmallWidth']  = (int)$nval['notes_width_small'];
      $cookie_prefs['NotesLargeMode']   = $nval['notes_mode_large'];
      $cookie_prefs['NotesLargeHeight'] = (int)$nval['notes_height_large'];
      $cookie_prefs['NotesLargeWidth']  = (int)$nval['notes_width_large'];

      set_cookie_prefs($player_row);
      $save_config_board = false;
   }
   else // save into db
   {
      $upd->upd_num('TableMaxRows', (int)$nval['table_maxrows'] );
      $upd->upd_num('ThumbnailSize', (int)$nval['thumbnail_size'] );
      $upd->upd_num('UserFlags', (int)$nval['user_flags'] );
      $upd->upd_txt('SkinName', $nval['skin_name'] );
      $upd->upd_txt('MenuDirection', $nval['menu_direction'] );
      $upd->upd_num('Button', (int)$nval['button'] );

      // board-prefs
      $cfg_board->set_stone_size(   (int)$nval['stone_size'] );
      $cfg_board->set_wood_color(   (int)$nval['wood_color'] );
      $cfg_board->set_board_flags(  (int)$nval['board_flags'] );
      $cfg_board->set_board_coords( (int)$nval['board_coords'] );
      $cfg_board->set_move_numbers( (int)$nval['move_numbers'] );
      $cfg_board->set_move_modulo(  (int)$nval['move_modulo'] );
      $cfg_board->set_notes_cutoff( (int)$nval['notes_cutoff'] );
      $cfg_board->set_notes_mode(   CFGBOARD_NOTES_SMALL, $nval['notes_mode_small'] );
      $cfg_board->set_notes_height( CFGBOARD_NOTES_SMALL, (int)$nval['notes_height_small'] );
      $cfg_board->set_notes_width(  CFGBOARD_NOTES_SMALL, (int)$nval['notes_width_small'] );
      $cfg_board->set_notes_mode(   CFGBOARD_NOTES_LARGE, $nval['notes_mode_large'] );
      $cfg_board->set_notes_height( CFGBOARD_NOTES_LARGE, (int)$nval['notes_height_large'] );
      $cfg_board->set_notes_width(  CFGBOARD_NOTES_LARGE, (int)$nval['notes_width_large'] );

      set_cookie_prefs($player_row, true);
      $save_config_board = true;
   }


   $dbgmsg = "edit_profile.handle_save_profile($my_id)";
   ta_begin();
   {//HOT-section to update players profile
      // table (Players)
      db_query( $dbgmsg, "UPDATE Players SET " . $upd->get_query() . " WHERE ID=$my_id LIMIT 1" );

      // table (ConfigBoard)
      if ( $save_config_board )
         $cfg_board->update_all();

      delete_cache_user_reference( $dbgmsg, $my_id, $player_row['Handle'] );
   }
   ta_end();

   $msg = urlencode(T_('Profile updated!'));
   jump_to("userinfo.php?uid=$my_id".URI_AMP."sysmsg=$msg");
}//handle_save_profile

?>

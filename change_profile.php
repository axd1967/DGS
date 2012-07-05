<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once 'include/classlib_userconfig.php';
require_once 'include/countries.php';
require_once 'include/db/bulletin.php';

{
   // NOTE: used by page: edit_profile.php

   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'change_profile');
   if( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'change_profile');
   $my_id = $player_row['ID'];

   $name = trim(get_request_arg('name')) ;
   if( strlen( $name ) < 1 )
      error('name_not_given', "change_profile.miss_name($my_id)");

   $email = trim(get_request_arg('email'));
   if( $email )
      verify_email( 'change_profile', $email);

   $cfg_board = ConfigBoard::load_config_board( $player_row['ID'] );

   $sendemail = '';
   $emailnotify = (int)@$_GET['emailnotify'];
   if( !empty($email) && $emailnotify >= 1 )
   {
      $sendemail = 'ON';
      if( $emailnotify >= 2 ) // BOARD also includes moves+message
      {
         $sendemail .= ',MOVE,MESSAGE';
         if( $emailnotify >= 3 )
            $sendemail .= ',BOARD';
      }
   }

   $skipbulletin = 0;
   foreach( array( BULLETIN_SKIPCAT_TOURNAMENT, BULLETIN_SKIPCAT_FEATURE, BULLETIN_SKIPCAT_PRIVATE_MSG, BULLETIN_SKIPCAT_SPAM ) as $mask )
      $skipbulletin |= ( !@$_GET['skipbull'.$mask] ? $mask : 0 );
   $reset_bulletin_count = ( (int)@$player_row['Skipbulletin'] != $skipbulletin );

   $woodcolor = (int)@$_GET['woodcolor'];
   $board_flags = ( @$_GET['bfl_mark_lc'] ? BOARDFLAG_MARK_LAST_CAPTURE : 0 );
   $boardcoords = ( @$_GET['coordsleft'] ? COORD_LEFT : 0 )
                | ( @$_GET['coordsup'] ? COORD_UP : 0 )
                | ( @$_GET['coordsright'] ? COORD_RIGHT : 0 )
                | ( @$_GET['coordsdown'] ? COORD_DOWN : 0 )
                | ( @$_GET['coordsover'] ? COORD_OVER : 0 )
                | ( @$_GET['coordssgfover'] ? COORD_SGFOVER : 0 )
                | ( @$_GET['numbersover'] ? NUMBER_OVER : 0 )
                | ( ( @$_GET['smoothedge'] && ($woodcolor < 10) ) ? SMOOTH_EDGE : 0 )
                | ( @$_GET['coordsrelnum'] ? COORD_RELATIVE_MOVENUM : 0 )
                | ( @$_GET['coordsrevnum'] ? COORD_REVERSE_MOVENUM : 0 )
                ;

   $userflags = 0;
   if( ALLOW_JAVASCRIPT )
      $userflags |= ( @$_GET['jsenable'] ? USERFLAG_JAVASCRIPT_ENABLED : 0 );

   $movenumbers = (int)@$_GET['movenumbers'];
   $movemodulo = (int)@$_GET['movemodulo'];

   $menudirection = ( @$_GET['menudir'] == 'HORIZONTAL' ? 'HORIZONTAL' : 'VERTICAL' );

   foreach( array( 'small', 'large') as $ltyp )
   {
      $notesmode = "notes{$ltyp}mode";
      $$notesmode = strtoupper(@$_GET[$notesmode]);
      if( $$notesmode != 'BELOW' )
         $$notesmode = 'RIGHT';
      if( @$_GET["notes{$ltyp}hide"] )
         $$notesmode.= 'OFF';
   }

   $skinname = get_request_arg('skinname');

   $tablemaxrows = get_maxrows(
      get_request_arg('tablemaxrows'),
      MAXROWS_PER_PAGE_PROFILE, MAXROWS_PER_PAGE_DEFAULT );

   if( @$_REQUEST['rwt_enable'] )
   {
      $reject_timeout = trim(get_request_arg('rwt_days'));
      if( !is_numeric($reject_timeout) )
         $reject_timeout = -1;
      elseif( $reject_timeout < 0 )
         $reject_timeout = 0;
      elseif( $reject_timeout > MAX_REJECT_TIMEOUT )
         $reject_timeout = MAX_REJECT_TIMEOUT;
   }
   else
      $reject_timeout = -1;


   $query = "UPDATE Players SET " .
      "Name='" . mysql_addslashes($name) . "', " .
      "Email='" . mysql_addslashes($email) . "', " .
      "Open='" . mysql_addslashes(trim(get_request_arg('open'))) . "', " .
      "SendEmail='$sendemail', " .
      "SkipBulletin=$skipbulletin, " .
      "RejectTimeoutWin=$reject_timeout, ";
   if( $reset_bulletin_count )
      $query .= "CountBulletinNew=-1, ";

   $country = trim(get_request_arg('country')) ;
   if( empty($country) )
      $query .= "Country='', ";
   else if( getCountryText($country) )
      $query .= "Country='" . mysql_addslashes($country) . "', ";

   if( @$_GET['locally'] == 1 )
   {
      // adjust $cookie_pref_rows too
      $cookie_prefs['UserFlags'] = $userflags;
      $cookie_prefs['Stonesize'] = (int)@$_GET['stonesize'];
      $cookie_prefs['Woodcolor'] = (int)@$_GET['woodcolor'];
      $cookie_prefs['BoardFlags'] = $board_flags;
      $cookie_prefs['Boardcoords'] = $boardcoords;
      $cookie_prefs['MoveNumbers'] = $movenumbers;
      $cookie_prefs['MoveModulo'] = $movemodulo;
      $cookie_prefs['MenuDirection'] = $menudirection;
      $cookie_prefs['Button'] = (int)@$_GET['button'];
      $cookie_prefs['NotesSmallHeight'] = (int)@$_GET['notessmallheight'];
      $cookie_prefs['NotesSmallWidth'] = (int)@$_GET['notessmallwidth'];
      $cookie_prefs['NotesSmallMode'] = $notessmallmode;
      $cookie_prefs['NotesLargeHeight'] = (int)@$_GET['noteslargeheight'];
      $cookie_prefs['NotesLargeWidth'] = (int)@$_GET['noteslargewidth'];
      $cookie_prefs['NotesLargeMode'] = $noteslargemode;
      $cookie_prefs['NotesCutoff'] = (int)@$_GET['notescutoff'];
      $cookie_prefs['SkinName'] = $skinname;
      $cookie_prefs['TableMaxRows'] = $tablemaxrows;

      set_cookie_prefs($player_row);
      $save_config_board = false;
   }
   else
   {
      $query .=
         "UserFlags=$userflags, " .
         "MenuDirection='$menudirection', " .
         "Button=" . (int)@$_GET['button'] . ", " .
         "SkinName='" . mysql_addslashes($skinname) . "', " .
         "TableMaxRows=$tablemaxrows, ";

      $cfg_board->set_stone_size( (int)@$_GET['stonesize'] );
      $cfg_board->set_wood_color( (int)@$_GET['woodcolor'] );
      $cfg_board->set_board_flags( $board_flags );
      $cfg_board->set_board_coords( $boardcoords );
      $cfg_board->set_move_numbers( $movenumbers );
      $cfg_board->set_move_modulo( $movemodulo );
      $cfg_board->set_notes_height( CFGBOARD_NOTES_SMALL, (int)@$_GET['notessmallheight'] );
      $cfg_board->set_notes_width( CFGBOARD_NOTES_SMALL, (int)@$_GET['notessmallwidth'] );
      $cfg_board->set_notes_mode( CFGBOARD_NOTES_SMALL, $notessmallmode );
      $cfg_board->set_notes_height( CFGBOARD_NOTES_LARGE, (int)@$_GET['noteslargeheight'] );
      $cfg_board->set_notes_width( CFGBOARD_NOTES_LARGE, (int)@$_GET['noteslargewidth'] );
      $cfg_board->set_notes_mode( CFGBOARD_NOTES_LARGE, $noteslargemode );
      $cfg_board->set_notes_cutoff( (int)@$_GET['notescutoff'] );

      set_cookie_prefs($player_row, true);
      $save_config_board = true;
   }


/* Because $_REQUEST['language'] is chosen prior $player_row['Lang']
   by include_translate_group(), this page use it for its translations
   (see the sysmsg below, displayed in the next page)
   This allow sysmsg to be translated in the right *futur* language ...
   ... and some debug with a temporary page translation via the URL.
*/
   $language = trim(get_request_arg('language'));

   if( $language === 'C'
         || ( $language === 'N' && @$player_row['Translator'] )
         || ( $language !== $player_row['Lang'] && language_exists($language) ) )
   {
       $query .= "Lang='" . $language . "', ";
   }

   $timezone = get_request_arg('timezone') ;
   $nightstart = mod( (int)@$_REQUEST['nightstart'], 24) ;
   if( $nightstart != $player_row["Nightstart"] || $timezone != $player_row["Timezone"] )
   {
      $query .= "ClockChanged='Y', ";
      // ClockUsed is updated only once a day to prevent eternal night...
      // setTZ( $timezone); //for get_clock_used()
      // $query .= "ClockUsed=" . get_clock_used($nightstart) . ", ";
   }

   $query .= "Timezone='" . $timezone . "', " .
       "Nightstart=" . $nightstart .
       " WHERE ID=$my_id LIMIT 1";

   ta_begin();
   {//HOT-section to update players profile
      // table (Players)
      db_query( "change_profile($my_id)", $query );

      // table (ConfigBoard)
      if( $save_config_board )
         $cfg_board->update_all();
   }
   ta_end();

   $msg = urlencode(T_('Profile updated!'));
   jump_to("userinfo.php?uid=" . $player_row['ID'] .URI_AMP."sysmsg=$msg");
}
?>

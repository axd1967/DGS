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
require_once( "include/rating.php" );
require_once( "include/countries.php" );

{
   disable_cache();
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   if( strlen( $_GET['name'] ) < 1 )
      error("name_not_given");

   if( $_GET['emailnotify'] == 0 or empty($email) )
      $sendemail = '';

   if( $_GET['emailnotify'] >= 1 )
      $sendemail = 'ON';

   if( $_GET['emailnotify'] >= 2 )
      $sendemail .= ',MOVE,MESSAGE';

   if( $_GET['emailnotify'] == 3 )
      $sendemail .= ',BOARD';

   $boardcoords = ( $_GET['coordsleft'] ? LEFT : 0 ) + ( $_GET['coordsup'] ? UP : 0 ) +
      ( $_GET['coordsright'] ? RIGHT : 0 ) + ( $_GET['coordsdown'] ? DOWN : 0 ) +
      ( $_GET['smoothedge'] ? SMOOTH_EDGE : 0 );

   $menudirection = ( $_GET['$menudir'] == 'HORIZONTAL' ? 'HORIZONTAL' : 'VERTICAL' );

   $notessmallmode = ( @$_GET['notessmallmod'] == 'RIGHT' ? 'RIGHT' : ( @$_GET['notessmallmod'] == 'BELOW' ? 'BELOW' : 'OFF') );
   $noteslargemode = ( @$_GET['noteslargemod'] == 'RIGHT' ? 'RIGHT' : ( @$_GET['noteslargemod'] == 'BELOW' ? 'BELOW' : 'OFF') );

   $query = "UPDATE Players SET " .
      "Name='" . trim($name) . "', " .
      "Email='" . trim($email) . "', " .
      "Rank='" . trim($rank) . "', " .
      "Open='" . trim($open) . "', " .
      "SendEmail='$sendemail', ";

   if( isset($COUNTRIES[trim($country)]) )
      $query .= "Country='" . trim($country) . "', ";
   else if( empty($country) )
      $query .= "Country=NULL, ";

   if( $_GET['locally'] == 1 )
   {
      $cookie_prefs['Stonesize'] = $_GET['stonesize'];
      $cookie_prefs['Boardcoords'] = $boardcoords;
      $cookie_prefs['MenuDirection'] = $menudirection;
      $cookie_prefs['Woodcolor'] = $_GET['woodcolor'];
      $cookie_prefs['Button'] = $_GET['button'];
      $cookie_prefs['NotesSmallHeight'] = $_GET['notessmallheight'];
      $cookie_prefs['NotesSmallWidth'] = $_GET['notessmallwidth'];
      $cookie_prefs['NotesSmallMode'] = $notessmallmode;
      $cookie_prefs['NotesLargeHeight'] = $_GET['noteslargeheight'];
      $cookie_prefs['NotesLargeWidth'] = $_GET['noteslargewidth'];
      $cookie_prefs['NotesLargeMode'] = $noteslargemode;
      $cookie_prefs['NotesCutoff'] = $_GET['notescutoff'];

      set_cookie_prefs($player_row['ID']);
   }
   else
   {
      $query .=
         "Stonesize=" . $_GET['stonesize'] . ", " .
         "Boardcoords=$boardcoords, " .
         "MenuDirection='$menudirection', " .
         "Woodcolor=" . $_GET['woodcolor'] . ", " .
         "Button=" . $_GET['button'] . ", " .
         "NotesSmallHeight=" . $_GET['notessmallheight'] . ", " .
         "NotesSmallWidth=" . $_GET['notessmallwidth'] . ", " .
         "NotesSmallMode='$notessmallmode', " .
         "NotesLargeHeight=" . $_GET['noteslargeheight'] . ", " .
         "NotesLargeWidth=" . $_GET['noteslargewidth'] . ", " .
         "NotesLargeMode='$noteslargemode', " .
         "NotesCutoff=" . $_GET['notescutoff'] . ", ";
        

      set_cookie_prefs($player_row['ID'], true);
   }

   list($lang,$enc) = explode('.', $_GET['language']);

   if( $_GET['language'] === 'C' or ( $_GET['language'] !== $player_row['Lang'] and
                              array_key_exists($lang, $known_languages) and
                              array_key_exists($enc, $known_languages[$lang])) )
     {
       $query .= "Lang='" . $_GET['language'] . "', ";
     }

   if( $_GET['nightstart'] != $player_row["Nightstart"] ||
       $_GET['timezone'] != $player_row["Timezone"] )
   {
      putenv("TZ=" . $_GET['timezone'] );

      $query .= "ClockChanged='Y', ";

      // ClockUsed is uppdated only once a day to prevent eternal night...
      // $query .= "ClockUsed=" . get_clock_used($nightstart) . ", ";
   }


   $newrating = convert_to_rating($_GET['rating'], $_GET['ratingtype']);

   if( $player_row["RatingStatus"] != 'RATED' and is_numeric($newrating) and
       ( $ratingtype != 'dragonrating' or !is_numeric($player_row["Rating2"])
         or  abs($newrating - $player_row["Rating2"]) > 0.005 ) )
   {
      $query .= "Rating=$newrating, " .
         "InitialRating=$newrating, " .
         "Rating2=$newrating, " .
         "RatingMax=$newrating+200+GREATEST(1600-($newrating),0)*2/15, " .
         "RatingMin=$newrating-200-GREATEST(1600-($newrating),0)*2/15, " .
         "RatingStatus='INIT', ";

   }

   $query .= "Timezone='" . $_GET['timezone'] . "', " .
       "Nightstart=" . $_GET['nightstart'] .
       " WHERE ID=" . $player_row['ID'];

   mysql_query( $query )
      or error("mysql_query_failed");

   $msg = urlencode(T_('Profile updated!'));

   jump_to("userinfo.php?uid=" . $player_row["ID"] . "&msg=$msg");
}
?>
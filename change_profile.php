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

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   if( $player_row["Handle"] == "guest" )
      error("not_allowed_for_guest");

   $name = trim(get_request_arg('name')) ;
   if( strlen( $name ) < 1 )
      error("name_not_given");

   $email = trim(get_request_arg('email')) ;
   $sendemail = '';
   if( !empty($email) && @$_GET['emailnotify'] >= 1 )
   {
      $sendemail = 'ON';
      if( @$_GET['emailnotify'] >= 2 )
      {
         $sendemail .= ',MOVE,MESSAGE';
         if( @$_GET['emailnotify'] >= 3 )
            $sendemail .= ',BOARD';
      }
   }

   $boardcoords = ( @$_GET['coordsleft'] ? LEFT : 0 ) + ( @$_GET['coordsup'] ? UP : 0 ) +
      ( @$_GET['coordsright'] ? RIGHT : 0 ) + ( @$_GET['coordsdown'] ? DOWN : 0 ) +
      ( @$_GET['coordsover'] ? OVER : 0 ) + ( @$_GET['smoothedge'] ? SMOOTH_EDGE : 0 );

   $menudirection = ( @$_GET['menudir'] == 'HORIZONTAL' ? 'HORIZONTAL' : 'VERTICAL' );

   $notessmallmode = @$_GET['notessmallmod'];
   if( !$notessmallmode )
      $notessmallmode = 'RIGHT';
   $noteslargemode = @$_GET['noteslargemod'];
   if( !$noteslargemode )
      $noteslargemode = 'RIGHT';


   $query = "UPDATE Players SET " .
      "Name='" . addslashes($name) . "', " .
      "Email='" . addslashes($email) . "', " .
      "Rank='" . addslashes(trim(get_request_arg('rank'))) . "', " .
      "Open='" . addslashes(trim(get_request_arg('open'))) . "', " .
      "SendEmail='$sendemail', ";

   $country = trim(get_request_arg('country')) ;
   if( isset($COUNTRIES[$country]) )
      $query .= "Country='" . addslashes($country) . "', ";
   else if( empty($country) )
      $query .= "Country=NULL, ";

   if( @$_GET['locally'] == 1 )
   {
      $cookie_prefs['Stonesize'] = @$_GET['stonesize'];
      $cookie_prefs['Boardcoords'] = $boardcoords;
      $cookie_prefs['MenuDirection'] = $menudirection;
      $cookie_prefs['Woodcolor'] = @$_GET['woodcolor'];
      $cookie_prefs['Button'] = @$_GET['button'];
      $cookie_prefs['NotesSmallHeight'] = @$_GET['notessmallheight'];
      $cookie_prefs['NotesSmallWidth'] = @$_GET['notessmallwidth'];
      $cookie_prefs['NotesSmallMode'] = $notessmallmode;
      $cookie_prefs['NotesLargeHeight'] = @$_GET['noteslargeheight'];
      $cookie_prefs['NotesLargeWidth'] = @$_GET['noteslargewidth'];
      $cookie_prefs['NotesLargeMode'] = $noteslargemode;
      $cookie_prefs['NotesCutoff'] = @$_GET['notescutoff'];

      set_cookie_prefs($player_row['ID']);
   }
   else
   {
      $query .=
         "Stonesize=" . @$_GET['stonesize'] . ", " .
         "Boardcoords=$boardcoords, " .
         "MenuDirection='$menudirection', " .
         "Woodcolor=" . @$_GET['woodcolor'] . ", " .
         "Button=" . @$_GET['button'] . ", " .
         "NotesSmallHeight=" . @$_GET['notessmallheight'] . ", " .
         "NotesSmallWidth=" . @$_GET['notessmallwidth'] . ", " .
         "NotesSmallMode='$notessmallmode', " .
         "NotesLargeHeight=" . @$_GET['noteslargeheight'] . ", " .
         "NotesLargeWidth=" . @$_GET['noteslargewidth'] . ", " .
         "NotesLargeMode='$noteslargemode', " .
         "NotesCutoff=" . @$_GET['notescutoff'] . ", ";         

      set_cookie_prefs($player_row['ID'], true);
   }

   $language = trim(@$_GET['language']) ;
   list($lang,$charenc) = explode('.', $language);

   if( $language === 'C' or ( $language !== $player_row['Lang'] and
                              array_key_exists($lang, $known_languages) and
                              array_key_exists($charenc, $known_languages[$lang])) )
   {
       $query .= "Lang='" . $language . "', ";
   }


   $ratingtype = @$_GET['ratingtype'] ;
   $newrating = convert_to_rating(@$_GET['rating'], $ratingtype);

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

   $timezone = @$_GET['timezone'] ;
   $nightstart = @$_GET['nightstart'] ;
   if( $nightstart != $player_row["Nightstart"] ||
       $timezone != $player_row["Timezone"] )
   {
      putenv("TZ=" . $timezone );

      $query .= "ClockChanged='Y', ";

      // ClockUsed is uppdated only once a day to prevent eternal night...
      // $query .= "ClockUsed=" . get_clock_used($nightstart) . ", ";
   }

   $query .= "Timezone='" . $timezone . "', " .
       "Nightstart=" . $nightstart .
       " WHERE ID=" . $player_row['ID'];

   mysql_query( $query )
      or error("mysql_query_failed");

   $msg = urlencode(T_('Profile updated!'));

   jump_to("userinfo.php?uid=" . $player_row["ID"] . "&msg=$msg");
}
?>
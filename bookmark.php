<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( "include/std_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row );
   if( !$logged_in )
      error('not_logged_in');

   $my_id = $player_row['ID'];

   $system_bookmarks = array(
      // recent forum posts during the last 4 weeks
      'S1' => 'forum/search.php?order=1&sf5=4&sf5tu=8&sf_init=1',
      // opponents "online" during the last 10 mins
      'S2' => 'opponents.php?ssf4=1&sf14=10&sf14tu=64&sort1=14&desc1=1',
      // users "online" during the last 5 mins
      'S3' => 'users.php?sf14=5&sf14tu=64&sort1=14&active=0',
      // shortcut to edit vacation
      'S4' => 'edit_vacation.php',
      // shortcut to editing profile
      'S5' => 'edit_profile.php',
   );

   // get and check args
   $jumpto = get_request_arg('jumpto');
   $referer_url = @$_SERVER['HTTP_REFERER'];

   // open bookmarked URL
   $target_url = @$system_bookmarks[$jumpto];
   if( !empty($target_url) )
      jump_to( $base_path . $target_url );
   else
      jump_to( $referer_url, true ); // absolute

   // should not be reached
   exit(0);
}
?>

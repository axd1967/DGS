<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir( '../' );
require_once 'include/std_functions.php';
require_once 'include/make_translationfiles.php';

{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'scripts.generate_translation_texts');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'scripts.generate_translation_texts');
   if ( !(@$player_row['admin_level'] & ADMIN_TRANSLATORS) )
      error('adminlevel_too_low', 'scripts.generate_translation_texts');

   $page = $_SERVER['PHP_SELF'];
   $page_args = array();

   $TheErrors->set_mode(ERROR_MODE_PRINT);

   start_html('generate_translation_texts', 0);

   if ( $do_it=@$_REQUEST['do_it'] )
   {
      echo "<p>*** Fixes errors ***"
         ."<br>".anchor(make_url($page, $page_args), 'Just show it')
         ."</p>";
   }
   else
   {
      $tmp = array_merge($page_args,array('do_it' => 1));
      echo "<p>(just show needed queries)"
         ."<br>".anchor(make_url($page, $page_args), 'Show it again')
         ."<br>".anchor(make_url($page, $tmp), '[Validate it]')
         ."</p>";
   }

   generate_translation_texts( $do_it );

   echo "<p>Done!!!\n";
   end_html();
}//main

?>

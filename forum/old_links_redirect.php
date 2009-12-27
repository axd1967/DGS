<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

chdir('..');
require_once( "include/quick_common.php" );

{
   connect2mysql();

   $old_forum = @$_REQUEST['f']+0;
   $old_thread = @$_REQUEST['t']+0;
   $old_id = @$_REQUEST['i']+0;

   $row = mysql_single_fetch('forum_old_links_redirect',
         "SELECT ID, Thread_ID, Forum_ID FROM Posts " .
         "WHERE Forum_ID='$old_forum' AND old_ID='$old_id'" )
      or error('unknown_post');

   header(
      "Location: ../forum/read.php?forum=" . $row['Forum_ID'] .
      "&thread=" . $row['Thread_ID'] . "#" . $row['ID'] );
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once( "include/std_functions.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   $ThePage['class']= 'Docs'; //temporary solution to CSS problem
   start_page(T_('Snapshot'), true, $logged_in, $player_row );

   section( 'Docs', T_('Snapshots of the source code'));
   centered_container();

   add_link_page_link('snapshot/DragonGoServer_cvs.tar.gz', 'DragonGoServer_cvs.tar.gz', T_('The latest version of the source code, directly from the cvs'));

   add_link_page_link('snapshot/DragonGoServer.tar.gz', 'DragonGoServer.tar.gz', T_('The code this server is running'));

   add_link_page_link('snapshot/images.tar.gz', 'images.tar.gz', T_('The colletion of images used on the server'));

   add_link_page_link();

   section( 'Older versions', T_('Older versions'));
   centered_container();

   if ($handle = @opendir('snapshot/archive'))
   {
      while (false !== ($file = readdir($handle)))
         if ($file[0] != ".")
            add_link_page_link("snapshot/archive/$file", $file);

      closedir($handle);
   }

   add_link_page_link();

   end_page();
}

?>

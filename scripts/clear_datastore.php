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

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/form_functions.php';


{
   disable_cache();

   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      error('not_logged_in', 'scripts.clear_datastore');
   if( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low', 'scripts.clear_datastore');

   $subdir = get_request_arg('subdir');

   $page = "clear_datastore.php";

   $title = 'Clear Data-Store';
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   $form = new Form('cdsform', $page, FORM_GET, true);

   // read and print subdirs -------------------------

   $path_datastore = build_path_dir( $_SERVER['DOCUMENT_ROOT'], DATASTORE_FOLDER );
   $subdirs = read_subdirs( $path_datastore );

   echo "Choose sub-dir of datastore-folder [$path_datastore] and action to perform:<br>\n";
   echo "<table><tr><td><dl>\n";
   foreach( $subdirs as $dir )
      printf("  <dd>%s\n", $form->print_insert_radio_buttonsx('subdir', array( $dir => "$dir/*" ), ($dir == $subdir)) );
   echo "</dl>\n</td></tr></table>";
   echo $form->print_insert_submit_button('list', 'List Files'),
      SMALL_SPACING,
      $form->print_insert_submit_button('del',  'Delete Files');

   if( @$_REQUEST['list'] && $subdir )
      list_files( $path_datastore, $subdir );
   elseif( @$_REQUEST['del'] && $subdir )
      delete_files( $path_datastore, $subdir );

   echo $form->print_end();

   $menu_array = array( $title => "scripts/$page" );
   end_page(@$menu_array);
}//main


function read_subdirs( $path )
{
   $out = array();
   $arr = glob("$path/*", GLOB_ONLYDIR);
   foreach( $arr as $item )
   {
      if( is_dir($item) )
         $out[] = basename($item);
   }
   return $out;
}//read_subdirs

function delete_files( $path, $subdir )
{
   $files = glob("$path/$subdir/*");
   $cnt = 0;
   section('result', 'Result');
   echo "<table><tr><td>\n",
      "<pre>\n",
      "Deleted files of sub-dir [$subdir]:\n";
   foreach( $files as $file )
   {
      if( !is_file($file) )
         continue;
      $cnt++;
      printf("  [%s]\n", basename($file));
      unlink($file);
   }
   echo "\n# Deleted $cnt files!\n",
      "</pre>\n",
      "</td></tr></table>\n";
   return $cnt;
}//delete_files

function list_files( $path, $subdir )
{
   $files = glob("$path/$subdir/*");
   $cnt = 0;
   section('result', 'Result');
   echo "<table><tr><td>\n",
      "<pre>\n",
      "Files of sub-dir [$subdir]:\n";
   foreach( $files as $file )
   {
      if( !is_file($file) )
         continue;
      $cnt++;
      printf("  [%s]\n", basename($file));
   }
   echo "\n# Found $cnt files\n",
      "</pre>\n",
      "</td></tr></table>\n";
}//list_files

?>

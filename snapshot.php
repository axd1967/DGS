<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Docs";

require_once 'include/std_functions.php';
$GLOBALS['ThePage'] = new Page('Docs');

/*
 * IMPORTANT NOTES:
 * - To create translation-data snapshot you may use (replace 'DBUSER' and 'DATABASE_NAME'):
 *   mysqldump --quick --extended-insert --compress --no-create-info -hdragongoserver.net -uDBUSER -p DATABASE_NAME  TranslationLanguages TranslationGroups TranslationPages TranslationTexts TranslationFoundInGroup Translations FAQ Intro Links | gzip -c > Translationdata.mysql.gz
 *
 * - To create a stable-snapshot from Git WITH images you may use (replace 'BRANCH_NAME'):
 *   git checkout BRANCH_NAME
 *   git archive --format=tar HEAD | gzip >DragonGoServer-BRANCH_NAME.tar.gz
 *
 * - To create stable-snapshot from Git WITHOUT images you may use (replace 'BRANCH_NAME'):
 *   git checkout BRANCH_NAME
 *   git archive --format=tar HEAD | tar --delete 5 7 9 11 13 17 21 25 29 35 42 50 images | gzip >DragonGoServer-BRANCH_NAME.tar.gz
 *
 * - To create stable-snapshot from CVS you may use (replace 'BRANCH_NAME' and 'SCM_USER'):
 *   cvs -d SCM_USER@dragongoserver.cvs.sourceforge.net:/cvsroot/dragongoserver export -r BRANCH_NAME -d DragonGoServer-BRANCH_NAME DragonGoServer
 *   tar czvf DragonGoServer-BRANCH_NAME.tar.gz DragonGoServer-BRANCH_NAME/
 *
 * - To create image-data you may use:
 *   tar czf images.tar.gz  images 5 7 9 11 13 17 21 25 29 35 42 50
 */


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row, LOGIN_DEFAULT_OPTS|LOGIN_SKIP_VFY_CHK );

   start_page(T_('Snapshot'), true, $logged_in, $player_row );

   section( 'current', T_('Snapshots of the source code'));
   centered_container();

   //add_link_page_link('snapshot/DragonGoServer-cvs.tar.gz', 'DragonGoServer-cvs.tar.gz',
      //T_('The latest version of the source code, directly from the cvs'));

   add_link_page_link('snapshot/DragonGoServer-stable-20140327.tar.gz', 'DragonGoServer-stable-20140327.tar.gz',
      sprintf( T_('The code this server is running (version %s)'), '1.0.17'));

   add_link_page_link('snapshot/DragonGoServer-stable-20130811.tar.gz', 'DragonGoServer-stable-20130811.tar.gz',
      sprintf( T_('The previous version %s'), '1.0.16'));

   add_link_page_link('snapshot/DragonGoServer-stable-20120610.tar.gz', 'DragonGoServer-stable-20120610.tar.gz',
      sprintf( T_('The version %s'), '1.0.15'));

   add_link_page_link('snapshot/DragonGoServer-stable-200812.tar.gz', 'DragonGoServer-stable-200812.tar.gz',
      sprintf( T_('The version %s'), '1.0.14'));

   add_link_page_link('snapshot/DragonGoServer-stable-200712.tar.gz', 'DragonGoServer-stable-200712.tar.gz',
      sprintf( T_('The version %s'), '1.0.13'));

   add_link_page_link('snapshot/DragonGoServer-stable-200608.tar.gz', 'DragonGoServer-stable-200608.tar.gz',
      sprintf( T_('The version %s'), '1.0.12'));

   add_link_page_link('snapshot/images.tar.gz', 'images.tar.gz',
      T_('The collection of images used on the server'));

   add_link_page_link('snapshot/Translationdata.mysql.gz', 'Translationdata.mysql.gz',
      T_('The translation data'));

   add_link_page_link();

   section( 'oldies', T_('Older versions'));
   centered_container();

   if ( $handle = @opendir('snapshot/archive') )
   {
      while ( false !== ($file = readdir($handle)) )
      {
         if ( $file[0] != "." )
            add_link_page_link("snapshot/archive/$file", $file);
      }

      closedir($handle);
   }

   add_link_page_link();

   end_page();
}

?>

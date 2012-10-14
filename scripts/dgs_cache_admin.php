<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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

//$TranslateGroups[] = "Admin";

chdir('..');
require_once 'include/std_functions.php';
require_once 'include/table_columns.php';
require_once 'include/table_infos.php';
require_once 'include/time_functions.php';


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'scripts.dgs_cache_admin');
   if( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low', 'scripts.dgs_cache_admin');

   $page = "scripts/dgs_cache_admin.php";

/* Actual REQUEST calls used
     ''                 : list cache config
     a=clear_apc        : clear APC-cache (op-code + user-entries)
     a=clear_file       : clear file-cache
     a=cleanup          : cleanup all cache-groups of DgsCache removing expired entries
     a=clear&gr=        : clear all entries for given cache-group
     a=clean&gr=        : cleanup all expired entries for given cache-group
     a=filelist&gr=     : list entries of file-cache for given cache-group
     a=del&gr=&file=    : delete single cache-entry $file for given cache-group
     a=view&gr=&file=   : view content of single cache-entry $file for given cache-group
     ..&j=filelist      : jump to filelist (after action handled)
*/

   $action = get_request_arg('a');
   $group = (int)get_request_arg('gr');
   $file = get_request_arg('file');
   $do_jump = ( get_request_arg('j') == 'filelist' );

   if( $action == 'clear_apc' ) // clear full APC-cache
   {
      $cache = new ApcCache();
      $cache->cache_clear();
      $_REQUEST['sysmsg'] = T_('APC Cache cleared (op-code + user-entries)!');
   }
   elseif( $action == 'clear_file' ) // clear full file-cache
   {
      $cache = new FileCache();
      $cnt_deleted = $cache->cache_clear();
      $_REQUEST['sysmsg'] = sprintf( T_('File Cache cleared (%s entries removed)!'), $cnt_deleted );
   }
   elseif( $action == 'cleanup' ) // run cleanup removing expired entries for all cache-groups
   {
      $cnt_deleted = DgsCache::cleanup_cache();
      $_REQUEST['sysmsg'] = sprintf( T_('Cleaned up %s expired entries on DgsCache!'), $cnt_deleted );
   }
   elseif( $action == 'clean' ) // cleanup single cache-group removing expired entries
   {
      $cnt_deleted = DgsCache::cleanup_cache( $group );
      $_REQUEST['sysmsg'] = sprintf( T_('Cleaned up %s expired entries for cache-group [%s]!'), $cnt_deleted, $group );
   }
   elseif( $action == 'clear' ) // Remove all entries for single cache-group
   {
      $cache = DgsCache::get_cache( $group );
      if( !is_null($cache) )
      {
         $cnt_deleted = $cache->cache_cleanup( $group, 0 );
         $_REQUEST['sysmsg'] = sprintf( T_('Cleared %s entries for cache-group [%s]!'), $cnt_deleted, $group );
      }
   }
   elseif( $action == 'del' && $file ) // Remove single cache-entry
   {
      if( DgsCache::delete( "dgs_cache_admin.del($group,$file)", $group, $file ) )
         $_REQUEST['sysmsg'] = sprintf( T_('Deleted cache-entry [%s] for cache-group [%s]!'), $file, $group );
   }
   if( $do_jump && is_numeric($_REQUEST['gr']) )
      jump_to($page.'?a=filelist'.URI_AMP."gr=$group".URI_AMP.'sysmsg='.urlencode(@$_REQUEST['sysmsg']));


   $title = 'DGS Cache Administration';
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   $sectmenu = array();
   $sectmenu[T_('List Cache Groups')] = $page;
   $sectmenu[T_('Full-List Cache Groups')] = $page.'?full=1';
   $sectmenu[T_('Cleanup All Expired')] = $page.'?a=cleanup';
   $sectmenu[T_('Clear APC Cache')] = $page.'?a=clear_apc';
   $sectmenu[T_('Clear File Cache')] = $page.'?a=clear_file';
   make_menu( $sectmenu, false);

   if( $action == 'filelist' && is_numeric($group) )
      build_file_list_cache_group( $group );
   elseif( $action == 'view' && is_numeric($group) && $file )
      build_view_cache_entry( $group, $file );
   else
      build_list_cache_config( @$_REQUEST['full'] );

   echo "<br>\n";

   end_page();
}//main


function build_list_cache_config( $full )
{
   global $page, $DGS_CACHE, $base_path, $ARR_CACHE_GROUP_NAMES, $ARR_CACHE_GROUP_CLEANUP;

   $table = new Table( 'dgscache', $page, null, '', TABLE_NO_SIZE|TABLE_NO_PAGE|TABLE_NO_SIZE|TABLE_ROWS_NAVI );
   $table->add_tablehead( 1, T_('##header'), 'Number' );
   $table->add_tablehead( 2, T_('Cache Group#header'), 'Left' );
   $table->add_tablehead( 3, T_('Cache Type#header'), 'Center' );
   $table->add_tablehead( 5, T_('Actions#header'), '' );
   $table->add_tablehead( 4, T_('Expire#header'), 'Center' );
   if( $full )
   {
      $table->add_tablehead( 6, T_('Entries#header'), 'Number' );
      $table->add_tablehead( 7, T_('Size [KB]#header'), 'Number' );
   }

   $sum_types = array(
      CACHE_TYPE_APC  => array( 'count' => 0, 'size' => 0 ),
      CACHE_TYPE_FILE => array( 'count' => 0, 'size' => 0 ),
      );
   $sum_all = array( 'count' => 0, 'size' => 0 );

   // show config for all cache-groups
   for( $cache_group=0; $cache_group <= MAX_CACHE_GRP; ++$cache_group )
   {
      $cache = DgsCache::get_cache( $cache_group, null, /*adm-mode*/true );
      $cache_type = $DGS_CACHE->get_cache_type( $cache_group );

      $actions = array();
      $apage = $base_path . $page . "?gr=$cache_group".URI_AMP;
      $group_name = @$ARR_CACHE_GROUP_NAMES[$cache_group];
      if( $cache_type == CACHE_TYPE_FILE )
      {
         $list_page = $apage.'a=filelist';
         $actions[] = anchor( $apage.'a=clear', T_('Clear') );
         $actions[] = anchor( $apage.'a=clean', T_('Clean Expired') );
      }
      elseif( $cache_type == CACHE_TYPE_APC )
         $list_page = $base_path.'scripts/apc-live.php?SCOPE=A'.URI_AMP.'SORT1=H'.URI_AMP.'SORT2=D'.URI_AMP.'COUNT=20'
            .URI_AMP.'OB=3' . ($cache_group == CACHE_GRP_DEFAULT ? '' : URI_AMP.'SEARCH='.urlencode($group_name) );
      else
         $list_page = false;

      if( $list_page )
         $group_name = anchor( $list_page, $group_name );

      $row_arr = array(
            1 => $cache_group,
            2 => $group_name,
            3 => ( $cache_type ) ? $cache_type : T_('None'),
            4 => build_cleanup_cycle( @$ARR_CACHE_GROUP_CLEANUP[$cache_group] ),
            5 => implode(SMALL_SPACING, $actions),
         );
      if( $full )
      {
         $cache_info = $cache->cache_info( $cache_group ); // [ count|size => ]
         $count = (int)@$cache_info['count'];
         $size  = (int)@$cache_info['size'];
         if( $count > 0 )
            $row_arr[6] = number_format( $count );
         if( $size > 0 )
            $row_arr[7] = number_format( $size / 1024 );

         $sum_types[$cache_type]['count'] += $count;
         $sum_types[$cache_type]['size'] += $size;
         $sum_all['count'] += $count;
         $sum_all['size'] += $size;
      }
      $table->add_row($row_arr);
   }

   // add sums
   foreach( $sum_types as $cache_type => $info )
   {
      $table->add_row( array(
            2 => span('bold', T_('Sum')),
            3 => $cache_type,
            6 => number_format( $info['count'] ),
            7 => number_format( $info['size'] / 1024 ),
         ));
   }
   $table->add_row( array(
         2 => span('bold', T_('Totals')),
         6 => span('bold', number_format( $sum_all['count'] ) ),
         7 => span('bold', number_format( $sum_all['size'] / 1024 ) ),
      ));

   echo "<br>\n";
   $table->echo_table();
}//build_list_cache_groups

function build_cleanup_cycle( $secs )
{
   if( !is_numeric($secs) )
      return NO_VALUE;
   elseif( ($secs % SECS_PER_DAY) == 0 )
      return build_timeunit($secs / SECS_PER_DAY, '%s day', '%s days');
   elseif( ($secs % SECS_PER_HOUR) == 0 )
      return build_timeunit($secs / SECS_PER_HOUR, '%s hour', '%s hours');
   elseif( ($secs % SECS_PER_MIN) == 0 )
      return build_timeunit($secs / SECS_PER_MIN, '%s min', '%s mins');
   else
      return build_timeunit($secs, '%s sec', '%s secs');
}

function build_timeunit( $units, $single, $multi )
{
   return sprintf( ($units == 1) ? $single : $multi, $units );
}

function build_file_list_cache_group( $cache_group )
{
   global $page, $base_path, $ARR_CACHE_GROUP_NAMES;

   $table = new Table( 'dgscache', $page, null, '',
      TABLE_NO_SIZE|TABLE_NO_PAGE|TABLE_NO_SIZE|TABLE_NO_SORT|TABLE_ROWS_NAVI|TABLE_ROW_NUM );
   $table->RowNumDiff = -1; // force row-num starting with 0

   $table->add_tablehead( 1, T_('File#header'), '' );
   $table->add_tablehead( 2, T_('Size#header'), 'Number' );
   $table->add_tablehead( 3, T_('Last changed#header'), 'Date', TABLE_NO_HIDE, 'mtime+' );
   $table->add_tablehead( 4, T_('Last access#header'), 'Date' );
   $table->add_tablehead( 5, T_('Actions#header'), '' );

   $table->set_default_sort( 3 );
   $table->make_sort_images();

   $file_cache = new FileCache();
   $arr_files = $file_cache->get_cache_files( $cache_group ); // [ file, size, mtime, atime ], ...
   $totals = array_shift( $arr_files );
   usort( $arr_files, '_compare_cache_files_mtime' ); // order by last-changed-time

   $actions = array();
   if( count($arr_files) > 0 )
   {
      $actions[] = anchor( $base_path . $page . '?j=filelist'.URI_AMP.'a=clear'.URI_AMP."gr=$cache_group", T_('Clear') );
      $actions[] = anchor( $base_path . $page . '?j=filelist'.URI_AMP.'a=clean'.URI_AMP."gr=$cache_group", T_('Clean Expired') );
   }

   $table->add_row( array( // totals
         1 => span('bold', T_('Totals') . ' #' . count($arr_files)),
         2 => span('bold', number_format($totals[1])),
         5 => implode(SMALL_SPACING, $actions),
      ));

   $apage = $base_path . $page . "?gr=$cache_group".URI_AMP;
   foreach( $arr_files as $info )
   {
      list( $file, $size, $mtime, $atime ) = $info;

      $row_arr = array(
            1 => anchor( $apage . 'a=view'.URI_AMP.'file='.urlencode($file), $file ),
            2 => number_format($size),
            3 => date(DATE_FMT_QUICK, $mtime),
            4 => date(DATE_FMT_QUICK, $atime),
            5 => anchor( $apage . 'j=filelist'.URI_AMP.'a=del'.URI_AMP.'file='.urlencode($file), T_('Delete') ),
         );
      $table->add_row($row_arr);
   }

   echo "<br>\n",
      sprintf( "<h3>Cache entries for cache-group #%d (%s) in cache-dir [%s]:</h3>\n",
         $cache_group, @$ARR_CACHE_GROUP_NAMES[$cache_group], $totals[0] );
   $table->echo_table();
}//build_file_list_cache_group

// \internal
function _compare_cache_files_mtime( $item1, $item2 )
{
   $a = $item1[2]; // [file, size, mtime, atime]
   $b = $item2[2];

   // could use cmp_int(), but inline is faster
   if ($a == $b)
      return 0;
   else
      return ($a < $b) ? -1 : 1;
}

function build_view_cache_entry( $cache_group, $id )
{
   global $page, $ARR_CACHE_GROUP_NAMES;

   $file_cache = new FileCache();
   $file_cache->set_unlink_expired( false ); // keep expired-cache-files on probing by admin
   $file_cache->set_read_expired( true ); // read value even if expired when probing by admin

   $data = $file_cache->get_cache_file( $id, $cache_group );
   if( !is_array($data) )
   {
      echo "<br>\n",
         sprintf( "<h3>No cache entry [%s] found for cache-group #%d (%s)!</h3>\n",
            $id, $cache_group, @$ARR_CACHE_GROUP_NAMES[$cache_group] );
   }
   else
   {
      $table = new Table( 'dgscache', $page, null, '', TABLE_NO_SIZE|TABLE_NO_PAGE|TABLE_NO_SIZE|TABLE_ROWS_NAVI );
      $table->add_tablehead( 1, T_('Attribute#header'), '' );
      $table->add_tablehead( 2, T_('Value#header'), '' );

      list( $content, $size, $mtime, $atime ) = $data;
      $fmt_content = htmlspecialchars( var_export($content, true), ENT_QUOTES, 'UTF-8' );

      $table->add_row( array( 1 => T_('Info#header'),          2 => $id, ));
      $table->add_row( array( 1 => T_('Last changed#header'),  2 => date(DATE_FMT_QUICK, $mtime), ));
      $table->add_row( array( 1 => T_('Last access#header'),   2 => date(DATE_FMT_QUICK, $atime), ));
      $table->add_row( array( 1 => T_('Size#header'),          2 => number_format($size), ));
      $table->add_row( array( 1 => T_('Stored Value#header'),  2 => "<pre>$fmt_content</pre>", ));

      echo "<br>\n",
         sprintf( "<h3>Cache entry [%s] for cache-group #%d (%s):</h3>\n",
            $id, $cache_group, @$ARR_CACHE_GROUP_NAMES[$cache_group] );
      $table->echo_table();
   }
}//build_view_cache_entry

?>

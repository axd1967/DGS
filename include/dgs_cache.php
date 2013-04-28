<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

 /* Author: Jens-Uwe Gaspar */

require_once 'include/globals.php';
require_once 'include/utilities.php';

 /*!
  * \file dgs_cache.php
  *
  * \brief Container and function to handle caching using different cache methods on DGS.
  */

if( !defined('DGS_CACHE') )
   define('DGS_CACHE', CACHE_TYPE_NONE);
if( !defined('DBG_CACHE') )
   define('DBG_CACHE', 0);


/*! \brief basic cache interface with basic cache-operations. */
abstract class AbstractCache
{
   /*! \brief Returns true if cache-implementation is storing persistent data (so other requests can see same data). */
   abstract public function is_persistent_cache();

   /*! \brief Returns stored cache-entry for given cache-key; or else null. */
   abstract public function cache_fetch( $id, $cache_group=0 );

   /*!
    * \brief Stores cache-entry for given cache-key with given TTL [secs].
    * \param $data storing NULL is NOT possible!
    */
   abstract public function cache_store( $id, $data, $ttl, $cache_group=0 );

   /*!
    * \brief Stores cache-entries in array( cache_key => data ) with given TTL [secs].
    * \param $arr_data storing NULL is NOT possible for array-values!
    * \note overwrite if there's a special implementation.
    */
   public function cache_store_array( $arr_data, $ttl, $cache_group=0 )
   {
      foreach( $arr_data as $id => $data )
         $this->cache_store( $id, $data, $ttl, $cache_group );
   }

   /*! \brief Deletes single cache-entry with given key $id. */
   abstract public function cache_delete( $id, $cache_group=0 );

   /*!
    * \brief Purge cache-entries of given cache-group for expired entries (if possible).
    * \return number of entries purged; false if cache does not support cleanup
    */
   abstract public function cache_cleanup( $cache_group, $expire_time );

   /*!
    * \brief Clears all cache-entries.
    * \return number of entries purged; 1 also if count cannot be determined.
    */
   abstract public function cache_clear();

   /*!
    * \brief Returns info about cache-content for given cache-group.
    * \return arr( count => #entries, size => bytes-taken, hits => #hits, misses => #misses )
    */
   abstract public function cache_info( $cache_group );

   /*! \brief Stores cache-entry with group of other cache-keys (used to clear cache for a group of related elements). */
   public function cache_store_group( $group_id, $elem_id, $ttl, $cache_group=0 )
   {
      $arr_group = $this->cache_fetch( $group_id, $cache_group );
      if( is_null($arr_group) )
         $arr_group = array( $elem_id );
      else if( !in_array($elem_id, $arr_group) ) // keep unique
         $arr_group[] = $elem_id;

      return $this->cache_store( $group_id, $arr_group, $ttl, $cache_group );
   }//cache_store_group

   /*! \brief Deletes all cache-entries of cache-group and group-id itself. */
   public function cache_delete_group( $group_id, $cache_group=0 )
   {
      $arr_group = $this->cache_fetch( $group_id, $cache_group );
      if( !is_null($arr_group) )
      {
         $arr_group = array_unique( $arr_group );
         foreach( $arr_group as $elem_id )
            $this->cache_delete( $elem_id, $cache_group );
         $this->cache_delete( $group_id, $cache_group );
      }
      return $arr_group;
   }//cache_delete_group

} // end of 'AbstractCache'



/*! \brief Cache-implementation with (shared-memory) APC-based cache. */
class ApcCache extends AbstractCache
{
   public function is_persistent_cache()
   {
      return true;
   }

   public function cache_fetch( $id, $cache_group=0 )
   {
      $result = apc_fetch($id, $success);
      return ( $success ) ? $result : null;
   }

   public function cache_store( $id, $data, $ttl, $cache_group=0 )
   {
      return apc_store( $id, $data, $ttl );
   }

   public function cache_store_array( $arr_data, $ttl, $cache_group=0 )
   {
      return apc_store( $arr_data, null, $ttl );
   }

   public function cache_delete( $id, $cache_group=0 )
   {
      return apc_delete($id);
   }

   public function cache_cleanup( $cache_group, $expire_time )
   {
      $entries = $this->get_cache_entries( $cache_group, $expire_time );
      $count_deleted = 0;
      foreach( $entries as $id )
      {
         if( apc_delete($id) )
            ++$count_deleted;
      }
      return $count_deleted;
   }//cache_cleanup

   public function cache_clear()
   {
      apc_clear_cache(); // clear op-code-cache
      apc_clear_cache('user');
      return 1;
   }

   public function cache_info( $cache_group )
   {
      global $ARR_CACHE_GROUP_NAMES;
      $count = $size = $hits = $misses = 0;

      $group_name = @$ARR_CACHE_GROUP_NAMES[$cache_group];
      if( $group_name )
      {
         $info = apc_cache_info('user');
         $misses = 0; // only global for cache-lifetime, so not available

         foreach( $info['cache_list'] as $entry )
         {
            if( stristr($entry['info'], $group_name) === false )
               continue;
            ++$count;
            $size += $entry['mem_size'];
            $hits += $entry['num_hits'];
         }
      }

      return array( 'count' => $count, 'size' => $size, 'hits' => $hits, 'misses' => $misses );
   }//cache_info


   // NOTE: not thread-save, but must only be roughly accurate to get an idea about hits and misses of other cache-types
   // \static
   // \param $hit true = cache-hit, false = cache-miss
   public static function saveHit( $cache_group, $hit )
   {
      global $DGS_CACHE_GROUPS;
      if( !function_exists('apc_fetch') )
         return;

      $cache_type = ( isset($DGS_CACHE_GROUPS[$cache_group]) ) ? $DGS_CACHE_GROUPS[$cache_group] : DGS_CACHE;
      if( $cache_type != CACHE_TYPE_APC && $cache_type != CACHE_TYPE_NONE )
      {
         $cache_id = "CacheGroups";
         $arr_key = sprintf('%s%02d', ($hit ? 'H' : 'M'), $cache_group );
         $arr_info = apc_fetch($cache_id);
         if( is_array($arr_info) )
         {
            if( isset($arr_info[$arr_key]) )
               ++$arr_info[$arr_key];
            else
               $arr_info[$arr_key] = 1;
         }
         else
            $arr_info = array( $arr_key => 1 );
         apc_store($cache_id, $arr_info, SECS_PER_DAY);
      }
   }//saveHit

   // \static
   function getHitInfo( $cache_group )
   {
      $hits = $miss = 0;
      if( function_exists('apc_fetch') )
      {
         $cache_id = "CacheGroups";
         $arr_info = apc_fetch($cache_id);
         if( is_array($arr_info) )
         {
            $key_hit  = sprintf('H%02d', $cache_group );
            $key_miss = sprintf('M%02d', $cache_group );
            $hits = (int)@$arr_info[$key_hit];
            $miss = (int)@$arr_info[$key_miss];
         }
      }

      return array( $hits, $miss );
   }//getHitInfo


   // \param $expire_time 0 = return all matching entries, >0 = only return matching entries that have expired
   private function get_cache_entries( $cache_group, $expire_time=0 )
   {
      global $ARR_CACHE_GROUP_NAMES;
      $result = array();

      $group_name = @$ARR_CACHE_GROUP_NAMES[$cache_group];
      if( $group_name )
      {
         $info = apc_cache_info('user');
         foreach( $info['cache_list'] as $entry )
         {
            if( stristr($entry['info'], $group_name) !== false )
            {
               if( $expire_time == 0 || $entry['mtime'] <= $expire_time ) // entry expired?
                  $result[] = $entry['info'];
            }
         }
      }

      return $result;
   }//get_cache_entries

} // end of 'ApcCache'



/*!
 * \class FileCache
 *
 * \brief Cache-implementation for file-based cache.
 *
 * \note adapted source Sabre_Cache_Filesystem taken from http://www.rooftopsolutions.nl/blog/107
 *       to match DGS-environment and needs.
 */
class FileCache extends AbstractCache
{
   private $base_filepath;
   private $unlink_expired = true; // true = unlink expired files on cache_fetch()
   private $read_expired = false; // false = return NULL if data expired on cache_fetch()

   public function __construct()
   {
      if( (string)DATASTORE_FOLDER == '' )
         error('internal_error', "FileCache.construct.miss.datastore");

      $this->base_filepath = build_path_dir( $_SERVER['DOCUMENT_ROOT'], DATASTORE_FOLDER ) . 'filecache';
      for( $cache_group=0; $cache_group <= MAX_CACHE_GRP; ++$cache_group )
      {
         $path = $this->build_cache_filename( 0, $cache_group, /*dir*/true );
         if( !is_dir($path) )
         {
            if( !mkdir($path, 0777, /*recursive*/true) )
               error('internal_error', "FileCache.construct.mkdir.datastore_dir(filecache,$cache_group)");
         }
      }
   }//__construct

   public function set_unlink_expired( $unlink_expired )
   {
      $this->unlink_expired = $unlink_expired;
   }

   public function set_read_expired( $read_expired )
   {
      $this->read_expired = $read_expired;
   }

   private function build_cache_filename( $id, $cache_group=0, $dir_only=false )
   {
      $path = ( $cache_group <= 0 )
         ? $this->base_filepath
         : $this->base_filepath . sprintf( '-%02d', $cache_group );
      return ($dir_only) ? $path : $path . '/' . $id;
   }

   public function is_persistent_cache()
   {
      return true;
   }

   public function cache_fetch( $id, $cache_group=0 )
   {
      $filename = $this->build_cache_filename( $id, $cache_group );
      if( !file_exists($filename) )
      {
         ApcCache::saveHit( $cache_group, false );
         return null;
      }

      $file_handle = fopen($filename, 'r'); // open read-only
      if( !$file_handle )
      {
         ApcCache::saveHit( $cache_group, false );
         return null;
      }

      flock($file_handle, LOCK_SH); // get shared lock
      $raw_data = @file_get_contents($filename);
      fclose($file_handle);

      $result = null;
      if( $raw_data )
      {
         $file_data = @unserialize($raw_data);
         if( $file_data )
         {
            list( $expire, $data ) = $file_data;
            if( $this->read_expired || $GLOBALS['NOW'] <= $expire )
               $result = $data;
         }
      }

      if( $this->unlink_expired && is_null($result) )
      {
         @unlink($filename); // delete file if loading or unserialize failed, or file expired
         ApcCache::saveHit( $cache_group, false );
      }
      else
         ApcCache::saveHit( $cache_group, true );

      return $result;
   }//cache_fetch

   public function cache_store( $id, $data, $ttl, $cache_group=0 )
   {
      $filename = $this->build_cache_filename( $id, $cache_group );

      $file_handle = fopen($filename, 'a+'); // open read-write (append) to get lock of potentially existing file
      if( !$file_handle )
         return false;

      flock($file_handle, LOCK_EX); // get exclusive lock
      fseek($file_handle, 0);
      ftruncate($file_handle, 0);

      $raw_data = serialize( array( $GLOBALS['NOW'] + $ttl, $data ) ); // store: array( expire-time, $data )
      $result = fwrite($file_handle, $raw_data);
      fclose($file_handle);

      return $result;
   }//cache_store

   public function cache_delete( $id, $cache_group=0 )
   {
      $filename = $this->build_cache_filename( $id, $cache_group );
      return ( file_exists($filename) ) ? unlink($filename) : false;
   }

   /*!
    * \brief Purges expired files in cache-dir for given cache-group, return number of deleted files.
    * \param $expire_time if 0 clear all entries; if > 0 removing entries older than expire-time
    */
   public function cache_cleanup( $cache_group, $expire_time )
   {
      $dir_path = $this->build_cache_filename( 0, $cache_group, /*dir*/true );
      $arr_files = glob("$dir_path/*");

      $cnt = 0;
      foreach( $arr_files as $file )
      {
         if( !is_file($file) )
            continue;
         if( $expire_time == 0 || (int)@filemtime($file) <= $expire_time ) // file expired?
         {
            if( unlink($file) )
               ++$cnt;
         }
      }

      if( DBG_CACHE && $cnt > 0 ) error_log("FileCache.cache_cleanup($cache_group): purged $cnt entries");
      return $cnt;
   }//cache_cleanup

   public function cache_clear()
   {
      $cnt = 0;
      for( $cache_group=0; $cache_group <= MAX_CACHE_GRP; ++$cache_group )
         $cnt += $this->cache_cleanup( $cache_group, 0 );
      return $cnt;
   }

   public function cache_info( $cache_group )
   {
      $entries = $this->get_cache_files( $cache_group );
      $totals = array_shift($entries);
      list( $hits, $misses ) = ApcCache::getHitInfo( $cache_group );

      return array( 'count' => count($entries), 'size' => $totals[1], 'hits' => $hits, 'misses' => $misses );
   }//cache_info


   /*!
    * \brief Returns info about cache-file-entry for given cache-group.
    * \return array of arr( filename, size, last-modify-time, last-access-time )
    *       with first row containing sum with arr( dir, total-size ).
    */
   public function get_cache_files( $cache_group )
   {
      $dir_path = $this->build_cache_filename( 0, $cache_group, /*dir*/true );
      $arr_files = glob("$dir_path/*");
      $len_docroot = strlen($_SERVER['DOCUMENT_ROOT']) + 1;
      $len_dir = strlen($dir_path) + 1;

      $result = array();
      $total_size = 0;
      foreach( $arr_files as $file )
      {
         if( is_file($file) )
         {
            $stat = stat($file);
            if( $stat !== false )
            {
               $result[] = array( substr($file, $len_dir), $stat['size'], $stat['mtime'], $stat['atime'] );
               $total_size += $stat['size'];
            }
         }
      }

      array_unshift( $result, array( substr($dir_path, $len_docroot), $total_size ) );

      return $result;
   }//get_cache_files

   /*!
    * \brief Returns info about single cache-file-entry for given cache-group and file.
    * \return array of arr( content, size, last-modify-time, last-access-time ); or null if not existing or on error.
    */
   public function get_cache_file( $id, $cache_group )
   {
      $content = $this->cache_fetch( $id, $cache_group );
      $result = null;
      if( !is_null($content) )
      {
         $file_path = $this->build_cache_filename( $id, $cache_group );
         if( is_file($file_path) )
         {
            $stat = stat($file_path);
            if( $stat !== false )
               $result = array( $content, (int)@$stat['size'], (int)@$stat['mtime'], (int)@$stat['atime'] );
            else
               $result = array( $content, 0, 0, 0 );
         }
      }

      return $result;
   }//get_cache_file

} // end of 'FileCache'




 /*!
  * \class DgsCache
  *
  * \brief Wrapper-class to cache objects with grouped-keys.
  *
  * \note use const DGS_CACHE (=Cache-class or 0=no-cache) to setup which cache-implementation to use
  * \note use const DBG_CACHE for logging cache-calls into error-log (if >0)
  *
  * \see specs/caching.txt
  */
class DgsCache
{
   /*! arr( CACHE_GRP_.. => CACHE_TYPE_.., ... ) */
   private $cache_groups = array();

   /*! arr( CACHE_TYPE_.. => initialized-cache-instance(-singleton), ... ) */
   private $cache_impl = array( CACHE_TYPE_NONE => null );


   private function __construct()
   {
      global $DGS_CACHE_GROUPS;

      $arr_cache_types = array( DGS_CACHE => 1 );
      if( @is_array($DGS_CACHE_GROUPS) )
      {
         foreach( $DGS_CACHE_GROUPS as $cache_group => $cache_type )
         {
            $this->cache_groups[$cache_group] = $cache_type;
            $arr_cache_types[$cache_type] = 1;
         }
      }

      if( isset($arr_cache_types[CACHE_TYPE_APC]) )
         $this->cache_impl[CACHE_TYPE_APC] = new ApcCache();
      if( isset($arr_cache_types[CACHE_TYPE_FILE]) )
         $this->cache_impl[CACHE_TYPE_FILE] = new FileCache();
   }//__construct

   /*!
    * \brief Return cache-type to use for cache-group.
    * \return can be one of CACHE_TYPE_NONE (no caching), CACHE_TYPE_APC (shared-mem), CACHE_TYPE_FILE (file-cache)
    */
   public function get_cache_type( $cache_group )
   {
      if( !is_numeric($cache_group) )
         error('invalid_args', "DgsCache.get_cache_type.check.cache_group($cache_group)");

      if( isset($this->cache_groups[$cache_group]) )
         $cache_type = $this->cache_groups[$cache_group];
      else
         $cache_type = DGS_CACHE; // use default if no special type set
      return $cache_type;
   }//get_cache_type

   /*!
    * \brief Returns cache-implementation for given cache-type.
    * \return cache-implementation (if used in cache-config, see constructor); otherwise null.
    */
   public function get_cache_impl( $cache_type )
   {
      return ( isset($this->cache_impl[$cache_type]) ) ? $this->cache_impl[$cache_type] : null;
   }


   // ------------ static functions ----------------------------

   /*!
    * \brief Returns true, if caching is persistent (meaning different request see same cache-content) and
    *        allowed for given cache-group.
    * \param $cache_group CACHE_GRP_...
    */
   public static function is_persistent( $cache_group )
   {
      $cache = self::get_cache( $cache_group );
      return ( is_null($cache) ) ? false : $cache->is_persistent_cache();
   }

   /*!
    * \brief Returns cache-implementation if caching enabled and caching for $cache_group is allowed.
    * \param $cache_group CACHE_GRP_...
    * \param $cache_type optional, CACHE_TYPE_... can ask for specific cache-type regardless of cache-group
    * \return NULL if caching disabled for given cache-group
    */
   public static function get_cache( $cache_group, $cache_type=null, $admin_mode=false )
   {
      global $DGS_CACHE;
      if( !@$DGS_CACHE )
         $DGS_CACHE = new DgsCache();

      if( !$admin_mode && $GLOBALS['is_maintenance'] ) // caching disabled during maintenance-mode
         $cache = null;
      else if( is_null($cache_type) )
      {
         $cache_type = $DGS_CACHE->get_cache_type( $cache_group );
         $cache = $DGS_CACHE->get_cache_impl( $cache_type );
      }
      else
         $cache = $DGS_CACHE->get_cache_impl( $cache_type );

      return $cache;
   }//get_cache


   /*!
    * \brief Fetches cache-entry for cache-group with cache-key $id.
    * \param $cache_group CACHE_GRP_...
    */
   public static function fetch( $dbgmsg, $cache_group, $id )
   {
      $cache = self::get_cache( $cache_group );
      if( !$cache )
         return null;

      $result = $cache->cache_fetch( $id, $cache_group );
      if( DBG_CACHE ) error_log("DgsCache.fetch($cache_group,$id).$dbgmsg = [" . self::debug_result($result) . "]");
      return $result;
   }//fetch

   /*!
    * \brief Stores cache-entry for cache-group with cache-key $id and given $data and $ttl (TimeToLive in secs).
    * \param $cache_group CACHE_GRP_...
    * \param $group_id optional group-name to collect specific cache-keys for collective delete with DgsCache::delete_group()
    */
   public static function store( $dbgmsg, $cache_group, $id, $data, $ttl, $group_id='' )
   {
      $cache = self::get_cache( $cache_group );
      if( !$cache )
         return false;

      $result = $cache->cache_store( $id, $data, $ttl, $cache_group );
      if( $group_id )
         $cache->cache_store_group( "GROUP_$group_id", $id, $ttl, $cache_group );

      if( DBG_CACHE ) error_log("DgsCache.store($cache_group,$id,$ttl,[$group_id]).$dbgmsg = [" . ($result ? 1 : 0) . "]");
      return $result;
   }//store

   /*!
    * \brief Deletes cache-entry for cache-group with cache-key $id.
    * \param $cache_group CACHE_GRP_...
    */
   public static function delete( $dbgmsg, $cache_group, $id )
   {
      $cache = self::get_cache( $cache_group );
      if( !$cache )
         return true;

      $result = $cache->cache_delete( $id, $cache_group );
      if( DBG_CACHE ) error_log("DgsCache.delete($cache_group,$id).$dbgmsg = [" . ($result ? 1 : 0) . "]");
      return $result;
   }//delete

   /*!
    * \brief Deletes multiple cache-entries collected under group $group_id for cache-group.
    * \param $cache_group CACHE_GRP_...
    * \param $group_id group-name under which specific cache-keys where collected, see $group_id for DgsCache::store()
    */
   public static function delete_group( $dbgmsg, $cache_group, $group_id )
   {
      $cache = self::get_cache( $cache_group );
      if( !$cache )
         return null;

      $arr_group = $cache->cache_delete_group( "GROUP_$group_id", $cache_group );
      if( DBG_CACHE ) error_log("DgsCache.delete_group($cache_group,$group_id).$dbgmsg: group [" . (is_null($arr_group) ? '-' : implode(' ', $arr_group)) . "]");
      return $arr_group;
   }//delete_group

   /*!
    * \brief Purges expired cache-entries for all supported cache-groups.
    * \param $only_cache_group null = cleanup all cache-groups; otherwise only given one
    * \return return number of deleted cache-entries.
    */
   public static function cleanup_cache( $only_cache_group=null )
   {
      global $NOW, $ARR_CACHE_GROUP_CLEANUP;
      if( !is_numeric($only_cache_group) || $only_cache_group < 0 || $only_cache_group > MAX_CACHE_GRP)
         $only_cache_group = null;

      $default_expire = ( isset($ARR_CACHE_GROUP_CLEANUP[CACHE_GRP_DEFAULT]) )
         ? $NOW - $ARR_CACHE_GROUP_CLEANUP[CACHE_GRP_DEFAULT]
         : $NOW - 7 * SECS_PER_DAY; // default-expire: 1 week

      $cnt_deleted = 0;
      $start_group = (is_numeric($only_cache_group)) ? $only_cache_group : 0;
      for( $cache_group=$start_group; $cache_group <= MAX_CACHE_GRP; ++$cache_group )
      {
         $cache = self::get_cache( $cache_group );
         if( !is_null($cache) )
         {
            $group_expire = ( isset($ARR_CACHE_GROUP_CLEANUP[$cache_group]) )
               ? $NOW - $ARR_CACHE_GROUP_CLEANUP[$cache_group]
               : $default_expire;
            $cnt_cleanup = $cache->cache_cleanup( $cache_group, $group_expire );
            if( is_numeric($cnt_cleanup) )
               $cnt_deleted += $cnt_cleanup;
         }
         if( is_numeric($only_cache_group) )
            break;
      }

      if( DBG_CACHE && $cnt_deleted > 0 ) error_log("DgsCache.cleanup_cache(SUM): purged $cnt_deleted total entries");
      return $cnt_deleted;
   }//cleanup_cache

   // \internal
   private static function debug_result( $value )
   {
      if( is_null($value) )
         return 'NULL';
      else
         return gettype($value) . ':' . ( is_object($value) ? get_class($value) : $value );
   }

} // end of 'DgsCache'

?>

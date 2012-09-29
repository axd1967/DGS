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

 /* Author: Jens-Uwe Gaspar */

require_once 'include/globals.php';
require_once 'include/utilities.php';

 /*!
  * \file dgs_cache.php
  *
  * \brief Container and function to handle caching using different cache methods on DGS.
  */

if( !defined('DGS_CACHE') )
   define('DGS_CACHE', ''); // ''=no-cache, use const CACHE_TYPE_...
if( !defined('DBG_CACHE') )
   define('DBG_CACHE', 0);


/*! \brief basic cache interface with basic cache-operations. */
abstract class AbstractCache
{
   /*! \brief Returns true if cache-implementation is storing persistent data (so other requests can see same data). */
   abstract public function is_persistent_cache();

   /*! \brief Returns stored cache-entry for given cache-key; or else null. */
   abstract public function cache_fetch( $id );

   /*!
    * \brief Stores cache-entry for given cache-key with given TTL [secs].
    * \param $data storing NULL is NOT possible!
    */
   abstract public function cache_store( $id, $data, $ttl );

   /*!
    * \brief Stores cache-entries in array( cache_key => data ) with given TTL [secs].
    * \param $arr_data storing NULL is NOT possible for array-values!
    * \note overwrite if there's a special implementation.
    */
   public function cache_store_array( $arr_data, $ttl )
   {
      foreach( $arr_data as $id => $data )
         $this->cache_store( $id, $data, $ttl );
   }

   /*! \brief Deletes single cache-entry with given key $id. */
   abstract public function cache_delete( $id );


   /*! \brief Stores cache-entry with group of other cache-keys (used to clear cache for a group of related elements). */
   public function cache_store_group( $group_id, $elem_id, $ttl )
   {
      $arr_group = $this->cache_fetch( $group_id );
      if( is_null($arr_group) )
         $arr_group = array( $elem_id );
      else
         $arr_group[] = $elem_id;

      return $this->cache_store( $group_id, $arr_group, $ttl );
   }

   /*! \brief Deletes all cache-entries of cache-group and group-id itself. */
   public function cache_delete_group( $group_id )
   {
      $arr_group = $this->cache_fetch( $group_id );
      if( !is_null($arr_group) )
      {
         $arr_group = array_unique( $arr_group );
         foreach( $arr_group as $elem_id )
            $this->cache_delete( $elem_id );
         $this->cache_delete( $group_id );
      }
      return $arr_group;
   }

} // end of 'AbstractCache'



/*! \brief Cache-implementation with (shared-memory) APC-based cache. */
class ApcCache extends AbstractCache
{
   public function is_persistent_cache()
   {
      return true;
   }

   public function cache_fetch( $id )
   {
      $result = apc_fetch($id, $success);
      return ( $success ) ? $result : null;
   }

   public function cache_store( $id, $data, $ttl )
   {
      return apc_store( $id, $data, $ttl );
   }

   public function cache_store_array( $arr_data, $ttl )
   {
      return apc_store( $arr_data, null, $ttl );
   }

   public function cache_delete( $id )
   {
      return apc_delete($id);
   }
} // end of 'ApcCache'



/*!
 * \brief Cache-implementation for file-based cache.
 * \note adapted source Sabre_Cache_Filesystem taken from http://www.rooftopsolutions.nl/blog/107
 *       to match DGS-environment and needs.
 */
class FileCache extends AbstractCache
{
   var $base_filepath;

   function FileCache()
   {
      if( (string)DATASTORE_FOLDER == '' )
         error('internal_error', "FileCache.construct.miss.datastore");

      $path = build_path_dir( $_SERVER['DOCUMENT_ROOT'], DATASTORE_FOLDER ) . 'filecache';
      if( !is_dir($path) )
      {
         if( !mkdir($path, 0777, /*recursive*/true) )
            error('internal_error', "FileCahce.construct.miss.datastore_dir(filecache)");
      }
      $this->base_filepath = $path;
   }

   private function build_cache_filename( $id ) {
      return $this->base_filepath . '/' . $id;
   }

   public function is_persistent_cache()
   {
      return true;
   }

   public function cache_fetch( $id )
   {
      $filename = $this->build_cache_filename( $id );
      if( !file_exists($filename) )
         return null;

      $file_handle = fopen($filename, 'r'); // open read-only
      if( !$file_handle )
         return null;

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
            if( $GLOBALS['NOW'] <= $expire )
               $result = $data;
         }
      }

      if( is_null($result) )
         @unlink($filename); // delete file if loading or unserialize failed, or file expired

      return $result;
   }//cache_fetch

   public function cache_store( $id, $data, $ttl )
   {
      $filename = $this->build_cache_filename( $id );

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

   public function cache_delete( $id )
   {
      $filename = $this->build_cache_filename( $id );
      return ( file_exists($filename) ) ? unlink($filename) : false;
   }

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
   var $groups_enabled; // false = enable all CACHE_GRP_..-groups; otherwise arr( CACHE_GRP_.. => 1, ... )
   var $cache_impl;

   private function DgsCache()
   {
      global $DGS_CACHE_ENABLE_GROUPS;

      $arr = array();
      if( is_array($DGS_CACHE_ENABLE_GROUPS) )
      {
         foreach( $DGS_CACHE_ENABLE_GROUPS as $group )
            $arr[$group] = 1;
      }
      $this->groups_enabled = ( count($arr) > 0 ) ? $arr : false;

      // NOTE: wanted "$class=DGS_CACHE; $class::cache_func(..)" but needs PHP >= 5.3 (and live-server is still PHP 5.1)
      if( DGS_CACHE === CACHE_TYPE_APC )
         $this->cache_impl = new ApcCache();
      elseif( DGS_CACHE === CACHE_TYPE_FILE )
         $this->cache_impl = new FileCache();
      else
         $this->cache_impl = null;
   }//constructor

   public function allow_group_caching( $cache_group )
   {
      return ( is_array($this->groups_enabled) ) ? @$this->groups_enabled[(int)$cache_group] : true;
   }


   // ------------ static functions ----------------------------

   /*!
    * \brief Returns true, if caching is persistent (meaning different request see same cache-content) and
    *        allowed for given cache-group.
    * \param $cache_group CACHE_GRP_...
    */
   function is_persistent( $cache_group )
   {
      $cache = DgsCache::get_cache( $cache_group );
      return ( is_null($cache) ) ? false : $cache->is_persistent_cache();
   }

   /*!
    * \brief Returns cache-implementation if caching enabled and caching for $cache_group is allowed.
    * \param $cache_group CACHE_GRP_...
    */
   function get_cache( $cache_group )
   {
      global $DGS_CACHE;
      if( !@$DGS_CACHE )
         $DGS_CACHE = new DgsCache();

      if( $DGS_CACHE->allow_group_caching($cache_group) )
         return $DGS_CACHE->cache_impl;
      else
         return null;
   }//get_cache


   /*!
    * \brief Fetches cache-entry for cache-group with cache-key $id.
    * \param $cache_group CACHE_GRP_...
    */
   function fetch( $dbgmsg, $cache_group, $id )
   {
      $cache = DgsCache::get_cache( $cache_group );
      if( !$cache )
         return null;

      $result = $cache->cache_fetch( $id );
      if( DBG_CACHE ) error_log("DgsCache.fetch($cache_group,$id).$dbgmsg = [" . DgsCache::debug_result($result) . "]");
      return $result;
   }

   /*!
    * \brief Stores cache-entry for cache-group with cache-key $id and given $data and $ttl (TimeToLive in secs).
    * \param $cache_group CACHE_GRP_...
    * \param $group_id optional group-name to collect specific cache-keys for collective delete with DgsCache::delete_group()
    */
   function store( $dbgmsg, $cache_group, $id, $data, $ttl, $group_id='' )
   {
      $cache = DgsCache::get_cache( $cache_group );
      if( !$cache )
         return false;

      $result = $cache->cache_store( $id, $data, $ttl );
      if( $group_id )
         $cache->cache_store_group( "GROUP_$group_id", $id, $ttl );

      if( DBG_CACHE ) error_log("DgsCache.store($cache_group,$id,$ttl,[$group_id]).$dbgmsg = [" . ($result ? 1 : 0) . "]");
      return $result;
   }

   /*!
    * \brief Deletes cache-entry for cache-group with cache-key $id.
    * \param $cache_group CACHE_GRP_...
    */
   function delete( $dbgmsg, $cache_group, $id )
   {
      $cache = DgsCache::get_cache( $cache_group );
      if( !$cache )
         return true;

      $result = $cache->cache_delete( $id );
      if( DBG_CACHE ) error_log("DgsCache.delete($cache_group,$id).$dbgmsg = [" . ($result ? 1 : 0) . "]");
      return $result;
   }

   /*!
    * \brief Deletes multiple cache-entries collected under group $group_id for cache-group.
    * \param $cache_group CACHE_GRP_...
    * \param $group_id group-name under which specific cache-keys where collected, see $group_id for DgsCache::store()
    */
   function delete_group( $dbgmsg, $cache_group, $group_id )
   {
      $cache = DgsCache::get_cache( $cache_group );
      if( !$cache )
         return null;

      $arr_group = $cache->cache_delete_group( "GROUP_$group_id" );
      if( DBG_CACHE ) error_log("DgsCache.delete_group($cache_group,$group_id).$dbgmsg: group [" . (is_null($arr_group) ? '-' : implode(' ', $arr_group)) . "]");
      return $arr_group;
   }

   // \internal
   function debug_result( $value )
   {
      if( is_null($value) )
         return 'NULL';
      else
         return gettype($value) . ':' . ( is_object($value) ? get_class($value) : $value );
   }

} // end of 'DgsCache'

?>

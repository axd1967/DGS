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

 /*!
  * \file dgs_cache.php
  *
  * \brief Container and function to handle caching using different cache methods on DGS.
  */

if( !defined('DGS_CACHE') )
   define('DGS_CACHE', ''); // ''=no-cache, cache-class-name (e.g. ApcCache)
if( !defined('DBG_CACHE') )
   define('DBG_CACHE', 0);


/*! \brief basic cache interface with basic cache-operations. */
abstract class AbstractCache
{
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
   var $cache_impl;

   private function DgsCache()
   {
      // NOTE (JUG): I wanted to call static cache-specific-functions like: $class=DGS_CACHE; $class::cache_func(..)
      //    but that construction needs PHP >= 5.3 (and live-server is still PHP 5.1).
      //    Therefore we need an instance of the cache-implementation using object-methods.
      if( DGS_CACHE === 'ApcCache' )
         $this->cache_impl = new ApcCache();
      else
         $this->cache_impl = null;
   }

   // ------------ static functions ----------------------------

   function is_shared_enabled()
   {
      return ( DGS_CACHE == 'ApcCache' );
   }

   function get_cache()
   {
      global $DGS_CACHE;
      if( !@$DGS_CACHE )
         $DGS_CACHE = new DgsCache();

      return $DGS_CACHE->cache_impl;
   }


   function fetch( $dbgmsg, $id )
   {
      $cache = DgsCache::get_cache();
      if( !$cache )
         return null;

      $result = $cache->cache_fetch( $id );
      if( DBG_CACHE ) error_log("DgsCache.fetch($id).$dbgmsg = [" . (is_null($result) ? 'NULL' : $result) . "]");
      return $result;
   }

   function store( $dbgmsg, $id, $data, $ttl, $group_id='' )
   {
      $cache = DgsCache::get_cache();
      if( !$cache )
         return false;

      $result = $cache->cache_store( $id, $data, $ttl );
      if( $group_id )
         $cache->cache_store_group( $group_id, $id, $ttl );

      if( DBG_CACHE ) error_log("DgsCache.store([$group_id]$id).$dbgmsg = [" . ($result ? 1 : 0) . "]");
      return $result;
   }

   function delete( $dbgmsg, $id )
   {
      $cache = DgsCache::get_cache();
      if( !$cache )
         return true;

      $result = $cache->cache_delete( $id );
      if( DBG_CACHE ) error_log("DgsCache.delete($id).$dbgmsg = [" . ($result ? 1 : 0) . "]");
      return $result;
   }

   function delete_group( $dbgmsg, $group_id )
   {
      $cache = DgsCache::get_cache();
      if( !$cache )
         return null;

      $arr_group = $cache->cache_delete_group( $group_id );
      if( DBG_CACHE ) error_log("DgsCache.delete_group($group_id).$dbgmsg: group [" . (is_null($arr_group) ? '-' : implode(' ', $arr_group)) . "]");
      return $arr_group;
   }

} // end of 'DgsCache'

?>

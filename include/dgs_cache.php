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

      // NOTE: "$class=DGS_CACHE; $class::cache_func(..)" needs PHP >= 5.3 (but live-server is still PHP 5.1)
      if( DGS_CACHE === CACHE_TYPE_APC )
         $this->cache_impl = new ApcCache();
      else
         $this->cache_impl = null;
   }

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
      if( is_null($cache) )
         return false;
      else
         return ( DGS_CACHE == 'ApcCache' );
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

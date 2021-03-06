<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once 'include/utilities.php';

 /* Author: Jens-Uwe Gaspar */

/*!
 * \file classlib_userquota.php
 *
 * \brief Classes and functions to manage user-specific quotas.
 */



define('FEATURE_POINTS_MAX_VALUE', 50);
define('FEATURE_POINTS_DAYS_LASTMOVED', (6*7) ); // increase feature-points only if user last-moved within X weeks
define('FEATURE_POINTS_INC_DAYS', 20); // increase feature-points every X days
define('FEATURE_POINTS_INC_VALUE', 1); // increase by amount of X points

 /*!
  * \class UserQuota
  *
  * \brief Class to manage UserQuota-table
  *
  * Examples:
  *    $uq->insert_default( $user_id );
  *
  *    $uq = UserQuota::load_user_quota( $user_id );
  *    $uq->modify_feature_points( -1 );
  *    $uq->update();
  */
class UserQuota
{
   private $user_id;
   private $feature_points;
   private $feature_points_updated;

   /*! \brief Constructs UserQuota-object with specified arguments. */
   public function __construct( $user_id, $feature_points=25, $feature_points_updated=0 )
   {
      self::_check_user_id( $user_id, 'UserQuota');

      $this->user_id = (int)$user_id;
      $this->set_feature_points( $feature_points );
      $this->feature_points_updated = $feature_points_updated;
   }

   public function get_feature_points()
   {
      return $this->feature_points;
   }

   /*! \brief Sets feature points within allowed limits -infinity..FEATURE_POINTS_MAX_VALUE. */
   public function set_feature_points( $points )
   {
      if ( !is_numeric($points) )
         $points = 25; // start-value
      elseif ( $points > FEATURE_POINTS_MAX_VALUE )
         $points = FEATURE_POINTS_MAX_VALUE;
      $this->feature_points = (int)$points;
   }

   /*!
    * \brief Increases or decreases amount of feature-points by given count within defined limits.
    */
   public function modify_feature_points( $count )
   {
      $this->set_feature_points( $this->feature_points + $count );
   }


   /*! \brief Updates all current UserQuota-data into database. */
   public function update_feature_points()
   {
      self::_check_user_id( $this->user_id, 'UserQuota:update_feature_points');
      if ( $this->user_id <= GUESTS_ID_MAX )
         error('not_allowed_for_guest', "UserQuota:update_feature_points({$this->user_id})");

      $update_query = 'UPDATE UserQuota SET'
         . '  FeaturePoints=' . $this->feature_points
         . " WHERE uid='{$this->user_id}' LIMIT 1";
         ;
      db_query( "UserQuota:update.update_feature_points({$this->user_id})", $update_query );
   }//update_feature_points


   // ------------ static functions ----------------------------

   /*! \internal (static) check for valid user-id */
   private static function _check_user_id( $user_id, $loc )
   {
      if ( !is_numeric($user_id) || $user_id <= 0 )
         error('invalid_user', "$loc:_check_user_id($user_id)");
   }

   /*! \brief Returns UserQuota-object created from specified (db-)row. */
   public static function new_from_row( $row )
   {
      $uq = new UserQuota(
            @$row['uid'],
            @$row['FeaturePoints'],
            @$row['FeaturePointsUpdated']
         );
      return $uq;
   }

   /*! \brief (static) Loads UserQuota-data for given user and returns UserQuota-object or null if not found. */
   public static function load_user_quota( $user_id )
   {
      self::_check_user_id( $user_id, 'UserQuota:load_user_quota');

      $row = mysql_single_fetch("UserQuota:load_user_quota.find($user_id)",
            "SELECT * FROM UserQuota WHERE uid='$user_id' LIMIT 1");
      if ( !$row )
         return null;
      return self::new_from_row( $row );
   }//load_user_quota

   /*! \brief (static) Inserts default UserQuota. */
   public static function insert_default( $user_id )
   {
      global $NOW;
      self::_check_user_id( $user_id, 'UserQuota:insert_default');
      db_query( "UserQuota:insert_default.insert({$user_id})",
         "INSERT INTO UserQuota SET uid='{$user_id}'"
         . ', FeaturePointsUpdated=FROM_UNIXTIME(' . $NOW . ')' );
   }

   /*! \brief Increases feature-points for all users, that match the update-criteria. */
   public static function increase_update_feature_points()
   {
      global $NOW;
      $lastmoved_date  = $NOW - FEATURE_POINTS_DAYS_LASTMOVED * 86400;
      $update_due_date = $NOW - FEATURE_POINTS_INC_DAYS * 86400;

      $update_query = 'UPDATE UserQuota AS UQ INNER JOIN Players AS P ON P.ID=UQ.uid SET'
         . ' UQ.FeaturePoints=IF(UQ.FeaturePoints <= '
               . (FEATURE_POINTS_MAX_VALUE - FEATURE_POINTS_INC_VALUE)
               . ',UQ.FeaturePoints+'.FEATURE_POINTS_INC_VALUE.','.FEATURE_POINTS_MAX_VALUE.')'
         . ", UQ.FeaturePointsUpdated=FROM_UNIXTIME($NOW)"
         . " WHERE UQ.FeaturePointsUpdated < FROM_UNIXTIME($update_due_date)"
         .   " AND P.LastMove >= FROM_UNIXTIME($lastmoved_date)"
         ;
      db_query( "UserQuota:increase_update_feature_points.update($NOW)", $update_query );
   }//increase_update_feature_points

} // end of 'UserQuota'

?>

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

require_once 'include/std_functions.php';

 /* Author: Jens-Uwe Gaspar */

/*!
 * \file classlib_user.php
 *
 * \brief Functions to represent a DGS user: tables Players
 */


 /*!
  * \class User
  *
  * \brief Convenience Class to manage some parts of Players-table.
  *        Used as container to hold data to be able to create user-reference.
  */
class User
{
   public $ID;
   public $Name;
   public $Handle;
   public $Type;
   public $Lastaccess;
   public $Country;
   public $Rating; // Players.Rating2
   public $RatingStatus;
   public $GamesRated;
   public $GamesFinished;
   public $AdminOptions;
   public $AdminLevel;

   // other DB-fields

   public $urow = null;

   /*! \brief Constructs a User with specified args. */
   public function __construct( $id=0, $name='', $handle='', $type=0, $lastaccess=0, $country='', $rating=NULL,
                  $rating_status=RATING_NONE, $games_rated=0, $games_finished=0, $admin_opts=0,
                  $admin_level=0 )
   {
      $this->ID = (int)$id;
      $this->Name = (string)$name;
      $this->Handle = (string)$handle;
      $this->Type = (int)$type;
      $this->Lastaccess = (int)$lastaccess;
      $this->Country = (string)$country;
      $this->setRating( $rating );
      $this->RatingStatus = $rating_status;
      $this->GamesRated = (int)$games_rated;
      $this->GamesFinished = (int)$games_finished;
      $this->AdminOptions = (int)$admin_opts;
      $this->AdminLevel = (int)$admin_level;
   }//__construct

   public function setRating( $rating )
   {
      if ( is_null($rating) || !is_numeric($rating) || abs($rating) >= OUT_OF_RATING )
         $this->Rating = NO_RATING;
      else
         $this->Rating = limit( (double)$rating, MIN_RATING, OUT_OF_RATING-1, NO_RATING );
   }

   /*! \brief Returns true, if user set (id != 0). */
   public function is_set()
   {
      return ( is_numeric($this->ID) && $this->ID > 0 );
   }

   /*! \brief Returns true, if user has set and valid rating. */
   public function hasRating( $check_rating_status=true )
   {
      if ( $check_rating_status )
      {
         if ( $this->RatingStatus == RATING_INIT || $this->RatingStatus == RATING_RATED ) // user has rating
            return ( abs($this->Rating) < OUT_OF_RATING ); // valid rating
         else // user not rated ( RatingStatus == RATING_NONE )
            return false;
      }
      else
         return ( abs($this->Rating) < OUT_OF_RATING ); // valid rating
   }//hasRating

   /*!
    * \brief Returns true, if user-rating falls inbetween given rating range (+/- 50%).
    * \param $fix true = check users rating in range ($min <= rating < $max);
    *        false = check in range ($min-50 <= rating < $max+50)
    */
   public function matchRating( $min, $max, $fix=false )
   {
      if ( !$fix )
      {
         $min = limit( $min - 50, MIN_RATING, OUT_OF_RATING-1, $min );
         $max = limit( $max + 50, MIN_RATING, OUT_OF_RATING-1, $max );
      }
      return ( $this->Rating >= $min ) && ( $this->Rating < $max );
   }//matchRating

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   public function to_string()
   {
      return "User(ID={$this->ID}):"
         . "  Name=[{$this->Name}]"
         . ", Handle=[{$this->Handle}]"
         . sprintf( ", Type=[0x%x]", $this->Type )
         . ", Lastaccess=[{$this->Lastaccess}]"
         . ", Country=[{$this->Country}]"
         . ", Rating=[{$this->Rating}]"
         . ", RatingStatus=[{$this->RatingStatus}]"
         . ", GamesRated=[{$this->GamesRated}]"
         . ", GamesFinished=[{$this->GamesFinished}]"
         . sprintf( ", AdminOptions=[0x%x]", $this->AdminOptions )
         . sprintf( ", AdminLevel=[0x%x]", $this->AdminLevel )
         . sprintf( ", urow={%s}", print_r($this->urow, true) )
         ;
   }//to_string

   /*! \brief Returns user_reference for user in this object. */
   public function user_reference()
   {
      $name = ( (string)$this->Name != '' ) ? $this->Name : UNKNOWN_VALUE;
      $handle = ( (string)$this->Handle != '' ) ? $this->Handle : UNKNOWN_VALUE;
      return user_reference( REF_LINK, 1, '', $this->ID, $name, $handle );
   }//user_reference


   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of User-object. */
   public static function build_query_sql()
   {
      // Players: ID,Name,Handle,Type,Lastaccess,LastQuickAccess,LastMove,Registerdate,Country,
      //          Rating2,RatingStatus,RatedGames,Finished,AdminOptions,Adminlevel
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.*',
         'UNIX_TIMESTAMP(P.Lastaccess) AS X_Lastaccess',
         'UNIX_TIMESTAMP(P.LastQuickAccess) AS X_LastQuickAccess',
         'UNIX_TIMESTAMP(P.LastMove) AS X_LastMove',
         'UNIX_TIMESTAMP(P.Registerdate) AS X_Registerdate' );
      $qsql->add_part( SQLP_FROM, 'Players AS P' );
      return $qsql;
   }//build_query_sql

   /*! \brief Returns User-object created from specified (db-)row and given table-prefix. */
   public static function new_from_row( $row, $prefix='', $urow_strip_prefix=false )
   {
      $user = new User(
            // expected from Players-table
            @$row[$prefix.'ID'],
            @$row[$prefix.'Name'],
            @$row[$prefix.'Handle'],
            @$row[$prefix.'Type'],
            @$row[$prefix.'X_Lastaccess'],
            @$row[$prefix.'Country'],
            @$row[$prefix.'Rating2'],
            @$row[$prefix.'RatingStatus'],
            @$row[$prefix.'RatedGames'],
            @$row[$prefix.'Finished'],
            @$row[$prefix.'AdminOptions'],
            @$row[$prefix.'Adminlevel']
         );

      $user->urow = $row;
      if ( $urow_strip_prefix && (string)$prefix != '' )
      {
         $prefixlen = strlen($prefix);
         foreach ( $user->urow as $key => $val )
         {
            if ( strpos($key, $prefix) == 0 )
               $user->urow[substr($key, $prefixlen)] = $val;
         }
      }

      return $user;
   }//new_from_row

   /*! \brief Constructs a User for forum-users. */
   public static function newForumUser( $id=0, $name='', $handle='', $admin_level=0, $rating=null )
   {
      $user = new User( $id, $name, $handle );
      $user->AdminLevel = (int)$admin_level;
      $user->setRating( $rating );
      return $user;
   }

   /*! \brief Loads and returns User-object for given additional query; NULL if nothing found. */
   public static function load_user_query( $query, $dbgmsg=NULL )
   {
      $result = NULL;
      if ( $query instanceof QuerySQL )
      {
         $qsql = self::build_query_sql();
         $qsql->merge( $query );
         $qsql->add_part( SQLP_LIMIT, '1' );

         if ( is_null($dbgmsg) )
            $dbgmsg = "User:load_user_query()";
         $row = mysql_single_fetch( $dbgmsg, $qsql->get_select() );
         if ( $row )
            $result = self::new_from_row( $row );
      }
      return $result;
   }//load_user_query

   /*! \brief Loads and returns User-object for given user-ID; NULL if nothing found. */
   public static function load_user( $uid )
   {
      $result = NULL;
      if ( is_numeric($uid) && $uid > 0 )
      {
         $query_part = new QuerySQL( SQLP_WHERE, "P.ID=$uid" );
         $result = self::load_user_query( $query_part, "User:load_user($uid)" );
      }
      return $result;
   }//load_user

   /*! \brief Loads and returns User-object for given user-Handle; NULL if nothing found. */
   public static function load_user_by_handle( $handle )
   {
      $result = NULL;
      if ( (string)$handle != '' )
      {
         $query_part = new QuerySQL( SQLP_WHERE, sprintf( "P.Handle='%s'", mysql_addslashes($handle) ));
         $result = self::load_user_query( $query_part, "User:load_user_by_handle($handle)" );
      }
      return $result;
   }//load_user_by_handle

   /*!
    * \brief Loads cached version and returns User-object for given user-Handle; NULL if nothing found.
    * \see User::load_user_by_handle()
    */
   public static function load_cache_user_by_handle( $xdbgmsg, $handle )
   {
      if ( (string)trim($handle) == '' )
         error('invalid_args', "User:load_cache_user_by_handle.miss_handle($handle)");

      $player_ref = strtolower($handle);
      $dbgmsg = "User:load_cache_user_by_handle($player_ref).$xdbgmsg";
      $key = "user_hdl.$player_ref";

      $urow = DgsCache::fetch( $dbgmsg, CACHE_GRP_USER_HANDLE, $key );
      if ( is_null($urow) )
      {
         $user = self::load_user_by_handle($handle);
         if ( !is_null($user) )
            DgsCache::store( $dbgmsg, CACHE_GRP_USER_HANDLE, $key, $user->urow, SECS_PER_HOUR );
      }
      else
         $user = self::new_from_row($urow);

      return $user;
   }//load_cache_user_by_handle

   public static function delete_cache_user_handle( $dbgmsg, $uhandle, $uhandle2=null )
   {
      DgsCache::delete( $dbgmsg, CACHE_GRP_USER_HANDLE, 'user_hdl.' . strtolower($uhandle) );
      if ( !is_null($uhandle2) )
         DgsCache::delete( $dbgmsg, CACHE_GRP_USER_HANDLE, 'user_hdl.' . strtolower($uhandle2) );
   }

   /*! \brief Returns non-null array( uid => { ID/Handle/Name/Rating2/Country => val }, ... ) for given users. */
   public static function load_quick_userinfo( $arr_uid )
   {
      $out = array();
      $size = count($arr_uid);
      if ( $size )
      {
         $users = implode(',', $arr_uid);
         $result = db_query( "User.load_quick_userinfo($users)",
               "SELECT ID, Handle, Name, Rating2, Country FROM Players WHERE ID IN ($users) LIMIT $size" );
         while ( $row = mysql_fetch_assoc($result) )
            $out[$row['ID']] = $row;
         mysql_free_result($result);
      }
      return $out;
   }//load_quick_userinfo

   /*! \brief Calculates hero-ratio of user. */
   public static function calculate_hero_ratio( $games_weaker, $games_finished, $rating, $rating_status )
   {
      return ( $rating_status == RATING_RATED && $rating >= MIN_RATING && $games_finished >= MIN_FIN_GAMES_HERO_AWARD )
         ? $games_weaker / $games_finished
         : 0;
   }

   /*!
    * \brief Returns number of games with weaker players required to reach next higher hero-level; 0 if end reached.
    * \param $hero_ratio 0..1
    * \param $cnt_finished number of finished games
    * \param $cnt_weaker number of finished games with weaker players
    * \return 0 = max hero-level reached; -1 = rating needed and min. 20 finished games
    *       else needed game-count with weaker players to reach next hero-level (>0 with rating, <0 miss rating)
    */
   public static function determine_games_next_hero_level( $hero_ratio, $cnt_finished, $cnt_weaker, $rating_status )
   {
      static $ARR_NEXT_HERO_LEVEL = array( 0 => HERO_BRONZE, 1 => HERO_SILVER, 2 => HERO_GOLDEN );

      if ( $rating_status != RATING_RATED || $cnt_finished < MIN_FIN_GAMES_HERO_AWARD )
         return -1;

      $hero_level = self::determine_hero_badge( $hero_ratio );
      if ( $hero_level == 3 )
         return 0;

      $calc_hero_ratio = $ARR_NEXT_HERO_LEVEL[$hero_level] / 100;
      return round( ($calc_hero_ratio * $cnt_finished - $cnt_weaker) / ( 1 - $calc_hero_ratio) + 0.5 );
   }//determine_games_next_hero_level

   /*! \brief Calculated hero-badge value-representation for hero-ratio: 0=none, 1=bronze, 2=silver, 3=gold. */
   public static function determine_hero_badge( $hero_ratio )
   {
      $chk_hero_ratio = 100 * $hero_ratio;
      if ( $chk_hero_ratio >= HERO_GOLDEN )
         return 3;
      elseif ( $chk_hero_ratio >= HERO_SILVER )
         return 2;
      elseif ( $chk_hero_ratio >= HERO_BRONZE )
         return 1;
      else
         return 0;
   }//determine_hero_badge

} // end of 'User'

?>

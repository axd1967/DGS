<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

require_once( 'include/std_functions.php' );

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
   var $ID;
   var $Name;
   var $Handle;
   var $Type;
   var $Lastaccess;
   var $Country;
   var $Rating;
   var $RatingStatus;
   var $GamesRated;
   var $GamesFinished;
   var $AdminOptions;
   var $AdminLevel;

   // other DB-fields

   var $urow;

   /*! \brief Constructs a User with specified args. */
   function User( $id=0, $name='', $handle='', $type=0, $lastaccess=0, $country='', $rating=NULL,
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
      // other DB-fields
      $this->urow = null;
   }

   function setRating( $rating )
   {
      if( is_null($rating) || !is_numeric($rating) || abs($rating) >= OUT_OF_RATING )
         $this->Rating = -OUT_OF_RATING;
      else
         $this->Rating = limit( (double)$rating, MIN_RATING, OUT_OF_RATING-1, -OUT_OF_RATING );
   }

   /*! \brief Returns true, if user set (id != 0). */
   function is_set()
   {
      return ( is_numeric($this->ID) && $this->ID > 0 );
   }

   /*! \brief Returns true, if user has set and valid rating. */
   function hasRating()
   {
      if( $this->RatingStatus == RATING_INIT || $this->RatingStatus == RATING_RATED ) // user has rating
         return ( abs($this->Rating) < OUT_OF_RATING ); // valid rating
      else // user not rated ( RatingStatus == RATING_NONE )
         return false;
   }

   /*! \brief Returns true, if user-rating falls inbetween given rating range (+/- 50%). */
   function matchRating( $min, $max, $fix=false )
   {
      if( !$fix )
      {
         $min = limit( $min - 50, MIN_RATING, OUT_OF_RATING-1, $min );
         $max = limit( $max + 50, MIN_RATING, OUT_OF_RATING-1, $max );
      }
      return ( $min <= $this->Rating ) && ( $this->Rating <= $max );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
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
   }

   /*! \brief Returns user_reference for user in this object. */
   function user_reference()
   {
      $name = ( (string)$this->Name != '' ) ? $this->Name : UNKNOWN_VALUE;
      $handle = ( (string)$this->Handle != '' ) ? $this->Handle : UNKNOWN_VALUE;
      return user_reference( REF_LINK, 1, '', $this->ID, $name, $handle );
   }

   // ------------ static functions ----------------------------

   /*! \brief Returns db-fields to be used for query of User-object. */
   function build_query_sql()
   {
      // Players: ID,Name,Handle,Type,Lastaccess,Country,Rating2,RatingStatus,RatedGames,Finished,AdminOptions,Adminlevel
      $qsql = new QuerySQL();
      $qsql->add_part( SQLP_FIELDS,
         'P.*',
         'UNIX_TIMESTAMP(P.Lastaccess) AS X_Lastaccess' );
      $qsql->add_part( SQLP_FROM, 'Players AS P' );
      return $qsql;
   }

   /*! \brief Returns User-object created from specified (db-)row and given table-prefix. */
   function new_from_row( $row, $prefix='', $urow_strip_prefix=false )
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
      if( $urow_strip_prefix && (string)$prefix != '' )
      {
         foreach( $user->urow as $key => $val )
         {
            if( strpos($key, $prefix) == 0 )
               $user->urow[substr($key, strlen($prefix))] = $val;
         }
      }

      return $user;
   }

   /*! \brief Constructs a User for forum-users. */
   function newForumUser( $id=0, $name='', $handle='', $admin_level=0 )
   {
      $user = new User( $id, $name, $handle );
      $user->AdminLevel = (int)$admin_level;
      return $user;
   }

   /*! \brief Loads and returns User-object for given additional query; NULL if nothing found. */
   function load_user_query( $query, $dbgmsg=NULL )
   {
      $result = NULL;
      if( is_a($query, 'QuerySQL') )
      {
         $qsql = User::build_query_sql();
         $qsql->merge( $query );
         $qsql->add_part( SQLP_LIMIT, '1' );

         if( is_nulL($dbgmsg) )
            $dbgmsg = "User.load_user_query()";
         $row = mysql_single_fetch( $dbgmsg, $qsql->get_select() );
         if( $row )
            $result = User::new_from_row( $row );
      }
      return $result;
   }

   /*! \brief Loads and returns User-object for given user-ID; NULL if nothing found. */
   function load_user( $uid )
   {
      $result = NULL;
      if( $uid > 0 )
      {
         $query_part = new QuerySQL( SQLP_WHERE, sprintf( "P.ID='%s'", $uid ) );
         $result = User::load_user_query( $query_part, "User.load_user($uid)" );
      }
      return $result;
   }

   /*! \brief Loads and returns User-object for given user-Handle; NULL if nothing found. */
   function load_user_by_handle( $handle )
   {
      $result = NULL;
      if( (string)$handle != '' )
      {
         $query_part = new QuerySQL( SQLP_WHERE, sprintf( "P.Handle='%s'", mysql_addslashes($handle) ));
         $result = User::load_user_query( $query_part, "User.load_user_by_handle($handle)" );
      }
      return $result;
   }

} // end of 'User'

?>

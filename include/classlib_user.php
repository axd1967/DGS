<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
  * \brief Convenience Class to manage some parts of Players-table
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

   /*! \brief Constructs a ForumUser with specified args. */
   function User( $id=0, $name='', $handle='', $type=0, $lastaccess=0, $country='', $rating=NULL )
   {
      $this->ID = (int)$id;
      $this->Name = (string)$name;
      $this->Handle = (string)$handle;
      $this->Type = (int)$type;
      $this->Lastaccess = (int)$lastaccess;
      $this->Country = (string)$country;
      $this->setRating( $rating );
   }

   function setRating( $rating )
   {
      if( is_null($rating) || $rating <= -OUT_OF_RATING || $rating >= OUT_OF_RATING )
         $this->Rating = -OUT_OF_RATING;
      else
         $this->Rating = limit( (double)$rating, MIN_RATING, OUT_OF_RATING-1, -OUT_OF_RATING );
   }

   /*! \brief Returns true, if user set (id != 0). */
   function is_set()
   {
      return ( is_numeric($this->ID) && $this->ID > 0 );
   }

   /*! \brief Returns string-representation of this object (for debugging purposes). */
   function to_string()
   {
      return "User(ID={$this->ID}):"
         . ", Name=[{$this->Name}]"
         . ", Handle=[{$this->Handle}]"
         . sprintf( " Type=[0x%x]", $this->Type )
         . ", Lastaccess=[{$this->Lastaccess}]"
         . ", Country=[{$this->Country}]"
         . ", Rating=[{$this->Rating}]"
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

   /*! \brief Returns User-object created from specified (db-)row and given table-prefix (including '_' within alias-prefix). */
   function new_from_row( $row, $prefix='' )
   {
      $user = new User(
            // expected from Players-table
            @$row[$prefix.'ID'],
            @$row[$prefix.'Name'],
            @$row[$prefix.'Handle'],
            @$row[$prefix.'Type'],
            @$row[$prefix.'X_Lastaccess'],
            @$row[$prefix.'Country'],
            @$row[$prefix.'Rating2']
         );
      return $user;
   }

} // end of 'User'

?>

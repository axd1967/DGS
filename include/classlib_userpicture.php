<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( 'include/classlib_upload.php' );
require_once( 'include/std_functions.php' );

 /* Author: Jens-Uwe Gaspar */

/*!
 * \file classlib_userpicture.php
 *
 * \brief Functions to handle user-pictures.
 */


/*!
 * \class UserPicture
 *
 * \brief Class to handle user-pictures.
 */

define('USERPIC_MAXSIZE_UPLOAD', 30*1024); // max. 30KB stored, keep factor of 1024
define('USERPIC_MAX_X', 800); // pixels
define('USERPIC_MAX_Y', 800); // pixels

class UserPicture
{

   /*!
    * \brief Returns array with info about user-picture.
    * \param user  array with user-id, pic-filename or user-id
    * \param ext   file-extension, if given add extension to user taken from $user;
    *              if null, read pic-filename directly from $user
    * \param check if true, check if picture exists; otherwise don't check (default: true)
    *
    * Examples:
    *    getPicturePath( $player_row )        // take 'UserPicture' from player_row
    *    getPicturePath( '2.jpg' )            // direct filename
    *    getPicturePath( $player_row, 'gif' ) // take user-id from array
    *    getPicturePath( $player_row['ID'], 'png', false )
    *    getPicturePath( 3, 'jpg' )
    *
    * \return array with [ full-path, dir-part, filename-part, image pic-URL suffix, bool pic-exists, cache-suffix ].
    *         NOTE: cache-suffix is to avoid browser-caching (stored in Players.UserPicture)
    * \note to display image use: getImageHtml( handle, false, pic-url-from-this-func, -1 )
    */
   function getPicturePath( $user, $ext=null, $check=true )
   {
      // determine pic-filename
      $old_cache_suffix = '';
      if( is_null($ext) )
      {
         if( is_array($user) )
         {
            $str = @$user['UserPicture'];
            if( strpos($str, '?') !== FALSE )
               list( $file_part, $old_cache_suffix) = explode( '?', $str );
            else
               $file_part = $str;
         }
         else
            $file_part = $user;
      }
      else
      {
         if( is_array($user) )
            $uid = @$user['ID'];
         elseif( is_numeric($user) )
            $uid = $user;
         else
            error('invalid_args', "UserPicture.getPicturePath.check.uid($uid,$ext)");
         $file_part = "$uid.$ext";
      }

      $path_part = $_SERVER['DOCUMENT_ROOT'] . '/' . USERPIC_FOLDER;
      $pic_path = $path_part . $file_part;
      $pic_exists = ( $check && !empty($file_part) && file_exists($pic_path) );

      // avoid browser-caching, with suffix file-part-len still <48
      global $NOW;
      $cache_suffix = '?t=' . date(DATE_FMT4, $NOW); // avoid caching with new URL
      if( $old_cache_suffix != '' )
         $pic_url = $file_part . '?' . $old_cache_suffix; // use saved URL
      else
         $pic_url = $file_part . $cache_suffix;

      $result = array( $pic_path, $path_part, $file_part, $pic_url, $pic_exists, $cache_suffix );
      return $result;
   }

   /*!
    * \brief Returns HTML-image for user-picture.
    * \param withLink true=add link to userinfo-picture, false=image not linked
    * \param pic_url null=picture-indicator-icon, else: url to user-picture
    * \param size if pic_url not null: size=<0 (no restriction),
    *             otherwise size restriction for x/y
    */
   function getImageHtml( $userhandle, $withLink=false, $pic_url=null, $size=17 )
   {
      global $base_path;
      if( is_null($pic_url) )
      {
         $pic_title = sprintf( T_('User [%s] has a picture'), $userhandle );
         $img = image( $base_path.'images/picture.gif', $pic_title, null );
      }
      else
      {
         $pic_src = $base_path . 'userpic/' . $pic_url;
         $pic_title = sprintf( T_('Picture of user [%s]'), $userhandle );
         $img = image( $pic_src, $pic_title, null, '', $size, $size );
      }
      return ($withLink) ? anchor( $base_path.'userinfo.php?user='.$userhandle.'#pic', $img) : $img;
   }

   /*! \brief Deletes picture of current user if existing (from file and database). */
   function delete_picture()
   {
      global $player_row;
      list( $path_picture ) = UserPicture::getPicturePath($player_row);
      if( $path_picture && file_exists($path_picture) )
      {
         if( @unlink($path_picture) )
            UserPicture::update_picture(''); // remove
      }
   }

   /*!
    * \brief Updates (replaces) picture of current user with given picture-filename
    *        and cache-suffix (to avoid browser-caching).
    */
   function update_picture( $pic_file, $cache_suffix='' )
   {
      global $player_row;
      $uid = @$player_row['ID'];
      if( $uid > 0 && is_numeric($uid) )
      {
         $pic_file_id = $pic_file . ( $pic_file != '' ? $cache_suffix : '' );
         $update_query = 'UPDATE Players SET'
            . "  UserPicture='" . mysql_addslashes($pic_file_id) . "'"
            . " WHERE ID='{$uid}' LIMIT 1";
         db_query( "UserPicture::update_picture($pic_file,$cache_suffix)", $update_query );
      }
   }

} // end of 'UserPicture'

?>

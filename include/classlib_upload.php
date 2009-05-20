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

$TranslateGroups[] = "Common";

 /* Author: Jens-Uwe Gaspar */

/*!
 * \file classlib_upload.php
 *
 * \brief Functions to upload files to server, especially images (ImageFileUpload) at first.
 */


/*!
 * \class ImageFileUpload
 *
 * \brief Class to manage the upload of image-files.
 *
 * Support types: JPG, GIF, PNG
 * Support operations: resize
 */

if( !defined('UPLOAD_ERR_EXTENSION') )
   define('UPLOAD_ERR_EXTENSION', 8); // since PHP 5.2.0

// Sources (but stripped to 'image/*'-mime-types):
// - http://de2.php.net/manual/en/function.image-type-to-mime-type.php
// - http://de2.php.net/manual/en/function.image-type-to-extension.php#69994
$ARR_GLOBAL_UPLOAD_EXTENSION = array(
   IMAGETYPE_GIF     => 'gif',  // 1 = GIF, image/gif
   IMAGETYPE_JPEG    => 'jpg',  // 2 = JPG, image/jpeg
   IMAGETYPE_PNG     => 'png',  // 3 = PNG, image/png
   //IMAGETYPE_SWF     => 'swf',  // 4 = SWF, application/x-shockwave-flash, (A. Duplicated MIME type)
   IMAGETYPE_PSD     => 'psd',  // 5 = PSD, image/psd
   IMAGETYPE_BMP     => 'bmp',  // 6 = BMP, image/bmp
   IMAGETYPE_TIFF_II => 'tiff', // 7 = TIFF, image/tiff, (intel byte order)
   IMAGETYPE_TIFF_MM => 'tiff', // 8 = TIFF, image/tiff, (motorola byte order)
   //IMAGETYPE_JPC     => 'jpc',  // 9 = JPC, application/octet-stream, (B. Duplicated MIME type)
   IMAGETYPE_JP2     => 'jp2',  // 10 = JP2, image/jp2
   //IMAGETYPE_JPX     => 'jpf',  // 11 = JPX, application/octet-stream, (B. Duplicated MIME type)
   //IMAGETYPE_JB2     => 'jb2',  // 12 = JB2, application/octet-stream, (B. Duplicated MIME type)
   //IMAGETYPE_SWC     => 'swc',  // 13 = SWC, application/x-shockwave-flash, (A. Duplicated MIME type)
   IMAGETYPE_IFF     => 'aiff', // 14 = IFF, image/iff
   IMAGETYPE_WBMP    => 'wbmp', // 15 = WBMP, image/vnd.wap.wbmp
   IMAGETYPE_XBM     => 'xbm',  // 16 = XBM, image/xbm
   //IMAGETYPE_ICO     => 'ico',  // 17 = ICO, image/vnd.microsoft.icon; since PHP 5.3.0
);

class ImageFileUpload
{
   /*! \brief original input for file-input from form. */
   var $arr_file;
   /*! \brief upper limit of file-size allowed to upload and stored. */
   var $max_upload_size;
   /*! \brief true, if image correctly uploaded. */
   var $is_uploaded;
   /*! \brief array of error-message; empty if no error. */
   var $errors;

   // browser-info about file-upload source (from $arr_file); size/type should be mistrusted
   var $file_src_tmpfile;
   var $file_src_clientfile;
   var $file_src_size;
   var $file_src_type;

   // image-related
   var $max_x;
   var $max_y;
   var $image_type; // IMAGETYPE_..
   var $image_mimetype;
   var $image_x;
   var $image_y;
   var $image;
   /*! \brief true, if image is too large and needs resizing to fit save-limits. */
   var $need_resize;

   /*!
    * \brief Constructs ImageFileUpload-object initializing vars with sizes and performing
    *        general checks on errorcode from file-upload, client-filename, and file-sizes
    *        and additional checks on image-properties image-type and dimensions (width/height).
    */
   function ImageFileUpload( $arr_file, $max_upload_size=0, $max_x=0, $max_y=0 )
   {
      $this->arr_file = $arr_file;
      $this->max_upload_size = $max_upload_size;
      $this->is_uploaded = false;
      $this->errors = array();

      $this->file_src_tmpfile = $arr_file['tmp_name'];
      $this->file_src_clientfile = $arr_file['name'];
      $this->file_src_size = $arr_file['size'];
      $this->file_src_type = $arr_file['type'];

      // image-related
      $this->max_x = round($max_x);
      $this->max_y = round($max_y);
      $this->image = null;
      $this->image_type = 0;
      $this->image_mimetype = '';
      $this->image_x = 0;
      $this->image_y = 0;
      $this->need_resize = false;

      // general check
      $this->checkFileUploadError( $arr_file['error'] );
      $this->checkImageFileUpload( array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) );
   }

   /*! \brief Returns true, if error encountered during image-file-upload; false if no error occured. */
   function has_error()
   {
      return ( count($this->errors) > 0 );
   }

   /*!
    * \brief Checks upload error-code from files-array, checks if file has been
    *        correctly uploaded and check filesize against max-upload-size.
    * \note Sets $this->is_uploaded if no error with upload encountered.
    *       Fills $this->errors with error-messages on encountered errors.
    */
   function checkFileUploadError( $errorcode )
   {
      $this->is_uploaded = false;
      switch($errorcode)
      {
         case UPLOAD_ERR_OK:
            if( $this->file_src_clientfile == '' )
               $this->errors[] = T_('No file specified to upload.');
            elseif( is_uploaded_file($this->file_src_tmpfile) )
               $this->is_uploaded = true;
            break;
         case UPLOAD_ERR_INI_SIZE:
            $this->errors[] = sprintf( T_('The uploaded file [%s] exceeds the max. '
                  . 'file size set by the server.'), $this->file_src_clientfile );
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $this->errors[] = sprintf( T_('The uploaded file [%s] exceeds the max. '
                  . 'file size of [%s bytes] specified for the input-form.'),
                  $this->file_src_clientfile, $this->max_upload_size );
            break;
        case UPLOAD_ERR_PARTIAL:
            $this->errors[] = sprintf( T_('The uploaded file [%s] was only partially uploaded.'),
                  $this->file_src_clientfile );
            break;
        case UPLOAD_ERR_NO_FILE:
            $this->errors[] = T_('No file was uploaded.');
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            error('upload_miss_temp_folder', "ImageFileUpload.$errorcode({$this->file_src_clientfile})");
            break;
        case UPLOAD_ERR_CANT_WRITE:
            error('upload_failed', "ImageFileUpload.no_write_to_disc.$errorcode({$this->file_src_clientfile})");
            break;
        case UPLOAD_ERR_EXTENSION:
            error('upload_failed', "ImageFileUpload.stopped_by_extension.$errorcode({$this->file_src_clientfile})");
            break;
        default:
            error('upload_failed', "ImageFileUpload.unknown_error.$errorcode({$this->file_src_clientfile})");
            break;
      }

      // check file-size (max. upload-size)
      $filesize = filesize($this->file_src_tmpfile);
      if( $errorcode != UPLOAD_ERR_FORM_SIZE && $this->max_upload_size > 0
            && $filesize > $this->max_upload_size )
      {
         $this->errors[] = sprintf( T_('The uploaded file [%s] exceeds the max.'
               . 'file size of [%s bytes] specified for the input-form.'),
               $this->file_src_clientfile, $this->max_upload_size );
         $this->need_resize = true;
      }

      if( $this->has_error() )
         $this->is_uploaded = false;
   }

   /*!
    * \brief Checks image-properties of uploaded file, that is check image-type
    *        against expected image-types and check image dimensions (width and height).
    * \param expected_imagetypes array with allowed IMAGETYPE_...
    * \note Sets this->need_resize/image_x/image_y/image_type
    * \return true if no error; false otherwise (this->errors filled accordingly)
    */
   function checkImageFileUpload( $expected_imagetypes )
   {
      if( !$this->is_uploaded )
         return false;

      // check for image and get image-infos
      $img_info = getimagesize($this->file_src_tmpfile);
      if( !is_array($img_info) )
      {
         $this->errors[] = sprintf( T_('The uploaded file [%s] has an unknown image format.'),
               $this->file_src_clientfile );
         return false;
      }
      list( $this->image_x, $this->image_y, $this->image_type ) = $img_info;
      $this->image_mimetype = @$img_info['mime'];

      // check image type
      if( !in_array($this->image_type, $expected_imagetypes) )
      {
         $this->errors[] = sprintf( T_('The image-type [%s, %s] of the uploaded file [%s] '
               . 'does not match one of the expected image-types [%s].'),
               $this->image_type, $this->image_mimetype, $this->file_src_clientfile,
               ImageFileUpload::getImageTypesText($expected_imagetypes) );
      }

      // check image dimensions
      if( $this->max_x > 0 && $this->image_x > $this->max_x )
      {
         $this->errors[] = sprintf( T_('The width [%s] of the uploaded image [%s] exceeds the limit of [%s pixels].'),
               $this->image_x, $this->file_src_clientfile, $this->max_x );
         $this->need_resize = true;
      }
      if( $this->max_y > 0 && $this->image_y > $this->max_y )
      {
         $this->errors[] = sprintf( T_('The height [%s] of the uploaded image [%s] exceeds the limit of [%s pixels].'),
               $this->image_y, $this->file_src_clientfile, $this->max_y );
         $this->need_resize = true;
      }

      return !$this->has_error();
   }

   /*! \brief Returns file-extension for current image-type. */
   function determineFileExtension()
   {
      global $ARR_GLOBAL_UPLOAD_EXTENSION;
      if( !isset($ARR_GLOBAL_UPLOAD_EXTENSION[$this->image_type]) )
         error('invalid_args', "ImageFileUpload.determineFileExtension.unknown_type({$this->image_type})");
      return $ARR_GLOBAL_UPLOAD_EXTENSION[$this->image_type];
   }

   /*!
    * \brief Finally saves uploaded-file to destination path if everything ok.
    * \param path_dest absolute server-pathname (with filename) to store image to
    * \return true on success; false on error (this->errors filled accordingly)
    */
   function uploadImageFile( $path_dest )
   {
      if( !$this->is_uploaded || $this->has_error() )
         return false;

      // no conversion needed, just store image
      if( move_uploaded_file($this->file_src_tmpfile, $path_dest) )
         return true;

      $this->errors[] = sprintf( T_('The uploaded file [%s] can not be stored.'),
            $this->file_src_clientfile );
      return false;
   }

   /*!
    * \brief Loads image from temp-file as GD-image, store data in this->image
    *        and dimensions in this->image_x/y.
    */
   function loadImage()
   {
      switch( $this->image_type )
      {
         case IMAGETYPE_GIF:
            $this->image = @imagecreatefromgif($this->file_src_tmpfile);
            break;
         case IMAGETYPE_JPEG:
            $this->image = @imagecreatefromjpeg($this->file_src_tmpfile);
            break;
         case IMAGETYPE_PNG:
            $this->image = @imagecreatefrompng($this->file_src_tmpfile);
            break;
         default:
            error('upload_failed', "ImageFileUpload.loadImage({$this->image_type})");
            break;
      }

      // get image-infos
      $this->image_x = imagesx($this->image);
      $this->image_y = imagesy($this->image);

      return !$this->has_error();
   }

   /*! \brief Cleans up GD-resource and temp-file. */
   function cleanup()
   {
      if( !is_null($this->image) )
      {
         @imagedestroy($this->image);
         $this->image = null;
      }
      if( file_exists($this->file_src_tmpfile) )
         @unlink($this->file_src_tmpfile);
   }

   // ------------- static functions ---------------------------

   /*!
    * \brief Returns array with information about image expecting at given path.
    * \return map with keys: x, y, size (bytes), size_kb (size in KB),
    *         type (IMAGETYPE_..), mimetype
    */
   function getImageInfo( $path_image )
   {
      if( !file_exists($path_image) )
         return null;

      $image_info = @getimagesize($path_image);
      list( $width, $height, $type, $imgstr ) = $image_info;
      $size = @filesize($path_image);
      return array(
            'x' => $width,
            'y' => $height,
            'size' => $size,
            'size_kb' => round(10 * $size / 1024.0) / 10,
            'type' => $type,
            'mimetype' => @$image_info['mime'],
         );
   }

   /*!
    * \brief Helper-method to return concattenated text of all image-types listed in arg.
    * \param imagetypes array with list of IMAGETYPE_..
    */
   function getImageTypesText( $imagetypes )
   {
      $arr = array();
      if( in_array(IMAGETYPE_GIF,  $imagetypes) ) $arr[] = T_('GIF#imagefmt');
      if( in_array(IMAGETYPE_JPEG, $imagetypes) ) $arr[] = T_('JPEG#imagefmt');
      if( in_array(IMAGETYPE_PNG,  $imagetypes) ) $arr[] = T_('PNG#imagefmt');
      return implode(', ', $arr);
   }

} // end of 'ImageFileUpload'
?>

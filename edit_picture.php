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

$TranslateGroups[] = "Users";

require_once( 'include/std_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'include/classlib_upload.php' );
require_once( 'include/classlib_userpicture.php' );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( USERPIC_FOLDER == '' )
      error('feature_disabled');

   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   if( (@$player_row['AdminOptions'] & ADMOPT_DENY_EDIT_BIO) )
      error('edit_bio_denied');

/* Actual REQUEST calls used:
     (no args)              : add/edit user picture
     pic_save&file_userpic= : replace user picture in file-system
     pic_delete             : remove user picture
*/

   // delete picture
   if( @$_REQUEST['pic_delete'] )
   {
      UserPicture::delete_picture();
      jump_to("edit_picture.php?sysmsg=". urlencode(T_('User picture removed!')) );
   }

   // upload, check and save picture
   $errors = null;
   $upload = null;
   if( @$_REQUEST['pic_save'] && isset($_FILES['file_userpic']) )
   {
      // update picture and db with values from edit-form
      $upload = new ImageFileUpload( $_FILES['file_userpic'],
            USERPIC_MAXSIZE_UPLOAD, USERPIC_MAXSIZE_SAVE,
            USERPIC_MAX_X, USERPIC_MAX_Y );
      if( $upload->is_uploaded && !$upload->has_error() )
      {
         $pic_ext = $upload->determineFileExtension();
         list( $path_dest, $_picdir, $pic_file, $_picurl, $_picexists, $cache_suffix )
            = UserPicture::getPicturePath( $my_id, $pic_ext, false);
         if( $upload->uploadImageFile($path_dest) )
         {
            @$upload->cleanup();
            UserPicture::update_picture($pic_file, $cache_suffix);
            jump_to("edit_picture.php?sysmsg=". urlencode(T_('User picture saved!')) );
         }
      }
      if( $upload->has_error() )
         $errors = $upload->errors;
      @$upload->cleanup();
   }


   // inits for form
   list( $pic_path, $tmp,$tmp, $pic_url, $pic_exists ) = UserPicture::getPicturePath($player_row);
   $curr_picture_str = NO_VALUE;
   $img_info_str = '';
   if( $pic_exists )
   {
      $curr_picture_str = image( $pic_url, sprintf( 'Picture of user [%s]', $player_row['Handle'] ));
      $img_info = ImageFileUpload::getImageInfo($pic_path);
      $img_info_str = sprintf( T_('[ Dimensions: %s x %s, Size: %s KB ]'),
            $img_info['x'], $img_info['y'], $img_info['size_kb'] ) . "<br><br>\n";
   }

   $page = "edit_picture.php";
   $title = T_('User picture edit');

   // ---------- EDIT-form ----------------------------------

   $pform = new Form( 'userpic', $page, FORM_POST );

   $pform->add_row( array(
      'DESCRIPTION', T_('Upload picture'),
      'FILE',        'file_userpic', 40, USERPIC_MAXSIZE_UPLOAD, 'image/*', true ));

   if( $errors )
   {
      $errstr = '';
      foreach( $errors as $err )
         $errstr .= make_html_safe($err, 'line') . "\n";
      $pform->add_empty_row();
      $pform->add_row( array(
            'DESCRIPTION', T_('Errors'),
            'TEXT',        sprintf( '<span class="TWarning">%s</span>', $errstr ), ));
   }

   $pform->add_empty_row();
   $pform->add_row( array(
      'DESCRIPTION', T_('Current picture'),
      'CELL', 1, '',
      'OWNHTML', $img_info_str . $curr_picture_str ));

   $pform->add_empty_row();
   $arr = array(
      'TAB', 'CELL', 1, '', // align submit-buttons
      'SUBMITBUTTON', 'pic_save', T_('Save picture'),
      'TEXT', SMALL_SPACING );
   if( $pic_exists )
      array_push( $arr,
         'SUBMITBUTTON', 'pic_delete', T_('Remove picture') );
   $pform->add_row( $arr );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";
   $pform->echo_string();

   end_page();
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2014  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Game";

require_once 'include/std_functions.php';
require_once 'include/form_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/table_columns.php';
require_once 'include/game_sgf_control.php';
require_once 'include/db/games.php';
require_once 'include/db/game_sgf.php';
require_once 'include/classlib_upload.php';

define('SGF_MAXSIZE_UPLOAD', 100*1024); // max. 100KB stored, keep factor of 1024


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'manage_sgf');

   $gid = (int)$_REQUEST['gid'];
   if ( $gid <= 0 )
      error('invalid_args', "manage_sgf.miss.gid");

   // download SGF
   if ( @$_REQUEST['sgf_download'] )
   {
      $uid_download = (int)@$_REQUEST['uid'];
      if ( $uid_download <= GUESTS_ID_MAX )
         error('invalid_user', "manage_sgf.download.check.uid($gid,$uid_download)");

      GameSgfControl::download_game_sgf( "manage_sgf", $gid, $uid_download );
      exit; // shouldn't come to here
   }

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'manage_sgf');

/* Actual REQUEST calls used:
     (no args)              : list attached SGFs, add/edit SGF
     sgf_save&file_userpic= : replace SGF
     sgf_delete             : remove SGF, ask for confirmation
     sgf_delete_confirm     : remove SGF
     sgf_download&uid=      : downloads SGF stored by user
     cancel                 : cancel delete-SGF
*/

   $game = Games::load_game( $gid );
   if ( is_null($game) )
      error('unknown_game', "manage_sgf.check.gid($gid)");
   if ( !isRunningGame($game->Status) && $game->Status != GAME_STATUS_FINISHED )
      error('invalid_game_status', "manage_sgf.check.status($gid,{$game->Status})");

   $page = "manage_sgf.php";
   $baseURL = "$page?gid=$gid".URI_AMP;


   if ( @$_REQUEST['cancel'] )
      jump_to($baseURL);

   // delete SGF
   if ( @$_REQUEST['sgf_delete_confirm'] )
   {
      GameSgfControl::delete_game_sgf( $gid );
      jump_to($baseURL."sysmsg=". urlencode(T_('SGF removed!')) );
   }

   // upload, check and save SGF
   $errors = array();
   $upload = null;
   if ( @$_REQUEST['sgf_save'] && isset($_FILES['file_sgf']) )
   {
      // update SGF in db with values from edit-form
      $upload = new FileUpload( $_FILES['file_sgf'], SGF_MAXSIZE_UPLOAD );
      if ( $upload->is_uploaded() && !$upload->has_error() )
      {
         $errors = GameSgfControl::save_game_sgf( $game, $my_id, $upload->get_file_src_tmpfile() );
         if ( count($errors) == 0 )
         {
            @$upload->cleanup();
            jump_to($baseURL."sysmsg=". urlencode(T_('SGF saved!')) );
         }
      }
      if ( $upload->has_error() )
         $errors = array_merge( $upload->get_errors(), $errors );
      @$upload->cleanup();
   }


   // inits for form
   $gstable = new Table( 'gamesgf', $page, null, '', TABLE_ROWS_NAVI|TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_HIDE );
   $gstable->use_show_rows( false );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $gstable->add_tablehead( 1, T_('sgf#header'), 'Image', 0, '');
   $gstable->add_tablehead( 2, T_('Author#header'), 'User', 0, '');
   $gstable->add_tablehead( 3, T_('Last changed#header'), 'Date', 0, 'Lastchanged-');

   $gstable->set_default_sort( 3 );
   $gstable->make_sort_images();

   $arr_game_sgf = GameSgf::load_game_sgfs( $gid );
   $my_sgf_exists = false;
   foreach ( $arr_game_sgf as $g_sgf )
   {
      $gstable->add_row( array(
            1 => anchor( $base_path.$baseURL."sgf_download=1".URI_AMP."uid=".$g_sgf->uid,
                     image( $base_path.'images/sgf.gif', T_('Download attached game-SGF'), null, 'class="InTextImage"' ) ),
            2 => user_reference( REF_LINK, 1, '', $g_sgf->uid ),
            3 => ($g_sgf->Lastchanged > 0 ? date(DATE_FMT2, $g_sgf->Lastchanged) : '' ),
         ));

      if ( $g_sgf->uid == $my_id )
         $my_sgf_exists = true;
   }


   $title = T_('Manage game-SGF attachments');

   // ---------- EDIT-form ----------------------------------

   $form = new Form( 'managesgf', $page, FORM_POST );
   $form->add_hidden('gid', $gid);

   $infos = array(
      anchor( "{$base_path}game.php?gid=$gid", "#$gid" ),
      echo_image_gameinfo($gid, /*sep*/false, $game->Size, ($game->Snapshot ? $game->Snapshot : null),
         $game->Last_X, $game->Last_Y ),
   );
   if ( $game->ShapeID > 0 )
      $infos[] = echo_image_shapeinfo( $game->ShapeID, $game->Size, $game->ShapeSnapshot, /*gob-ed*/false);
   if ( $game->tid > 0 )
      $infos[] = echo_image_tournament_info($game->tid);
   $form->add_row( array(
      'DESCRIPTION', T_('Game ID'),
      'TEXT', implode(' ', $infos), ));
   $form->add_row( array(
      'DESCRIPTION', T_('Game Type'),
      'TEXT', GameTexts::format_game_type($game->GameType, $game->GamePlayers)
            . ( ($game->GameType != GAMETYPE_GO ) ? MED_SPACING . echo_image_game_players($gid) : '' ), ));
   $form->add_row( array(
      'DESCRIPTION', T_('Last move#header'),
      'TEXT', date(DATE_FMT3, @$game->Lastchanged), ));

   if ( count($errors) )
   {
      $errstr = '';
      foreach ( $errors as $err )
         $errstr .= make_html_safe($err, 'line') . "<br>\n";
      $form->add_empty_row();
      $form->add_row( array(
            'DESCRIPTION', T_('Errors'),
            'TEXT',        sprintf( '<span class="TWarning">%s</span>', $errstr ), ));
   }

   $form->add_empty_row();
   if ( $my_sgf_exists && @$_REQUEST['sgf_delete'] ) // ask for DEL-confirm
   {
      $form->add_row( array(
         'CELL', 2, 'class="center darkred"',
         'OWNHTML', T_('Please confirm, that you want to remove your SGF!'), ));
      $form->add_row( array(
         'SUBMITBUTTON', 'sgf_delete_confirm', T_('Remove SGF'),
         'SUBMITBUTTON', 'cancel',  T_('Cancel') ));
   }
   else
   {
      $form->add_row( array(
         'DESCRIPTION', T_('Upload SGF'),
         'FILE',        'file_sgf', 40, SGF_MAXSIZE_UPLOAD, 'application/x-go-sgf', true ));

      $form->add_empty_row();
      $arr = array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'sgf_save', T_('Save SGF'),
         'TEXT', SMALL_SPACING );
      if ( $my_sgf_exists && !@$_REQUEST['sgf_delete'] )
         array_push( $arr,
            'SUBMITBUTTON', 'sgf_delete', T_('Remove my SGF') );
      $form->add_row( $arr );
   }


   $form->add_row( array( 'HEADER', T_('Attached SGFs') ));
   $form->add_row( array(
      'CELL', 2, '',
      'OWNHTML', $gstable->make_table(), ));
   if ( count($arr_game_sgf) == 0 )
   {
      $form->add_row( array(
         'CELL', 2, 'class="center"',
         'OWNHTML', sprintf( '--- %s ---', T_('No SGF attachments stored') ), ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $form->echo_string();


   $notes = array();
   $notes[] = array(
      'text' => span('darkred bold', T_('To create a game review it\'s recommended to download the SGF without comments.')) . "<br>\n" .
                span('darkred bold', T_('Otherwise it may happen that private comments or your game notes are published!')) . "<br>\n" .
                T_('For instructions please read the FAQ how best to download a SGF.#sgf')
      );
   $notes[] = null;
   $notes[] = T_('Each user can upload only one SGF.' );
   $notes[] = sprintf( T_('Limit on uploaded SGF-file: max. %s KB'), ROUND(10*SGF_MAXSIZE_UPLOAD/1024)/10 );
   echo_notes( 'managesgf', T_('Manage SGF notes'), $notes );


   $menu_arr = array();
   $menu_arr[T_('Download sgf WITHOUT comments')] = "sgf.php?gid=$gid".URI_AMP."owned_comments=N" ;
   $menu_arr[T_('Download sgf')] = "sgf.php?gid=$gid";
   $menu_arr[T_('Download sgf with all comments')] = "sgf.php?gid=$gid".URI_AMP."owned_comments=1" ;
   $menu_arr[T_('Refresh')] = $page."?gid=$gid";

   end_page(@$menu_arr);
}//main
?>

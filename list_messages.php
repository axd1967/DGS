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

$TranslateGroups[] = "Messages";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'include/message_functions.php';
require_once 'include/filter.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if ( !$logged_in )
      error('login_if_not_logged_in', 'list_messages');

   $my_id = $player_row['ID'];

   $find_answers = (int)@$_REQUEST['find_answers'] ;

   init_standard_folders();
   $my_folders = get_folders($my_id);

/*
   Actions:
      toggle_marks      : toggle marks
      move_marked       : change folder for marked messages
      trash_marked      : move marked messages into Trashcan-folder
      empty_trash       : destroy all messages in Trashcan-folder (aka 'empty trashcan')
      follow            : true to switch into target folder after move
      folder            : target-folder for changing folder

   *folder* args rules:

   - &folder= not set, and &current_folder= not set:
       assume enter FOLDER_ALL_RECEIVED without move
   - &folder= set, and &current_folder= not set:
       assume enter &folder= without move
   - &folder= not set, and &current_folder= set (may be 0=FOLDER_ALL_RECEIVED):
       reenter &current_folder= without move
   - &folder= set, and &current_folder= set:
       reenter &current_folder=
       &folder= is the move query field, effective on a *move_marked* click

   *folder* vars meaning:
   - $current_folder: folder shown
   - $folder: destination folder for move queries,
       effective on a *move_marked* click
       kept if != $current_folder
*/
   $folder = (int)@$_GET['folder']; // 0 if unset
   if ( $folder < FOLDER_ALL_RECEIVED )
      $folder = FOLDER_ALL_RECEIVED; // ineffective for move

   $current_folder = @$_GET['current_folder'];
   if ( !isset($current_folder) )
   {
      $current_folder = $folder;
      $folder = FOLDER_ALL_RECEIVED; // ineffective for move
   }
   else
      $current_folder = (int)$current_folder;
   if ( !isset($my_folders[$current_folder]) )
      $current_folder = FOLDER_ALL_RECEIVED;

   $follow = (bool)@$_GET['follow']; // follow into target folder?

   $toggle_marks = false;
   if ( @$_REQUEST['empty_trash'] )
   {
      $cnt = empty_trashcan_folder_messages( $my_id );
      if ( $cnt > 0 )
         set_sysmessage( sprintf( T_('Destroyed %s messages from trashcan folder!'), $cnt ) );
   }
   elseif ( isset($_GET['toggle_marks']) )
      $toggle_marks = true;
   elseif ( change_folders_for_marked_messages($my_id, $my_folders) > 0 )
   {
      if ( isset($my_folders[$folder]) && $current_folder != FOLDER_DELETED && $follow )
      {
         // follow the move if one
         $current_folder= $folder;

         // first page if a move. keep $mtable prefix
         // WARNING: it should be better to follow the message but
         // we don't know its page in the new folder+sorting.
         $_REQUEST['from_row'] = $_GET['from_row'] = 0;
      }
   }

   $page = '';
   $qsql = new QuerySQL(); // add extra-parts to SQL-statement
   if ( $find_answers > 0 )
   {
      $title = T_('Answers list');
      $page.= URI_AMP.'find_answers='.$find_answers ;
      $qsql->add_part( SQLP_WHERE, "M.ReplyTo=$find_answers" );
      $current_folder = FOLDER_NONE;
      $folderstring = 'all';
   }
   else
   {
      $title = T_('Message list');
      if ( $current_folder == FOLDER_ALL_RECEIVED )
         $folderstring = implode(',', build_folders_all_received($my_folders));
      else
         $folderstring = (string)$current_folder;
   }

   $page.= URI_AMP.'current_folder=' . $current_folder ;
   if ( $folder!=$current_folder )
      $page.= URI_AMP.'folder=' . $folder ;

   if ( $page )
      $page= '?'.substr( $page, strlen(URI_AMP));

   start_page($title, true, $logged_in, $player_row );

   $rx_term = get_request_arg('xterm');

   $mtable = new Table( 'message', 'list_messages.php'.$page, '', '',
      TABLE_NO_HIDE | TABLE_NO_SIZE | ( ENABLE_MESSAGE_NAVIGATION ? TABLE_ROWS_NAVI : 0 ) );
   //$mtable->add_or_del_column();
   if ( ENABLE_MESSAGE_NAVIGATION )
      $mtable->handle_show_rows( true );

   $marked_form = new Form('messageMove','list_messages.php#action', FORM_GET, true, 'FormTable');
   $marked_form->set_tabindex(1);
   $marked_form->attach_table( $mtable);

   $msglist_builder = new MessageListBuilder( $mtable, $current_folder, /*no_mark*/false, /*full_details*/false );
   $msglist_builder->message_list_head();
   $mtable->set_default_sort( 4); //on date
   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   $qsql->merge( $mtable->get_query() ); // include found-rows-stuff, may need adjustment for filters
   list( $arr_msg, $num_rows, $found_rows ) =
      MessageListBuilder::message_list_query( $my_id, $folderstring, $order, $limit, ENABLE_MESSAGE_NAVIGATION, $qsql );
   $show_rows = $mtable->compute_show_rows( $num_rows );
   $mtable->set_found_rows( $found_rows ); // might be too slow for so many msgs

   if ( $find_answers && $show_rows == 1 )
   {
      $mid = $arr_msg[0]['mid'];
      jump_to( "message.php?mode=ShowMessage".URI_AMP."mid=$mid".URI_AMP."xterm=".urlencode($rx_term) );
   }


   echo echo_folders($my_folders, $current_folder);

   echo "<h3 class=Header>$title</h3>\n";

   $can_move_messages = $msglist_builder->message_list_body( $arr_msg, $show_rows, $my_folders, $toggle_marks, $rx_term);

   $mtable->echo_table();
   //echo "<br>\n";

   if ( $can_move_messages )
   {
      /****
       * Actually, toggle marks does not destroy sort,
       * but sort, page move and add/del-column destroy marks.
       * (unless a double *toggle marks* that transfer marks in URL)
       * (but then, the URL limited length may not be enough)
       * See message_list_head/body() to re-insert the marks in the URL
       ****/

      if ( $find_answers > 0 )
         $marked_form->add_hidden( 'find_answers', $find_answers);
      else if ( $current_folder >= FOLDER_ALL_RECEIVED )
         $marked_form->add_hidden( 'current_folder', $current_folder);

      $elem_toggle_marks =
           $marked_form->print_insert_submit_buttonx( 'toggle_marks',
               T_('Toggle marks'), array( 'accesskey' => ACCKEY_ACT_PREVIEW ));

      if ( $current_folder == FOLDER_DELETED )
      {
         echo $marked_form->print_insert_submit_button( 'destroy_marked', T_('Destroy marked messages') ),
              MED_SPACING,
              $elem_toggle_marks,
              SMALL_SPACING, SMALL_SPACING,
              $marked_form->print_insert_submit_button( 'empty_trash', T_('Empty trashcan#msg') );
      }
      else
      {
         $fld = array( FOLDER_NONE => '' );
         foreach ( $my_folders as $key => $val )
         {
            if ( $key != $current_folder && $key != FOLDER_NEW &&
                !($current_folder == FOLDER_SENT && $key == FOLDER_REPLY ) )
               $fld[$key] = $val[0];
         }

         // control-elements: target-folder-selectbox + follow-check, move, toggle
         echo "\n<table class=FormClass><tr>\n",
              '<td>',
                T_('Target folder:'),
              "</td>\n<td>",
                 $marked_form->print_insert_select_box( 'folder', '1', $fld, $folder, '') .
                 $marked_form->print_insert_submit_buttonx( 'move_marked',
                     T_('Move marked messages'),
                     array('id'=>'action', 'accesskey' => ACCKEY_ACT_EXECUTE )) .
                 "<br>\n" .
                 $marked_form->print_insert_checkbox( 'follow', '1', T_('Follow moving'), $follow ),
              "</td>\n<td>",
                 $elem_toggle_marks,
                 ( ENABLE_MESSAGE_NAVIGATION
                     ? SMALL_SPACING . SMALL_SPACING . $mtable->make_show_rows( $marked_form, true )
                     : '' ),
                 SMALL_SPACING, SMALL_SPACING,
                 $marked_form->print_insert_submit_button('trash_marked', T_('Delete marked#msg')),
              "</td>\n",
              "</tr></table>\n";
      }
   }
   echo $marked_form->print_end();


   $menu_array = array();
   $menu_array[ T_('Search messages') ] = "search_messages.php";

   if ( $find_answers > 0 )
      $menu_array[ T_('Back to message') ] = "message.php?mode=ShowMessage".URI_AMP."mid=$find_answers".URI_AMP."xterm=".urlencode($rx_term);
   else
      $menu_array[ T_('Edit folders') ] = "edit_folders.php";

   end_page(@$menu_array);
}
?>

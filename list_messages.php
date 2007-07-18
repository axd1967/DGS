<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Messages";

require_once( "include/std_functions.php" );
require_once( "include/std_classes.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/filter.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $my_id = $player_row["ID"];

   $find_answers = @$_GET['find_answers'] ;

   $my_folders = get_folders($my_id);

/*
   *folder* args rules:
   - no &folder= neither &current_folder= set:
       assume enter FOLDER_ALL_RECEIVED without move
   - &folder= set but not &current_folder=:
       assume enter &folder= without move
   - no &folder= but &current_folder= set (may be 0=FOLDER_ALL_RECEIVED):
       reenter &current_folder= without move
   - both &folder= and &current_folder= set:
       reenter &current_folder=
       &folder= is the move query field, effective on a *move_marked* click

   *folder* vars meaning:
   - $current_folder: folder shown
   - $folder: destination folder for move queries,
       effective on a *move_marked* click
       kept if != $current_folder
*/
   $folder = @$_GET['folder'];
   if( !isset($folder) or $folder < FOLDER_ALL_RECEIVED )
      $folder = FOLDER_ALL_RECEIVED; //ineffective for move
   $current_folder = @$_GET['current_folder'];
   if( !isset($current_folder) )
   {
      $current_folder = $folder;
      $folder = FOLDER_ALL_RECEIVED; //ineffective for move
   }
   if( !isset($my_folders[$current_folder]) )
      $current_folder = FOLDER_ALL_RECEIVED;

   if( isset($_GET['toggle_marks']) )
      $toggle_marks= true;
   else
   {
      $toggle_marks= false;
      if( change_folders_for_marked_messages($my_id, $my_folders) > 0 )
         if( isset($my_folders[$folder]) && $current_folder != FOLDER_DELETED )
         {
            //follow the move if one
            $current_folder= $folder;
            // first page if a move. keep $mtable prefix
            $_GET['from_row'] = 0;
            // WARNING: it should be better to follow the message but
            // we don't know its page in the new folder+sorting.
         }
   }

   $page = '';
   $qsql = new QuerySQL(); // add extra-parts to SQL-statement
   if( $find_answers > 0 )
   {
      $title = T_('Answers list');
      $page.= URI_AMP.'find_answers=' . $find_answers ;
      $qsql->add_part( SQLP_WHERE, "M.ReplyTo=$find_answers" );
      $current_folder = FOLDER_NONE;
      $folderstring = 'all';
   }
   else
   {
      $title = T_('Message list');

      if( $current_folder == FOLDER_ALL_RECEIVED )
      {
         $fldrs = $my_folders;
         unset($fldrs[FOLDER_SENT]);
         unset($fldrs[FOLDER_DELETED]);
         $folderstring =implode(',', array_keys($fldrs));
         unset($fldrs);
      }
      else
      {
         $folderstring = (string)$current_folder;
      }
   }

   $page.= URI_AMP.'current_folder=' . $current_folder ;
   if( $folder!=$current_folder )
      $page.= URI_AMP.'folder=' . $folder ;

   if( $page )
      $page= '?'.substr( $page, strlen(URI_AMP));

   start_page($title, true, $logged_in, $player_row );

   $terms = get_request_arg('terms');

   $mtable = new Table( 'message', 'list_messages.php' . $page );
   $mtable->set_default_sort( 'date', 1);
   //$mtable->add_or_del_column();

   $marked_form = new Form('messageMove','list_messages.php#action', FORM_GET, true, 'formTable');
   $marked_form->set_tabindex(1);
   $marked_form->attach_table( $mtable);

   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   list( $result ) = message_list_query($my_id, $folderstring, $order, $limit, $qsql);

   $show_rows = mysql_num_rows($result);

   if( $find_answers && $show_rows == 1 )
   {
      $row = mysql_fetch_assoc( $result);
      $mid = $row["mid"];
      jump_to( "message.php?mode=ShowMessage".URI_AMP."mid=$mid".URI_AMP."terms=".urlencode($terms) );
   }

   $show_rows = $mtable->compute_show_rows( $show_rows);


   echo echo_folders($my_folders, $current_folder);

   echo "<h3 class=Header>$title</h3>\n";

   $can_move_messages =
     message_list_table( $mtable, $result, $show_rows
             , $current_folder, $my_folders
             , /*sort*/false, $current_folder == FOLDER_NEW, $toggle_marks
             , /*full-details*/false, /*only-TH*/null, $terms);
   //mysql_free_result($result); //already free

   $mtable->echo_table();
   //echo "<br>\n";

   if( $can_move_messages && $current_folder != FOLDER_NEW )
   {
      /****
       *      Actually, toggle marks does not destroy sort
       *      but sort, page move and add/del column destroy marks.
       * (unless a double *toggle marks* that transfert marks in URL)
       * (but then, the URL limited length may not be enought)
       * See message_list_table() to re-insert the marks in the URL
       ****/

      if( $find_answers > 0 )
         $marked_form->add_hidden( 'find_answers', $find_answers);
      else if( $current_folder >= FOLDER_ALL_RECEIVED )
         $marked_form->add_hidden( 'current_folder', $current_folder);

      echo $marked_form->print_insert_submit_buttonx( 'toggle_marks',
               T_('Marks toggle'), array('accesskey'=>'w'));

      if( $current_folder == FOLDER_DELETED )
      {
         echo $marked_form->print_insert_submit_button( 'destroy_marked',
                  T_('Destroy marked messages'));
      }
      else
      {
         $fld = array('' => '');
         foreach( $my_folders as $key => $val )
         {
            if( $key != $current_folder and $key != FOLDER_NEW and
                !($current_folder == FOLDER_SENT and $key == FOLDER_REPLY ) )
               $fld[$key] = $val[0];
         }

         echo $marked_form->print_insert_submit_buttonx( 'action',
                  T_('Move marked messages to folder'),
                  array('id'=>'action','accesskey'=>'x'));
         echo $marked_form->print_insert_select_box( 'folder',
                  '1', $fld, $folder, '');
      }
   }
   echo $marked_form->print_end();


   $menu_array = array();
   $menu_array[ T_('Search messages') ] = "search_messages.php";

   if( $find_answers > 0 )
      $menu_array[ T_('Back to message') ] = "message.php?mode=ShowMessage".URI_AMP."mid=$find_answers".URI_AMP."terms=".urlencode($terms);
   else
      $menu_array[ T_('Edit folders') ] = "edit_folders.php";

   end_page(@$menu_array);
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/timezones.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $my_id = $player_row["ID"];

   if(!@$_GET['sort1'])
   {
      $_GET['sort1'] = 'date';
      $_GET['desc1'] = 1;
   }

   $find_answers = @$_GET['find_answers'] ;

   $my_folders = get_folders($my_id);

   if( isset($_GET['toggle_marks']) )
   {
      $toggle_marks= true;
      $current_folder = @$_GET['current_folder'];
   }
   else
   {
      $toggle_marks= false;
      if( change_folders_for_marked_messages($my_id, $my_folders) != 0 )
         $current_folder = @$_GET['folder'];
      if( !isset($current_folder) or !isset($my_folders[$current_folder]) )
         $current_folder = @$_GET['current_folder'];
   }

   $page = '';
   if( $find_answers > 0 )
   {
      $title = T_('Answers list');
      $page.= '&find_answers=' . $find_answers ;
      $where = "AND Messages.ReplyTo=$find_answers";
      $current_folder = FOLDER_NONE;
      $folderstring = 'all';
   }
   else
   {
      $title = T_('Message list');
      $where = "";
      if( !isset($current_folder) or !isset($my_folders[$current_folder]) )
         $current_folder = FOLDER_ALL_RECEIVED;

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
         $page.= '&folder=' . $current_folder ;
         $folderstring = (string)$current_folder;
      }
   }


   start_page($title, true, $logged_in, $player_row );


   if( $page )
      $page{0} = '?';
   $mtable = new Table( 'list_messages.php' . $page, '', '', true );

   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   $result = message_list_query($my_id, $folderstring, $order, $limit, $where);

   $show_rows = $mtable->compute_show_rows(mysql_num_rows($result));

   $marked_form = new Form('','', FORM_GET);
   echo "<form name=\"marked\" action=\"list_messages.php\" method=\"GET\">\n";

   echo echo_folders($my_folders, $current_folder);

   echo "<center><h3><font color=$h3_color>" . $title . '</font></h3></center>';

   $can_move_messages =
     message_list_table( $mtable, $result, $show_rows
             , $current_folder, $my_folders
             , false, $current_folder == FOLDER_NEW, $toggle_marks) ;

   $mtable->echo_table();
   echo "<p>\n";

   if( $can_move_messages && $current_folder != FOLDER_NEW )
   {
/* Actually, toggle marks does not destroy sort
        but sort destroy marks
*/
      echo '<center>';
      echo $mtable->echo_hiddens();

      if( $find_answers > 0 )
        echo '<input type="hidden" name="find_answers" value="' . $find_answers . "\">\n";
      else if( $current_folder != FOLDER_ALL_RECEIVED )
        echo '<input type="hidden" name="current_folder" value="' . $current_folder . "\">\n";
      echo '<input type="submit" name="toggle_marks" value="' . T_('Marks toggle') . "\">\n";

      if( $current_folder == FOLDER_DELETED )
      {
         echo '<input type="submit" name="destroy_marked" value="' .
            T_('Destroy marked messages') . "\">\n";
      }
      else
      {
         $fld = array('' => '');
         foreach( $my_folders as $key => $val )
            if( $key != $current_folder and $key != FOLDER_NEW and
                !($current_folder == FOLDER_SENT and $key == FOLDER_REPLY ) )
               $fld[$key] = $val[0];

         echo '<input type="submit" name="move_marked" value="' .
            T_('Move marked messages to folder') . "\">\n" .
            $marked_form->print_insert_select_box( 'folder', '1', $fld, '', '') ;
      }
      echo "</center>\n";
   }
   echo "</form>\n";

   if( $find_answers > 0 )
      $menu_array = array( T_('Back to message') => "message.php?mode=ShowMessage&mid=$find_answers" );
   else
      $menu_array = array( T_('Edit folders') => "edit_folders.php" );

   end_page(@$menu_array);
}
?>

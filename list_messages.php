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
   if( $find_answers > 0 )
   {
      $title = T_('Answers list');
      $page.= URI_AMP.'find_answers=' . $find_answers ;
      $where = "AND Messages.ReplyTo=$find_answers";
      $current_folder = FOLDER_NONE;
      $folderstring = 'all';
   }
   else
   {
      $title = T_('Message list');
      $where = "";

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
   $mtable = new Table( 'message', 'list_messages.php' . $page );

   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   $result = message_list_query($my_id, $folderstring, $order, $limit, $where);

   $show_rows = mysql_num_rows($result);

   if( $find_answers && $show_rows == 1 )
   {
      $row = mysql_fetch_assoc( $result);
      $mid = $row["mid"];
      jump_to( "message.php?mode=ShowMessage".URI_AMP."mid=$mid");
   }

   $show_rows = $mtable->compute_show_rows( $show_rows);


   start_page($title, true, $logged_in, $player_row );


   $marked_form = new Form('','', FORM_GET);
   echo "<form name=\"marked\" action=\"list_messages.php\" method=\"GET\">\n";

   echo echo_folders($my_folders, $current_folder);

   echo "<center><h3><font color=$h3_color>" . $title . '</font></h3></center>';

   $can_move_messages =
     message_list_table( $mtable, $result, $show_rows
             , $current_folder, $my_folders
             , false, $current_folder == FOLDER_NEW, $toggle_marks) ;
   //mysql_free_result($result); //already free

   $mtable->echo_table();
   //echo "<br>\n";

   if( $can_move_messages && $current_folder != FOLDER_NEW )
   {
/* Actually, toggle marks does not destroy sort
        but sort destroy marks
   (unless a double *toggle marks* that transfert marks in URL)
   (<$>but then, the URL limited length may not be enought)
*/
      echo '<center>';
      echo $mtable->echo_hiddens();

      if( $find_answers > 0 )
        echo '<input type="hidden" name="find_answers" value="' . $find_answers . "\">\n";
      else if( $current_folder >= FOLDER_ALL_RECEIVED )
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
            $marked_form->print_insert_select_box( 'folder', '1', $fld, $folder, '') ;
      }
      echo "</center>\n";
   }
   echo "</form>\n";

   if( $find_answers > 0 )
      $menu_array = array( T_('Back to message') => "message.php?mode=ShowMessage".URI_AMP."mid=$find_answers" );
   else
      $menu_array = array( T_('Edit folders') => "edit_folders.php" );

   end_page(@$menu_array);
}
?>

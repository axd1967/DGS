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

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   $my_folders = get_folders($my_id);
   change_folders_for_marked_messages($my_id, $my_folders);


   $current_folder = $_GET['folder'];
   if( empty($my_folders[$current_folder]) )
      $current_folder = FOLDER_ALL_RECEIVED;

   $folderstring = $current_folder;
   if( $current_folder == FOLDER_ALL_RECEIVED )
   {
      $fldrs = $my_folders;
      unset($fldrs[FOLDER_SENT]);
      unset($fldrs[FOLDER_DELETED]);
      $folderstring =implode(',', array_keys($fldrs));
   }

   if(!$_GET['sort1'])
   {
      $_GET['sort1'] = 'date';
      $_GET['desc1'] = 1;
   }

   $order = $_GET['sort1'] . ( $_GET['desc1'] ? ' DESC' : '' );
   if( $sort2 )
      $order .= "," . $_GET['sort2'] . ( $_GET['desc2'] ? ' DESC' : '' );

   if( !is_numeric($_GET['from_row']) or $_GET['from_row'] < 0 )
      $_GET['from_row'] = 0;

   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS time, " .
      "Messages.Type, Messages.Subject, " .
      "me.mid, me.mid as date, me.Replied, me.Sender, " .
      "Players.Name AS other, Players.ID AS other_ID, me.Folder_nr AS folder " .
      "FROM MessageCorrespondents AS me " .
      "LEFT JOIN Messages ON Messages.ID=me.mid " .
      "LEFT JOIN MessageCorrespondents AS other " .
      "ON other.mid=me.mid AND other.Sender != me.Sender " .
      "LEFT JOIN Players ON Players.ID=other.uid " .
      "WHERE me.uid=$my_id AND me.Folder_nr IN ($folderstring) " .
      "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";

//    $rec_query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " .
//       "Players.Name AS other, To_Folder_nr AS folder " .
//       "FROM Messages, Players " .
//       "WHERE To_ID=$my_id AND To_Folder_nr IN ($folderstring) AND To_ID=Players.ID " .
//       "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";

//    $sent_query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " .
//       "Players.Name AS other, From_Folder_nr AS folder " .
//       "FROM Messages, Players " .
//       "WHERE From_ID=$my_id AND From_Folder_nr IN ($folderstring) AND To_ID=Players.ID " .
//       "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";


// for mysql 4.0

//    $l = $_GET['from_row']+$MaxRowsPerPage;
//    $query = "(SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " ,
//       "Players.Name AS other, From_Folder_nr AS folder " .
//       "FROM Messages, Players WHERE From_ID=$my_id AND From_Folder_nr IN ($folderstring) " .
//       "AND To_ID=Players.ID order by $order limit $l)" .
//       "UNION " .
//       "(SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
//       "Messages.ID AS mid, Messages.Subject, Messages.Replied, " .
//       "Players.Name AS other, To_Folder_nr AS folder " .
//       "FROM Messages, Players WHERE To_ID=$my_id AND To_Folder_nr IN ($folderstring) " .
//       "AND From_ID=Players.ID order by $order limit $l)" .
//       "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";

   $result = mysql_query( $query )
       or die ( error("mysql_query_failed") );

   $title = T_('Message list');

   start_page($title, true, $logged_in, $player_row );

   $marked_form = new Form('','', 0);
   echo "<form name=\"marked\" action=\"list_messages.php\" method=\"GET\">\n";

   echo echo_folders($my_folders, $current_folder);

   echo "<center><h3><font color=$h3_color>" . $title . '</font></h3></center>';

   $mtable = new Table( 'list_messages.php' . ( $current_folder == FOLDER_ALL_RECEIVED ? '' :
                                                '?folder=' . $current_folder ),
                        '', '', true );
   $show_rows = mysql_num_rows($result);
   if( $show_rows == $MaxRowsPerPage )
   {
      $show_rows = $RowsPerPage;
      $mtable->Last_Page = false;
   }

   $mtable->add_tablehead( 1, T_('Folder'), '', true, true );
   $mtable->add_tablehead( 2, ($current_folder == FOLDER_SENT ? T_('To') : T_('From') ),
                           'other', false, true );
   $mtable->add_tablehead( 3, T_('Subject'), 'Subject', false, true );
   $mtable->add_tablehead( 0, '&nbsp;', NULL, false, true );
   $mtable->add_tablehead( 4, T_('Date'), 'date', true, true );
   if( $current_folder != FOLDER_NEW )
      $mtable->add_tablehead( 5, T_('Mark'), NULL, true, true );

   $can_move_messages = false;
   $any_sent_message = false;
   $i=0;
   while( $row = mysql_fetch_array( $result ) )
   {
      $mid = $row["mid"];

      if( $row['Sender'] == 'Y' )
         $any_sent_message = true;

      $bgcolor = substr($mtable->Row_Colors[count($mtable->Tablerows) % 2], 2, 6);

      $row_strings[1] = echo_folder_box($my_folders, $row['folder'], $bgcolor);

      if( empty($row["other_ID"]) )
         $row["other"] = T_('Server message');
      if( empty($row["other"]) )
         $row["other"] = '-';

      $row_strings[2] = "<td>" . ( $row['Sender'] == 'Y' ? T_('To') . ': ' : '') .
         "<A href=\"message.php?mode=ShowMessage&mid=$mid\">" .
         make_html_safe($row["other"]) . "</A></td>";
      $row_strings[3] = "<td>" . make_html_safe($row["Subject"], true) . "&nbsp;</td>";
      $row_strings[0] = "<td>" .
         ($row['Replied'] == 'Y' ? '<font color="#009900">A</font>' : '&nbsp;' ) . '</td>';
      $row_strings[4] = "<td>" . date($date_fmt, $row["time"]) . "</td>";
      if( $row['folder'] == FOLDER_NEW or
          ( $row['folder'] == FOLDER_REPLY and $row['Type'] == 'INVITATION'
            and $row['Replied'] == 'N' ) )
         $row_strings[5] = '<td>&nbsp;</td>';
      else
      {
         $row_strings[5] = '<td align=center>'  .
            '<input type="checkbox" name="mark' . $mid . '" value="Y"></td>';
         $can_move_messages = true;
      }

      $mtable->add_row( $row_strings );

      if(++$i >= $show_rows)
         break;
   }

   $mtable->echo_table();

   if( $can_move_messages )
   {
      if( $current_folder == FOLDER_DELETED )
      {
         echo '<center><input type="submit" name="destory_marked" value="' .
            T_('Destroy marked messages') . "\"></center>\n";
      }
      else if( $current_folder != FOLDER_NEW )
      {
         $fld = array();
         foreach( $my_folders as $key => $val )
            if( $key != $current_folder and $key != FOLDER_NEW and
                !($current_folder == FOLDER_SENT and $key == FOLDER_REPLY ) )
               $fld[$key] = $val[0];

         echo '<center>' .
            '<input type="submit" name="move_marked" value="' .
            T_('Move marked messages to folder') . '">' .
            $marked_form->print_insert_select_box( 'folder', '1', $fld, '', '') .
            "</center></form>\n";
      }
   }

   $menu_array = array( T_('Edit folders') => "edit_folders.php" );

   end_page( $menu_array );
}
?>

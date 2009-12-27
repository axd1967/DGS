<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

require_once( 'include/std_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/table_columns.php' );
require_once( 'include/message_functions.php' );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row['ID'];
   $page = 'message_thread.php?';

/* Actual GET calls used (to identify the ways to handle them):
   message_thread.php?mid= : show message thread with given message-id in focus
*/

   init_standard_folders();
   $my_folders = get_folders($my_id);

   $thread = (int)get_request_arg('thread');
   $mid = (int)get_request_arg('mid');
   $with_text = get_request_arg('text', 0);
   if( $mid <= 0 || $thread <= 0 )
      error('unknown_message', "message_thread.find_msg($mid,$thread)" );

   // init search profile
   $mtable = new Table( 'message_thread', $page, '', 'msgThread',
      TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_ROW_NUM );
   $mtable->use_show_rows(false);

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $mtable->add_tablehead( 1, T_('Folder#header'), 'Folder' );
   $mtable->add_tablehead( 2, T_('Direction#header'), 'MsgDir' );
   $mtable->add_tablehead( 3, T_('Correspondent#header'), 'User' );
   $mtable->add_tablehead( 4, T_('Level#header'), 'Number' );
   $mtable->add_tablehead( 5, T_('Subject#header') );
   $mtable->add_tablehead( 6, T_('Date#header'), 'Date' );


   // see also the note about MessageCorrespondents.mid==0 in message_list_query()
   $qsql = new QuerySQL(
      SQLP_FIELDS,
         'M.ID', 'M.Type', 'M.Level', 'M.ReplyTo', 'M.Subject', 'M.Game_ID',
         'UNIX_TIMESTAMP(M.Time) AS X_Time',
         'me.Folder_nr', 'me.Sender',
         'POther.ID AS other_ID', 'POther.Handle AS other_handle', 'POther.Name AS other_name',
      SQLP_FROM,
         'Messages AS M',
         "INNER JOIN MessageCorrespondents AS me ON me.mid=M.ID AND me.uid=$my_id", // only my-msgs
         'LEFT JOIN MessageCorrespondents AS other ON other.mid=M.ID AND other.Sender!=me.Sender',
         'INNER JOIN Players AS POther ON POther.ID=other.uid',
      SQLP_WHERE,
         "M.Thread=$thread",
      SQLP_ORDER,
         'M.ID'
   );
   if( $with_text )
      $qsql->add_part( SQLP_FIELDS, 'M.Text' );

   $query = $qsql->get_select();
   $result = db_query( "message_thread.find_msg.load($thread,$mid)", $query );
   $msg_thread = array(); // mid => ThreadList
   $roots = array(); // root-threads (should only be one, but with corrupt db this may be >1 )
   while( $row = mysql_fetch_array( $result ) )
   {
      $reply = $row['ReplyTo'];
      if( $reply > 0 && isset($msg_thread[$reply]) )
      {
         $parent = $msg_thread[$reply];
         $threadlist = $parent->addChild( $row );
      }
      else
      {
         $threadlist = new ThreadList( $row );
         $roots[] = $threadlist;
      }
      $msg_thread[$row['ID']] = $threadlist;
   }
   mysql_free_result($result);
   $msg_count = count($msg_thread);
   unset($msg_thread);


   $title = T_('Message thread');
   start_page( $title, true, $logged_in, $player_row );
   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) . "<br>\n";
   echo "<h3 class=Header>$title</h3>\n";

   // thread-list can be very long, so add switch also on top
   $menu = array();
   $url_args = "thread=$thread".URI_AMP."mid=$mid#mid$mid";
   if( $with_text )
      $menu[T_('Hide message texts')] = $page.'text=0'.URI_AMP.$url_args;
   else
      $menu[T_('Show message texts')] = $page.'text=1'.URI_AMP.$url_args;
   make_menu($menu,false);
   echo "<br>\n";


   $arr_directions = get_message_directions();
   foreach( $roots as $threadlist )
      $threadlist->traverse( 'echo_message_row', $mtable );

   $mtable->echo_table();


   $menu_array = array();
   $menu_array[T_('Browse folders')]  = "list_messages.php";
   $menu_array[T_('Search messages')] = "search_messages.php";
   $menu_array += $menu;

   end_page(@$menu_array);
}

function echo_message_row( $threadlist, &$mtable )
{
   global $base_path, $player_row, $my_folders, $arr_directions, $mid, $with_text;

   $item = $threadlist->getItem();
   $level = $threadlist->getLevel();
   $bgcolor = $mtable->blend_next_row_color_hex();
   $curr_msg = ( $item['ID'] == $mid )
      ? "<a name=\"mid$mid\">"
            . anchor( 'message.php?mode=ShowMessage'.URI_AMP."mid=$mid",
                  image( $base_path.'images/msg.gif', T_('Current message'), null, 'class="InTextImage"' ))
            . MINI_SPACING
      : '';
   $max_indent = min( 20, $level );

   $row_str = array();
   $row_str[1] = array( 'owntd' => echo_folder_box($my_folders, $item['Folder_nr'], $bgcolor) );
   $row_str[2] = $arr_directions[$item['Sender']];
   $row_str[3] = message_build_user_string( $item, $player_row, true );
   $row_str[4] = $curr_msg . $level;
   $row_str[5] =
      ($level > $max_indent ? '...'.MINI_SPACING : '' ) . str_repeat( MED_SPACING, $max_indent )
      . anchor( 'message.php?mode=ShowMessage'.URI_AMP."mid={$item['ID']}",
            make_html_safe( $item['Subject'], SUBJECT_HTML ) );
   $row_str[6] = date(DATE_FMT, $item['X_Time']);

   if( $with_text )
   {
      $row_str['extra_row_class'] = 'MessageThread';
      $row_str['extra_row'] =
         '<td colspan="2" class="MessageThread">' . T_('Message') . ':</td>' .
         '<td colspan="4"><div class="MessageBox">'
            . make_html_safe( $item['Text'], true )
            . '</div></td>';
   }

   $mtable->add_row($row_str);
}

?>

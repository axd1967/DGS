<?php
/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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
require_once( "include/timezones.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   if( $_GET['del'] )
   {
      // delete messages

      if( $_GET['del'] == 'all' )
      {
         $result = mysql_query("UPDATE Messages " .
                               "SET Flags=CONCAT_WS(',',Flags,'DELETED') " .
                               "WHERE To_ID=$my_id AND " .
                               "NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' )");
      }
      else
      {
         $query = "UPDATE Messages " .
             "SET Flags=" .
             ( $_GET['del'] > 0 ? "CONCAT_WS(',',Flags,'DELETED')" : "REPLACE(Flags,'DELETED','')" ) .
             " WHERE To_ID=$my_id AND ID=" . abs($_GET['del']) . " AND " .
             "NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' ) LIMIT 1";

         mysql_query($query);

      }
   }


   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
       "Messages.ID AS mid, Messages.Subject, Messages.Flags, " .
       "Players.Name AS sender " .
       "FROM Messages, Players ";

   if( $_GET['sent']==1 )
      $query .= "WHERE From_ID=$my_id AND To_ID=Players.ID ";
   else
   {
      $query .= "WHERE To_ID=$my_id AND From_ID=Players.ID ";

      if( !($_GET['all']==1) )
         $query .= "AND NOT (Messages.Flags LIKE '%DELETED%') ";
      else
         $all_str = "&all=1 ";
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

   $query .= "ORDER BY $order LIMIT " . $_GET['from_row'] . ",$MaxRowsPerPage";

   $result = mysql_query( $query )
       or die ( error("mysql_query_failed") );

   $title = ( $_GET['sent'] == 1 ? T_('Sent messages') :
              ( $_GET['all'] == 1 ? T_('All messages') :
                T_('Message list') ));

   start_page($title, true, $logged_in, $player_row );

   echo "<center><h3><font color=$h3_color>" . $title . '</font></h3></center>';

   $mtable = new Table( make_url( 'list_messages.php',
                                  true,
                                  array('all' => $_GET['all'], 'sent' => $_GET['sent']) ),
                        '', '', true );
   $show_rows = mysql_num_rows($result);
   if( $show_rows == $MaxRowsPerPage )
   {
      $show_rows = $RowsPerPage;
      $mtable->Last_Page = false;
   }

   if( $_GET['sent'] == 1 )
   {
      $mtable->add_tablehead( 2, T_('To'), 'sender', false, true );
   }
   else
   {
      $mtable->add_tablehead( 1, T_('Flags'), '', true, true );
      $mtable->add_tablehead( 2, T_('From'), 'sender', false, true );
   }

   $mtable->add_tablehead( 3, T_('Subject'), 'Subject', false, true );
   $mtable->add_tablehead( 4, T_('Date'), 'date', true, true );

   if( !($_GET['sent']==1) )
   {
      $mtable->add_tablehead( 5, T_('Del'), NULL, true, true );
   }

   $i=0;
   $row_color=2;
   while( $row = mysql_fetch_array( $result ) )
   {
      $row_color=3-$row_color;
      $bgcolor = ${"table_row_color$row_color"};

      $mid = $row["mid"];
      if( !($_GET['sent']==1) and !(strpos($row["Flags"],'DELETED') === false) )
      {
         $mid = -$row["mid"];
         $bgcolor=${"table_row_color_del$row_color"};
      }

      $row_strings['BG_Color'] = $bgcolor;

      if( !($_GET['sent']==1) )
      {
         if( !(strpos($row["Flags"],'NEW') === false) )
         {
            $row_strings[1] = "<td bgcolor=\"00F464\">" . T_('New') . "</td>";
         }
         else if( !(strpos($row["Flags"],'REPLIED') === false) )
         {
            $row_strings[1] = '<td bgcolor="FFEE00">'. T_('Replied') . "</td>";
         }
         else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
         {
            $row_strings[1] = '<td bgcolor="FFA27A">' . T_('Reply!') . "</td>";
         }
         else
         {
            $row_strings[1] = "<td>&nbsp;</td>";
         }
      }

      $row_strings[2] = "<td><A href=\"message.php?mode=ShowMessage&mid=" . $row["mid"] . "\">" .
         make_html_safe($row["sender"]) . "</A></td>";
      $row_strings[3] = "<td>" . make_html_safe($row["Subject"]) . "&nbsp;</td>";
      $row_strings[4] = "<td>" . date($date_fmt, $row["date"]) . "</td>";

      if( !($_GET['sent']==1) and
          strpos($row["Flags"],'NEW') === false and
          ( strpos($row["Flags"],'REPLY REQUIRED') === false or
            !(strpos($row["Flags"],'REPLIED') === false) ) )
      {
         $row_strings[5] =
            '<td align=center><a href="' .
            make_url('list_messages.php',
                     false,
                     array( 'del' => $mid,
                            'all' => $_GET['all'],
                            'sort1' => $_GET['sort1'],
                            'desc1' => $_GET['desc1'],
                            'sort2' => $_GET['sort2'],
                            'desc2' => $_GET['desc2'] )) . "\"> " .
            "<img width=15 height=16 border=0 alt='X' " .
            "src=\"images/trashcan.gif\"></A></td>";
      }
      else if( !($_GET['sent']==1) )
      {
        $row_strings[5] = "<td>&nbsp;</td>";
      }

      $mtable->add_row( $row_strings );

      if(++$i >= $show_rows)
         break;
   }

   $mtable->echo_table();

   $menu_array = array( T_('Send a message') => 'message.php?mode=NewMessage' );


   if( $_GET['sent']==1 )
      $menu_array[T_('Show recieved messages')] = 'list_messages.php';
   else
   {
      if( $_GET['all']==1 )
         $menu_array[T_('Hide deleted')] = 'list_messages.php';
      else
         $menu_array[T_('Show all')] = 'list_messages.php?all=1';

      $menu_array[T_('Show sent messages')] = 'list_messages.php?sent=1';
      $menu_array[T_('Delete all')] = "list_messages.php?del=all$all_str";
   }

   end_page($menu_array);
}
?>

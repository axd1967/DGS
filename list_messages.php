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

require( "include/std_functions.php" );
require( "include/table_columns.php" );
require( "include/timezones.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $my_id = $player_row["ID"];

   if( $del )
   {
      // delete messages

      if( $del == 'all' )
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
             ( $del > 0 ? "CONCAT_WS(',',Flags,'DELETED')" : "REPLACE(Flags,'DELETED','')" ) .
             " WHERE To_ID=$my_id AND ID=" . abs($del) . " AND " .
             "NOT ( Flags LIKE '%NEW%' OR Flags LIKE '%REPLY REQUIRED%' ) LIMIT 1";

         mysql_query($query);

      }
   }


   $query = "SELECT UNIX_TIMESTAMP(Messages.Time) AS date, " .
       "Messages.ID AS mid, Messages.Subject, Messages.Flags, " .
       "Players.Name AS sender " .
       "FROM Messages, Players ";

   if( $sent==1 )
      $query .= "WHERE From_ID=$my_id AND To_ID=Players.ID ";
   else
   {
      $query .= "WHERE To_ID=$my_id AND From_ID=Players.ID ";

      if( !($all==1) )
         $query .= "AND NOT (Messages.Flags LIKE '%DELETED%') ";
      else
         $all_str = "&all=1 ";
   }


   if(!($limit > 0 ))
      $limit = 0;

   if(!$sort1)
   {
      $sort1 = 'date';
      $desc1 = 1;
   }

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   if( !is_numeric($from_row) or $from_row < 0 )
      $from_row = 0;

   $query .= "ORDER BY $order LIMIT $from_row,$MaxRowsPerPage";

   $result = mysql_query( $query )
       or die ( error("mysql_query_failed") );


   start_page(T_('Message list'), true, $logged_in, $player_row );

   $column_set=255;
   $page = make_url('list_messages.php', true, 'all', $all, 'sent', $sent);
   $show_rows = $nr_rows = mysql_num_rows($result);
   if( $nr_rows == $MaxRowsPerPage )
      $show_rows = $RowsPerPage;

   echo start_end_column_table(true);
   if( $sent == 1 )
   {
      echo tablehead(1, T_('To'), 'sender', false, true);
   }
   else
   {
      echo tablehead(1, T_('Flags'), '', true, true);
      echo tablehead(1, T_('From'), 'sender', false, true);
   }

   echo tablehead(1, T_('Subject'), 'Subject', false, true) .
      tablehead(1, T_('Date'), 'date', true, true);


   if( !($sent==1) )
      echo tablehead(1, T_('Del'), NULL, true, true);

   echo "</tr>\n";


   $i=0;
   $row_color=2;
   while( $row = mysql_fetch_array( $result ) )
   {
      $row_color=3-$row_color;
      $bgcolor = ${"table_row_color$row_color"};

      $mid = $row["mid"];
      if( !($sent==1) and !(strpos($row["Flags"],'DELETED') === false) )
      {
         $mid = -$row["mid"];
         $bgcolor=${"table_row_color_del$row_color"};
      }
      echo "<tr bgcolor=$bgcolor>\n";

      if( !($sent==1) )
      {
         if( !(strpos($row["Flags"],'NEW') === false) )
         {
            echo "<td bgcolor=\"00F464\">" . T_('New') . "</td>\n";
         }
         else if( !(strpos($row["Flags"],'REPLIED') === false) )
         {
            echo '<td bgcolor="FFEE00">'. T_('Replied') . "</td>\n";
         }
         else if( !(strpos($row["Flags"],'REPLY REQUIRED') === false) )
         {
            echo '<td bgcolor="FFA27A">' . T_('Reply!') . "</td>\n";
         }
         else
         {
            echo "<td>&nbsp;</td>\n";
         }
      }

      echo "<td><A href=\"message.php?mode=ShowMessage&mid=" . $row["mid"] . "\">" .
         $row["sender"] . "</A></td>\n" .
         "<td>" . make_html_safe($row["Subject"]) . "&nbsp;</td>\n" .
         "<td>" . date($date_fmt, $row["date"]) . "</td>\n";

      if( !($sent==1) and strpos($row["Flags"],'NEW') === false and
          ( strpos($row["Flags"],'REPLY REQUIRED') === false or
            !(strpos($row["Flags"],'REPLIED') === false) ) )
      {
         echo '<td align=center><a href="' .
            make_url('list_messages.php',false,'del',$mid,'all',$all,
                     'sort1',$sort1,'desc1',$desc1,'sort2',$sort2,'desc2',$desc2 ) .
            "\"> <img width=15 height=16 border=0 alt='X' src=\"images/trashcan.gif\"></A></td>\n";
      }
      else if( !($sent==1) )
         echo "<td>&nbsp;</td>\n";
      echo "</tr>\n";

      if(++$i >= $show_rows)
         break;
   }

   echo start_end_column_table(false);


   $menu_array = array( T_('Send a message') => 'message.php?mode=NewMessage' );


   if( $sent==1 )
      $menu_array[T_('Show recieved messages')] = 'list_messages.php';
   else
   {
      if( $all==1 )
         $menu_array[T_('Hide deleted')] = 'list_messages.php';
      else
         $menu_array[T_('Show all')] = 'list_messages.php?all=1';

      $menu_array[T_('Show sent messages')] = 'list_messages.php?sent=1';
      $menu_array[T_('Delete all')] = "list_messages.php?del=all$all_str";
   }

   end_page($menu_array);
}
?>

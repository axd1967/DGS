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


require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );

{
   connect2mysql();

   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $column_set = $player_row["UsersColumns"];
   $page = "users.php?";

   if( $showall ) $page .= "showall=1&";

   add_or_del($add, $del, "UsersColumns");

   if(!$sort1)
      $sort1 = 'ID';

   $order = $sort1 . ( $desc1 ? ' DESC' : '' );
   if( $sort2 )
      $order .= ",$sort2" . ( $desc2 ? ' DESC' : '' );

   if( !$showall )
       $where_clause = "WHERE Activity>$ActiveLevel1 ";

   $query = "SELECT *, Rank AS Rankinfo, " .
       "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, " .
       "Running+Finished AS Games, " .
       "IFNULL(100*Won/Finished,-0.01) AS Percent, " .
       "IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS lastaccess, " .
       "IFNULL(UNIX_TIMESTAMP(LastMove),0) AS Lastmove " .
       "FROM Players $where_clause ORDER BY $order";

   $result = mysql_query( $query );


   start_page(T_('Users'), true, $logged_in, $player_row );



   echo start_end_column_table(true) .
      tablehead(1, T_('ID'), 'ID') .
      tablehead(2, T_('Name'), 'Name') .
      tablehead(3, T_('Nick'), 'Handle') .
      tablehead(4, T_('Rank Info')) .
      tablehead(5, T_('Rating'), 'Rating', true) .
      tablehead(6, T_('Open for matches?')) .
      tablehead(7, T_('Games'), 'Games', true) .
      tablehead(8, T_('Running'), 'Running', true) .
      tablehead(9, T_('Finished'), 'Finished', true) .
      tablehead(10, T_('Won'), 'Won', true) .
      tablehead(11, T_('Lost'), 'Lost', true) .
      tablehead(12, T_('Percent'), 'Percent', true) .
      tablehead(13, T_('Activity'), 'ActivityLevel', true) .
      tablehead(14, T_('Last Access'), 'Lastaccess', true) .
                tablehead(15, T_('Last Moved'), 'Lastmove', true) .
                "</tr>\n";

   $row_color=2;
   while( $row = mysql_fetch_array( $result ) )
   {
      $ID = $row['ID'];
      $percent = ( $row["Finished"] == 0 ? '' : round($row["Percent"]). '%' );
      $a = $row['ActivityLevel'];
      $activity = ( $a == 0 ? '' :
                    ( $a == 1 ? '<img align=middle alt="*" src=images/star2.gif>' :
                      '<img align=middle alt="*" src=images/star.gif>' .
                      '<img align=middle alt="*" src=images/star.gif>' ) );

      $lastaccess = ($row["lastaccess"] > 0 ? date($date_fmt2, $row["lastaccess"]) : NULL );
      $lastmove = ($row["Lastmove"] > 0 ? date($date_fmt2, $row["Lastmove"]) : NULL );

      $row_color=3-$row_color;
      echo "<tr bgcolor=" . ${"table_row_color$row_color"} . ">\n";

      if( (1 << 0) & $column_set )
         echo "<td><A href=\"userinfo.php?uid=$ID\">$ID</A></td>\n";
      if( (1 << 1) & $column_set )
         echo "<td><A href=\"userinfo.php?uid=$ID\">" . make_html_safe($row['Name']) .
            "</A></td>\n";
      if( (1 << 2) & $column_set )
         echo "<td><A href=\"userinfo.php?uid=$ID\">" . make_html_safe($row['Handle']) .
            "</A></td>\n";
      if( (1 << 3) & $column_set )
         echo '<td>' . make_html_safe($row['Rankinfo'],true) . '&nbsp;</td>';
      if( (1 << 4) & $column_set )
         echo '<td>' . echo_rating($row['Rating'],true,$ID) . '&nbsp;</td>';
      if( (1 << 5) & $column_set )
         echo '<td>' . make_html_safe($row['Open'],true) . '&nbsp;</td>';
      if( (1 << 6) & $column_set )
         echo '<td>' . $row['Games'] . '&nbsp;</td>';
      if( (1 << 7) & $column_set )
         echo '<td>' . $row['Running'] . '&nbsp;</td>';
      if( (1 << 8) & $column_set )
         echo '<td>' . $row['Finished'] . '&nbsp;</td>';
      if( (1 << 9) & $column_set )
         echo '<td>' . $row['Won'] . '&nbsp;</td>';
      if( (1 << 10) & $column_set )
         echo '<td>' . $row['Lost'] . '&nbsp;</td>';
      if( (1 << 11) & $column_set )
         echo '<td>' . $percent . '&nbsp;</td>';
      if( (1 << 12) & $column_set )
         echo '<td>' . $activity . '&nbsp;</td>';
      if( (1 << 13) & $column_set )
         echo '<td>' . $lastaccess . '&nbsp;</td>';
      if( (1 << 14) & $column_set )
         echo '<td>' . $lastmove . '&nbsp;</td>';
      echo "</tr>\n";
   }

   echo start_end_column_table(false);


   $order = order_string($sort1, $desc1, $sort2, $desc2);

   if( strlen( $order ) > 0 )
      $vars = '?' . $order . ( $showall ? '' : '&showall=1');
   else
      $vars = 'showall=1';

   $menu_array = array( ( $showall ? T_("Only active users")  : T_("Show all users") ) =>
                        "users.php$vars" );

   end_page($menu_array);
}
?>

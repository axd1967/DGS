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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/countries.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $page = "users.php?";
   $where_clause = '';
   $showall = (boolean)@$_GET['showall'];
   if( $showall )
      $page .= "showall=1".URI_AMP;
   else
      $where_clause = "WHERE Activity>$ActiveLevel1 ";

   if(!@$_GET['sort1'])
   {
      $_GET['sort1'] = 'ID';
      $_GET['desc1'] = 0;
   }

   if(!@$_GET['sort2'])
   {
      $_GET['sort2'] = ($_GET['sort1'] != 'ID' ? 'ID' : 'Name');
      $_GET['desc2'] = 0;
   }

   $utable = new Table( $page, 'UsersColumns' );
   $utable->add_or_del_column();

   $order = $utable->current_order_string();
   $limit = $utable->current_limit_string();

   $query = "SELECT *, Rank AS Rankinfo, " .
       "(Activity>$ActiveLevel1)+(Activity>$ActiveLevel2) AS ActivityLevel, " .
       "Running+Finished AS Games, " .
       "ROUND(100*Won/RatedGames) AS Percent, " .
       "IFNULL(UNIX_TIMESTAMP(Lastaccess),0) AS lastaccess, " .
       "IFNULL(UNIX_TIMESTAMP(LastMove),0) AS Lastmove " .
       "FROM Players $where_clause ORDER BY $order $limit";

   $result = mysql_query( $query )
               or error("mysql_query_failed"); //die(mysql_error());

   $show_rows = $utable->compute_show_rows(mysql_num_rows($result));

   start_page(T_('Users'), true, $logged_in, $player_row );

   $utable->add_tablehead(1, T_('ID'), 'ID');
   $utable->add_tablehead(2, T_('Name'), 'Name');
   $utable->add_tablehead(3, T_('Userid'), 'Handle');
   $utable->add_tablehead(16, T_('Country'), 'Country');
   $utable->add_tablehead(4, T_('Rank info'));
   $utable->add_tablehead(5, T_('Rating'), 'Rating2', true);
   $utable->add_tablehead(6, T_('Open for matches?'));
   $utable->add_tablehead(7, T_('Games'), 'Games', true);
   $utable->add_tablehead(8, T_('Running'), 'Running', true);
   $utable->add_tablehead(9, T_('Finished'), 'Finished', true);
   $utable->add_tablehead(17, T_('Rated'), 'RatedGames', true);
   $utable->add_tablehead(10, T_('Won'), 'Won', true);
   $utable->add_tablehead(11, T_('Lost'), 'Lost', true);
   $utable->add_tablehead(12, T_('Percent'), 'Percent', true);
   $utable->add_tablehead(13, T_('Activity'), 'ActivityLevel', true);
   $utable->add_tablehead(14, T_('Last access'), 'Lastaccess', true);
   $utable->add_tablehead(15, T_('Last Moved'), 'Lastmove', true);

   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $ID = $row['ID'];

      $urow_strings = array();
      if( $utable->Is_Column_Displayed[1] )
         $urow_strings[1] = "<td><A href=\"userinfo.php?uid=$ID\">$ID</A></td>";
      if( $utable->Is_Column_Displayed[2] )
         $urow_strings[2] = "<td><A href=\"userinfo.php?uid=$ID\">" .
            make_html_safe($row['Name']) . "</A></td>";
      if( $utable->Is_Column_Displayed[3] )
         $urow_strings[3] = "<td><A href=\"userinfo.php?uid=$ID\">" .
            $row['Handle'] . "</A></td>";
      if( $utable->Is_Column_Displayed[16] )
      {
         $cntr = @$row['Country'];
         $cntrn = T_(@$COUNTRIES[$cntr]);
         $cntrn = (empty($cntr) ? '' :
             "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");
         $urow_strings[16] = "<td>" . $cntrn . "</td>";
      }
      if( $utable->Is_Column_Displayed[4] )
         $urow_strings[4] = '<td>' . make_html_safe(@$row['Rankinfo'],INFO_HTML) . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[5] )
         $urow_strings[5] = '<td>' . echo_rating(@$row['Rating2'],true,$ID) . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[6] )
         $urow_strings[6] = '<td>' . make_html_safe($row['Open'],INFO_HTML) . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[7] )
         $urow_strings[7] = '<td>' . $row['Games'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[8] )
         $urow_strings[8] = '<td>' . $row['Running'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[9] )
         $urow_strings[9] = '<td>' . $row['Finished'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[17] )
         $urow_strings[17] = '<td>' . $row['RatedGames'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[10] )
         $urow_strings[10] = '<td>' . $row['Won'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[11] )
         $urow_strings[11] = '<td>' . $row['Lost'] . '&nbsp;</td>';
      if( $utable->Is_Column_Displayed[12] )
      {
         $percent = ( is_numeric($row['Percent']) ? $row['Percent'].'%' : '' );
         $urow_strings[12] = '<td>' . $percent . '&nbsp;</td>';
      }
      if( $utable->Is_Column_Displayed[13] )
      {
         $a = $row['ActivityLevel'];
         $activity = ( $a == 0 ? '' :
                    ( $a == 1 ? '<img align=middle alt="*" src=images/star2.gif>' :
                      '<img align=middle alt="*" src=images/star.gif>' .
                      '<img align=middle alt="*" src=images/star.gif>' ) );
         $urow_strings[13] = '<td>' . $activity . '&nbsp;</td>';
      }
      if( $utable->Is_Column_Displayed[14] )
      {
         $lastaccess = ($row["lastaccess"] > 0 ? date($date_fmt2, $row["lastaccess"]) : NULL );
         $urow_strings[14] = '<td>' . $lastaccess . '&nbsp;</td>';
      }
      if( $utable->Is_Column_Displayed[15] )
      {
         $lastmove = ($row["Lastmove"] > 0 ? date($date_fmt2, $row["Lastmove"]) : NULL );
         $urow_strings[15] = '<td>' . $lastmove . '&nbsp;</td>';
      }

      $utable->add_row( $urow_strings );
   }

   $utable->echo_table();

   $orderstring = $utable->current_sort_string();

   if( $showall )
   {
      $str = T_("Only active users");
      $vars = ( $orderstring ? '?'.$orderstring : '');
   }
   else
   {
      $str = T_("Show all users");
      $vars = '?showall=1' . ( $orderstring ? URI_AMP.$orderstring : '');
   }

   $menu_array = array( $str => "users.php$vars" );

   end_page(@$menu_array);
}
?>

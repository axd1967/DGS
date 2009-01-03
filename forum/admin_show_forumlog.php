<?php
/*
Dragon Go Server
Copyright (C) 2001-2008  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/* PURPOSE: Edit user attributes except admin-related fields, needs ADMIN_DEVELOPER rights */

$TranslateGroups[] = "Admin";

chdir('..');
require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/filter.php" );
require_once( "forum/forum_functions.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_FORUM) )
      error('adminlevel_too_low');
   $show_ip = ( @$player_row['admin_level'] & ADMIN_DEVELOPER );

   $page = 'admin_show_forumlog.php';

   // table filters
   $flfilter = new SearchFilter();
   $flfilter->add_filter( 1, 'Numeric', 'FL.ID', true);
   $flfilter->add_filter( 2, 'Text',    'P.Handle', true);
   $flfilter->add_filter( 3, 'RelativeDate', 'FL.Time', true,
      array( FC_TIME_UNITS => FRDTU_ABS | FRDTU_ALL,
             FC_SIZE => 10 ));
   $flfilter->add_filter( 4, 'Selection',
      array( T_('All#filter')           => '',
             T_('New post#filter')      => "Action LIKE 'new_%'",
             T_('Edit post#filter')     => "Action LIKE 'edit_%'",
             T_('Moderate post#filter') => "Action IN ('"
                  . FORUMLOGACT_APPROVE_POST
                  . "','". FORUMLOGACT_REJECT_POST
                  . "','". FORUMLOGACT_SHOW_POST
                  . "','". FORUMLOGACT_HIDE_POST
                  . "')",
      ), true );
   if( $show_ip )
      $flfilter->add_filter( 5, 'Text', 'FL.IP', true,
         array( FC_SIZE => 16, FC_SUBSTRING => 1, FC_START_WILD => 1 ));
   $flfilter->init(); // parse current value from _GET
   $flfilter->set_accesskeys('x', 'e');

   $fltable = new Table( 'forumlog', $page, '' );
   $fltable->register_filter( $flfilter );
   $fltable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $fltable->add_tablehead( 1, T_('ID#header'), 'ID', TABLE_NO_HIDE, 'FL.ID+');
   $fltable->add_tablehead( 2, T_('Userid#header'), 'User', 0, 'P.Handle+');
   $fltable->add_tablehead( 3, T_('Time#header'), 'Date', 0, 'FL.Time-');
   $fltable->add_tablehead( 4, T_('Action#header'));
   if( $show_ip )
      $fltable->add_tablehead( 5, T_('IP#header'));
   $fltable->add_tablehead( 6, T_('Show Thread/Post#header') );

   $fltable->set_default_sort( 1 ); // on ID
   $order = $fltable->current_order_string();
   $limit = $fltable->current_limit_string();

   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'FL.*',
      'IFNULL(UNIX_TIMESTAMP(FL.Time),0) AS X_Time',
      'P.Handle AS P_Handle',
      'P.Name AS P_Name' );
   $qsql->add_part( SQLP_FROM, 'Forumlog AS FL', 'INNER JOIN Players AS P ON P.ID=FL.User_ID' );

   $query_flfilter = $fltable->get_query(); // clause-parts for filter
   $qsql->merge( $query_flfilter );
   $query = $qsql->get_select() . " $order $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'show_forumlog.find_data');

   $show_rows = $fltable->compute_show_rows(mysql_num_rows($result));

   $title = T_('Forum log');
   start_page( $title, true, $logged_in, $player_row );
   if ( $DEBUG_SQL ) echo "WHERE: " . make_html_safe($query_flfilter->get_select()) ."<br>";
   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);

   echo "<h3 class=Header>$title</h3>\n";

   while( $show_rows-- > 0 && ($row = mysql_fetch_assoc( $result )) )
   {
      $flrow_str = array();
      if( $fltable->Is_Column_Displayed[1] )
         $flrow_str[1] = @$row['ID'];
      if( $fltable->Is_Column_Displayed[2] )
         $flrow_str[2] = user_reference( REF_LINK, 1, '', @$row['User_ID'], @$row['P_Name'], @$row['P_Handle'] );
      if( $fltable->Is_Column_Displayed[3] )
         $flrow_str[3] = (@$row['X_Time'] > 0) ? date(DATE_FMT2, @$row['X_Time']) : NULL;
      if( $fltable->Is_Column_Displayed[4] )
         $flrow_str[4] = @$row['Action'];
      if( $show_ip && $fltable->Is_Column_Displayed[5] )
         $flrow_str[5] = @$row['IP'];
      if( $fltable->Is_Column_Displayed[6] )
         $flrow_str[6] = "<A HREF=\"read.php?thread=".@$row['Thread_ID'].URI_AMP."moderator=y#".@$row['Post_ID']."\">"
            . sprintf( T_('Show T%s/P%s'), @$row['Thread_ID'], @$row['Post_ID'] ) . "</A>";

      $fltable->add_row( $flrow_str );
   }
   mysql_free_result($result);
   $fltable->echo_table();

   // end of table

   echo "<br>\n",
      "NOTE: Log shows all user forum post events and all moderating actions by forum moderators.<br>\n";

   end_page();
}
?>

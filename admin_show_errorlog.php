<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/**
 * PURPOSE:
 * Show admins (Adminlevel>0) and administrated users (AdminOptions>0),
 * needs ADMIN_DEVELOPER rights
 */

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );
require_once( "include/filter.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'admin_show_errorlog');
   if( !(@$player_row['admin_level'] & ADMIN_DEVELOPER) )
      error('adminlevel_too_low', 'admin_show_errorlog');
   $show_ip = ( @$player_row['admin_level'] & ADMIN_DEVELOPER ); // only for adm-dev!

   // init
   $page = 'admin_show_errorlog.php';

   // table filters
   $elfilter = new SearchFilter();
   $elfilter->add_filter( 1, 'Numeric', 'EL.ID', true);
   $elfilter->add_filter( 3, 'RelativeDate', 'EL.Date', true,
      array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8 ));
   $elfilter->add_filter( 4, 'Text', 'EL.Message', true,
      array( FC_SIZE => 20 ));
   if( $show_ip )
      $elfilter->add_filter( 7, 'Text', 'EL.IP', true,
         array( FC_SIZE => 16, FC_SUBSTRING => 1, FC_START_WILD => 1 ));
   $elfilter->init(); // parse current value from _GET

   $atable = new Table( 'errorlog', $page, '', '', TABLE_ROW_NUM|TABLE_ROWS_NAVI );
   $atable->register_filter( $elfilter );
   $atable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $atable->add_tablehead( 1, /*T_*/('ID#header'), 'ID', TABLE_NO_HIDE, 'EL.ID-');
   $atable->add_tablehead( 9, /*T_*/('uid#header'), 'ID');
   $atable->add_tablehead( 2, /*T_*/('User#header'), 'User');
   $atable->add_tablehead( 3, /*T_*/('Time#header'), 'Date', 0, 'EL.Date-');
   $atable->add_tablehead( 4, /*T_*/('Message#header'));
   $atable->add_tablehead( 8, /*T_*/('Request#header'));
   $atable->add_tablehead( 5, /*T_*/('DB error#header'));
   $atable->add_tablehead( 6, /*T_*/('Debug info#header'));
   if( $show_ip )
      $atable->add_tablehead( 7, /*T_*/('IP#header'));
   $tbl_colcnt = $atable->get_column_count();

   $atable->set_default_sort( 1); // on ID
   $order = $atable->current_order_string();
   $limit = $atable->current_limit_string();

   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'EL.*',
      'IFNULL(UNIX_TIMESTAMP(EL.Date),0) AS X_Date',
      'PUser.ID AS PUser_ID',
      'PUser.Name AS PUser_Name',
      'PUser.Handle AS PUser_Handle' );
   $qsql->add_part( SQLP_FROM,
      'Errorlog AS EL',
      'LEFT JOIN Players AS PUser ON PUser.Handle=EL.Handle' );

   $query_elfilter = $atable->get_query(); // clause-parts for filter
   $qsql->merge( $query_elfilter );
   $query = $qsql->get_select() . " $order $limit";

   $result = db_query( 'admin_show_errorlog.find_data', $query );

   $show_rows = $atable->compute_show_rows(mysql_num_rows($result));
   $atable->set_found_rows( mysql_found_rows('admin_show_errorlog.found_rows') );

   $cols_dbginfo = $tbl_colcnt - 1;
   while( $show_rows-- > 0 && ($row = mysql_fetch_assoc( $result )) )
   {
      $arow_str = array();
      $dbginfo = '';
      if( $atable->Is_Column_Displayed[1] )
         $arow_str[1] = @$row['ID'];
      if( $atable->Is_Column_Displayed[2] )
      {
         $arow_str[2] = ((string)@$row['Handle'] != '')
            ? user_reference( REF_LINK, 1, '',
                  $row['PUser_ID'], $row['PUser_Name'], $row['PUser_Handle'] )
            : '';
      }
      if( $atable->Is_Column_Displayed[3] )
         $arow_str[3] = ($row['X_Date'] > 0 ? date(DATE_FMT3, $row['X_Date']) : NULL );
      if( $atable->Is_Column_Displayed[4] )
         $arow_str[4] = sprintf( '<b>%s</b>', @$row['Message'] );
      if( $atable->Is_Column_Displayed[8] )
      {
         if( strlen(@$row['Request']) <= 50 )
            $arow_str[8] = @$row['Request'];
         else
         {
            $arow_str[8] = '(' . /*T_*/('see next line') . ')';
            $dbginfo .= sprintf( '<u>%s:</u> <span class="DebugText">%s</span>',
               /*T_*/('Request info'), @$row['Request'] );
         }
      }
      if( $atable->Is_Column_Displayed[5] )
         $arow_str[5] = wordwrap(@$row['MysqlError'], 40, "<br>\n", true);
      if( $atable->Is_Column_Displayed[6] )
      {
         if( strlen(@$row['Debug']) <= 40 )
            $arow_str[6] = wordwrap(@$row['Debug'], 40, "<br>\n", true);
         else
         {
            $arow_str[6] = '(' . /*T_*/('see next line') . ')';
            $dbginfo = sprintf( '<u>%s:</u> <span class="DebugText">%s</span>',
               /*T_*/('Debug info'), wordwrap(@$row['Debug'], 120, "<br>\n", true) );
         }
      }
      if( $show_ip && $atable->Is_Column_Displayed[7] )
         $arow_str[7] = @$row['IP'];
      if( $atable->Is_Column_Displayed[9] )
      {
         $arow_str[9] = ((int)@$row['uid'] > 0)
            ? anchor( $base_path."userinfo.php?uid=".$row['uid'], $row['uid'] ) : '';
      }

      if( $dbginfo ) // put long-info in extra line
         $arow_str['extra_row'] = "<td></td><td colspan=\"$cols_dbginfo\">$dbginfo</td>";

      $atable->add_row( $arow_str );
   }
   mysql_free_result($result);


   start_page(/*T_*/('Show Error Log'), true, $logged_in, $player_row);
   section( 'errorlog', /*T_*/('Error Log') );
   if ( $DEBUG_SQL ) echo "WHERE: " . make_html_safe($query_elfilter->get_select()) ."<br>";
   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query);

   $atable->echo_table();

   echo "<br>\n",
      "NOTE: Log shows all error() events (e.g. db-errors, quick-suite errors, ",
      "GUI-errors and many more).<br>\n";

   end_page();
}

?>

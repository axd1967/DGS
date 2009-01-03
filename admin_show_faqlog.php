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

/**
 * PURPOSE:
 * Show admins (Adminlevel>0) and administrated users (AdminOptions>0),
 * needs ADMIN_DEVELOPER/PASSWORD/FORUM or /FAQ rights
 */

$TranslateGroups[] = "Admin";

require_once( "include/std_functions.php" );
require_once( "include/table_columns.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');

   if( !(@$player_row['admin_level'] & ADMIN_FAQ) )
      error('adminlevel_too_low');

   // init
   $page = 'admin_show_faqlog.php';

   start_page(T_('Show faq log'), true, $logged_in, $player_row);

   section( 'faqlog', T_('FAQ log') );

   $atable = new Table( 'faqlog', $page, '' );
   $atable->add_or_del_column();
   $limit = $atable->current_limit_string();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $atable->add_tablehead( 1, T_('ID#header'), 'ID');
   $atable->add_tablehead( 2, T_('User#header'), 'User');
   $atable->add_tablehead( 3, T_('Time#header'), 'User');
   $atable->add_tablehead( 4, T_('FAQ ID#header'), 'ID');
   $atable->add_tablehead( 5, T_('Question#header'));
   $atable->add_tablehead( 6, T_('Answer#header'));

   $result = mysql_query(
         'SELECT FL.*, FAQ.Level, ' .
            'IFNULL(UNIX_TIMESTAMP(FL.Date),0) AS X_Date, ' .
            'PUser.Handle AS PUser_Handle ' .
         'FROM FAQlog AS FL ' .
            'LEFT JOIN FAQ ON FAQ.ID=FL.FAQID ' .
            'LEFT JOIN Players AS PUser ON PUser.ID=FL.uid ' .
         'ORDER BY ID DESC ' . $limit )
      or error('mysql_query_failed', 'admin_show_errorlog.find_data');

   $show_rows = $atable->compute_show_rows(mysql_num_rows($result));

   while( $show_rows-- > 0 && ($row = mysql_fetch_assoc( $result )) )
   {
      $arow_str = array();
      if( $atable->Is_Column_Displayed[1] )
         $arow_str[1] = @$row['ID'];
      if( $atable->Is_Column_Displayed[2] )
         $arow_str[2] = ((string)@$row['PUser_Handle'] != '') ? $row['PUser_Handle'] : '';
      if( $atable->Is_Column_Displayed[3] )
         $arow_str[3] = ($row['X_Date'] > 0 ? date(DATE_FMT2, $row['X_Date']) : NULL );
      if( $atable->Is_Column_Displayed[4] )
      {
         $typechar = (@$row['Level'] == 1) ? 'c' : 'e';
         $edit_link = 'admin_faq.php?edit=1'.URI_AMP.'type='.$typechar.URI_AMP.'id='.@$row['FAQID'];
         $arow_str[4] = '<a href="' . $edit_link . '">' . sprintf( T_('Edit(%s)#faq'), @$row['FAQID']) . '</a>';
      }
      if( $atable->Is_Column_Displayed[5] )
         $arow_str[5] = make_html_safe( @$row['Question'], 'cell' );
      if( $atable->Is_Column_Displayed[6] )
         $arow_str[6] = make_html_safe( wordwrap(@$row['Answer'], 60, "\n", false), 'faq' );
      $atable->add_row( $arow_str );
   }
   mysql_free_result($result);

   $atable->echo_table();

   echo "<br>\n",
      "NOTE: Log shows changed FAQ-entries with user, time and new text.<br>\n";

   end_page();
}

?>

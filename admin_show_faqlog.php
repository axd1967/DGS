<?php
/*
Dragon Go Server
Copyright (C) 2001-2015  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
 * needs ADMIN_FAQ | DEVELOPER rights
 */

// translations remove for admin page: $TranslateGroups[] = "Admin";

require_once 'include/std_functions.php';
require_once 'include/table_columns.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'admin_show_faqlog');
   if ( $player_row['ID'] <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'admin_show_faqlog');
   if ( !(@$player_row['admin_level'] & (ADMIN_FAQ|ADMIN_DEVELOPER)) )
      error('adminlevel_too_low', 'admin_show_faqlog');

   // init
   $page = 'admin_show_faqlog.php';

   start_page(T_('Show FAQ Log'), true, $logged_in, $player_row);

   section( 'faqlog', T_('FAQ Log') );

   $atable = new Table( 'faqlog', $page, '' );
   $atable->add_or_del_column();
   $limit = $atable->current_limit_string();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $atable->add_tablehead( 1, T_('ID#header'), 'ID');
   $atable->add_tablehead( 7, T_('Type#header'));
   $atable->add_tablehead( 8, T_('Flags#header'));
   $atable->add_tablehead( 2, T_('User#header'), 'User');
   $atable->add_tablehead( 3, T_('Time#header'), 'User');
   $atable->add_tablehead( 4, T_('Ref ID#header'), 'ID');
   $atable->add_tablehead( 5, T_('Question & Reference#header'));
   $atable->add_tablehead( 6, T_('Answer#header'));

   $result = db_query( 'admin_show_errorlog.find_data',
         'SELECT FL.*, FAQ.Level, ' .
            'IFNULL(UNIX_TIMESTAMP(FL.Date),0) AS X_Date, ' .
            'PUser.Handle AS PUser_Handle ' .
         'FROM FAQlog AS FL ' .
            'LEFT JOIN FAQ ON FAQ.ID=FL.Ref_ID ' .
            'LEFT JOIN Players AS PUser ON PUser.ID=FL.uid ' .
         'ORDER BY ID DESC ' . $limit );

   $show_rows = $atable->compute_show_rows(mysql_num_rows($result));

   while ( $show_rows-- > 0 && ($row = mysql_fetch_assoc( $result )) )
   {
      $arow_str = array();
      if ( $atable->Is_Column_Displayed[1] )
         $arow_str[1] = @$row['ID'];
      if ( $atable->Is_Column_Displayed[2] )
         $arow_str[2] = ((string)@$row['PUser_Handle'] != '') ? $row['PUser_Handle'] : '';
      if ( $atable->Is_Column_Displayed[3] )
         $arow_str[3] = ($row['X_Date'] > 0 ? date(DATE_FMT2, $row['X_Date']) : NULL );
      if ( $atable->Is_Column_Displayed[4] )
      {
         $objtype = (@$row['Type'] == 'FAQ')
            ? TXTOBJTYPE_FAQ
            : ( @$row['Type'] == 'Links' ? TXTOBJTYPE_LINKS : TXTOBJTYPE_INTRO );
         $typechar = (@$row['Level'] == 1) ? 'c' : 'e';
         $edit_link = 'admin_faq.php?ot='.$objtype.URI_AMP.'edit=1'.URI_AMP.'type='.$typechar.URI_AMP.'id='.@$row['Ref_ID'];
         $arow_str[4] = '<a href="' . $edit_link . '">' . sprintf( T_('Edit(%s)#faq'), @$row['Ref_ID']) . '</a>';
      }
      if ( $atable->Is_Column_Displayed[5] )
         $arow_str[5] = make_html_safe( @$row['Question'], 'cell' )
            . (@$row['Reference'] ? "<br>\n" . $row['Reference'] : '' );
      if ( $atable->Is_Column_Displayed[6] )
         $arow_str[6] = make_html_safe( wordwrap(@$row['Answer'], 60, "\n", false), 'faq' );
      if ( $atable->Is_Column_Displayed[7] )
         $arow_str[7] = @$row['Type'];
      if ( $atable->Is_Column_Displayed[8] )
      {
         $out = array();
         if ( @$row['Flags'] & HELPFLAG_HIDDEN )
            $out[] = 'Hidden';
         if ( @$row['Flags'] & HELPFLAG_OPS_ONLY )
            $out[] = 'OPS-only';
         $arow_str[8] = explode(', ', $out);
      }
      $atable->add_row( $arow_str );
   }
   mysql_free_result($result);

   $atable->echo_table();

   echo "<br>\n",
      "NOTE: Log shows changed FAQ-entries with user, time and new text.<br>\n";

   $menu_array = array(
      T_('Edit FAQ') => "admin_faq.php",
      T_('Edit Introduction') => "admin_faq.php?ot=".TXTOBJTYPE_INTRO,
      T_('Edit Links') => "admin_faq.php?ot=".TXTOBJTYPE_LINKS,
      );

   end_page(@$menu_array);
}

?>

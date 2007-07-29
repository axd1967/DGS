<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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
require_once( "include/std_classes.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/countries.php" );
require_once( "include/filter.php" );
require_once( "include/contacts.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $uid = $player_row["ID"];
   $page = "list_contacts.php?";


   $arr_chk_sysflags = array();
   foreach( $ARR_CONTACT_SYSFLAGS as $sysflag => $arr ) // arr=( form_elem_name, flag-text )
   {
      $td_flagtext = "<td align=left>%s&nbsp;{$arr[1]}&nbsp;&nbsp;</td>";
      $arr_chk_sysflags[$td_flagtext] = array( $sysflag, T_('System Category') . ': ' . $arr[1] );
   }
   $arr_chk_userflags = array();
   foreach( $ARR_CONTACT_USERFLAGS as $userflag => $arr ) // arr=( form_elem_name, flag-text )
   {
      $td_flagtext = "<td align=left>%s&nbsp;{$arr[1]}&nbsp;&nbsp;</td>";
      $arr_chk_userflags[$td_flagtext] = array( $userflag, T_('User Category') . ': ' . $arr[1] );
   }

   // static filters on flags
   $scfilter = new SearchFilter('s');
   $scfilter->add_filter( 1, 'CheckboxArray', 'SystemFlags', true,
         array( FC_SIZE => 1, FC_BITMASK => 1, FC_MULTIPLE => $arr_chk_sysflags ) );
   $scfilter->add_filter( 2, 'CheckboxArray', 'UserFlags', true,
         array( FC_SIZE => 2, FC_BITMASK => 1, FC_MULTIPLE => $arr_chk_userflags ) );
   $scfilter->init(); // parse current value from _GET

   // table filters
   $cfilter = new SearchFilter();
   $cfilter->add_filter( 1, 'Text', 'P.Name', true,
         array( FC_SIZE => 12 ));
   $cfilter->add_filter( 2, 'Text', 'P.Handle', true);
   $cfilter->add_filter( 3, 'Country', 'P.Country', false,
         array( FC_HIDE => 1 ));
   $cfilter->add_filter( 4, 'Rating',  'P.Rating2', true);
   $cfilter->add_filter( 5, 'RelativeDate', 'P.Lastaccess', true);
   $filter_note =&
   $cfilter->add_filter( 8, 'Text', 'C.Note', true,
         array( FC_SIZE => 20, FC_SUBSTRING => 1, FC_START_WILD => 1 ));
   $cfilter->add_filter(10, 'RelativeDate', 'C.Created', true);
   $cfilter->add_filter(11, 'RelativeDate', 'C.Lastchanged', false);
   $cfilter->init(); // parse current value from _GET
   $rxterms_note = implode(' | ', $filter_note->get_terms() );

   $ctable = new Table( 'contact', $page, 'ContactColumns' );
   $ctable->register_filter( $cfilter );
   $ctable->set_default_sort( 'C.Lastchanged', 1);
   $ctable->add_or_del_column();

   // add_tablehead($nr, $descr, $sort=NULL, $desc_def=false, $undeletable=false, $attbs=NULL)
   //TODO: sort-image reversed ? expect asc/desc vice versa !?
   $ctable->add_tablehead( 9, T_('Actions'), null, false, true); // static
   $ctable->add_tablehead( 1, T_('Name'), 'P.Name');
   $ctable->add_tablehead( 2, T_('Userid'), 'P.Handle', true, true); // static
   $ctable->add_tablehead( 3, T_('Country'), 'P.Country');
   $ctable->add_tablehead( 4, T_('Rating'), 'P.Rating2', true);
   $ctable->add_tablehead( 5, T_('Last access'), 'P.Lastaccess', true);
   $ctable->add_tablehead( 6, T_('System categories'));
   $ctable->add_tablehead( 7, T_('User categories'));
   $ctable->add_tablehead( 8, T_('Notes'), '', false, false);
   $ctable->add_tablehead(10, T_('Created'), 'C.Created', true);
   $ctable->add_tablehead(11, T_('Lastchanged'), 'C.Lastchanged', true);

   // External-Search-Form
   $cform = new Form( $ctable->Prefix, $page, FORM_GET, false, 'forctable' );
   $cform->set_layout( FLAYOUT_GLOBAL, '(1|3|2)' );
   $cform->set_tabindex(1);
   $cform->set_config( FEC_TR_ATTR, 'valign=top' );
   $cform->set_config( FEC_EXTERNAL_FORM, true );
   $ctable->set_externalform( $cform );
   $cform->attach_table($scfilter); // for hiddens

   // attach external URL-parameters to table (for links)
   $extparam = $scfilter->get_req_params();
   $ctable->add_external_parameters( $extparam );

   $cform->set_area(1);
   $cform->add_row( array(
         'CELL', 1, 'align=left',
         'TEXT', '<b>' . T_('Search system categories') . ':</b>' )); // system-flags
   $cform->add_row( array(
         'FILTER',      $scfilter, 1 ));
   $cform->set_area(2);
   $cform->add_row( array(
         'CELL', 1, 'align=left', // DESCRIPTION not wanted (centers header)
         'TEXT', '<b>' . T_('Search user categories') . ':</b>' )); // user-flags
   $cform->add_row( array(
         'FILTER',      $scfilter, 2 ));
   $cform->set_area(3);
   $cform->add_row( array( 'TEXT', str_repeat('&nbsp', 5) ));

   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'P.Name', 'P.Handle', 'P.Country', 'P.Rating2',
      'IFNULL(UNIX_TIMESTAMP(P.Lastaccess),0) AS lastaccess',
      'C.cid', 'C.SystemFlags', 'C.UserFlags', 'C.Note',
      'C.Created', 'C.Lastchanged',
      'IFNULL(UNIX_TIMESTAMP(C.Created),0) AS created',
      'IFNULL(UNIX_TIMESTAMP(C.Lastchanged),0) AS lastchanged' );
   $qsql->add_part( SQLP_FROM,
      'Contacts C',
      'INNER JOIN Players P ON C.cid = P.ID' );

   $query_scfilter = $scfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_cfilter  = $ctable->get_query(); // clause-parts for filter
   $order = $ctable->current_order_string();
   $limit = $ctable->current_limit_string();

   $qsql->merge( $query_scfilter );
   $qsql->merge( $query_cfilter );

   $query = $qsql->get_select() . " ORDER BY $order $limit";

   $result = mysql_query( $query )
      or error('mysql_query_failed', 'list_contacts.find_data');

   $show_rows = $ctable->compute_show_rows(mysql_num_rows($result));


   $title = T_('Contact list');

   start_page( $title, true, $logged_in, $player_row );
   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) . "<br>\n";
   if ( $DEBUG_SQL ) echo "TERMS: " . $rxterms_note . "<br>\n";

   echo "<h3 class=Header>$title</h3>\n";


   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $crow_strings = array();
      $cid = $row['cid'];

      if( $ctable->Is_Column_Displayed[1] )
         $crow_strings[1] = "<td><A href=\"userinfo.php?uid=$cid\">" .
            make_html_safe($row['Name']) . "</A></td>";
      if( $ctable->Is_Column_Displayed[2] )
         $crow_strings[2] = "<td><A href=\"userinfo.php?uid=$cid\">" .
            $row['Handle'] . "</A></td>";
      if( $ctable->Is_Column_Displayed[3] )
      {
         $cntr = @$row['Country'];
         $cntrn = T_(@$COUNTRIES[$cntr]);
         $cntrn = (empty($cntr) ? '' :
             "<img title=\"$cntrn\" alt=\"$cntrn\" src=\"images/flags/$cntr.gif\">");
         $crow_strings[3] = "<td>" . $cntrn . "</td>";
      }
      if( $ctable->Is_Column_Displayed[4] )
         $crow_strings[4] = '<td>' . echo_rating(@$row['Rating2'],true,$cid) . '&nbsp;</td>';
      if( $ctable->Is_Column_Displayed[5] )
      {
         $lastaccess = ($row["lastaccess"] > 0 ? date($date_fmt2, $row["lastaccess"]) : NULL );
         $crow_strings[5] = '<td>' . $lastaccess . '&nbsp;</td>';
      }
      if( $ctable->Is_Column_Displayed[6] )
      {
         $str = Contact::format_system_flags($row['SystemFlags'], ',<br>');
         if ( $str == '' )
            $str = '---';
         $crow_strings[6] = "<td><font size=\"-1\">$str</font></td>";
      }
      if( $ctable->Is_Column_Displayed[7] )
      {
         $str = Contact::format_user_flags($row['UserFlags'], ',<br>');
         if ( $str == '' )
            $str = '---';
         $crow_strings[7] = "<td><font size=\"-1\">$str</font></td>";
      }
      if( $ctable->Is_Column_Displayed[8] )
      {
         $note = make_html_safe( $row['Note'], false, $rxterms_note );
         $note = preg_replace( "/(\r\n|\n)/", '<br>', $note ); // LF -> <br>
         $crow_strings[8] = "<td>$note</A></td>";
      }
      if( $ctable->Is_Column_Displayed[9] )
      {
         # TODO: add marker + action-select !? preferred to use images for M/I/E/R
         $sep = str_repeat('&nbsp;', 3);
         $links  = anchor( "message.php?mode=NewMessage&uid=$cid",
               image( 'images/msg.gif', T_('Send message') ),
               T_('Send message') ) . $sep;
         $links .= anchor( "message.php?mode=Invite&uid=$cid",
               image( 'images/favicon.ico', T_('Invite') ),
               T_('Invite') ) . $sep;
         $links .= anchor( "edit_contact.php?cid=$cid",
               'E',
               T_('Edit contact') ) . $sep;
         $links .= anchor( "edit_contact.php?cid=$cid".URI_AMP."contact_delete=1",
               image( 'images/trashcan.gif', T_('Remove contact') ),
               T_('Remove contact') ) . $sep;
         $crow_strings[9] = "<td>$sep$links</td>";
      }
      if( $ctable->Is_Column_Displayed[10] )
      {
         $created = ($row['created'] > 0 ? date($date_fmt2, $row['created']) : NULL );
         $crow_strings[10] = '<td>' . $created . '&nbsp;</td>';
      }
      if( $ctable->Is_Column_Displayed[11] )
      {
         $lastchanged = ($row['lastchanged'] > 0 ? date($date_fmt2, $row['lastchanged']) : NULL );
         $crow_strings[11] = '<td>' . $lastchanged . '&nbsp;</td>';
      }

      $ctable->add_row( $crow_strings );
   }

   // print form with table
   $extform_string = $cform->get_form_string(); // static form

   echo "\n"
      . $cform->print_start_default()
      . $ctable->make_table() . "<br>\n"
      . $extform_string
      . $cform->print_end();

   // end of table

   $menu_array = array(
      T_('Add new contact') => "edit_contact.php" );

   end_page(@$menu_array);
}
?>

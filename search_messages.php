<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

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
require_once( "include/std_classes.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );
require_once( "include/filter.php" );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $page = 'search_messages.php?';
   $my_id = $player_row["ID"];
   $my_folders = get_folders($my_id);

   $qsql = new QuerySQL(); // add extra-parts to SQL-statement

   $arr_chkfolders = array();
   foreach( $my_folders as $folder_id => $arr ) // arr=( Name, BGColor, FGColor )
   {
      $folder_box = echo_folder_box( $my_folders, $folder_id, null, 'style="padding:6px;"', '%s&nbsp;', '' );
      $arr_chkfolders[$folder_box] = array( $folder_id, T_('Folder') . ' ' . $arr[0] );
   }

   // Types: NORMAL,INVITATION, ACCEPTED, DECLINED, DISPUTED, RESULT, DELETED
   $arr_types = array(
      T_('All') => '',
      T_('Normal')   => "M.Type='NORMAL'",
      T_('Invites')  => "M.Type='INVITATION'",
      T_('Accepted') => "M.Type='ACCEPTED'",
      T_('Declined') => "M.Type='DECLINED'",
      T_('Disputed') => "M.Type='DISPUTED'",
      T_('Result')   => "M.Type='RESULT'",
   );

   /* SQL-statement-fields from message_list_query(), see below:
      FROM:
         Messages M - the message to show
         MessageCorrespondents me - always <>null
         MessageCorrespondents other - null for messages to myself
         Players otherP - other message-partner (null, if message to myself)
         MessageCorrespondents previous - may be null, if first message in a "thread"
         Games G - according game of message (if related)
      FIELDS:
         M:       Type, Subject, Time, Game_ID
         me:      mid as date ??? (for sorting?),
                  flow (FLOW_ANSWER +? FLOW_ANSWERED),
                  mid, Replied, Sender, folder (=Folder_nr)
         otherP:  other_name, other_handle, other_ID
    */

   // static filters
   $smfilter = new SearchFilter('s');
   $smfilter->add_filter( 1, 'CheckboxArray', 'me.Folder_nr', true,
         array( FC_SIZE => 5, FC_MULTIPLE => $arr_chkfolders ) );
   $smfilter->add_filter( 2, 'Boolean',       'M.ReplyTo=0', true,
         array( FC_LABEL => T_('Show only initial-messages') ) );
   //NOT-USED: $smfilter->add_filter( 3, 'Boolean', array( true  => 'me.Folder_Nr IS NULL', false => 'me.Folder_Nr IS NOT NULL' ), true, array( FC_LABEL => T_('Show deleted messages') ) );
   $smfilter->add_filter( 4, 'Selection',
         array( T_('All') => '',
                T_('Game-related')   => 'M.Game_ID<>0',
                T_('Game-unrelated') => 'M.Game_ID=0' ),
         true);
   $smfilter->init(); // parse current value from _GET

   // table-filters
   $mfilter = new SearchFilter();
   $mfilter->add_filter( 2, 'Text',    //! \todo can't search for myself with this filter (because otherP maybe null and therefore removing rows from SQL-result)!!
         '(other_name #OP #VAL OR other_Handle #OP #VAL)',
         true,
         array( FC_SIZE => 14, FC_ADD_HAVING => 1, FC_SQL_TEMPLATE => 1 ));
   $mfilter->add_filter( 3, 'MysqlMatch', 'M.Subject,M.Text', true,
         array( FC_MATCH_MODE => MATCH_BOOLMODE_SET ) );
   $mfilter->add_filter( 4, 'RelativeDate', 'M.Time', true);
   $mfilter->add_filter( 6, 'Selection', $arr_types, true);
   $mfilter->add_filter( 7, 'Selection',
         array( T_('All') => '',
                T_('Received') => "me.Sender IN ('N','M')",
                T_('Sent')     => "me.Sender IN ('Y','M')",
                T_('Myself')   => "me.Sender='M'" ),
         true);
   $mfilter->init(); // parse current value from _GET
   $sf3 =& $mfilter->get_filter(3);

   $mtable = new Table( 'message', $page );
   $mtable->register_filter( $mfilter );
   $mtable->set_default_sort( 'date', 1);
   $mtable->add_or_del_column();

   // only add tableheads
   message_list_table( $mtable, null, 0, FOLDER_NONE, $my_folders,
      /*no-sort*/ false, /*no-mark*/ true, /*toggle-mark*/ false,
      /*full-details*/ true, /*only-tablehead*/ true, /*terms*/ '' );

   // External-Search-Form
   $smform = new Form( $mtable->Prefix, $page, FORM_GET, false, 'formTable' );
   $smform->set_tabindex(1);
   $smform->set_config( FEC_TR_ATTR, 'valign=top' );
   $smform->set_config( FEC_EXTERNAL_FORM, true );
   $smform->set_attr_form_element( 'Description', FEA_ALIGN, 'left' );
   $mtable->set_externalform( $smform );
   $smform->attach_table($smfilter); // for hiddens

   // attach external URL-parameters to table (for links)
   $extparam = $smfilter->get_req_params();
   $mtable->add_external_parameters( $extparam );

   $smform->add_row( array(
         'DESCRIPTION', T_('Select folders'),
         'FILTER',      $smfilter, 1 ));
   $smform->add_empty_row();
   $smform->add_row( array(
         'DESCRIPTION', T_('Message scope'),
         'FILTER',      $smfilter, 2, // initial-msg
         'BR',
         'FILTER',      $smfilter, 4, // game-related
         ));
   $smform->add_empty_row();
   $smform->add_row( array(
         'TAB',
         'CELL',        1, 'align=left',
         'OWNHTML',     implode( '', $mfilter->get_submit_elements() ) ));

   // build SQL-query
   $query_smfilter = $smfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_mfilter  = $mtable->get_query(); // clause-parts for filter
   $terms = implode('|', $sf3->get_terms() );
   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   $qsql->merge( $query_smfilter );
   $qsql->merge( $query_mfilter );

   $qsql->add_part( SQLP_WHERE, 'me.Folder_Nr IS NOT NULL' ); // only non-deleted

   list( $result, $rqsql ) = message_list_query($my_id, '', $order, $limit, $qsql);

   $show_rows = mysql_num_rows($result);
   $show_rows = $mtable->compute_show_rows( $show_rows);


   $title = T_('Message list');
   start_page($title, true, $logged_in, $player_row,
               $mtable->button_style($player_row['Button']) );
   if ( $DEBUG_SQL ) echo "MARK-TERMS: " . make_html_safe($terms) . "<br>\n";
   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($rqsql->get_select()) . "<br>\n";

   echo "<h3 class=Header>" . T_('Message search') . "</h3>\n";

   message_list_table( $mtable, $result, $show_rows, FOLDER_NONE, $my_folders,
      false, true, false, // no-sort, no-mark, toggle-mark
      true, false, $terms ); // full-details, only-tablehead, terms

   // print form with table
   $extform_string =
      "<center>\n"
      . $smform->get_form_string() // static form
      . "</center>\n";

   echo "<br>\n"
      . $smform->print_start_default()
      . (( LAYOUT_FILTER_EXTFORM_HEAD )
            ? $extform_string . "<br>\n" . $mtable->make_table()
            : $mtable->make_table() . "<br>\n" . $extform_string )
      . $smform->print_end();


   $menu_array = array();
   $menu_array[ T_('Back to browsing folders') ] = "list_messages.php";
   $menu_array[ T_('Edit folders') ] = "edit_folders.php";

   end_page(@$menu_array);
}
?>

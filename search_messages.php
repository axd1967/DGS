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
      error('not_logged_in');

   $page = 'search_messages.php?';
   $my_id = $player_row["ID"];

   init_standard_folders();
   $my_folders = get_folders($my_id);

   $qsql = new QuerySQL(); // add extra-parts to SQL-statement

   $arr_chkfolders = array();
   foreach( $my_folders as $folder_id => $arr ) // arr=( Name, BGColor, FGColor )
   {
      $folder_box = echo_folder_box( $my_folders, $folder_id, null, '', "%%s%s");
      $arr_chkfolders[$folder_box] = $folder_id;
   }

   /* SQL-statement-fields from message_list_query(), see below:
      FROM:
         Messages M - the message to show
         MessageCorrespondents me - always <>null
         MessageCorrespondents other - null for messages to myself
         Players otherP - other message-partner (null, if message to myself)
         MessageCorrespondents previous - may be null, if first message in a "thread"
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
         array( FC_SIZE => 8, FC_MULTIPLE => $arr_chkfolders ) );
   $smfilter->add_filter( 2, 'Boolean',       'M.ReplyTo=0', true,
         array( FC_LABEL => T_('Show only initial-messages') ) );
   //NOT-USED: $smfilter->add_filter( 3, 'Boolean', array( true  => 'me.Folder_Nr IS NULL', false => 'me.Folder_Nr IS NOT NULL' ), true, array( FC_LABEL => T_//('Show deleted messages') ) );
   $smfilter->add_filter( 4, 'Selection',
         array( T_('All#filtermsg') => '',
                T_('Game-related#filtermsg')   => 'M.Game_ID>0', // <>0
                T_('Game-unrelated#filtermsg') => 'M.Game_ID=0' ),
         true);
   $smfilter->add_filter( 5, 'Boolean',
         new QuerySQL(SQLP_FROM,  "LEFT JOIN Games AS G ON M.Game_ID=G.ID",
                      SQLP_WHERE, "G.Status='INVITED'"), // not in left-join(!)
         true,
         array( FC_LABEL => T_('Show only pending invitations (if message not deleted)') ));
   $smfilter->init(); // parse current value from _GET

   // table-filters
   $mfilter = new SearchFilter();
   $mfilter->add_filter( 2, 'Text', // can't search for myself with this filter, because otherP maybe null and therefore removing rows from SQL-result(!)
         'other_Handle',
         // TODO: could use filter on both, but would need dynamic UNION to avoid 'OR':
         //'(other_name #OP #VAL OR other_Handle #OP #VAL)',
         true,
         array( FC_SIZE => 14, FC_ADD_HAVING => 1,
                FC_SYNTAX_HINT => array( FCV_SYNHINT_ADDINFO => T_('find Userid#filtermsg') ) ));
   $mfilter->add_filter( 3, 'MysqlMatch', 'M.Subject,M.Text', true,
         array( FC_MATCH_MODE => MATCH_BOOLMODE_SET ) );
   $mfilter->add_filter( 4, 'RelativeDate', 'M.Time', true);
   //NOT-USED: $mfilter->add_filter( 6, 'Selection', $arr_types, true);
   $mfilter->add_filter( 7, 'Selection',
         array( T_('All#msgdir') => '',   // sync this transl-texts with: message_functions.php (message_list_table)
                T_('From#msgdir')   => "me.Sender='N'", // from other user
                T_('To#msgdir')     => "me.Sender='Y'", // to other user
                T_('Myself#msgdir') => "me.Sender='M'", // from/to myself
                T_('Server#msgdir') => "me.Sender='S'", // from server
            ),
         true);
   $mfilter->init(); // parse current value from _GET
   $mfilter->set_accesskeys('x', 'e');
   $sf3 =& $mfilter->get_filter(3);

   $mtable = new Table( 'message', $page, '', 'msgSearch');
   $mtable->register_filter( $mfilter );
   $mtable->set_default_sort( 'date', 1);
   $mtable->add_or_del_column();

   // only add tableheads
   message_list_table( $mtable, null, 0, FOLDER_NONE, $my_folders,
      /*no-sort*/ false, /*no-mark*/ true, /*toggle-mark*/ false,
      /*full-details*/ true, /*only-tablehead*/ true, /*rx_terms*/ '' );

   // External-Search-Form
   $smform = new Form( $mtable->Prefix, $page, FORM_GET, false, 'FormTable' );
   $smform->set_tabindex(1);
   $smform->set_config( FEC_TR_ATTR, 'valign=top' );
   $smform->set_config( FEC_EXTERNAL_FORM, true );
   $mtable->set_externalform( $smform );
   $smform->attach_table($smfilter); // for hiddens

   // attach external URL-parameters to table (for links)
   $extparam = $smfilter->get_req_params();
   $mtable->add_external_parameters( $extparam );

   $smform->add_row( array(
         'ROW', 'SelectFolders',
         'DESCRIPTION', T_('Select folders#filtermsg'),
         'FILTER',      $smfilter, 1 ));
   $smform->add_row( array(
         'DESCRIPTION', T_('Message scope#filtermsg'),
         'FILTER',      $smfilter, 4, // game-related
         'BR',
         'FILTER',      $smfilter, 2, // initial-msg
         'BR',
         'FILTER',      $smfilter, 5, // pending invitations
         ));

   // build SQL-query
   $query_smfilter = $smfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_mfilter  = $mtable->get_query(); // clause-parts for filter
   $rx_term = implode('|', $sf3->get_rx_terms() );
   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   $qsql->merge( $query_smfilter );
   $qsql->merge( $query_mfilter );

   $qsql->add_part( SQLP_WHERE, 'me.Folder_Nr IS NOT NULL' ); // only non-deleted

   list( $result, $rqsql ) = message_list_query($my_id, '', $order, $limit, $qsql);

   $show_rows = mysql_num_rows($result);
   $show_rows = $mtable->compute_show_rows( $show_rows);


   $title = T_('Message search');
   start_page($title, true, $logged_in, $player_row);
   if ( $DEBUG_SQL ) echo "MARK-TERMS: " . make_html_safe($rx_term) . "<br>\n";
   if ( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($rqsql->get_select()) . "<br>\n";

   echo "<h3 class=Header>$title</h3>\n";

   message_list_table( $mtable, $result, $show_rows, FOLDER_NONE, $my_folders,
      false, true, false, // no-sort, no-mark, toggle-mark
      true, false, $rx_term); // full-details, only-tablehead, terms

   // print form with table
   $extform_string = $smform->get_form_string(); // static form

   echo "\n"
      . $smform->print_start_default()
      . (( LAYOUT_FILTER_EXTFORM_HEAD )
            ? $extform_string . "<br>\n" . $mtable->make_table()
            : $mtable->make_table() . "<br>\n" . $extform_string )
      . $smform->print_end();


   $menu_array = array();
   $menu_array[ T_('Browse folders') ] = "list_messages.php";
   $menu_array[ T_('Edit folders') ] = "edit_folders.php";

   end_page(@$menu_array);
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Messages";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/form_functions.php';
require_once 'include/message_functions.php';
require_once 'include/filter.php';
require_once 'include/filterlib_mysqlmatch.php';
require_once 'include/classlib_profile.php';

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'search_messages');

   $page = 'search_messages.php?';
   $my_id = $player_row["ID"];

   init_standard_folders();
   $my_folders = get_folders($my_id);

   $qsql = new QuerySQL(); // add extra-parts to SQL-statement

   $arr_chkfolders = array();
   foreach ( $my_folders as $folder_id => $arr ) // arr=( Name, BGColor, FGColor )
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

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_MSG_SEARCH );
   $smfilter = new SearchFilter( 's', $search_profile );
   $mfilter = new SearchFilter( '', $search_profile );
   //#$search_profile->register_regex_save_args( '' ); // named-filters FC_FNAME
   $mtable = new Table( 'message', $page, '', 'msgSearch',
      TABLE_NO_HIDE | ( ENABLE_MESSAGE_NAVIGATION ? TABLE_ROWS_NAVI : 0 ) );
   $mtable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // static filters
   $smfilter->add_filter( 1, 'CheckboxArray', 'me.Folder_nr', true,
         array( FC_SIZE => FOLDER_COLS_MODULO, FC_MULTIPLE => $arr_chkfolders ) );
   $smfilter->add_filter( 2, 'Boolean',       'M.ReplyTo=0', true,
         array( FC_LABEL => T_('Show only initial-messages') ) );
   $smfilter->add_filter( 4, 'Selection',
         array( T_('All#filtermsg') => '',
                T_('Game-related#filtermsg')   => 'M.Game_ID>0', // <>0
                T_('Game-unrelated#filtermsg') => 'M.Game_ID=0' ),
         true);
   $smfilter->add_filter( 5, 'Selection',
         array( T_('All messages#filtermsg') => '',
                T_('Pending invitations#filterinv') => build_qsql_games(GAME_STATUS_INVITED),
                T_('Accepted invitations (for started games)#filterinv') => build_qsql_games(true),
                T_('Accepted invitations (for finished games)#filterinv') => build_qsql_games(GAME_STATUS_FINISHED),
                T_('Declined invitations#filterinv') => new QuerySQL(SQLP_WHERE, "M.Game_ID>0", "M.Subject='Game invitation decline'" ),
                T_('All invitations (without declines)#filterinv') => build_qsql_games(),
            ),
         true);
   $smfilter->init(); // parse current value from _GET

   // table-filters
   $mfilter->add_filter( 2, 'Text', // can't search for myself with this filter, because otherP maybe null and therefore removing rows from SQL-result(!)
         'other_Handle',
         // NOTE: could use filter on both, but would need dynamic UNION to avoid 'OR':
         //'(other_name #OP #VAL OR other_Handle #OP #VAL)',
         true,
         array( FC_SIZE => 14, FC_ADD_HAVING => 1,
                FC_SYNTAX_HINT => array( FCV_SYNHINT_ADDINFO => T_('find Userid#filtermsg') ) ));
   $mfilter->add_filter( 3, 'MysqlMatch', 'M.Subject,M.Text', true,
         array( FC_MATCH_MODE => MATCH_BOOLMODE_SET ) );
   $mfilter->add_filter( 4, 'RelativeDate', 'M.Time', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8 ) );

   // synchronize those translations with message_functions.php (get_message_directions)
   $mfilter->add_filter( 7, 'Selection',
         array( T_('All#msgdir')    => '',
                T_('From#msgdir')   => "me.Sender='N'", // from other user
                T_('To#msgdir')     => "me.Sender='Y'", // to other user
                T_('Myself#msgdir') => "me.Sender='M'", // from/to myself
                T_('Server#msgdir') => "me.Sender='S'", // from server
            ),
         true);

   $mfilter->init(); // parse current value from _GET
   $sf3 =& $mfilter->get_filter(3);

   // init table
   $mtable->register_filter( $mfilter );
   $mtable->add_or_del_column();

   $msglist_builder = new MessageListBuilder( $mtable, FOLDER_NONE, /*no_mark*/true, /*full-details*/true );
   $msglist_builder->message_list_head();
   $mtable->set_default_sort( 4); //on 'date'

   // External-Search-Form
   $smform = new Form( $mtable->get_prefix(), $page, FORM_GET, false, 'FormTable' );
   $smform->set_tabindex(1);
   $smform->set_config( FEC_EXTERNAL_FORM, true );
   $mtable->set_externalform( $smform );
   $smform->attach_table($smfilter); // for hiddens

   // attach external URL-parameters to table (for links)
   $extparam = $smfilter->get_req_params();
   $mtable->add_external_parameters( $extparam, false );

   $smform->add_row( array(
         'ROW', 'SelectFolders',
         'DESCRIPTION', T_('Select folders#filtermsg'),
         'FILTER',      $smfilter, 1 ));
   $smform->add_row( array(
         'DESCRIPTION', T_('Invitations#filtermsg'),
         'FILTER',      $smfilter, 5 ));
   $smform->add_row( array(
         'DESCRIPTION', T_('Game message scope#filtermsg'),
         'FILTER',      $smfilter, 4, // game-related
         'BR',
         'FILTER',      $smfilter, 2, // initial-msg
         ));

   // build SQL-query
   $query_smfilter = $smfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_mfilter  = $mtable->get_query(); // clause-parts for filter
   $rx_term = implode('|', $sf3->get_rx_terms() );
   $order = $mtable->current_order_string();
   $limit = $mtable->current_limit_string();

   $qsql->merge( $query_smfilter );
   $qsql->merge( $query_mfilter );

   // only std- & user-folders, i.e. non-deleted
   $qsql->add_part( SQLP_WHERE, 'me.Folder_Nr > '.FOLDER_ALL_RECEIVED );

   list( $arr_msg, $num_rows, $found_rows ) =
      MessageListBuilder::message_list_query( $my_id, '', $order, $limit, ENABLE_MESSAGE_NAVIGATION, $qsql );
   $show_rows = $mtable->compute_show_rows( $num_rows );
   $mtable->set_found_rows( $found_rows );


   $title = T_('Message search');
   start_page($title, true, $logged_in, $player_row);

   echo "<h3 class=Header>$title</h3>\n";

   $msglist_builder->message_list_body( $arr_msg, $show_rows, $my_folders, /*toggle_marks*/false, $rx_term);

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
}//main


function build_qsql_games( $gstatus=null )
{
   $qsql = new QuerySQL(
      SQLP_FROM, "INNER JOIN Games AS G ON G.ID=M.Game_ID",
      SQLP_WHERE, "M.Type<>'".MSGTYPE_RESULT."'", "me.Sender IN ('Y','N')" );
   if ( $gstatus === true )
      $qsql->add_part( SQLP_WHERE, 'G.Status'.IS_STARTED_GAME );
   elseif ( !is_null($gstatus) )
      $qsql->add_part( SQLP_WHERE, "G.Status='$gstatus'" );
   return $qsql;
}

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( 'include/gui_functions.php' );
require_once( "include/std_classes.php" );
require_once( "include/countries.php" );
require_once( "include/rating.php" );
require_once( "include/table_columns.php" );
require_once( "include/form_functions.php" );
require_once( "include/filter.php" );
require_once( "include/filterlib_country.php" );
require_once( "include/contacts.php" );
require_once( "include/classlib_profile.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( 'include/classlib_userpicture.php' );

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = (int)@$player_row['ID'];
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_CONTACTS );

   $page = "list_contacts.php?";

   $tid = (int)get_request_arg('tid'); // convenience for tourney-invites
   if( $tid < 0 ) $tid = 0;

   $arr_chk_sysflags = array();
   foreach( Contact::getContactSystemFlags() as $sysflag => $arr ) // arr=( form_elem_name, flag-text )
   {
      $td_flagtext = "<td>%s{$arr[1]}</td>";
      $arr_chk_sysflags[$td_flagtext] = $sysflag;
   }
   $arr_chk_userflags = array();

   foreach( Contact::getContactUserFlags() as $userflag => $arr ) // arr=( form_elem_name, flag-text )
   {
      $td_flagtext = "<td>%s{$arr[1]}</td>";
      $arr_chk_userflags[$td_flagtext] = $userflag;
   }

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_CONTACTS );
   $scfilter = new SearchFilter( 's', $search_profile );
   $cfilter = new SearchFilter( '', $search_profile );
   //$search_profile->register_regex_save_args( '' ); // named-filters FC_FNAME
   $ctable = new Table( 'contact', $page, $cfg_tblcols, 'contact' );
   $ctable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // static filters on flags
   $scfilter->add_filter( 1, 'CheckboxArray', 'C.SystemFlags', true,
         array( FC_SIZE => 1, FC_BITMASK => 1, FC_MULTIPLE => $arr_chk_sysflags ) );
   $scfilter->add_filter( 2, 'CheckboxArray', 'C.UserFlags', true,
         array( FC_SIZE => 2, FC_BITMASK => 1, FC_MULTIPLE => $arr_chk_userflags ) );
   $scfilter->init(); // parse current value from _GET

   // table filters
   $cfilter->add_filter( 1, 'Text', 'P.Name', true,
         array( FC_SIZE => 12 ));
   $cfilter->add_filter( 2, 'Text', 'P.Handle', true);
   $cfilter->add_filter( 3, 'Country', 'P.Country', false,
         array( FC_HIDE => 1 ));
   $cfilter->add_filter( 4, 'Rating',  'P.Rating2', true);
   $cfilter->add_filter( 5, 'RelativeDate', 'P.Lastaccess', true);
   $filter_note =&
      $cfilter->add_filter( 8, 'Text', 'C.Notes #OP #VAL', true,
         array( FC_SIZE => 20, FC_SUBSTRING => 1, FC_START_WILD => 1, FC_SQL_TEMPLATE => 1 ));
/* old try with a binary field:
      $cfilter->add_filter( 8, 'Text', 'LOWER(C.Notes) #OP LOWER(#VAL)', true,
         array( FC_SIZE => 20, FC_SUBSTRING => 1, FC_START_WILD => 1, FC_SQL_TEMPLATE => 1 ));
*/
   $cfilter->add_filter(10, 'RelativeDate', 'C.Created', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 8 ) );
   $cfilter->add_filter(11, 'RelativeDate', 'C.Lastchanged', false);
   $cfilter->init(); // parse current value from _GET
   $rx_term = implode('|', $filter_note->get_rx_terms() );

   // init table
   $ctable->register_filter( $cfilter );
   $ctable->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ctable->add_tablehead( 9, T_('Actions#header'), 'Image', TABLE_NO_HIDE, '');
   $ctable->add_tablehead(12, T_('Type#header'), 'Enum', 0, 'P.Type+');
   if( USERPIC_FOLDER != '' )
      $ctable->add_tablehead(13, new TableHead( T_('User picture#header'),
         'images/picture.gif', T_('Indicator for existing user picture') ), 'Image', 0, 'P.UserPicture+' );
   $ctable->add_tablehead( 1, T_('Name#header'), 'User', 0, 'P.Name+');
   $ctable->add_tablehead( 2, T_('Userid#header'), 'User', TABLE_NO_HIDE, 'P.Handle+');
   $ctable->add_tablehead( 3, T_('Country#header'), 'Image', 0, 'P.Country+');
   $ctable->add_tablehead( 4, T_('Rating#header'), 'Rating', 0, 'P.Rating2-');
   $ctable->add_tablehead(14, new TableHead( T_('User online#header'),
      'images/online.gif', sprintf( T_('Indicator for being online up to %s mins ago'), SPAN_ONLINE_MINS) ), 'Image', 0 );
   $ctable->add_tablehead( 5, T_('Last access#header'), 'Date', 0, 'P.Lastaccess-');
   $ctable->add_tablehead( 6, T_('System categories#header'), 'Enum', 0, 'C.SystemFlags+');
   $ctable->add_tablehead( 7, T_('User categories#header'), 'Enum', 0, 'C.UserFlags+');
   $ctable->add_tablehead( 8, T_('Notes#header'), '', TABLE_NO_SORT);
   $ctable->add_tablehead(10, T_('Created#header'), 'Date', 0, 'C.Created-');
   $ctable->add_tablehead(11, T_('Modified#header'), 'Date', 0, 'C.Lastchanged-');

   $ctable->set_default_sort( 1); //on P.Name

   // External-Search-Form
   $cform = new Form( 'contact', $page, FORM_GET, false);
   $cform->set_layout( FLAYOUT_GLOBAL, '1|2' );
   $cform->set_tabindex(1);
   $cform->set_config( FEC_EXTERNAL_FORM, true );
   $ctable->set_externalform( $cform );
   $cform->attach_table($scfilter); // for hiddens

   // attach external URL-parameters to table (for links)
   $ctable->add_external_parameters( $scfilter->get_req_params(), false );
   if( $tid )
   {
      $page_vars = new RequestParameters();
      $page_vars->add_entry( 'tid', $tid );
      $cform->attach_table( $page_vars ); // for page-vars as hiddens in form
      $ctable->add_external_parameters( $page_vars, true ); // add as hiddens
   }

   $cform->set_area(1);
   $cform->add_row( array(
         'FILTER',      $scfilter, 1 ));
   $cform->set_layout( FLAYOUT_AREACONF, 1, array(
         'title' => T_('Search system categories'),
         FAC_TABLE => 'class=FilterOptions',
      ) );

   $cform->set_area(2);
   $cform->add_row( array(
         'FILTER',      $scfilter, 2 ));
   $cform->set_layout( FLAYOUT_AREACONF, 2, array(
         'title' => T_('Search user categories'),
         FAC_TABLE => 'class=FilterOptions',
      ) );

   // build SQL-query
   $qsql = new QuerySQL();
   $qsql->add_part( SQLP_FIELDS,
      'P.Type', 'P.Name', 'P.Handle', 'P.Country', 'P.Rating2', 'P.UserPicture',
      'UNIX_TIMESTAMP(P.Lastaccess) AS lastaccessU',
      'C.cid', 'C.SystemFlags', 'C.UserFlags AS ContactsUserFlags', 'C.Notes',
      'C.Created', 'C.Lastchanged',
      'IFNULL(UNIX_TIMESTAMP(C.Created),0) AS createdU',
      'IFNULL(UNIX_TIMESTAMP(C.Lastchanged),0) AS lastchangedU' );
   $qsql->add_part( SQLP_FROM,
      'Contacts AS C',
      'INNER JOIN Players AS P ON C.cid = P.ID' );
   $qsql->add_part( SQLP_WHERE,
      "C.uid=$my_id AND C.cid>".GUESTS_ID_MAX ); //exclude guest

   $query_scfilter = $scfilter->get_query(GETFILTER_ALL); // clause-parts for static filter
   $query_cfilter  = $ctable->get_query(); // clause-parts for filter
   $order = $ctable->current_order_string();
   $limit = $ctable->current_limit_string();

   $qsql->merge( $query_scfilter );
   $qsql->merge( $query_cfilter );

   $query = $qsql->get_select() . "$order$limit";

   $result = db_query( 'list_contacts.find_data', $query );

   $show_rows = $ctable->compute_show_rows(mysql_num_rows($result));


   $title = T_('Contact list');

   start_page( $title, true, $logged_in, $player_row );
   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe($query) . "<br>\n";
   if( $DEBUG_SQL ) echo "TERMS: " . $rx_term . "<br>\n";

   echo "<h3 class=Header>$title</h3>\n";


   while( ($row = mysql_fetch_assoc( $result )) && $show_rows-- > 0 )
   {
      $crow_strings = array();
      $cid = $row['cid'];

      if( $ctable->Is_Column_Displayed[ 9] )
      {
         $uinvlink = ( $tid )
            ? "tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$cid"
            : "message.php?mode=Invite".URI_AMP."uid=$cid";

         $links  = anchor( "message.php?mode=NewMessage".URI_AMP."uid=$cid",
               image( 'images/send.gif', 'M'),
               T_('Send a message'), 'class=ButIcon');
         $links .= anchor( $uinvlink,
               image( 'images/invite.gif', 'I'),
               T_('Invite'), 'class=ButIcon');
         $links .= anchor( "edit_contact.php?cid=$cid",
               image( 'images/edit.gif', 'E'),
               T_('Edit contact'), 'class=ButIcon');
         $links .= anchor( "edit_contact.php?cid=$cid".URI_AMP."contact_delete=1",
               image( 'images/trashcan.gif', 'X'),
               T_('Remove contact'), 'class=ButIcon');
         $crow_strings[ 9] = $links;
      }
      if( $ctable->Is_Column_Displayed[ 1] )
         $crow_strings[ 1] = "<A href=\"userinfo.php?uid=$cid\">" .
            make_html_safe($row['Name']) . "</A>";
      if( $ctable->Is_Column_Displayed[12] )
         $crow_strings[12] = build_usertype_text(@$row['Type'], ARG_USERTYPE_NO_TEXT, true, ' ');
      if( @$row['UserPicture'] && $ctable->Is_Column_Displayed[13] )
         $crow_strings[13] = UserPicture::getImageHtml( @$row['Handle'], true );
      if( $ctable->Is_Column_Displayed[ 2] )
         $crow_strings[ 2] = "<A href=\"userinfo.php?uid=$cid\">" .
            $row['Handle'] . "</A>";
      if( $ctable->Is_Column_Displayed[ 3] )
         $crow_strings[ 3] = getCountryFlagImage( @$row['Country'] );
      if( $ctable->Is_Column_Displayed[ 4] )
         $crow_strings[ 4] = echo_rating(@$row['Rating2'],true,$cid);
      if( $ctable->Is_Column_Displayed[ 5] )
         $crow_strings[ 5] = ($row['lastaccessU']>0 ? date(DATE_FMT2, $row['lastaccessU']) : '');
      if( $ctable->Is_Column_Displayed[ 6] )
      {
         $str = Contact::format_system_flags($row['SystemFlags'], ',<br>');
         $crow_strings[ 6] = ($str == '' ? NO_VALUE : $str);
      }
      if( $ctable->Is_Column_Displayed[ 7] )
      {
         $str = Contact::format_user_flags($row['ContactsUserFlags'], ',<br>');
         $crow_strings[ 7] = ($str == '' ? NO_VALUE : $str);
      }
      if( $ctable->Is_Column_Displayed[ 8] )
      {
         $note = make_html_safe( $row['Notes'], false, $rx_term);
         //reduce multiple LF to one <br>
         $note = preg_replace( "/[\r\n]+/", '<br>', $note );
         $crow_strings[ 8] = $note;
      }
      if( $ctable->Is_Column_Displayed[10] )
      {
         $crow_strings[10] =
            ($row['createdU']>0 ? date(DATE_FMT2, $row['createdU']) : '');
      }
      if( $ctable->Is_Column_Displayed[11] )
      {
         $crow_strings[11] =
            ($row['lastchangedU']>0 ? date(DATE_FMT2, $row['lastchangedU']) : '');
      }
      if( $ctable->Is_Column_Displayed[14] )
      {
         $is_online = ($NOW - @$row['lastaccessU']) < SPAN_ONLINE_MINS * 60; // online up to X mins ago
         $crow_strings[14] = echo_image_online( $is_online, @$row['lastaccessU'], false );
      }

      $ctable->add_row( $crow_strings );
   }
   mysql_free_result($result);

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

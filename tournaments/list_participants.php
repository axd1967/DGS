<?php
/*
Dragon Go Server
Copyright (C) 2001-2009  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Tournament";

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/std_classes.php' );
require_once( 'include/countries.php' );
require_once( 'include/table_columns.php' );
require_once( 'include/filter.php' );
require_once( 'include/filterlib_country.php' );
require_once( 'include/rating.php' );
require_once( 'include/classlib_profile.php' );
require_once( 'include/classlib_userconfig.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_participant.php' );

$ThePage = new Page('TournamentParticipantList');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_participants');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = Tournament::load_tournament( $tid );
   if( is_null($tourney) )
      error('unknown_tournament', "list_participants.find_tournament($tid)");

   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );

   // TD has different view of table-column-set
   $cfg_tblcols = ConfigTableColumns::load_config( $my_id,
         ($allow_edit_tourney) ? CFGCOLS_TD_TOURNAMENT_PARTICIPANTS : CFGCOLS_TOURNAMENT_PARTICIPANTS );

   $page = "list_participants.php?";

   // config for filters
   $status_filter_array = array( T_('All') => '' );
   foreach( TournamentParticipant::getStatusText() as $status => $text )
      $status_filter_array[$text] = "TP.Status='$status'";

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_TOURNAMENT_PARTICIPANTS );
   $tpfilter = new SearchFilter( '', $search_profile );
   $tptable = new Table( 'tournament', $page, $cfg_tblcols, '', TABLE_ROW_NUM|TABLE_ROWS_NAVI );
   $tptable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $tpfilter->add_filter( 2, 'Text', 'TPP.Name', true);
   $tpfilter->add_filter( 3, 'Text', 'TPP.Handle', true);
   $tpfilter->add_filter( 4, 'Country', 'TPP.Country', false,
         array( FC_HIDE => 1 ));
   $tpfilter->add_filter( 5, 'Rating', 'TPP.Rating2', true);
   if( $allow_edit_tourney )
      $tpfilter->add_filter( 8, 'Selection', $status_filter_array, true);
   $tpfilter->add_filter(10, 'Numeric', 'TP.StartRound', true,
         array( FC_SIZE => 4 ));
   $tpfilter->add_filter(11, 'Rating', 'TP.Rating', true);
   $tpfilter->add_filter(12, 'RelativeDate', 'TP.Created', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ));
   $tpfilter->add_filter(13, 'RelativeDate', 'TP.Lastchanged', true);
   $tpfilter->init();

   // init table
   $tptable->register_filter( $tpfilter );
   $tptable->add_or_del_column();

   // page vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'tid', $tid );
   $tptable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   if( $allow_edit_tourney )
      $tptable->add_tablehead( 1, T_('Actions#T_reg'), 'Image', TABLE_NO_HIDE, '');
   $tptable->add_tablehead( 2, T_('Name#T_reg'), 'User', 0, 'Name+');
   $tptable->add_tablehead( 3, T_('Userid#T_reg'), 'User', TABLE_NO_HIDE, 'Handle+');
   $tptable->add_tablehead( 4, T_('Country#T_reg'), 'Image', 0, 'Country+');
   $tptable->add_tablehead( 5, T_('Current Rating#T_reg'), 'Rating', 0, 'Rating2-');
   $tptable->add_tablehead( 6, T_('Comment#T_reg'), null, TABLE_NO_SORT);
   $tptable->add_tablehead( 7, T_('Reg ID#T_reg'), 'Number', 0, 'ID+');
   $tptable->add_tablehead( 8, T_('Status#T_reg'), 'Enum', ($allow_edit_tourney ? TABLE_NO_HIDE : 0), 'Status+');
   if( $allow_edit_tourney )
      $tptable->add_tablehead( 9, T_('Flags#T_reg'), 'Enum', 0, 'Flags+');
   $tptable->add_tablehead(10, T_('Round#T_reg'), 'Number', 0, 'StartRound-');
   $tptable->add_tablehead(11, T_('Tournament Rating#T_reg'), 'Rating', 0, 'Rating-');
   $tptable->add_tablehead(12, T_('Registered#T_reg'), 'Date', 0, 'Created+');
   $tptable->add_tablehead(13, T_('Updated#T_reg'), 'Date', 0, 'Lastchanged-');
   if( $allow_edit_tourney )
      $tptable->add_tablehead(14, T_('Messages#T_reg'), 'Enum', TABLE_NO_SORT);

   $tptable->set_default_sort( 7 ); //on Reg-ID

   $iterator = new ListIterator( 'TournamentParticipants',
         $tptable->get_query(),
         $tptable->current_order_string(),
         $tptable->current_limit_string() );
   $iterator = TournamentParticipant::load_tournament_participants( $iterator, $tid );


   $pagetitle = sprintf( T_('Tournament Participants #%d'), $tid );
   $title = sprintf( T_('Tournament Participants of [%s]'), $tourney->Title );
   start_page($pagetitle, true, $logged_in, $player_row );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   echo "<h3 class=Header>". $title . "</h3>\n";


   $show_rows = $tptable->compute_show_rows( $iterator->ResultRows );
   $tptable->set_found_rows( mysql_found_rows('Tournament.list_participants.found_rows') );

   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $tp, $orow ) = $arr_item;
      $rid = $tp->ID; // reg-id
      $uid = $tp->uid;
      $row_str = array();

      if( $allow_edit_tourney && $tptable->Is_Column_Displayed[1] )
      {
         $msg_subj = urlencode( sprintf( T_('[Tournament #%d]'), $tid ));
         $msg_text = urlencode( sprintf(
            T_("Registration info for %s:\n\nEdit subject and text"),
            "<tourney $tid>" ));
         $links = anchor( $base_path."message.php?mode=NewMessage".URI_AMP
                     . "uid=$uid".URI_AMP."subject=$msg_subj".URI_AMP."message=$msg_text",
               image( $base_path.'images/send.gif', 'M'),
               T_('Send a message'), 'class=ButIcon');

         $links .= SMALL_SPACING;
         $links .= anchor( $base_path."tournaments/edit_participant.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."rid=$rid",
               image( $base_path.'images/edit.gif', 'E'),
               T_('Edit user registration'), 'class=ButIcon');

         $row_str[1] = $links;
      }

      if( $tptable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = user_reference( REF_LINK, 1, '', $uid, $tp->User->Name, '');
      if( $tptable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $tp->User->Handle, '');
      if( $tptable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = getCountryFlagImage( $tp->User->Country );
      if( $tptable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = echo_rating($tp->User->Rating, true, $uid);
      if( $tptable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = $tp->Comment;
      if( $tptable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = $rid;
      if( $tptable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = TournamentParticipant::getStatusText( $tp->Status );
      if( $allow_edit_tourney && $tptable->Is_Column_Displayed[ 9] )
         $row_str[ 9] = TournamentParticipant::getFlagsText( $tp->Flags );
      if( $tptable->Is_Column_Displayed[10] )
         $row_str[10] = $tp->StartRound;
      if( $tptable->Is_Column_Displayed[11] )
      {
         $rating_str = echo_rating($tp->Rating, true, $uid);
         if( $rating_str == '' )
            $rating_str = echo_rating( $tp->User->Rating, true, $uid);
         $row_str[11] = $rating_str;
      }
      if( $tptable->Is_Column_Displayed[12] )
         $row_str[12] = ($tp->Created > 0) ? date(DATE_FMT2, $tp->Created) : '';
      if( $tptable->Is_Column_Displayed[13] )
         $row_str[13] = ($tp->Lastchanged > 0) ? date(DATE_FMT2, $tp->Lastchanged) : '';
      if( $allow_edit_tourney && $tptable->Is_Column_Displayed[14] )
      {
         $msgs = array();
         if( (string)$tp->Notes != '' ) $msgs[] = T_('Notes#tmsg');
         if( (string)$tp->UserMessage != '' ) $msgs[] = T_('UserMsg#tmsg');
         if( (string)$tp->AdminMessage != '' ) $msgs[] = T_('AdmMsg#tmsg');
         $row_str[14] = implode(', ', $msgs);
      }

      $tptable->add_row( $row_str );
   }

   // print table
   echo $tptable->make_table();


   $menu_array = array();
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   $reg_user_str = TournamentParticipant::getLinkTextRegistration( $tourney, $my_id );
   if( $reg_user_str )
      $menu_array[$reg_user_str] = "tournaments/register.php?tid=$tid";
   if( $allow_edit_tourney )
      $menu_array[T_('Edit participants')] =
         array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' ); # for TD

   end_page(@$menu_array);
}

?>

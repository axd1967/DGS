<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once( 'include/table_columns.php' );
require_once( 'include/filter.php' );
require_once( 'include/rating.php' );
require_once( 'include/classlib_profile.php' );
require_once( 'include/classlib_userconfig.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_director.php' );

$GLOBALS['ThePage'] = new Page('TournamentDirectorList');

{
   #$DEBUG_SQL = true;
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in', 'Tournament.list_directors');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.list_directors');
   $my_id = $player_row['ID'];

   $tid = (int) @$_REQUEST['tid'];
   $tourney = Tournament::load_tournament( $tid );
   if( is_null($tourney) )
      error('unknown_tournament', "list_directors.find_tournament($tid)");
   $allow_edit = $tourney->allow_edit_directors($my_id);

   $page = "list_directors.php?";

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_TOURNAMENT_DIRECTORS );
   $tdfilter = new SearchFilter( '', $search_profile );
   $tdtable = new Table( 'tournament', $page, null );
   $tdtable->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $tdfilter->add_filter(3, 'Rating',  'P.Rating2', true);
   $tdfilter->add_filter(4, 'RelativeDate', 'TDPL.Lastaccess', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS ) );
   $tdfilter->init();

   // init table
   $tdtable->register_filter( $tdfilter );
   $tdtable->add_or_del_column();

   // page vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'tid', $tid );
   $tdtable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $tdtable->add_tablehead( 1, T_('Actions#T_dir'), 'ImagesLeft', TABLE_NO_HIDE, '');
   $tdtable->add_tablehead( 2, T_('Tournament director#T_dir'), 'User', 0, 'Name+');
   $tdtable->add_tablehead( 3, T_('Rating#T_dir'), 'Rating', 0, 'Rating2-');
   $tdtable->add_tablehead( 4, T_('Last access#T_dir'), 'Date', 0, 'Lastaccess-');
   $tdtable->add_tablehead( 6, T_('Admin Flags#T_dir'), '', TABLE_NO_SORT);
   $tdtable->add_tablehead( 5, T_('Comment#T_dir'), '', TABLE_NO_SORT);

   $tdtable->set_default_sort( 4 ); //on Lastaccess

   $iterator = new ListIterator( 'TournamentDirectors',
         $tdtable->get_query(),
         $tdtable->current_order_string(),
         $tdtable->current_limit_string() );
   $iterator = TournamentDirector::load_tournament_directors( $iterator, $tid );
   $allow_del1 = TournamentDirector::assert_min_directors( $tid, $tourney->Status, false,
         $iterator->getItemCount() );


   $pagetitle = sprintf( T_('Tournament Directors #%d'), $tid );
   $title = sprintf( T_('Tournament Directors of [%s]'), $tourney->Title );
   start_page($pagetitle, true, $logged_in, $player_row,
               button_style($player_row['Button']) );

   if( $DEBUG_SQL ) echo "QUERY: " . make_html_safe( $iterator->Query );
   echo "<h3 class=Header>". $title . "</h3>\n";

   $tform = new Form( 'tournament', $page, FORM_POST );
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Owner#tourney'),
         'TEXT',        ( ($tourney->Owner_ID) ? user_reference( REF_LINK, 1, '', $tourney->Owner_ID ) : NO_VALUE ) ));
   $tform->echo_string();
   echo "<br>\n";


   $show_rows = $tdtable->compute_show_rows( $iterator->ResultRows );
   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $director, $orow ) = $arr_item;
      $uid = $director->uid;
      $row_str = array();

      $userURL = "userinfo.php?uid=$uid";
      if( $tdtable->Is_Column_Displayed[1] )
      {
         $msg_subj = urlencode( sprintf( T_('[Tournament #%d]'), $tid ));
         $msg_text = urlencode( sprintf(
            T_("Request for %s:\n\nEdit subject and text to match your request "
               . "but please keep the reference to the tournament.#tourney"),
            "<tourney $tid>" ));
         $links = array();
         $links[] = anchor( $base_path."message.php?mode=NewMessage".URI_AMP."uid=$uid".URI_AMP."subject=$msg_subj" .
                            URI_AMP."message=$msg_text",
            image( $base_path.'images/send.gif', 'M', '', 'class="Action"' ), T_('Send a message'));
         if( $allow_edit || $my_id == $uid )
            $links[] = anchor( $base_path."tournaments/edit_director.php?tid=$tid".URI_AMP."uid=$uid",
               image( $base_path.'images/edit.gif', 'E', '', 'class="Action"' ), T_('Edit tournament director'));
         if( $allow_edit )
            $links[] = anchor( $base_path."tournaments/edit_director.php?tid=$tid".URI_AMP."uid=$uid".URI_AMP."td_delete=1",
               image( $base_path.'images/trashcan.gif', 'X', '', 'class="Action"' ), T_('Remove tournament director'));
         $row_str[1] = implode(' ', $links);
      }
      if( $tdtable->Is_Column_Displayed[2] )
         $row_str[2] = $director->User->user_reference();
      if( $tdtable->Is_Column_Displayed[3] )
         $row_str[3] = echo_rating( $director->User->Rating, true, $uid );
      if( $tdtable->Is_Column_Displayed[4] )
         $row_str[4] = ($director->User->Lastaccess > 0) ? date(DATE_FMT2, $director->User->Lastaccess) : '';
      if( $tdtable->Is_Column_Displayed[5] )
         $row_str[5] = make_html_safe( $director->Comment, true );
      if( $tdtable->Is_Column_Displayed[6] )
         $row_str[6] = span('TInfo', $director->formatFlags());

      $tdtable->add_row( $row_str );
   }

   // print table
   echo $tdtable->make_table();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_edit )
      $menu_array[T_('Add tournament director')] =
         array( 'url' => "tournaments/edit_director.php?tid=$tid", 'class' => 'TAdmin' );
   if( $tourney->allow_edit_tournaments($my_id) )
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}

?>

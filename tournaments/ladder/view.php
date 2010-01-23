<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Jens-Uwe Gaspar

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

chdir('../..');
require_once 'include/std_functions.php';
require_once 'include/table_columns.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_userconfig.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_ladder.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.view');
   $my_id = $player_row['ID'];


   $tid = (int)@$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.ladder_view.find_tournament($tid)");

   $cfg_tblcols = ConfigTableColumns::load_config( $my_id, CFGCOLS_TOURNAMENT_LADDER_VIEW );

   // init table
   $page = "view.php?";
   $ltable = new Table( 'tournament_ladder', $page, $cfg_tblcols, '',
      TABLE_NO_SORT|TABLE_NO_PAGE|TABLE_NO_SIZE|TABLE_ROWS_NAVI );
   $ltable->use_show_rows(false);
   $ltable->add_or_del_column();

   // page vars
   $page_vars = new RequestParameters();
   $page_vars->add_entry( 'tid', $tid );
   $ltable->add_external_parameters( $page_vars, true ); // add as hiddens

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ltable->add_tablehead( 1, T_('Rank#T_ladder'), 'Number', TABLE_NO_HIDE );
   $ltable->add_tablehead( 2, T_('Best Rank#T_ladder'), 'Number', 0 );
   $ltable->add_tablehead( 3, T_('Name#T_ladder'), 'User', 0 );
   $ltable->add_tablehead( 4, T_('Userid#T_ladder'), 'User', TABLE_NO_HIDE );
   $ltable->add_tablehead( 5, T_('Country#T_ladder'), 'Image', 0 );
   $ltable->add_tablehead( 6, T_('Current Rating#T_ladder'), 'Rating', 0 );
   $ltable->add_tablehead( 7, T_('Action#T_ladder'), 'Image', TABLE_NO_HIDE );
   $ltable->add_tablehead( 8, T_('Challenges#T_ladder'), '', TABLE_NO_HIDE );
   $ltable->add_tablehead( 9, T_('Rank Changed#T_ladder'), 'Date', 0 );
   $ltable->add_tablehead(10, T_('Started#T_ladder'), 'Date', 0 );

   $iterator = new ListIterator( 'Tournament.ladder_view',
      $ltable->get_query(), 'ORDER BY Rank ASC' );
   $iterator->addQuerySQLMerge( new QuerySQL(
         SQLP_FIELDS, 'TLP.ID AS TLP_ID', 'TLP.Name AS TLP_Name', 'TLP.Handle AS TLP_Handle',
                      'TLP.Country AS TLP_Country', 'TLP.Rating2 AS TLP_Rating2',
         SQLP_FROM,   'INNER JOIN Players AS TLP ON TLP.ID=TL.uid'
      ));
   $iterator = TournamentLadder::load_tournament_ladder( $iterator, $tid );

   $show_rows = $ltable->compute_show_rows( $iterator->ResultRows );
   $ltable->set_found_rows( mysql_found_rows('Tournament.ladder_view.found_rows') );


   $title = sprintf( T_('Tournament-Ladder #%s'), $tid );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h2 class=Header>", $tourney->build_info(2), "</h2>\n";

   while( list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $tl, $orow ) = $arr_item;
      $uid = $tl->uid;
      $user = User::new_from_row($orow, 'TLP_');

      $row_str = array();

      if( $ltable->Is_Column_Displayed[ 1] )
         $row_str[ 1] = $tl->Rank . '.';
      if( $ltable->Is_Column_Displayed[ 2] )
         $row_str[ 2] = $tl->BestRank . '.';
      if( $ltable->Is_Column_Displayed[ 3] )
         $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
      if( $ltable->Is_Column_Displayed[ 4] )
         $row_str[ 4] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
      if( $ltable->Is_Column_Displayed[ 5] )
         $row_str[ 5] = getCountryFlagImage( $user->Country );
      if( $ltable->Is_Column_Displayed[ 6] )
         $row_str[ 6] = echo_rating( $user->Rating, true, $uid);
      if( $ltable->Is_Column_Displayed[ 7] )
         $row_str[ 7] = ''; //TODO actions
      if( $ltable->Is_Column_Displayed[ 8] )
         $row_str[ 8] = ''; //TODO game-list
      if( $ltable->Is_Column_Displayed[ 9] )
         $row_str[ 9] = ($tl->RankChanged > 0) ? date(DATE_FMT2, $tl->RankChanged) : '';
      if( $ltable->Is_Column_Displayed[10] )
         $row_str[10] = ($tl->Created > 0) ? date(DATE_FMT2, $tl->Created) : '';

      $ltable->add_row( $row_str );
   }

   // print table
   $ltable->echo_table();


   $menu_array = array();
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   if( $tourney->allow_edit_tournaments($my_id) )
      $menu_array[T_('Manage this tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}
?>

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
require_once 'include/form_functions.php';
require_once 'include/countries.php';
require_once 'include/rating.php';
require_once 'include/classlib_user.php';
require_once 'include/classlib_userconfig.php';
require_once 'include/time_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_ladder.php';
require_once 'tournaments/include/tournament_ladder_props.php';
require_once 'tournaments/include/tournament_games.php';

$GLOBALS['ThePage'] = new Page('TournamentLadderView');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.ladder.view');
   $my_id = $player_row['ID'];

/* Actual REQUEST calls used
     tid=                           : view T-ladder
     tid=&admin=1                   : admin-mode on T-ladder
     tid=&admin=1&ta_updrank&rid=&new_rank= : change rank of user given by rid
*/

   $tid = (int)@$_REQUEST['tid'];
   $rid = (int)@$_REQUEST['rid'];
   $admin_mode = (bool)@$_REQUEST['admin'];
   $new_rank = (int)@$_REQUEST['new_rank'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament($tid);
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.ladder_view.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( $admin_mode && !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.ladder_view.admin_mode($tid,$my_id)");

   $tl_props = TournamentLadderProps::load_tournament_ladder_props($tid);
   if( is_null($tl_props) )
      error('bad_tournament', "Tournament.ladder_view.ladder_props($tid,$my_id)");

   $errors = $tstatus->check_view_status( TournamentLadder::get_view_ladder_status($allow_edit_tourney) );
   $allow_view = ( count($errors) == 0 );

   $tl_user = null;
   if( $allow_view )
   {
      if( $admin_mode && @$_REQUEST['ta_updrank'] ) // move rank (by TD)
      {
         $prep_errors = array();
         $tladder = prepare_update_rank( $tid, $rid, $new_rank, $prep_errors );
         if( count($prep_errors) )
            $errors = array_merge( $errors, $prep_errors );
         else
         {
            $old_rank = $tladder->Rank;
            if( $tladder->change_user_rank($new_rank) )
            {
               $sys_msg = urlencode( sprintf( T_('Moved selected user from rank [%s] to [%s]!#tourney'),
                                             $old_rank, $new_rank ));
               jump_to("tournaments/ladder/view.php?tid=$tid".URI_AMP."admin=1".URI_AMP."rid=$rid"
                     . URI_AMP."sysmsg=$sys_msg");
            }
         }
      }

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
      if( $admin_mode )
         $page_vars->add_entry( 'admin', ($admin_mode ? 1 : 0) );
      $ltable->add_external_parameters( $page_vars, true ); // add as hiddens

      // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
      $ltable->add_tablehead( 1, T_('Rank#T_ladder'), 'Number', TABLE_NO_HIDE );
      $ltable->add_tablehead( 2, T_('Best Rank#T_ladder'), 'Number', 0 );
      $ltable->add_tablehead( 3, T_('Name#T_ladder'), 'User', 0 );
      $ltable->add_tablehead( 4, T_('Userid#T_ladder'), 'User', TABLE_NO_HIDE );
      $ltable->add_tablehead( 5, T_('Country#T_ladder'), 'Image', 0 );
      $ltable->add_tablehead( 6, T_('Current Rating#T_ladder'), 'Rating', 0 );
      $ltable->add_tablehead( 7, T_('Action#T_ladder'), '', TABLE_NO_HIDE );
      $ltable->add_tablehead( 8, T_('Challenges#T_ladder'), '', TABLE_NO_HIDE );
      $ltable->add_tablehead( 9, T_('Rank Changed#T_ladder'), 'Date', 0 );
      $ltable->add_tablehead(10, T_('Rank Kept#T_ladder'), '', 0 );
      $ltable->add_tablehead(11, T_('Started#T_ladder'), 'Date', 0 );

      $iterator = new ListIterator( 'Tournament.ladder_view.load_ladder',
         $ltable->get_query(), 'ORDER BY Rank ASC' );
      $iterator->addIndex( 'Rank', 'uid' );
      $iterator->addQuerySQLMerge( new QuerySQL(
            SQLP_FIELDS, 'TLP.ID AS TLP_ID', 'TLP.Name AS TLP_Name', 'TLP.Handle AS TLP_Handle',
                         'TLP.Country AS TLP_Country', 'TLP.Rating2 AS TLP_Rating2',
            SQLP_FROM,   'INNER JOIN Players AS TLP ON TLP.ID=TL.uid'
         ));
      $iterator = TournamentLadder::load_tournament_ladder( $iterator, $tid );

      $show_rows = $ltable->compute_show_rows( $iterator->ResultRows );
      $ltable->set_found_rows( mysql_found_rows('Tournament.ladder_view.found_rows') );

      $tg_iterator = new ListIterator( 'Tournament.ladder_view.load_tgames' );
      $tg_iterator = TournamentGames::load_tournament_games( $tg_iterator, $tid, TG_STATUS_PLAY );

      // add ladder-info (challenge-range)
      $tl_user = $tl_props->fill_ladder_challenge_range( $iterator, $my_id );
      $tl_props->fill_ladder_running_games( $iterator, $tg_iterator, $my_id );

      if( $admin_mode )
         $ltable->set_extend_table_form_function( 'admin_edit_ladder_extend_table_form' ); //defined below
   }//allow-view


   $title = sprintf( T_('Tournament-Ladder #%s'), $tid );
   start_page( $title, true, $logged_in, $player_row );
   echo "<h2 class=Header>", $tourney->build_info(2), "</h2>\n";

   if( $allow_view )
   {
      $tform = $ltable->make_table_form();
      while( list(,$arr_item) = $iterator->getListIterator() )
      {
         list( $tladder, $orow ) = $arr_item;
         $uid = $tladder->uid;
         $user = User::new_from_row($orow, 'TLP_');
         $is_mine = ( $my_id == $uid );

         $row_str = array();

         if( $ltable->Is_Column_Displayed[ 1] )
            $row_str[ 1] = $tladder->Rank . '.';
         if( $ltable->Is_Column_Displayed[ 2] )
            $row_str[ 2] = ($tladder->BestRank > 0) ? $tladder->BestRank . '.' : '';
         if( $ltable->Is_Column_Displayed[ 3] )
            $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $user->Name, '');
         if( $ltable->Is_Column_Displayed[ 4] )
            $row_str[ 4] = user_reference( REF_LINK, 1, '', $uid, $user->Handle, '');
         if( $ltable->Is_Column_Displayed[ 5] )
            $row_str[ 5] = getCountryFlagImage( $user->Country );
         if( $ltable->Is_Column_Displayed[ 6] )
            $row_str[ 6] = echo_rating( $user->Rating, true, $uid);
         if( $ltable->Is_Column_Displayed[ 7] ) // actions
         {
            if( $admin_mode )
            {
               $row_str[7] =
                  anchor( $base_path."tournaments/ladder/admin.php?tid=$tid".URI_AMP."uid=$uid",
                          image( $base_path.'images/edit.gif', 'E'),
                          T_('Admin user'), 'class=ButIcon')
                  . SMALL_SPACING
                  . $tform->print_insert_radio_buttonsx(
                        'rid', array( $tladder->rid => '' ), ($rid == $tladder->rid) );
            }
            elseif( $is_mine )
               $row_str[7] = span('TourneyUser', T_('This is you#T_ladder') );
            else
            {
               $row_str[7] = '';
               if( $tladder->AllowChallenge )
                  $row_str[7] = sprintf( '[%s]',
                     anchor( $base_path."tournaments/ladder/challenge.php?tid=$tid".URI_AMP."rid={$tladder->rid}",
                             T_('Challenge this user') ));
            }
         }
         if( $ltable->Is_Column_Displayed[ 8] )
            $row_str[ 8] = implode(' ', $tladder->build_linked_running_games());
         if( $ltable->Is_Column_Displayed[ 9] )
            $row_str[ 9] = ($tladder->RankChanged > 0) ? date(DATE_FMT2, $tladder->RankChanged) : '';
         if( $ltable->Is_Column_Displayed[10] )
            $row_str[10] = $tladder->build_rank_kept();
         if( $ltable->Is_Column_Displayed[11] )
            $row_str[11] = ($tladder->Created > 0) ? date(DATE_FMT2, $tladder->Created) : '';

         if( $is_mine )
            $row_str['extra_class'] = 'TourneyUser';
         $ltable->add_row( $row_str );
      }
   }//allow-view

   if( $admin_mode )
      echo '<h3 class="Header">', T_('Edit Ladder'), "</h3>\n";

   if( count($errors) )
   {
      echo "<table><tr>",
         TournamentUtils::buildErrorListString( T_('There are some errors'), $errors, 1 ),
         "</tr></table>\n";
   }

   if( $allow_view )
   {
      if( !is_null($tl_user) && !$admin_mode )
      {
         echo sprintf( T_('Your current rank is #%s.'), $tl_user->Rank );
         if( $tl_user->BestRank > 0 )
            echo MED_SPACING, sprintf( T_('Your best rank is #%s.'), $tl_user->BestRank );
         echo "<br>\n";
      }

      $ltable->echo_table();
   }

   if( !is_null($tl_props) )
   {
      $tt_notes = $tl_props->build_notes_props();
      echo_notes( 'ttprops', $tt_notes[0], $tt_notes[1] );
   }


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_view )
   {
      $menu_array[T_('View Ladder')] = "tournaments/ladder/view.php?tid=$tid";
      if( !is_null($tl_user) && !$admin_mode )
         $menu_array[T_('Retreat from Ladder')] = "tournaments/ladder/retreat.php?tid=$tid";
   }
   if( $allow_edit_tourney )
   {
      if( $admin_mode )
         $menu_array[T_('Tournament participants')] = "tournaments/list_participants.php?tid=$tid";
      $menu_array[T_('Edit Ladder')] =
         array( 'url' => "tournaments/ladder/view.php?tid=$tid".URI_AMP."admin=1", 'class' => 'TAdmin' );
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}


// return TournamentLadder-object; maybe null
function prepare_update_rank( $tid, $rid, $new_rank, &$errors )
{
   $tladder = null;
   if( @$_REQUEST['ta_updrank'] && $rid <= 0 )
      $errors[] = T_('Missing user selection for rank change');
   else
   {
      $tladder = TournamentLadder::load_tournament_ladder_by_user($tid, 0, $rid);
      if( is_null($tladder) )
         error('invalid_args', "Tournament.ladder_view.find_ladder_user($tid,$rid)");
   }

   $max_rank = TournamentLadder::load_max_rank($tid);
   if( $new_rank < 1 || $new_rank > $max_rank )
      $errors[] = sprintf( T_('New rank [%s] is out of valid range [%s..%s]'), $new_rank, 1, $max_rank );

   if( !is_null($tladder) && $new_rank == $tladder->Rank )
      $errors[] = sprintf( T_('No change in rank [%s] for selected user'), $tladder->Rank );

   return $tladder;
}

// callback-func for admin-edit-ladder Table-form adding form-elements below table
function admin_edit_ladder_extend_table_form( &$table, &$form )
{
   $result = $form->print_insert_text_input( 'new_rank', 6, 6, get_request_arg('new_rank') );
   $result .= $form->print_insert_submit_button( 'ta_updrank', T_('Update rank') );
   return $result;
}
?>

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
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_participant.php';
require_once 'tournaments/include/tournament_pool.php';
require_once 'tournaments/include/tournament_round.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentPoolDefine');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.define_pools');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

   $page = "define_pools.php";

/* Actual REQUEST calls used:
     tid=&round=              : define tournament pools
     t_preview&tid=&round=    : preview for tournament-pool-save
     t_save&tid=&round=       : update (replace) tournament-round pool-info in database
     t_suggest&tid=&round=    : show suggestions for pool-info
     t_cancel&tid=&round=     : cancel editing, reload page
     ...&addpool=1            : add one pool (if possible)
     ...&delpool=1            : delete last pool (if possible)
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $round = (int) @$_REQUEST['round'];
   if( $round < 0 ) $round = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.define_pools.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);
   if( !$ttype->need_rounds )
      error('tournament_edit_rounds_not_allowed', "Tournament.define_pools.need_rounds($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.define_pools.edit_tournament($tid,$my_id)");

   // load existing T-round
   if( $round < 1 )
      $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.define_pools.find_tournament_round($tid,$round,$my_id)");

   if( @$_REQUEST['t_cancel'] )
      jump_to("tournaments/roundrobin/define_pools.php?tid=$tid".URI_AMP."round=$round");


   // init
   $old_poolcount = $tround->Pools;
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_tournament_status() );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   $tp_counts = TournamentParticipant::count_tournament_participants( $tid, TP_STATUS_REGISTER );
   $reg_count = (int)@$tp_counts[TPCOUNT_STATUS_ALL];
   $min_pool_count = min( TROUND_MAX_POOLCOUNT, TournamentUtils::calc_pool_count($reg_count, $tround->MaxPoolSize) );
   $max_pool_count = min( TROUND_MAX_POOLCOUNT, TournamentUtils::calc_pool_count($reg_count, $tround->MinPoolSize) );

   // check + parse edit-form (notes)
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tround, $reg_count );
   $errors = array_merge( $errors, $input_errors );

   $adjust_pool = '';
   if( @$_REQUEST['t_save'] || @$_REQUEST['t_preview'] )
   {
      if( count($edits) == 0 ) // no edits (add/del-pools checkbox is no edit)
      {
         if( $vars['addpool'] )
         {
            $adjust_pool = '+1';
            $tround->Pools++;
         }
         elseif( $vars['delpool'] )
         {
            $adjust_pool = '-1';
            if( TournamentPool::exists_tournament_pool($tid, $round, $tround->Pools) )
               $errors[] = sprintf( T_('Last Pool [%s] must be empty to allow deletion!'), $tround->Pools );
            else
               $tround->Pools--;
         }
      }
      else
      {
         if( TournamentPool::exists_tournament_pool($tid, $round) )
            $errors[] = T_('Pool parameters can only be directly changed if all pools have been removed!');
      }
   }

   // ---------- Process actions ------------------------------------------------

   $ttable = null;
   if( @$_REQUEST['t_suggest'] || @$_REQUEST['t_save'] || @$_REQUEST['t_preview'] )
      $ttable = make_suggestions_table( $tround, $reg_count, $errors, $tround->PoolSize, $tround->Pools );

   // save tournament-round-object with values from edit-form
   if( @$_REQUEST['t_save'] && count($errors) == 0 )
   {
      $tround->update();
      jump_to("tournaments/roundrobin/define_pools.php?tid=$tid".URI_AMP."round=$round".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament round saved!')) );
   }

   // --------------- Tournament-Pools EDIT form --------------------


   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );
   $tform->add_hidden( 'round', $round );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament Round#tround'),
         'TEXT',        $tourney->formatRound(), ));
   TournamentUtils::show_tournament_flags( $tform, $tourney );
   $tform->add_row( array(
         'DESCRIPTION', T_('Round Status#tround'),
         'TEXT',        TournamentRound::getStatusText($tround->Status), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Registered users#tround'),
         'TEXT',        $reg_count, ));

   if( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   $tform->add_row( array( 'HR' ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Base Pool Size#tround'),
         'TEXTINPUT',   'pool_size', 4, 3, $vars['pool_size'],
         'TEXT',        TournamentUtils::build_range_text( $tround->MinPoolSize, $tround->MaxPoolSize ), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Pool Count#tround'),
         'TEXTINPUT',   'pool_count', 4, 4, $vars['pool_count'],
         'TEXT',        ( $adjust_pool ? "<b>$adjust_pool</b>" . SMALL_SPACING : ''),
         'TEXT',        TournamentUtils::build_range_text( $min_pool_count, $max_pool_count ), ));
   if( $old_poolcount < $max_pool_count )
      $tform->add_row( array(
            'TAB',
            'CHECKBOX', 'addpool', 1, T_('Add Pool (possible if no violation)'), $vars['addpool'], ));
   if( $old_poolcount > $min_pool_count - 1 )
      $tform->add_row( array(
            'TAB',
            'CHECKBOX', 'delpool', 1, T_('Delete last Pool (possible if pool empty and no violation)'), $vars['delpool'], ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_save', T_('Save pools'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_preview', T_('Preview'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_suggest', T_('Suggest Pool Parameters'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_cancel', T_('Cancel'), ));


   $title = T_('Tournament Pools Setup');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   if( !is_null($ttable) )
   {
      section( 'suggestions', T_('Pool Suggestions') );
      echo "<p>\n",
         sprintf( T_('The table shows sample distributions for the %s registered users (of round #%s) for allowed pool-sizes'),
                  $reg_count, $tourney->CurrentRound ),
         ":<br>\n",
         $ttable->make_table(),
         "<br>\n";

      echo_notes( 'definetpoolsTable', T_('Legend#T_poolsugg'), array(
         T_('Recommended distributions are the suggestions with a minimal slice, pool count and remaining users.'),
         T_('You are free to use other combinations if they are valid.'),
         T_('The marked first line may contain the distribution for the currently entered parameters (not evaluated).'),
         null,
         sprintf( T_('%s = expected min. and max. number of games for this round for given pool-size#T_poolsugg'), T_('Games#T_poolsugg') ),
         sprintf( T_('%s = number of remaining users that do not make a full pool for given pool-size#T_poolsugg'), T_('Remain#T_poolsugg') ),
         sprintf( T_('%s = number of users, that need to be sliced away from other pools to reach the min. pool-size for last incomplete pool#T_poolsugg'), T_('Slice#T_poolsugg') ),
         sprintf( T_('%s = number of users, that are available to fill up the last incomplete pool up to the given pool-size and max. pool-size#T_poolsugg'), T_('Space#T_poolsugg') ),
         ), false);
   }


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Define pools')] =
      array( 'url' => "tournaments/roundrobin/define_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Create pools')] =
      array( 'url' => "tournaments/roundrobin/create_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Edit pools')] =
      array( 'url' => "tournaments/roundrobin/edit_pools.php?tid=$tid", 'class' => 'TAdmin' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$trd )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['t_save'] || @$_REQUEST['t_preview'] || @$_REQUEST['t_suggest'] );

   // read from props or set defaults
   $vars = array(
      'pool_size'    => $trd->PoolSize,
      'pool_count'   => $trd->Pools,
      'addpool'      => 0,
      'delpool'      => 0,
   );

   // copy to determine edit-changes
   $old_vals = array_merge( array(), $vars );
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if( $is_posted )
   {
      foreach( array('addpool','delpool') as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   if( $is_posted )
   {
      global $min_pool_count, $max_pool_count;

      $new_value = $vars['pool_size'];
      if( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= $trd->MinPoolSize && $new_value <= $trd->MaxPoolSize )
         $trd->PoolSize = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for pool size in range %s.'),
            TournamentUtils::build_range_text($trd->MinPoolSize, $trd->MaxPoolSize) );

      $new_value = $vars['pool_count'];
      if( TournamentUtils::isNumberOrEmpty($new_value) && $new_value >= $min_pool_count && $new_value <= $max_pool_count )
         $trd->Pools = $new_value;
      else
         $errors[] = sprintf( T_('Expecting number for pool count in range %s.'),
            TournamentUtils::build_range_text( $min_pool_count, $max_pool_count ) );

      if( $vars['addpool'] && $vars['delpool'] )
         $errors[] = T_('Adding and deleting pool are mutual exclusive actions: Choose only one.');

      // determine edits
      if( $old_vals['pool_size'] != $trd->PoolSize ) $edits[] = T_('Pool-Size#edits');
      if( $old_vals['pool_count'] != $trd->Pools ) $edits[] = T_('Pool-Count#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

function make_suggestions_table( $tround, $reg_count, &$errors, $user_pool_size=0, $user_pool_count=0 )
{
   global $page;

   $table = new Table( 'TPoolSuggestions', $page, null, '',
      TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('Pool Size#T_poolsugg'), 'NumberC');
   $table->add_tablehead( 2, T_('Pools#T_poolsugg'), 'NumberC');
   $table->add_tablehead( 6, T_('Games#T_poolsugg'), 'Number');
   $table->add_tablehead( 3, T_('Remain#T_poolsugg'), 'Number');
   $table->add_tablehead( 4, T_('Slice#T_poolsugg'), 'Number');
   $table->add_tablehead( 5, T_('Space#T_poolsugg'), 'Number');
   $table->add_tablehead( 7, T_('Suggestion Note#T_poolsugg'), 'Note');

   $arr_check = array();
   if( $user_pool_size > 0 && $user_pool_count > 0 )
      $arr_check[] = array( $user_pool_size, $user_pool_count, /*suggestion*/false );
   for( $pool_size = $tround->MaxPoolSize; $pool_size >= $tround->MinPoolSize; $pool_size-- )
   {
      $pool_count = TournamentUtils::calc_pool_count( $reg_count, $pool_size );
      $arr_check[] = array( $pool_size, $pool_count, /*suggestion*/true );
   }

   $chall_games = 1;
   $arr_best = array();
   $row_idx = 0;
   foreach( $arr_check as $arr_item )
   {
      list( $pool_size, $pool_count, $suggest ) = $arr_item;

      $remaining_users = $reg_count % $pool_size;
      $slice_users = max( $tround->MinPoolSize - $remaining_users, 0 );
      $space_users = ($remaining_users > 0) ? $pool_size - $remaining_users : 0;
      $max_space_users = ($remaining_users > 0) ? $tround->MaxPoolSize - $remaining_users : 0;

      $full_pool_games = TournamentUtils::calc_pool_games( $pool_size, $chall_games );
      if( $remaining_users == 0 )
         $min_games = $max_games = $pool_count * $pool_games;
      else
      {
         // equally share users for min-games
         $slice_pools = $reg_count % $pool_count;
         $min_games = ( $pool_count - $slice_pools ) * $full_pool_games
                    + $slice_pools * TournamentUtils::calc_pool_games( $pool_size - 1, $chall_games );
         $max_games = '<' . ($pool_count * $full_pool_games);
      }

      $row_str = array(
         1 => $pool_size,
         2 => $pool_count,
         3 => $remaining_users,
         4 => $slice_users,
         5 => ($space_users == $max_space_users) ? $space_users : "$space_users - $max_space_users",
         6 => ($min_games == $max_games) ? $max_games : "$min_games - $max_games",
         7 => '',
      );

      $pool_users = (float)( $reg_count / $pool_count );
      if( $pool_users < $tround->MinPoolSize )
      {
         $row_str[7] = T_('Violates min. pool-size on slicing!#T_poolsugg');
         if( !$suggest )
            $errors[] = sprintf( T_('Pool count [%s] is too small, the min. pool size [%s] would be violated on slicing.'),
               $pool_count, $tround->MinPoolSize );
      }
      elseif( $pool_users > $tround->MaxPoolSize )
      {
         $row_str[7] = T_('Violates max. pool-size!#T_poolsugg');
         if( !$suggest )
            $errors[] = sprintf( T_('Pool count [%s] is too large, the max. pool size [%s] would be violated.'),
               $pool_count, $tround->MaxPoolSize );
      }
      elseif( $pool_size * $pool_count < $reg_count )
      {
         $row_str[7] = T_('Pool-size or count too small!#T_poolsugg');
         if( !$suggest )
            $errors[] = sprintf( T_('Either the pool size [%s] or pool count [%s] is too small to cover all %s registered users.'),
               $pool_size, $pool_count, $reg_count );
      }

      $extra_class = '';
      if( !$suggest )
         $extra_class .= ' UserChoice';
      if( $row_str[7] )
         $extra_class .= ' Violation';
      if( $extra_class )
         $row_str['extra_class'] = trim($extra_class);

      $table->add_row( $row_str );
      if( $suggest && !$row_str[7] )
         $arr_best[] = array( $slice_users, $pool_count, $remaining_users, $pool_size, false, $row_idx );
      $row_idx++;
   }

   // find best suggestion (min. slice-users / pool-count / remaining-users)
   usort( $arr_best, 'compare_suggestions' );
   $break_val = 0;
   foreach( $arr_best as $arr )
   {
      list( $slice_users, $pool_count, $rem_users, $pool_size, $has_violation, $row_idx ) = $arr;
      if( $has_violation )
         continue;
      $stop_val = ($slice_users+1) * $pool_count * ($rem_users+1);
      if( $break_val > 0 && $break_val != $stop_val )
         break;
      $break_val = $stop_val;
      $table->set_row_extra( $row_idx, array(
            7 => T_('Recommended distribution!#T_poolsugg'),
            'extra_class' => 'Best' ));
   }

   return $table;
}//make_suggestions_table

// $a/$b are arrays with key/vals: 0=slice_users, 1=pool-count, 2=remaining_users, 3=pool_size, 4=has_violation, 5=row_idx
function compare_suggestions( $a, $b )
{
   if( $a[0] == $b[0] ) // slice_users
   {
      if( $a[1] == $b[1] ) // pool_count
      {
         if( $a[2] == $b[2] ) // remaining_users
            return 0;
         else
            return ($a[2] < $b[2]) ? -1 : 1;
      }
      else
         return ($a[1] < $b[1]) ? -1 : 1;
   }
   else
      return ($a[0] < $b[0]) ? -1 : 1;
}
?>

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
require_once 'tournaments/include/tournament_round_status.php';
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
     tid=                     : define tournament pools
     t_preview&tid=           : preview for tournament-pool-save
     t_save&tid=              : update (replace) tournament-round pool-info in database
     t_suggest&tid=           : show suggestions for pool-info
     t_cancel&tid=            : cancel editing, reload page
     ...&addpool=1            : add one pool (if possible)
     ...&delpool=1            : delete last pool (if possible)
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

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
   $round = $tourney->CurrentRound;
   $tround = TournamentRound::load_tournament_round( $tid, $round );
   if( is_null($tround) )
      error('bad_tournament', "Tournament.define_pools.find_tournament_round($tid,$round,$my_id)");
   $trstatus = new TournamentRoundStatus( $tourney, $tround );

   if( @$_REQUEST['t_cancel'] )
      jump_to("tournaments/roundrobin/define_pools.php?tid=$tid".URI_AMP."round=$round");


   // init
   $old_poolcount = $tround->Pools;
   $errors = $tstatus->check_edit_status( TournamentPool::get_edit_tournament_status() );
   $errors = array_merge( $errors, $trstatus->check_edit_status( TROUND_STATUS_POOL ) );
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
      // NOTE: add/del-pools checkboxes don't show up under 'edits'
      if( $tround->Status == TROUND_STATUS_POOL && $old_poolcount > 0 && count($edits) == 0 )
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

   $arr_sugg_order = array(
      1 => T_('Pool Size#T_poolsugg_order'),
      2 => T_('Best Pool Distribution#T_poolsugg_order'),
   );
   $sugg_order_val = get_request_arg('sugg_order', 1);

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

   if( count($errors) )
   {
      $tform->add_row( array( 'HR' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
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

   if( $tround->Status == TROUND_STATUS_POOL && $old_poolcount > 0 )
   {
      if( $old_poolcount < $max_pool_count )
         $tform->add_row( array(
               'TAB',
               'CHECKBOX', 'addpool', 1, T_('Add Pool (possible if no violation)'), $vars['addpool'], ));
      if( $old_poolcount > $min_pool_count - 1 )
         $tform->add_row( array(
               'TAB',
               'CHECKBOX', 'delpool', 1, T_('Delete last Pool (possible if pool empty and no violation)'), $vars['delpool'], ));
   }

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
         'SUBMITBUTTON', 't_cancel', T_('Cancel'), ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'CELL', 2, '',
         'TEXT',        T_('Order by#T_poolsugg') . ' ',
         'SELECTBOX',   'sugg_order', 1, $arr_sugg_order, $sugg_order_val, false,
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_suggest', T_('Suggest Pool Parameters'), ));


   $title = T_('Tournament Pools Setup');
   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   if( !is_null($ttable) )
   {
      section( 'suggestions', T_('Pool Suggestions') );
      echo "<p>\n",
         sprintf( T_('The table shows sample distributions for the %s registered users of round #%s for allowed pool-sizes.'),
                  "<b>$reg_count</b>", $round ), ":<br>\n",
         $ttable->make_table(),
         "<br>\n";

      echo_notes( 'defineTPoolsTable', T_('Legend#T_poolsugg'), array(
         sprintf( T_('%s = maximal user capacity for chosen pool-size and count'), stripLF(T_("User\nCapacity#T_poolsugg")) ),
         sprintf( T_('%s = number of missing users to only have full pools'), stripLF(T_("User\nDiff#T_poolsugg")) ),
         sprintf( T_('%s = best equally shared round-robin pool distribution for all %s registered users'),
                  stripLF(T_("Best Pool\nDistribution#T_poolsugg")), $reg_count )
            . "\n" . T_('P x S : P = number of pools, S = pool-size; P1 + P2 = chosen pool-count'),
         sprintf( T_('%s = number of games for best pool distribution'), stripLF(T_("Games\nCount#T_poolsugg")) ),
         null,
         T_("Recommended distributions are the suggestions with a minimal user-diff\nand a minimum of pools, that are smaller than the chosen pool-size."),
         T_('You are free to use other combinations if they are valid.'),
         T_('The current parameter choice by tournament director is included and the line marked if valid.')
            . "\n"
            . T_('Eventually the pool-size is adjusted in order to find a good pool distribution.'),
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

function stripLF( $str )
{
   return str_replace( array( "\r\n", "\r", "\n" ), ' ', $str );
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

// return array( 0:$pool_size, 1:$pool_count, 2:$user_capacity, 3:$pool_size_base,
//               4:$pool_count_remain, 5:$pool_count_base, 6:$games_count, 7:$distribution,
//               8:$user_choice )
//     for given pool-size and pool-count
function calc_suggestion( $reg_count, $pool_size, $pool_count, $user_choice=0 )
{
   static $chall_games = 1; // later: 2 for double-round-robin
   $user_capacity = $pool_size * $pool_count;
   $pool_size_base = floor( $reg_count / $pool_count );
   $pool_count_remain = $reg_count - $pool_size_base * $pool_count;
   $pool_count_base = $pool_count - $pool_count_remain;
   $games_count = $pool_count_base * TournamentUtils::calc_pool_games( $pool_size_base, $chall_games )
              + $pool_count_remain * TournamentUtils::calc_pool_games( $pool_size_base + 1, $chall_games );

   if( $pool_count_remain > 0 )
      $distribution = sprintf( '%d x %d + %d x %d', //==reg_count, PC_base + PC_remain = pool_count
         $pool_count_base, $pool_size_base, $pool_count_remain, $pool_size_base + 1 );
   else
      $distribution = sprintf( '%d x %d', $pool_count_base, $pool_size_base );

   return array( $pool_size, $pool_count, $user_capacity, $pool_size_base,
      $pool_count_remain, $pool_count_base, $games_count, $distribution, $user_choice );
}

function make_suggestions_table( $tround, $reg_count, &$errors, $user_pool_size=0, $user_pool_count=0 )
{
   $arr_check = array();
   $arr_uniq = array();
   if( $user_pool_size > 0 && $user_pool_count > 0 )
   {
      $old_user_pool_size = $user_pool_size;
      $arr_sugg = calc_suggestion( $reg_count, $user_pool_size, $user_pool_count, 1 );
      if( $arr_sugg[4] > 0 && $arr_sugg[3] + 1 != $user_pool_size )
         $user_pool_size = $arr_sugg[3] + 1;
      elseif( $arr_sugg[4] == 0 && $arr_sugg[3] != $user_pool_size )
         $user_pool_size = $arr_sugg[3];
      if( $old_user_pool_size != $user_pool_size )
         $arr_sugg = calc_suggestion( $reg_count, $user_pool_size, $user_pool_count, 2 );
      $arr_uniq["$user_pool_size:$user_pool_count"] = 1;
      $arr_check[] = $arr_sugg;
   }
   for( $pool_size = $tround->MinPoolSize; $pool_size <= $tround->MaxPoolSize; $pool_size++ )
   {
      $pool_count = TournamentUtils::calc_pool_count( $reg_count, $pool_size );
      $key_uniq = "$pool_size:$pool_count";
      if( !isset($arr_uniq[$key_uniq]) )
         $arr_check[] = calc_suggestion( $reg_count, $pool_size, $pool_count );
      $arr_uniq[$key_uniq] = 1;
   }
   unset($arr_uniq);

   global $page;
   $table = new Table( 'TPoolSuggestions', $page, null, '',
      TABLE_NO_SORT|TABLE_NO_HIDE|TABLE_NO_PAGE|TABLE_NO_SIZE );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_("Pool\nSize#T_poolsugg"), 'NumberC');
   $table->add_tablehead( 2, T_("Pool\nCount#T_poolsugg"), 'NumberC');
   $table->add_tablehead( 3, T_("User\nCapacity#T_poolsugg"), 'NumberC');
   $table->add_tablehead( 4, T_("User\nDiff#T_poolsugg"), 'NumberC');
   $table->add_tablehead( 5, T_("Best Pool\nDistribution#T_poolsugg"), 'Right');
   $table->add_tablehead( 6, T_("Games\nCount#T_poolsugg"), 'Number');
   $table->add_tablehead( 7, T_('Suggestion Note#T_poolsugg'), 'Note');

   $arr_best = array();
   foreach( $arr_check as $arr_item )
   {
      list( $pool_size, $pool_count, $user_capacity, $pool_size_base, $pool_count_remain,
            $pool_count_base, $games_count, $distribution, $user_choice ) = $arr_item;
      $user_diff = $user_capacity - $reg_count;

      $row_str = array(
         1 => $pool_size,
         2 => $pool_count,
         3 => $user_capacity,
         4 => $user_diff,
         5 => $distribution,
         6 => $games_count,
         7 => '',
      );

      // check for violations
      $pool_size_check = ( $pool_count_remain > 0 ) ? $pool_size_base + 1 : $pool_size_base;
      $violation = '';
      if( $pool_size_check < $tround->MinPoolSize )
      {
         $violation = T_('Violates min. pool-size on slicing!#T_poolsugg');
         $errors[] = sprintf( T_('Pool count [%s] is too large, the min. pool size [%s] would be violated on slicing.'),
            $pool_count, $tround->MinPoolSize );
      }
      elseif( $pool_size_check > $tround->MaxPoolSize )
      {
         $violation = T_('Violates max. pool-size!#T_poolsugg');
         $errors[] = sprintf( T_('Pool count [%s] is too small, the max. pool size [%s] would be violated.'),
            $pool_count, $tround->MaxPoolSize );
      }
      elseif( $user_capacity < $reg_count )
      {
         $violation = T_('Pool-size or count too small!#T_poolsugg');
         $errors[] = sprintf( T_('Either the pool size [%s] or pool count [%s] is too small to cover all %s registered users.'),
            $pool_size, $pool_count, $reg_count );
      }

      $extra_class = '';
      $arr_note = array();
      if( $user_choice )
      {
         $extra_class .= ' UserChoice';
         $arr_note[] = T_('TD-Choice#T_poolsugg');
      }
      if( $violation )
      {
         $extra_class .= ' Violation';
         $arr_note[] = $violation;
      }
      if( count($arr_note) )
         $row_str[7] = implode(', ', $arr_note);
      if( $extra_class )
         $row_str['extra_class'] = trim($extra_class);

      if( $pool_count_remain > 0 )
      {
         $r1 = $pool_count_base * $pool_size_base;
         $r2 = $pool_count_remain * ( $pool_size_base + 1 );
         $ratio = min($r1,$r2) / max($r1,$r2);
      }
      else
         $ratio = 0;
      $arr_best[] = array( $row_str, $user_diff, $ratio, $pool_size, $pool_count, (bool)$violation );
   }

   // find best suggestion (ordered by: min. user-diff, slice-ratio, pool-size)
   usort( $arr_best, 'compare_suggestions' );
   $row_idx = 0;
   $break_val = 0;
   foreach( $arr_best as $arr )
   {
      list(, $user_diff, $ratio, $pool_size, $pool_count, $has_violation ) = $arr;
      $row_str =& $arr_best[$row_idx++][0];
      if( $has_violation )
         continue;

      $stop_val = ( $user_diff + 1 ) * ( $ratio + 1 );
      if( $break_val > 0 && $break_val != $stop_val )
         break;
      $break_val = $stop_val;

      $row_str[7] = concat_str( $row_str[7], ', ', T_('Recommended distribution!#T_poolsugg') );
      $row_str['extra_class'] = concat_str( (string)@$row_str['extra_class'], ' ' , 'Best' );
   }

   global $sugg_order_val;
   if( $sugg_order_val == 1 ) // order by pool-size
      usort( $arr_best, 'compare_pool_parameters' );

   foreach( $arr_best as $arr )
      $table->add_row( $arr[0] );

   return $table;
}//make_suggestions_table

// $a/$b are arrays with key/vals: 0:row_str, 1:user_diff, 2:ratio, 3:pool_size, 4:pool_count, 5:has_violation
function compare_pool_parameters( $a, $b )
{
   if( $a[3] == $b[3] ) // pool-size
      return ($a[5] > $b[5]) ? -1 : 1; // pool-count
   else
      return ($a[3] < $b[3]) ? -1 : 1;
}

// $a/$b are arrays with key/vals: 0:row_str, 1:user_diff, 2:ratio, 3:pool_size, 4:pool_count, 5:has_violation
function compare_suggestions( $a, $b )
{
   if( $a[1] == $b[1] ) // user-diff
   {
      if( $a[2] == $b[2] ) // ratio1
         return ($a[3] < $b[3]) ? -1 : 1; // pool-size
      else
         return ($a[2] < $b[2]) ? -1 : 1;
   }
   else
      return ($a[1] < $b[1]) ? -1 : 1;
}
?>

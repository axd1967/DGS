<?php
/*
Dragon Go Server
Copyright (C) 2001-  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'tournaments/include/tournament.php';
require_once 'tournaments/include/tournament_cache.php';
require_once 'tournaments/include/tournament_factory.php';
require_once 'tournaments/include/tournament_helper.php';
require_once 'tournaments/include/tournament_log_helper.php';
require_once 'tournaments/include/tournament_status.php';
require_once 'tournaments/include/tournament_utils.php';

$GLOBALS['ThePage'] = new Page('TournamentEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if ( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_tournament');
   if ( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_tournament');

   $my_id = $player_row['ID'];
   if ( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_tournament');

/* Actual REQUEST calls used:
     tid=               : edit existing tournament
     t_preview&tid=     : preview for tournament-save
     t_save&tid=        : update (replace) tournament in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if ( $tid < 0 ) $tid = 0;

   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_tournament.find_tournament', $tid );
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   // load T-owner
   if ( $tourney->Owner_ID > 0 )
   {
      $owner_row = load_cache_user_reference( "Tournament.edit_tournament.find_owner($tid,{$tourney->Owner_ID})",
         true, $tourney->Owner_ID );
      $tourney->Owner_Handle = @$owner_row['Handle'];
   }

   // edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = TournamentHelper::allow_edit_tournaments($tourney, $my_id);
   if ( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_tournament.edit($tid,$my_id)");

   // init
   $arr_scopes = Tournament::getScopeText();
   $tdir = TournamentCache::is_cache_tournament_director( 'Tournament.edit_tournament.find_tdir', $tid, $my_id );

   $td_warn_status = ( !$is_admin && $tdir->isEditAdmin() )
      ? $tstatus->check_edit_status( Tournament::get_edit_tournament_status() )
      : null;
   $errors = $tstatus->check_edit_status(
      Tournament::get_edit_tournament_status(), $ttype->allow_edit_tourney_status_td_adm_edit, $tdir );
   if ( !$is_admin && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   // check + parse edit-form
   $old_tourney = clone $tourney;
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tourney, $ttype, $is_admin );
   $errors = array_merge( $errors, $input_errors );

   // save tournament-object with values from edit-form
   if ( @$_REQUEST['t_save'] && !@$_REQUEST['t_preview'] && count($errors) == 0 )
   {
      $tourney->update();
      TournamentLogHelper::log_change_tournament( $tid, $allow_edit_tourney, $edits, $old_tourney, $tourney );

      jump_to("tournaments/edit_tournament.php?tid={$tourney->ID}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament saved!')) );
   }

   $page = "edit_tournament.php";
   $title = T_('Tournament Editor');


   // ---------- Tournament EDIT form ------------------------------

   $tform = new Form( 'tournament', $page, FORM_POST );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Owner#tourney'),
         'TEXT',        user_reference( REF_LINK, 1, '', $tourney->Owner_ID ), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Created'),
         'TEXT',        date(DATE_FMT, $tourney->Created) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'TEXT',        TournamentUtils::buildLastchangedBy($tourney->Lastchanged, $tourney->ChangedBy) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Type#tourney'),
         'TEXT',        Tournament::getTypeText($tourney->Type), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Wizard type#tourney'),
         'TEXT',        Tournament::getWizardTypeText($tourney->WizardType), ));
   if ( $tourney->Flags > 0 )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Flags'),
            'TEXT',        $tourney->formatFlags(), ));
   }
   $tform->add_row( array(
         'DESCRIPTION', T_('Status#tourney'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   if ( $ttype->need_rounds )
      $tform->add_row( array(
            'DESCRIPTION', T_('Rounds#tourney'),
            'TEXT', $tourney->formatRound(), ));

   $tform->add_row( array( 'HR' ));

   if ( count($errors) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }
   if ( count(@$td_warn_status) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Warnings'),
            'TEXT', TournamentDirector::buildAdminEditWarnings($td_warn_status) ));
      $tform->add_empty_row();
   }

   if ( $is_admin )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Owner#tourney'),
            'TEXTINPUT',   'owner', 16, 16, $vars['owner'],
            'TEXT',        T_('(change with care, only by admin)'), ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Scope#tourney'),
            'SELECTBOX',   'scope', 1, $arr_scopes, $vars['scope'], false,
            'TEXT',        T_('(change with care, only by admin)'), ));
   }
   else
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Scope#tourney'),
            'TEXT',        Tournament::getScopeText($tourney->Scope), ));
   }

   $tform->add_empty_row();
   $tform->add_row( array(
         'DESCRIPTION', T_('Start time'),
         'TEXTINPUT',   'start_time', 20, 20, $vars['start_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s], local timezone)'), FMT_PARSE_DATE )), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('End time'),
         'TEXT',        formatDate($tourney->EndTime, NO_VALUE) ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Title'),
         'TEXTINPUT',   'title', 80, 255, $vars['title'] ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXTAREA',    'descr', 70, 15, $vars['descr'] ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_save', T_('Save Tournament'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_preview', T_('Preview'),
      ));

   if ( @$_REQUEST['t_preview'] || $tourney->Title . $tourney->Description != '' )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Preview Title'),
            'TEXT', make_html_safe( $tourney->Title, true ) ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Preview Description'),
            'TEXT', make_html_safe( $tourney->Description, true ) ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   if ( $tid )
      $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if ( $allow_edit_tourney ) # for TD
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tney, $ttype, $is_admin )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['t_save'] || @$_REQUEST['t_preview'] );

   // read from props or set defaults
   $vars = array(
      'owner'           => $tney->Owner_Handle,
      'scope'           => $tney->Scope,
      'start_time'      => formatDate($tney->StartTime),
      'title'           => $tney->Title,
      'descr'           => $tney->Description,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach ( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if ( $is_posted )
   {
      $old_vals['start_time'] = $tney->StartTime;

      if ( $is_admin )
      {
         $new_value = trim($vars['owner']);
         if ( (string)$new_value == '' )
            $errors[] = T_('Missing owner handle#tourney');
         elseif ( $new_value != $tney->Owner_Handle )
         {
            $owner_row = TournamentDirector::load_user_row( 0, $new_value );
            if ( !is_array($owner_row) )
               $errors[] = T_('Unknown user handle for owner#tourney');
            else
            {
               $tney->Owner_ID = $owner_row['ID'];
               $tney->Owner_Handle = $owner_row['Handle'];
               $vars['owner'] = $tney->Owner_Handle;
            }
         }

         global $arr_scopes;
         $new_value = trim($vars['scope']);
         if ( !isset($arr_scopes[$new_value]) )
            $errors[] = T_('Unknown tournament scope');
         else
            $tney->setScope( $new_value );
      }

      $parsed_value = parseDate( T_('Start time for tournament'), $vars['start_time'] );
      if ( is_numeric($parsed_value) )
      {
         $tney->StartTime = $parsed_value;
         $vars['start_time'] = formatDate($tney->StartTime);
      }
      else
         $errors[] = $parsed_value;

      $new_value = trim($vars['title']);
      if ( strlen($new_value) < 8 )
         $errors[] = T_('Tournament title missing or too short');
      elseif ( $tney->Scope != TOURNEY_SCOPE_DRAGON
            && preg_match('/(dragon|drag\s+on|\bD\W*G\W*S\b)/i', $new_value, $groups) )
         $errors[] = sprintf( T_('Tournament title must not contain reserved Dragon/DGS words [%s].'), $groups[1] );
      else
         $tney->Title = $new_value;

      $new_value = trim($vars['descr']);
      if ( strlen($new_value) < 4 )
         $errors[] = T_('Tournament description missing or too short');
      else
         $tney->Description = $new_value;


      // determine edits
      if ( $old_vals['owner'] != $vars['owner'] ) $edits[] = T_('Owner#tourney');
      if ( $old_vals['scope'] != $tney->Scope ) $edits[] = T_('Scope#tourney');
      if ( $old_vals['start_time'] != $tney->StartTime ) $edits[] = T_('Start time');
      if ( $old_vals['title'] != $tney->Title ) $edits[] = T_('Title');
      if ( $old_vals['descr'] != $tney->Description ) $edits[] = T_('Description');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>

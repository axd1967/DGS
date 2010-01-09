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

chdir('..');
require_once( 'include/std_functions.php' );
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_status.php' );
require_once( 'tournaments/include/tournament_factory.php' );

$GLOBALS['ThePage'] = new Page('TournamentEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_tournament');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     (no args)          : add new tournament
     tid=               : edit new (preview) or existing tournament
     t_preview&tid=     : preview for tournament-save
     t_save&tid=        : update (replace) tournament in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_tournament.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );
   $ttype = TournamentFactory::getTournament($tourney->WizardType);

   // edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_tournament.edit($tid,$my_id)");

   // init
   $arr_scopes = Tournament::getScopeText();

   // check + parse edit-form
   list( $vars, $edits, $errorlist ) = parse_edit_form( $tourney, $ttype, $is_admin );
   $errorlist += $tstatus->check_edit_status( Tournament::get_edit_tournament_status() );

   // save tournament-object with values from edit-form
   if( @$_REQUEST['t_save'] && !@$_REQUEST['t_preview'] && count($errorlist) == 0 )
   {
      $tourney->persist(); // insert or update
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
         'DESCRIPTION', T_('Creation date'),
         'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Created) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Last changed date'),
         'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Lastchanged) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Type#tourney'),
         'TEXT',        Tournament::getTypeText($tourney->Type), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Wizard type#tourney'),
         'TEXT',        Tournament::getWizardTypeText($tourney->WizardType), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Status#tourney'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   $tform->add_row( array( 'HR' ));

   if( count($errorlist) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errorlist) ));
      $tform->add_empty_row();
   }

   if( $is_admin )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Owner#tourney'),
            'TEXTINPUT',   'owner', 16, 16, textarea_safe($vars['owner']),
            'TEXT',        T_('(change with care, only by admin)'), ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Scope#tourney'),
            'SELECTBOX',   'scope', 1, $arr_scopes, $tourney->Scope, false,
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
         'TEXTINPUT',   'start_time', 20, 20,
                        TournamentUtils::formatDate($tourney->StartTime, $vars['start_time']), '',
         'TEXT',  '&nbsp;<span class="EditNote">'
                     . sprintf( T_('(Date format [%s])'), TOURNEY_DATEFMT ) . '</span>' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('End time'),
         'TEXT',        TournamentUtils::formatDate($tourney->EndTime, NO_VALUE) ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Title'),
         'TEXTINPUT',   'title', 80, 255, $tourney->Title, '' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXTAREA',    'descr', 70, 15, $tourney->Description ));

   if( $ttype->need_rounds )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Tournament rounds'),
            'TEXTINPUT',   'rounds', 5, 5, $tourney->Rounds, '' ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Current tournament round'),
            'TEXTINPUT',   'current_round', 5, 5, $tourney->CurrentRound, '' ));
   }
   else
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Tournament rounds'),
            'TEXT',        $tourney->Rounds, ));
      $tform->add_row( array(
            'DESCRIPTION', T_('Current tournament round'),
            'TEXT',        $tourney->CurrentRound, ));
   }

   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT', sprintf( '<span class="TWarning">[%s]</span>', implode(', ', $edits)), ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_save', T_('Save tournament'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_preview', T_('Preview'),
      ));

   if( @$_REQUEST['t_preview'] || $tourney->Title . $tourney->Description != '' )
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
   if( $tid )
      $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
      $menu_array[T_('Manage this tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


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
      'start_time'      => TournamentUtils::formatDate($tney->StartTime),
      'title'           => $tney->Title,
      'descr'           => $tney->Description,
      'rounds'          => $tney->Rounds,
      'current_round'   => $tney->CurrentRound,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $old_vals['start_time'] = $tney->StartTime;

      if( $is_admin )
      {
         $new_value = trim($vars['owner']);
         if( (string)$new_value == '' )
            $errors[] = T_('Missing owner handle');
         elseif( $new_value != $tney->Owner_Handle )
         {
            $owner_row = TournamentDirector::load_user_row( 0, $new_value );
            if( !is_array($owner_row) )
               $errors[] = T_('Unknown user handle for owner');
            else
            {
               $tney->Owner_ID = $owner_row['ID'];
               $tney->Owner_Handle = $owner_row['Handle'];
               $vars['owner'] = $tney->Owner_Handle;
            }
         }

         global $arr_scopes;
         $new_value = trim($vars['scope']);
         if( !isset($arr_scopes[$new_value]) )
            $errors[] = T_('Unknown tournament scope');
         else
            $tney->setScope( $new_value );
      }

      $parsed_value = TournamentUtils::parseDate( T_('Start time for tournament'), $vars['start_time'] );
      if( is_numeric($parsed_value) )
      {
         $tney->StartTime = $parsed_value;
         $vars['start_time'] = TournamentUtils::formatDate($tney->StartTime);
      }
      else
         $errors[] = $parsed_value;

      $new_value = trim($vars['title']);
      if( strlen($new_value) < 8 )
         $errors[] = T_('Tournament title missing or too short');
      elseif( $tney->Scope != TOURNEY_SCOPE_DRAGON
            && preg_match('/(dragon|drag\s+on|\bD\W*G\W*S\b)/i', $new_value, $groups) )
         $errors[] = sprintf( T_('Tournament title must not contain reserved Dragon/DGS words [%s].'), $groups[1] );
      else
         $tney->Title = $new_value;

      $new_value = trim($vars['descr']);
      if( strlen($new_value) < 4 )
         $errors[] = T_('Tournament description missing or too short');
      else
         $tney->Description = $new_value;

      if( $ttype->need_rounds )
      {
         $new_value = $vars['rounds'];
         if( !is_numeric($new_value) )
            $errors[] = T_('Expecting positive number for tournament rounds');
         elseif( $new_value <= 0 )
            $errors[] = T_('Tournament must have at least one round.');
         else
            $tney->Rounds = (int)$new_value;

         $new_value = $vars['current_round'];
         if( !is_numeric($new_value) )
            $errors[] = T_('Expecting positive number for tournament current round');
         elseif( $new_value < 1 || $new_value > $tney->Rounds )
            $errors[] = sprintf( T_('Current tournament round must be in rounds value-range [1..%s].'), $tney->Rounds );
         else
            $tney->CurrentRound = (int)$new_value;
      }

      // determine edits
      if( $old_vals['owner'] != $vars['owner'] ) $edits[] = T_('Owner#edits');
      if( $old_vals['scope'] != $tney->Scope ) $edits[] = T_('Scope#edits');
      if( $old_vals['start_time'] != $tney->StartTime ) $edits[] = T_('Start-time#edits');
      if( $old_vals['title'] != $tney->Title ) $edits[] = T_('Title#edits');
      if( $old_vals['descr'] != $tney->Description ) $edits[] = T_('Description#edits');
      if( $old_vals['rounds'] != $tney->Rounds ) $edits[] = T_('Rounds#edits');
      if( $old_vals['current_round'] != $tney->CurrentRound ) $edits[] = T_('Rounds#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>

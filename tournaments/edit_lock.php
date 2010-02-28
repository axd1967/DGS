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

$GLOBALS['ThePage'] = new Page('TournamentLockEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_lock');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=               : edit tournament-lock-flags
     t_preview&tid=     : preview for tournament-save
     t_save&tid=        : update tournament-flags in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_lock.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   // edit allowed?
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_lock.edit($tid,$my_id)");

   // init
   $arr_flags = array(
      //TOURNEY_FLAG_LOCK_ADMIN - can only be edited by admin on edit-tourney page
      TOURNEY_FLAG_LOCK_REGISTER => 'tfl_lock_reg',
      TOURNEY_FLAG_LOCK_LADDER   => 'tfl_lock_ladder',
      //TOURNEY_FLAG_LOCK_CRON - can only be edited/set by admin/cron
   );
   if( $tourney->Type != TOURNEY_TYPE_LADDER )
      unset($arr_flags[TOURNEY_FLAG_LOCK_LADDER]);
   $errors = $tstatus->check_edit_status( Tournament::get_edit_lock_tournament_status() );

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tourney );
   $errors = array_merge( $errors, $input_errors );

   // save tournament-object with values from edit-form
   if( @$_REQUEST['t_save'] && !@$_REQUEST['t_preview'] && count($errors) == 0 )
   {
      $tourney->update();
      jump_to("tournaments/edit_tournament.php?tid={$tourney->ID}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament saved!')) );
   }

   $page = "edit_lock.php";
   $title = T_('Tournament Lock Editor');


   // ---------- Tournament EDIT form ------------------------------

   $tform = new Form( 'tournament', $page, FORM_GET );
   $tform->add_hidden( 'tid', $tid );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Last changed'),
         'TEXT',        TournamentUtils::buildLastchangedBy($tourney->Lastchanged, $tourney->ChangedBy) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Flags#tourney'),
         'TEXT',        $tourney->formatFlags(NO_VALUE), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Status#tourney'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   $tform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
      $tform->add_empty_row();
   }

   $first = true;
   foreach( $arr_flags as $flag => $name )
   {
      $arr = ($first) ? array( 'DESCRIPTION', T_('Flags#tourney') ) : array( 'TAB' );
      $first = false;
      array_push( $arr,
         'CHECKBOX', $name, 1, Tournament::getFlagsText($flag), $tourney->isFlagSet($flag) );
      $tform->add_row( $arr );
   }
   $tform->add_empty_row();

   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tform->add_empty_row();
   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 't_save', T_('Save locks'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 't_preview', T_('Preview'),
      ));


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();


   $menu_array = array();
   if( $tid )
      $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_edit_tourney ) # for TD
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tney )
{
   global $arr_flags;

   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['t_save'] || @$_REQUEST['t_preview'] );

   // read from props or set defaults
   $vars = array(
      'flags' => $tney->Flags,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if( $is_posted )
   {
      foreach( array_values($arr_flags) as $key )
         $vars[$key] = get_request_arg( $key, false );
   }

   // parse URL-vars
   if( $is_posted )
   {
      $new_value = 0;
      $flagmask = 0;
      foreach( $arr_flags as $flag => $name )
      {
         $flagmask |= $flag;
         if( $vars[$name] )
            $new_value |= $flag;
      }
      $tney->Flags = ($tney->Flags & ~$flagmask) | $new_value; // don't touch other flags

      // determine edits
      if( $old_vals['flags'] != $tney->Flags ) $edits[] = T_('Flags#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form
?>
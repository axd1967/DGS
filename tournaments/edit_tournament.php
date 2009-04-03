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
require_once( 'include/gui_functions.php' );
require_once( 'include/form_functions.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );

$ThePage = new Page('TournamentEdit');

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

   $tourney = null;
   if( $tid )
      $tourney = Tournament::load_tournament( $tid ); // existing tournament ?

   // create/edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = false;
   $allow_new_del_TD = false;
   if( is_null($tourney) )
   {
      if( !Tournament::allow_create($my_id) )
         error('tournament_edit_not_allowed', "edit_tournament.new_tournament($my_id)");

      // new tournament
      $tourney = new Tournament();
      $tourney->Owner_ID = $my_id;
   }
   else
   {
      if( !$tourney->allow_edit_tournaments($my_id) )
         error('tournament_edit_not_allowed', "edit_tournament.edit_tournament($tid,$my_id)");
      $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
      $allow_new_del_TD = $tourney->allow_edit_directors($my_id, true);
   }

   // init
   $arr_scopes = Tournament::getScopeText();
   if( !$is_admin )
      unset($arr_scopes[TOURNEY_SCOPE_DRAGON]); // only admin can set Dragon-scope
   unset($arr_scopes[TOURNEY_SCOPE_PRIVATE]); //TODO(later) not supported yet

   if( !$is_admin && !$tid ) // only admin can edit status on T-creation
      $arr_status = array( TOURNEY_STATUS_NEW => Tournament::getStatusText(TOURNEY_STATUS_NEW) );
   else
      $arr_status = Tournament::getStatusText();

   // check + parse edit-form
   //TODO use same method as in edit_properties.php (error, edits, vars, parsing)
   $errorlist = parse_edit_form( $tourney );

   // save tournament-object with values from edit-form
   if( @$_REQUEST['t_save'] && !@$_REQUEST['t_preview'] && is_null($errorlist) )
   {
      $tourney->persist(); // insert or update
      jump_to("tournaments/edit_tournament.php?tid={$tourney->ID}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament saved!')) );
   }

   $page = "edit_tournament.php";
   $title = T_('Tournament Management');


   // ---------- Tournament EDIT form ------------------------------

   $tform = new Form( 'tournament', $page, FORM_POST );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        ( ($tid) ? anchor( "view_tournament.php?tid=$tid", $tid ) : NO_VALUE ) ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Owner'),
         'TEXT',        ( ($tourney->Owner_ID) ? user_reference( REF_LINK, 1, '', $tourney->Owner_ID ) : NO_VALUE ) ));
   if( $tourney->Created )
      $tform->add_row( array(
            'DESCRIPTION', T_('Creation date'),
            'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Created) ));
   if( $tourney->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed date'),
            'TEXT',        date(DATEFMT_TOURNAMENT, $tourney->Lastchanged) ));

   $start_time = trim(get_request_arg('start_time'));
   $tform->add_row( array(
         'DESCRIPTION', T_('Start time'),
         'TEXTINPUT',   'start_time', 20, 20,
                        TournamentUtils::formatDate($tourney->StartTime, $start_time), '',
         'TEXT',  '&nbsp;<span class="EditNote">'
                     . sprintf( T_('(Date format [%s])'), TOURNEY_DATEFMT ) . '</span>' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('End time'),
         'TEXT',        TournamentUtils::formatDate($tourney->EndTime, NO_VALUE) ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Scope'),
         'SELECTBOX',   'scope', 1, $arr_scopes, $tourney->Scope, false ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Type'),
         'SELECTBOX',   'type', 1, Tournament::getTypeText(), $tourney->Type, false ));
   //TODO Type can ONLY be changed when T has no registrations
   //TODO Status can NOT be changed to NEW if T has been started (PLAY) !? -> why not, but makes things more complex
   if( $is_admin || $tid ) // only admin can edit status on T-creation
      $tform->add_row( array(
            'DESCRIPTION', T_('Status'),
            'SELECTBOX',   'status', 1, $arr_status, $tourney->Status, false ));
   else
   {
      $tform->add_hidden( 'status', TOURNEY_STATUS_NEW );
      $tform->add_row( array(
            'DESCRIPTION', T_('Status'),
            'TEXT',        $arr_status[TOURNEY_STATUS_NEW] ));
   }

   if( !is_null($errorlist) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT',        '<span class="ErrorMsg">'
                  . T_('There are some errors, so Tournament can\'t be saved:') . "<br>\n"
                  . '* ' . implode(",<br>\n* ", $errorlist)
                  . '</span>' ));
   }

   $tform->add_row( array(
         'DESCRIPTION', T_('Title'),
         'TEXTINPUT',   'title', 80, 255, $tourney->Title, '' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Description'),
         'TEXTAREA',    'descr', 70, 15, $tourney->Description ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament rounds'),
         'TEXTINPUT',   'rounds', 5, 5, $tourney->Rounds, '' ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Current tournament round'),
         'TEXTINPUT',   'current_round', 5, 5, $tourney->CurrentRound, '' ));

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


   $tform->add_hidden( 'tid', $tid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   $notes = Tournament::build_notes();
   echo_notes( 'edittournamentnotesTable', T_('Tournament notes'), $notes );

   $menu_array = array();
   if( $tid )
      $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   if( $allow_new_del_TD )
      $menu_array[T_('Add tournament director')] =
         array( 'url' => "tournaments/edit_director.php?tid=$tid", 'class' => 'TAdmin' );
   if( $allow_edit_tourney ) # for TD
   {
      $menu_array[T_('Edit properties')] =
         array( 'url' => "tournaments/edit_properties.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit rules')] =
         array( 'url' => "tournaments/edit_rules.php?tid=$tid", 'class' => 'TAdmin' );
      $menu_array[T_('Edit participants')] =
         array( 'url' => "tournaments/edit_participant.php?tid=$tid", 'class' => 'TAdmin' );
   }

   end_page(@$menu_array);
}


/*! \brief Parses and checks input, returns error-list or NULL if no error. */
function parse_edit_form( &$tourney )
{
   global $arr_scopes, $arr_status;
   $read = ( @$_REQUEST['t_save'] || @$_REQUEST['t_preview'] ); // read-URL-vars
   $errors = array();

   $new_value = get_request_arg('scope');
   if( $read )
   {
      if( !isset($arr_scopes[$new_value]) ) // admin-restricted
         error('invalid_args', "edit_tournament.check.scope($new_value)");
      $tourney->setScope( $new_value );
   }

   $new_value = get_request_arg('type');
   if( $read )
      $tourney->setType( $new_value );

   $new_value = get_request_arg('status');
   if( $read )
   {
      if( !isset($arr_status[$new_value]) ) // admin-restricted
         error('invalid_args', "edit_tournament.check.status($new_value)");
      $tourney->setStatus( $new_value );
   }

   $new_value = trim(get_request_arg('title'));
   if( $read )
      $tourney->Title = $new_value;
   if( $tourney->Title == '' )
      $errors[] = T_('Title for tournament is missing');

   $new_value = trim(get_request_arg('descr'));
   if( $read )
      $tourney->Description = $new_value;
   if( $tourney->Description == '' )
      $errors[] = T_('Description for tournament is missing');

   $new_value = trim(get_request_arg('start_time'));
   if( $read )
   {
      $parsed_value = TournamentUtils::parseDate( T_('Start time of tournament'), $new_value );
      if( is_numeric($parsed_value) )
         $tourney->StartTime = $parsed_value;
      else
         $errors[] = $parsed_value;
   }

   $new_value = (int)trim(get_request_arg('rounds'));
   if( $read && is_numeric($new_value) && $new_value >= 0 )
      $tourney->Rounds = $new_value;
   if( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN && $tourney->Rounds <= 0 )
      $errors[] = T_('Round-Robin tournament should have at least one round.');

   $new_value = (int)trim(get_request_arg('current_round'));
   if( $read && is_numeric($new_value) && $new_value >= 0 )
      $tourney->CurrentRound = $new_value;
   if( $tourney->Type == TOURNEY_TYPE_ROUND_ROBIN )
   {
      if( $tourney->CurrentRound <= 0 || $tourney->CurrentRound > $tourney->Rounds )
         $errors[] = sprintf( T_('Round-Robin tournament round must be in range [1-%s].'), $tourney->Rounds );
   }

   return (count($errors)) ? $errors : NULL;
}

?>

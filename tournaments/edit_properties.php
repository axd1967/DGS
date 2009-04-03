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
require_once( 'include/rating.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_properties.php' );

$ThePage = new Page('TournamentPropertiesEdit');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_properties');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=                : add new or edit tournament properties
     tp_preview&tid=     : preview for tournament-properties-save
     tp_save&tid=        : update (replace) tournament-properties in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_properties.find_tournament($tid)");

   // create/edit allowed?
   if( !$tourney->allow_edit_tournaments($my_id) )
      error('tournament_edit_not_allowed', "Tournament.edit_properties.edit_tournament($tid,$my_id)");

   $tprops = TournamentProperties::load_tournament_properties( $tid );
   $no_tprops = is_null($tprops);
   if( $no_tprops )
      $tprops = new TournamentProperties( $tid );

   // init
   $arr_rating_use_modes = TournamentProperties::getRatingUseModeText();
   $rating_array = getRatingArray();

   // check + parse edit-form
   list( $vars, $edits, $errorlist ) = parse_edit_form( $tprops, $no_tprops );

   // save tournament-properties-object with values from edit-form
   if( @$_REQUEST['tp_save'] && !@$_REQUEST['tp_preview'] && is_null($errorlist) )
   {
      $tprops->persist(); // insert or update
      jump_to("tournaments/edit_properties.php?tid={$tid}".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament Properties saved!')) );
   }

   $page = "edit_properties.php";
   $title = T_('Tournament Properties Editor');


   // ---------- Tournament-Properties EDIT form --------------------

   $tform = new Form( 'tournament', $page, FORM_POST );

   $tform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        anchor( "view_tournament.php?tid=$tid", $tid ),
         'TEXT',        SMALL_SPACING . '[' . make_html_safe( $tourney->Title, true ) . ']', ));
   if( $tprops->Lastchanged )
      $tform->add_row( array(
            'DESCRIPTION', T_('Last changed date'),
            'TEXT',        date(DATEFMT_TOURNAMENT, $tprops->Lastchanged) ));

   if( !is_null($errorlist) )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT',        '<span class="ErrorMsg">'
                  . T_('There are some errors, so Tournament-properties can\'t be saved:') . "<br>\n"
                  . '* ' . implode(",<br>\n* ", $errorlist)
                  . '</span>' ));
   }

   $reg_end_time = trim(get_request_arg('reg_end_time'));
   $tform->add_row( array(
         'DESCRIPTION', T_('Register end time'),
         'TEXTINPUT',   'reg_end_time', 20, 20, $vars['reg_end_time'], '',
         'TEXT',  '&nbsp;<span class="EditNote">'
                     . sprintf( T_('(Date format [%s])'), TOURNEY_DATEFMT ) . '</span>' ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Restrict participants'),
         'TEXTINPUT',   'min_participants', 5, 5, $vars['min_participants'], '',
         'TEXT',        MINI_SPACING . T_('(Minimum)'), ));
   $tform->add_row( array(
         'TAB',
         'TEXTINPUT',   'max_participants', 5, 5, $vars['max_participants'], '',
         'TEXT',        MINI_SPACING . T_('(Maximum)'), ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Rating Use Mode'),
         'SELECTBOX',   'rating_use_mode', 1, $arr_rating_use_modes, $vars['rating_use_mode'], false, ));
   $tform->add_empty_row();

   $tform->add_row( array(
         'DESCRIPTION', T_('Restrict user rating'),
         'CHECKBOX',    'user_rated', '1', '', $vars['user_rated'],
         'TEXT',        sptext(T_('If yes, rating between'), 1),
         'SELECTBOX',   'user_min_rating', 1, $rating_array, $vars['user_min_rating'], false,
         'TEXT',        sptext(T_('and')),
         'SELECTBOX',   'user_max_rating', 1, $rating_array, $vars['user_max_rating'], false, ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Minimum finished games'),
         'TEXTINPUT',   'min_games_finished', 5, 5, $vars['min_games_finished'], '',
         'TEXT',        MINI_SPACING . T_('(rated and unrated)'), ));
   $tform->add_row( array(
         'TAB',
         'TEXTINPUT',   'min_games_rated', 5, 5, $vars['min_games_rated'], '',
         'TEXT',        MINI_SPACING . T_('(rated only)'), ));

   $tform->add_row( array(
         'DESCRIPTION', T_('Notes'),
         'TEXTAREA',    'notes', 70, 10, $vars['notes'] ));
   $tform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT', sprintf( '<span class="TWarning">[%s]</span>', implode(', ', $edits)), ));

   $tform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tp_save', T_('Save tournament properties'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tp_preview', T_('Preview'),
      ));

   if( @$_REQUEST['tp_preview'] || $tprops->Notes != '' )
   {
      $tform->add_row( array(
            'DESCRIPTION', T_('Preview Notes'),
            'TEXT', make_html_safe( $tprops->Notes, true ) ));
   }

   $tform->add_hidden( 'tid', $tid );


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tform->echo_string();

   $notes = TournamentProperties::build_notes();
   echo_notes( 'edittournamentpropsnotesTable', T_('Tournament properties notes'), $notes );


   $menu_array = array();
   $menu_array[T_('View this tournament')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Manage this tournament')] =
         array( 'url' => "tournaments/edit_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


/*!
 * \brief Parses and checks input, returns error-list or NULL if no error.
 * \return ( vars-hash, edits-arr, errorlist|null )
 */
function parse_edit_form( &$tpr, $not_saved )
{
   $edits = array();
   $errors = array();

   // read from props or set defaults
   $vars = array(
      'reg_end_time'       => TournamentUtils::formatDate($tpr->RegisterEndTime),
      'min_participants'   => $tpr->MinParticipants,
      'max_participants'   => $tpr->MaxParticipants,
      'rating_use_mode'    => $tpr->RatingUseMode,
      'user_rated'         => $tpr->UserRated,
      'user_min_rating'    => echo_rating( $tpr->UserMinRating, false, 0, true, false ),
      'user_max_rating'    => echo_rating( $tpr->UserMaxRating, false, 0, true, false ),
      'min_games_finished' => $tpr->UserMinGamesFinished,
      'min_games_rated'    => $tpr->UserMinGamesRated,
      'notes'              => $tpr->Notes,
   );

   // copy to determine edit-changes
   $old_vals = array_merge( array(), $vars );
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( @$_REQUEST['tp_save'] || @$_REQUEST['tp_preview'] )
   {
      $old_vals['reg_end_time'] = $tpr->RegisterEndTime;
      $old_vals['user_min_rating'] = $tpr->UserMinRating;
      $old_vals['user_max_rating'] = $tpr->UserMaxRating;

      $parsed_value = TournamentUtils::parseDate( T_('End time for registration'), $vars['reg_end_time'] );
      if( is_numeric($parsed_value) )
         $tpr->RegisterEndTime = $parsed_value;
      else
         $errors[] = $parsed_value;

      $new_value = $vars['min_participants'];
      if( isNumberOrEmpty($new_value) )
         $tpr->MinParticipants = limit( $new_value, 0, 99999, 0 );
      else
         $errors[] = T_('Expecting positive number for minimum participants');

      $new_value = $vars['max_participants'];
      if( isNumberOrEmpty($new_value) )
         $tpr->MaxParticipants = limit( $new_value, 0, 99999, 0 );
      else
         $errors[] = T_('Expecting positive number for maximum participants');

      $tpr->setRatingUseMode( $vars['rating_use_mode'] );
      $tpr->UserRated = (bool)$vars['user_rated'];
      $tpr->setUserMinRating( read_rating( $vars['user_min_rating'] ));
      $tpr->setUserMaxRating( read_rating( $vars['user_max_rating'] ));

      $new_value = $vars['min_games_finished'];
      if( isNumberOrEmpty($new_value) )
         $tpr->UserMinGamesFinished = limit( $new_value, 0, 9999, 0 );
      else
         $errors[] = T_('Expecting positive number of finished games');

      $new_value = $vars['min_games_rated'];
      if( isNumberOrEmpty($new_value) )
         $tpr->UserMinGamesRated = limit( $new_value, 0, 9999, 0 );
      else
         $errors[] = T_('Expecting positive number of rated finished games');

      $tpr->Notes = $vars['notes'];

      // reformat
      $vars['reg_end_time'] = TournamentUtils::formatDate($tpr->RegisterEndTime);
      $vars['user_min_rating'] = echo_rating( $tpr->UserMinRating, false, 0, true, false );
      $vars['user_max_rating'] = echo_rating( $tpr->UserMaxRating, false, 0, true, false );

      // determine edits
      if( $old_vals['reg_end_time'] != $tpr->RegisterEndTime ) $edits[] = T_('End time#edits');
      if( $old_vals['min_participants'] != $tpr->MinParticipants ) $edits[] = T_('Participants#edits');
      if( $old_vals['max_participants'] != $tpr->MaxParticipants ) $edits[] = T_('Participants#edits');
      if( $old_vals['rating_use_mode'] != $tpr->RatingUseMode ) $edits[] = T_('Rating use mode#edits');
      if( $old_vals['user_rated'] != $tpr->UserRated ) $edits[] = T_('User-Rating#edits');
      if( $old_vals['user_min_rating'] != $tpr->UserMinRating ) $edits[] = T_('User-Rating#edits');
      if( $old_vals['user_max_rating'] != $tpr->UserMaxRating ) $edits[] = T_('User-Rating#edits');
      if( $old_vals['min_games_finished'] != $tpr->UserMinGamesFinished ) $edits[] = T_('User-Games#edits');
      if( $old_vals['min_games_rated'] != $tpr->UserMinGamesRated ) $edits[] = T_('User-Games#edits');
      if( $old_vals['notes'] != $tpr->Notes ) $edits[] = T_('Notes#edits');
   }

   if( ($tpr->MinParticipants + $tpr->MaxParticipants == 0) && ($tpr->MinParticipants > $tpr->MaxParticipants) )
   {
      swap( $tpr->MinParticipants, $tpr->MaxParticipants );
      swap( $vars['min_participants'], $vars['max_participants'] );
   }

   if( $tpr->UserMinRating > $tpr->UserMaxRating )
   {
      swap( $tpr->UserMinRating, $tpr->UserMaxRating );
      swap( $vars['user_min_rating'], $vars['user_max_rating'] );
   }

   return array( $vars, array_unique($edits), ( count($errors) ? $errors : NULL ) );
}

function isNumberOrEmpty( $value )
{
   return ((string)$value == '') || preg_match( "/^\d+$/", $value );
}
?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2011  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_factory.php' );
require_once( 'tournaments/include/tournament_news.php' );
require_once( 'tournaments/include/tournament_status.php' );
require_once( 'tournaments/include/tournament_utils.php' );

$GLOBALS['ThePage'] = new Page('TournamentNewsEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_news');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     tid=                     : add new tournament-news
     tid=&tnid=               : edit existing tournament-news
     tn_preview&tid=&tnid=    : preview for tournament-news-save
     tn_save&tid=             : add new tournament-news in database
     tn_save&tid=&tnid=       : update (replace) tournament-news in database
*/

   $tid = (int) @$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $tnews_id = (int) @$_REQUEST['tnid'];
   if( $tnews_id < 0 ) $tnews_id = 0;

   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "TournamentNews.edit_news.find_tournament($tid)");

   // edit allowed?
   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "TournamentNews.edit_news.edit($tid,$my_id)");

   // init
   $tnews = TournamentNews::load_tournament_news_entry( $tnews_id, $tid ); // existing T-news ?
   if( is_null($tnews) )
   {
      if( $tnews_id )
         error('bad_tournament_news', "TournamentNews.edit_news.edit($tid,$tnews_id)");
      $tnews = new TournamentNews( 0, $tid, $my_id );
      $tnews->setStatus( TNEWS_STATUS_SHOW );
      $tnews->Published = $NOW;
   }

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tnews, $is_admin );
   $errors = $input_errors;

   // save tournament-news-object with values from edit-form
   if( @$_REQUEST['tn_save'] && !@$_REQUEST['tn_preview'] && count($errors) == 0 )
   {
      $tnews->persist();
      jump_to("tournaments/edit_news.php?tid=$tid".URI_AMP."tnid=$tnews_id".URI_AMP
            . "sysmsg=". urlencode(T_('Tournament News saved!')) );
   }

   $page = "edit_news.php";
   $title = T_('Tournament News Editor');


   // ---------- Tournament EDIT form ------------------------------

   $tnform = new Form( 'tournamenteditnews', $page, FORM_POST );
   $tnform->add_hidden( 'tid', $tid );
   $tnform->add_hidden( 'tnid', $tnews_id );

   $tnform->add_row( array(
         'DESCRIPTION', T_('Tournament ID'),
         'TEXT',        $tourney->build_info() ));

   $tnform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $tnform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tnform->add_empty_row();
   }

   $tnform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 255, $vars['subject'] ));
   $tnform->add_row( array(
         'DESCRIPTION', T_('Text'),
         'TEXTAREA',    'text', 70, 15, $vars['text'] ));

   $tnform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tnform->add_empty_row();
   $tnform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tn_save', T_('Save tournament news'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tn_preview', T_('Preview'),
      ));

   if( @$_REQUEST['tn_preview'] || $tnews->Subject . $tnews->Text != '' )
   {
      $tnform->add_row( array(
            'DESCRIPTION', T_('Preview Subject'),
            'TEXT', make_html_safe($tnews->Subject, true) ));
      $tnform->add_row( array(
            'DESCRIPTION', T_('Preview Text'),
            'TEXT', make_html_safe($tnews->Text, true) ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tnform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament news')] = "tournaments/list_news.php?tid=$tid";
   $menu_array[T_('Add news#tnews')] = "tournaments/edit_news.php?tid=$tid";
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tnews, $is_admin )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tn_save'] || @$_REQUEST['tn_preview'] );

   // read from props or set defaults
   $vars = array(
      'subject'         => $tnews->Subject,
      'text'            => $tnews->Text,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $new_value = trim($vars['subject']);
      if( strlen($new_value) < 8 )
         $errors[] = T_('Tournament-News subject missing or too short');
      else
         $tnews->Subject = $new_value;

      $new_value = trim($vars['text']);
      $tnews->Text = $new_value;


      // determine edits
      if( $old_vals['subject'] != $tnews->Subject ) $edits[] = T_('Subject#edits');
      if( $old_vals['text'] != $tnews->Text ) $edits[] = T_('Text#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

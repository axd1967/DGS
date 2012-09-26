<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Jens-Uwe Gaspar

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
require_once( 'tournaments/include/tournament_news.php' );
require_once( 'tournaments/include/tournament_utils.php' );

$GLOBALS['ThePage'] = new Page('TournamentNewsEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'Tournament.edit_news');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_news');
   $my_id = $player_row['ID'];

   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest', 'Tournament.edit_news');

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

   // edit allowed?
   $tourney = TournamentCache::load_cache_tournament( 'Tournament.edit_news.find_tournament', $tid );
   $is_admin = TournamentUtils::isAdmin();
   $allow_edit_tourney = $tourney->allow_edit_tournaments( $my_id );
   if( !$allow_edit_tourney )
      error('tournament_edit_not_allowed', "Tournament.edit_news.edit($tid,$my_id)");

   // init
   if( $tnews_id > 0 )
   {
      $qsql = TournamentNews::build_query_sql( $tnews_id, $tid );
      $tnews = TournamentNews::load_tournament_news_entry_by_query( $qsql ); // existing T-news ?
   }
   else
      $tnews = null;
   if( is_null($tnews) )
   {
      if( $tnews_id )
         error('bad_tournament_news', "Tournament.edit_news.edit($tid,$tnews_id)");
      $tnews = new TournamentNews( 0, $tid, $my_id );
      $tnews->Published = $NOW;
   }
   $tnews_old_status = $tnews->Status;
   $arr_status = TournamentNews::getStatusText();
   $arr_flags = array(
      TNEWS_FLAG_HIDDEN  => array( 'flag_hidden', T_('news only for tournament-directors#tnews') ),
      TNEWS_FLAG_PRIVATE => array( 'flag_priv',   T_('news only for tournament-users#tnews') ),
   );

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $tnews );
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
   $tnform->add_row( array(
         'DESCRIPTION', T_('Tournament Status'),
         'TEXT',        Tournament::getStatusText($tourney->Status), ));
   if( $tnews->Lastchanged )
      $tnform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        TournamentUtils::buildLastchangedBy($tnews->Lastchanged, $tnews->ChangedBy) ));

   $tnform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $tnform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $tnform->add_empty_row();
   }

   $tnform->add_row( array(
         'DESCRIPTION', T_('Current Status#tnews'),
         'TEXT',        TournamentNews::getStatusText($tnews_old_status) ));
   $tnform->add_row( array(
         'TAB',
         'SELECTBOX',    'status', 1, $arr_status, $vars['status'], false, ));

   $first = true;
   foreach( $arr_flags as $flag => $farr )
   {
      list( $name, $ftext ) = $farr;
      $arr = ($first) ? array( 'DESCRIPTION', T_('Flags') ) : array( 'TAB' );
      $first = false;
      array_push( $arr,
         'CHECKBOX', $name, 1, TournamentNews::getFlagsText($flag), ($tnews->Flags & $flag),
         'TEXT', sptext("($ftext)", 1) );
      $tnform->add_row( $arr );
   }

   $tnform->add_row( array(
         'DESCRIPTION', T_('Publish Time'),
         'TEXTINPUT',   'publish', 20, 30, $vars['publish'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE )), ));
   $tnform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 255, $vars['subject'] ));
   $tnform->add_row( array(
         'DESCRIPTION', T_('Text'),
         'TEXTAREA',    'text', 70, 10, $vars['text'] ));

   $tnform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $tnform->add_empty_row();
   $tnform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'tn_save', T_('Save Tournament News'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'tn_preview', T_('Preview'),
      ));

   if( @$_REQUEST['tn_preview'] || $tnews->Subject . $tnews->Text != '' )
   {
      $tnform->add_empty_row();
      $tnform->add_row( array(
            'DESCRIPTION', T_('Preview'),
            'OWNHTML', '<td class="Preview">' . TournamentNews::build_tournament_news($tnews) . '</td>', ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tnform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament news')] = "tournaments/list_news.php?tid=$tid";
   $menu_array[T_('Add news#tnews')] = "tournaments/edit_news.php?tid=$tid";
   $menu_array[T_('New bulletin')] = "edit_bulletin.php?n_tid=$tid";
   if( Bulletin::is_bulletin_admin() )
      $menu_array[T_('New admin bulletin')] =
         array( 'url' => "admin_bulletin.php?n_tid=$tid", 'class' => 'AdminLink' );
   $menu_array[T_('Manage tournament')] =
      array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tnews )
{
   global $arr_flags;

   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['tn_save'] || @$_REQUEST['tn_preview'] );

   // read from props or set defaults
   $vars = array(
      'status'          => $tnews->Status,
      'flags'           => $tnews->Flags,
      'publish'         => formatDate($tnews->Published),
      'subject'         => $tnews->Subject,
      'text'            => $tnews->Text,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );
   // handle checkboxes having no key/val in _POST-hash
   if( $is_posted )
   {
      foreach( array_values($arr_flags) as $farr )
      {
         list( $key, $tmp ) = $farr;
         $vars[$key] = get_request_arg( $key, false );
      }
   }

   // parse URL-vars
   if( $is_posted )
   {
      $old_vals['publish'] = $tnews->Published;

      $tnews->setStatus($vars['status']);

      $new_value = 0;
      foreach( $arr_flags as $flag => $farr )
      {
         list( $name, $tmp ) = $farr;
         if( $vars[$name] )
            $new_value |= $flag;
      }
      $tnews->Flags = $new_value;

      $parsed_value = parseDate( T_('Publish time for news#tnews'), $vars['publish'] );
      if( is_numeric($parsed_value) )
      {
         $tnews->Published = $parsed_value;
         $vars['publish'] = formatDate($tnews->Published);
      }
      else
         $errors[] = $parsed_value;

      $new_value = trim($vars['subject']);
      if( strlen($new_value) < 8 )
         $errors[] = T_('Tournament-News subject missing or too short');
      else
         $tnews->Subject = $new_value;

      $new_value = trim($vars['text']);
      $tnews->Text = $new_value;


      // determine edits
      if( $old_vals['status'] != $tnews->Status ) $edits[] = T_('Status');
      if( $old_vals['flags'] != $tnews->Flags ) $edits[] = T_('Flags');
      if( $old_vals['publish'] != $tnews->Published ) $edits[] = T_('Publish Time');
      if( $old_vals['subject'] != $tnews->Subject ) $edits[] = T_('Subject');
      if( $old_vals['text'] != $tnews->Text ) $edits[] = T_('Text');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

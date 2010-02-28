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
require_once( 'include/rating.php' );
require_once( 'tournaments/include/tournament_utils.php' );
require_once( 'tournaments/include/tournament.php' );
require_once( 'tournaments/include/tournament_director.php' );
require_once( 'tournaments/include/tournament_status.php' );

$GLOBALS['ThePage'] = new Page('TournamentDirectorEdit');

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');
   if( !ALLOW_TOURNAMENTS )
      error('feature_disabled', 'Tournament.edit_director');

   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used (TD=tournament-director)
     tid=                  : add new TD for tournament
     td_check&cuser=       : load TD-user for editing (new or existing) TD
     tid=&uid=             : edit new or existing TD
     td_preview&tid=&uid=  : preview for tournament-save
     td_save&uid=          : update (replace) TD in database
     td_delete&uid=        : remove TD (need confirm)
     td_delete&confirm=1&uid= : remove TD (confirmed)
     td_cancel             : cancel remove-confirmation
*/

   $tid = (int)@$_REQUEST['tid'];
   if( $tid < 0 ) $tid = 0;
   $tourney = Tournament::load_tournament( $tid ); // existing tournament ?
   if( is_null($tourney) )
      error('unknown_tournament', "Tournament.edit_director.find_tournament($tid)");
   $tstatus = new TournamentStatus( $tourney );

   if( !$tourney->allow_edit_directors($my_id, false) )
      error('tournament_director_edit_not_allowed', "Tournament.edit_director.edit($tid,$my_id)");

   if( @$_REQUEST['td_delete'] ) // at least one TD remaining ?
      TournamentDirector::assert_min_directors( $tid, $tourney->Status );

   if( @$_REQUEST['td_cancel'] ) // cancel delete
      jump_to("tournaments/list_directors.php?tid=$tid");

   $uid = (int) @$_REQUEST['uid'];
   $user = trim(get_request_arg('user')); //Handle
   if( $uid < 0 ) $uid = 0;
   $has_user = ( $uid || $user != '' ); // has in vars, can still be unknown

   // new+del needs special rights
   $owner_allow_edit = $tourney->allow_edit_directors($my_id, true);
   if( !$has_user && !$owner_allow_edit )
      error('tournament_director_new_del_not_allowed', "Tournament.edit_director.new_del($tid,$my_id)");

   // identify user from $uid and $user: other-player (=user to add/edit)
   $tduser_row = TournamentDirector::load_user_row( $uid, $user );

   $errors = $tstatus->check_edit_status( TournamentDirector::get_edit_tournament_status() );
   if( !TournamentUtils::isAdmin() && $tourney->isFlagSet(TOURNEY_FLAG_LOCK_ADMIN) )
      $errors[] = $tourney->buildAdminLockText();

   $tduser_errors = array();
   if( $tduser_row ) // valid user
   {
      $uid = $tduser_row['ID'];
      $user = $tduser_row['Handle'];
   }
   elseif( $has_user )
      $tduser_errors[] = T_('Unknown user');


   $director = null;
   if( count($tduser_errors) == 0 && $uid )
      $director = TournamentDirector::load_tournament_director( $tid, $uid ); // existing TD ?
   if( is_null($director) )
      $director = new TournamentDirector( $tid, $uid ); // new TD

   if( $uid && $owner_allow_edit && @$_REQUEST['td_delete'] && @$_REQUEST['confirm'] && count($errors) == 0 ) // delete TD
   {
      $director->delete();
      jump_to("tournaments/list_directors.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode(T_('Tournament director removed!')) );
   }

   // init
   $arr_flags = array(
      TD_FLAG_GAME_END        => 'flag_gend',
      TD_FLAG_GAME_ADD_TIME   => 'flag_addtime',
   );

   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $director );
   $errors = array_merge( $errors, $input_errors );

   // persist TD in database
   if( $uid && @$_REQUEST['td_save'] && !@$_REQUEST['td_preview'] && count($errors) == 0 )
   {
      $director->persist();
      jump_to("tournaments/list_directors.php?tid=$tid".URI_AMP."sysmsg="
            . urlencode(T_('Tournament director saved!')) );
   }


   $page = "edit_director.php";
   if( @$_REQUEST['td_delete'] )
      $title = T_('Tournament Director Removal for [%s]');
   else
      $title = T_('Tournament Director Edit for [%s]');
   $title = sprintf( $title, $tourney->Title );


   // ---------- Tournament-Director EDIT form ------------------------------

   $tdform = new Form( 'tournamentdirector', $page, FORM_POST );
   $tdform->add_hidden( 'tid', $tid );

   if( count($errors) )
   {
      $tdform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', TournamentUtils::buildErrorListString(T_('There are some errors'), $errors) ));
   }

   if( $uid <= 0 ) // ask for user to add/edit
   {
      $tdform->add_row( array(
            'DESCRIPTION',  T_('Userid'),
            'TEXTINPUT',    'user', 16, 16, textarea_safe($user),
            'SUBMITBUTTON', 'td_check', T_('Check user') ));
      if( count($tduser_errors) )
         $tdform->add_row( array( 'TAB', 'TEXT', TournamentUtils::buildErrorListString( '', $tduser_errors ) ));
   }
   else // edit user (no change of user-id allowed)
   {
      $tdform->add_row( array(
            'DESCRIPTION', T_('Userid'),
            'TEXT',        $user ));
      $tdform->add_row( array(
            'DESCRIPTION', T_('Name'),
            'TEXT',        user_reference( REF_LINK, 1, '', $tduser_row ) ));
      $tdform->add_row( array(
            'DESCRIPTION', T_('Rating'),
            'TEXT',        echo_rating( @$tduser_row['Rating2'], true, $uid ) ));
      $lastaccess = @$tduser_row['X_Lastaccess'];
      $tdform->add_row( array(
            'DESCRIPTION', T_('Last access'),
            'TEXT',        ( ($lastaccess > 0) ? date(DATE_FMT2, $lastaccess) : '' ) ));

      if( $tduser_row )
      {
         if( !@$_REQUEST['td_delete'] )
         {
            if( $owner_allow_edit )
            {
               $first = true;
               foreach( $arr_flags as $flag => $name )
               {
                  $arr = ($first) ? array( 'DESCRIPTION', T_('Flags') ) : array( 'TAB' );
                  $first = false;
                  array_push( $arr,
                     'CHECKBOX', $name, 1, TournamentDirector::getFlagsText($flag), ($director->Flags & $flag) );
                  $tdform->add_row( $arr );
               }
            }
            else
            {
               $tdform->add_row( array(
                     'DESCRIPTION', T_('Flags'),
                     'TEXT',        $director->formatFlags(), ));
            }

            $tdform->add_row( array(
                  'DESCRIPTION', T_('Comment'),
                  'TEXTAREA', 'comment', 60, 3, $director->Comment,
                  'BR', 'TEXT', span('EditNote', T_('(Keep comment short, max. 255 chars)')) ));
            $preview_descr = T_('Preview');
         }
         else
            $preview_descr = T_('Comment');

         $tdform->add_row( array(
               'DESCRIPTION', T_('Unsaved edits'),
               'TEXT',        span('TWarning', implode(', ', $edits), '[%s]') ));

         if( @$_REQUEST['td_preview'] || $director->Comment != '' )
         {
            $tdform->add_row( array(
                  'DESCRIPTION', $preview_descr,
                  'TEXT', make_html_safe( $director->Comment, true ) ));
         }

         if( @$_REQUEST['td_delete'] )
         {
            $tdform->add_hidden( 'confirm', 1 );
            $tdform->add_row( array(
               'TAB', 'CELL', 1, '', // align submit-buttons
               'SUBMITBUTTON', 'td_delete', T_('Remove tournament director'),
               'TEXT', SMALL_SPACING,
               'SUBMITBUTTON', 'td_cancel', T_('Cancel') ));
         }
         else
         {
            $tdform->add_row( array(
                  'TAB', 'CELL', 1, '', // align submit-buttons
                  'SUBMITBUTTON', 'td_save', T_('Save tournament director'),
                  'TEXT', SMALL_SPACING,
                  'SUBMITBUTTON', 'td_preview', T_('Preview'),
               ));
         }

         $tdform->add_hidden( 'uid', $uid );
      }
   }//edit


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $tdform->echo_string();


   $menu_array = array();
   $menu_array[T_('Tournament info')] = "tournaments/view_tournament.php?tid=$tid";
   $menu_array[T_('Tournament directors')] = "tournaments/list_directors.php?tid=$tid";
   if( $owner_allow_edit )
      $menu_array[T_('Add tournament director')] =
         array( 'url' => "tournaments/edit_director.php?tid=$tid", 'class' => 'TAdmin' );
   if( $tourney->allow_edit_tournaments($my_id) )
      $menu_array[T_('Manage tournament')] =
         array( 'url' => "tournaments/manage_tournament.php?tid=$tid", 'class' => 'TAdmin' );

   end_page(@$menu_array);
}


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$tdir )
{
   global $arr_flags, $owner_allow_edit;

   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['td_save'] || @$_REQUEST['td_preview'] );

   // read from props or set defaults
   $vars = array(
      'flags'     => $tdir->Flags,
      'comment'   => $tdir->Comment,
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
      if( $owner_allow_edit )
      {
         $new_value = 0;
         foreach( $arr_flags as $flag => $name )
         {
            if( $vars[$name] )
               $new_value |= $flag;
         }
         $tdir->Flags = $new_value;
      }

      $tdir->Comment = trim($vars['comment']);

      // determine edits
      if( $old_vals['flags'] != $tdir->Flags ) $edits[] = T_('Flags#edits');
      if( $old_vals['comment'] != $tdir->Comment ) $edits[] = T_('Comment#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

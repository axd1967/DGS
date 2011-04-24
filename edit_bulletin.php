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

$TranslateGroups[] = "Bulletin";

require_once 'include/std_functions.php';
require_once 'include/gui_functions.php';
require_once 'include/form_functions.php';
require_once 'include/db/bulletin.php';
require_once 'include/gui_bulletin.php';
require_once 'include/classlib_user.php';
require_once 'tournaments/include/tournament.php';

$GLOBALS['ThePage'] = new Page('BulletinEdit');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   if( $my_id <= GUESTS_ID_MAX )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     gid=                     : add new bulletin TargetType=MPG

     bid=                     : edit existing bulletin
     preview&bid=             : preview for bulletin-save
     save&bid=                : save new/updated bulletin
*/

   $bid = (int) get_request_arg('bid');
   if( $bid < 0 ) $bid = 0;
   $gid = (int) get_request_arg('gid');
   if( $gid < 0 ) $gid = 0;

   // init
   if( $bid > 0 )
   {
      $bulletin = Bulletin::load_bulletin($bid);
      if( is_null($bulletin) )
         error('unknown_bulletin', "edit_bulletin.check.load_bulletin($bid)");
      if( $bulletin->uid != $my_id )
         error('bulletin_edit_not_allowed', "edit_bulletin.check.edit.author($bid)");
      if( $bulletin->Status == BULLETIN_STATUS_DELETE || $bulletin->Status == BULLETIN_STATUS_ARCHIVE )
         error('bulletin_edit_not_allowed', "edit_bulletin.check.edit.status($bid,{$bulletin->Status})");

      $gid = $bulletin->gid; // DB-data has prio
   }
   else
      $bulletin = Bulletin::new_bulletin( /*adm*/false );

   // init new bulletin
   $b_old_status = $bulletin->Status;
   if( $bid == 0 )
   {
      if( $gid > 0 ) // MPG-bulletin, no admin-ACK needed
      {
         $bulletin->setStatus( BULLETIN_STATUS_SHOW );
         $bulletin->setTargetType( BULLETIN_TRG_MPG );
         $bulletin->gid = $gid;
      }
      else
         error('invalid_args', "edit_bulletin.check.gid($bid,$gid)");
   }


   // check + parse edit-form
   list( $vars, $edits, $input_errors ) = parse_edit_form( $bulletin );
   $errors = $input_errors;

   // save bulletin-object with values from edit-form
   if( @$_REQUEST['save'] && !@$_REQUEST['preview'] && count($errors) == 0 )
   {
      ta_begin();
      {//HOT-section to save bulletin-data
         $bulletin->persist();
         $bid = $bulletin->ID;

         if( $bulletin->CountReads > 0 && $b_old_status != BULLETIN_STATUS_SHOW && $bulletin->Status == BULLETIN_STATUS_SHOW )
            Bulletin::reset_bulletin_read( $bid );

         $bulletin->update_count_players('edit_bullet');
      }
      ta_end();

      jump_to("edit_bulletin.php?bid=$bid".URI_AMP."sysmsg=". urlencode(T_('Bulletin saved!')) );
   }

   $page = "edit_bulletin.php";
   $title = ($bid) ? T_('Edit Bulletin') : T_('New Bulletin');


   // ---------- Bulletin EDIT form --------------------------------

   $bform = new Form( 'bulletinEdit', $page, FORM_POST );
   $bform->add_hidden( 'bid', $bid );
   if( $bid == 0 )
   {
      $bform->add_hidden( 'gid', $bulletin->gid );
   }

   $bform->add_row( array(
         'DESCRIPTION', T_('Bulletin ID'),
         'TEXT',        ($bid ? anchor( $base_path."edit_bulletin.php?bid=$bid", $bid )
                              : T_('NEW bulletin#bulletin')), ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Bulletin Author'),
         'TEXT',        $bulletin->User->user_reference(), ));
   if( $bulletin->Lastchanged > 0 )
      $bform->add_row( array(
            'DESCRIPTION', T_('Last changed'),
            'TEXT',        formatDate($bulletin->Lastchanged), ));
   if( !is_null($bulletin->Tournament) )
      $bform->add_row( array(
            'DESCRIPTION', T_('Tournament#bulletin'),
            'TEXT',        $bulletin->Tournament->build_info(1), ));
   if( $bulletin->gid > 0 )
      $bform->add_row( array(
            'DESCRIPTION', T_('Game#bulletin'),
            'TEXT',        game_reference( REF_LINK, 1, '', $bulletin->gid ), ));
   if( $bid )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Admin Note'),
            'TEXT',        span('FormWarning', $bulletin->AdminNote), ));
   }

   $bform->add_row( array( 'HR' ));

   if( count($errors) )
   {
      $bform->add_row( array(
            'DESCRIPTION', T_('Error'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
      $bform->add_empty_row();
   }

   $bform->add_row( array(
         'DESCRIPTION', T_('Category#bulletin'),
         'TEXT',        GuiBulletin::getCategoryText($bulletin->Category) ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Status#bulletin'),
         'TEXT',        GuiBulletin::getStatusText($b_old_status) .
                        ' => ' . GuiBulletin::getStatusText($bulletin->Status) ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Target Type#bulletin'),
         'TEXT',        GuiBulletin::getTargetTypeText($bulletin->TargetType) ));


   $bform->add_empty_row();
   $bform->add_row( array(
         'DESCRIPTION', T_('Publish Time'),
         'TEXTINPUT',   'publish_time', 20, 30, $vars['publish_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE)), ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Expire Time'),
         'TEXTINPUT',   'expire_time', 20, 30, $vars['expire_time'],
         'TEXT',  '&nbsp;' . span('EditNote', sprintf( T_('(Date format [%s])'), FMT_PARSE_DATE ) .
                                  ', ' . T_('can be empty#bulletin_expire')), ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Subject'),
         'TEXTINPUT',   'subject', 80, 255, $vars['subject'] ));
   $bform->add_row( array(
         'DESCRIPTION', T_('Text'),
         'TEXTAREA',    'text', 80, 10, $vars['text'] ));

   $bform->add_row( array(
         'DESCRIPTION', T_('Unsaved edits'),
         'TEXT',        span('TWarning', implode(', ', $edits), '[%s]'), ));

   $bform->add_empty_row();
   $bform->add_row( array(
         'TAB', 'CELL', 1, '', // align submit-buttons
         'SUBMITBUTTON', 'save', T_('Save bulletin'),
         'TEXT', SMALL_SPACING,
         'SUBMITBUTTON', 'preview', T_('Preview'),
      ));

   if( @$_REQUEST['preview'] || $bulletin->Subject . $bulletin->Text != '' )
   {
      $bform->add_empty_row();
      $bform->add_row( array(
            'DESCRIPTION', T_('Preview'),
            'OWNHTML', '<td class="Preview">' . GuiBulletin::build_view_bulletin($bulletin) . '</td>', ));
   }


   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   $bform->echo_string();


   $menu_array = array();
   $menu_array[T_('My Bulletins')] = "list_bulletins.php?text=0".URI_AMP."read=2".URI_AMP."mine=1".URI_AMP."no_adm=1";
   if( $gid > 0 )
      $menu_array[T_('Show game-players')] = "game_players.php?gid=$gid";

   end_page(@$menu_array);
}//main


// return [ vars-hash, edits-arr, errorlist ]
function parse_edit_form( &$bulletin )
{
   $edits = array();
   $errors = array();
   $is_posted = ( @$_REQUEST['save'] || @$_REQUEST['preview'] );

   // read from props or set defaults
   $vars = array(
      'publish_time'    => formatDate($bulletin->PublishTime),
      'expire_time'     => formatDate($bulletin->ExpireTime),
      'subject'         => $bulletin->Subject,
      'text'            => $bulletin->Text,
      'gid'             => $bulletin->gid,
   );

   $old_vals = array() + $vars; // copy to determine edit-changes
   // read URL-vals into vars
   foreach( $vars as $key => $val )
      $vars[$key] = get_request_arg( $key, $val );

   // parse URL-vars
   if( $is_posted )
   {
      $old_vals['publish_time'] = $bulletin->PublishTime;
      $old_vals['expire_time'] = $bulletin->ExpireTime;

      // check/correct gid
      if( $bulletin->TargetType != BULLETIN_TRG_MPG )
         $bulletin->gid = 0;
      else if( $bulletin->gid > 0 )
      {
         $game_row = Bulletin::load_multi_player_game($bulletin->gid);
         if( is_null($game_row) )
            $errors[] = sprintf( T_('No game found for game-ID [%s]!'), $bulletin->gid );
         elseif( $game_row['GameType'] != GAMETYPE_TEAM_GO && $game_row['GameType'] != GAMETYPE_ZEN_GO )
            $errors[] = sprintf( T_('Game-ID [%s] must reference a multi-player-game!'), $bulletin->gid );
      }


      $parsed_value = parseDate( T_('Publish time for bulletin'), $vars['publish_time'] );
      if( is_numeric($parsed_value) )
      {
         $bulletin->PublishTime = $parsed_value;
         $vars['publish_time'] = formatDate($bulletin->PublishTime);
      }
      else
         $errors[] = $parsed_value;
      if( $bulletin->PublishTime == 0 )
         $errors[] = T_('Missing Publish time#bulletin');

      $parsed_value = parseDate( T_('Expire time for bulletin'), $vars['expire_time'] );
      if( is_numeric($parsed_value) )
      {
         $bulletin->ExpireTime = $parsed_value;
         $vars['expire_time'] = formatDate($bulletin->ExpireTime);
      }
      else
         $errors[] = $parsed_value;

      $new_value = trim($vars['subject']);
      if( strlen($new_value) < 8 )
         $errors[] = T_('Bulletin subject missing or too short');
      else
         $bulletin->Subject = $new_value;

      $new_value = trim($vars['text']);
      $bulletin->Text = $new_value;


      // determine edits
      if( $old_vals['gid'] != $bulletin->gid ) $edits[] = T_('Game-ID#edits');
      if( $old_vals['publish_time'] != $bulletin->PublishTime ) $edits[] = T_('PublishTime#edits');
      if( $old_vals['expire_time'] != $bulletin->ExpireTime ) $edits[] = T_('ExpireTime#edits');
      if( $old_vals['subject'] != $bulletin->Subject ) $edits[] = T_('Subject#edits');
      if( $old_vals['text'] != $bulletin->Text ) $edits[] = T_('Text#edits');
   }

   return array( $vars, array_unique($edits), $errors );
}//parse_edit_form

?>

<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/contacts.php" );

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error('not_logged_in');

   if( $player_row['Handle'] == 'guest' )
      error('not_allowed_for_guest');

/* Actual REQUEST calls used:
     (no args)             : add new contact
     contact_check&cuser=  : load contact-user for editing (new or existing) contact
     cid=                  : edit new or existing contact
     contact_new           : reset form (add new contact)
     contact_save&cid=     : update (replace) contact in database
     contact_delete&cid=   : remove contact (need confirm)
     contact_delete&confirm=1&cid= : remove contact (confirmed)
     contact_cancel        : cancel remove-confirmation
*/

   if ( @$_REQUEST['contact_cancel'] ) // cancel delete
      jump_to("list_contacts.php");

   $my_id = $player_row['ID'];
   $cid = (int) @$_REQUEST['cid'];
   $cuser = get_request_arg('cuser'); //Handle

   if ( $cid < 0 )
      $cid = 0;

   if ( @$_REQUEST['contact_new'] ) // reset
   {
      $cid = 0;
      $cuser = '';
   }

   if( $cuser == 'guest' or $cid == 1 )
      error('not_allowed_for_guest');

   // identify cid from cid and cuser
   $other_row = null; // other-player (=contact to add/edit)
   if ( $cid )
   { // have cid to edit new or existing
      $result = mysql_query("SELECT ID, Name, Handle FROM Players WHERE ID=$cid")
         or error('mysql_query_failed', 'edit_contact.find_user.id');
      if ( mysql_affected_rows() == 1 )
         $other_row = mysql_fetch_assoc( $result );
      mysql_free_result($result);
   }
   if ( !$other_row and $cuser != '' ) // not identified yet
   { // load cid for userid
      $qhandle = mysql_addslashes($cuser);
      $result = mysql_query("SELECT ID, Name, Handle FROM Players WHERE Handle='$qhandle'")
         or error('mysql_query_failed', 'edit_contact.find_user.handle');
      if ( mysql_affected_rows() == 1 )
         $other_row = mysql_fetch_assoc( $result );
      mysql_free_result($result);
   }

   $errormsg = null;
   if ( $other_row ) // valid contact
   {
      $cid = $other_row['ID'];
      $cuser = $other_row['Handle'];
      if ( $my_id == $cid )
      {
         $other_row = null;
         $cid = 0;
         $errormsg = '('.T_('Can\'t add myself as contact').')';
      }
   }
   else
      $errormsg = '('.T_('unknown user').')';

   $contact = null;
   if ( !$errormsg and $cid )
      $contact = Contact::load_contact( $my_id, $cid ); // existing contact ?
   if ( is_null($contact) )
      $contact = Contact::new_contact( $my_id, $cid ); // new contact

   if ( $cid and @$_REQUEST['contact_delete'] and @$_REQUEST['confirm'] )
   {
      $contact->delete_contact();
      jump_to("list_contacts.php?sysmsg=". urlencode(T_('Contact removed!')) );
   }

   // update contact-object with values from edit-form
   if ( $cid and @$_REQUEST['contact_save'] )
   {
      $contact->parse_system_flags(); // read sfl_...
      $contact->parse_user_flags(); // read ufl_...
      $contact->set_note( get_request_arg('note') );

      $contact->update_contact();
      jump_to("list_contacts.php?sysmsg=". urlencode(T_('Contact saved!')) );
      //jump_to("edit_contact.php?cid=$cid".URI_AMP."sysmsg=". urlencode(T_('Contact saved!')) );
   }


   $page = "edit_contact.php";
   if ( @$_REQUEST['contact_delete'] )
      $title = T_('Contact removal');
   else
      $title = T_('Contact edit');


   $cform = new Form( 'contact', $page, FORM_POST );
   $cform->set_layout( FLAYOUT_GLOBAL, ( $cid ? '1,2|3,4' : '1' ) );

   $cform->set_area(1);
   if ( $cid <= 0 ) // ask for contact to add/edit
   {
      $cform->add_row( array(
         'DESCRIPTION',  T_('Userid'),
         'TEXTINPUT',    'cuser', 16, 16, textarea_safe($cuser),
         'SUBMITBUTTON', 'contact_check', T_('Check contact') ));
      if ( !is_null($errormsg) )
         $cform->add_row( array(
            'TAB', 'TEXT', $errormsg ));
   }
   else // edit contact (no change of contact-id allowed)
   {
      $cform->add_row( array(
         'DESCRIPTION',  T_('Userid'),
         'TEXT',         $cuser ));
      $cform->add_row( array(
            'DESCRIPTION', T_('Name'),
            'TEXT', user_reference( REF_LINK, 1, '', $other_row ) ));

      if( $other_row )
      {
         if ( @$_REQUEST['contact_delete'] )
         {
            $cform->add_hidden( 'confirm', 1 );
            $cform->add_row( array(
               'SUBMITBUTTON', 'contact_delete', T_('Remove contact'),
               'SUBMITBUTTON', 'contact_cancel', T_('Cancel') ));
         }
         else
         {
            $cform->set_area(2);
            $cform->add_row( array(
                  'DESCRIPTION', T_('System categories') )); // system-flags
            $cform->set_layout( FLAYOUT_AREACONF, 2, array(
                  FAC_TABLE => 'class=EditOptions',
               ) );
            foreach( $ARR_CONTACT_SYSFLAGS as $sysflag => $arr )
            {
               $cform->add_row( array(
                  'CHECKBOX', $arr[0], 1, $arr[1], $contact->is_sysflag_set($sysflag) ));
            }

            $cform->set_area(3);
            $cform->add_row( array(
                  'DESCRIPTION', T_('User categories') )); // user-flags
            $cform->set_layout( FLAYOUT_AREACONF, 3, array(
                  FAC_TABLE => 'class=EditOptions',
               ) );
            reset($ARR_CONTACT_USERFLAGS);
            while( list($userflag, $arr) = each($ARR_CONTACT_USERFLAGS) )
            {
               $row_arr = array();
               array_push( $row_arr,
                  'CHECKBOX', $arr[0], 1, $arr[1], $contact->is_userflag_set($userflag) );
               if( list($userflag, $arr) = each($ARR_CONTACT_USERFLAGS) )
                  array_push( $row_arr, 'TD',
                     'CHECKBOX', $arr[0], 1, $arr[1], $contact->is_userflag_set($userflag) );
               else
                  array_push( $row_arr, 'TAB');
               $cform->add_row( $row_arr );
            }

            $cform->set_area(4);
            $cform->add_row( array(
                  'DESCRIPTION', T_('Notes'),
                  'TEXTAREA', 'note', 60, 3, textarea_safe($contact->note),
                  'BR', 'TEXT', '(' . T_('keep notes short, max. 255 chars') . ')'
                  ));

            $cform->add_row( array(
               'SUBMITBUTTON', 'contact_save', T_('Save contact'),
               ));
         }

         $cform->add_hidden( 'cid', $cid );
      }
   }

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=Header>$title</h3>\n";

   echo "<CENTER>\n";
   $cform->echo_string();
   echo "</CENTER><BR>\n";

   $menu_array[T_('Show contacts')] = "list_contacts.php";
   if( $cid > 0 )
      $menu_array[T_('Add new contact')] = $page;

   end_page(@$menu_array);
}
?>

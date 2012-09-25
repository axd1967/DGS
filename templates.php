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

$TranslateGroups[] = "Common";

require_once 'include/std_functions.php';
require_once 'include/game_functions.php';
require_once 'include/form_functions.php';
require_once 'include/table_columns.php';
require_once 'include/classlib_profile.php';

define('CMD_NEW',  'new');
define('CMD_LIST', 'list');
define('CMD_DELETE', 'del');

define('FACT_SAVE', 'save');
define('FACT_CANCEL', 'cancel');


{
/* Actual REQUEST calls used:
     cmd=list|''                 : show existing templates
     cmd=new&type=&data=         : redirected from send-msg/invite/new-game to save template with data
     save&cmd=new&type=&data=    : save template
     cmd=del&tmpl=               : remove template, show confirm page
     cancel                      : cancel operation, reload template-list
     ...&to=                     : forward user-id to-handle to use with template
*/

   connect2mysql();
   $logged_in = who_is_logged($player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'templates');

   $page = "templates.php";

   $my_id = $player_row['ID'];
   $is_guest = ( $my_id <= GUESTS_ID_MAX );
   $cmd = get_request_arg('cmd', CMD_LIST);

   $arg_to = trim( get_request_arg('to') );
   $url_to = ( (string)$arg_to != '' ) ? URI_AMP.'to=' . urlencode($arg_to) : '';


   // ---------- handle actions ----------

   if( @$_REQUEST[FACT_CANCEL] )
      jump_to($page.'?to=' . urlencode($arg_to));

   $count_profiles = $arr_profiles = NULL;
   if( $cmd == CMD_LIST || $cmd == CMD_NEW )
   {
      $arr_profiles = Profile::load_profiles( $my_id, ProfileTemplate::known_template_types(), /*templ*/true );
      $count_profiles = count($arr_profiles);
   }

   $is_save = @$_REQUEST[FACT_SAVE];
   if( $is_guest && ($is_save || $cmd) )
      error('not_allowed_for_guest', 'templates.save_cmd');

   $errors = NULL;
   $auto_name = @$_REQUEST['name'];
   if( $cmd == CMD_NEW && $is_save )
   {
      $name = trim( get_request_arg('name') );
      $replace = (int)@$_REQUEST['replace'];
      list( $errors, $replace_name ) = check_save_template( $arr_profiles, $name, $replace );
      if( !is_null($replace_name) )
         $name = $auto_name = $replace_name;

      if( count($errors) == 0 ) // save
      {
         save_template( $my_id, $name, $replace );
         jump_to($page."?sysmsg=" . urlencode(T_('Template saved!#tmpl')));
      }
   }
   elseif( $cmd == CMD_DELETE )
   {
      $del_id = (int)get_request_arg('tmpl');
      $del_prof = check_delete_template( $my_id, $del_id );
      if( $is_save && !is_null($del_prof) )
      {
         $del_prof->delete_profile();
         jump_to($page."?sysmsg=" . urlencode(T_('Template deleted!#tmpl')));
      }
   }


   // ---------- page start ----------

   if( is_numeric($count_profiles) )
      $title = sprintf( T_('Templates (used %s of max. %s)'), $count_profiles, MAX_PROFILE_TEMPLATES );
   else
      $title = T_('Templates');
   start_page( $title, true, $logged_in, $player_row );

   echo "<h3 class=Header>$title</h3>\n";

   if( $cmd == CMD_LIST )
      echo_list_templates( $arr_profiles );
   elseif( $cmd == CMD_NEW )
      echo_save_template( $arr_profiles, $errors, $auto_name );
   elseif( $cmd == CMD_DELETE && !$is_save )
      echo_delete_template( $del_prof );

   //echo "<hr>\n<center><pre>\n", print_r($_REQUEST, false), "</pre></center>\n"; //DEBUG

   $menu_array = array();
   ProfileTemplate::add_menu_link( $menu_array, $arg_to );

   end_page(@$menu_array);
}//main



function echo_list_templates( $arr_profiles )
{
   global $page, $url_to;

   $ttable = new Table( 'templates', $page, null, '', TABLE_ROWS_NAVI|TABLE_NO_SORT|TABLE_NO_HIDE );
   $ttable->use_show_rows( false );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ttable->add_tablehead( 1, T_('Actions#header'), 'ImagesRightPadded', 0, '');
   $ttable->add_tablehead( 2, T_('Type#header'), 'Enum', 0, 'Type+');
   $ttable->add_tablehead( 3, T_('Name#header'), 'User', 0, 'Name+');
   $ttable->add_tablehead( 4, T_('Last changed#header'), 'Date', 0, 'Lastchanged-');

   foreach( $arr_profiles as $prof )
   {
      $links = '';
      $url_tmpl = "tmpl={$prof->id}";
      if( $prof->Type == PROFTYPE_TMPL_SENDMSG )
      {
         $links .= anchor( "message.php?mode=NewMessage{$url_to}" . URI_AMP . $url_tmpl,
               image( 'images/send.gif', 'M', '', 'class="Action"' ), T_('Send a message'));
      }
      elseif( $prof->Type == PROFTYPE_TMPL_INVITE )
      {
         $links .= anchor( "message.php?mode=Invite{$url_to}" . URI_AMP . $url_tmpl,
               image( 'images/invite.gif', 'I', '', 'class="Action"' ), T_('Invite'));
         $links .= anchor( "new_game.php?$url_tmpl",
               image( 'images/newgame.gif', 'N', '', 'class="Action"' ), T_('New Game'));
      }
      elseif( $prof->Type == PROFTYPE_TMPL_NEWGAME )
      {
         if( ProfileTemplate::is_valid_template_raw_check( $prof->Type, PROFTYPE_TMPL_INVITE, $prof->Text ) )
         {
            $links .= anchor( "message.php?mode=Invite{$url_to}" . URI_AMP . $url_tmpl,
                  image( 'images/invite.gif', 'I', '', 'class="Action"' ), T_('Invite'));
         }
         $links .= anchor( "new_game.php?$url_tmpl",
               image( 'images/newgame.gif', 'N', '', 'class="Action"' ), T_('New Game'));
      }
      $links .= anchor( $page."?cmd=".CMD_DELETE . URI_AMP . $url_tmpl,
            image( 'images/trashcan.gif', 'X', '', 'class="Action"' ), T_('Delete template'));

      $ttable->add_row( array(
            1 => $links,
            2 => ProfileTemplate::get_template_type_text( $prof->Type ),
            3 => $prof->Name,
            4 => ($prof->Lastchanged > 0 ? date(DATE_FMT2, $prof->Lastchanged) : '' ),
         ));
   }

   $ttable->echo_table();
}//echo_list_templates


// form with fields to save-template
function echo_save_template( $arr_profiles, $errors, $new_name )
{
   global $page;

   $ttable = new Table( 'savetmpl', $page, null, '', TABLE_ROWS_NAVI|TABLE_NO_SORT|TABLE_NO_HIDE );
   $ttable->use_show_rows( false );

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $ttable->add_tablehead( 1, T_('Replace#header'), 'Center', 0, '');
   $ttable->add_tablehead( 2, T_('Type#header'), 'Enum', 0, 'Type+');
   $ttable->add_tablehead( 3, T_('Name#header'), 'User', 0, 'Name+');
   $ttable->add_tablehead( 4, T_('Last changed#header'), 'Date', 0, 'Lastchanged-');

   $form = new Form('template', $page, FORM_POST, false);
   $form->set_config( FEC_EXTERNAL_FORM, true );
   $form->add_hidden('cmd', CMD_NEW);
   $form->add_hidden('type', $_REQUEST['type']);
   $form->add_hidden('data', $_REQUEST['data']);

   if( !is_null($errors) && count($errors) )
   {
      $form->add_empty_row();
      $form->add_row( array(
            'DESCRIPTION', T_('Errors'),
            'TEXT', buildErrorListString(T_('There are some errors'), $errors) ));
   }

   $form->add_row( array(
         'DESCRIPTION', T_('Name#tmpl'),
         'TEXTINPUT', 'name', 60, 70, $new_name,
         'SUBMITBUTTON', FACT_SAVE, T_('Save Template'), ));

   $form->add_empty_row();
   $form->add_row( array( 'TAB', 'TEXT',
         T_('Select a template to replace an existing entry').':' ));
   $form->add_empty_row();

   $key = 'replace';
   $val_replace = (int)@$_REQUEST[$key];
   foreach( $arr_profiles as $prof )
   {
      $ttable->add_row( array(
            1 => $form->print_insert_radio_buttonsx( $key, array( $prof->id => '' ), $val_replace ),
            2 => ProfileTemplate::get_template_type_text( $prof->Type ),
            3 => $prof->Name,
            4 => ($prof->Lastchanged > 0 ? date(DATE_FMT2, $prof->Lastchanged) : '' ),
         ));
   }
   $ttable->add_row( array(
         1 => $form->print_insert_radio_buttonsx( $key, array( 0 => '' ), $val_replace ), // default
         3 => T_('New template#tmpl'),
      ));

   echo $form->print_start_default(),
      $form->get_form_string(),
      $ttable->echo_table(),
      $form->print_end();
}//echo_save_template

// return arr( errors, replace_name|null ) : replace_name is set to name of entry to replace if name was missing
function check_save_template( $arr_profiles, $name, $replace )
{
   $errors = array();

   $type = (int)@$_REQUEST['type'];
   if( !ProfileTemplate::is_valid_type($type) )
      $errors[] = T_('Invalid type for template to save!#tmpl');

   $datalen = strlen(@$_REQUEST['data']);
   if( $datalen > MAX_PROFILE_TEMPLATES_DATA )
      $errors[] = sprintf( T_('Data to store for template exceeds limit of %s bytes (by %s bytes)!#tmpl'),
         MAX_PROFILE_TEMPLATES_DATA, $datalen - MAX_PROFILE_TEMPLATES_DATA );

   $miss_name = ( (string)$name == '' );

   if( !is_null($arr_profiles) )
   {
      // check for unique name of user
      foreach( $arr_profiles as $prof )
      {
         if( $replace > 0 && $replace == $prof->id ) // skip replace-entry for uniq-check
            continue;
         if( strcasecmp($prof->Name, $name) == 0 )
         {
            $errors[] = T_('Name of template must be unique!#tmpl');
            break;
         }
      }

      // check template-count
      if( count($arr_profiles) >= MAX_PROFILE_TEMPLATES && $replace <= 0 )
         $errors[] = T_('Max. limit of templates is reached. You have to replace an existing entry!#tmpl');
   }

   // check that replace-entry exists and is of user
   $replace_miss_name = null;
   if( $replace > 0 )
   {
      $can_replace = false;
      foreach( $arr_profiles as $prof )
      {
         if( $prof->id == $replace )
         {
            $can_replace = true;
            if( $miss_name )
               $replace_miss_name = $prof->Name;
            break;
         }
      }
      if( !$can_replace )
         $errors[] = T_('Replace selection is faulty, because the entry to replace does not exist for this user.#tmpl');
   }

   if( $miss_name && is_null($replace_miss_name) )
      $errors[] = T_('Missing name for template!#tmpl');

   return array( $errors, $replace_miss_name );
}//check_save_template

// insert or update profile
function save_template( $uid, $name, $replace )
{
   $prof_type = (int)@$_REQUEST['type'];

   if( $replace > 0 )
      $profile = Profile::load_profile_by_id( $replace, $uid );
   else
      $profile = ProfileTemplate::new_default_profile( $uid, $prof_type );

   $profile->set_type( $prof_type );
   $profile->Name = $name;
   $profile->set_text( @$_REQUEST['data'] );

   $profile->save_profile();
}//save_template

function check_delete_template( $uid, $prof_id )
{
   $profile = Profile::load_profile_by_id( $prof_id, $uid );
   if( is_null($profile) )
      error('invalid_profile', "templates.check_delete_template.find($prof_id,$uid)");

   if( !ProfileTemplate::is_valid_type($profile->Type) )
      error('invalid_profile', "templates.check_delete_template.check.type($prof_id,$uid,{$profile->Type})");

   return $profile;
}//check_delete_template

function echo_delete_template( $profile )
{
   global $page;

   $form = new Form( 'deltemplate', $page, FORM_POST );
   $form->add_hidden( 'cmd', CMD_DELETE );
   $form->add_hidden( 'tmpl', $profile->id );

   $form->add_row( array( 'HEADER', T_('Delete template#tmpl') ));

   $form->add_row( array( 'DESCRIPTION', T_('Type#tmpl'),
                          'TEXT', ProfileTemplate::get_template_type_text( $profile->Type ), ));
   $form->add_row( array( 'DESCRIPTION', T_('Name#tmpl'),
                          'TEXT', $profile->Name, ));
   $form->add_row( array( 'DESCRIPTION', T_('Last changed'),
                          'TEXT', ($profile->Lastchanged > 0 ? date(DATE_FMT2, $profile->Lastchanged) : '' ), ));

   $form->add_empty_row();
   $form->add_row( array( 'TAB', 'TEXT', T_('Are you sure to delete this template ?#tmpl') ));
   $form->add_row( array( 'TAB', 'CELL', 1, '', // align submit-buttons
                          'SUBMITBUTTON', FACT_SAVE, T_('Yes'),
                          'SUBMITBUTTON', FACT_CANCEL, T_('No'), ));

   $form->echo_string();
}//echo_delete_template

?>

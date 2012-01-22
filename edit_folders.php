<?php
/*
Dragon Go Server
Copyright (C) 2001-2012  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Users";

require_once( 'include/globals.php' );
require_once( "include/std_functions.php" );
require_once( 'include/classlib_userconfig.php' );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('not_logged_in');
   $my_id = $player_row['ID'];
   $cfg_pages = ConfigPages::load_config_pages($my_id);

   init_standard_folders();
   $folders = get_folders($my_id);
   $max_folder = array_reduce(array_keys($folders), "max", USER_FOLDERS-1);

   $cfg_statusfolders = $cfg_pages->get_status_folders();
   $statusfolders = empty($cfg_statusfolders) ? array() : explode( ',', $cfg_statusfolders);
   $old_statusfolders = $statusfolders;

   $sysmsg = '';


   // Update folders

   $folder_query = '';
   $old_status_flags = $cfg_pages->get_status_flags();
   foreach( $_POST as $key => $val )
   {
      if( !preg_match("/^folder(\d+)$/", $key, $matches) ) // filters out negative (special) folders too
         continue;

      $nr = $matches[1]; // >=0

      $bgred = limit(@$_POST["bgred$nr"], 0, 255, 0);
      $bggreen = limit(@$_POST["bggreen$nr"], 0, 255, 0);
      $bgblue = limit(@$_POST["bgblue$nr"], 0, 255, 0);
      $bgalpha = limit(@$_POST["bgalpha$nr"], 0, 255, 255);
      $fgred = limit(@$_POST["fgred$nr"], 0, 255, 0);
      $fggreen = limit(@$_POST["fggreen$nr"], 0, 255, 0);
      $fgblue = limit(@$_POST["fgblue$nr"], 0, 255, 0);

      $bgcolor = RGBA( $bgred, $bggreen, $bgblue, $bgalpha);
      $fgcolor = RGBA( $fgred, $fggreen, $fgblue);
      $name = trim(get_request_arg("folder$nr"));

      $onstatuspage = ( @$_POST["onstatuspage$nr"] == 't' );

      if( $nr >= USER_FOLDERS && ( in_array($nr, $statusfolders) xor $onstatuspage ) )
      {
         if( $onstatuspage )
            $statusfolders[]= $nr;
         else
         {
            $i=array_search( $nr, $statusfolders);
            if($i !== false)
               unset($statusfolders[$i]);
         }
      }
      elseif( ConfigPages::is_system_status_folder($nr) )
      {
         $cfg_pages->set_status_flags_folderbit( $nr, $onstatuspage );
      }

      if( empty($name) && $nr > $max_folder )
         continue;


      $newfolder = array($name, $bgcolor, $fgcolor);
      if( !isset($folders[$nr]) )
      {
         $delete = false;
         $update = false;
         //else insert $newfolder
      }
      else if( $folders[$nr] == $newfolder )
      {
         continue;
      }
      else if( $nr >= USER_FOLDERS )
      {
         if( !empty($name) )
            $delete = false;
         elseif( folder_is_empty($nr, $my_id) )
            $delete = true;
         else
         {
            $sysmsg = T_('A folder must be empty to be deleted!');
            continue;
         }
         $update = !$delete;
      }
      else //STANDARD_FOLDERS
      {
         $delete = ( empty($name) || $STANDARD_FOLDERS[$nr] == $newfolder );
         $update = ( !$delete && $STANDARD_FOLDERS[$nr] != $folders[$nr] );
      }


      if( $delete )
      {
         $folder_query = "DELETE FROM Folders WHERE uid=$my_id AND Folder_nr=$nr LIMIT 1";
      }
      else if( $update )
      {
         list($oldname, $oldbgcolor, $oldfgcolor) = $folders[$nr];

         $folder_query = "UPDATE Folders SET ";
         $updates = array();
         if( $name != $oldname ) $updates[]= "Name='".mysql_addslashes($name)."'";
         if( $bgcolor != $oldbgcolor ) $updates[]= "BGColor='$bgcolor'";
         if( $fgcolor != $oldfgcolor ) $updates[]= "FGColor='$fgcolor'";

         if( !(count($updates) > 0) )
            continue;

         $folder_query .= implode(",", $updates);
         $folder_query .= " WHERE uid=$my_id AND Folder_nr=$nr LIMIT 1";
      }
      else
      {
         $folder_query = "INSERT INTO Folders SET " .
            "uid=$my_id, " .
            "Folder_nr=$nr, " .
            "Name='".mysql_addslashes($name)."', " .
            "BGColor='$bgcolor', " .
            "FGColor='$fgcolor' ";
      }
   } //foreach folders in $_POST


   ta_begin();
   {//HOT-section to update folders
      if( $folder_query )
      {
         db_query( "edit_folders.main($my_id)", $folder_query ); // table Folders
         if( !$sysmsg )
            $sysmsg = T_('Folders adjusted!');
      }

      asort($statusfolders);
      if( $statusfolders != $old_statusfolders || $cfg_pages->get_status_flags() != $old_status_flags )
      {
         $cfg_pages->set_status_folders( implode(',', $statusfolders) );
         $cfg_pages->update_status_folders();
         if( !$sysmsg )
            $sysmsg = T_('Folders adjusted!');
      }
   }
   ta_end();


   //reset folders infos
   $folders = get_folders($my_id);
   $max_folder = array_reduce(array_keys($folders), "max", USER_FOLDERS-1);


   start_page(T_("Edit message folders"), true, $logged_in, $player_row );

   echo "<center>\n";

   $form = new Form( 'folderform', 'edit_folders.php', FORM_POST );
   $form->max_nr_columns = 11;

   $form->add_row( array( 'HEADER', T_('Edit message folders') ) );

   foreach( $folders as $nr => $fld )
   {
      list($name, $bgcolor, $fgcolor) = $fld;
      list($bgred,$bggreen,$bgblue,$bgalpha)= split_RGBA($bgcolor, 0);
      list($fgred,$fggreen,$fgblue,$dummy)= split_RGBA($fgcolor);

      $show_checkbox = $cfg_pages->get_status_folder_visibility($nr);
      if( $show_checkbox < 0 )
         $show_checkbox = in_array($nr, $statusfolders);

      make_folder_form_row($form, $name, $nr,
                           $bgred, $bggreen, $bgblue, $bgalpha, $fgred, $fggreen, $fgblue,
                           $show_checkbox );
   }



// And now two empty ones:

   for($i=$max_folder+1; $i<=$max_folder+2; $i++)
   {
      make_folder_form_row($form, '', $i, 247, 245, 227, 255, 0, 0, 0, false);
   }



   $form->add_row( array(
//                          'HIDDEN', 'sysmsg', T_('Folders adjusted!'),
//                          'SUBMITBUTTON', 'action_preview" accesskey="'.ACCKEY_ACT_PREVIEW.', T_('Preview'),
                          'SUBMITBUTTONX', 'action', T_('Update'),
                              array( 'accesskey' => ACCKEY_ACT_EXECUTE ),
                          ) );
   $form->add_empty_row();

   $form->echo_string(1);

   echo "</center>\n";

   end_page();
}//main


function make_folder_form_row(&$form, $name, $nr,
                              $bgred, $bggreen, $bgblue, $bgalpha, $fgred, $fggreen, $fgblue,
                              $onstatuspage)
{

   $fcol = RGBA($fgred, $fggreen, $fgblue);

   $name_cel = '<td bgcolor="#' . blend_alpha($bgred, $bggreen, $bgblue, $bgalpha) . '">';
   if( empty($name) )
      $name_cel.= "<font color=\"#$fcol\">" . T_('Folder name') . '</font></td>';
   else
      $name_cel.= "<a style=\"color:'#$fcol'\" href=\"list_messages.php?folder=$nr\">" .
                     make_html_safe($name) . '</a></td>';


   $array = array( 'OWNHTML', $name_cel,
                   'TEXTINPUT', "folder$nr", 32, 32, $name,
                   'DESCRIPTION', T_('Background'),
                   'DESCRIPTION', T_('Red'),
                   'TEXTINPUT', "bgred$nr", 3, 5, "$bgred",
                   'DESCRIPTION', T_('Green'),
                   'TEXTINPUT', "bggreen$nr", 3, 5, "$bggreen",
                   'DESCRIPTION', T_('Blue'),
                   'TEXTINPUT', "bgblue$nr", 3, 5, "$bgblue",
                   'DESCRIPTION', T_('Alpha'),
                   'TEXTINPUT', "bgalpha$nr", 3, 5, "$bgalpha" );

   $form->add_row( $array );

   if( ConfigPages::is_system_status_folder($nr) || $nr >= USER_FOLDERS )
      $array = array( 'TAB',
                      'CHECKBOX', "onstatuspage$nr", 't',
                      T_('Show on status page'), $onstatuspage );
   else
      $array = array( 'OWNHTML', '<td colspan=2></td>' );

   array_push( $array, 'DESCRIPTION', T_('Foreground'),
               'DESCRIPTION', T_('Red'),
               'TEXTINPUT', "fgred$nr", 3, 5, "$fgred",
               'DESCRIPTION', T_('Green'),
               'TEXTINPUT', "fggreen$nr", 3, 5, "$fggreen",
               'DESCRIPTION', T_('Blue'),
               'TEXTINPUT', "fgblue$nr", 3, 5, "$fgblue");

   $form->add_row( $array );
   $form->add_row( array('SPACE'));
}//make_folder_form_row

?>
